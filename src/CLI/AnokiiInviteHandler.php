<?php

declare(strict_types=1);

namespace App\CLI;

use App\Auth\SetupTokenRepository;
use App\Auth\SetupTokenSchema;
use App\Support\Db;
use Waaseyaa\CLI\Command\SymfonyCommandIO;
use Waaseyaa\Entity\EntityTypeManagerInterface;
use Waaseyaa\User\User;

/**
 * `vendor/bin/waaseyaa anokii:invite <email> [--name=] [--base-url=]`
 *
 * Ensures an account exists for the email (created with NO usable password),
 * mints a one-time set-password token, and prints the invite link. The person
 * opens the link and sets their own password. No password is ever generated,
 * stored, or printed here, only a one-time token link.
 */
final class AnokiiInviteHandler
{
    public function __construct(
        private readonly EntityTypeManagerInterface $entityTypeManager,
    ) {}

    public function execute(SymfonyCommandIO $io): int
    {
        $email = strtolower(trim((string) $io->argument('email')));
        if ($email === '' || !str_contains($email, '@')) {
            $io->error('Provide a valid email: anokii:invite <email> [--name=...]');

            return 1;
        }
        $name = (string) ($io->option('name') ?? '');
        $baseUrl = rtrim((string) ($io->option('base-url') ?? 'https://fnprocure.ca'), '/');

        try {
            $storage = $this->entityTypeManager->getStorage('user');
            $user = $storage->loadByKey('mail', $email);

            if (!$user instanceof User) {
                $values = ['name' => $name !== '' ? $name : $email, 'mail' => $email, 'status' => 1];
                $user = $storage->create($values);
                $storage->save($user);
                $io->writeln(sprintf('Created account for %s (uid %s).', $email, (string) $user->id()));
            } else {
                $io->writeln(sprintf('Account for %s already exists (uid %s).', $email, (string) $user->id()));
            }

            $db = Db::persistent();
            new SetupTokenSchema($db)->ensure();
            $token = new SetupTokenRepository($db)->mint($email);
        } catch (\Throwable $e) {
            $io->error(sprintf('Failed: %s', $e->getMessage()));

            return 1;
        }

        $io->writeln('');
        $io->writeln('Set-password link (one-time, give this to the account holder):');
        $io->writeln(sprintf('%s/admin/anokii/set-password?token=%s', $baseUrl, $token));

        return 0;
    }
}
