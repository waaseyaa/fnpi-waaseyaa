<?php

declare(strict_types=1);

namespace App\Controller;

use App\Pages\PublishedPageRenderer;
use App\Support\CsrfTokenValue;
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

    public function defence(): Response
    {
        return $this->renderPath('/defence');
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
     * Render the published revision of the `page` entity at the given path,
     * via the shared PublishedPageRenderer (the same render app:ingest-knowledge
     * feeds to the Co-Intelligence knowledge base).
     */
    private function renderPath(string $path): Response
    {
        if ($this->pages === null) {
            return new Response('Page unavailable: page storage is not configured.', 500);
        }

        $twig = SsrServiceProvider::getTwigEnvironment();
        if ($twig === null) {
            return new Response('Page unavailable: Twig is not initialised.', 500);
        }

        // The contact form prints {{ csrf_token }} (the framework session
        // token as a hidden field); register it lazily so it resolves at
        // render time, after SessionMiddleware has run. Re-setting an
        // already-registered global is permitted; first-time registration
        // after a render is not, hence the guard.
        try {
            $twig->addGlobal('csrf_token', new CsrfTokenValue());
        } catch (\LogicException) {
            // A template rendered before the global existed (test edge); the
            // form falls back to default('') there.
        }

        $html = new PublishedPageRenderer($this->pages, $twig)->render($path);
        if ($html === null) {
            return new Response('Page not found.', 404);
        }

        return new Response($html, 200, ['Content-Type' => 'text/html; charset=UTF-8']);
    }
}
