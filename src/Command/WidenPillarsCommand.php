<?php

declare(strict_types=1);

namespace App\Command;

use Waaseyaa\CLI\CliIO;
use Waaseyaa\Database\DatabaseInterface;
use Waaseyaa\Database\DBALDatabase;
use Waaseyaa\Entity\EntityTypeManager;
use Waaseyaa\EntityStorage\SqlSchemaHandler;

/**
 * `vendor/bin/waaseyaa app:widen-pillars` — one-time, idempotent migration of
 * the `identity_pillar` BASE table to the two-axis shape: the primary key is
 * widened to `(id, langcode)` and `langcode` / `default_langcode` columns are
 * added. Every existing row becomes its English (default-language) peer; the
 * single-axis revision history (`identity_pillar_revision`) is preserved
 * untouched, and the per-language revision table
 * (`identity_pillar__translation__revision`) is created empty.
 *
 * Safe and reversible:
 *   - If `identity_pillar` already has a `langcode` column, do nothing.
 *   - Otherwise rebuild the table (SQLite cannot ALTER a primary key): rename the
 *     old table aside, let `SqlSchemaHandler` build the correct translatable
 *     schema (no DDL drift), copy every row verbatim with `langcode = 'en'` /
 *     `default_langcode = 'en'`, and verify in == out before keeping the result.
 *     The pre-migration table is archived (renamed, never dropped) as
 *     `identity_pillar_pretx_backup`, and on any failure the rename is rolled
 *     back so the original table is restored.
 *
 * The live migration is gated by an external database snapshot (the operator
 * snapshots first); this command's backup table is a second, in-database net.
 */
final class WidenPillarsCommand
{
    private const TABLE = 'identity_pillar';
    private const BACKUP = 'identity_pillar_pretx_backup';
    private const DEFAULT_LANGCODE = 'en';

    public function __construct(
        private readonly EntityTypeManager $entityTypeManager,
        private readonly DatabaseInterface $db,
    ) {}

    public function run(CliIO $io): int
    {
        $schema = $this->db->schema();
        if (!$schema->tableExists(self::TABLE)) {
            $io->error(self::TABLE . ' does not exist. Run app:migrate-pillars first.');

            return 1;
        }
        if (!$this->db instanceof DBALDatabase) {
            $io->error('Widening requires a DBAL (SQLite) connection.');

            return 1;
        }
        // The definitive two-axis marker is the COMPOSITE primary key
        // (id, langcode) — not merely a langcode column, which a half-migrated
        // table can carry without the widened key.
        if ($this->primaryKeyOf(self::TABLE) === ['id', 'langcode']) {
            $io->writeln('  skip   identity_pillar is already two-axis (composite (id, langcode) primary key).');

            return 0;
        }
        if ($schema->tableExists(self::BACKUP)) {
            $io->error('A previous backup ' . self::BACKUP . ' exists; resolve it before re-running.');

            return 1;
        }

        $allColumns = $this->columnsOf(self::TABLE);
        if ($allColumns === []) {
            $io->error('Could not introspect identity_pillar columns.');

            return 1;
        }
        // Columns to copy verbatim: everything except the two-axis system columns
        // (which the new schema supplies). A half-migrated table may already carry
        // a `langcode` value — preserve it; otherwise default to English.
        $copyColumns = array_values(array_filter(
            $allColumns,
            static fn(string $c): bool => $c !== 'langcode' && $c !== 'default_langcode',
        ));
        $langcodeExpr = in_array('langcode', $allColumns, true) ? 'langcode' : "'" . self::DEFAULT_LANGCODE . "'";

        $before = $this->rowCount(self::TABLE);
        $io->writeln(sprintf('Widening identity_pillar to (id, langcode); %d row(s) become their English peer.', $before));

        $entityType = $this->entityTypeManager->getDefinition(self::TABLE);
        $conn = $this->db->getConnection();

        $conn->executeStatement('ALTER TABLE ' . self::TABLE . ' RENAME TO ' . self::BACKUP);
        try {
            // SQLite keeps index NAMES globally when a table is renamed, so the
            // old (now backup) table still owns e.g. `identity_pillar_bundle`.
            // Drop those user indexes before the new schema recreates them.
            foreach ($this->userIndexesOf(self::BACKUP) as $index) {
                $conn->executeStatement('DROP INDEX IF EXISTS ' . $index);
            }

            // Build the correct two-axis schema for the (now absent) base table.
            new SqlSchemaHandler($entityType, $this->db)->ensureTable();

            $cols = implode(', ', $copyColumns);
            $conn->executeStatement(sprintf(
                "INSERT INTO %s (%s, langcode, default_langcode) SELECT %s, %s, '%s' FROM %s",
                self::TABLE,
                $cols,
                $cols,
                $langcodeExpr,
                self::DEFAULT_LANGCODE,
                self::BACKUP,
            ));

            $after = $this->rowCount(self::TABLE);
            if ($after !== $before) {
                throw new \RuntimeException(sprintf('Row count mismatch after copy: %d in, %d out.', $before, $after));
            }
        } catch (\Throwable $e) {
            if ($this->db->schema()->tableExists(self::TABLE)) {
                $conn->executeStatement('DROP TABLE ' . self::TABLE);
            }
            $conn->executeStatement('ALTER TABLE ' . self::BACKUP . ' RENAME TO ' . self::TABLE);
            $io->error('Widening failed and was rolled back: ' . $e->getMessage());

            return 1;
        }

        // Create the (empty) per-language revision table.
        new SqlSchemaHandler($entityType, $this->db)->ensureTranslationRevisionTable();

        $io->writeln(sprintf('  done   identity_pillar widened; %d English peer row(s). Backup kept as %s.', $before, self::BACKUP));
        $io->writeln('         identity_pillar__translation__revision created (empty).');

        return 0;
    }

    /** @return list<string> */
    private function columnsOf(string $table): array
    {
        $cols = [];
        foreach ($this->db->query('PRAGMA table_info(' . $table . ')', []) as $row) {
            $name = (string) ($row['name'] ?? '');
            if ($name !== '') {
                $cols[] = $name;
            }
        }

        return $cols;
    }

    /**
     * User-created (named) indexes on a table — excludes SQLite's implicit
     * `sqlite_autoindex_*` entries, which cannot be dropped directly and do not
     * collide with CREATE INDEX names.
     *
     * @return list<string>
     */
    private function userIndexesOf(string $table): array
    {
        $indexes = [];
        foreach ($this->db->query('PRAGMA index_list(' . $table . ')', []) as $row) {
            $name = (string) ($row['name'] ?? '');
            if ($name !== '' && !str_starts_with($name, 'sqlite_autoindex')) {
                $indexes[] = $name;
            }
        }

        return $indexes;
    }

    /**
     * The primary-key columns of a table, in key order (SQLite `pk` ordinal).
     *
     * @return list<string>
     */
    private function primaryKeyOf(string $table): array
    {
        $pk = [];
        foreach ($this->db->query('PRAGMA table_info(' . $table . ')', []) as $row) {
            $ordinal = (int) ($row['pk'] ?? 0);
            if ($ordinal > 0) {
                $pk[$ordinal] = (string) ($row['name'] ?? '');
            }
        }
        ksort($pk);

        return array_values($pk);
    }

    private function rowCount(string $table): int
    {
        foreach ($this->db->query('SELECT COUNT(*) AS c FROM ' . $table, []) as $row) {
            return (int) ($row['c'] ?? 0);
        }

        return 0;
    }
}
