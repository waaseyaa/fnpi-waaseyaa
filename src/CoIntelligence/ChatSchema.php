<?php

declare(strict_types=1);

namespace App\CoIntelligence;

use Waaseyaa\Database\DatabaseInterface;

/**
 * Creates the Co-Intelligence tables on demand: ensured at boot, guarded by
 * tableExists(), via DatabaseInterface (the framework's sanctioned abstraction
 * for non-entity app tables, e.g. the RAG chunk index and conversations).
 * All three live in the app SQLite on the storage volume (sovereign at rest).
 *
 * - anokii_doc_chunk: the RAG knowledge base (heading-delimited passages).
 * - anokii_conversation: a chat thread, shared across the FNPI accounts.
 * - anokii_message: the turns in a thread, each stamped with its author.
 */
final class ChatSchema
{
    public const CHUNKS = 'anokii_doc_chunk';
    public const CONVERSATIONS = 'anokii_conversation';
    public const MESSAGES = 'anokii_message';

    public function __construct(private readonly DatabaseInterface $db) {}

    public function ensure(): void
    {
        $schema = $this->db->schema();

        if (!$schema->tableExists(self::CHUNKS)) {
            $schema->createTable(self::CHUNKS, [
                'fields' => [
                    'id' => ['type' => 'serial', 'not null' => true],
                    'chunk_key' => ['type' => 'varchar', 'length' => 255, 'not null' => true],
                    'source_url' => ['type' => 'varchar', 'length' => 255, 'not null' => true],
                    'title' => ['type' => 'varchar', 'length' => 255, 'not null' => true],
                    'heading' => ['type' => 'varchar', 'length' => 255],
                    'text' => ['type' => 'text'],
                    'created_at' => ['type' => 'varchar', 'length' => 19],
                ],
                'primary key' => ['id'],
                'indexes' => [
                    'idx_chunk_key' => ['chunk_key'],
                ],
            ]);
        }

        if (!$schema->tableExists(self::CONVERSATIONS)) {
            $schema->createTable(self::CONVERSATIONS, [
                'fields' => [
                    'id' => ['type' => 'serial', 'not null' => true],
                    'title' => ['type' => 'varchar', 'length' => 255, 'not null' => true],
                    'created_by' => ['type' => 'varchar', 'length' => 128],
                    'created_at' => ['type' => 'varchar', 'length' => 19],
                    'updated_at' => ['type' => 'varchar', 'length' => 19],
                ],
                'primary key' => ['id'],
                'indexes' => [
                    'idx_conv_updated' => ['updated_at'],
                ],
            ]);
        }

        if (!$schema->tableExists(self::MESSAGES)) {
            $schema->createTable(self::MESSAGES, [
                'fields' => [
                    'id' => ['type' => 'serial', 'not null' => true],
                    'conversation_id' => ['type' => 'int', 'not null' => true],
                    'role' => ['type' => 'varchar', 'length' => 16, 'not null' => true],
                    'author' => ['type' => 'varchar', 'length' => 128],
                    'content' => ['type' => 'text'],
                    'sources' => ['type' => 'text'],
                    'created_at' => ['type' => 'varchar', 'length' => 19],
                ],
                'primary key' => ['id'],
                'indexes' => [
                    'idx_msg_conv' => ['conversation_id'],
                ],
            ]);
        }
    }
}
