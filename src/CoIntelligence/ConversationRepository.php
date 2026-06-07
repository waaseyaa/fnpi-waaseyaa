<?php

declare(strict_types=1);

namespace App\CoIntelligence;

use Waaseyaa\Database\DatabaseInterface;

/**
 * Reads and writes Co-Intelligence conversations and their messages.
 *
 * Conversations are shared across the FNPI accounts (the same pattern as the
 * Identity Workspace): the recent list shows every thread, and each message
 * carries its author so a question asked by one account is attributed to that
 * person and visible to the other. Plain DatabaseInterface, app SQLite.
 */
final class ConversationRepository
{
    public function __construct(private readonly DatabaseInterface $db) {}

    /**
     * Create a conversation and return its new id. The same connection is used
     * for the insert and the rowid read so the id is correct.
     */
    public function create(string $title, string $createdBy): int
    {
        $now = gmdate('Y-m-d H:i:s');
        $title = $this->clip($title, 255);
        $this->db->query(
            'INSERT INTO ' . ChatSchema::CONVERSATIONS . ' (title, created_by, created_at, updated_at) VALUES (?, ?, ?, ?)',
            [$title !== '' ? $title : 'New conversation', $this->clip($createdBy, 128), $now, $now],
        );

        foreach ($this->db->query('SELECT last_insert_rowid() AS id', []) as $row) {
            return (int) ($row['id'] ?? 0);
        }

        return 0;
    }

    public function exists(int $conversationId): bool
    {
        return $this->find($conversationId) !== null;
    }

    /**
     * @return array{id:int,title:string,created_by:string,created_at:string,updated_at:string}|null
     */
    public function find(int $conversationId): ?array
    {
        foreach ($this->db->query('SELECT * FROM ' . ChatSchema::CONVERSATIONS . ' WHERE id = ?', [$conversationId]) as $row) {
            return [
                'id' => (int) ($row['id'] ?? 0),
                'title' => (string) ($row['title'] ?? ''),
                'created_by' => (string) ($row['created_by'] ?? ''),
                'created_at' => (string) ($row['created_at'] ?? ''),
                'updated_at' => (string) ($row['updated_at'] ?? ''),
            ];
        }

        return null;
    }

    /**
     * Append a message and bump the conversation's updated_at so the recent list
     * orders by latest activity.
     *
     * @param list<array{title:string,source_url:string}> $sources
     */
    public function addMessage(int $conversationId, string $role, string $author, string $content, array $sources = []): void
    {
        $now = gmdate('Y-m-d H:i:s');
        $this->db->query(
            'INSERT INTO ' . ChatSchema::MESSAGES . ' (conversation_id, role, author, content, sources, created_at) VALUES (?, ?, ?, ?, ?, ?)',
            [$conversationId, $role, $this->clip($author, 128), $content, json_encode($sources), $now],
        );
        $this->db->query(
            'UPDATE ' . ChatSchema::CONVERSATIONS . ' SET updated_at = ? WHERE id = ?',
            [$now, $conversationId],
        );
    }

    /**
     * The most recently active conversations, newest first (shared across users).
     *
     * @return list<array{id:int,title:string,created_by:string,updated_at:string}>
     */
    public function recent(int $limit = 20): array
    {
        $rows = [];
        foreach ($this->db->query(
            'SELECT id, title, created_by, updated_at FROM ' . ChatSchema::CONVERSATIONS . ' ORDER BY updated_at DESC, id DESC LIMIT ?',
            [$limit],
        ) as $row) {
            $rows[] = [
                'id' => (int) ($row['id'] ?? 0),
                'title' => (string) ($row['title'] ?? ''),
                'created_by' => (string) ($row['created_by'] ?? ''),
                'updated_at' => (string) ($row['updated_at'] ?? ''),
            ];
        }

        return $rows;
    }

    /**
     * All messages in a conversation, oldest first, with sources decoded.
     *
     * @return list<array{role:string,author:string,content:string,sources:list<array{title:string,source_url:string}>,created_at:string}>
     */
    public function messages(int $conversationId): array
    {
        $rows = [];
        foreach ($this->db->query(
            'SELECT role, author, content, sources, created_at FROM ' . ChatSchema::MESSAGES . ' WHERE conversation_id = ? ORDER BY id ASC',
            [$conversationId],
        ) as $row) {
            $decoded = json_decode((string) ($row['sources'] ?? '[]'), true);
            $rows[] = [
                'role' => (string) ($row['role'] ?? ''),
                'author' => (string) ($row['author'] ?? ''),
                'content' => (string) ($row['content'] ?? ''),
                'sources' => is_array($decoded) ? $decoded : [],
                'created_at' => (string) ($row['created_at'] ?? ''),
            ];
        }

        return $rows;
    }

    private function clip(string $value, int $max): string
    {
        return mb_substr(trim($value), 0, $max);
    }
}
