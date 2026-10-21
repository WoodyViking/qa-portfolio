<?php
/**
 * System tests of the Gomoku API (src/gomoku.php) over HTTP.
 * Traceability: FR-GOMOKU-01..09, FR-GAMES-01/02; school spec FR-02, FR-06..10.
 */
class GomokuApiTest extends HakooTestCase
{
    private function api(string $action, array $body = [], ?string $token = null, string $query = ''): array
    {
        $headers = $token ? ['X-Gomoku-Token: ' . $token] : [];
        $method = $action === 'state' ? 'GET' : 'POST';
        return $this->http($method, "gomoku.php?action=$action$query", $method === 'POST' ? $body : null, null, $headers);
    }

    /** Start a computer game where the player is black (the computer has not moved). */
    private function computerGame(string $level = 'medium'): array
    {
        [$status, , , $j] = $this->api('new', ['mode' => 'computer', 'color' => 'black', 'difficulty' => $level, 'name' => 'Tester']);
        $this->assertSame(200, $status);
        return $j;
    }

    /** A started friend game: [game id, black token, white token]. */
    private function friendGame(): array
    {
        [, , , $a] = $this->api('new', ['mode' => 'friend', 'color' => 'black', 'name' => 'Alice']);
        [, , , $b] = $this->api('join', ['game' => $a['game'], 'invite' => $a['invite'], 'name' => 'Bob']);
        return [$a['game'], $a['token'], $b['token']];
    }

    private function move(string $game, string $token, int $row, int $col): array
    {
        return $this->api('move', ['game' => $game, 'row' => $row, 'col' => $col], $token);
    }

    /** TC-API-060 | FR-03.3, FR-06.1, FR-02.4: a computer game starts empty, black to move */
    public function test_new_computer_game()
    {
        $j = $this->computerGame();
        $this->assertMatchesRegularExpression('/^[0-9a-f]{16}$/', $j['game']);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{32}$/', $j['token']);
        $s = $j['state'];
        $this->assertSame('active', $s['status']);
        $this->assertSame('black', $s['you']);
        $this->assertSame('white', $s['computer']);
        $this->assertSame(['black' => 'Tester', 'white' => 'Computer'], $s['names']);
        $this->assertSame([], $s['moves']);
        $this->assertSame(15, $s['size']);
    }

    /** TC-API-061 | FR-04.3, FR-06.2: when the computer plays black it opens in the centre */
    public function test_computer_opens_when_it_is_black()
    {
        [, , , $j] = $this->api('new', ['mode' => 'computer', 'color' => 'white', 'size' => 19]);
        $this->assertSame([[9, 9]], $j['state']['moves']);
        $this->assertSame('white', $j['state']['toMove']);
        $this->assertSame('Player 1', $j['state']['names']['white'], 'FR-02.4 default name');
    }

    /** TC-API-062 | FR-08.1, FR-08.4, FR-06.2: a move is stored and the computer answers in the same call */
    public function test_move_and_computer_reply()
    {
        $j = $this->computerGame('hard');
        [$status, , , $m] = $this->move($j['game'], $j['token'], 7, 7);
        $this->assertSame(200, $status);
        $this->assertCount(2, $m['state']['moves']);
        $this->assertSame([7, 7], $m['state']['moves'][0]);
        $this->assertSame('black', $m['state']['toMove']);
        $this->assertSame(2, $m['state']['version']);

        $stored = json_decode($this->pdo->query("SELECT moves FROM hakoo_gomoku_games WHERE id = '{$j['game']}'")->fetchColumn(), true);
        $this->assertCount(3, $stored[0], 'FR-08.12/08.17: row, col and time of each move');
        $this->assertEqualsWithDelta(time() * 1000, $stored[0][2], 60_000);
    }

    /** TC-API-063 | FR-08.2: an occupied point is rejected and nothing changes */
    public function test_occupied_point_is_rejected()
    {
        $j = $this->computerGame();
        [, , , $m] = $this->move($j['game'], $j['token'], 7, 7);
        [$cr, $cc] = $m['state']['moves'][1];
        [$status, , , $e] = $this->move($j['game'], $j['token'], $cr, $cc);
        $this->assertSame(409, $status);
        $this->assertSame('occupied', $e['error']);
        [, , , $s] = $this->api('state', [], $j['token'], '&game=' . $j['game']);
        $this->assertCount(2, $s['state']['moves']);
    }

