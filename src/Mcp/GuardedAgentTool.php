<?php

declare(strict_types=1);

namespace App\Mcp;

use Waaseyaa\Access\AccountInterface;
use Waaseyaa\AI\Tools\AgentToolInterface;
use Waaseyaa\AI\Tools\AgentToolResult;

/**
 * Per-tool wrapper for the MCP surface. Before delegating to the framework
 * tool it applies {@see McpAgentScope::guard()} (workspace entity-type scope,
 * publish/revision field denials, human-only tools), then records the
 * invocation in the audit log.
 *
 * Also adds transport-level dry-run support: the MCP bridge only ever calls
 * execute(), so a boolean `dry_run` argument routes to the framework tool's
 * dryRun() instead. The flag is stripped before delegation.
 */
final class GuardedAgentTool implements AgentToolInterface
{
    public function __construct(
        private readonly string $name,
        private readonly AgentToolInterface $inner,
        private readonly bool $dryRunSupported,
        private readonly McpInvocationAuditor $auditor,
    ) {}

    public function execute(array $arguments, AccountInterface $account): AgentToolResult
    {
        $dryRun = ($arguments['dry_run'] ?? false) === true;
        unset($arguments['dry_run']);

        $denied = McpAgentScope::guard($this->name, $arguments);
        if ($denied !== null) {
            $this->audit($arguments, $account, $denied, $dryRun);

            return $denied;
        }

        try {
            $result = $dryRun
                ? $this->inner->dryRun($arguments, $account)
                : $this->inner->execute($arguments, $account);
        } catch (\Throwable $e) {
            $this->auditor->record($this->name, $account, $arguments, 'error', $e->getMessage(), $dryRun);

            throw $e;
        }

        $this->audit($arguments, $account, $result, $dryRun);

        return $result;
    }

    public function dryRun(array $arguments, AccountInterface $account): AgentToolResult
    {
        unset($arguments['dry_run']);

        $denied = McpAgentScope::guard($this->name, $arguments);
        if ($denied !== null) {
            $this->audit($arguments, $account, $denied, true);

            return $denied;
        }

        $result = $this->inner->dryRun($arguments, $account);
        $this->audit($arguments, $account, $result, true);

        return $result;
    }

    public function argumentsForAudit(array $arguments): array
    {
        return $this->inner->argumentsForAudit($arguments);
    }

    public function inputSchema(): array
    {
        $schema = $this->inner->inputSchema();

        // Advertise the transport-level dry_run flag where the tool supports it.
        if ($this->dryRunSupported && isset($schema['properties']) && \is_array($schema['properties'])) {
            $schema['properties']['dry_run'] = [
                'type' => 'boolean',
                'description' => 'When true, perform a side-effect-free preview instead of executing.',
            ];
        }

        return $schema;
    }

    public function description(): string
    {
        return $this->inner->description();
    }

    /**
     * @param array<string, mixed> $arguments
     */
    private function audit(array $arguments, AccountInterface $account, AgentToolResult $result, bool $dryRun): void
    {
        $outcome = 'allowed';
        if ($result->isError) {
            $outcome = \in_array($result->summary, ['forbidden', 'out_of_scope', 'publish_denied'], true)
                ? 'denied'
                : 'error';
        }

        $this->auditor->record($this->name, $account, $arguments, $outcome, $result->summary, $dryRun);
    }
}
