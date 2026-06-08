<?php

declare(strict_types=1);

namespace App\CoIntelligence;

use Waaseyaa\Access\AccountInterface;
use Waaseyaa\AI\Agent\Provider\MessageRequest;
use Waaseyaa\AI\Agent\Provider\ProviderInterface;
use Waaseyaa\AI\Agent\Provider\ToolResultBlock;
use Waaseyaa\AI\Agent\Provider\ToolUseBlock;
use Waaseyaa\AI\Tools\AgentToolResult;

/**
 * The Co-Intelligence agent loop with confirm-before-apply.
 *
 * Drives a bounded tool-calling loop over the provider. Read tools run inline.
 * A write tool is never executed directly: it is captured as a proposal (with a
 * human-readable before/after diff), persisted, and surfaced to the user as a
 * `proposal` event. Nothing changes until the user approves via applyDecision(),
 * which executes the tool as the signed-in account (so the same AccessPolicy the
 * UI uses gates it), attributes it, records a revision, and resumes the loop by
 * feeding the tool result back to the model.
 */
final class AgentConversation
{
    private const int MAX_ITERS = 6;
    private const int MAX_TOKENS = 1500;

    public function __construct(
        private readonly ProviderInterface $provider,
        private readonly AgentTools $tools,
        private readonly AgentProposalRepository $proposals,
        private readonly ConversationRepository $conversations,
        private readonly ChatPromptBuilder $prompts,
    ) {}

    /**
     * Start a turn from a fresh user message (RAG context already folded in).
     *
     * @param callable(string, array<string,mixed>): void $emit
     */
    public function respond(int $conversationId, string $userMessage, AccountInterface $account, string $authorLabel, callable $emit): void
    {
        $messages = [['role' => 'user', 'content' => $userMessage]];
        $this->runLoop($conversationId, $messages, $account, $authorLabel, $emit);
    }

    /**
     * Resume a paused turn after the user approves or rejects a proposal.
     *
     * @param array<string,mixed> $proposal
     * @param callable(string, array<string,mixed>): void $emit
     */
    public function applyDecision(array $proposal, bool $approve, AccountInterface $account, string $authorLabel, callable $emit): void
    {
        $conversationId = (int) ($proposal['conversation_id'] ?? 0);
        $messages = is_array($proposal['messages'] ?? null) ? $proposal['messages'] : [];
        $prefix = is_array($proposal['prefix_results'] ?? null) ? $proposal['prefix_results'] : [];
        $toolName = (string) ($proposal['tool_name'] ?? '');
        $toolUseId = (string) ($proposal['tool_use_id'] ?? '');
        $input = is_array($proposal['tool_input'] ?? null) ? $proposal['tool_input'] : [];
        $summary = (string) ($proposal['summary'] ?? 'change');

        if ($approve) {
            $result = $this->tools->execute(
                $toolName,
                $this->withAttribution($toolName, $input, (int) $account->id(), $authorLabel),
                $account,
            );
            $resultText = $this->resultText($result);
            $isError = $result->isError;
            $emit('applied', ['ok' => !$isError, 'summary' => $summary, 'error' => $isError ? $resultText : null]);
        } else {
            $resultText = 'The user rejected this proposed change. Do not retry it. Acknowledge and ask what they would like to do instead.';
            $isError = false;
            $emit('applied', ['ok' => false, 'rejected' => true, 'summary' => $summary]);
        }

        $userResults = $prefix;
        $userResults[] = (new ToolResultBlock($toolUseId, $resultText, $isError))->toArray();
        $messages[] = ['role' => 'user', 'content' => $userResults];

        $this->runLoop($conversationId, $messages, $account, $authorLabel, $emit);
    }