    /** TC-API-064 | FR-08.3: a move out of turn is rejected */
    public function test_move_out_of_turn_is_rejected()
    {
        [$game, $black, $white] = $this->friendGame();
        [$status, , , $e] = $this->move($game, $white, 7, 7);
        $this->assertSame(409, $status);
        $this->assertSame('not_your_turn', $e['error']);
    }

    /** TC-API-065 | security: without the right token nobody can see or play a game */
    public function test_wrong_token_is_refused()
    {
        $j = $this->computerGame();
        foreach ([null, 'x', str_repeat('0', 32)] as $token) {
            [$status, , , $e] = $this->move($j['game'], $token ?? '', 7, 7);
            $this->assertSame(403, $status);
            $this->assertSame('bad_token', $e['error']);
        }
        [$status] = $this->api('state', [], 'x', '&game=' . $j['game']);
        $this->assertSame(403, $status);
        $this->assertStringNotContainsString($j['token'], $this->pdo->query("SELECT CONCAT_WS(',', black_token, white_token) FROM hakoo_gomoku_games WHERE id = '{$j['game']}'")->fetchColumn(),
            'only hashes of tokens are stored');
    }

    /** TC-API-066 | FR-07.1, FR-07.5, FR-07.9: invite, join, both see each other's names */
    public function test_friend_invite_and_join()
    {
        [, , , $a] = $this->api('new', ['mode' => 'friend', 'color' => 'white', 'name' => 'Alice']);
        $this->assertSame('waiting', $a['state']['status']);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{32}$/', $a['invite']);
        $this->assertEqualsWithDelta(15 * 60, $a['state']['inviteExpiresIn'], 5, 'FR-07.3');

        [$status, , , $b] = $this->api('join', ['game' => $a['game'], 'invite' => $a['invite'], 'name' => 'Bob']);
        $this->assertSame(200, $status);
        $this->assertSame('black', $b['state']['you']);
        $this->assertSame('active', $b['state']['status']);
        $this->assertSame(['black' => 'Bob', 'white' => 'Alice'], $b['state']['names']);

        [, , , $s] = $this->api('state', [], $a['token'], '&game=' . $a['game']);
        $this->assertSame('active', $s['state']['status']);
        $this->assertSame('Bob', $s['state']['names']['black']);
    }

    /** TC-API-067 | FR-07.8: a third player cannot join */
    public function test_game_is_limited_to_two_players()
    {
        [, , , $a] = $this->api('new', ['mode' => 'friend', 'name' => 'Alice']);
        $this->api('join', ['game' => $a['game'], 'invite' => $a['invite'], 'name' => 'Bob']);
        [$status, , , $e] = $this->api('join', ['game' => $a['game'], 'invite' => $a['invite'], 'name' => 'Eve']);
        $this->assertSame(409, $status);
        $this->assertSame('game_full', $e['error']);
    }

    /** TC-API-068 | FR-07.6, FR-07.7, FR-07.10: invalid, cancelled and expired invites are refused with a reason */
    public function test_invalid_cancelled_and_expired_invites()
    {
        [, , , $a] = $this->api('new', ['mode' => 'friend', 'name' => 'Alice']);
        [$status, , , $e] = $this->api('join', ['game' => $a['game'], 'invite' => str_repeat('a', 32)]);
        $this->assertSame([404, 'invite_invalid'], [$status, $e['error']]);

        [$status, , , $c] = $this->api('cancel', ['game' => $a['game']], $a['token']);
        $this->assertSame([200, 'cancelled'], [$status, $c['state']['status']]);
        [$status, , , $e] = $this->api('join', ['game' => $a['game'], 'invite' => $a['invite']]);
        $this->assertSame([410, 'invite_cancelled'], [$status, $e['error']]);

        [, , , $b] = $this->api('new', ['mode' => 'friend', 'name' => 'Alice']);
        $this->pdo->prepare("UPDATE hakoo_gomoku_games SET invite_expires_at = '2000-01-01 00:00:00' WHERE id = ?")->execute([$b['game']]);
        [$status, , , $e] = $this->api('join', ['game' => $b['game'], 'invite' => $b['invite']]);
        $this->assertSame([410, 'invite_expired'], [$status, $e['error']]);
    }

