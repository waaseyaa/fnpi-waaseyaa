<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Auth\SetupTokenRepository;
use App\Auth\SetupTokenSchema;
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

        $this->assertSame('anokii.home', $router->match('/anokii')['_route'] ?? null);
        $this->assertSame('anokii.login', $router->match('/anokii/login')['_route'] ?? null);
        $this->assertSame('anokii.settings', $router->match('/anokii/settings')['_route'] ?? null);
        $this->assertSame('anokii.setpw', $router->match('/anokii/set-password')['_route'] ?? null);
        $this->assertSame('anokii.identity', $router->match('/anokii/identity')['_route'] ?? null);
    }

    #[Test]
    public function anokii_pages_redirect_to_login_when_signed_out(): void
    {
        // No EntityTypeManager => no current user => signed out, and the
        // controller redirects before the service is ever touched.
        $shell = new AnokiiController(null, new SetupTokenRepository($this->db()));
        $home = $shell->dashboard(new Request());
        $this->assertInstanceOf(RedirectResponse::class, $home);
        $this->assertSame('/anokii/login', $home->getTargetUrl());

        $identity = new IdentityController(null, new PillarService(null));
        $tool = $identity->index(new Request());
        $this->assertInstanceOf(RedirectResponse::class, $tool);
        $this->assertSame('/anokii/login', $tool->getTargetUrl());
    }

    #[Test]
    public function identity_save_is_401_when_signed_out(): void
    {
        $identity = new IdentityController(null, new PillarService(null));
        $request = Request::create('/anokii/identity/save', 'POST', [], [], [], [], (string) json_encode(['pid' => 'purpose', 'status' => 'work']));
        $response = $identity->save($request);
        $this->assertSame(401, $response->getStatusCode());
    }

    #[Test]
    public function identity_history_is_401_when_signed_out(): void
    {
        $identity = new IdentityController(null, new PillarService(null));
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
        $this->assertStringContainsString('/anokii/logout', $html);
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
            'modules' => \App\Anokii\Modules::all(),
            'user_label' => 'Russell',
            'user_role' => 'Editor',
            'user_initials' => 'RU',
        ];
    }

    #[Test]
    public function modules_define_live_and_soon_set(): void
    {
        $ids = array_column(\App\Anokii\Modules::all(), 'id');
        foreach (['home', 'identity', 'drive', 'ai', 'rooms', 'workspaces', 'portal', 'vault', 'governance', 'settings'] as $id) {
            $this->assertContains($id, $ids);
        }
        $this->assertTrue(\App\Anokii\Modules::find('identity')['live']);
        $this->assertTrue(\App\Anokii\Modules::find('home')['live']);
        $this->assertTrue(\App\Anokii\Modules::find('drive')['live']);
        $this->assertFalse(\App\Anokii\Modules::find('governance')['live']);
        $this->assertNull(\App\Anokii\Modules::find('nope'));
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
        $this->assertStringContainsString('href="/anokii/identity"', $html);
        $this->assertStringContainsString('href="/anokii/drive"', $html);
        $this->assertStringNotContainsString('{%', $html);
    }

    #[Test]
    public function coming_soon_redirects_to_login_when_signed_out(): void
    {
        $shell = new AnokiiController(null, new SetupTokenRepository($this->db()));
        $r = $shell->comingSoon(new Request(), 'rooms');
        $this->assertInstanceOf(RedirectResponse::class, $r);
        $this->assertSame('/anokii/login', $r->getTargetUrl());
    }
}
