<?php

declare(strict_types=1);

namespace App\Tests\Unit\Mcp;

use App\Mcp\McpAgentScope;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Publish stays human-only over MCP. These pin the app-side gate that closes
 * the framework-level hole: entity.create / entity.update pass field values
 * straight into base-table columns, and published_revision_id IS a column —
 * so without this gate a value write could move the published pointer.
 */
final class McpAgentScopeTest extends TestCase
{
    #[Test]
    public function publish_pointer_writes_are_denied_on_create_and_update(): void
    {
        foreach (['entity.create', 'entity.update'] as $tool) {
            foreach (['published_revision_id', 'revision_id', 'published', 'moderation_state'] as $field) {
                $denied = McpAgentScope::guard($tool, [
                    'entity_type' => 'page',
                    'values' => [$field => 1],
                ]);
                $this->assertNotNull($denied, "$tool with $field must be denied");
                $this->assertTrue($denied->isError);
                $this->assertSame('publish_denied', $denied->summary);
            }
        }
    }

    #[Test]
    public function page_status_is_denied_but_pillar_status_is_not(): void
    {
        $page = McpAgentScope::guard('entity.update', [
            'entity_type' => 'page',
            'values' => ['status' => 'published'],
        ]);
        $this->assertNotNull($page);
        $this->assertSame('publish_denied', $page->summary);

        // identity_pillar.status is its maturity field, not publish state.
        $pillar = McpAgentScope::guard('entity.update', [
            'entity_type' => 'identity_pillar',
            'values' => ['status' => 'work'],
        ]);
        $this->assertNull($pillar);
    }

    #[Test]
    public function revision_pointer_tools_are_denied_outright(): void
    {
        foreach (['entity.set_current_revision', 'entity.rollback'] as $tool) {
            $denied = McpAgentScope::guard($tool, ['entity_type' => 'page', 'id' => 1, 'revision_id' => 1]);
            $this->assertNotNull($denied, "$tool must be denied");
            $this->assertSame('forbidden', $denied->summary);
        }
    }

    #[Test]
    public function writes_are_scoped_to_revisionable_workspace_types(): void
    {
        // user is the escalation vector: roles/permissions/pass live on the
        // user entity, so a write there defeats every other gate.
        foreach (['entity.create', 'entity.update', 'entity.delete'] as $tool) {
            $denied = McpAgentScope::guard($tool, [
                'entity_type' => 'user',
                'id' => 1,
                'values' => ['roles' => ['administrator']],
            ]);
            $this->assertNotNull($denied, "$tool on user must be denied");
            $this->assertSame('out_of_scope', $denied->summary);
        }

        // document_note is live (not revisionable): no MCP writes.
        $note = McpAgentScope::guard('entity.update', [
            'entity_type' => 'document_note',
            'id' => 1,
            'values' => ['body' => 'x'],
        ]);
        $this->assertNotNull($note);
        $this->assertSame('out_of_scope', $note->summary);
    }

    #[Test]
    public function reads_are_scoped_to_workspace_types(): void
    {
        $this->assertNotNull(McpAgentScope::guard('entity.read', ['entity_type' => 'user', 'id' => 1]));
        $this->assertNull(McpAgentScope::guard('entity.read', ['entity_type' => 'page', 'id' => 1]));
        $this->assertNull(McpAgentScope::guard('entity.read', ['entity_type' => 'document_note', 'id' => 1]));
    }

    #[Test]
    public function draft_writes_on_workspace_types_pass(): void
    {
        $this->assertNull(McpAgentScope::guard('entity.create', [
            'entity_type' => 'page',
            'values' => ['title' => 'Draft', 'path' => '/draft', 'blocks' => []],
        ]));
        $this->assertNull(McpAgentScope::guard('entity.update', [
            'entity_type' => 'document',
            'id' => 3,
            'values' => ['title' => 'Renamed'],
        ]));
    }

    #[Test]
    public function non_entity_tools_are_not_scoped(): void
    {
        $this->assertNull(McpAgentScope::guard('bimaaji_search_specs', ['query' => 'mcp']));
    }
}
