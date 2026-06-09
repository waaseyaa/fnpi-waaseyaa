<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\CoIntelligence\AgentTools;
use App\Entity\Page;
use App\Pages\PageSeeder;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Waaseyaa\Access\AccountInterface;
use Waaseyaa\Database\DBALDatabase;
use Waaseyaa\Entity\EntityType;
use Waaseyaa\Entity\EntityTypeManager;
use Waaseyaa\EntityStorage\Connection\SingleConnectionResolver;
use Waaseyaa\EntityStorage\Driver\RevisionableStorageDriver;
use Waaseyaa\EntityStorage\Driver\SqlStorageDriver;
use Waaseyaa\EntityStorage\EntityRepository;
use Waaseyaa\EntityStorage\SqlSchemaHandler;

/**
 * Anokii Pages increment 3, the guardrail proof. When Co-Intelligence drafts
 * page copy from the pillars, the change must land as a DRAFT revision and the
 * live (published) view must NOT move — the agent can never publish. This runs
 * the agent's actual write tool (AgentTools::execute, the same path
 * applyDecision takes on approval) against a real `page` repository and asserts:
 *
 *   - an entity.update on a page creates a new revision (the draft advances),
 *   - the published-revision pointer is unchanged (the live site does not move),
 *   - bilingual block fields (h1 + h1_oj) persist into the draft,
 *   - and the agent's tool surface exposes no publish/rollback-of-live verb.
 *
 * The model's copy generation runs against the live Anthropic model (gated by
 * ANOKII_AGENT_TOOLS) and is verified on the Pi; this proves the mechanism the
 * approval applies, deterministically.
 */
final class AgentPageDraftTest extends TestCase
{
    private EntityRepository $pages;
    private AgentTools $tools;

    protected function setUp(): void
    {
        $db = DBALDatabase::createSqlite();

        $pageType = new EntityType(
            id: 'page',
            label: 'Page',
            class: Page::class,
            keys: ['id' => 'id', 'uuid' => 'uuid', 'label' => 'title', 'revision' => 'revision_id'],
            revisionable: true,
            revisionDefault: true,
        );

        $handler = new SqlSchemaHandler($pageType, $db);
        $handler->ensureTable();
        $handler->ensureRevisionTable();

        $resolver = new SingleConnectionResolver($db);
        $this->pages = new EntityRepository(
            $pageType,
            new SqlStorageDriver($resolver),
            new EventDispatcher(),
            new RevisionableStorageDriver($resolver, $pageType),
            $db,
        );
        new PageSeeder($this->pages)->seed();

        $etm = new EntityTypeManager(
            new EventDispatcher(),
            repositoryFactory: fn(string $id, $def): EntityRepository => $this->pages,
        );
        $etm->registerEntityType($pageType);

        $this->tools = new AgentTools($etm);
    }

    private function account(): AccountInterface
    {
        return new class implements AccountInterface {
            public function id(): int|string
            {
                return 1;
            }

            public function hasPermission(string $permission): bool
            {
                return true;
            }

            public function getRoles(): array
            {
                return ['administrator'];
            }

            public function isAuthenticated(): bool
            {
                return true;
            }
        };
    }

    private function homeId(): string
    {
        foreach ($this->pages->findBy(['path' => '/']) as $page) {
            if ($page instanceof Page) {
                return (string) $page->id();
            }
        }
        self::fail('home page not seeded');
    }

    #[Test]
    public function an_agent_page_edit_drafts_bilingually_and_never_publishes(): void
    {
        $id = $this->homeId();

        $before = $this->pages->find($id);
        $this->assertInstanceOf(Page::class, $before);
        $draftBefore = (int) $before->getRevisionId();
        $publishedBefore = $this->pages->loadPublishedRevision($id);
        $this->assertInstanceOf(Page::class, $publishedBefore);
        $publishedRevBefore = (int) $publishedBefore->getRevisionId();

        // The agent proposes (and, on approval, applies) an entity.update on the
        // page with bilingual hero copy drafted from the moat pillar.
        $result = $this->tools->execute('entity.update', [
            'entity_type' => 'page',
            'id' => $id,
            'values' => [
                'blocks' => [
                    ['type' => 'hero', 'h1' => 'Sourcing and Sovereignty', 'h1_oj' => 'Maamawichigewin'],
                ],
            ],
        ], $this->account());

        $this->assertFalse($result->isError, 'the agent may draft a page edit');

        // A new DRAFT revision was created...
        $after = $this->pages->find($id);
        $this->assertInstanceOf(Page::class, $after);
        $this->assertGreaterThan($draftBefore, (int) $after->getRevisionId(), 'a new draft revision is created');

        // ...but the LIVE (published) view did not move: the agent cannot publish.
        $publishedAfter = $this->pages->loadPublishedRevision($id);
        $this->assertInstanceOf(Page::class, $publishedAfter);
        $this->assertSame($publishedRevBefore, (int) $publishedAfter->getRevisionId(), 'the live page is unchanged (draft only)');
        $this->assertNotSame('Sourcing and Sovereignty', $publishedAfter->getBlocks()[0]['h1'] ?? null, 'the live page still shows the published copy');

        // Bilingual block fields persisted into the draft.
        $draftBlocks = $after->getBlocks();
        $this->assertSame('Sourcing and Sovereignty', $draftBlocks[0]['h1'] ?? null);
        $this->assertSame('Maamawichigewin', $draftBlocks[0]['h1_oj'] ?? null, 'the Anishinaabemowin field is drafted beside the English');
    }

    #[Test]
    public function the_agent_tool_surface_has_no_publish_verb(): void
    {
        // The agent can create/update (draft), but there is no tool that moves a
        // page's published pointer — publishing stays a human action.
        foreach (AgentTools::MUTATING as $tool) {
            $this->assertStringNotContainsString('publish', $tool, 'no publish tool may exist');
        }
        $this->assertNotContains('entity.set_published_revision', AgentTools::TOOLS);
    }
}
