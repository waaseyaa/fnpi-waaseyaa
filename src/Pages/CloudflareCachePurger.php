<?php

declare(strict_types=1);

namespace App\Pages;

/**
 * Purges the Cloudflare edge cache for the site zone, so a publish is visible
 * to every visitor immediately (Cloudflare can otherwise serve stale HTML from
 * a colo or an intermediary until it expires).
 *
 * Configuration is server-side only, via the container env (fnpi.env on the
 * Pi, mode 0600 — same delivery as the other secrets):
 *   CLOUDFLARE_PURGE_TOKEN  an API token scoped to Zone > Cache Purge for the
 *                           fnprocure.ca zone (nothing broader)
 *   CLOUDFLARE_ZONE_ID      the zone id (Cloudflare dashboard > Overview)
 *
 * Fail-soft by design: an unconfigured or failing purge NEVER blocks a
 * publish — the published pointer is already flipped; the purge is cache
 * hygiene, not correctness. purgeAll() returns null when unconfigured, true
 * on a confirmed purge, false on an API failure (callers may log).
 */
final class CloudflareCachePurger
{
    public function isConfigured(): bool
    {
        return $this->token() !== null && $this->zoneId() !== null;
    }

    public function purgeAll(): ?bool
    {
        $token = $this->token();
        $zone = $this->zoneId();
        if ($token === null || $zone === null) {
            return null;
        }

        $ch = curl_init('https://api.cloudflare.com/client/v4/zones/' . rawurlencode($zone) . '/purge_cache');
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => '{"purge_everything":true}',
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $token,
                'Content-Type: application/json',
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 15,
        ]);
        $body = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);

        if (!is_string($body) || $status !== 200) {
            return false;
        }
        $json = json_decode($body, true);

        return is_array($json) && ($json['success'] ?? false) === true;
    }

    private function token(): ?string
    {
        $v = getenv('CLOUDFLARE_PURGE_TOKEN');

        return is_string($v) && $v !== '' ? $v : null;
    }

    private function zoneId(): ?string
    {
        $v = getenv('CLOUDFLARE_ZONE_ID');

        return is_string($v) && $v !== '' ? $v : null;
    }
}
