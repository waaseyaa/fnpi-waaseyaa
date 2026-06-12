<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\CoIntelligence\AgentTools;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Access\AccountInterface;

/**
 * The Co-Intelligence agent tool layer: classification (read vs write) and the
 * workspace scope guard. The full propose -> approve -> revision loop runs
 * against a real model + real entities, so it is verified live on the Pi; this
 * suite covers the wiring fnpi owns. The framework's EntityToolAccessTest covers
 * the policy gating inside the tools.
 */
final class AgentToolsTest extends TestCase
{
    private function account(): AccountInterface
    {
        return new class implements AccountInterface {
            public function id(): int|string
            {
                return 1;
            }

            public function hasPermission(string $permission): bool
            {
                return true;
            }

            public function getRoles(): array
            {
                return ['administrator'];
            }

            public function isAuthenticated(): bool
            {
                return true;
            }
        };
    }

    #[Test]
    public function reads_and_writes_are_classified(): void
    {
        $tools = new AgentTools(null);

        $this->assertTrue($tools->isMutating('entity.create'));
        $this->assertTrue($tools->isMutating('entity.update'));
        $this->assertTrue($tools->isMutating('entity.delete'));
        $this->assertTrue($tools->isMutating('entity.set_current_revision'));
        $this->assertTrue($tools->isMutating('entity.rollback'));

        $this->assertFalse($tools->isMutating('entity.read'));
        $this->assertFalse($tools->isMutating('entity.search'));
        $this->assertFalse($tools->isMutating('entity.list'));
        $this->assertFalse($tools->isMutating('entity.list_revisions'));

        $this->assertTrue($tools->isKnown('entity.update'));
        $this->assertFalse($tools->isKnown('entity.truncate'));
    }

    #[Test]
    public function an_out_of_scope_entity_type_is_refused_before_dispatch(): void
    {
        $tools = new AgentTools(null);

        $result = $tools->execute('entity.update', ['entity_type' => 'user', 'id' => 1, 'values' => ['name' => 'x']], $this->account());

        $this->assertTrue($result->isError);
        $this->assertSame('out_of_scope', $result->summary);
        $this->assertStringContainsString('workspace content', (string) ($result->content[0]['text'] ?? ''));
    }

    #[Test]
    public function an_unknown_tool_is_refused(): void
    {
        $tools = new AgentTools(null);

        $result = $tools->execute('entity.truncate', ['entity_type' => 'identity_pillar'], $this->account());

        $this->assertTrue($result->isError);
        $this->assertSame('unknown_tool', $result->summary);
    }

    #[Test]
    public function tool_names_are_wire_safe_for_anthropic_and_map_back(): void
    {
        $tools = new AgentTools(null);

        // Anthropic tool names must match ^[a-zA-Z0-9_-]+$ (no dots).
        $this->assertSame('entity_update', $tools->wireName('entity.update'));
        $this->assertSame('entity.update', $tools->canonicalName('entity_update'));
        $this->assertSame('entity.set_current_revision', $tools->canonicalName('entity_set_current_revision'));

        // Classification + dispatch work when the model calls the wire name.
        $this->assertTrue($tools->isMutating('entity_update'));
        $this->assertFalse($tools->isMutating('entity_read'));
        $this->assertTrue($tools->isKnown('entity_search'));

        $result = $tools->execute('entity_update', ['entity_type' => 'user', 'id' => 1, 'values' => []], $this->account());
        $this->assertSame('out_of_scope', $result->summary);
    }

    #[Test]
    public function the_workspace_types_include_pages_and_the_venture_section(): void
    {
        // The agent may draft public website copy (page) in addition to the
        // identity / documents / drive entities (drafts only, never publish),
        // and may read and propose edits to the staff-only venture numbers
        // (lanes, gating facts, the provenance snapshot), every write behind
        // the same human-approval loop.
        $this->assertSame(
            ['identity_pillar', 'document', 'document_note', 'drive_asset', 'page', 'venture_lane', 'gating_fact', 'venture_snapshot', 'venture_thread', 'venture_item'],
            AgentTools::WORKSPACE_TYPES,
        );
    }

    #[Test]
    public function a_page_update_passes_the_workspace_scope_guard(): void
    {
        // `page` is in scope now, so an update on it is NOT refused as
        // out_of_scope (it gets past the guard; without a kernel it then reports
        // the tool is unavailable, which is a different, expected outcome).
        $tools = new AgentTools(null);

        $result = $tools->execute('entity.update', ['entity_type' => 'page', 'id' => 1, 'values' => ['blocks' => []]], $this->account());

        $this->assertNotSame('out_of_scope', $result->summary, 'page must be a permitted agent target');
    }
}
