<?php

declare(strict_types=1);

namespace App\CoIntelligence;

use Waaseyaa\Database\DatabaseInterface;

/**
 * Reads and writes the RAG knowledge base (anokii_doc_chunk) in the app SQLite.
 *
 * Idempotent ingestion: chunks are keyed by a stable chunk_key, so a re-run
 * updates rows whose key is unchanged, inserts new ones, and (when pruning)
 * deletes stored chunks not regenerated this run, converging the index to
 * exactly the current corpus. Plain DatabaseInterface (a non-entity index
 * table, not an entity repository).
 */
final class DocChunkRepository
{
    public function __construct(private readonly DatabaseInterface $db) {}

    public function count(): int
    {
        foreach ($this->db->query('SELECT COUNT(*) AS c FROM ' . ChatSchema::CHUNKS, []) as $row) {
            return (int) ($row['c'] ?? 0);
        }

        return 0;
    }

    /**
     * @return list<array{chunk_key:string,source_url:string,title:string,heading:string,text:string}>
     */
    public function all(): array
    {
        $rows = [];
        foreach ($this->db->query('SELECT chunk_key, source_url, title, heading, text FROM ' . ChatSchema::CHUNKS, []) as $row) {
            $rows[] = [
                'chunk_key' => (string) ($row['chunk_key'] ?? ''),
                'source_url' => (string) ($row['source_url'] ?? ''),
                'title' => (string) ($row['title'] ?? ''),
                'heading' => (string) ($row['heading'] ?? ''),
                'text' => (string) ($row['text'] ?? ''),
            ];
        }

        return $rows;
    }

    /**
     * Upsert chunks by stable key and (optionally) prune any not seen this run.
     *
     * @param list<ChunkData> $chunks
     *
     * @return array{created:int, updated:int, deleted:int, total:int}
     */
    public function sync(array $chunks, bool $prune = true): array
    {
        $existing = [];
        foreach ($this->db->query('SELECT chunk_key FROM ' . ChatSchema::CHUNKS, []) as $row) {
            $existing[(string) ($row['chunk_key'] ?? '')] = true;
        }

        $seen = [];
        $created = 0;
        $updated = 0;
        $now = gmdate('Y-m-d H:i:s');

        foreach ($chunks as $c) {
            $seen[$c->chunkKey] = true;
            if (isset($existing[$c->chunkKey])) {
                $this->db->query(
                    'UPDATE ' . ChatSchema::CHUNKS . ' SET source_url = ?, title = ?, heading = ?, text = ? WHERE chunk_key = ?',
                    [$c->sourceUrl, $c->title, $c->heading, $c->text, $c->chunkKey],
                );
                $updated++;
                continue;
            }
            $this->db->query(
                'INSERT INTO ' . ChatSchema::CHUNKS . ' (chunk_key, source_url, title, heading, text, created_at) VALUES (?, ?, ?, ?, ?, ?)',
                [$c->chunkKey, $c->sourceUrl, $c->title, $c->heading, $c->text, $now],
            );
            $created++;
        }

        $deleted = 0;
        if ($prune) {
            foreach (array_keys($existing) as $key) {
                if (!isset($seen[$key])) {
                    $this->db->query('DELETE FROM ' . ChatSchema::CHUNKS . ' WHERE chunk_key = ?', [$key]);
                    $deleted++;
                }
            }
        }

        return ['created' => $created, 'updated' => $updated, 'deleted' => $deleted, 'total' => count($chunks)];
    }
}
