<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Entity\Page;
use App\Pages\PagesService;
use App\Pages\PageSeeder;
use App\Provider\AnokiiServiceProvider;
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
use Waaseyaa\Routing\WaaseyaaRouter;

/**
 * Anokii Pages increment 2: the workspace editor for public `page` entities.
 * This suite drives PagesService over a real seeded `page` repository and proves
 * the draft / publish / rollback contract on the framework's two pointers
 * (revision_id = draft, published_revision_id = live view):
 *
 *   - a fresh save advances the DRAFT without moving the live view,
 *   - publish points the live view at the draft,
 *   - rollback points the live view at an older revision (the draft untouched),
 *   - and the public render (loadPublishedRevision) tracks the live pointer.
 */
final class PagesToolTest extends TestCase
{
    private PagesService $service;
    private EntityRepository $repo;

    protected function setUp(): void
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
        $this->repo = new EntityRepository(
            $entityType,
            new SqlStorageDriver($resolver),
            new EventDispatcher(),
            new RevisionableStorageDriver($resolver, $entityType),
            $db,
        );
        new PageSeeder($this->repo)->seed();

        $etm = new EntityTypeManager(
            new EventDispatcher(),
            repositoryFactory: fn(string $id, $def): EntityRepository => $this->repo,
        );
        $etm->registerEntityType($entityType);

        $this->service = new PagesService($etm);
    }

    private function homeId(): string
    {
        foreach ($this->service->listPages() as $row) {
            if ($row['path'] === '/') {
                return $row['id'];
            }
        }
        self::fail('home page not seeded');
    }

    #[Test]
    public function seeded_pages_are_listed_and_live(): void
    {
        $rows = $this->service->listPages();
        $this->assertGreaterThanOrEqual(4, count($rows));

        $paths = array_column($rows, 'path');
        foreach (['/', '/technology', '/how-it-works', '/contact'] as $p) {
            $this->assertContains($p, $paths);
        }
        foreach ($rows as $row) {
            $this->assertTrue($row['is_live'], $row['path'] . ' is published by the seeder');
            $this->assertFalse($row['has_unpublished_changes'], $row['path'] . ' has no draft ahead at seed time');
            $this->assertSame(1, $row['published_rev']);
        }
    }

    #[Test]
    public function saving_a_draft_advances_the_draft_without_moving_the_live_view(): void
    {
        $id = $this->homeId();

        $draftRev = $this->service->saveDraft($id, ['title' => 'New Home Title'], 'Russell');
        $this->assertSame(2, $draftRev, 'a save creates a new revision');

        // Live view is still revision 1 (the published pointer did not move).
        $this->assertSame(1, $this->service->publishedRevisionId($id));
        $published = $this->repo->loadPublishedRevision($id);
        $this->assertInstanceOf(Page::class, $published);
        $this->assertNotSame('New Home Title', $published->getTitle(), 'the live page still shows the old title');

        // The list reflects the draft-ahead state.
        $row = $this->rowFor($id);
        $this->assertTrue($row['has_unpublished_changes']);
        $this->assertSame(2, $row['draft_rev']);
        $this->assertSame(1, $row['published_rev']);
    }

    #[Test]
    public function publish_makes_the_draft_live(): void
    {
        $id = $this->homeId();
        $this->service->saveDraft($id, ['title' => 'Published Home'], 'Russell');

        $live = $this->service->publish($id);
        $this->assertSame(2, $live);
        $this->assertSame(2, $this->service->publishedRevisionId($id));

        $published = $this->repo->loadPublishedRevision($id);
        $this->assertInstanceOf(Page::class, $published);
        $this->assertSame('Published Home', $published->getTitle(), 'the live page now shows the new title');

        $row = $this->rowFor($id);
        $this->assertFalse($row['has_unpublished_changes'], 'draft == published after publish');
    }

    #[Test]
    public function rollback_reverts_the_live_view_but_keeps_the_draft(): void
    {
        $id = $this->homeId();
        // rev 2 = new title, published.
        $this->service->saveDraft($id, ['title' => 'Version Two'], 'Russell');
        $this->service->publish($id);
        // rev 3 = a further draft, also published.
        $this->service->saveDraft($id, ['title' => 'Version Three'], 'Russell');
        $this->service->publish($id);
        $this->assertSame(3, $this->service->publishedRevisionId($id));

        // Roll the LIVE view back to revision 2 (an instant revert).
        $live = $this->service->rollbackPublished($id, 2);
        $this->assertSame(2, $live);
        $this->assertSame('Version Two', $this->repo->loadPublishedRevision($id)?->getTitle());

        // The draft (current revision) is untouched — still the latest content.
        $this->assertSame('Version Three', $this->service->find($id)?->getTitle());

        // History flags: the live tag is on rev 2, the draft tag on rev 3.
        $history = $this->service->listHistory($id);
        $byRev = [];
        foreach ($history as $h) {
            $byRev[$h['rev']] = $h;
        }
        $this->assertTrue($byRev[2]['is_published']);
        $this->assertTrue($byRev[3]['is_draft']);
        $this->assertFalse($byRev[3]['is_published']);
    }

    #[Test]
    public function block_text_edits_persist_and_structured_fields_are_preserved(): void
    {
        $id = $this->homeId();
        $original = $this->service->find($id);
        $this->assertInstanceOf(Page::class, $original);
        $blocks = $original->getBlocks();
        $this->assertNotEmpty($blocks, 'home page has seeded blocks');

        // Edit the first block's first string field; keep the rest verbatim.
        $stringKey = null;
        foreach ($blocks[0] as $k => $v) {
            if ($k !== 'type' && is_string($v)) {
                $stringKey = $k;
                break;
            }
        }
        $this->assertNotNull($stringKey, 'the first block has an editable string field');

        $edited = $blocks;
        $edited[0][$stringKey] = 'EDITED COPY';
        $this->service->saveDraft($id, ['blocks' => $edited], 'Russell');

        $reloaded = $this->service->find($id)?->getBlocks();
        $this->assertNotNull($reloaded);
        $this->assertSame('EDITED COPY', $reloaded[0][$stringKey]);
        // The block type and field count are unchanged (nothing dropped).
        $this->assertSame($blocks[0]['type'], $reloaded[0]['type']);
        $this->assertSame(count($blocks[0]), count($reloaded[0]));
        $this->assertCount(count($blocks), $reloaded);
    }

    #[Test]
    public function pages_routes_are_registered(): void
    {
        $router = new WaaseyaaRouter();
        new AnokiiServiceProvider()->routes($router);

        $this->assertSame('anokii.pages', $router->match('/admin/anokii/pages')['_route'] ?? null);
        $this->assertSame('anokii.pages.edit', $router->match('/admin/anokii/pages/3')['_route'] ?? null);
        $this->assertSame('anokii.pages.preview', $router->match('/admin/anokii/pages/3/preview')['_route'] ?? null);
    }

    /** @return array{id:string, path:string, title:string, draft_rev:int, published_rev:?int, has_unpublished_changes:bool, is_live:bool} */
    private function rowFor(string $id): array
    {
        foreach ($this->service->listPages() as $row) {
            if ($row['id'] === $id) {
                return $row;
            }
        }
        self::fail('row not found for ' . $id);
    }
}
