<?php

declare(strict_types=1);

namespace App\Contact;

use Waaseyaa\Database\DatabaseInterface;

/**
 * Per-sender rate limiting for the public contact form, sovereignty-postured:
 * no third-party service and no raw IP retained. The key is a salted HMAC of
 * the client IP (secret = the app JWT secret), truncated to 32 hex chars, in
 * a non-entity counter table (the analytics-table convention: ensured at
 * boot, DatabaseInterface directly).
 *
 * Window: at most LIMIT submissions per ip-hash per hour. Old windows are
 * pruned opportunistically on each write.
 */
final class ContactRateLimiter
{
    public const TABLE = 'contact_rate';
    public const LIMIT_PER_WINDOW = 5;
    private const WINDOW_SECONDS = 3600;

    public function __construct(
        private readonly DatabaseInterface $db,
        private readonly string $secret,
    ) {}

    public function ensure(): void
    {
        $schema = $this->db->schema();
        if ($schema->tableExists(self::TABLE)) {
            return;
        }

        $schema->createTable(self::TABLE, [
            'fields' => [
                'id' => ['type' => 'serial', 'not null' => true],
                'ip_hash' => ['type' => 'varchar', 'length' => 32, 'not null' => true],
                'window_start' => ['type' => 'int', 'not null' => true],
                'hits' => ['type' => 'int', 'not null' => true],
            ],
            'primary key' => ['id'],
        ]);
    }

    /** A stable, salted, truncated hash of the client IP. Never the raw IP. */
    public function ipHash(?string $ip): string
    {
        return substr(hash_hmac('sha256', (string) $ip, $this->secret), 0, 32);
    }

    /**
     * Record one attempt and report whether it is within the limit. Counting
     * happens BEFORE the allow decision so a flood cannot reset its own window.
     */
    public function allow(string $ipHash, ?int $now = null): bool
    {
        $now ??= time();
        $window = intdiv($now, self::WINDOW_SECONDS) * self::WINDOW_SECONDS;

        $row = null;
        foreach ($this->db->query(
            'SELECT id, hits FROM ' . self::TABLE . ' WHERE ip_hash = ? AND window_start = ?',
            [$ipHash, $window],
        ) as $r) {
            $row = $r;
            break;
        }

        if ($row === null) {
            $this->db->query(
                'INSERT INTO ' . self::TABLE . ' (ip_hash, window_start, hits) VALUES (?, ?, 1)',
                [$ipHash, $window],
            );
            // Prune windows older than the previous one while we are here.
            $this->db->query('DELETE FROM ' . self::TABLE . ' WHERE window_start < ?', [$window - self::WINDOW_SECONDS]);

            return true;
        }

        $hits = (int) $row['hits'] + 1;
        $this->db->query('UPDATE ' . self::TABLE . ' SET hits = ? WHERE id = ?', [$hits, (int) $row['id']]);

        return $hits <= self::LIMIT_PER_WINDOW;
    }
}
