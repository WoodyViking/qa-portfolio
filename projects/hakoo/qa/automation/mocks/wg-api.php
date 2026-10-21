<?php
// ============================================================
// TEST DOUBLE (stub) for the Wargaming API. Runs as the `wgmock` service:
//   php -S 0.0.0.0:8000 wg-api.php
// Only what hakoo.eu's account linking uses:
//
//   GET /wot/auth/login/?redirect_uri=...[&mock_account=ID]
//       Pretends the player logged in at Wargaming and redirects back with
//       status=ok, account_id, nickname, expires_at and access_token
//       "valid-token-<ID>". Default ID 500000077 ("MockTanker").
//
//   GET /wot/account/info/?account_id=ID&access_token=T
//       T == "valid-token-<ID>"          -> ok, with the private block (owner)
//       T == "valid-token-<other ID>"    -> ok, private = null (not the owner)
//       anything else                    -> error 407 INVALID_ACCESS_TOKEN
// ============================================================

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
header('Content-Type: application/json; charset=utf-8');

// ------------------------------------------------------------
// Also a stub of Discord's REST API, for the bot's posts from the website
// (src/hakoo-discord.inc.php; config.test.php: DISCORD_API_BASE = /discord).
//   GET  /discord/guilds/G/members/ID   a member, except ids ending in 404 (not on the server)
//                                       and ids ending in 777 (a bot)
//   GET  /discord/guilds/G/channels     text channels #general, #board-game-challenges (id 900000000000000001), #reminders-spams (…004)
//                                       and #reports-from-hakoo-eu (id 900000000000000003)
//   GET  /discord/guilds/G/roles        roles @everyone, Hakoo testers (id 900000000000000010), WoT, and two
//                                       shown apart (hoisted) with a colour: HAKOO Owner (…20), Officers (…21)
//   GET  /discord/guilds/G/members      the member list: Alice (HAKOO Owner), Bob (Officers, Hakoo testers),
//                                       Carol (no role), a bot
//   PUT|DELETE /discord/guilds/G/members/ID/roles/R   recorded; 204 (404 for a role that is not there)
//   POST /discord/users/@me/channels    a DM channel (members whose id ends in 403 take no DMs: sending there is 403, code 50007)
//   POST|PATCH /discord/channels/C/messages[/M]   recorded; answers with a message id
//   GET  /discord/_log                  every recorded call, oldest first ({method, path, body})
//   POST /discord/_reset                forget them
// ------------------------------------------------------------
if (strpos($path, '/discord/') === 0) {
    $log = sys_get_temp_dir() . '/discord-mock.json';
    $calls = json_decode((string)@file_get_contents($log), true) ?: [];
    $p = substr($path, strlen('/discord'));
    $m = $_SERVER['REQUEST_METHOD'];
    if ($p === '/_log') { echo json_encode($calls); exit; }
    if ($p === '/_reset') { @unlink($log); echo '[]'; exit; }
    $body = json_decode((string)file_get_contents('php://input'), true);
    $calls[] = ['method' => $m, 'path' => $p, 'body' => $body];
    file_put_contents($log, json_encode($calls), LOCK_EX);
    if (preg_match('~^/guilds/\d+/members/(\d+)$~', $p, $x)) {
        if (substr($x[1], -3) === '404') { http_response_code(404); echo '{"message":"Unknown Member","code":10007}'; exit; }
        echo json_encode(['nick' => null, 'user' => ['id' => $x[1], 'username' => 'member' . substr($x[1], -4),
                          'global_name' => 'Member ' . substr($x[1], -4), 'bot' => substr($x[1], -3) === '777']]);
        exit;
    }
    if (preg_match('~^/guilds/\d+/roles$~', $p)) {
        echo json_encode([['id' => '900000000000000000', 'name' => '@everyone', 'position' => 0],
                          ['id' => '900000000000000010', 'name' => 'Hakoo testers', 'position' => 3],
                          ['id' => '900000000000000011', 'name' => 'WoT', 'position' => 2, 'color' => 0x3fb950],
                          ['id' => '900000000000000020', 'name' => 'HAKOO Owner', 'position' => 10, 'hoist' => true, 'color' => 0xe67e22],
                          ['id' => '900000000000000021', 'name' => 'Officers', 'position' => 8, 'hoist' => true, 'color' => 0x9b59b6]]);
        exit;
    }
    if (preg_match('~^/guilds/\d+/members$~', $p)) {
        $u = fn($id, $name) => ['id' => $id, 'username' => strtolower($name), 'global_name' => $name];
        echo json_encode([['nick' => null, 'roles' => ['900000000000000020', '900000000000000011'], 'user' => $u('100000000000000001', 'Alice')],
                          ['nick' => 'Bob the Officer', 'roles' => ['900000000000000010', '900000000000000021'], 'user' => $u('100000000000000002', 'Bob')],
                          ['nick' => null, 'roles' => [], 'user' => $u('100000000000000003', 'Carol')],
                          ['nick' => null, 'roles' => [], 'user' => $u('100000000000000777', 'Botty') + ['bot' => true]]]);
        exit;
    }
    if (preg_match('~^/guilds/\d+/members/\d+/roles/(\d+)$~', $p, $x) && in_array($m, ['PUT', 'DELETE'], true)) {
        if (!in_array($x[1], ['900000000000000010', '900000000000000011'], true)) { http_response_code(404); echo '{"message":"Unknown Role","code":10011}'; exit; }
        http_response_code(204);
        exit;
    }
    if (preg_match('~^/guilds/\d+/channels$~', $p)) {
        echo json_encode([['id' => '900000000000000009', 'type' => 2, 'name' => 'Voice', 'position' => 0],
                          ['id' => '900000000000000002', 'type' => 0, 'name' => 'general', 'position' => 1],
                          ['id' => '900000000000000001', 'type' => 0, 'name' => 'board-game-challenges', 'position' => 2],
                          ['id' => '900000000000000003', 'type' => 0, 'name' => 'reports-from-hakoo-eu', 'position' => 3],
                          ['id' => '900000000000000004', 'type' => 0, 'name' => 'reminders-spams', 'position' => 4]]);
        exit;
    }
    // A member whose id ends in 403 takes no direct messages: their DM channel refuses messages (Discord's 50007)
    if ($p === '/users/@me/channels') { echo json_encode(['id' => substr((string)($body['recipient_id'] ?? ''), -3) === '403' ? '900000000000000403' : '900000000000000050', 'type' => 1]); exit; }
    if ($p === '/channels/900000000000000403/messages') { http_response_code(403); echo '{"message":"Cannot send messages to this user","code":50007}'; exit; }
    if (preg_match('~^/channels/\d+/messages(/\d+)?$~', $p)) { echo json_encode(['id' => (string)(800000000000000000 + count($calls))]); exit; }
    http_response_code(404);
    echo '{"message":"404: Not Found"}';
    exit;
}

