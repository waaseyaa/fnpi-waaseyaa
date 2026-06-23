<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use Anokii\Auth\SetupTokenRepository;
use Anokii\Auth\SetupTokenSchema;
use App\Access\WorkspaceAccess;
use App\Controller\AnokiiController;
use App\Controller\IdentityController;
use App\Identity\IdentitySeed;
use App\Identity\PillarService;
use App\Provider\AnokiiServiceProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Waaseyaa\Database\DatabaseInterface;
use Waaseyaa\Database\DBALDatabase;
use Waaseyaa\Routing\Exception\RouteNotFoundException;
use Waaseyaa\Routing\WaaseyaaRouter;
use Waaseyaa\SSR\SsrServiceProvider;

/**
 * Anokii workspace: auth gating, the Identity tool's shell rendering, modules,
 * and the dashboard. The entity-native Identity behaviour (revisionable pillar,
 * registration, migration faithfulness) lives in IdentityPillarTest; the live
 * Pi check covers the end-to-end migrate + edit flow.
 */
final class AnokiiTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        $provider = new SsrServiceProvider();
        $provider->setKernelContext(dirname(__DIR__, 2), [], []);
        $provider->boot();
        \App\Tests\Support\ShellTemplates::register();
    }

    private function db(): DatabaseInterface
    {
        $db = DBALDatabase::createSqlite(':memory:');
        new SetupTokenSchema($db)->ensure();

        return $db;
    }

    #[Test]
    public function anokii_routes_are_registered(): void
    {
        $router = new WaaseyaaRouter();
        new AnokiiServiceProvider()->routes($router);

        $this->assertSame('anokii.home', $router->match('/admin/anokii')['_route'] ?? null);
        $this->assertSame('anokii.login', $router->match('/admin/anokii/login')['_route'] ?? null);
        $this->assertSame('anokii.settings', $router->match('/admin/anokii/settings')['_route'] ?? null);
        $this->assertSame('anokii.setpw', $router->match('/admin/anokii/set-password')['_route'] ?? null);
        $this->assertSame('anokii.identity', $router->match('/admin/anokii/identity')['_route'] ?? null);
    }

    #[Test]
    public function legacy_anokii_subpaths_301_to_admin_anokii(): void
    {
        $router = new WaaseyaaRouter();
        new AnokiiServiceProvider()->routes($router);

        // Every old sub-path (incl. dynamic, multi-segment, and m/*) 301s to its
        // /admin/anokii equivalent so invite links and bookmarks resolve.
        $paths = [
            '/anokii/login',
            '/anokii/set-password',
            '/anokii/settings',
            '/anokii/identity',
            '/anokii/cointelligence',
            '/anokii/drive',
            '/anokii/documents',
            '/anokii/pages',
            '/anokii/inbox',
            '/anokii/venture',
            '/anokii/ventures',
            '/anokii/analytics',
            '/anokii/m/rooms',
            '/anokii/documents/abc/file/2/preview',
        ];
        foreach ($paths as $old) {
            $match = $router->match($old);
            $this->assertSame('anokii.legacy_redirect', $match['_route'] ?? null, $old);

            $response = $match['_controller'](Request::create($old), $match['rest']);
            $this->assertInstanceOf(RedirectResponse::class, $response, $old);
            $this->assertSame(301, $response->getStatusCode(), $old);
            // Old path `/anokii/<sub>` redirects to `/admin` + the same path.
            $this->assertSame('/admin' . $old, $response->getTargetUrl(), $old);
        }
    }

    #[Test]
    public function legacy_redirect_preserves_query_string(): void
    {
        $router = new WaaseyaaRouter();
        new AnokiiServiceProvider()->routes($router);

        // The one-time set-password ?token=… invite link must survive the 301.
        $match = $router->match('/anokii/set-password');
        $response = $match['_controller'](
            Request::create('/anokii/set-password', 'GET', ['token' => 'abc123']),
            $match['rest'],
        );
        $this->assertSame(301, $response->getStatusCode());
        $this->assertSame('/admin/anokii/set-password?token=abc123', $response->getTargetUrl());
    }

    #[Test]
    public function bare_anokii_root_is_freed_and_does_not_serve_the_workspace(): void
    {
        $router = new WaaseyaaRouter();
        new AnokiiServiceProvider()->routes($router);

        // The bare /anokii root is intentionally unrouted (freed for the upcoming
        // public marketing page): it neither serves the workspace nor 301s — only
        // sub-paths redirect. An unmatched path throws (→ 404 at runtime).
        $this->expectException(RouteNotFoundException::class);
        $router->match('/anokii');
    }

    #[Test]
    public function anokii_pages_redirect_to_login_when_signed_out(): void
    {
        // No EntityTypeManager => no current user => signed out, and the
        // controller redirects before the service is ever touched.
        $shell = new AnokiiController(null);
        $home = $shell->dashboard(new Request());
        $this->assertInstanceOf(RedirectResponse::class, $home);
        $this->assertSame('/admin/anokii/login', $home->getTargetUrl());

        $identity = new IdentityController(null, new PillarService(null), WorkspaceAccess::handler());
        $tool = $identity->index(new Request());
        $this->assertInstanceOf(RedirectResponse::class, $tool);
        $this->assertSame('/admin/anokii/login', $tool->getTargetUrl());
    }

    #[Test]
    public function identity_save_is_401_when_signed_out(): void
    {
        $identity = new IdentityController(null, new PillarService(null), WorkspaceAccess::handler());
        $request = Request::create('/admin/anokii/identity/save', 'POST', [], [], [], [], (string) json_encode(['pid' => 'purpose', 'status' => 'work']));
        $response = $identity->save($request);
        $this->assertSame(401, $response->getStatusCode());
    }

    #[Test]
    public function identity_history_is_401_when_signed_out(): void
    {
        $identity = new IdentityController(null, new PillarService(null), WorkspaceAccess::handler());
        $response = $identity->history(new Request(), 'purpose');
        $this->assertSame(401, $response->getStatusCode());
    }

    #[Test]
    public function set_password_token_round_trip(): void
    {
        $tokens = new SetupTokenRepository($this->db());
        $token = $tokens->mint('matthew@fnprocure.ca');

        $this->assertSame('matthew@fnprocure.ca', $tokens->emailForToken($token));
        $this->assertNull($tokens->emailForToken('not-a-real-token'));

        $this->assertSame('matthew@fnprocure.ca', $tokens->consume($token));
        // Single use: a consumed token no longer resolves.
        $this->assertNull($tokens->emailForToken($token));
    }

    #[Test]
    public function identity_template_renders_the_tool(): void
    {
        $twig = SsrServiceProvider::getTwigEnvironment();
        $this->assertNotNull($twig);

        // Build the controller's presented-pillar context from the seed shape,
        // so the template renders without a database.
        $sections = [];
        foreach (IdentitySeed::sections() as $key => $meta) {
            $sections[$key] = $meta + ['key' => $key, 'pillars' => []];
        }
        $counts = ['defined' => 0, 'draft' => 0, 'work' => 0, 'gap' => 0];
        foreach (IdentitySeed::pillars() as $p) {
            $sections[(string) $p['section']]['pillars'][] = [
                'pid' => $p['pid'], 'section' => $p['section'], 'title' => $p['title'],
                'now_label' => $p['now_label'], 'body' => $p['body'], 'is_quote' => (bool) $p['is_quote'],
                'decide_label' => $p['decide_label'], 'decision' => $p['decision'], 'status' => $p['status'],
                'notes' => '', 'pills' => $p['pills'], 'is_full' => (bool) $p['is_full'],
                'last_edited_by' => '', 'last_edited_at' => '',
            ];
            if (isset($counts[$p['status']])) {
                $counts[$p['status']]++;
            }
        }
        $counts['total'] = array_sum($counts);

        $html = $twig->render('anokii/identity.html.twig', $this->shell('identity') + [
            'sections' => array_values($sections),
            'counts' => $counts,
            'statuses' => [
                ['v' => 'defined', 't' => 'Defined'], ['v' => 'draft', 't' => 'Draft / legacy'],
                ['v' => 'work', 't' => 'Needs work'], ['v' => 'gap', 't' => 'Gap'],
            ],
        ]);

        $this->assertStringContainsString('Identity maturity', $html);
        $this->assertStringContainsString('Positioning spine', $html);
        $this->assertStringContainsString('Anishinaabe foundation', $html);
        $this->assertStringContainsString('data-pid="tagline"', $html);
        // The revisionable rebuild surfaces a per-pillar history control.
        $this->assertStringContainsString('class="hbtn"', $html);
        $this->assertStringContainsString('/admin/anokii/logout', $html);
        // Rendered inside the new shell (sidebar + topbar).
        $this->assertStringContainsString('Sovereign workspace', $html);
        $this->assertStringContainsString('Co-Intelligence', $html);
        $this->assertStringContainsString('class="aavatar"', $html);
        $this->assertStringNotContainsString('{%', $html);
    }

    /** Minimal shell context for template render tests. */
    private function shell(string $active): array
    {
        return [
            'nav_active' => $active,
            'modules' => \App\Support\AnokiiShell::modules(),
            'user_label' => 'Russell',
            'user_role' => 'Editor',
            'user_initials' => 'RU',
        ];
    }

    #[Test]
    public function modules_define_live_and_soon_set(): void
    {
        $ids = array_column(\App\Support\AnokiiShell::modules(), 'id');
        foreach (['home', 'identity', 'drive', 'ai', 'rooms', 'workspaces', 'portal', 'vault', 'governance', 'settings'] as $id) {
            $this->assertContains($id, $ids);
        }
        $this->assertTrue(\App\Support\AnokiiShell::find('identity')['live']);
        $this->assertTrue(\App\Support\AnokiiShell::find('home')['live']);
        $this->assertTrue(\App\Support\AnokiiShell::find('drive')['live']);
        $this->assertFalse(\App\Support\AnokiiShell::find('governance')['live']);
        $this->assertNull(\App\Support\AnokiiShell::find('nope'));
    }

    #[Test]
    public function dashboard_template_renders_hero_and_tiles(): void
    {
        $twig = SsrServiceProvider::getTwigEnvironment();
        $html = $twig->render('anokii/home.html.twig', $this->shell('home'));

        $this->assertStringContainsString('Aanii, Russell', $html);
        $this->assertStringContainsString('Identity Workspace', $html);
        $this->assertStringContainsString('Co-Intelligence', $html);
        $this->assertStringContainsString('Coming soon', $html);
        $this->assertStringContainsString('href="/admin/anokii/identity"', $html);
        $this->assertStringContainsString('href="/admin/anokii/drive"', $html);
        $this->assertStringNotContainsString('{%', $html);
    }

    #[Test]
    public function coming_soon_redirects_to_login_when_signed_out(): void
    {
        $shell = new AnokiiController(null);
        $r = $shell->comingSoon(new Request(), 'rooms');
        $this->assertInstanceOf(RedirectResponse::class, $r);
        $this->assertSame('/admin/anokii/login', $r->getTargetUrl());
    }
}
