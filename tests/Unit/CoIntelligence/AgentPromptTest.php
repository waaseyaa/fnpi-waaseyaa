<?php

declare(strict_types=1);

namespace App\Tests\Unit\CoIntelligence;

use App\CoIntelligence\ChatPromptBuilder;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Anokii Pages increment 3: the agent's system prompt must teach it to draft
 * public website copy from the Identity pillars, as a DRAFT only (never
 * publish), bilingually (English + Anishinaabemowin). These are the behaviour
 * contracts the rest of the feature depends on, so they are asserted here rather
 * than left to drift.
 */
final class AgentPromptTest extends TestCase
{
    private string $prompt;

    protected function setUp(): void
    {
        $this->prompt = new ChatPromptBuilder()->agentSystem();
    }

    #[Test]
    public function the_agent_knows_it_can_act_on_pages(): void
    {
        $this->assertStringContainsString('page:', $this->prompt);
        $this->assertStringContainsString('blocks', $this->prompt);
    }

    #[Test]
    public function the_agent_drafts_page_copy_from_the_pillars(): void
    {
        $this->assertStringContainsString('Identity pillars', $this->prompt);
        $this->assertStringContainsStringIgnoringCase('draft', $this->prompt);
    }

    #[Test]
    public function the_agent_only_drafts_a_page_never_publishes_it(): void
    {
        $this->assertStringContainsString('never publish', $this->prompt);
        $this->assertStringContainsString('the live public site does not change', $this->prompt);
    }

    #[Test]
    public function the_agent_drafts_bilingually(): void
    {
        $this->assertStringContainsString('Anishinaabemowin', $this->prompt);
        // The convention: an Anishinaabemowin field sits beside the English one.
        $this->assertStringContainsString('_oj', $this->prompt);
    }

    #[Test]
    public function the_agent_knows_the_venture_numbers_and_their_placeholder_posture(): void
    {
        $this->assertStringContainsString('venture_lane:', $this->prompt);
        $this->assertStringContainsString('gating_fact:', $this->prompt);
        $this->assertStringContainsString('y1_worst .. y5_best', $this->prompt);
        // The hard rule: venture figures are placeholder-grade and must be
        // labeled that way whenever quoted.
        $this->assertStringContainsString('placeholder-grade', $this->prompt);
        $this->assertStringContainsString('Never present a venture number as confirmed or final', $this->prompt);
        // Numbers come from the entities, not the RAG passages.
        $this->assertStringContainsString('entity.list venture_lane', $this->prompt);
    }
}
