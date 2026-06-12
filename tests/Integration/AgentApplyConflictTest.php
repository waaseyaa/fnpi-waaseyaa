<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\CoIntelligence\AgentConversation;
use App\CoIntelligence\AgentProposalRepository;
use App\CoIntelligence\AgentTools;
use App\CoIntelligence\ChatPromptBuilder;
use App\CoIntelligence\ChatSchema;
use App\CoIntelligence\ConversationRepository;
use App\Entity\Pillar;
use App\Identity\PillarService;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Waaseyaa\AI\Agent\Provider\MessageRequest;
use Waaseyaa\AI\Agent\Provider\MessageResponse;
use Waaseyaa\AI\Agent\Provider\ProviderInterface;
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
 * Optimistic locking in the dual-writer loop (alpha.207 adoption). The agent
 * drafts a proposal from a read; a human approves it later; the entity may
 * have moved in between. This pins, against real sqlite storage and the real
 * AgentTools write path:
 *
 *   - propose() records the entity's revision_id at draft time as the stored
 *     tool input's expected_revision_id (the canonical SC-002 recipe),
 *   - an approval whose expectation is STALE is refused: the structured
 *     revision_conflict surfaces as the user-facing CONFLICT_MESSAGE on the
 *     `applied` event, nothing is persisted, and the competing edit survives,
 *   - an approval whose expectation matches the head applies normally.
 */
final class AgentApplyConflictTest extends TestCase
{
    private EntityRepository $repo;
    private PillarService $pillars;
    private EntityTypeManager $etm;
    private AgentProposalRepository $proposals;
    private ConversationRepository $conversations;

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

        $this->etm = new EntityTypeManager(
            new EventDispatcher(),
            repositoryFactory: fn(string $id, $def): EntityRepository => $this->repo,
        );
        $this->etm->registerEntityType($type);

        $this->pillars = new PillarService($this->etm);
        $this->pillars->createPillar(
            pid: 'moat', section: 'positioning', title: 'Differentiation / moat', nowLabel: 'Now',
            body: 'Four layers.', isQuote: false, decideLabel: '', decision: '',
            status: 'defined', notes: '', pills: [], isFull: false, sortOrder: 7,
            editorLabel: 'Matthew', updatedAt: '2026-06-07T00:38:04Z', revisionLog: 'Imported',
        );

        new ChatSchema($db)->ensure();
        $this->proposals = new AgentProposalRepository($db);
        $this->proposals->ensure();
        $this->conversations = new ConversationRepository($db);
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

    /**
     * @param list<MessageResponse> $responses
     */
    private function agent(array $responses): AgentConversation
    {
        $provider = new class($responses) implements ProviderInterface {
            /** @param list<MessageResponse> $responses */
            public function __construct(private array $responses) {}

            public function sendMessage(MessageRequest $request): MessageResponse
            {
                $next = array_shift($this->responses);

                return $next ?? new MessageResponse([['type' => 'text', 'text' => 'Done.']], 'end_turn');
            }
        };

        return new AgentConversation(
            $provider,
            new AgentTools($this->etm),
            $this->proposals,
            $this->conversations,
            new ChatPromptBuilder(),
        );
    }

    private function pillarId(): string
    {
        $pillar = $this->pillars->findByPid('moat');
        $this->assertNotNull($pillar);

        return (string) $pillar->id();
    }

    private function headRevision(): int
    {
        $pillar = $this->pillars->findByPid('moat');
        $this->assertInstanceOf(Pillar::class, $pillar);

        return (int) $pillar->getRevisionId();
    }

    /**
     * @return array<string,mixed> a proposal row as applyDecision() receives it
     */
    private function proposalRow(int $conversationId, array $toolInput): array
    {
        return [
            'conversation_id' => $conversationId,
            'messages' => [['role' => 'user', 'content' => 'Set the moat pillar to needs-work.']],
            'prefix_results' => [],
            'tool_name' => 'entity.update',
            'tool_use_id' => 'tu_1',
            'tool_input' => $toolInput,
            'summary' => 'Update identity_pillar',
        ];
    }

