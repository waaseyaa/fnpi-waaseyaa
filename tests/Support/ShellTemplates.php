<?php

declare(strict_types=1);

namespace App\Tests\Support;

use Anokii\Admin\AdminTemplates;
use Twig\Loader\ChainLoader;
use Twig\Loader\FilesystemLoader;
use Waaseyaa\SSR\SsrServiceProvider;

/**
 * Test helper: make the shared Anokii package templates resolvable on the booted
 * SSR Twig environment, mirroring AnokiiServiceProvider::registerPackageTemplates()
 * at runtime. Tests that render an `anokii/*.html.twig` page (which extends
 * `@anokiipkg/_shell.html.twig` via `_fnpi_base`) must call this in
 * setUpBeforeClass after booting SsrServiceProvider, so the package shell and the
 * `@anokiipkg` namespace are on the loader exactly as they are in the running app.
 */
final class ShellTemplates
{
    public static function register(): void
    {
        $twig = SsrServiceProvider::getTwigEnvironment();
        if ($twig === null) {
            return;
        }
        $pkg = AdminTemplates::path();
        $loader = $twig->getLoader();
        if ($loader instanceof ChainLoader) {
            $fs = new FilesystemLoader();
            $fs->addPath($pkg);
            $fs->addPath($pkg . '/anokii', 'anokiipkg');
            $loader->addLoader($fs);
        } elseif ($loader instanceof FilesystemLoader) {
            $loader->addPath($pkg);
            $loader->addPath($pkg . '/anokii', 'anokiipkg');
        }
    }
}
