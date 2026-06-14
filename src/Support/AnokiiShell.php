<?php

declare(strict_types=1);

namespace App\Support;

use Anokii\Shell\Shell;
use App\Anokii\Modules;
use Waaseyaa\User\User;

/**
 * Instance shell wiring for the FNPI Anokii workspace.
 *
 * The shared chrome context (user chip label, humanized role, avatar initials,
 * nav_active) lives in the Anokii base {@see Shell}. This class owns only what
 * is FNPI-specific: the module list for the sidebar. The role label is left to
 * the base, which humanizes the role id ("administrator" -> "Administrator"),
 * matching the prior FNPI behaviour.
 */
final class AnokiiShell
{
    /**
     * @return array<string,mixed>
     */
    public static function context(User $user, string $active): array
    {
        return Shell::context($user, $active, ['modules' => Modules::all()]);
    }
}