    /**
     * @param list<array<string,mixed>> $messages
     * @param callable(string, array<string,mixed>): void $emit
     */
    private function runLoop(int $conversationId, array $messages, AccountInterface $account, string $authorLabel, callable $emit): void
    {
        for ($i = 0; $i < self::MAX_ITERS; $i++) {
            $request = new MessageRequest(
                messages: $messages,
                system: $this->prompts->agentSystem(),
                tools: $this->tools->descriptors(),
                maxTokens: self::MAX_TOKENS,
            );

            try {
                $response = $this->provider->sendMessage($request);
            } catch (\Throwable) {
                $this->finishText($conversationId, 'I hit an error reaching the model. Please try again.', $emit);

                return;
            }

            $messages[] = ['role' => 'assistant', 'content' => $response->content];
            $toolUses = $response->getToolUseBlocks();

            if ($toolUses === []) {
                $this->finishText($conversationId, ChatPromptBuilder::sanitizeDashes($response->getText()), $emit);

                return;
            }

            // Reads run inline; the first write pauses as a proposal.
            $reads = [];
            $writes = [];
            foreach ($toolUses as $toolUse) {
                if ($this->tools->isMutating($toolUse->name)) {
                    $writes[] = $toolUse;
                } else {
                    $reads[] = $toolUse;
                }
            }

            $resultBlocks = [];
            foreach ($reads as $toolUse) {
                $result = $this->tools->execute($toolUse->name, $toolUse->input, $account);
                $resultBlocks[] = (new ToolResultBlock($toolUse->id, $this->resultText($result), $result->isError))->toArray();
            }

            if ($writes === []) {
                $messages[] = ['role' => 'user', 'content' => $resultBlocks];
                continue;
            }

            $first = array_shift($writes);
            foreach ($writes as $extra) {
                $resultBlocks[] = (new ToolResultBlock($extra->id, 'Deferred: please propose one change at a time.', true))->toArray();
            }
            $this->propose($conversationId, $first, $messages, $resultBlocks, (int) $account->id(), $authorLabel, $account, $emit);

            return;
        }

        $this->finishText($conversationId, 'I reached the step limit before finishing. Please narrow the request.', $emit);
    }

    /**
     * @param list<array<string,mixed>> $messages
     * @param list<array<string,mixed>> $prefixResults
     * @param callable(string, array<string,mixed>): void $emit
     */
    private function propose(int $conversationId, ToolUseBlock $toolUse, array $messages, array $prefixResults, int $authorUid, string $authorLabel, AccountInterface $account, callable $emit): void
    {
        [$summary, $diff] = $this->describe($toolUse, $account);
        $token = $this->proposals->create(
            conversationId: $conversationId,
            toolName: $this->tools->canonicalName($toolUse->name),
            toolUseId: $toolUse->id,
            toolInput: $toolUse->input,
            messages: $messages,
            prefixResults: $prefixResults,
            summary: $summary,
            diff: $diff,
            authorUid: $authorUid,
            authorLabel: $authorLabel,
        );

        $emit('proposal', [
            'token' => $token,
            'tool' => $toolUse->name,
            'summary' => $summary,
            'diff' => $diff,
            'destructive' => $this->tools->canonicalName($toolUse->name) === 'entity.delete',
        ]);
    }

