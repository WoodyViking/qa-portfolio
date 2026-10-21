<?php
/**
 * System tests of Gomoku rewards and looks (src/gomoku.php, actions new/move/me/look/crowns).
 * Fixture: Alice has won on every level (crown shown), Bob on Easy only, Carol on none.
 * Traceability: FR-GOMOKU-12..16, US-17, US-18, FR-GDPR-01/02.
 */
class GomokuRewardsTest extends HakooTestCase
{
    private function api(string $action, array $body = [], ?string $cookie = null, ?string $token = null, string $query = ''): array
    {
        $method = in_array($action, ['me', 'crowns', 'state'], true) ? 'GET' : 'POST';
        $headers = $token ? ['X-Gomoku-Token: ' . $token] : [];
        return $this->http($method, "gomoku.php?action=$action$query", $method === 'POST' ? $body : null, $cookie, $headers);
    }

    /**
     * A computer game on $level where black (the player) has four in a row on
     * row 7 and it is black's turn: (7,4) wins. Returns [game, token].
     */
    private function winnableGame(string $level, ?string $cookie): array
    {
        [, , , $j] = $this->api('new', ['mode' => 'computer', 'color' => 'black', 'difficulty' => $level], $cookie);
        $moves = [];
        for ($k = 0; $k < 4; $k++) { $moves[] = [7, $k, 0]; $moves[] = [0, $k * 2, 0]; }
        $this->pdo->prepare('UPDATE hakoo_gomoku_games SET moves = ? WHERE id = ?')->execute([json_encode($moves), $j['game']]);
        return [$j['game'], $j['token'], $j['state']];
    }

    private function win(string $game, string $token): array
    {
        [, , , $m] = $this->api('move', ['game' => $game, 'row' => 7, 'col' => 4], null, $token);
        $this->assertSame('finished', $m['state']['status']);
        $this->assertSame('black', $m['state']['winner']);
        return $m;
    }

