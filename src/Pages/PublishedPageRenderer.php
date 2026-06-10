<?php

declare(strict_types=1);

namespace App\Pages;

use App\Entity\Page;
use Twig\Environment;
use Waaseyaa\Entity\Repository\EntityRepositoryInterface;

/**
 * Renders the PUBLISHED revision of a public `page` entity through the shared
 * block renderer (templates/page.html.twig + templates/blocks/*).
 *
 * This is the single render path for the public site copy: PageController
 * serves it over HTTP, and app:ingest-knowledge feeds the same render to the
 * Co-Intelligence knowledge base, so the RAG index cannot drift from what
 * fnprocure.ca serves. Draft revisions are invisible here by construction —
 * only loadPublishedRevision() is ever read.
 */
final class PublishedPageRenderer
{
    public function __construct(
        private readonly EntityRepositoryInterface $pages,
        private readonly Environment $twig,
    ) {}

    /**
     * Render the published revision of the page at the given path, or null
     * when no page exists there or nothing is published yet.
     */
    public function render(string $path): ?string
    {
        $page = $this->loadPublishedByPath($path);
        if ($page === null) {
            return null;
        }

        return $this->twig->render('page.html.twig', [
            'path' => $path,
            'page' => [
                'title' => $page->getTitle(),
                'meta_description' => $page->getMetaDescription(),
                'meta_robots' => $page->getMetaRobots(),
                'head_styles' => $page->getHeadStyles(),
                'blocks' => $page->getBlocks(),
            ],
        ]);
    }

    /**
     * Find the page whose `path` matches, then load its published revision.
     * Returns null when no page is seeded for the path or nothing is published.
     */
    public function loadPublishedByPath(string $path): ?Page
    {
        $entityId = null;
        foreach ($this->pages->findBy(['path' => $path]) as $candidate) {
            if ($candidate instanceof Page) {
                $entityId = (string) $candidate->id();
                break;
            }
        }

        if ($entityId === null) {
            return null;
        }

        $published = $this->pages->loadPublishedRevision($entityId);

        return $published instanceof Page ? $published : null;
    }
}
