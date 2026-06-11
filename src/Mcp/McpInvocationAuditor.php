<?php

declare(strict_types=1);

namespace App\Mcp;

use Waaseyaa\Access\AccountInterface;
use Waaseyaa\Audit\Contract\AuditEventDescriptor;
use Waaseyaa\Audit\Contract\AuditWriterInterface;
use Waaseyaa\Audit\Enum\AuditEventKind;

/**
 * Records every MCP tool invocation into the OCAP audit log (append-only
 * audit_event table via waaseyaa/audit).
 *
 * The framework ships McpDispatchAuditListener for the forward-compatible
 * `waaseyaa.mcp.dispatch` event, but McpEndpoint (alpha.202) never dispatches
 * it — so the app records the entries itself from the guarded tool wrapper.
 * Same privacy posture as that listener: a SHA-256 hash of the arguments is
 * stored, never the raw values (workspace content may be confidential).
 *
 * Best-effort by contract: a failed audit write never disrupts the call.
 */
final readonly class McpInvocationAuditor
{
    public function __construct(
        private ?AuditWriterInterface $writer,
    ) {}

    /**
     * @param array<string, mixed> $arguments Raw call arguments — only a hash is
     *   stored, never the values. (Deliberately NOT AgentToolInterface::
     *   argumentsForAudit(): on alpha.202 it TypeErrors on list-valued
     *   arguments — strtolower() on integer keys; upstream gap.)
     * @param 'allowed'|'denied'|'error' $outcome
     */
    public function record(
        string $toolName,
        AccountInterface $account,
        array $arguments,
        string $outcome,
        ?string $summary,
        bool $dryRun,
    ): void {
        if ($this->writer === null) {
            return;
        }

        try {
            $argumentsHash = hash('sha256', json_encode($arguments, JSON_THROW_ON_ERROR));
            $this->writer->record(new AuditEventDescriptor(
                kind: AuditEventKind::McpDispatch,
                accountUid: (int) $account->id(),
                subjectUri: sprintf('/mcp/tools/%s', $toolName),
                outcome: $outcome,
                severity: match ($outcome) {
                    'allowed' => 'info',
                    'denied' => 'notice',
                    default => 'warning',
                },
                attributes: [
                    'tool' => $toolName,
                    'dry_run' => $dryRun,
                    'arguments_hash' => $argumentsHash,
                    'summary' => (string) ($summary ?? ''),
                ],
            ));
        } catch (\Throwable) {
            // Best-effort: never let an audit failure break the tool call.
        }
    }
}
