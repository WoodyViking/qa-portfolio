<?php
// Test mode over the API (system level): the host's test players and the invitation link.
// Traceability: US-26, K-121, K-122, K-123, K-12, K-13, K-41, AK-94, AK-95.
use PHPUnit\Framework\TestCase;

final class TestModeApiTest extends TestCase
{
    private const BOB   = [TEST_BOB, 'Bob'];
    private const CAROL = [TEST_CAROL, 'Carol'];
    private const ERIK  = [TEST_ERIK, 'Erik'];

    private function create(string $category = 'demo', array $over = []): array
    {
        [$status, $g] = my_api('POST', 'create', $over + ['category' => $category, 'lengthMin' => 60, 'waitMin' => 5, 'hostPlays' => true,
                                                          'boardLang' => 'sv'], self::BOB);
        $this->assertSame(200, $status, json_encode($g));
        return $g;
    }

    private function view(?array $as, string $token): array
    {
        [$status, $v] = my_api('GET', 'state', null, $as, $token);
        $this->assertSame(200, $status, json_encode($v));
        return $v['view'];
    }

    /** TC-API-045 (K-121, K-12, K-13, AK-94): the host's test players are players of their own, never the host */
    public function testTheHostsTestPlayers(): void
    {
        $g = $this->create();
        [, $e] = my_api('POST', 'join', ['code' => $g['code'], 'kind' => 'player'], self::ERIK);
        my_api('POST', 'approve', ['device' => $e['view']['you']['id'], 'ok' => true], self::BOB, $g['token']);
        [$status, $j] = my_api('POST', 'test_player', [], self::ERIK, $e['token']);
        $this->assertSame([403, 'not_host'], [$status, $j['error']], 'only the host adds test players');
        [$status] = my_api('GET', 'test_player', null, self::BOB, $g['token']);
        $this->assertSame(405, $status);

        [$status, $t] = my_api('POST', 'test_player', [], self::BOB, $g['token']);
        $this->assertSame(200, $status, json_encode($t));
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $t['token']);
        $this->assertSame(['Bob', 'Erik', 'Bob (2)'], array_column($t['view']['players'], 'name'), 'approved at once, a player of its own');
        $v = $this->view(self::BOB, $t['token']);
        $this->assertSame(['player', 'approved', false, 1], [$v['you']['kind'], $v['you']['status'], $v['you']['host'], $v['you']['test']]);
        $this->assertArrayNotHasKey('waiting', $v);
        $this->assertArrayNotHasKey('invite', $v, 'K-122: only the host sees the invitation');
        foreach (['start', 'approve', 'test_player', 'pause', 'end'] as $a) {
            [$status, $j] = my_api('POST', $a, [], self::BOB, $t['token']);
            $this->assertSame([403, 'not_host'], [$status, $j['error']], "a test player may not $a");
        }
        [$status] = my_api('GET', 'state', null, self::ERIK, $t['token']);
        $this->assertSame(401, $status, 'the token works only with the host\'s own login');
        [$status] = my_api('GET', 'state', null, null, $t['token']);
        $this->assertSame(401, $status);

        [$status, $j] = my_api('POST', 'start', [], self::BOB, $g['token']);
        $this->assertSame(200, $status, json_encode($j));
        $cards = [];
        foreach ([[self::BOB, $g['token']], [self::ERIK, $e['token']], [self::BOB, $t['token']]] as $i => [$as, $token]) {
            $v = $this->view($as, $token);
            $this->assertCount(2, $v['private']['cards']);
            $cards[$i] = $v['private']['cards'];
            $this->assertStringNotContainsString(TEST_BOB, json_encode($v));
        }
        $this->assertCount(6, array_unique(array_merge(...$cards)), 'AK-94: each has their own cards');
        $rows = get_pdo()->prepare('SELECT p.discord_id, p.seat FROM mysteries_private p JOIN mysteries_sessions s ON s.id = p.session_id WHERE s.code = ? ORDER BY p.discord_id, p.seat');
        $rows->execute([$g['code']]);
        $this->assertSame([[TEST_BOB, 0], [TEST_BOB, 1], [TEST_ERIK, 0]], array_map(fn($r) => [(string)$r[0], (int)$r[1]], $rows->fetchAll(PDO::FETCH_NUM)));

        [$status, $j] = my_api('POST', 'act', ['do' => 'reveal', 'card' => $cards[2][0]], self::BOB, $t['token']);
        $this->assertSame(200, $status, json_encode($j));
        $this->assertSame([['card' => $cards[2][0], 'by' => 'Bob (2)']], $j['view']['board']['revealed']);
        $this->assertCount(1, $j['view']['private']['cards'], 'its own card is gone, the host keeps both');
        $this->assertCount(2, $this->view(self::BOB, $g['token'])['private']['cards']);
        [$status, $j] = my_api('POST', 'act', ['do' => 'reveal', 'card' => $cards[0][0]], self::BOB, $t['token']);
        $this->assertSame(400, $status, 'the host\'s card is not the test player\'s');

