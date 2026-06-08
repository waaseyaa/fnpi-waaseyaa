<?php

declare(strict_types=1);

namespace App\CoIntelligence;

use App\Access\WorkspaceAccess;
use Waaseyaa\AI\Tools\AgentToolInterface;
use Waaseyaa\AI\Tools\AgentToolResult;
use Waaseyaa\AI\Tools\Entity\EntityCreateTool;
use Waaseyaa\AI\Tools\Entity\EntityDeleteTool;
use Waaseyaa\AI\Tools\Entity\EntityListRevisionsTool;
use Waaseyaa\AI\Tools\Entity\EntityListTool;
use Waaseyaa\AI\Tools\Entity\EntityReadTool;
use Waaseyaa\AI\Tools\Entity\EntityRollbackTool;
use Waaseyaa\AI\Tools\Entity\EntitySearchTool;
use Waaseyaa\AI\Tools\Entity\EntitySetCurrentRevisionTool;
use Waaseyaa\AI\Tools\Entity\EntityUpdateTool;
use Waaseyaa\Access\AccountInterface;
use Waaseyaa\Entity\EntityTypeManager;

/**
 * The Co-Intelligence agent's tool layer. It exposes the framework's native
 * entity CRUD + revision tools (waaseyaa/ai-tools, alpha.193), constructed with
 * the workspace AccessPolicy attached, so every call the agent makes is gated by
 * exactly the same policy the UI controllers use. Nothing is hand-rolled here:
 * this only wires, scopes, and classifies the framework tools.
 *
 * Scope (Phase 2 decision): create / update / delete plus set-current-revision /
 * rollback on the four workspace entities only. Reads (read / list / search /
 * list-revisions) run without confirmation; mutations are proposed and require
 * approval before they execute.
 */
final class AgentTools
{
    /** The only entity types the agent may name. */
    public const array WORKSPACE_TYPES = ['identity_pillar', 'document', 'document_note', 'drive_asset'];

    /** Tools advertised to the model, in a sensible order. */
    public const array TOOLS = [
        'entity.search',
        'entity.read',
        'entity.list',
        'entity.list_revisions',
        'entity.create',
        'entity.update',
        'entity.delete',
        'entity.set_current_revision',
        'entity.rollback',
    ];

    /** Mutating tools: never executed without approval. */
    public const array MUTATING = [
        'entity.create',
        'entity.update',
        'entity.delete',
        'entity.set_current_revision',
        'entity.rollback',
    ];

    public function __construct(private readonly ?EntityTypeManager $entityTypeManager) {}

    public function isMutating(string $name): bool
    {
        return in_array($name, self::MUTATING, true);
    }

    public function isKnown(string $name): bool
    {
        return in_array($name, self::TOOLS, true);
    }

    /**
     * Anthropic `tools` descriptors for the advertised tools.
     *
     * @return list<array{name:string, description:string, input_schema:array<string,mixed>}>
     */
    public function descriptors(): array
    {
        $out = [];
        foreach (self::TOOLS as $name) {
            $tool = $this->build($name);
            if ($tool === null) {
                continue;
            }
            $out[] = [
                'name' => $name,
                'description' => $tool->description(),
                'input_schema' => $tool->inputSchema(),
            ];
        }

        return $out;
    }

    /**
     * Execute a tool as the signed-in account. Refuses any entity type outside
     * the workspace scope before dispatching.
     *
     * @param array<string,mixed> $input
     */
    public function execute(string $name, array $input, AccountInterface $account): AgentToolResult
    {
        $scope = $this->guardScope($name, $input);
        if ($scope !== null) {
            return $scope;
        }
        $tool = $this->build($name);
        if ($tool === null) {
            return AgentToolResult::error(sprintf('Unknown tool "%s".', $name), 'unknown_tool');
        }

        return $tool->execute($input, $account);
    }

    /**
     * Side-effect-free preview of a tool.
     *
     * @param array<string,mixed> $input
     */
    public function dryRun(string $name, array $input, AccountInterface $account): AgentToolResult
    {
        $scope = $this->guardScope($name, $input);
        if ($scope !== null) {
            return $scope;
        }
        $tool = $this->build($name);
        if ($tool === null) {
            return AgentToolResult::error(sprintf('Unknown tool "%s".', $name), 'unknown_tool');
        }

        return $tool->dryRun($input, $account);
    }

    /**
     * @param array<string,mixed> $input
     */
    private function guardScope(string $name, array $input): ?AgentToolResult
    {
        if (!$this->isKnown($name)) {
            return AgentToolResult::error(sprintf('Tool "%s" is not available.', $name), 'unknown_tool');
        }
        $type = (string) ($input['entity_type'] ?? '');
        if ($type !== '' && !in_array($type, self::WORKSPACE_TYPES, true)) {
            return AgentToolResult::error(
                sprintf('The agent may only act on workspace content (%s), not "%s".', implode(', ', self::WORKSPACE_TYPES), $type),
                'out_of_scope',
            );
        }

        return null;
    }

    private function build(string $name): ?AgentToolInterface
    {
        $etm = $this->entityTypeManager;
        if ($etm === null) {
            return null;
        }
        $tool = match ($name) {
            'entity.read' => new EntityReadTool($etm),
            'entity.list' => new EntityListTool($etm),
            'entity.search' => new EntitySearchTool($etm),
            'entity.list_revisions' => new EntityListRevisionsTool($etm),
            'entity.create' => new EntityCreateTool($etm),
            'entity.update' => new EntityUpdateTool($etm),
            'entity.delete' => new EntityDeleteTool($etm),
            'entity.set_current_revision' => new EntitySetCurrentRevisionTool($etm),
            'entity.rollback' => new EntityRollbackTool($etm),
            default => null,
        };
        // Attach the workspace AccessPolicy so the tool enforces the same gate as
        // the UI, for this account, on this entity, for this operation.
        $tool?->setAccessHandler(WorkspaceAccess::handler());

        return $tool;
    }
}
