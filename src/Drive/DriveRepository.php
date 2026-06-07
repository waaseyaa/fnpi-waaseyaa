<?php

declare(strict_types=1);

namespace App\Drive;

use Symfony\Component\Uid\Uuid;
use Waaseyaa\Database\DatabaseInterface;

/**
 * Reads and writes the Drive file index in the app SQLite.
 *
 * One row per stored file: the listing/query metadata (name, kind, size,
 * uploader, folder, date) plus the media-layer storage URI that points at the
 * bytes on the sovereign volume. The Drive is Nation-shared: every signed-in
 * account sees every file, with per-file attribution to its uploader.
 */
final class DriveRepository
{
    public const string DEFAULT_FOLDER = 'General';

    public function __construct(private readonly DatabaseInterface $db) {}

    /**
     * Insert a file row and return it (decorated), keyed by a fresh UUID.
     *
     * @return array<string,mixed>
     */
    public function create(
        string $name,
        string $mimeType,
        int $sizeBytes,
        int $ownerId,
        string $ownerLabel,
        string $folder,
        string $storageUri,
    ): array {
        $uuid = Uuid::v4()->toRfc4122();
        $kind = FileTypes::kind($mimeType, $name);
        $uploadedAt = gmdate('Y-m-d H:i:s');
        $folder = trim($folder) !== '' ? trim($folder) : self::DEFAULT_FOLDER;

        $this->db->query(
            'INSERT INTO ' . FileSchema::TABLE
            . ' (uuid, name, mime_type, kind, size_bytes, owner_id, owner_label, folder, storage_uri, version, uploaded_at)'
            . ' VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [$uuid, $name, $mimeType, $kind, $sizeBytes, $ownerId, $ownerLabel, $folder, $storageUri, 1, $uploadedAt],
        );

        return $this->find($uuid) ?? [];
    }

    /**
     * All files, newest first, decorated for display.
     *
     * @return list<array<string,mixed>>
     */
    public function all(): array
    {
        $rows = [];
        foreach ($this->db->query(
            'SELECT * FROM ' . FileSchema::TABLE . ' ORDER BY uploaded_at DESC, id DESC',
            [],
        ) as $row) {
            $rows[] = $this->decorate($row);
        }

        return $rows;
    }

    /**
     * @return array<string,mixed>|null
     */
    public function find(string $uuid): ?array
    {
        foreach ($this->db->query('SELECT * FROM ' . FileSchema::TABLE . ' WHERE uuid = ?', [$uuid]) as $row) {
            return $this->decorate($row);
        }

        return null;
    }

    /**
     * Delete a file row, returning its storage URI so the caller can remove the
     * bytes, or null if the UUID is unknown.
     */
    public function delete(string $uuid): ?string
    {
        $row = $this->find($uuid);
        if ($row === null) {
            return null;
        }

        $this->db->query('DELETE FROM ' . FileSchema::TABLE . ' WHERE uuid = ?', [$uuid]);

        return (string) $row['storage_uri'];
    }

    /**
     * Distinct folder names, alphabetical.
     *
     * @return list<string>
     */
    public function folders(): array
    {
        $folders = [];
        foreach ($this->db->query(
            'SELECT DISTINCT folder FROM ' . FileSchema::TABLE . ' ORDER BY folder ASC',
            [],
        ) as $row) {
            $folder = (string) ($row['folder'] ?? '');
            if ($folder !== '') {
                $folders[] = $folder;
            }
        }

        return $folders;
    }

    public function count(): int
    {
        foreach ($this->db->query('SELECT COUNT(*) AS c FROM ' . FileSchema::TABLE, []) as $row) {
            return (int) ($row['c'] ?? 0);
        }

        return 0;
    }

    /**
     * @param array<string,mixed> $row
     * @return array<string,mixed>
     */
    private function decorate(array $row): array
    {
        $row['size_bytes'] = (int) ($row['size_bytes'] ?? 0);
        $row['version'] = (int) ($row['version'] ?? 1);
        $row['size_human'] = FileTypes::humanSize($row['size_bytes']);
        $row['is_image'] = ($row['kind'] ?? '') === 'img';

        return $row;
    }
}
