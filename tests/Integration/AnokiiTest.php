<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Auth\SetupTokenRepository;
use App\Auth\SetupTokenSchema;
use App\Controller\AnokiiController;
use App\Controller\IdentityController;
use App\Identity\IdentitySeed;
use App\Identity\PillarRepository;
use App\Identity\PillarSchema;
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
 * Anokii workspace: auth gating, the seeded Identity tool, and persistence.
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
        new PillarSchema($db)->ensure();
        new SetupTokenSchema($db)->ensure();

        return $db;
    }

    #[Test]
    public function anokii_routes_are_registered(): void
    {
        $router = new WaaseyaaRouter();
        new AnokiiServiceProvider()->routes($router);

        // GET routes (match() defaults to GET; the POST save route is covered by
        // the signed-out 401 test and the live verification).
        $this->assertSame('anokii.home', $router->match('/anokii')['_route'] ?? null);
        $this->assertSame('anokii.login', $router->match('/anokii/login')['_route'] ?? null);
        $this->assertSame('anokii.settings', $router->match('/anokii/settings')['_route'] ?? null);
        $this->assertSame('anokii.setpw', $router->match('/anokii/set-password')['_route'] ?? null);
        $this->assertSame('anokii.identity', $router->match('/anokii/identity')['_route'] ?? null);
    }

    #[Test]
    public function anokii_pages_redirect_to_login_when_signed_out(): void
    {
        // No EntityTypeManager => no current user => signed out.
        $shell = new AnokiiController(null, new SetupTokenRepository($this->db()));
        $home = $shell->dashboard(new Request());
        $this->assertInstanceOf(RedirectResponse::class, $home);
        $this->assertSame('/anokii/login', $home->getTargetUrl());

        $identity = new IdentityController(null, new PillarRepository($this->db()));
        $tool = $identity->index(new Request());
        $this->assertInstanceOf(RedirectResponse::class, $tool);
        $this->assertSame('/anokii/login', $tool->getTargetUrl());
    }

    #[Test]
    public function identity_save_is_401_when_signed_out(): void
    {
        $identity = new IdentityController(null, new PillarRepository($this->db()));
        $request = Request::create('/anokii/identity/save', 'POST', [], [], [], [], (string) json_encode(['pid' => 'purpose', 'status' => 'work']));
        $response = $identity->save($request);
        $this->assertSame(401, $response->getStatusCode());
    }

    #[Test]
    public function pillars_seed_all_seven_sections_and_eighteen_cards(): void
    {
        $repo = new PillarRepository($this->db());
        $repo->seedIfEmpty();

        $this->assertSame(18, $repo->count());
        $this->assertCount(7, IdentitySeed::sections());

        $counts = $repo->statusCounts();
        $this->assertSame(18, $counts['total']);
        $this->assertSame(4, $counts['defined']);

        // A representative pillar carries its artifact content.
        $spine = $repo->find('spine');
        $this->assertNotNull($spine);
        $this->assertStringContainsString('turns Indigenous procurement qualification into a platform', (string) $spine['body']);
        $this->assertSame('defined', $spine['status']);
    }

    #[Test]
    public function seeding_is_idempotent(): void
    {
        $repo = new PillarRepository($this->db());
        $repo->seedIfEmpty();
        $repo->seedIfEmpty();
        $this->assertSame(18, $repo->count());
    }

    #[Test]
    public function a_saved_note_and_status_persist_with_editor_stamp(): void
    {
        $repo = new PillarRepository($this->db());
        $repo->seedIfEmpty();

        $result = $repo->update('purpose', 'work', 'A decision we settled.', 'Russell');
        $this->assertNotNull($result);
        $this->assertSame('Russell', $result['last_edited_by']);

        $row = $repo->find('purpose');
        $this->assertSame('work', $row['status']);
        $this->assertSame('A decision we settled.', $row['notes']);
        $this->assertSame('Russell', $row['last_edited_by']);
        $this->assertNotSame('', (string) $row['last_edited_at']);

        // Invalid status is rejected.
        $this->assertNull($repo->update('purpose', 'bogus', null, 'Russell'));
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
    public function identity_template_renders_the_seeded_tool(): void
    {
        $twig = SsrServiceProvider::getTwigEnvironment();
        $this->assertNotNull($twig);

        $repo = new PillarRepository($this->db());
        $repo->seedIfEmpty();

        // Mirror IdentityController::index's context build.
        $sections = [];
        foreach (IdentitySeed::sections() as $key => $meta) {
            $sections[$key] = $meta + ['key' => $key, 'pillars' => []];
        }
        foreach ($repo->all() as $p) {
            $sections[(string) $p['section']]['pillars'][] = $p;
        }

        $html = $twig->render('anokii/identity.html.twig', $this->shell('identity') + [
            'sections' => array_values($sections),
            'counts' => $repo->statusCounts(),
            'statuses' => [
                ['v' => 'defined', 't' => 'Defined'], ['v' => 'draft', 't' => 'Draft / legacy'],
                ['v' => 'work', 't' => 'Needs work'], ['v' => 'gap', 't' => 'Gap'],
            ],
        ]);

        $this->assertStringContainsString('Identity maturity', $html);
        $this->assertStringContainsString('Positioning spine', $html);
        $this->assertStringContainsString('Anishinaabe foundation', $html);
        $this->assertStringContainsString('data-pid="tagline"', $html);
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
