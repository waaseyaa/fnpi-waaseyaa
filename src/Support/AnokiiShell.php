<?php

declare(strict_types=1);

namespace App\Support;

use Anokii\Admin\AdminModules;
use Anokii\Shell\Shell;
use Waaseyaa\User\User;

/**
 * Instance shell wiring for the FNPI Anokii workspace.
 *
 * The shared chrome context (user chip, nav_active) comes from the package
 * {@see Shell}, and the module catalog now comes from the package
 * {@see AdminModules} (no forked catalog). FNPI declares only its presentation:
 * the live set, per-module overrides (FNPI's order, grouping, labels, descriptions,
 * the `home`/`ai` ids and `Soon` badge it uses), and the `settings` extra the
 * canonical catalog does not carry. The result is byte-identical to the previous
 * hand-rolled `App\Anokii\Modules::all()` (verified field-by-field), so the
 * forked `_shell.html.twig` renders the same sidebar.
 */
final class AnokiiShell
{
    /** Live module set, in package catalog ids. */
    private const LIVE = [
        'dashboard', 'cointelligence', 'identity', 'drive', 'documents',
        'pages', 'inbox', 'venture', 'ventures', 'analytics',
    ];

    /**
     * @return array<string,mixed>
     */
    public static function context(User $user, string $active): array
    {
        return Shell::context($user, $active, ['modules' => self::modules()]);
    }

    /**
     * The FNPI module list, sourced from the canonical catalog with FNPI's
     * presentation overrides and the settings extra.
     *
     * @return list<array<string,mixed>>
     */
    public static function modules(): array
    {
        return AdminModules::resolve(self::LIVE, self::overrides(), self::extra());
    }

    /**
     * @return array<string,mixed>|null
     */
    public static function find(string $id): ?array
    {
        foreach (self::modules() as $m) {
            if (($m['id'] ?? '') === $id) {
                return $m;
            }
        }

        return null;
    }

    /**
     * Per-module overrides keyed by package catalog id: FNPI order, grouping,
     * labels, descriptions, the `home`/`ai` ids, and the `Soon` preview badge.
     * Fields not overridden (icon, href, tile, the live/preview flip) come from
     * the canonical catalog unchanged.
     *
     * @return array<string, array<string, mixed>>
     */
    private static function overrides(): array
    {
        return [
            'dashboard' => ['id' => 'home', 'order' => 0],
            'identity' => ['label' => 'Identity Workspace', 'desc' => 'Define who FNPI is, pillar by pillar.', 'order' => 1],
            'drive' => ['desc' => 'Department file storage, scoped to the Nation.', 'order' => 2],
            'documents' => ['order' => 3],
            'cointelligence' => ['id' => 'ai', 'desc' => 'Ask questions of your own documents and decisions.', 'order' => 4],
            'pages' => ['order' => 5],
            'inbox' => ['order' => 6],
            'venture' => ['desc' => 'The live working board across FNPI ventures.', 'order' => 7],
            'analytics' => ['group' => 'Workspace', 'desc' => "First-party site analytics, in the Nation's own database.", 'order' => 8],
            'ventures' => ['order' => 9],
            'rooms' => ['badge' => 'Soon', 'order' => 10],
            'workspaces' => ['badge' => 'Soon', 'order' => 11],
            'portal' => ['badge' => 'Soon', 'order' => 12],
            'vault' => ['badge' => 'Soon', 'order' => 13],
            'governance' => ['badge' => 'Soon', 'order' => 14],
        ];
    }

    /**
     * FNPI-only modules the canonical catalog does not carry.
     *
     * @return list<array<string, mixed>>
     */
    private static function extra(): array
    {
        return [
            [
                'id' => 'settings', 'label' => 'Settings', 'group' => 'Administration', 'live' => true,
                'href' => '/admin/anokii/settings', 'desc' => '', 'badge' => '', 'tile' => false, 'order' => 15,
                'icon' => '<circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="1.7" fill="none"/><path d="M12 3v3M12 18v3M3 12h3M18 12h3M5.6 5.6l2.1 2.1M16.3 16.3l2.1 2.1M18.4 5.6l-2.1 2.1M7.7 16.3l-2.1 2.1" stroke="currentColor" stroke-width="1.5"/>',
            ],
        ];
    }
}