    #[Test]
    public function propose_pins_the_proposal_to_the_revision_it_was_drafted_from(): void
    {
        $id = $this->pillarId();
        $head = $this->headRevision();

        // Turn 1: the model proposes an entity.update; turn never resumes
        // (the loop pauses on the proposal).
        $agent = $this->agent([
            new MessageResponse([
                ['type' => 'tool_use', 'id' => 'tu_1', 'name' => 'entity_update', 'input' => [
                    'entity_type' => 'identity_pillar',
                    'id' => $id,
                    'values' => ['status' => 'work'],
                ]],
            ], 'tool_use'),
        ]);

        $events = [];
        $cid = $this->conversations->create('Test', 'Russell');
        $agent->respond($cid, 'Set the moat pillar to needs-work.', $this->account(), 'Russell', function (string $event, array $payload) use (&$events): void {
            $events[$event][] = $payload;
        });

        $this->assertArrayHasKey('proposal', $events);
        $token = (string) ($events['proposal'][0]['token'] ?? '');
        $this->assertNotSame('', $token);

        $stored = $this->proposals->find($token);
        $this->assertNotNull($stored);
        $this->assertSame(
            $head,
            $stored['tool_input']['expected_revision_id'] ?? null,
            'the proposal must carry the revision_id the draft was built from',
        );
    }

    #[Test]
    public function a_stale_expectation_is_refused_and_surfaced_as_a_conflict(): void
    {
        $id = $this->pillarId();
        $staleRev = $this->headRevision();

        // A competing edit lands between draft and approval: the head moves.
        $this->assertNotNull($this->pillars->update('moat', null, 'Competing note.', 'Matthew'));
        $this->assertGreaterThan($staleRev, $this->headRevision());

        $cid = $this->conversations->create('Test', 'Russell');
        $events = [];
        $this->agent([])->applyDecision(
            $this->proposalRow($cid, [
                'entity_type' => 'identity_pillar',
                'id' => $id,
                'values' => ['status' => 'work'],
                'expected_revision_id' => $staleRev,
            ]),
            true,
            $this->account(),
            'Russell',
            function (string $event, array $payload) use (&$events): void {
                $events[$event][] = $payload;
            },
        );

        $this->assertArrayHasKey('applied', $events);
        $applied = $events['applied'][0];
        $this->assertFalse($applied['ok']);
        $this->assertTrue($applied['conflict']);
        $this->assertSame(AgentConversation::CONFLICT_MESSAGE, $applied['error']);

        // Nothing was persisted: the competing edit survives, the status is
        // untouched, and the head did not move again.
        $pillar = $this->pillars->findByPid('moat');
        $this->assertInstanceOf(Pillar::class, $pillar);
        $this->assertSame('defined', $pillar->getStatus());
        $this->assertSame('Competing note.', $pillar->getNotes());
    }

    #[Test]
    public function a_fresh_expectation_applies_and_advances_the_revision(): void
    {
        $id = $this->pillarId();
        $head = $this->headRevision();

        $cid = $this->conversations->create('Test', 'Russell');
        $events = [];
        $this->agent([])->applyDecision(
            $this->proposalRow($cid, [
                'entity_type' => 'identity_pillar',
                'id' => $id,
                'values' => ['status' => 'work'],
                'expected_revision_id' => $head,
            ]),
            true,
            $this->account(),
            'Russell',
            function (string $event, array $payload) use (&$events): void {
                $events[$event][] = $payload;
            },
        );

        $this->assertArrayHasKey('applied', $events);
        $applied = $events['applied'][0];
        $this->assertTrue($applied['ok']);
        $this->assertFalse($applied['conflict']);
        $this->assertNull($applied['error']);

        $pillar = $this->pillars->findByPid('moat');
        $this->assertInstanceOf(Pillar::class, $pillar);
        $this->assertSame('work', $pillar->getStatus());
        $this->assertSame('Russell', $pillar->getEditorLabel(), 'apply attributes the approver');
        $this->assertSame($head + 1, $this->headRevision());
    }
}
