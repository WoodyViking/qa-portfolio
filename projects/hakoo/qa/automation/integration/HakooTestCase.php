<?php
use PHPUnit\Framework\TestCase;

/**
 * Shared setup for all integration tests.
 * Every test starts from the same known state: the fictional test data in
 * docker/db/init/90-test-data.sql (Alice, Bob, Carol and a WoT-only player).
 */
abstract class HakooTestCase extends TestCase
{
    const ALICE = '100000000000000001';   // admin, data in every table, WoT 500000011
    const BOB   = '100000000000000002';   // roster entry, WoT 500000012
    const CAROL = '100000000000000003';   // logged in once, no data
    const DAVE  = '100000000000000004';   // never logged in (no hakoo_users row)
    const WOT_ONLY = '500000099';         // WoT tracker user without Discord

    const DATA_TABLES = HakooTestData::DATA_TABLES;

    /** @var PDO */
    protected $pdo;

    protected function setUp(): void
    {
        $this->pdo = get_pdo();
        self::resetDatabase($this->pdo, true);
    }

    /**
     * Leave the fixture behind, not the last test's changes (some tests delete Alice and Bob),
     * so the Playwright run and phpMyAdmin see the test data after a PHPUnit run.
     */
    public static function tearDownAfterClass(): void
    {
        self::resetDatabase(get_pdo(), true);
    }

    /** Reload the fixture. $clearDeletionLog=false simulates restoring an old backup. */
    public static function resetDatabase(PDO $pdo, $clearDeletionLog)
    {
        HakooTestData::reset($pdo, $clearDeletionLog);
    }

    protected function rows($table, $where, array $params = [])
    {
        $s = $this->pdo->prepare("SELECT COUNT(*) FROM $table WHERE $where");
        $s->execute($params);
        return (int)$s->fetchColumn();
    }

    /** A valid hakoo_auth cookie for a Discord id, signed with the TEST secret. */
    protected function sessionCookie($discordId, $name = 'Tester', $secret = <SESSION_SECRET>)
    {
        $b = rtrim(strtr(base64_encode(json_encode(['id' => $discordId, 'name' => $name, 'avatar' => '', 'ts' => time()])), '+/', '-_'), '=');
        return 'hakoo_auth=' . $b . '.' . hash_hmac('sha256', $b, $secret);
    }

    /**
     * HTTP request to the web container. Returns [status, headers[], body, json|null].
     */
    protected function http($method, $path, $body = null, $cookie = null, array $headers = [])
    {
        if ($cookie) $headers[] = 'Cookie: ' . $cookie;
        if ($body !== null) $headers[] = 'Content-Type: application/json';
        $ctx = stream_context_create(['http' => [
            'method' => $method,
            'header' => implode("\r\n", $headers),
            'content' => $body === null ? '' : json_encode($body),
            'ignore_errors' => true,
            'follow_location' => 0,
            'timeout' => 15,
        ]]);
        $raw = file_get_contents(getenv('BASE_URL') . '/' . ltrim($path, '/'), false, $ctx);
        $respHeaders = http_get_last_response_headers() ?? [];
        preg_match('#HTTP/\S+\s+(\d{3})#', $respHeaders[0] ?? '', $m);
        return [(int)($m[1] ?? 0), $respHeaders, $raw, json_decode($raw, true)];
    }
}
