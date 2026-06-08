<?php

declare(strict_types=1);

namespace App\Command;

use App\Drive\DriveFileService;
use Waaseyaa\CLI\CliIO;
use Waaseyaa\Database\DatabaseInterface;

/**
 * `vendor/bin/waaseyaa app:migrate-drive` — one-time migration of Drive from the
 * raw `drive_file` table prototype to the entity-native, revisionable
 * `drive_asset` entity.
 *
 * Faithful and idempotent:
 *   - If drive_asset already has rows, do nothing (safe to re-run).
 *   - Else if the legacy raw `drive_file` table exists, copy every row verbatim
 *     into a DriveFile entity as its initial revision, preserving the uuid (so
 *     existing links stay valid), the storage_uri (the bytes are never touched),
 *     the metadata, and the uploader as the initial editor/time.
 *   - Else (fresh install) there is nothing to migrate; Drive starts empty.
 *
 * After a legacy migration the row counts are checked (in == out); only on a
 * match is the legacy table archived (renamed to drive_file_legacy_backup,
 * never dropped) so the migration is reversible.
 */
final class MigrateDriveCommand
{
    private const string LEGACY_TABLE = 'drive_file';
    private const string ARCHIVE_TABLE = 'drive_file_legacy_backup';

    public function __construct(
        private readonly DriveFileService $files,
        private readonly DatabaseInterface $db,
    ) {}

    public function run(CliIO $io): int
    {
        if ($this->files->count() > 0) {
            $io->writeln('  skip   drive_asset already populated. Nothing to do.');

            return 0;
        }

        $schema = $this->db->schema();
        if (!$schema->tableExists(self::LEGACY_TABLE)) {
            $io->writeln('No legacy drive_file table found; Drive starts empty (nothing to migrate).');

            return 0;
        }

        $rows = [];
        foreach ($this->db->query('SELECT * FROM ' . self::LEGACY_TABLE . ' ORDER BY id ASC', []) as $row) {
            $rows[] = $row;
        }
        $expected = count($rows);
        $io->writeln(sprintf('Migrating %d Drive file(s) from the legacy table, verbatim (bytes untouched).', $expected));

        foreach ($rows as $row) {
            $name = (string) ($row['name'] ?? '');
            $ownerLabel = (string) ($row['owner_label'] ?? '');
            $uploadedAt = (string) ($row['uploaded_at'] ?? '');

            $this->files->createFile(
                name: $name,
                mimeType: (string) ($row['mime_type'] ?? 'application/octet-stream'),
                kind: (string) ($row['kind'] ?? 'gen'),
                sizeBytes: (int) ($row['size_bytes'] ?? 0),
                ownerUid: (int) ($row['owner_id'] ?? 0),
                ownerLabel: $ownerLabel,
                folder: (string) ($row['folder'] ?? ''),
                storageUri: (string) ($row['storage_uri'] ?? ''),
                uploadedAt: $uploadedAt,
                editorUid: (int) ($row['owner_id'] ?? 0),
                editorLabel: $ownerLabel,
                updatedAt: $uploadedAt,
                revisionLog: 'Imported from prototype',
                uuid: (string) ($row['uuid'] ?? ''),
            );
            $io->writeln(sprintf('  add    %s%s', $name, $ownerLabel !== '' ? ' (' . $ownerLabel . ')' : ''));
        }

        $actual = $this->files->count();
        if ($actual !== $expected) {
            $io->error(sprintf('Migration count mismatch: read %d, wrote %d. Legacy table left in place; not archived.', $expected, $actual));

            return 1;
        }

        $this->archiveLegacy($io);
        $io->writeln(sprintf('Drive migration complete: %d files migrated verbatim, legacy table archived as %s.', $actual, self::ARCHIVE_TABLE));

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
}