        [$status, $j] = my_api('POST', 'test_player', [], self::BOB, $g['token']);
        $this->assertSame([409, 'not_lobby'], [$status, $j['error']], 'only in the lobby');
    }

    /** TC-API-045 (K-121): at most five test players; a game for two can start with the host and one test player */
    public function testFiveTestPlayersAndAGameForTwo(): void
    {
        $g = $this->create('escape', ['room' => 'runesmith']);
        [$status, $j] = my_api('POST', 'start', [], self::BOB, $g['token']);
        $this->assertSame([409, 'escape_players'], [$status, $j['error']]);
        $tokens = [];
        for ($n = 1; $n <= 5; $n++) {
            [$status, $t] = my_api('POST', 'test_player', [], self::BOB, $g['token']);
            $this->assertSame(200, $status, json_encode($t));
            $tokens[] = $t['token'];
        }
        [$status, $j] = my_api('POST', 'test_player', [], self::BOB, $g['token']);
        $this->assertSame([409, 'test_full'], [$status, $j['error']]);
        $this->assertSame(['Bob', 'Bob (2)', 'Bob (3)', 'Bob (4)', 'Bob (5)', 'Bob (6)'], array_column($j['view']['players'] ?? $this->view(self::BOB, $g['token'])['players'], 'name'));
        foreach (array_slice($tokens, 1) as $token) my_api('POST', 'leave', [], self::BOB, $token);
        [$status, $j] = my_api('POST', 'start', [], self::BOB, $g['token']);
        $this->assertSame(200, $status, json_encode($j));
        $this->assertSame(0, $j['view']['mine']['player']);
        $this->assertSame(1, $this->view(self::BOB, $tokens[0])['mine']['player'], 'the test player has the second seat');
        [$status, $j] = my_api('POST', 'act', ['do' => 'press'], self::BOB, $tokens[0]);
        $this->assertSame(200, $status, json_encode($j));
    }

    /** TC-API-046 (K-122, K-123, K-12, K-41, AK-95): the invitation lets a member without the role ask to join that game only */
    public function testTheInvitation(): void
    {
        $g = $this->create();
        $key = $g['view']['invite'] ?? null;
        $this->assertMatchesRegularExpression('/^[0-9a-f]{32}$/', (string)$key, 'the host sees the key in the lobby');
        $other = $this->create()['view']['invite'];
        $this->assertNotSame($key, $other, 'one key per game');

        foreach ([null, '', str_repeat('0', 32), $other, strtoupper($key), [$key], substr($key, 0, 31)] as $bad) {
            [$status, $j] = my_api('POST', 'join', ['code' => $g['code'], 'kind' => 'player', 'invite' => $bad], self::CAROL);
            $this->assertSame([403, 'not_tester'], [$status, $j['error']], json_encode($bad));
        }
        [$status, $j] = my_api('POST', 'join', ['code' => $g['code'], 'kind' => 'player', 'invite' => $key]);
        $this->assertSame([401, 'not_logged_in'], [$status, $j['error']], 'the link still needs a Discord login');

        [$status, $c] = my_api('POST', 'join', ['code' => $g['code'], 'kind' => 'player', 'invite' => $key], self::CAROL);
        $this->assertSame(200, $status, json_encode($c));
        $this->assertSame('waiting', $c['view']['you']['status'], 'K-12: still the host\'s yes');
        $this->assertArrayNotHasKey('players', $c['view']);
        $this->assertSame(['Carol'], array_column($this->view(self::BOB, $g['token'])['waiting'], 'name'));
        my_api('POST', 'approve', ['device' => $c['view']['you']['id'], 'ok' => true], self::BOB, $g['token']);
        $v = $this->view(self::CAROL, $c['token']);
        $this->assertSame(['approved', false], [$v['you']['status'], $v['you']['host']]);
        $this->assertSame(['Bob', 'Carol'], array_column($v['players'], 'name'));
        $this->assertArrayNotHasKey('invite', $v, 'only the host sees the key');

        [$status, $j] = my_api('POST', 'create', ['category' => 'demo', 'lengthMin' => 60], self::CAROL);
        $this->assertSame([403, 'not_tester'], [$status, $j['error']], 'the invitation does not make her a tester');
        [$status] = my_api('POST', 'start', [], self::BOB, $g['token']);
        $this->assertSame(200, $status);
        $this->assertArrayNotHasKey('invite', $this->view(self::BOB, $g['token']), 'shown in the lobby only');
        [$status, $j] = my_api('POST', 'join', ['code' => $g['code'], 'kind' => 'board', 'name' => 'TV', 'invite' => $key]);
        $this->assertSame(200, $status);
        $this->assertArrayNotHasKey('invite', $this->view(null, $j['token']));
    }
}