    private function profile(string $id): ?array
    {
        $s = $this->pdo->prepare('SELECT * FROM hakoo_gomoku_profile WHERE discord_id = ?');
        $s->execute([$id]);
        return $s->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /** TC-API-080 | FR-GOMOKU-12: a logged-in member's computer game counts; an anonymous one does not */
    public function test_only_logged_in_computer_games_count()
    {
        [$game, , $state] = $this->winnableGame('easy', $this->sessionCookie(self::CAROL));
        $this->assertTrue($state['rewardGame']);
        $this->assertSame(self::CAROL, (string)$this->pdo->query("SELECT discord_id FROM hakoo_gomoku_games WHERE id = '$game'")->fetchColumn());

        [$anon, , $state] = $this->winnableGame('easy', null);
        $this->assertFalse($state['rewardGame']);
        $this->assertNull($this->pdo->query("SELECT discord_id FROM hakoo_gomoku_games WHERE id = '$anon'")->fetchColumn());
    }

    /** TC-API-081 | FR-GOMOKU-12, US-17: the first win on a level unlocks its reward, once */
    public function test_first_win_unlocks_the_reward()
    {
        $carol = $this->sessionCookie(self::CAROL);
        [$game, $token] = $this->winnableGame('easy', $carol);
        $m = $this->win($game, $token);
        $this->assertSame('easy', $m['unlocked']);
        $p = $this->profile(self::CAROL);
        $this->assertNotNull($p['won_easy']);
        $this->assertNull($p['won_medium']);

        [$game, $token] = $this->winnableGame('easy', $carol);
        $this->assertNull($this->win($game, $token)['unlocked'], 'a second win on the same level unlocks nothing new');

        [$game, $token] = $this->winnableGame('hard', $carol);
        $this->assertSame('hard', $this->win($game, $token)['unlocked']);
    }

    /** TC-API-082 | FR-GOMOKU-12: winning without being logged in unlocks nothing */
    public function test_anonymous_win_unlocks_nothing()
    {
        [$game, $token] = $this->winnableGame('medium', null);
        $this->assertNull($this->win($game, $token)['unlocked']);
        $this->assertSame(2, (int)$this->pdo->query('SELECT COUNT(*) FROM hakoo_gomoku_profile')->fetchColumn(), 'only the fixture rows');
    }

    /** TC-API-083 | FR-GOMOKU-13: "me" reports unlocks and the look; locked parts fall back to the default */
    public function test_me_reports_unlocks_and_look()
    {
        [, , , $out] = $this->api('me');
        $this->assertSame(['status' => 'ok', 'loggedIn' => false], $out);

        [, , , $a] = $this->api('me', [], $this->sessionCookie(self::ALICE));
        $this->assertSame(['easy' => true, 'medium' => true, 'hard' => true], $a['unlocked']);
        $this->assertSame(['stoneBlack' => 'purple', 'stoneWhite' => 'gold', 'stones3d' => true, 'board' => 'hakoo', 'showCrown' => true], $a['look']);

        $this->pdo->exec("UPDATE hakoo_gomoku_profile SET stones_3d = 1 WHERE discord_id = " . self::BOB);   // not unlocked: ignored
        [, , , $b] = $this->api('me', [], $this->sessionCookie(self::BOB));
        $this->assertSame(['easy' => true, 'medium' => false, 'hard' => false], $b['unlocked']);
        $this->assertFalse($b['look']['stones3d']);
        $this->assertFalse($b['look']['showCrown']);
    }

    /** TC-API-084 | FR-GOMOKU-14: only unlocked parts of the look can be changed; the board is free */
    public function test_look_respects_unlocks()
    {
        $carol = $this->sessionCookie(self::CAROL);
        $base = ['stoneBlack' => 'black', 'stoneWhite' => 'white', 'stones3d' => false, 'board' => 'felt', 'showCrown' => true];
        [$status, , , $r] = $this->api('look', $base, $carol);
        $this->assertSame(200, $status);
        $this->assertSame('felt', $r['look']['board']);

        [$status, , , $e] = $this->api('look', ['stoneBlack' => 'red'] + $base, $carol);
        $this->assertSame([403, 'locked'], [$status, $e['error']]);
        [$status, , , $e] = $this->api('look', ['stones3d' => true] + $base, $carol);
        $this->assertSame([403, 'locked'], [$status, $e['error']]);

        $bob = $this->sessionCookie(self::BOB);
        [$status, , , $r] = $this->api('look', ['stoneBlack' => 'red', 'stoneWhite' => 'teal'] + $base, $bob);
        $this->assertSame(200, $status);
        $this->assertSame(['red', 'teal'], [$r['look']['stoneBlack'], $r['look']['stoneWhite']]);
        [$status, , , $e] = $this->api('look', ['stoneBlack' => 'red', 'stoneWhite' => 'red'] + $base, $bob);
        $this->assertSame([400, 'same_colors'], [$status, $e['error']]);
        [$status, , , $e] = $this->api('look', ['board' => '<script>'] + $base, $bob);
        $this->assertSame([400, 'bad_look'], [$status, $e['error']]);
        $this->assertNotNull($this->profile(self::BOB)['won_easy'], 'saving a look keeps the rewards');

        [$status, , , $e] = $this->api('look', $base);
        $this->assertSame([401, 'not_logged_in'], [$status, $e['error']]);
    }

    /** TC-API-085 | FR-GOMOKU-15: the public crown list has winners on Hard who show their crown, as strings */
    public function test_crowns()
    {
        [, $headers, , $c] = $this->api('crowns');
        $this->assertSame([self::ALICE], $c['ids']);
        $this->assertEmpty(array_filter($headers, fn($h) => stripos($h, 'Set-Cookie:') === 0));

        $alice = $this->sessionCookie(self::ALICE);
        $this->api('look', ['stoneBlack' => 'purple', 'stoneWhite' => 'gold', 'stones3d' => true, 'board' => 'hakoo', 'showCrown' => false], $alice);
        [, , , $c] = $this->api('crowns');
        $this->assertSame([], $c['ids']);
    }

    /** TC-API-086 | security: a forged login cookie is treated as not logged in */
    public function test_forged_cookie_is_ignored()
    {
        [, , , $out] = $this->api('me', [], $this->sessionCookie(self::ALICE, 'Tester', '<WRONG_SECRET_VALUE>'));
        $this->assertFalse($out['loggedIn']);
        [, , , $j] = $this->api('new', ['mode' => 'computer'], $this->sessionCookie(self::ALICE, 'Tester', '<WRONG_SECRET_VALUE>'));
        $this->assertFalse($j['state']['rewardGame']);
    }

    /** TC-API-087 | FR-GDPR-02: deleting a member removes their rewards and their computer games */
    public function test_delete_removes_rewards_and_games()
    {
        $dave = '100000000000000077';                           // a member only this test uses
        [$game, $token] = $this->winnableGame('easy', $this->sessionCookie($dave));
        $this->win($game, $token);
        $this->assertNotNull($this->profile($dave));
        hakoo_delete_user($this->pdo, $dave);
        $this->assertNull($this->profile($dave));
        $this->assertSame(0, $this->rows('hakoo_gomoku_games', 'id = ?', [$game]));
    }
}
