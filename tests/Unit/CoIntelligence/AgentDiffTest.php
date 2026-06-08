<?php

declare(strict_types=1);

namespace App\Tests\Unit\CoIntelligence;

use App\CoIntelligence\AgentConversation;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * The no-op guard at the heart of the confirm-before-apply fix: a proposed
 * entity.update whose values already match the stored values must be reported
 * as "already set", never turned into a proposal that records an empty revision
 * and claims success. AgentConversation::diffValues() is the pure decision; the
 * loop wiring around it is exercised live on the Pi.
 */
final class AgentDiffTest extends TestCase
{
    #[Test]
    public function values_equal_to_current_are_a_no_op(): void
    {
        $current = ['status' => 'defined', 'notes' => 'hello'];

        $result = AgentConversation::diffValues($current, ['status' => 'defined']);

        $this->assertSame([], $result['changed']);
        $this->assertSame(['status is already "defined"'], $result['already']);
    }

    #[Test]
    public function a_real_change_is_detected(): void
    {
        $current = ['status' => 'draft', 'notes' => 'hello'];

        $result = AgentConversation::diffValues($current, ['status' => 'defined']);

        $this->assertSame(['status'], $result['changed']);
        $this->assertSame([], $result['already']);
    }

    #[Test]
    public function mixed_proposal_reports_both_changed_and_already(): void
    {
        $current = ['status' => 'defined', 'notes' => 'old'];

        $result = AgentConversation::diffValues($current, ['status' => 'defined', 'notes' => 'new']);

        $this->assertSame(['notes'], $result['changed']);
        $this->assertSame(['status is already "defined"'], $result['already']);
    }

    #[Test]
    public function a_field_absent_from_current_counts_as_a_change(): void
    {
        $result = AgentConversation::diffValues(['status' => 'draft'], ['body' => 'A new statement.']);

        $this->assertSame(['body'], $result['changed']);
        $this->assertSame([], $result['already']);
    }

    #[Test]
    public function scalar_equality_ignores_int_vs_string_representation(): void
    {
        // entity.read returns stored values as strings; the model may send an int.
        $result = AgentConversation::diffValues(['rank' => '3'], ['rank' => 3]);

        $this->assertSame([], $result['changed']);
    }

    #[Test]
    public function clearing_a_field_to_empty_is_a_change_when_it_had_text(): void
    {
        $result = AgentConversation::diffValues(['decision' => 'write one sentence'], ['decision' => '']);

        $this->assertSame(['decision'], $result['changed']);
    }
}
