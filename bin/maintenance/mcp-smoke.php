<?php

declare(strict_types=1);

/**
 * MCP endpoint smoke test.
 *
 *   WAASEYAA_MCP_AGENT_TOKEN=<token> php bin/maintenance/mcp-smoke.php <base-url> [--write]
 *
 * Default mode is non-destructive: auth rejections, server card, tools/list,
 * a dry-run create, and the full publish-denial battery. --write additionally
 * creates and updates a real draft page titled "MCP wire-up test page" so its
 * id is printed for manual cleanup — do not use --write casually on prod.
 *
 * Exit code 0 = every check passed.
 */

$base = rtrim((string) ($argv[1] ?? 'http://127.0.0.1:8080'), '/');
$write = in_array('--write', $argv, true);
$token = (string) (getenv('WAASEYAA_MCP_AGENT_TOKEN') ?: '');
if ($token === '') {
    fwrite(STDERR, "Set WAASEYAA_MCP_AGENT_TOKEN in the environment.\n");
    exit(1);
}

$failures = 0;
$rpcId = 0;

function http(string $url, ?string $auth, ?array $payload): array
{
    $ch = curl_init($url);
    $headers = ['Content-Type: application/json'];
    if ($auth !== null) {
        $headers[] = 'Authorization: Bearer ' . $auth;
    }
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTPHEADER => $headers,
    ]);
    if ($payload !== null) {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload, JSON_THROW_ON_ERROR));
    }
    $body = (string) curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);

    return ['status' => $status, 'json' => json_decode($body, true)];
}

function call(string $base, string $token, string $tool, array $arguments): array
{
    global $rpcId;

    return http($base . '/mcp', $token, [
        'jsonrpc' => '2.0',
        'id' => ++$rpcId,
        'method' => 'tools/call',
        'params' => ['name' => $tool, 'arguments' => $arguments],
    ]);
}

function toolError(array $response): ?string
{
    if (($response['json']['result']['isError'] ?? false) !== true) {
        return null;
    }

    return (string) ($response['json']['result']['content'][0]['text'] ?? '');
}

function check(string $label, bool $pass, string $detail = ''): void
{
    global $failures;
    if (!$pass) {
        $failures++;
    }
    printf("[%s] %s%s\n", $pass ? 'PASS' : 'FAIL', $label, $detail !== '' ? " — $detail" : '');
}

// Server card.
$card = http($base . '/.well-known/mcp.json', null, null);
check('server card', $card['status'] === 200 && ($card['json']['endpoint'] ?? '') === '/mcp');

// Auth.
$noAuth = http($base . '/mcp', null, ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list']);
check('unauthenticated rejected (401/-32001)', $noAuth['status'] === 401 && ($noAuth['json']['error']['code'] ?? 0) === -32001);
$badAuth = http($base . '/mcp', str_repeat('f', 64), ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list']);
check('wrong token rejected (401/-32001)', $badAuth['status'] === 401 && ($badAuth['json']['error']['code'] ?? 0) === -32001);

// tools/list.
$list = http($base . '/mcp', $token, ['jsonrpc' => '2.0', 'id' => ++$rpcId, 'method' => 'tools/list']);
$names = array_map(static fn(array $t): string => $t['name'], $list['json']['result']['tools'] ?? []);
check('tools/list includes entity.create + entity.update', in_array('entity.create', $names, true) && in_array('entity.update', $names, true), implode(', ', $names));
check('revision-pointer tools hidden', !in_array('entity.set_current_revision', $names, true) && !in_array('entity.rollback', $names, true));

// Dry-run (non-destructive).
$dry = call($base, $token, 'entity.create', [
    'entity_type' => 'page',
    'values' => ['title' => 'MCP smoke dry-run', 'path' => '/mcp-smoke-dry-run', 'blocks' => []],
    'dry_run' => true,
]);
check('entity.create dry-run succeeds', isset($dry['json']['result']['content']) && ($dry['json']['result']['isError'] ?? false) !== true);

// Publish-denial battery (all non-destructive: every call must be refused).
check('deny update published_revision_id', str_contains((string) toolError(call($base, $token, 'entity.update', ['entity_type' => 'page', 'id' => 1, 'values' => ['published_revision_id' => 1]])), 'human-only'));
check('deny update page status', str_contains((string) toolError(call($base, $token, 'entity.update', ['entity_type' => 'page', 'id' => 1, 'values' => ['status' => 'published']])), 'human-only'));
check('deny update revision_id', str_contains((string) toolError(call($base, $token, 'entity.update', ['entity_type' => 'page', 'id' => 1, 'values' => ['revision_id' => 1]])), 'human-only'));
check('deny create with published_revision_id', str_contains((string) toolError(call($base, $token, 'entity.create', ['entity_type' => 'page', 'values' => ['title' => 'x', 'published_revision_id' => 1]])), 'human-only'));
$setCur = call($base, $token, 'entity.set_current_revision', ['entity_type' => 'page', 'id' => 1, 'revision_id' => 1]);
check('deny entity.set_current_revision', ($setCur['json']['error']['code'] ?? 0) === -32602 || str_contains((string) toolError($setCur), 'human-only'));
$rollback = call($base, $token, 'entity.rollback', ['entity_type' => 'page', 'id' => 1, 'revision_id' => 1]);
check('deny entity.rollback', ($rollback['json']['error']['code'] ?? 0) === -32602 || str_contains((string) toolError($rollback), 'human-only'));
check('deny entity.delete (capability)', str_contains((string) toolError(call($base, $token, 'entity.delete', ['entity_type' => 'page', 'id' => 1])), 'not permitted'));
check('deny entity.read user (scope)', str_contains((string) toolError(call($base, $token, 'entity.read', ['entity_type' => 'user', 'id' => 1])), 'workspace content'));
check('deny entity.update user (scope)', str_contains((string) toolError(call($base, $token, 'entity.update', ['entity_type' => 'user', 'id' => 1, 'values' => ['name' => 'x']])), 'workspace content'));

// Optional destructive leg.
if ($write) {
    $created = call($base, $token, 'entity.create', [
        'entity_type' => 'page',
        'values' => ['title' => 'MCP wire-up test page (safe to delete)', 'path' => '/mcp-smoke-test', 'blocks' => []],
        'revision_log' => 'mcp-smoke --write',
    ]);
    $data = $created['json']['result']['content'][0]['data'] ?? [];
    $id = (string) ($data['id'] ?? '');
    check('entity.create real draft', $id !== '', "page id=$id (REMEMBER TO DELETE)");
    if ($id !== '') {
        $updated = call($base, $token, 'entity.update', [
            'entity_type' => 'page',
            'id' => $id,
            'values' => ['title' => 'MCP wire-up test page (updated)'],
        ]);
        check('entity.update draft', isset($updated['json']['result']['content']) && ($updated['json']['result']['isError'] ?? false) !== true);
        $readBack = call($base, $token, 'entity.read', ['entity_type' => 'page', 'id' => $id]);
        $values = $readBack['json']['result']['content'][0]['data']['values'] ?? [];
        check('draft is unpublished', ($values['published_revision_id'] ?? 'missing') === null, 'published_revision_id=' . json_encode($values['published_revision_id'] ?? 'missing'));
    }
}

echo $failures === 0 ? "\nALL CHECKS PASSED\n" : "\n$failures CHECK(S) FAILED\n";
exit($failures === 0 ? 0 : 1);
