<?php

declare(strict_types=1);

namespace App\Support;

use App\Anokii\Modules;
use Waaseyaa\User\User;

/**
 * Builds the shared context every Anokii shell page needs: the active nav id,
 * the module list for the sidebar, and the real signed-in user's chip (label,
 * role, avatar initials).
 */
final class AnokiiShell
{
    /**
     * @return array<string,mixed>
     */
    public static function context(User $user, string $active): array
    {
        return [
            'nav_active' => $active,
            'modules' => Modules::all(),
            'user_label' => Auth::label($user),
            'user_role' => self::role($user),
            'user_initials' => self::initials(Auth::label($user)),
        ];
    }

    private static function role(User $user): string
    {
        $roles = $user->getRoles();
        if ($roles !== []) {
            // Humanize the first role id (e.g. "administrator" -> "Administrator").
            return ucwords(str_replace(['_', '-'], ' ', (string) $roles[0]));
        }

        // No explicit role: both accounts are full editors in this MVP.
        return 'Editor';
    }

    private static function initials(string $label): string
    {
        $label = trim($label);
        if ($label === '') {
            return '??';
        }
        $words = preg_split('/\s+/', $label) ?: [];
        if (count($words) >= 2) {
            return strtoupper(mb_substr($words[0], 0, 1) . mb_substr($words[1], 0, 1));
        }

        return strtoupper(mb_substr($label, 0, 2));
    }
}
