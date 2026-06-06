<?php

declare(strict_types=1);

namespace App\Identity;

use Waaseyaa\Database\DatabaseInterface;

/**
 * Reads and writes Identity Workspace pillars in the app SQLite.
 *
 * Status + notes are the editable fields; every edit stamps last_edited_by /
 * last_edited_at so a change by one account is visible to the other. Seeding is
 * idempotent (guarded by a count) so the workspace opens fully populated on
 * first deploy and is never re-seeded over edits.
 */
final class PillarRepository
{
    public const STATUSES = ['defined', 'draft', 'work', 'gap'];

    public function __construct(private readonly DatabaseInterface $db) {}

    /**
     * Insert the artifact seed once, if the table is empty.
     */
    public function seedIfEmpty(): void
    {
        if ($this->count() > 0) {
            return;
        }

        foreach (IdentitySeed::pillars() as $p) {
            $this->db->query(
                'INSERT INTO ' . PillarSchema::TABLE
                . ' (pid, section, title, now_label, body, is_quote, decide_label, decision, status, notes, pills, is_full, sort_order, last_edited_by, last_edited_at)'
                . ' VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [
                    $p['pid'], $p['section'], $p['title'], $p['now_label'], $p['body'],
                    $p['is_quote'], $p['decide_label'], $p['decision'], $p['status'], '',
                    json_encode($p['pills']), $p['is_full'], $p['sort'], '', '',
                ],
            );
        }
    }

    public function count(): int
    {
        foreach ($this->db->query('SELECT COUNT(*) AS c FROM ' . PillarSchema::TABLE, []) as $row) {
            return (int) ($row['c'] ?? 0);
        }

        return 0;
    }

    /**
     * All pillars, ordered, with pills decoded.
     *
     * @return list<array<string,mixed>>
     */
    public function all(): array
    {
        $rows = [];
        foreach ($this->db->query('SELECT * FROM ' . PillarSchema::TABLE . ' ORDER BY sort_order ASC', []) as $row) {
            $decoded = json_decode((string) ($row['pills'] ?? '[]'), true);
            $row['pills'] = is_array($decoded) ? $decoded : [];
            $row['is_quote'] = (int) ($row['is_quote'] ?? 0);
            $row['is_full'] = (int) ($row['is_full'] ?? 0);
            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * @return array<string,mixed>|null
     */
    public function find(string $pid): ?array
    {
        foreach ($this->db->query('SELECT * FROM ' . PillarSchema::TABLE . ' WHERE pid = ?', [$pid]) as $row) {
            return $row;
        }

        return null;
    }

    /**
     * Update the editable fields (status and/or notes) and stamp the editor.
     *
     * @return array{last_edited_by:string, last_edited_at:string}|null null if pid unknown or nothing valid to change
     */
    public function update(string $pid, ?string $status, ?string $notes, string $editedBy): ?array
    {
        if ($this->find($pid) === null) {
            return null;
        }

        $sets = [];
        $args = [];
        if ($status !== null) {
            if (!in_array($status, self::STATUSES, true)) {
                return null;
            }
            $sets[] = 'status = ?';
            $args[] = $status;
        }
        if ($notes !== null) {
            $sets[] = 'notes = ?';
            $args[] = $notes;
        }
        if ($sets === []) {
            return null;
        }

        $editedAt = gmdate('Y-m-d H:i:s');
        $sets[] = 'last_edited_by = ?';
        $args[] = $editedBy;
        $sets[] = 'last_edited_at = ?';
        $args[] = $editedAt;
        $args[] = $pid;

        $this->db->query(
            'UPDATE ' . PillarSchema::TABLE . ' SET ' . implode(', ', $sets) . ' WHERE pid = ?',
            $args,
        );

        return ['last_edited_by' => $editedBy, 'last_edited_at' => $editedAt];
    }

    /**
     * Counts per status across all pillars (for the maturity bar).
     *
     * @return array{defined:int, draft:int, work:int, gap:int, total:int}
     */
    public function statusCounts(): array
    {
        $counts = ['defined' => 0, 'draft' => 0, 'work' => 0, 'gap' => 0];
        foreach ($this->db->query(
            'SELECT status, COUNT(*) AS c FROM ' . PillarSchema::TABLE . ' GROUP BY status',
            [],
        ) as $row) {
            $s = (string) ($row['status'] ?? '');
            if (isset($counts[$s])) {
                $counts[$s] = (int) $row['c'];
            }
        }
        $counts['total'] = $counts['defined'] + $counts['draft'] + $counts['work'] + $counts['gap'];

        return $counts;
    }
}
