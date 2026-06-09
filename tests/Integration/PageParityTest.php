<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Pages\PageSeedData;
use App\Provider\SiteServiceProvider;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\SSR\SsrServiceProvider;

/**
 * Anokii Pages, increment 1 acceptance: the entity-driven render (page.html.twig
 * looping the seeded content blocks) is BYTE-IDENTICAL to the original hand-coded
 * page template, for all four public pages. This is the visual-parity gate: if
 * these pass, switching the routes to render from page entities changes nothing
 * the browser sees.
 */
final class PageParityTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        $provider = new SsrServiceProvider();
        $provider->setKernelContext(dirname(__DIR__, 2), [], []);
        $provider->boot();
    }

    /**
     * @return list<array{0:string,1:string}> [route path, original template]
     */
    public static function pageProvider(): array
    {
        return [
            ['/', 'home.html.twig'],
            ['/technology', 'technology.html.twig'],
            ['/how-it-works', 'how-it-works.html.twig'],
            ['/contact', 'contact.html.twig'],
        ];
    }

    #[Test]
    #[DataProvider('pageProvider')]
    public function entity_render_is_byte_identical_to_the_original_template(string $path, string $originalTemplate): void
    {
        $twig = SsrServiceProvider::getTwigEnvironment();
        $this->assertNotNull($twig, 'Twig environment must be booted.');

        $seed = PageSeedData::all()[$path] ?? null;
        $this->assertNotNull($seed, "Seed data missing for {$path}.");

        $expected = $twig->render($originalTemplate);
        $actual = $twig->render('page.html.twig', ['page' => $seed, 'path' => $path]);

        $this->assertSame(
            $expected,
            $actual,
            "Entity render for {$path} differs from the original {$originalTemplate}.",
        );
    }
}
