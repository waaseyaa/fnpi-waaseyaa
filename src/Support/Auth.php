<?php

declare(strict_types=1);

namespace App\Support;

use Waaseyaa\Entity\EntityTypeManager;
use Waaseyaa\User\User;

/**
 * Reads the signed-in account from the framework session.
 *
 * The framework's AuthManager stores the authenticated user id in
 * $_SESSION['waaseyaa_uid']; we load the User entity from the kernel's user
 * storage. Returns null when there is no valid session (the gate for /anokii/*).
 */
final class Auth
{
    public static function currentUser(?EntityTypeManager $entityTypeManager): ?User
    {
        if ($entityTypeManager === null) {
            return null;
        }
        $uid = $_SESSION['waaseyaa_uid'] ?? null;
        if ($uid === null || $uid === '') {
            return null;
        }

        try {
            $user = $entityTypeManager->getStorage('user')->load((int) $uid);
        } catch (\Throwable) {
            return null;
        }

        return $user instanceof User ? $user : null;
    }

    /**
     * A friendly display label for the signed-in user (name, else email).
     */
    public static function label(User $user): string
    {
        $name = $user->getName();

        return $name !== '' ? $name : $user->getEmail();
    }
}
