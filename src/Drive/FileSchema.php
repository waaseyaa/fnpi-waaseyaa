<?php

declare(strict_types=1);

namespace App\Drive;

use Waaseyaa\Database\DatabaseInterface;

/**
 * Creates the Anokii Drive `drive_file` index table on demand.
 *
 * Same approach as the identity pillar schema: ensured at boot, guarded by
 * tableExists(), via DatabaseInterface (the framework's sanctioned abstraction
 * for app tables). The physical bytes live on the storage volume through the
 * Waaseyaa media layer (see DriveStorage); this table is the queryable index
 * the media layer does not model on its own: folder, shared listing, and the
 * uploader's display name. Lives in the app SQLite on the storage volume.
 */
final class FileSchema
{
    public const TABLE = 'drive_file';

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
                'uuid' => ['type' => 'varchar', 'length' => 36, 'not null' => true],
                // Display name (original filename as uploaded).
                'name' => ['type' => 'varchar', 'length' => 255, 'not null' => true],
                'mime_type' => ['type' => 'varchar', 'length' => 128, 'not null' => true],
                // Coarse type code for the UI icon: pdf|doc|xls|img|gen.
                'kind' => ['type' => 'varchar', 'length' => 8, 'not null' => true],
                'size_bytes' => ['type' => 'int', 'not null' => true],
                // Owner attribution: uid + cached label so the list never needs a join.
                'owner_id' => ['type' => 'int', 'not null' => true],
                'owner_label' => ['type' => 'varchar', 'length' => 128, 'not null' => true],
                // Folder / tag, e.g. "Global relationships".
                'folder' => ['type' => 'varchar', 'length' => 128, 'not null' => true],
                // Native media-layer URI (public://drive/<safe-name>).
                'storage_uri' => ['type' => 'varchar', 'length' => 255, 'not null' => true],
                'version' => ['type' => 'int', 'not null' => true],
                'uploaded_at' => ['type' => 'varchar', 'length' => 19, 'not null' => true],
            ],
            'primary key' => ['id'],
            'indexes' => [
                'idx_drive_uuid' => ['uuid'],
                'idx_drive_folder' => ['folder'],
                'idx_drive_uploaded' => ['uploaded_at'],
            ],
        ]);
    }
}
