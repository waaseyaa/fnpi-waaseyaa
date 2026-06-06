<?php

declare(strict_types=1);

namespace App\Provider;

use App\Auth\SetupTokenRepository;
use App\Auth\SetupTokenSchema;
use App\CLI\AnokiiInviteHandler;
use App\Controller\AnokiiController;
use App\Controller\IdentityController;
use App\Identity\PillarRepository;
use App\Identity\PillarSchema;
use App\Support\Db;
use Symfony\Component\HttpFoundation\Request;
use Waaseyaa\CLI\ArgumentDefinition;
use Waaseyaa\CLI\ArgumentMode;
use Waaseyaa\CLI\CommandDefinition;
use Waaseyaa\CLI\OptionDefinition;
use Waaseyaa\CLI\OptionMode;
use Waaseyaa\Database\DatabaseInterface;
use Waaseyaa\Foundation\ServiceProvider\Capability\HasNativeCommandsInterface;
use Waaseyaa\Foundation\ServiceProvider\ServiceProvider;
use Waaseyaa\Routing\RouteBuilder;
use Waaseyaa\Routing\WaaseyaaRouter;

/**
 * Wires the authenticated Anokii workspace at /anokii/*: the shell (login,
 * logout, dashboard, settings, set-password) and tool #1 (Identity Workspace).
 *
 * Routes are registered ->allowAll() at the framework layer; each controller
 * enforces the session itself and redirects unauthenticated page requests to
 * /anokii/login (and returns 401 for JSON actions), so the gate's redirect
 * target is exactly /anokii/login. Public marketing routes live in
 * SiteServiceProvider and are untouched.
 */
final class AnokiiServiceProvider extends ServiceProvider implements HasNativeCommandsInterface
{
    private ?DatabaseInterface $db = null;

    public function register(): void {}

    public function boot(): void
    {
        // Ensure schema + seed on the persistent file, gated so routing-only
        // unit tests (no kernel) skip it. Wrapped so a storage hiccup never
        // takes down a page.
        if (!$this->kernelPresent()) {
            return;
        }
        try {
            $db = $this->db();
            new PillarSchema($db)->ensure();
            new SetupTokenSchema($db)->ensure();
            new PillarRepository($db)->seedIfEmpty();
        } catch (\Throwable) {
            // best effort; the tool surfaces an empty state rather than 500ing
        }
    }

    public function routes(WaaseyaaRouter $router, ?\Waaseyaa\Entity\EntityTypeManager $entityTypeManager = null): void
    {
        $shell = new AnokiiController($entityTypeManager, new SetupTokenRepository($this->db()));
        $identity = new IdentityController($entityTypeManager, new PillarRepository($this->db()));

        $get = static fn(string $name, string $path, callable $c) => $router->addRoute(
            $name,
            RouteBuilder::create($path)->controller($c)->allowAll()->methods('GET')->build(),
        );
        $post = static fn(string $name, string $path, callable $c) => $router->addRoute(
            $name,
            RouteBuilder::create($path)->controller($c)->allowAll()->methods('POST')->build(),
        );

        $get('anokii.home', '/anokii', fn(Request $r) => $shell->dashboard($r));
        $get('anokii.login', '/anokii/login', fn(Request $r) => $shell->loginForm($r));
        $post('anokii.login.post', '/anokii/login', fn(Request $r) => $shell->loginSubmit($r));
        $get('anokii.logout', '/anokii/logout', fn(Request $r) => $shell->logout($r));
        $get('anokii.settings', '/anokii/settings', fn(Request $r) => $shell->settings($r));
        $post('anokii.settings.post', '/anokii/settings', fn(Request $r) => $shell->settingsSave($r));
        $get('anokii.setpw', '/anokii/set-password', fn(Request $r) => $shell->setPasswordForm($r));
        $post('anokii.setpw.post', '/anokii/set-password', fn(Request $r) => $shell->setPasswordSubmit($r));
        $get('anokii.identity', '/anokii/identity', fn(Request $r) => $identity->index($r));
        $post('anokii.identity.save', '/anokii/identity/save', fn(Request $r) => $identity->save($r));
        // Coming-soon placeholder for not-yet-live modules (drive, ai, rooms, ...).
        $get('anokii.module', '/anokii/m/{module}', fn(Request $r, string $module) => $shell->comingSoon($r, $module));
    }

    public function nativeCommands(): iterable
    {
        yield new CommandDefinition(
            name: 'anokii:invite',
            description: 'Create (if needed) an Anokii account and print a one-time set-password link',
            arguments: [
                new ArgumentDefinition(
                    name: 'email',
                    mode: ArgumentMode::Required,
                    description: 'Email address of the account to invite',
                ),
            ],
            options: [
                new OptionDefinition(name: 'name', mode: OptionMode::Required, description: 'Display name for a new account'),
                new OptionDefinition(name: 'base-url', mode: OptionMode::Required, description: 'Base URL for the link (default https://fnprocure.ca)'),
            ],
            handler: [AnokiiInviteHandler::class, 'execute'],
        );
    }

    private function db(): DatabaseInterface
    {
        return $this->db ??= Db::persistent();
    }

    private function kernelPresent(): bool
    {
        try {
            return $this->resolve(DatabaseInterface::class) instanceof DatabaseInterface;
        } catch (\Throwable) {
            return false;
        }
    }
}
