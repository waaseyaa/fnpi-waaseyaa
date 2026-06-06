<?php

declare(strict_types=1);

namespace App\Identity;

use Waaseyaa\Database\DatabaseInterface;

/**
 * Creates the Anokii identity `pillar` table on demand.
 *
 * Same approach as the analytics schema: ensured at boot, guarded by
 * tableExists(), via DatabaseInterface (the framework's sanctioned abstraction
 * for app tables). Lives in the app SQLite on the storage volume (sovereign).
 */
final class PillarSchema
{
    public const TABLE = 'pillar';

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
                'pid' => ['type' => 'varchar', 'length' => 64, 'not null' => true],
                'section' => ['type' => 'varchar', 'length' => 32, 'not null' => true],
                'title' => ['type' => 'varchar', 'length' => 255, 'not null' => true],
                'now_label' => ['type' => 'varchar', 'length' => 80],
                'body' => ['type' => 'text'],
                'is_quote' => ['type' => 'int'],
                'decide_label' => ['type' => 'varchar', 'length' => 64],
                'decision' => ['type' => 'text'],
                'status' => ['type' => 'varchar', 'length' => 16, 'not null' => true],
                'notes' => ['type' => 'text'],
                'pills' => ['type' => 'text'],
                'is_full' => ['type' => 'int'],
                'sort_order' => ['type' => 'int', 'not null' => true],
                'last_edited_by' => ['type' => 'varchar', 'length' => 128],
                'last_edited_at' => ['type' => 'varchar', 'length' => 19],
            ],
            'primary key' => ['id'],
            'indexes' => [
                'idx_pillar_pid' => ['pid'],
                'idx_pillar_sort' => ['sort_order'],
            ],
        ]);
    }
}
