<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Controller\PageController;
use App\Provider\SiteServiceProvider;
use App\Tests\Support\SeededPages;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Entity\Repository\EntityRepositoryInterface;
use Waaseyaa\Routing\WaaseyaaRouter;
use Waaseyaa\SSR\SsrServiceProvider;

/**
 * The FNPI homepage renders the approved concept at `/`.
 *
 * Boots the SSR Twig environment the same way the kernel does
 * (setKernelContext + boot) so the page controller can render templates/.
 */
final class HomepageTest extends TestCase
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
    public function site_provider_registers_the_home_route(): void
    {
        $router = new WaaseyaaRouter();
        new SiteServiceProvider()->routes($router);

        $this->assertSame('home', $router->match('/')['_route'] ?? null);
    }

    #[Test]
    public function homepage_renders_the_fnpi_concept(): void
    {
        $response = new PageController(self::$pages)->home();
        $html = (string) $response->getContent();

        $this->assertSame(200, $response->getStatusCode());

        // Identity + hero.
        $this->assertStringContainsString('First Nations Procurement', $html);
        $this->assertStringContainsString('Service', $html);
        $this->assertStringContainsString('Sourcing Solutions', $html);
        $this->assertStringContainsString('100% First Nations-owned', $html);

        // The three lanes.
        $this->assertStringContainsString('Sourcing', $html);
        $this->assertStringContainsString('Technology', $html);
        $this->assertStringContainsString('Privacy', $html);

        // "Built on sovereignty" proof band.
        $this->assertStringContainsString('Built on sovereignty', $html);

        // Faraday section.
        $this->assertStringContainsString('Faraday', $html);

        // Vision / Mission.
        $this->assertStringContainsString('Our Vision', $html);
        $this->assertStringContainsString('Our Mission', $html);

        // Contact posts to mailto for now.
        $this->assertStringContainsString('mailto:info@fnprocure.ca', $html);

        // Brand assets: the FNPI logos are served locally, not from the wsimg CDN.
        $this->assertStringContainsString('/img/fnpi-wolf.png', $html);
        $this->assertStringContainsString('/img/fnpi-wordmark.jpg', $html);
        $this->assertStringNotContainsString('wsimg.com', $html);

        // Brand typography from the concept.
        $this->assertStringContainsString('Anton', $html);
        $this->assertStringContainsString('Oswald', $html);

        // First-party analytics beacon is loaded (cookieless, self-hosted).
        $this->assertStringContainsString('/js/fnpi-analytics.js', $html);

        // Rendered through Twig; no raw template tags leaked.
        $this->assertStringNotContainsString('{%', $html);
    }
}
