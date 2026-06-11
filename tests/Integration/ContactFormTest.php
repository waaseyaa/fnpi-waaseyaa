<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Access\WorkspaceAccess;
use App\Contact\ContactRateLimiter;
use App\Controller\ContactSubmitController;
use App\Entity\ContactSubmission;
use App\Mcp\McpAgentScope;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;
use Waaseyaa\Access\AccountInterface;
use Waaseyaa\Database\DBALDatabase;
use Waaseyaa\Database\DatabaseInterface;
use Waaseyaa\Entity\EntityType;
use Waaseyaa\Entity\Repository\EntityRepositoryInterface;
use Waaseyaa\EntityStorage\Connection\SingleConnectionResolver;
use Waaseyaa\EntityStorage\Driver\SqlStorageDriver;
use Waaseyaa\EntityStorage\EntityRepository;
use Waaseyaa\EntityStorage\SqlSchemaHandler;
use Waaseyaa\SSR\SsrServiceProvider;

/**
 * The working contact form: email-only validation, the three spam traps
 * (honeypot, minimum fill time, per-sender rate limit), the entity
 * round-trip, the access posture (staff read, manage-inbox writes, no create
 * through the gate, MCP agent excluded), and the signed-out inbox redirect.
 */
final class ContactFormTest extends TestCase
{
    private DatabaseInterface $db;
    private EntityRepositoryInterface $submissions;

    public static function setUpBeforeClass(): void
    {
        $provider = new SsrServiceProvider();
        $provider->setKernelContext(dirname(__DIR__, 2), [], []);
        $provider->boot();
    }

    protected function setUp(): void
    {
        $this->db = DBALDatabase::createSqlite(':memory:');
        $entityType = new EntityType(
            id: 'contact_submission',
            label: 'Contact submission',
            class: ContactSubmission::class,
            keys: ['id' => 'id', 'uuid' => 'uuid', 'label' => 'email'],
        );
        new SqlSchemaHandler($entityType, $this->db)->ensureTable();
        $resolver = new SingleConnectionResolver($this->db);
        $this->submissions = new EntityRepository(
            $entityType,
            new SqlStorageDriver($resolver),
            new EventDispatcher(),
            null,
            $this->db,
        );
    }

    /** A submit controller with a working limiter on the same test database. */
    private function controller(): ContactSubmitController
    {
        $limiter = new ContactRateLimiter($this->db, 'test-secret');
        $limiter->ensure();

        return new ContactSubmitController($this->submissions, $limiter);
    }

    /** @param array<string, string> $overrides */
    private function post(array $overrides = []): Request
    {
        $params = array_merge([
            'email' => 'chief@example-nation.ca',
            'name' => '',
            'organization' => '',
            'topic' => '',
            'message' => '',
            'website' => '',
            'fts' => (string) (time() - 30),
        ], $overrides);

        return Request::create('/contact/submit', 'POST', $params);
    }

    private function storedCount(): int
    {
        return iterator_count((function () {
            foreach ($this->submissions->findBy([]) as $e) {
                yield $e;
            }
        })());
    }

    #[Test]
    public function entity_type_is_registered_and_not_revisionable(): void
    {
        $byId = [];
        foreach (require dirname(__DIR__, 2) . '/config/entity-types.php' as $type) {
            $byId[$type->id()] = $type;
        }
        $this->assertArrayHasKey('contact_submission', $byId);
        $this->assertSame(ContactSubmission::class, $byId['contact_submission']->getClass());
        $this->assertFalse($byId['contact_submission']->isRevisionable(), 'submissions are immutable records');
    }

    #[Test]
    public function an_email_only_submission_is_stored_and_confirmed(): void
    {
        $response = $this->controller()->submit($this->post());
        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('Got it.', (string) $response->getContent());

        $stored = null;
        foreach ($this->submissions->findBy([]) as $e) {
            $stored = $e;
        }
        $this->assertInstanceOf(ContactSubmission::class, $stored);
        $this->assertSame('chief@example-nation.ca', $stored->getEmail());
        $this->assertSame('', $stored->getName());
        $this->assertSame('/contact', $stored->getSourcePath());
        $this->assertFalse($stored->isRead());
        $this->assertNotSame('', $stored->getSubmittedAt());
    }

    #[Test]
    public function a_full_submission_round_trips_every_field(): void
    {
        $this->controller()->submit($this->post([
            'name' => 'Russell',
            'organization' => 'FNPI',
            'topic' => 'defence',
            'message' => 'Scope a platform for us.',
        ]));

        $stored = null;
        foreach ($this->submissions->findBy([]) as $e) {
            $stored = $e;
        }
        $this->assertSame('Russell', $stored->getName());
        $this->assertSame('FNPI', $stored->getOrg());
        $this->assertSame('defence', $stored->getTopic());
        $this->assertSame('Scope a platform for us.', $stored->getMessage());
    }

