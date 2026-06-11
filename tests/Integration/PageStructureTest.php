<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Controller\PageController;
use App\Tests\Support\SeededPages;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Entity\Repository\EntityRepositoryInterface;
use Waaseyaa\SSR\SsrServiceProvider;

/**
 * Structural smoke test for the four public pages, successor to the retired
 * PageParityTest. The byte-identical parity gate did its job: the entity
 * migration is proven lossless and the hand-coded page templates are no longer
 * the reference. What must stay true now is structural: every public page
 * serves its published revision with a 200, and renders its expected content
 * blocks in order (each block emits a `<!-- blk:type -->` marker via
 * page.html.twig).
 */
final class PageStructureTest extends TestCase
{
    private static EntityRepositoryInterface $pages;

    public static function setUpBeforeClass(): void
    {
        $provider = new SsrServiceProvider();
        $provider->setKernelContext(dirname(__DIR__, 2), [], []);
        $provider->boot();
        self::$pages = SeededPages::repository();
    }

    /**
     * @return array<string, array{0:string,1:list<string>}> [controller method, expected block types in order]
     */
    public static function pageProvider(): array
    {
        return [
            '/' => ['home', [
                'hero',
                'feature_lanes',
                'band_proof',
                'photo_strip', // the Track record strip
                'faraday_feature', // the sovereign-AI band
                'faraday_feature', // the Faraday product band
                'vision_mission',
                'cta_band_center',
            ]],
            '/technology' => ['technology', [
                'hero',
                'text_intro',
                'module_grid',
                'faraday_showcase',
                'checklist',
                'department_list',
                'text_center',
                'hero_cta',
            ]],
            '/how-it-works' => ['howItWorks', [
                'hero',
                'stage_ladder',
                'migration_sequence',
                'text_center',
                'faraday_showcase',
                'hero_cta',
            ]],
            '/defence' => ['defence', [
                'hero',
                'text_intro',
                'module_grid',
                'checklist',
                'text_center',
                'hero_cta',
            ]],
            '/faraday' => ['faraday', [
                'hero',
                'faraday_feature',
                'faraday_feature',
                'faraday_feature',
                'cta_band_center',
                'photo_strip',
                'text_center',
            ]],
            '/contact' => ['contact', [
                'hero',
                'contact_form',
            ]],
        ];
    }

    /**
     * @param list<string> $expectedBlockTypes
     */
    #[Test]
    #[DataProvider('pageProvider')]
    public function page_serves_200_with_its_blocks_in_order(string $method, array $expectedBlockTypes): void
    {
        $response = new PageController(self::$pages)->{$method}();

        $this->assertSame(200, $response->getStatusCode());

        $html = (string) $response->getContent();
        preg_match_all('/<!-- blk:([a-z0-9_]+) -->/', $html, $matches);

        $this->assertSame($expectedBlockTypes, $matches[1]);
    }
}
