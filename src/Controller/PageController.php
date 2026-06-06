<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Component\HttpFoundation\Response;
use Waaseyaa\SSR\SsrServiceProvider;

/**
 * Static content pages for the FNPI public site.
 *
 * Thin orchestration only: each action renders a Twig template through the
 * shared SSR environment. New pages get an action here plus a route in
 * SiteServiceProvider.
 */
final class PageController
{
    public function home(): Response
    {
        return $this->renderTemplate('home.html.twig');
    }

    private function renderTemplate(string $name): Response
    {
        $twig = SsrServiceProvider::getTwigEnvironment();
        if ($twig === null) {
            return new Response('Page unavailable: Twig is not initialised.', 500);
        }

        return new Response(
            $twig->render($name),
            200,
            ['Content-Type' => 'text/html; charset=UTF-8'],
        );
    }
}
