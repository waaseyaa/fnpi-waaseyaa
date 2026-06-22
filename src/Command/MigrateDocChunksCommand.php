<?php

declare(strict_types=1);

namespace App\Command;

use Anokii\Entity\DocChunk;
use Waaseyaa\CLI\Command\SymfonyCommandIO;
use Waaseyaa\Database\DatabaseInterface;
use Waaseyaa\Entity\Repository\EntityRepositoryInterface;

/**
 * `vendor/bin/waaseyaa app:migrate-doc-chunks [--dry-run] [--force]` — one-time,
 * idempotent migration of the Co-Intelligence RAG corpus from the raw
 * `anokii_doc_chunk` table into the package-canonical `doc_chunk` entity, so the
 * shared {@see \Anokii\CoIntelligence\GraphRetriever} reads exactly the passages
 * the forked App\CoIntelligence\Retriever used to read from the raw table.
 *
 * Faithful and non-destructive:
 *   - Each raw row becomes (or updates) a DocChunk entity keyed by its stable
 *     chunk_key. source_url/title/heading/text are copied verbatim;
 *     entity_type/entity_id stay empty (FNPI is a single flat vantage, no graph),
 *     which is exactly the "general content" the flat retriever expects.
 *   - Idempotent: a re-run upserts by chunk_key (0 created, N updated), so it is
 *     safe to run on every deploy.
 *   - The raw `anokii_doc_chunk` table is LEFT IN PLACE, never dropped or
 *     renamed, so rolling back to the previous engine (which reads it) needs only
 *     a redeploy of the prior ref, with no data restore.
 *   - Row-count parity is verified (distinct raw chunk_keys vs doc_chunk count);
 *     a shortfall fails the command non-zero, leaving the snapshot and raw table
 *     untouched.
 *
 * Safety: refuses to run unless a pre-migration DB snapshot exists next to the
 * SQLite file (a `*.bak` sibling, e.g. the deploy's
 * waaseyaa.sqlite.pretx-<ts>.bak from VACUUM INTO), unless --force is given. The
 * production deploy takes that snapshot immediately before invoking this command.
 */
final class MigrateDocChunksCommand
{
    private const RAW_TABLE = 'anokii_doc_chunk';

    public function __construct(
        private readonly EntityRepositoryInterface $chunks,
        private readonly DatabaseInterface $db,
        private readonly string $dbPath,
    ) {}

    public function run(SymfonyCommandIO $io): int
    {
        $dryRun = (bool) $io->option('dry-run');
        $force = (bool) $io->option('force');

        if (!$this->db->schema()->tableExists(self::RAW_TABLE)) {
            $io->writeln('  skip   no ' . self::RAW_TABLE . ' table present; nothing to migrate.');

            return 0;
        }

        if (!$dryRun && !$force && !$this->snapshotExists()) {
            $io->error(sprintf(
                'No DB snapshot found beside %s (expected a *.bak sibling such as %s.pretx-<ts>.bak). Take a snapshot first (the deploy does this via VACUUM INTO) or pass --force.',
                $this->dbPath,
                basename($this->dbPath),
            ));

            return 1;
        }

        // Read the raw corpus, dedupe by stable chunk_key (last row wins),
        // preserving id order so equal-score ties resolve as they did before.
        $rows = [];
        foreach ($this->db->query('SELECT chunk_key, source_url, title, heading, text FROM ' . self::RAW_TABLE . ' ORDER BY id ASC', []) as $row) {
            $key = (string) ($row['chunk_key'] ?? '');
            if ($key === '') {
                continue;
            }
            $rows[$key] = [
                'chunk_key' => $key,
                'source_url' => (string) ($row['source_url'] ?? ''),
                'title' => (string) ($row['title'] ?? ''),
                'heading' => (string) ($row['heading'] ?? ''),
                'text' => (string) ($row['text'] ?? ''),
            ];
        }
        $expected = count($rows);
        $io->writeln(sprintf('Found %d distinct chunk_key(s) in %s.', $expected, self::RAW_TABLE));

        if ($dryRun) {
            foreach (array_slice($rows, 0, 8) as $r) {
                $io->writeln(sprintf('  [%s] %s (%d chars)', $r['source_url'], $r['heading'] !== '' ? $r['heading'] : '(intro)', mb_strlen($r['text'])));
            }
            $io->writeln('Dry run: no changes written.');

            return 0;
        }

        // Existing entities by chunk_key, for an idempotent upsert.
        $byKey = [];
        foreach ($this->chunks->findBy([]) as $existing) {
            if ($existing instanceof DocChunk) {
                $byKey[$existing->getChunkKey()] = $existing;
            }
        }

        $created = 0;
        $updated = 0;
        foreach ($rows as $key => $r) {
            $existing = $byKey[$key] ?? null;
            if ($existing instanceof DocChunk) {
                $existing->set('source_url', $r['source_url']);
                $existing->set('title', $r['title']);
                $existing->set('heading', $r['heading']);
                $existing->set('text', $r['text']);
                $existing->set('entity_type', '');
                $existing->set('entity_id', '');
                $this->chunks->save($existing);
                $updated++;
                continue;
            }
            $this->chunks->save(DocChunk::make([
                'chunk_key' => $r['chunk_key'],
                'source_url' => $r['source_url'],
                'title' => $r['title'],
                'heading' => $r['heading'],
                'text' => $r['text'],
                'entity_type' => '',
                'entity_id' => '',
            ]));
            $created++;
        }

        $actual = $this->countEntities();
        $io->writeln(sprintf('doc_chunk migration: %d created, %d updated (%d entity rows total).', $created, $updated, $actual));

        if ($actual < $expected) {
            $io->error(sprintf(
                'Row-count parity FAILED: %d distinct raw chunk_keys but only %d doc_chunk entities. Snapshot intact; raw %s table untouched.',
                $expected,
                $actual,
                self::RAW_TABLE,
            ));

            return 1;
        }

        $io->writeln(sprintf('Row-count parity OK: %d distinct raw chunk_keys -> %d doc_chunk entities.', $expected, $actual));
        $io->writeln('Raw ' . self::RAW_TABLE . ' table left in place (rollback-safe).');

        return 0;
    }

    private function countEntities(): int
    {
        $n = 0;
        foreach ($this->chunks->findBy([]) as $_) {
            $n++;
        }

        return $n;
    }

    private function snapshotExists(): bool
    {
        $pattern = $this->dbPath . '*.bak';
        foreach (glob($pattern) ?: [] as $file) {
            if (is_file($file)) {
                return true;
            }
        }

        return false;
    }
}
