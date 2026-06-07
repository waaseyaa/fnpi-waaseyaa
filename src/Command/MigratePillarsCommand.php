<?php

declare(strict_types=1);

namespace App\Command;

use App\Identity\IdentitySeed;
use App\Identity\PillarService;
use Waaseyaa\CLI\CliIO;
use Waaseyaa\Database\DatabaseInterface;

/**
 * `vendor/bin/waaseyaa app:migrate-pillars` — one-time migration of the Identity
 * Workspace from the raw `pillar` table prototype to the entity-native,
 * revisionable `identity_pillar` entity.
 *
 * Faithful and idempotent:
 *   - If identity_pillar already has rows, do nothing (safe to re-run).
 *   - Else if the legacy raw `pillar` table exists, copy every row verbatim
 *     (status, notes, attribution unchanged) into a Pillar entity as its initial
 *     revision, stamped with the row's last_edited_by / last_edited_at.
 *   - Else (fresh install) seed from IdentitySeed defaults so the tool is never
 *     empty in dev.
 *
 * After a legacy migration, the row counts are checked (in == out); only on a
 * match is the legacy table archived (renamed to pillar_legacy_backup, never
 * dropped) so nothing recreates or reads it.
 */
final class MigratePillarsCommand
{
    private const LEGACY_TABLE = 'pillar';
    private const ARCHIVE_TABLE = 'pillar_legacy_backup';

    public function __construct(
        private readonly PillarService $pillars,
        private readonly DatabaseInterface $db,
    ) {}

    public function run(CliIO $io): int
    {
        if ($this->pillars->count() > 0) {
            $io->writeln('  skip   identity_pillar already populated. Nothing to do.');

            return 0;
        }

        $schema = $this->db->schema();
        if ($schema->tableExists(self::LEGACY_TABLE)) {
            return $this->migrateLegacy($io);
        }

        return $this->seedFresh($io);
    }

    private function migrateLegacy(CliIO $io): int
    {
        $rows = [];
        foreach ($this->db->query('SELECT * FROM ' . self::LEGACY_TABLE . ' ORDER BY sort_order ASC', []) as $row) {
            $rows[] = $row;
        }
        $expected = count($rows);
        $io->writeln(sprintf('Migrating %d pillar(s) from the legacy table, verbatim.', $expected));

        foreach ($rows as $row) {
            $decoded = json_decode((string) ($row['pills'] ?? '[]'), true);
            $pills = is_array($decoded) ? $this->normalizePills($decoded) : [];
            $editedBy = trim((string) ($row['last_edited_by'] ?? ''));
            $editedAt = trim((string) ($row['last_edited_at'] ?? ''));

            $this->pillars->createPillar(
                pid: (string) ($row['pid'] ?? ''),
                section: (string) ($row['section'] ?? ''),
                title: (string) ($row['title'] ?? ''),
                nowLabel: (string) ($row['now_label'] ?? ''),
                body: (string) ($row['body'] ?? ''),
                isQuote: (int) ($row['is_quote'] ?? 0) === 1,
                decideLabel: (string) ($row['decide_label'] ?? ''),
                decision: (string) ($row['decision'] ?? ''),
                status: (string) ($row['status'] ?? 'gap'),
                notes: (string) ($row['notes'] ?? ''),
                pills: $pills,
                isFull: (int) ($row['is_full'] ?? 0) === 1,
                sortOrder: (int) ($row['sort_order'] ?? 0),
                editorUid: 0,
                editorLabel: $editedBy,
                updatedAt: $editedAt,
                revisionLog: 'Imported from prototype',
            );
            $io->writeln(sprintf('  add    %-14s %-8s%s', (string) ($row['pid'] ?? '?'), (string) ($row['status'] ?? '?'), $editedBy !== '' ? ' (by ' . $editedBy . ')' : ''));
        }

        $actual = $this->pillars->count();
        if ($actual !== $expected) {
            $io->error(sprintf('Migration count mismatch: read %d, wrote %d. Legacy table left in place; not archived.', $expected, $actual));

            return 1;
        }

        $this->archiveLegacy($io);
        $io->writeln(sprintf('Identity migration complete: %d pillars migrated verbatim, legacy table archived as %s.', $actual, self::ARCHIVE_TABLE));

        return 0;
    }

    private function seedFresh(CliIO $io): int
    {
        $seed = IdentitySeed::pillars();
        $io->writeln(sprintf('No legacy table found. Seeding %d pillar(s) from defaults (fresh install).', count($seed)));

        foreach ($seed as $p) {
            $this->pillars->createPillar(
                pid: (string) $p['pid'],
                section: (string) $p['section'],
                title: (string) $p['title'],
                nowLabel: (string) $p['now_label'],
                body: (string) $p['body'],
                isQuote: (int) $p['is_quote'] === 1,
                decideLabel: (string) $p['decide_label'],
                decision: (string) $p['decision'],
                status: (string) $p['status'],
                notes: '',
                pills: $this->normalizePills($p['pills']),
                isFull: (int) $p['is_full'] === 1,
                sortOrder: (int) $p['sort'],
                editorUid: 0,
                editorLabel: '',
                updatedAt: '',
                revisionLog: 'Initial seed',
            );
        }

        $io->writeln(sprintf('Seeded %d pillars.', $this->pillars->count()));

        return 0;
    }

    private function archiveLegacy(CliIO $io): void
    {
        $schema = $this->db->schema();
        if ($schema->tableExists(self::ARCHIVE_TABLE)) {
            $io->writeln('  note   ' . self::ARCHIVE_TABLE . ' already exists; leaving the legacy table in place.');

            return;
        }
        try {
            $this->db->query('ALTER TABLE ' . self::LEGACY_TABLE . ' RENAME TO ' . self::ARCHIVE_TABLE, []);
        } catch (\Throwable $e) {
            $io->error('Could not archive the legacy table: ' . $e->getMessage());
        }
    }

    /**
     * @param mixed $pills
     * @return list<array{t:string,cyan:bool}>
     */
    private function normalizePills(mixed $pills): array
    {
        if (!is_array($pills)) {
            return [];
        }
        $out = [];
        foreach ($pills as $pill) {
            if (is_array($pill)) {
                $out[] = ['t' => (string) ($pill['t'] ?? ''), 'cyan' => (bool) ($pill['cyan'] ?? false)];
            }
        }

        return $out;
    }
}
