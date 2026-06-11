<?php

declare(strict_types=1);

namespace App\CLI;

use Waaseyaa\CLI\CliIO;
use Waaseyaa\Entity\EntityTypeManagerInterface;
use Waaseyaa\User\User;

/**
 * `vendor/bin/waaseyaa mcp:agent-account`
 *
 * Ensures the dedicated MCP agent service account exists and holds EXACTLY the
 * agreed capability set — no roles, no workspace permissions, no password (so
 * it can never sign in interactively; the only way in is the MCP bearer token
 * bound by App\Provider\McpAgentServiceProvider). Idempotent: re-running
 * resets the permissions to the canonical set.
 */
final class McpAgentAccountHandler
{
    public const string AGENT_MAIL = 'mcp-agent@fnprocure.ca';
    public const string AGENT_NAME = 'mcp-agent';

    /**
     * The full grant. Publishing, deleting, revision-pointer surgery, and
     * every workspace permission (edit/publish/administer ...) are
     * deliberately absent; App\Mcp\McpAgentScope closes the field-level and
     * entity-type holes the coarse capabilities leave open.
     */
    public const array CAPABILITIES = [
        'tool.entity.read',
        'tool.entity.list',
        'tool.entity.search',
        'tool.entity.create',
        'tool.entity.update',
        'bimaaji.read',
    ];

    public function __construct(
        private readonly EntityTypeManagerInterface $entityTypeManager,
    ) {}

    public function execute(CliIO $io): int
    {
        try {
            $storage = $this->entityTypeManager->getStorage('user');
            $user = $storage->loadByKey('mail', self::AGENT_MAIL);

            if (!$user instanceof User) {
                $user = $storage->create([
                    'name' => self::AGENT_NAME,
                    'mail' => self::AGENT_MAIL,
                    'status' => 1,
                ]);
                $io->writeln(sprintf('Creating MCP agent account %s.', self::AGENT_MAIL));
            } else {
                $io->writeln(sprintf('MCP agent account %s exists (uid %s); resetting grants.', self::AGENT_MAIL, (string) $user->id()));
            }

            // Exactly the agreed set: no roles (especially not administrator,
            // which short-circuits every permission check), no password.
            $user->setRoles([]);
            $user->setPermissions(self::CAPABILITIES);
            $storage->save($user);
        } catch (\Throwable $e) {
            $io->error(sprintf('Failed: %s', $e->getMessage()));

            return 1;
        }

        $io->writeln(sprintf('uid: %s', (string) $user->id()));
        $io->writeln(sprintf('roles: [%s]', implode(', ', $user->getRoles())));
        $io->writeln(sprintf('permissions: [%s]', implode(', ', self::CAPABILITIES)));
        $io->writeln('');
        $io->writeln('Bind the bearer token via the WAASEYAA_MCP_AGENT_TOKEN env var (see .env.example).');

        return 0;
    }
}
