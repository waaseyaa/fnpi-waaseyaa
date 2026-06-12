<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Entity\Pillar;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Uid\Uuid;
use Waaseyaa\Database\DBALDatabase;
use Waaseyaa\Entity\EntityType;
use Waaseyaa\EntityStorage\Connection\SingleConnectionResolver;
use Waaseyaa\EntityStorage\Driver\RevisionableStorageDriver;
use Waaseyaa\EntityStorage\Driver\SqlStorageDriver;
use Waaseyaa\EntityStorage\EntityRepository;
use Waaseyaa\EntityStorage\SqlSchemaHandler;

/**
 * Phase 2 (Anishinaabemowin): the `identity_pillar` becomes a two-axis entity —
 * English is the default-language base row, Anishinaabemowin (`oj`) a true peer
 * `(id, langcode)` row with its OWN independent revision history. This suite
 * dogfoods the framework's unified two-axis save (`saveTranslation`, alpha.198)
 * against fnpi's real `Pillar` entity and proves the guardrails:
 *
 *   - English content and its single-axis history are untouched (byte-for-byte).
 *   - The default-langcode listing returns one row per pillar (no peer-row
 *     duplicates leaking into the workspace).
 *   - `oj` is independently editable with its own per-language revision timeline.
 */
final class IdentityTranslationTest extends TestCase
{
    private const EN = 'en';
    private const OJ = 'oj';

    private EntityRepository $repo;

    protected function setUp(): void
    {
        $db = DBALDatabase::createSqlite();

        // The translatable shape: the single-axis pillar widened with the
        // langcode / default_langcode keys + translatable:true (what
        // config/entity-types.php declares for the live tool).
        $entityType = new EntityType(
            id: 'identity_pillar',
            label: 'Identity pillar',
            class: Pillar::class,
            keys: [
                'id' => 'id',
                'uuid' => 'uuid',
                'label' => 'title',
                'revision' => 'revision_id',
                'langcode' => 'langcode',
                'default_langcode' => 'default_langcode',
            ],
            revisionable: true,
            revisionDefault: true,
            translatable: true,
        );

        $handler = new SqlSchemaHandler($entityType, $db);
        $handler->ensureTable();
        $handler->ensureRevisionTable();
        $handler->ensureTranslationRevisionTable();

        $resolver = new SingleConnectionResolver($db);
        $this->repo = new EntityRepository(
            $entityType,
            new SqlStorageDriver($resolver),
            new EventDispatcher(),
            new RevisionableStorageDriver($resolver, $entityType),
            $db,
        );
    }

    private function seedEnglishPillar(string $pid, string $title, string $body, string $status): Pillar
    {
        $pillar = new Pillar();
        $pillar->set('uuid', Uuid::v4()->toRfc4122());
        $pillar->fill(
            pid: $pid, section: 'positioning', title: $title, nowLabel: 'Now', body: $body,
            isQuote: false, decideLabel: 'Keep', decision: '', status: $status, notes: '',
            pills: [], isFull: false, sortOrder: 7, editorLabel: 'Matthew',
            updatedAt: '2026-06-07T00:38:04Z',
        );
        $pillar->recordEdit('Imported from prototype');
        $pillar->enforceIsNew();
        $this->repo->save($pillar);

        return $pillar;
    }

