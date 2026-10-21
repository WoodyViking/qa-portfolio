<?php
// Test bootstrap: the TEST config (fake secrets) and small HTTP/login helpers.
require '/var/www/html/<CONFIG_FILE>';
if (!defined('<SESSION_SECRET>') || <SESSION_SECRET> !== '<TEST_SECRET_VALUE>') {
    fwrite(STDERR, "Refusing: not the test config.\n");
    exit(1);
}

const TEST_ALICE = '100000000000000001';   // the owner
const TEST_BOB   = '100000000000000002';   // tester
const TEST_CAROL = '100000000000000003';   // no role
const TEST_ERIK  = '100000000000000005';   // tester: a second player

/** A hakoo_auth cookie for a test user, signed with the test secret (as hakoo's login does). */
function test_session_cookie(string $id, string $name): string
{
    $b = rtrim(strtr(base64_encode(json_encode(['id' => $id, 'name' => $name, 'avatar' => '', 'ts' => time()])), '+/', '-_'), '=');
    return 'hakoo_auth=' . $b . '.' . hash_hmac('sha256', $b, <SESSION_SECRET>);
}

/**
 * Call the Mysteries API: [status, decoded JSON]. $as = [id, name] logs in as that test user;
 * $device is a device token (header X-Mysteries-Device).
 */
function my_api(string $method, string $action, ?array $body = null, ?array $as = null, ?string $device = null, string $query = ''): array
{
    $headers = ['Content-Type: application/json'];
    if ($as) $headers[] = 'Cookie: ' . test_session_cookie($as[0], $as[1]);
    if ($device !== null) $headers[] = 'X-Mysteries-Device: ' . $device;
    $opts = ['method' => $method, 'header' => implode("\r\n", $headers), 'ignore_errors' => true];
    if ($body !== null) $opts['content'] = json_encode($body);
    $raw = file_get_contents((getenv('BASE_URL') ?: 'http://web') . "/mysteries/api.php?action=$action$query", false, stream_context_create(['http' => $opts]));
    $status = (int)explode(' ', $http_response_header[0] ?? 'HTTP/1.1 0')[1];
    return [$status, json_decode((string)$raw, true)];
}

/** GET a path on the test site: [status, headers, body]. */
function test_get(string $path, array $headers = []): array
{
    $ctx = stream_context_create(['http' => ['method' => 'GET', 'header' => implode("\r\n", $headers), 'ignore_errors' => true]]);
    $body = file_get_contents((getenv('BASE_URL') ?: 'http://web') . $path, false, $ctx);
    $status = (int)explode(' ', $http_response_header[0] ?? 'HTTP/1.1 0')[1];
    return [$status, $http_response_header ?? [], (string)$body];
}
