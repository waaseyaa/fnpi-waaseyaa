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
use Waaseyaa\EntityStorage\SqlSchemaHandler;

/**
 * PillarService's peer-language read/write surface — the methods the Identity
 * panel and the knowledge-base ingestion (bilingual RAG) rely on:
 * saveTranslation upserts the Anishinaabemowin peer, getTranslation reads it
 * back, and listTranslationHistory is the independent per-language timeline.
 */
final class PillarTranslationServiceTest extends TestCase
{
    private PillarService $service;

    protected function setUp(): void
    {
        $db = DBALDatabase::createSqlite();

        $entityType = new EntityType(
            id: 'identity_pillar',
            label: 'Identity pillar',
            class: Pillar::class,
            keys: [
                'id' => 'id', 'uuid' => 'uuid', 'label' => 'title', 'revision' => 'revision_id',
                'langcode' => 'langcode', 'default_langcode' => 'default_langcode',
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
        $repo = new EntityRepository(
            $entityType,
            new SqlStorageDriver($resolver),
            new EventDispatcher(),
            new RevisionableStorageDriver($resolver, $entityType),
            $db,
        );

        $etm = new EntityTypeManager(
            new EventDispatcher(),
            repositoryFactory: fn(string $id, $def): EntityRepository => $repo,
        );
        $etm->registerEntityType($entityType);

        $this->service = new PillarService($etm);
        $this->service->createPillar(
            pid: 'moat', section: 'positioning', title: 'Differentiation / moat', nowLabel: 'Now',
            body: 'Four layers, only FNPI has all four.', isQuote: false, decideLabel: 'Keep', decision: '',
            status: 'defined', notes: '', pills: [], isFull: false, sortOrder: 7, editorUid: 0,
            editorLabel: 'Matthew', updatedAt: '2026-06-07T00:38:04Z', revisionLog: 'Imported',
        );
    }

    #[Test]
    public function a_translation_round_trips_through_the_service(): void
    {
        // No translation yet.
        $this->assertNull($this->service->getTranslation('moat', 'oj'));

        $stamp = $this->service->saveTranslation('moat', 'oj', 'Maamawichigewin', 'Niiwin apatchitchiganan.', 3, 'Russell Jones');
        $this->assertNotNull($stamp);
        $this->assertSame(1, $stamp['revision']);

        // getTranslation reads the Anishinaabemowin peer back (what ingestion uses).
        $oj = $this->service->getTranslation('moat', 'oj');
        $this->assertInstanceOf(Pillar::class, $oj);
        $this->assertSame('Maamawichigewin', $oj->getTitle());
        $this->assertSame('Niiwin apatchitchiganan.', $oj->getBody());

        // The English pillar is untouched (non-translatable workspace fields stay).
        $en = $this->service->findByPid('moat');
        $this->assertSame('Differentiation / moat', $en?->getTitle());
        $this->assertSame('defined', $en?->getStatus());

        // Independent per-language history.
        $this->assertCount(1, $this->service->listTranslationHistory('moat', 'oj'));
        $this->service->saveTranslation('moat', 'oj', 'Maamawichigewin v2', 'oj v2', 3, 'Russell Jones');
        $this->assertCount(2, $this->service->listTranslationHistory('moat', 'oj'));
        $this->assertSame('Maamawichigewin v2', $this->service->getTranslation('moat', 'oj')?->getTitle());
    }

    #[Test]
    public function an_unsupported_language_is_rejected(): void
    {
        $this->assertFalse($this->service->isTranslationLangcode('fr'));
        $this->assertNull($this->service->saveTranslation('moat', 'fr', 'x', 'y', 0, ''));
        $this->assertNull($this->service->getTranslation('moat', 'fr'));
        $this->assertSame([], $this->service->listTranslationHistory('moat', 'fr'));
    }
}
