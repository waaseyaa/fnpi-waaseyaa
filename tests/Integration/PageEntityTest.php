<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Entity\Page;
use App\Pages\PageSeedData;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Entity\EntityType;

/**
 * Wiring + integrity for the Anokii Pages migration: the `page` entity type is
 * registered revisionable (so it carries history and a published-revision
 * pointer), and every block type the seed references has a rendering partial.
 */
final class PageEntityTest extends TestCase
{
    #[Test]
    public function page_entity_type_is_registered_revisionable(): void
    {
        /** @var list<EntityType> $types */
        $types = require dirname(__DIR__, 2) . '/config/entity-types.php';

        $byId = [];
        foreach ($types as $type) {
            $byId[$type->id()] = $type;
        }

        $this->assertArrayHasKey('page', $byId, 'page entity type must be registered');
        $page = $byId['page'];
        $this->assertTrue($page->isRevisionable(), 'page must be revisionable');
        $this->assertSame('revision_id', $page->getKeys()['revision'] ?? null);
        $this->assertSame('id', $page->getKeys()['id'] ?? null);
        $this->assertSame(Page::class, $page->getClass());
    }

    #[Test]
    public function the_four_pages_are_seeded_with_paths(): void
    {
        $paths = array_keys(PageSeedData::all());
        sort($paths);
        $this->assertSame(['/', '/contact', '/how-it-works', '/technology'], $paths);
    }

    #[Test]
    public function every_seeded_block_type_has_a_partial(): void
    {
        $blocksDir = dirname(__DIR__, 2) . '/templates/blocks';

        foreach (PageSeedData::all() as $path => $def) {
            foreach ($def['blocks'] as $i => $block) {
                $this->assertArrayHasKey('type', $block, "block $i on $path has no type");
                $partial = $blocksDir . '/' . $block['type'] . '.html.twig';
                $this->assertFileExists($partial, "missing partial for block type '{$block['type']}' on {$path}");
            }
        }
    }
}
