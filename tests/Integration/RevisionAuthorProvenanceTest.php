<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Entity\Pillar;
use App\Identity\PillarService;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Waaseyaa\Database\DBALDatabase;
use Waaseyaa\Entity\EntityType;
use Waaseyaa\Entity\EntityTypeManager;
use Waaseyaa\EntityStorage\Connection\SingleConnectionResolver;
use Waaseyaa\EntityStorage\Driver\RevisionableStorageDriver;
use Waaseyaa\EntityStorage\Driver\SqlStorageDriver;
use Waaseyaa\EntityStorage\EntityRepository;
use Waaseyaa\EntityStorage\SaveContext;
use Waaseyaa\EntityStorage\SqlSchemaHandler;

/**
 * Framework revision authorship (alpha.205+) replaces the app's editor_uid
 * writes. This pins the stage-2 adoption contract against real sqlite storage:
 *
 *   - a NEW save writes no editor_uid into _data (the app stopped writing it;
 *     only the editor_label display cache remains app data),
 *   - the acting account uid lands in revision_author (here stated explicitly
 *     via SaveContext::withActorUid — in a request the kernel's ambient
 *     account context supplies it) and reads back via revisionMetadata(),
 *   - OLD revisions, whose _data still carries the pre-upgrade editor_uid
 *     snapshot, keep their attribution: getEditorUid() falls back to the
 *     snapshot when revision_author is null (pre-upgrade rows are never
 *     backfilled), and prefers revision_author when it exists.
 */
final class RevisionAuthorProvenanceTest extends TestCase
{
    private EntityRepository $repo;
    private PillarService $service;

    protected function setUp(): void
    {
        $db = DBALDatabase::createSqlite();

        $type = new EntityType(
            id: 'identity_pillar',
            label: 'Identity pillar',
            class: Pillar::class,
            keys: ['id' => 'id', 'uuid' => 'uuid', 'label' => 'title', 'revision' => 'revision_id'],
            revisionable: true,
            revisionDefault: true,
        );

        $handler = new SqlSchemaHandler($type, $db);
        $handler->ensureTable();
        $handler->ensureRevisionTable();

        $resolver = new SingleConnectionResolver($db);
        $this->repo = new EntityRepository(
            $type,
            new SqlStorageDriver($resolver),
            new EventDispatcher(),
            new RevisionableStorageDriver($resolver, $type),
            $db,
        );

        $etm = new EntityTypeManager(
            new EventDispatcher(),
            repositoryFactory: fn(string $id, $def): EntityRepository => $this->repo,
        );
        $etm->registerEntityType($type);

        $this->service = new PillarService($etm);
        $this->service->createPillar(
            pid: 'moat', section: 'positioning', title: 'Differentiation / moat', nowLabel: 'Now',
            body: 'Four layers.', isQuote: false, decideLabel: 'Keep', decision: '',
            status: 'defined', notes: '', pills: [], isFull: false, sortOrder: 7,
            editorLabel: 'Matthew', updatedAt: '2026-06-07T00:38:04Z', revisionLog: 'Imported',
        );
    }

    #[Test]
    public function a_new_save_records_revision_author_and_writes_no_editor_uid(): void
    {
        $pillar = $this->service->findByPid('moat');
        $this->assertNotNull($pillar);

        // The acting account: in a request the framework resolves it from the
        // ambient session context; stated explicitly here (same resolution).
        $pillar->setStatus('work')->setEditorLabel('Russell')->recordEdit('Status set to work');
        $this->repo->save($pillar, context: SaveContext::default()->withActorUid(3));

        $revisions = $this->repo->listRevisions((string) $pillar->id());
        $this->assertCount(2, $revisions);

        $latest = $revisions[0];
        $this->assertInstanceOf(Pillar::class, $latest);
        $this->assertSame('work', $latest->getStatus());

        // The uid is framework metadata now, not app field data.
        $this->assertSame(3, $latest->revisionMetadata()?->revisionAuthor);
        $this->assertNull($latest->get('editor_uid'), 'the app must not write editor_uid any more');
        $this->assertSame(3, $latest->getEditorUid(), 'getEditorUid() reads revision_author');

        // The display cache is still app data.
        $this->assertSame('Russell', $latest->getEditorLabel());
    }

    #[Test]
    public function a_save_without_acting_context_records_a_null_author(): void
    {
        // The seed save in setUp ran with no actor (CLI-like): SQL NULL, not 0.
        $revisions = $this->repo->listRevisions((string) $this->service->findByPid('moat')?->id());
        $this->assertCount(1, $revisions);
        $this->assertInstanceOf(Pillar::class, $revisions[0]);
        $this->assertNull($revisions[0]->revisionMetadata()?->revisionAuthor);
    }

    #[Test]
    public function old_history_keeps_attribution_via_the_editor_uid_snapshot_fallback(): void
    {
        // Simulate a pre-upgrade revision: editor_uid snapshotted into _data
        // by the old app code, saved with no acting context (author NULL —
        // exactly what every pre-alpha.205 revision row hydrates).
        $pillar = $this->service->findByPid('moat');
        $this->assertNotNull($pillar);
        $pillar->set('editor_uid', 7);
        $pillar->setEditorLabel('Matthew (legacy)');
        $pillar->recordEdit('Legacy-style edit');
        $this->repo->save($pillar);

        $legacy = $this->repo->listRevisions((string) $pillar->id())[0];
        $this->assertInstanceOf(Pillar::class, $legacy);
        $this->assertNull($legacy->revisionMetadata()?->revisionAuthor);
        $this->assertSame(7, $legacy->getEditorUid(), 'revision_author null -> fall back to the _data snapshot');

        // A later authored revision prefers revision_author over the stale
        // snapshot that rides along in the copied _data.
        $pillar = $this->repo->find((string) $pillar->id());
        $this->assertInstanceOf(Pillar::class, $pillar);
        $pillar->setStatus('gap')->setEditorLabel('Russell')->recordEdit('Status set to gap');
        $this->repo->save($pillar, context: SaveContext::default()->withActorUid(3));

        $authored = $this->repo->listRevisions((string) $pillar->id())[0];
        $this->assertInstanceOf(Pillar::class, $authored);
        $this->assertSame(3, $authored->getEditorUid(), 'revision_author wins when present');
    }

    #[Test]
    public function the_service_update_no_longer_writes_editor_uid(): void
    {
        $result = $this->service->update('moat', 'work', null, 'Russell');
        $this->assertNotNull($result);
        $this->assertSame('Russell', $result['editor_label']);

        $latest = $this->repo->listRevisions((string) $this->service->findByPid('moat')?->id())[0];
        $this->assertInstanceOf(Pillar::class, $latest);
        $this->assertNull($latest->get('editor_uid'));
        $this->assertSame('Russell', $latest->getEditorLabel());
    }
}
