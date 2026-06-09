<?php

declare(strict_types=1);

namespace App\Pages;

use App\Entity\Page;
use Waaseyaa\Entity\Repository\EntityRepositoryInterface;

/**
 * Seeds the four public pages into `page` entities and publishes them.
 *
 * For each page in {@see PageSeedData}: create a revisionable `page` entity
 * (revision 1), then move the published-revision pointer to revision 1 so the
 * public route renders it. Publishing is deliberate and separate from saving
 * the draft, exactly the draft-then-publish flow increment 2 will expose in the
 * UI; here the migration both seeds and publishes in one pass.
 *
 * Idempotent: a page whose `path` already exists is left untouched.
 */
final class PageSeeder
{
    public function __construct(
        private readonly EntityRepositoryInterface $pages,
    ) {}

    /**
     * @return list<string> the paths that were newly seeded (empty when all
     *                      pages already existed)
     */
    public function seed(): array
    {
        $seeded = [];

        foreach (PageSeedData::all() as $path => $def) {
            if ($this->findByPath($path) !== null) {
                continue;
            }

            $page = new Page();
            $page->setPath($path)
                ->setTitle($def['title'])
                ->setMetaDescription($def['meta_description'])
                ->setMetaRobots($def['meta_robots'])
                ->setHeadStyles($def['head_styles'])
                ->setStatus('published')
                ->setBlocks($def['blocks'])
                ->setRevisionLog('Seed: initial published content');
            $page->enforceIsNew();

            $this->pages->save($page);

            // Re-find to get the storage-assigned id, then publish revision 1
            // (a fresh revisionable entity's first revision is always 1).
            $saved = $this->findByPath($path);
            if ($saved !== null) {
                $this->pages->setPublishedRevision((string) $saved->id(), 1);
            }

            $seeded[] = $path;
        }

        return $seeded;
    }

    private function findByPath(string $path): ?Page
    {
        foreach ($this->pages->findBy(['path' => $path]) as $candidate) {
            if ($candidate instanceof Page) {
                return $candidate;
            }
        }

        return null;
    }
}
