<?php

declare(strict_types=1);

namespace App\CoIntelligence;

use Symfony\Component\Uid\Uuid;
use Waaseyaa\Database\DatabaseInterface;

/**
 * Stores the pending mutations the Co-Intelligence agent proposes, so the
 * confirm-before-apply loop can pause across two requests: the streaming send
 * that proposes a write, and the apply request that approves and executes it.
 *
 * A proposal captures the tool call (name + input), a human-readable summary +
 * before/after diff for the approval card, and the full Anthropic message array
 * up to and including the assistant turn that requested the tool, so the loop
 * can be resumed after the tool result is fed back. Nothing here writes to the
 * workspace entities; that only happens at apply time, through the gated tools.
 *
 * Supporting table (not an entity): a token-addressed audit/queue row, so it
 * uses DatabaseInterface directly per the storage rules.
 */
final class AgentProposalRepository
{
    public const string TABLE = 'anokii_agent_proposal';

    public function __construct(private readonly DatabaseInterface $db) {}

    public function ensure(): void
    {
        $schema = $this->db->schema();
        if ($schema->tableExists(self::TABLE)) {
            return;
        }
        $schema->createTable(self::TABLE, [
            'fields' => [
                'id' => ['type' => 'serial', 'not null' => true],
                'token' => ['type' => 'varchar', 'length' => 64, 'not null' => true],
                'conversation_id' => ['type' => 'int', 'not null' => true],
                'status' => ['type' => 'varchar', 'length' => 16, 'not null' => true],
                'tool_name' => ['type' => 'varchar', 'length' => 64, 'not null' => true],
                'tool_use_id' => ['type' => 'varchar', 'length' => 80, 'not null' => true],
                'tool_input' => ['type' => 'text'],
                'messages' => ['type' => 'text'],
                'prefix_results' => ['type' => 'text'],
                'summary' => ['type' => 'text'],
                'diff' => ['type' => 'text'],
                'author_uid' => ['type' => 'int', 'not null' => true],
                'author_label' => ['type' => 'varchar', 'length' => 128, 'not null' => true],
                'created_at' => ['type' => 'varchar', 'length' => 19, 'not null' => true],
            ],
            'primary key' => ['id'],
            'indexes' => [
                'idx_proposal_token' => ['token'],
                'idx_proposal_conv' => ['conversation_id'],
            ],
        ]);
    }

    /**
     * Persist a pending proposal and return its opaque token.
     *
     * @param array<string,mixed> $toolInput
     * @param list<array<string,mixed>> $messages
     * @param list<array<string,mixed>> $prefixResults
     * @param list<array{field:string,before:mixed,after:mixed}> $diff
     */
    public function create(
        int $conversationId,
        string $toolName,
        string $toolUseId,
        array $toolInput,
        array $messages,
        array $prefixResults,
        string $summary,
        array $diff,
        int $authorUid,
        string $authorLabel,
    ): string {
        $token = Uuid::v4()->toRfc4122();
        $this->db->query(
            'INSERT INTO ' . self::TABLE
            . ' (token, conversation_id, status, tool_name, tool_use_id, tool_input, messages, prefix_results, summary, diff, author_uid, author_label, created_at)'
            . ' VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $token,
                $conversationId,
                'pending',
                $toolName,
                $toolUseId,
                json_encode($toolInput, JSON_UNESCAPED_SLASHES),
                json_encode($messages, JSON_UNESCAPED_SLASHES),
                json_encode($prefixResults, JSON_UNESCAPED_SLASHES),
                $summary,
                json_encode($diff, JSON_UNESCAPED_SLASHES),
                $authorUid,
                $authorLabel,
                gmdate('Y-m-d H:i:s'),
            ],
        );

        return $token;
    }

    /**
     * @return array<string,mixed>|null
     */
    public function find(string $token): ?array
    {
        if ($token === '') {
            return null;
        }
        foreach ($this->db->query('SELECT * FROM ' . self::TABLE . ' WHERE token = ?', [$token]) as $row) {
            $row['tool_input'] = $this->decode($row['tool_input'] ?? null);
            $row['messages'] = $this->decode($row['messages'] ?? null);
            $row['prefix_results'] = $this->decode($row['prefix_results'] ?? null);
            $row['diff'] = $this->decode($row['diff'] ?? null);

            return $row;
        }

        return null;
    }

    public function markStatus(string $token, string $status): void
    {
        $this->db->query(
            'UPDATE ' . self::TABLE . ' SET status = ? WHERE token = ?',
            [$status, $token],
        );
    }

    private function decode(mixed $json): mixed
    {
        if (!is_string($json) || $json === '') {
            return [];
        }
        $decoded = json_decode($json, true);

        return is_array($decoded) ? $decoded : [];
    }
}
