<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Page;
use Symfony\Component\HttpFoundation\Response;
use Waaseyaa\Entity\Repository\EntityRepositoryInterface;
use Waaseyaa\SSR\SsrServiceProvider;

/**
 * Public content pages for the FNPI site, served from `page` entities.
 *
 * Anokii Pages, increment 1: each route renders the PUBLISHED revision of the
 * matching `page` entity (looked up by its `path`) through the shared block
 * renderer (templates/page.html.twig + templates/blocks/*), instead of a
 * hardcoded per-page template. The published revision is what the public sees;
 * later draft revisions stay private until published. Each page's status and
 * block order are pinned by PageStructureTest (the byte-parity gate against the
 * retired hand-coded templates did its job and was removed once the migration
 * was proven lossless).
 *
 * The page repository is injected (SiteServiceProvider passes
 * EntityTypeManager::getRepository('page')); tests pass a seeded repository.
 * The /proof page stays parked (no route, no entity) pending SFN consent.
 */
final class PageController
{
    public function __construct(
        private readonly ?EntityRepositoryInterface $pages = null,
    ) {}

    public function home(): Response
    {
        return $this->renderPath('/');
    }

    public function technology(): Response
    {
        return $this->renderPath('/technology');
    }

    public function howItWorks(): Response
    {
        return $this->renderPath('/how-it-works');
    }

    /**
     * The proof / reference-build page is intentionally DISABLED pending SFN
     * consent to be named publicly. It has no route and no seeded page entity.
     * The template is parked at templates/proof.html.twig.disabled.
     */
    public function proof(): Response
    {
        return new Response('Not found.', 404);
    }

    public function contact(): Response
    {
        return $this->renderPath('/contact');
    }

    /**
     * Render the published revision of the `page` entity at the given path.
     */
    private function renderPath(string $path): Response
    {
        if ($this->pages === null) {
            return new Response('Page unavailable: page storage is not configured.', 500);
        }

        $page = $this->loadPublishedByPath($path);
        if ($page === null) {
            return new Response('Page not found.', 404);
        }

        $twig = SsrServiceProvider::getTwigEnvironment();
        if ($twig === null) {
            return new Response('Page unavailable: Twig is not initialised.', 500);
        }

        $html = $twig->render('page.html.twig', [
            'path' => $path,
            'page' => [
                'title' => $page->getTitle(),
                'meta_description' => $page->getMetaDescription(),
                'meta_robots' => $page->getMetaRobots(),
                'head_styles' => $page->getHeadStyles(),
                'blocks' => $page->getBlocks(),
            ],
        ]);

        return new Response($html, 200, ['Content-Type' => 'text/html; charset=UTF-8']);
    }

    /**
     * Find the page whose `path` matches, then load its published revision.
     * Returns null when no page is seeded for the path or nothing is published.
     */
    private function loadPublishedByPath(string $path): ?Page
    {
        \assert($this->pages !== null);

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
