<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\CoIntelligence\ChatSchema;
use App\CoIntelligence\DocChunkRepository;
use App\Command\IngestKnowledgeCommand;
use App\Entity\Page;
use App\Entity\Pillar;
use App\Identity\PillarService;
use App\Pages\PublishedPageRenderer;
use App\Tests\Support\SeededPages;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Waaseyaa\CLI\CliIO;
use Waaseyaa\Database\DBALDatabase;
use Waaseyaa\Entity\EntityType;
use Waaseyaa\Entity\EntityTypeManager;
use Waaseyaa\Entity\Repository\EntityRepositoryInterface;
use Waaseyaa\EntityStorage\Connection\SingleConnectionResolver;
use Waaseyaa\EntityStorage\Driver\RevisionableStorageDriver;
use Waaseyaa\EntityStorage\Driver\SqlStorageDriver;
use Waaseyaa\EntityStorage\EntityRepository;
use Waaseyaa\EntityStorage\SqlSchemaHandler;
use Waaseyaa\SSR\SsrServiceProvider;

/**
 * app:ingest-knowledge builds the "live site copy" knowledge from the PUBLISHED
 * revision of the public `page` entities — the same source the public routes
 * render (PublishedPageRenderer). This pins the contract that protects the RAG
 * index across copy changes: published copy is indexed, draft revisions never
 * leak into Co-Intelligence answers, and the structural block markers never
 * reach the knowledge text.
 */
final class IngestKnowledgeTest extends TestCase
{
    private EntityRepositoryInterface $pages;
    private DocChunkRepository $chunks;
    private IngestKnowledgeCommand $command;

    public static function setUpBeforeClass(): void
    {
        $provider = new SsrServiceProvider();
        $provider->setKernelContext(dirname(__DIR__, 2), [], []);
        $provider->boot();
    }

    protected function setUp(): void
    {
        $twig = SsrServiceProvider::getTwigEnvironment();
        self::assertNotNull($twig);

        $this->pages = SeededPages::repository();

        $db = DBALDatabase::createSqlite();
        new ChatSchema($db)->ensure();
        $this->chunks = new DocChunkRepository($db);

        // An empty knowledge dir: the bundled-docs source contributes nothing,
        // keeping the assertions focused on the page-entity source. An empty
        // pillar repository does the same for the pillar source.
        $this->command = new IngestKnowledgeCommand(
            $this->chunks,
            new PillarService($this->emptyPillarManager()),
            new PublishedPageRenderer($this->pages, $twig),
            sys_get_temp_dir() . '/fnpi-ingest-test-no-docs',
        );
    }

    #[Test]
    public function ingestion_indexes_the_published_revision_of_every_public_page(): void
    {
        $exit = $this->command->run($this->io());
        $this->assertSame(0, $exit);

        $rows = $this->chunks->all();
        $bySource = [];
        foreach ($rows as $row) {
            $bySource[$row['source_url']][] = $row;
        }

        $this->assertSame(
            ['/', '/contact', '/how-it-works', '/technology'],
            array_keys((function (array $a) { ksort($a); return $a; })($bySource)),
            'Exactly the four public pages are indexed as site copy.',
        );

        // A known published string per page proves the copy came through
        // (headings become the chunk heading, body copy the chunk text).
        $all = $this->corpus($rows);
        $this->assertStringContainsString('Three lanes, one qualification', $all);
        $this->assertStringContainsString('Owned, not rented', $all);
        $this->assertStringContainsString('A ladder, not a leap.', $all);
        $this->assertStringContainsString('Tell us what your Nation needs.', $all);

        $this->assertSame('FNPI Home (public site)', $bySource['/'][0]['title']);
    }

    #[Test]
    public function draft_revisions_never_reach_the_knowledge_index(): void
    {
        // Save a NEW draft revision of the homepage with sentinel copy, exactly
        // as the Pages tool does: the working revision moves ahead, the
        // published pointer stays where it was.
        $page = null;
        foreach ($this->pages->findBy(['path' => '/']) as $candidate) {
            if ($candidate instanceof Page) {
                $page = $candidate;
                break;
            }
        }
        $this->assertNotNull($page);

        $blocks = $page->getBlocks();
        $blocks[0]['h1'] = 'UNPUBLISHED SENTINEL COPY';
        $page->setBlocks($blocks);
        $page->recordEdit('Test draft, never published');
        $this->pages->save($page);

        $exit = $this->command->run($this->io());
        $this->assertSame(0, $exit);

        $all = $this->corpus($this->chunks->all());
        $this->assertStringNotContainsString('UNPUBLISHED SENTINEL COPY', $all, 'Draft copy must never reach the RAG index.');
        $this->assertStringContainsString('Sourcing Solutions', $all, 'The published hero copy is what gets indexed.');
    }

    #[Test]
    public function a_missing_published_page_aborts_without_pruning_the_index(): void
    {
        // A healthy ingest first, so the index holds the live page knowledge.
        $this->assertSame(0, $this->command->run($this->io()));
        $before = $this->chunks->all();
        $this->assertNotEmpty($before);

        // The same chunk store, but a fresh page database where nothing is
        // seeded or published — ingest must refuse to write rather than let
        // the prune wipe the live page knowledge while reporting success.
        $twig = SsrServiceProvider::getTwigEnvironment();
        self::assertNotNull($twig);
        $broken = new IngestKnowledgeCommand(
            $this->chunks,
            new PillarService($this->emptyPillarManager()),
            new PublishedPageRenderer(SeededPages::emptyRepository(), $twig),
            sys_get_temp_dir() . '/fnpi-ingest-test-no-docs',
        );

        $this->assertSame(1, $broken->run($this->io()));
        $this->assertSame($before, $this->chunks->all(), 'An ingest with missing published pages must not touch the index.');
    }

    #[Test]
    public function block_markers_never_leak_into_knowledge_text(): void
    {
        $this->command->run($this->io());

        foreach ($this->chunks->all() as $row) {
            $this->assertStringNotContainsString('blk:', $row['heading'] . ' ' . $row['text'], 'The <!-- blk:type --> structure markers are not copy.');
        }
    }

    /**
     * Everything the index would surface to the model: headings + passage text.
     *
     * @param list<array{chunk_key:string,source_url:string,title:string,heading:string,text:string}> $rows
     */
    private function corpus(array $rows): string
    {
        return implode("\n", array_merge(array_column($rows, 'heading'), array_column($rows, 'text')));
    }

    private function emptyPillarManager(): EntityTypeManager
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

        return $etm;
    }

    /** A silent CliIO: real options for a non-dry-run, pruning ingest. */
    private function io(): CliIO
    {
        return new class implements CliIO {
            public function write(string $text): void {}

            public function writeln(string $text = ''): void {}

            public function argument(string $name): string|int|float|bool|array|null
            {
                return null;
            }

            public function option(string $name): string|int|float|bool|array|null
            {
                return $name === 'prune' ? true : null;
            }

            public function arguments(): array
            {
                return [];
            }

            public function options(): array
            {
                return ['prune' => true];
            }

            public function error(string $line): void {}

            public function ask(string $question, ?string $default = null): ?string
            {
                return $default;
            }

            public function confirm(string $question, bool $default = false): bool
            {
                return $default;
            }

            public function isVerbose(): bool
            {
                return false;
            }

            public function isInteractive(): bool
            {
                return false;
            }
        };
    }
}