    /**
     * Build a human-readable summary + before/after diff for a proposed write.
     *
     * @return array{0:string, 1:list<array{field:string,before:mixed,after:mixed}>}
     */
    private function describe(ToolUseBlock $toolUse, AccountInterface $account): array
    {
        $input = $toolUse->input;
        $type = (string) ($input['entity_type'] ?? '');
        $id = (string) ($input['id'] ?? '');

        switch ($this->tools->canonicalName($toolUse->name)) {
            case 'entity.update':
                $current = $this->loadValues($type, $id, $account);
                $diff = [];
                foreach ((array) ($input['values'] ?? []) as $field => $after) {
                    $diff[] = ['field' => (string) $field, 'before' => $current[$field] ?? null, 'after' => $after];
                }

                return [sprintf('Update %s #%s', $type, $id), $diff];

            case 'entity.create':
                $diff = [];
                foreach ((array) ($input['values'] ?? []) as $field => $after) {
                    $diff[] = ['field' => (string) $field, 'before' => null, 'after' => $after];
                }

                return [sprintf('Create a new %s', $type), $diff];

            case 'entity.delete':
                $current = $this->loadValues($type, $id, $account);
                $label = (string) ($current['title'] ?? ($current['name'] ?? $id));

                return [sprintf('Delete %s #%s (%s)', $type, $id, $label), [['field' => '(delete)', 'before' => $label, 'after' => null]]];

            case 'entity.set_current_revision':
                $rev = $input['revision_id'] ?? null;

                return [sprintf('Set %s #%s to revision %s', $type, $id, (string) $rev), [['field' => 'current_revision', 'before' => '(current)', 'after' => $rev]]];

            case 'entity.rollback':
                $rev = $input['target_revision_id'] ?? null;

                return [sprintf('Roll %s #%s back to revision %s', $type, $id, (string) $rev), [['field' => 'rollback_to', 'before' => '(current)', 'after' => $rev]]];

            default:
                return [$toolUse->name, []];
        }
    }

    /**
     * @return array<string,mixed>
     */
    private function loadValues(string $type, string $id, AccountInterface $account): array
    {
        if ($type === '' || $id === '') {
            return [];
        }
        $result = $this->tools->execute('entity.read', ['entity_type' => $type, 'id' => $id], $account);
        if ($result->isError) {
            return [];
        }
        foreach ($result->content as $block) {
            if (($block['type'] ?? '') === 'json') {
                $values = $block['data']['values'] ?? [];

                return is_array($values) ? $values : [];
            }
        }

        return [];
    }

    /**
     * Inject attribution + a revision log into a create/update before it runs,
     * so the agent's writes carry the signed-in user and a revision, exactly
     * like the UI. The agent itself is told not to set these.
     *
     * @param array<string,mixed> $input
     * @return array<string,mixed>
     */
    private function withAttribution(string $toolName, array $input, int $uid, string $label): array
    {
        if (!in_array($toolName, ['entity.create', 'entity.update'], true)) {
            return $input;
        }
        $type = (string) ($input['entity_type'] ?? '');
        $now = gmdate('Y-m-d H:i:s');
        $attribution = match ($type) {
            'identity_pillar', 'drive_asset' => ['editor_uid' => $uid, 'editor_label' => $label, 'updated_at' => $now],
            'document' => ['version_author_uid' => $uid, 'version_author_label' => $label, 'updated_at' => $now],
            default => ['updated_at' => $now],
        };
        $values = is_array($input['values'] ?? null) ? $input['values'] : [];
        $input['values'] = array_merge($values, $attribution);
        if (!isset($input['revision_log']) || !is_string($input['revision_log']) || $input['revision_log'] === '') {
            $input['revision_log'] = 'Co-Intelligence edit by ' . $label;
        }

        return $input;
    }

    private function resultText(AgentToolResult $result): string
    {
        $parts = [];
        foreach ($result->content as $block) {
            if (($block['type'] ?? '') === 'json') {
                $parts[] = (string) json_encode($block['data'] ?? null, JSON_UNESCAPED_SLASHES);
            } elseif (($block['type'] ?? '') === 'text') {
                $parts[] = (string) ($block['text'] ?? '');
            }
        }
        $text = trim(implode("\n", $parts));

        return $text !== '' ? $text : ($result->isError ? 'error' : 'ok');
    }

    /**
     * @param callable(string, array<string,mixed>): void $emit
     */
    private function finishText(int $conversationId, string $text, callable $emit): void
    {
        if (trim($text) === '') {
            $text = 'Done.';
        }
        $this->conversations->addMessage($conversationId, 'assistant', 'Co-Intelligence', $text);
        $emit('delta', ['text' => $text]);
        $emit('done', ['sources' => []]);
    }
}
