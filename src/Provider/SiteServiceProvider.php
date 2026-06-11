<?php

declare(strict_types=1);

namespace App\Provider;

use App\Analytics\AnalyticsRecorder;
use App\Analytics\AnalyticsReport;
use App\Analytics\AnalyticsSchema;
use App\Contact\ContactRateLimiter;
use App\Controller\ContactSubmitController;
use App\Controller\CollectController;
use App\Controller\PageController;
use Symfony\Component\HttpFoundation\Request;
use Waaseyaa\Database\DatabaseInterface;
use Waaseyaa\Database\DBALDatabase;
use Waaseyaa\Foundation\ServiceProvider\ServiceProvider;
use Waaseyaa\Routing\RouteBuilder;
use Waaseyaa\Routing\WaaseyaaRouter;

/**
 * Registers the FNPI public site's routes and first-party analytics.
 *
 * Analytics mirrors oiatc.ca exactly: a cookieless, self-hosted beacon
 * (public/js/fnpi-analytics.js) posts pageview + engagement events to
 * /api/collect, stored append-only in FNPI's own SQLite on the storage volume
 * (sovereign at rest, salted by FNPI's own secret), with a dashboard at
 * /admin/analytics. Wired into the kernel via composer.json -> extra.waaseyaa.providers.
 */
final class SiteServiceProvider extends ServiceProvider
{
    private ?DatabaseInterface $persistentDatabase = null;

    public function register(): void {}

    public function boot(): void
    {
        // Ensure the analytics schema on the persistent file connection (not the
        // ephemeral one resolve(DatabaseInterface) hands back at boot). The
        // tryResolveDatabase() probe gates this so routing-only unit tests (no
        // kernel) skip analytics entirely. Mirrors oiatc.
        if ($this->tryResolveDatabase() !== null) {
            new AnalyticsSchema($this->persistentDatabase())->ensure();
            new ContactRateLimiter($this->persistentDatabase(), $this->rateSecret())->ensure();
        }
    }

    private function rateSecret(): string
    {
        return getenv('WAASEYAA_JWT_SECRET') ?: 'fnpi-contact';
    }

