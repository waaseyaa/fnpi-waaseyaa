<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Entity\Page;
use App\Pages\PageSeeder;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Waaseyaa\Database\DBALDatabase;
use Waaseyaa\Entity\EntityType;
use Waaseyaa\Entity\Repository\EntityRepositoryInterface;
use Waaseyaa\EntityStorage\Connection\SingleConnectionResolver;
use Waaseyaa\EntityStorage\Driver\RevisionableStorageDriver;
use Waaseyaa\EntityStorage\Driver\SqlStorageDriver;
use Waaseyaa\EntityStorage\EntityRepository;
use Waaseyaa\EntityStorage\SqlSchemaHandler;

/**
 * Test helper: an in-memory `page` repository, schema-built and seeded with the
 * four published pages, wired exactly like the kernel wires a revisionable
 * entity (base + revision tables, the published-revision pointer column, the
 * revision driver). Lets the page controller render real published revisions
 * under test without a full kernel boot.
 */
final class SeededPages
{
    public static function repository(): EntityRepositoryInterface
    {
        $repository = self::emptyRepository();
        new PageSeeder($repository)->seed();

        return $repository;
    }

    /**
     * The same wiring with nothing seeded: a fresh database where no page (and
     * so no published revision) exists yet — what app:ingest-knowledge sees
     * when it runs before app:seed-pages.
     */
    public static function emptyRepository(): EntityRepositoryInterface
    {
        $db = DBALDatabase::createSqlite();

        $entityType = new EntityType(
            id: 'page',
            label: 'Page',
            class: Page::class,
            keys: ['id' => 'id', 'uuid' => 'uuid', 'label' => 'title', 'revision' => 'revision_id'],
            revisionable: true,
            revisionDefault: true,
        );

        $handler = new SqlSchemaHandler($entityType, $db);
        $handler->ensureTable();
        $handler->ensureRevisionTable();

        $resolver = new SingleConnectionResolver($db);

        return new EntityRepository(
            $entityType,
            new SqlStorageDriver($resolver),
            new EventDispatcher(),
            new RevisionableStorageDriver($resolver, $entityType),
            $db,
        );
    }
}
