<?php

declare(strict_types=1);

namespace App\Mcp;

use Waaseyaa\AI\Tools\AgentToolResult;

/**
 * The remote MCP agent's scope rules: which entity types it may touch, which
 * field writes are refused, and which tools are human-only on this site.
 *
 * Publishing stays human-only (PagesService::publish() behind the session UI's
 * `publish pages` gate). The framework's entity tools write whatever field
 * values they are handed, and `published_revision_id` / `revision_id` are real
 * base-table columns (SqlStorageDriver::write() routes existing columns
 * straight through), so a value write could move the published pointer. This
 * gate closes that hole app-side, mirroring App\CoIntelligence\AgentTools'
 * guardScope() for the in-app agent.
 */
final class McpAgentScope
{
    /** Entity types the MCP agent may read (the Anokii workspace surface). */
    public const array READ_TYPES = ['page', 'identity_pillar', 'document', 'document_note', 'drive_asset'];

    /**
     * Entity types the MCP agent may write. Revisionable types only: a write
     * lands as a draft revision and never moves the published pointer.
     * document_note is excluded — it is not revisionable, so an update would
     * mutate the live note thread rather than produce a reviewable draft.
     */
    public const array WRITE_TYPES = ['page', 'identity_pillar', 'document', 'drive_asset'];

    /**
     * Field keys refused in entity.create / entity.update values for every
     * entity type: the revision pointers (publish state lives in
     * published_revision_id) and generic publish flags.
     */
    public const array DENIED_FIELDS = ['published_revision_id', 'revision_id', 'published', 'moderation_state'];

    /**
     * Per-type field denials. `page.status` is the publish marker ("published"
     * once live); identity_pillar's `status` is its maturity field and stays
     * writable.
     */
    public const array DENIED_FIELDS_BY_TYPE = ['page' => ['status']];

    /**
     * Tools refused outright for the MCP surface. Both share the
     * `tool.entity.update` capability so the capability gate cannot exclude
     * them, and both re-point revision state: set_current_revision writes a
     * historical revision row back over the base row (which can carry a stale
     * published_revision_id with it), rollback clobbers the draft head.
     * Revision-pointer surgery stays human-only.
     */
    public const array DENIED_TOOLS = ['entity.set_current_revision', 'entity.rollback'];

    /** Entity tools that read; scoped to READ_TYPES. */
    private const array READ_TOOLS = ['entity.read', 'entity.list', 'entity.search', 'entity.list_revisions'];

    /** Entity tools that write; scoped to WRITE_TYPES. */
    private const array WRITE_TOOLS = ['entity.create', 'entity.update', 'entity.delete'];

    /**
     * Check a tool invocation against the scope. Null means allowed; a result
     * is the denial to return to the caller.
     *
     * @param array<string, mixed> $arguments
     */
    public static function guard(string $toolName, array $arguments): ?AgentToolResult
    {
        if (\in_array($toolName, self::DENIED_TOOLS, true)) {
            return AgentToolResult::error(
                sprintf('%s: revision-pointer operations are human-only on this site.', $toolName),
                'forbidden',
            );
        }

        $isRead = \in_array($toolName, self::READ_TOOLS, true);
        $isWrite = \in_array($toolName, self::WRITE_TOOLS, true);
        if (!$isRead && !$isWrite) {
            return null;
        }

        $type = (string) ($arguments['entity_type'] ?? '');
        $allowed = $isWrite ? self::WRITE_TYPES : self::READ_TYPES;
        if ($type === '' || !\in_array($type, $allowed, true)) {
            return AgentToolResult::error(
                sprintf(
                    '%s: the MCP agent may only %s workspace content (%s), not "%s".',
                    $toolName,
                    $isWrite ? 'write' : 'read',
                    implode(', ', $allowed),
                    $type,
                ),
                'out_of_scope',
            );
        }

        if ($toolName === 'entity.create' || $toolName === 'entity.update') {
            $values = $arguments['values'] ?? [];
            $values = \is_array($values) ? $values : [];
            $denied = array_merge(self::DENIED_FIELDS, self::DENIED_FIELDS_BY_TYPE[$type] ?? []);
            $hit = array_values(array_intersect(array_map('strval', array_keys($values)), $denied));
            if ($hit !== []) {
                return AgentToolResult::error(
                    sprintf(
                        '%s: field(s) [%s] control publish/revision state and are human-only; remove them from values.',
                        $toolName,
                        implode(', ', $hit),
                    ),
                    'publish_denied',
                );
            }
        }

        return null;
    }
}
