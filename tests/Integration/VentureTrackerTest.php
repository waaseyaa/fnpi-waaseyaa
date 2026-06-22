<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Access\WorkspaceAccess;
use App\Controller\VentureController;
use App\Entity\VentureItem;
use App\Entity\VentureThread;
use App\Mcp\McpAgentScope;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Twig\Environment;
use Waaseyaa\Access\AccountInterface;
use Waaseyaa\SSR\SsrServiceProvider;

/**
 * The venture tracker: entity registration, the staff-only access posture
 * (anonymous denied, signed-in staff read + write), the MCP agent's read AND
 * write access to both types (it maintains the board), and the template
 * render (status dots, cards, who-notes).
 */
final class VentureTrackerTest extends TestCase
{
    private static Environment $twig;

    public static function setUpBeforeClass(): void
    {
        $provider = new SsrServiceProvider();
        $provider->setKernelContext(dirname(__DIR__, 2), [], []);
        $provider->boot();
        \App\Tests\Support\ShellTemplates::register();
        $twig = SsrServiceProvider::getTwigEnvironment();
        self::assertNotNull($twig);
        self::$twig = $twig;
    }

    #[Test]
    public function both_types_are_registered_and_not_revisionable(): void
    {
        $byId = [];
        foreach (require dirname(__DIR__, 2) . '/config/entity-types.php' as $type) {
            $byId[$type->id()] = $type;
        }
        foreach (['venture_thread' => VentureThread::class, 'venture_item' => VentureItem::class] as $id => $class) {
            $this->assertArrayHasKey($id, $byId);
            $this->assertSame($class, $byId[$id]->getClass());
            $this->assertFalse($byId[$id]->isRevisionable(), "$id is a flat working board, not revisionable");
        }
    }

    #[Test]
    public function the_tracker_is_staff_only_read_and_write_anonymous_denied(): void
    {
        $handler = WorkspaceAccess::handler();
        $anon = $this->account(false);
        $staff = $this->account(true);

        foreach ([new VentureThread(), new VentureItem()] as $entity) {
            $type = $entity instanceof VentureThread ? 'venture_thread' : 'venture_item';
            foreach (['view', 'update', 'delete'] as $op) {
                $this->assertFalse($handler->check($entity, $op, $anon)->isAllowed(), "anon $op denied on $type");
                $this->assertTrue($handler->check($entity, $op, $staff)->isAllowed(), "staff $op allowed on $type");
            }
            $this->assertFalse($handler->checkCreateAccess($type, '', $anon)->isAllowed(), "anon create denied on $type");
            $this->assertTrue($handler->checkCreateAccess($type, '', $staff)->isAllowed(), "staff create allowed on $type");
        }
    }

    #[Test]
    public function the_mcp_agent_can_read_and_write_both_types(): void
    {
        foreach (['venture_thread', 'venture_item'] as $type) {
            $this->assertNull(McpAgentScope::guard('entity.read', ['entity_type' => $type, 'id' => 1]), "$type read allowed");
            $this->assertNull(McpAgentScope::guard('entity.update', ['entity_type' => $type, 'id' => 1, 'values' => ['body' => 'x']]), "$type update allowed");
            $this->assertNull(McpAgentScope::guard('entity.create', ['entity_type' => $type, 'values' => ['title' => 'x']]), "$type create allowed");
        }
        // The tracker status is a plain field the agent flips freely (unlike
        // page.status or gating_fact.status), but publish/revision fields stay
        // refused even though the type is non-revisionable.
        $this->assertNull(McpAgentScope::guard('entity.update', ['entity_type' => 'venture_item', 'id' => 1, 'values' => ['status' => 'done']]));
        $this->assertNotNull(McpAgentScope::guard('entity.update', ['entity_type' => 'venture_item', 'id' => 1, 'values' => ['published_revision_id' => 5]]));
    }

    #[Test]
    public function the_signed_out_tracker_redirects_to_login(): void
    {
        $response = new VentureController(null)->index(new Request());
        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame('/admin/anokii/login', $response->headers->get('Location'));
    }

    #[Test]
    public function the_tracker_module_is_live_in_the_workspace_nav(): void
    {
        $venture = null;
        foreach (\App\Support\AnokiiShell::modules() as $module) {
            if ($module['id'] === 'venture') {
                $venture = $module;
            }
        }
        $this->assertNotNull($venture);
        $this->assertTrue($venture['live']);
        $this->assertSame('/admin/anokii/venture', $venture['href']);
    }

    #[Test]
    public function the_template_renders_cards_with_status_dots_and_who_notes(): void
    {
        $html = self::$twig->render('anokii/venture.html.twig', [
            'nav_active' => 'venture', 'modules' => \App\Support\AnokiiShell::modules(),
            'user_label' => 'Russell', 'user_role' => 'Editor', 'user_initials' => 'R',
            'threads' => [
                ['title' => 'Copy refresh', 'next' => 'Ship it.', 'sort_order' => 1, 'items' => [
                    ['body' => 'Orientation done', 'status' => 'done', 'who' => '(prod authoritative)', 'sort_order' => 1],
                    ['body' => 'Stripe run', 'status' => 'doing', 'who' => '', 'sort_order' => 2],
                ]],
                ['title' => 'Open decisions', 'next' => '', 'sort_order' => 2, 'items' => [
                    ['body' => 'Tagline pillar empty', 'status' => 'dec', 'who' => 'Matt', 'sort_order' => 1],
                ]],
            ],
        ]);

        $this->assertSame(2, substr_count($html, 'class="vcard'));
        $this->assertStringContainsString('<span class="vd done"></span>Orientation done', $html);
        $this->assertStringContainsString('<span class="vd doing"></span>Stripe run', $html);
        $this->assertStringContainsString('<span class="vd dec"></span>Tagline pillar empty', $html);
        $this->assertStringContainsString('<span class="vwho">(prod authoritative)</span>', $html);
        $this->assertStringContainsString('<b>Next</b>Ship it.', $html);
    }

    private function account(bool $authenticated): AccountInterface
    {
        return new class($authenticated) implements AccountInterface {
            public function __construct(private readonly bool $authenticated) {}

            public function id(): int|string
            {
                return $this->authenticated ? 1 : 0;
            }

            public function hasPermission(string $permission): bool
            {
                return false;
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
