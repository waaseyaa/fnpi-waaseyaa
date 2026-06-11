<?php

declare(strict_types=1);

namespace App\Mcp;

use Waaseyaa\Api\McpAdmin\RecentInvocation;
use Waaseyaa\Audit\Contract\AuditQuery;
use Waaseyaa\Audit\Contract\AuditQueryInterface;
use Waaseyaa\Audit\Enum\AuditEventKind;
use Waaseyaa\Mcp\Admin\RecentInvocationsQueryInterface;

/**
 * Backs the MCP admin surface's per-tool recent-invocations table from the
 * OCAP audit log written by {@see McpInvocationAuditor}. The framework's
 * optional ai-observability adapter for this port does not exist in
 * alpha.202, so the admin table would otherwise always be empty.
 */
final readonly class McpRecentInvocationsQuery implements RecentInvocationsQueryInterface
{
    /** How many newest mcp.dispatch rows to scan for the requested tool. */
    private const int SCAN_WINDOW = 500;

    public function __construct(
        private AuditQueryInterface $query,
    ) {}

    public function recentForTool(string $toolName, int $limit): array
    {
        $subjectUri = sprintf('/mcp/tools/%s', $toolName);

        $rows = [];
        foreach ($this->query->findBy(new AuditQuery(kinds: [AuditEventKind::McpDispatch], limit: self::SCAN_WINDOW)) as $event) {
            if ($event->getSubjectUri() !== $subjectUri) {
                continue;
            }

            $attributes = $event->get('attributes');
            if (\is_string($attributes)) {
                $attributes = json_decode($attributes, true) ?: [];
            }
            $attributes = \is_array($attributes) ? $attributes : [];
            $outcome = $event->getOutcome();
            $summary = (string) ($attributes['summary'] ?? '');

            $rows[] = [
                'created_at' => (string) ($event->get('created_at') ?? ''),
                'invocation' => new RecentInvocation(
                    traceUuid: (string) ($event->get('uuid') ?? ''),
                    invokedAt: (string) ($event->get('created_at') ?? ''),
                    account: (string) $event->getAccountUid(),
                    outcome: $outcome === 'allowed' ? 'ok' : 'error',
                    errorMessage: $outcome === 'allowed' ? null : $summary,
                    latencyMs: null,
                ),
            ];
        }

        usort($rows, static fn(array $a, array $b): int => strcmp($b['created_at'], $a['created_at']));

        return array_map(
            static fn(array $row): RecentInvocation => $row['invocation'],
            \array_slice($rows, 0, max(1, $limit)),
        );
    }
}
