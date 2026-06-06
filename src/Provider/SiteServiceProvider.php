<?php

declare(strict_types=1);

namespace App\Provider;

use App\Controller\PageController;
use Waaseyaa\Foundation\ServiceProvider\ServiceProvider;
use Waaseyaa\Routing\RouteBuilder;
use Waaseyaa\Routing\WaaseyaaRouter;

/**
 * Registers the FNPI public site's routes.
 *
 * Wired into the kernel via composer.json -> extra.waaseyaa.providers.
 */
final class SiteServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function routes(WaaseyaaRouter $router, ?\Waaseyaa\Entity\EntityTypeManager $entityTypeManager = null): void
    {
        $controller = new PageController();

        $router->addRoute(
            'home',
            RouteBuilder::create('/')
                ->controller(fn () => $controller->home())
                ->allowAll()
                ->methods('GET')
                ->build(),
        );
    }
}
