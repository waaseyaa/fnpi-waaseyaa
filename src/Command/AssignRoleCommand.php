<?php

declare(strict_types=1);

namespace App\Command;

use App\Access\WorkspaceAccess;
use Waaseyaa\CLI\CliIO;
use Waaseyaa\Entity\EntityTypeManagerInterface;
use Waaseyaa\User\User;

/**
 * `vendor/bin/waaseyaa app:assign-role <role> <user>` — assign an Anokii
 * workspace role (admin | editor | viewer) to an account, addressed by email or
 * numeric uid.
 *
 * Applies the role through WorkspaceAccess so the role and its permissions are
 * always written together (the framework's own `user:role` only sets the role
 * string, which is not enough for the non-admin roles). Idempotent: re-running
 * with the same role is a no-op in effect.
 */
final class AssignRoleCommand
{
    public function __construct(private readonly ?EntityTypeManagerInterface $entityTypeManager) {}

    public function run(CliIO $io): int
    {
        $role = strtolower(trim((string) $io->argument('role')));
        $userRef = trim((string) $io->argument('user'));

        // Accept friendly aliases for the admin role.
        if ($role === 'admin') {
            $role = WorkspaceAccess::ROLE_ADMIN;
        }
        if (!WorkspaceAccess::isRole($role)) {
            $io->error(sprintf('Unknown role "%s". Valid roles: admin, editor, viewer.', $role));

            return 1;
        }
        if ($userRef === '') {
            $io->error('Provide a user (email or numeric uid).');

            return 1;
        }
        if ($this->entityTypeManager === null) {
            $io->error('Role assignment requires a booted kernel (EntityTypeManager).');

            return 1;
        }

        $storage = $this->entityTypeManager->getStorage('user');
        try {
            $user = ctype_digit($userRef)
                ? $storage->load((int) $userRef)
                : $storage->loadByKey('mail', strtolower($userRef));
        } catch (\Throwable $e) {
            $io->error('Could not load the account: ' . $e->getMessage());

            return 1;
        }
        if (!$user instanceof User) {
            $io->error(sprintf('No account found for "%s".', $userRef));

            return 1;
        }

        WorkspaceAccess::apply($user, $role);
        $storage->save($user);

        $label = $user->getName() !== '' ? $user->getName() : (string) $user->id();
        $roleLabel = WorkspaceAccess::roles()[$role]['label'] ?? $role;
        $io->writeln(sprintf(
            '  ok     %s (uid %s) is now %s. roles=[%s]',
            $label,
            (string) $user->id(),
            $roleLabel,
            implode(', ', $user->getRoles()),
        ));

        return 0;
    }
}