    /** TC-API-069 | FR-08.9, FR-08.10, FR-08.13: five in a row ends the game with a winner and the line */
    public function test_five_in_a_row_wins_and_ends_the_game()
    {
        [$game, $black, $white] = $this->friendGame();
        for ($k = 0; $k < 4; $k++) {
            $this->move($game, $black, 7, $k);
            $this->move($game, $white, 9, $k);
        }
        [, , , $w] = $this->move($game, $black, 7, 4);
        $this->assertSame('finished', $w['state']['status']);
        $this->assertSame('black', $w['state']['winner']);
        $this->assertSame('five', $w['state']['reason']);
        $this->assertSame([[7, 0], [7, 1], [7, 2], [7, 3], [7, 4]], $w['state']['winLine']);

        [$status, , , $e] = $this->move($game, $white, 9, 4);
        $this->assertSame([409, 'game_over'], [$status, $e['error']]);
    }

    /** TC-API-070 | FR-09.3, FR-09.5: resigning gives the opponent the win, and only once */
    public function test_resign()
    {
        [$game, $black, $white] = $this->friendGame();
        [, , , $r] = $this->api('resign', ['game' => $game], $white);
        $this->assertSame(['finished', 'black', 'resign'], [$r['state']['status'], $r['state']['winner'], $r['state']['reason']]);
        [$status, , , $e] = $this->api('resign', ['game' => $game], $black);
        $this->assertSame([409, 'game_over'], [$status, $e['error']]);
    }

    /** TC-API-071 | FR-10.3, FR-10.5: ending a game records no winner */
    public function test_end_game_without_winner()
    {
        $j = $this->computerGame();
        $this->move($j['game'], $j['token'], 7, 7);
        [, , , $r] = $this->api('end', ['game' => $j['game']], $j['token']);
        $this->assertSame('ended', $r['state']['status']);
        $this->assertNull($r['state']['winner']);
        $this->assertCount(2, $r['state']['moves']);
    }

    /** TC-API-072 | NFR-02.6 polling: an unchanged game answers with a tiny "no change" */
    public function test_polling_with_since_version()
    {
        [$game, $black, $white] = $this->friendGame();
        [, , , $s] = $this->api('state', [], $white, "&game=$game");
        $v = $s['state']['version'];
        [, , , $same] = $this->api('state', [], $white, "&game=$game&since=$v");
        $this->assertSame(['status' => 'ok', 'changed' => false], $same);
        $this->move($game, $black, 7, 7);
        [, , , $new] = $this->api('state', [], $white, "&game=$game&since=$v");
        $this->assertSame([[7, 7]], $new['state']['moves']);
    }

    /** TC-API-073 | FR-02.2, FR-02.3: names must be 2-20 letters, digits, '-' or '_' */
    public function test_name_rules()
    {
        foreach (['A', str_repeat('x', 21), 'Bad name', '<b>x</b>'] as $bad) {
            [$status, , , $e] = $this->api('new', ['mode' => 'computer', 'name' => $bad]);
            $this->assertSame([400, 'bad_name'], [$status, $e['error']], $bad);
        }
        [$status] = $this->api('new', ['mode' => 'computer', 'name' => 'Ok_name-20']);
        $this->assertSame(200, $status);
    }

    /** TC-API-074 | FR-GAMES-02: the Gomoku API never sets a cookie */
    public function test_no_cookies()
    {
        [, $headers] = $this->api('new', ['mode' => 'computer']);
        $this->assertEmpty(array_filter($headers, fn($h) => stripos($h, 'Set-Cookie:') === 0));
    }

    /** TC-API-075 | FR-05.2, FR-05.7: an unknown difficulty falls back to medium */
    public function test_unknown_difficulty_falls_back_to_medium()
    {
        [, , , $j] = $this->api('new', ['mode' => 'computer', 'difficulty' => 'impossible']);
        $this->assertSame('medium', $j['state']['difficulty']);
    }
}