    #[Test]
    public function a_missing_or_malformed_email_rerenders_with_values_preserved(): void
    {
        $response = $this->controller()->submit($this->post([
            'email' => 'not-an-email',
            'name' => 'Keep Me',
            'message' => 'Preserve this text.',
        ]));

        $this->assertSame(422, $response->getStatusCode());
        $html = (string) $response->getContent();
        $this->assertStringContainsString('working email address', $html);
        $this->assertStringContainsString('value="Keep Me"', $html);
        $this->assertStringContainsString('Preserve this text.', $html);
        $this->assertSame(0, $this->storedCount(), 'nothing stored on validation failure');
    }

    #[Test]
    public function a_honeypot_hit_renders_success_but_stores_nothing(): void
    {
        $response = $this->controller()->submit($this->post(['website' => 'http://spam.example']));
        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('Got it.', (string) $response->getContent());
        $this->assertSame(0, $this->storedCount());
    }

    #[Test]
    public function a_too_fast_or_forged_fill_time_is_dropped_silently(): void
    {
        $c = $this->controller();
        foreach ([(string) time(), (string) (time() + 600), 'abc', ''] as $fts) {
            $response = $c->submit($this->post(['fts' => $fts]));
            $this->assertSame(200, $response->getStatusCode());
            $this->assertStringContainsString('Got it.', (string) $response->getContent());
        }
        $this->assertSame(0, $this->storedCount());
    }

    #[Test]
    public function the_rate_limit_drops_a_flood_silently_after_the_cap(): void
    {
        $c = $this->controller();
        for ($i = 0; $i < ContactRateLimiter::LIMIT_PER_WINDOW + 3; $i++) {
            $response = $c->submit($this->post(['email' => "visitor{$i}@example.ca"]));
            $this->assertSame(200, $response->getStatusCode());
        }
        $this->assertSame(ContactRateLimiter::LIMIT_PER_WINDOW, $this->storedCount(), 'storage stops at the cap; responses stay success-looking');
    }

    #[Test]
    public function access_policy_staff_read_manage_inbox_writes_no_create_anywhere(): void
    {
        $handler = WorkspaceAccess::handler();
        $submission = new ContactSubmission();

        $anon = $this->account(false, []);
        $viewer = $this->account(true, []);
        $editor = $this->account(true, [WorkspaceAccess::MANAGE_INBOX]);

        $this->assertFalse($handler->check($submission, 'view', $anon)->isAllowed(), 'anonymous cannot read the inbox');
        $this->assertTrue($handler->check($submission, 'view', $viewer)->isAllowed(), 'any signed-in staff account can read');
        $this->assertFalse($handler->check($submission, 'update', $viewer)->isAllowed(), 'viewer cannot mark read');
        $this->assertTrue($handler->check($submission, 'update', $editor)->isAllowed(), 'manage inbox can mark read');
        $this->assertTrue($handler->check($submission, 'delete', $editor)->isAllowed(), 'manage inbox can delete');

        foreach ([$anon, $viewer, $editor] as $account) {
            $this->assertFalse(
                $handler->checkCreateAccess('contact_submission', '', $account)->isAllowed(),
                'no account creates submissions through the gate; only the public endpoint writes',
            );
        }
    }

    #[Test]
    public function the_mcp_agent_cannot_read_or_write_submissions(): void
    {
        $read = McpAgentScope::guard('entity.read', ['entity_type' => 'contact_submission', 'id' => 1]);
        $this->assertNotNull($read, 'agent reads of submissions are denied');
        $this->assertSame('out_of_scope', $read->summary);

        $write = McpAgentScope::guard('entity.create', ['entity_type' => 'contact_submission', 'fields' => []]);
        $this->assertNotNull($write, 'agent writes of submissions are denied');
        $this->assertSame('out_of_scope', $write->summary);
    }

    #[Test]
    public function the_signed_out_inbox_redirects_to_login(): void
    {
        $controller = new \App\Controller\ContactInboxController(null, WorkspaceAccess::handler());
        $response = $controller->index(new Request());
        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame('/anokii/login', $response->headers->get('Location'));

        $json = $controller->markAllRead(new Request());
        $this->assertSame(401, $json->getStatusCode());
    }

    #[Test]
    public function the_inbox_module_is_live_in_the_workspace_nav(): void
    {
        $inbox = null;
        foreach (\App\Anokii\Modules::all() as $module) {
            if ($module['id'] === 'inbox') {
                $inbox = $module;
            }
        }
        $this->assertNotNull($inbox);
        $this->assertTrue($inbox['live']);
        $this->assertSame('/anokii/inbox', $inbox['href']);
    }

    /** @param list<string> $permissions */
    private function account(bool $authenticated, array $permissions): AccountInterface
    {
        return new class($authenticated, $permissions) implements AccountInterface {
            /** @param list<string> $permissions */
            public function __construct(private readonly bool $authenticated, private readonly array $permissions) {}

            public function id(): int|string
            {
                return $this->authenticated ? 7 : 0;
            }

            public function hasPermission(string $permission): bool
            {
                return in_array($permission, $this->permissions, true);
            }

            /** @return list<string> */
            public function getRoles(): array
            {
                return [];
            }

            public function isAuthenticated(): bool
            {
                return $this->authenticated;
            }
        };
    }
}
