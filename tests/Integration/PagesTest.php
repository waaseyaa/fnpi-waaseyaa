<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Controller\PageController;
use App\Provider\SiteServiceProvider;
use App\Tests\Support\SeededPages;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Entity\Repository\EntityRepositoryInterface;
use Waaseyaa\Routing\WaaseyaaRouter;
use Waaseyaa\SSR\SsrServiceProvider;

/**
 * The v1 tech-lane pages render with their key headlines, and the spec's hard
 * guardrails hold on every public page (no defense/drones, no published pricing).
 */
final class PagesTest extends TestCase
{
    private static EntityRepositoryInterface $pages;

    public static function setUpBeforeClass(): void
    {
        $provider = new SsrServiceProvider();
        $provider->setKernelContext(dirname(__DIR__, 2), [], []);
        $provider->boot();
        self::$pages = SeededPages::repository();
    }

    #[Test]
    public function all_v1_routes_are_registered(): void
    {
        $router = new WaaseyaaRouter();
        new SiteServiceProvider()->routes($router);

        $this->assertSame('home', $router->match('/')['_route'] ?? null);
        $this->assertSame('technology', $router->match('/technology')['_route'] ?? null);
        $this->assertSame('how-it-works', $router->match('/how-it-works')['_route'] ?? null);
        $this->assertSame('contact', $router->match('/contact')['_route'] ?? null);
        // /proof is intentionally disabled pending SFN consent (see below).
        $this->assertFalse($this->routeExists($router, '/proof'), '/proof must be unrouted (404).');
    }

    /**
     * The router throws ResourceNotFoundException for an unrouted path; treat
     * that as "route does not exist".
     */
    private function routeExists(WaaseyaaRouter $router, string $path): bool
    {
        try {
            return isset($router->match($path)['_route']);
        } catch (\Throwable) {
            return false;
        }
    }

    #[Test]
    public function technology_page_leads_with_sovereignty_and_anokii(): void
    {
        $html = (string) new PageController(self::$pages)->technology()->getContent();
        $this->assertSame(200, new PageController(self::$pages)->technology()->getStatusCode());
        $this->assertStringContainsString('Owned, not rented', $html);
        $this->assertStringContainsString('Anokii', $html);
        $this->assertStringContainsString('Co-Intelligence', $html);
        $this->assertStringContainsString('Member Portal', $html);
        // Sovereignty framing (ownership / data stays in Canada), no pricing.
        $this->assertStringContainsString('Your data stays yours', $html);
        $this->assertStringContainsString('Web Networks', $html);
        // Honest staging: platform + first modules building now, the rest is grown into.
        $this->assertStringContainsString('building now', strtolower($html));
        $this->assertStringNotContainsString('{%', $html);
    }

    #[Test]
    public function how_it_works_shows_the_ladder_and_ai_operator(): void
    {
        $html = (string) new PageController(self::$pages)->howItWorks()->getContent();
        $this->assertStringContainsString('Start where it shows', $html);
        foreach (['Land', 'Prove', 'Expand', 'Own'] as $stage) {
            $this->assertStringContainsString($stage, $html);
        }
        $this->assertStringContainsString('AI Operator', $html);
        // Procurement appears as the enabler (5% set-aside), not pricing.
        $this->assertStringContainsString('5%', $html);
        $this->assertStringContainsString('email last', strtolower($html));
        $this->assertStringNotContainsString('{%', $html);
    }

    #[Test]
    public function proof_route_is_disabled_pending_consent(): void
    {
        // The /proof route is unregistered (returns 404) until SFN consent to be
        // named publicly. The template is parked at proof.html.twig.disabled so
        // the SSR path resolver can no longer serve it by URL either.
        $router = new WaaseyaaRouter();
        new SiteServiceProvider()->routes($router);
        $this->assertFalse($this->routeExists($router, '/proof'));

        $root = dirname(__DIR__, 2);
        $this->assertFileDoesNotExist($root . '/templates/proof.html.twig', 'Active proof template must be parked so /proof 404s.');
        $parked = $root . '/templates/proof.html.twig.disabled';
        $this->assertFileExists($parked, 'Proof template must be preserved (parked) for re-enable.');
        // Defense-in-depth: the parked template still must never name the Nation.
        $this->assertStringNotContainsString('Sheguiandah', (string) file_get_contents($parked));
    }

    #[Test]
    #[DataProvider('pageHtmlProvider')]
    public function no_page_links_to_proof(string $method): void
    {
        $html = (string) new PageController(self::$pages)->{$method}()->getContent();
        $this->assertStringNotContainsString('href="/proof"', $html, sprintf('Page "%s" must not link to the disabled /proof.', $method));
    }

    #[Test]
    public function contact_page_has_quote_form_to_mailto(): void
    {
        $html = (string) new PageController(self::$pages)->contact()->getContent();
        $this->assertStringContainsString('Tell us what your Nation needs', $html);
        $this->assertStringContainsString('mailto:info@fnprocure.ca', $html);
        $this->assertStringContainsString('name="organization"', $html);
        $this->assertStringNotContainsString('{%', $html);
    }

    /**
     * @return list<array{0:string}>
     */
    public static function pageHtmlProvider(): array
    {
        // Live, routed pages only. /proof is disabled (parked template), so it is
        // not a public page and is excluded from the live-page guardrail sweep.
        return [
            ['home'],
            ['technology'],
            ['howItWorks'],
            ['contact'],
            ['defence'],
        ];
    }

    #[Test]
    #[DataProvider('pageHtmlProvider')]
    public function no_defense_or_drones_anywhere_public(string $method): void
    {
        // Posture change 2026-06-10 (Russell, DAF EOI filed): the defence lane is
        // public via /defence and the word appears site-wide (nav, home band), so
        // 'defense'/'defence' left the banned list. The private side stays banned
        // EVERYWHERE, including the /defence page itself: drones, the military
        // network, weapons, ISR, autonomous monitoring.
        $html = strtolower((string) new PageController(self::$pages)->{$method}()->getContent());
        foreach (['drone', 'military', 'weapon', ' isr', 'autonomous monitoring'] as $banned) {
            $this->assertStringNotContainsString($banned, $html, sprintf('Public page "%s" must not mention "%s".', $method, trim($banned)));
        }
    }

    #[Test]
    #[DataProvider('pageHtmlProvider')]
    public function no_published_pricing_anywhere_public(string $method): void
    {
        $html = (string) new PageController(self::$pages)->{$method}()->getContent();
        // No dollar figures on public pages; pricing lives behind "request a quote".
        $this->assertDoesNotMatchRegularExpression('/\$\s*[0-9]/', $html, sprintf('Public page "%s" must not publish pricing.', $method));
        // The pitch is ownership/sovereignty, not price: no pricing language at all.
        $lower = strtolower($html);
        foreach (['commercial rate', 'nonprofit', 'non-profit', 'discount', 'per-seat', 'per seat'] as $banned) {
            $this->assertStringNotContainsString($banned, $lower, sprintf('Public page "%s" must not use pricing language ("%s").', $method, $banned));
        }
    }

    #[Test]
    #[DataProvider('pageHtmlProvider')]
    public function no_em_or_en_dashes_in_rendered_copy(string $method): void
    {
        // House style: no em dashes or en dashes in visible site copy.
        $html = (string) new PageController(self::$pages)->{$method}()->getContent();
        $this->assertStringNotContainsString("\u{2014}", $html, sprintf('Public page "%s" must not contain an em dash.', $method));
        $this->assertStringNotContainsString("\u{2013}", $html, sprintf('Public page "%s" must not contain an en dash.', $method));
    }
}