if (rtrim($path, '/') === '/wot/auth/login') {
    $id = ctype_digit($_GET['mock_account'] ?? '') ? $_GET['mock_account'] : '500000077';
    $back = (string)($_GET['redirect_uri'] ?? '');
    if (!preg_match('~^https?://~', $back)) { http_response_code(400); echo '{"status":"error"}'; exit; }
    $sep = strpos($back, '?') === false ? '?' : '&';
    header('Location: ' . $back . $sep . http_build_query([
        'status' => 'ok', 'access_token' => 'valid-token-' . $id, 'nickname' => 'MockTanker_' . $id,
        'account_id' => $id, 'expires_at' => time() + 14 * 86400,
    ]));
    exit;
}

if (rtrim($path, '/') === '/wot/account/info') {
    $id = (string)($_GET['account_id'] ?? '');
    $token = (string)($_GET['access_token'] ?? '');
    if (strpos($token, 'valid-token-') !== 0) {
        echo json_encode(['status' => 'error', 'error' => ['code' => 407, 'message' => 'INVALID_ACCESS_TOKEN']]);
        exit;
    }
    $owner = $token === 'valid-token-' . $id;
    echo json_encode(['status' => 'ok', 'data' => [$id => [
        'nickname' => 'MockTanker_' . $id,
        'private'  => $owner ? ['credits' => 123456] : null,
    ]]]);
    exit;
}

http_response_code(404);
echo json_encode(['status' => 'error', 'error' => ['message' => 'not mocked: ' . $path]]);