    #[Test]
    public function english_is_the_default_row_and_anishinaabemowin_is_a_peer(): void
    {
        $en = $this->seedEnglishPillar('moat', 'Differentiation / moat', 'Four layers, only FNPI has all four.', 'defined');
        $id = (string) $en->id();
        $this->assertSame(1, (int) $id, 'translatable entity gets an assigned id');

        // English single-axis history is recorded as before (unchanged path).
        $this->assertCount(1, $this->repo->listRevisions($id));

        // Add the Anishinaabemowin peer: its own (id, langcode) row + revision.
        $rev = $this->repo->saveTranslation($id, self::OJ, [
            'title' => 'Maamawichigewin',
            'body' => "Niiwin apatchitchiganan, FNPI eta kakina niiwin ayaawag.",
        ], 'Anishinaabemowin translation added');
        $this->assertSame(1, $rev);

        // The peer row carries the oj content; English is untouched.
        $oj = $this->repo->loadTranslation($id, self::OJ);
        $this->assertNotNull($oj);
        $this->assertSame('Maamawichigewin', $oj->label());
        $this->assertInstanceOf(Pillar::class, $oj);
        $this->assertSame("Niiwin apatchitchiganan, FNPI eta kakina niiwin ayaawag.", $oj->getBody());

        $enReloaded = $this->repo->find($id, self::EN);
        $this->assertNotNull($enReloaded);
        $this->assertSame('Differentiation / moat', $enReloaded->label());
        $this->assertSame('defined', $enReloaded->getStatus(), 'non-translatable status stays on the English row');

        // find by langcode returns the right peer.
        $this->assertSame('Maamawichigewin', $this->repo->find($id, self::OJ)?->label());
    }

    #[Test]
    public function default_langcode_listing_has_no_peer_duplicates(): void
    {
        $a = $this->seedEnglishPillar('purpose', 'Purpose', 'Why FNPI exists.', 'defined');
        $b = $this->seedEnglishPillar('moat', 'Moat', 'Four layers.', 'work');
        $this->repo->saveTranslation((string) $a->id(), self::OJ, ['title' => 'Wenji-ayaamagak'], 'oj');
        $this->repo->saveTranslation((string) $b->id(), self::OJ, ['title' => 'Maamawichigewin'], 'oj');

        // The workspace lists the canonical (English) rows only — the two peer
        // rows must not show up as extra pillars.
        $canonical = $this->repo->findBy(['langcode' => self::EN]);
        $this->assertCount(2, $canonical);
        foreach ($canonical as $p) {
            $this->assertInstanceOf(Pillar::class, $p);
            $this->assertNotSame('', $p->getPid(), 'canonical rows carry the pid handle');
        }

        // The peer rows exist in the table but are language overlays (no pid).
        $all = $this->repo->findBy([]);
        $this->assertCount(4, $all, 'two English rows + two Anishinaabemowin peers');
    }

    #[Test]
    public function languages_revise_independently(): void
    {
        $en = $this->seedEnglishPillar('moat', 'Moat', 'EN body.', 'defined');
        $id = (string) $en->id();

        // Edit English twice more through the ordinary save path.
        foreach (['work', 'defined'] as $s) {
            $reloaded = $this->repo->find($id, self::EN);
            $this->assertInstanceOf(Pillar::class, $reloaded);
            $reloaded->setStatus($s)->setEditorLabel('Russell')->recordEdit('Status set to ' . $s);
            $this->repo->save($reloaded);
        }
        // English single-axis history: 1 create + 2 edits.
        $this->assertCount(3, $this->repo->listRevisions($id));

        // Anishinaabemowin edited three times — its OWN sequence 1,2,3,
        // independent of English's count.
        $this->assertSame(1, $this->repo->saveTranslation($id, self::OJ, ['title' => 'Ma / v1'], 'oj v1'));
        $this->assertSame(2, $this->repo->saveTranslation($id, self::OJ, ['title' => 'Maa / v2'], 'oj v2'));
        $this->assertSame(3, $this->repo->saveTranslation($id, self::OJ, ['title' => 'Maamawichigewin'], 'oj v3'));

        $this->assertCount(3, $this->repo->listTranslationRevisions($id, self::OJ));
        // English history count is unchanged by the oj edits.
        $this->assertCount(3, $this->repo->listRevisions($id));

        // The current oj value is the latest; an old oj revision recoverable.
        $this->assertSame('Maamawichigewin', $this->repo->loadTranslation($id, self::OJ)?->label());
        $this->assertSame('Ma / v1', $this->repo->loadTranslationRevision($id, self::OJ, 1)?->label());

        // Languages enumerated.
        $this->assertSame([self::OJ], $this->repo->translationLangcodes($id));
    }
}