    public function routes(WaaseyaaRouter $router, ?\Waaseyaa\Entity\EntityTypeManager $entityTypeManager = null): void
    {
        // Render public pages from the published revision of `page` entities.
        // Resolve the repository defensively: routing-only unit tests (no kernel)
        // pass a null EntityTypeManager and only assert route registration.
        $pages = null;
        if ($entityTypeManager !== null) {
            try {
                $pages = $entityTypeManager->getRepository('page');
            } catch (\Throwable) {
                $pages = null;
            }
        }
        $controller = new PageController($pages);

        $router->addRoute(
            'home',
            RouteBuilder::create('/')
                ->controller(fn () => $controller->home())
                ->allowAll()
                ->methods('GET')
                ->build(),
        );

        $router->addRoute(
            'technology',
            RouteBuilder::create('/technology')
                ->controller(fn () => $controller->technology())
                ->allowAll()
                ->methods('GET')
                ->build(),
        );

        $router->addRoute(
            'how-it-works',
            RouteBuilder::create('/how-it-works')
                ->controller(fn () => $controller->howItWorks())
                ->allowAll()
                ->methods('GET')
                ->build(),
        );

        $router->addRoute(
            'defence',
            RouteBuilder::create('/defence')
                ->controller(fn () => $controller->defence())
                ->allowAll()
                ->methods('GET')
                ->build(),
        );

        // /proof is intentionally DISABLED (unrouted -> 404) pending SFN consent
        // to name the reference build publicly. The PageController::proof() method
        // and templates/proof.html.twig are preserved; re-enable by restoring this
        // route and the nav/footer/homepage links removed alongside it.
        // $router->addRoute(
        //     'proof',
        //     RouteBuilder::create('/proof')
        //         ->controller(fn () => $controller->proof())
        //         ->allowAll()
        //         ->methods('GET')
        //         ->build(),
        // );

        $router->addRoute(
            'faraday',
            RouteBuilder::create('/faraday')
                ->controller(fn () => $controller->faraday())
                ->allowAll()
                ->methods('GET')
                ->build(),
        );

        $router->addRoute(
            'contact',
            RouteBuilder::create('/contact')
                ->controller(fn () => $controller->contact())
                ->allowAll()
                ->methods('GET')
                ->build(),
        );

        // The real contact-form POST. Classic form-encoded body carrying the
        // framework CSRF token as a hidden field (no route exemption); spam
        // hygiene (honeypot, fill-time, rate limit) lives in the controller.
        $submissions = null;
        if ($entityTypeManager !== null) {
            try {
                $submissions = $entityTypeManager->getRepository('contact_submission');
            } catch (\Throwable) {
                $submissions = null;
            }
        }
        $limiter = $this->tryResolveDatabase() !== null
            ? new ContactRateLimiter($this->persistentDatabase(), $this->rateSecret())
            : null;
        $submit = new ContactSubmitController($submissions, $limiter);
        $router->addRoute(
            'contact.submit',
            RouteBuilder::create('/contact/submit')
                ->controller(fn (Request $request) => $submit->submit($request))
                ->allowAll()
                ->methods('POST')
                ->build(),
        );

        // First-party analytics (cookieless, self-hosted). Pin the recorder +
        // report to the persistent SQLite file: resolve(DatabaseInterface) at
        // route-build time can hand back an ephemeral connection (the
        // route/controller closure is built once, not per request), so beacon
        // writes wired to it never reach storage/waaseyaa.sqlite. The
        // tryResolveDatabase() probe stays only as a "kernel present?" gate so
        // routing-only unit tests skip analytics.
        if ($this->tryResolveDatabase() !== null) {
            $database = $this->persistentDatabase();
            $secret = getenv('WAASEYAA_ANALYTICS_SECRET')
                ?: (getenv('WAASEYAA_JWT_SECRET') ?: 'fnpi-analytics');
            $collect = new CollectController(new AnalyticsRecorder($database, $secret));

            // JSON body -> CSRF auto-skipped (same as oiatc's collect/chat).
            $router->addRoute(
                'analytics.collect',
                RouteBuilder::create('/api/collect')
                    ->controller(fn (Request $request) => $collect->collect($request))
                    ->allowAll()
                    ->methods('POST')
                    ->build(),
            );

            // The old standalone dashboard assumed an edge basic-auth gate that
            // was never configured, so it sat publicly reachable. Analytics now
            // lives staff-gated in the workspace; old bookmarks land there.
            // priority(10) so this exact route wins over any admin-surface
            // catch-all (/admin/{path}) the framework may register.
            $router->addRoute(
                'admin.analytics',
                RouteBuilder::create('/admin/analytics')
                    ->controller(fn () => new \Symfony\Component\HttpFoundation\RedirectResponse('/anokii/analytics'))
                    ->allowAll()
                    ->methods('GET')
                    ->priority(10)
                    ->build(),
            );
        }
    }

    /**
     * Resolve the database, returning null when no binding is available
     * (e.g. in unit tests that exercise routing without a kernel). Keeps the
     * analytics wiring optional so it never takes down the content pages.
     */
    private function tryResolveDatabase(): ?DatabaseInterface
    {
        try {
            $database = $this->resolve(DatabaseInterface::class);
        } catch (\Throwable) {
            return null;
        }

        return $database instanceof DatabaseInterface ? $database : null;
    }

    /**
     * The app's SQLite file path, mirroring the kernel: WAASEYAA_DB if set
     * (relative paths resolved against the project root), else the default
     * storage/waaseyaa.sqlite.
     */
    private function databasePath(): string
    {
        $configured = getenv('WAASEYAA_DB') ?: '';
        if ($configured === '') {
            return $this->projectRoot . '/storage/waaseyaa.sqlite';
        }
        $isAbsolute = str_starts_with($configured, '/') || preg_match('#^[A-Za-z]:[\\\\/]#', $configured) === 1;

        return $isAbsolute ? $configured : $this->projectRoot . '/' . ltrim($configured, './');
    }

    /**
     * A DatabaseInterface pinned to the persistent SQLite file, memoised per
     * provider instance. Everything that must persist (analytics) shares this
     * file-backed connection instead of the container's ephemeral one.
     */
    private function persistentDatabase(): DatabaseInterface
    {
        return $this->persistentDatabase ??= DBALDatabase::createSqlite($this->databasePath());
    }
}
