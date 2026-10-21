<?php
/**
 * System tests of the Yatzy API (src/yatzy.php): new games (alone, against the computer, with friends),
 * the lobby (join, ready, start, leave), rolling, keeping dice and writing scores, the computer taking over,
 * the member's last choices, and wrong input.
 * Traceability: FR-YATZY-01, FR-YATZY-08..13, FR-GAMES-13, FR-GAMES-20, NFR-SEC-01.
 */
class YatzyApiTest extends HakooTestCase
{
    private function call(string $action, ?array $body, ?string $token = null, string $get = ''): array
    {
        return $this->http($body === null ? 'GET' : 'POST', "yatzy.php?action=$action$get", $body, null, $token ? ["X-Yatzy-Token: $token"] : []);
    }

    private function newGame(array $over = []): array
    {
        [$status, , , $j] = $this->call('new', $over + ['mode' => 'computer', 'players' => 3, 'difficulty' => 'easy',
            'name' => 'Tester', 'rules' => ['order' => 'free']]);
        $this->assertSame(200, $status, json_encode($j));
        return $j;
    }

    /** [status, error] of a response */
    private function pick(array $r): array
    {
        return [$r[0], $r[3]['error'] ?? null];
    }

    /** Play a game to the end: every person rolls once and writes the dice in their first open row. */
    private function playOut(string $game, array $tokens): array
    {
        $st = $this->call('state', null, reset($tokens), "&game=$game")[3]['state'];
        for ($n = 0; $n < 500 && $st['status'] === 'active'; $n++) {
            $t = $tokens[$st['turn']];
            $j = $st['rolls'] === 0
                ? $this->call('roll', ['game' => $game, 'hold' => []], $t)[3]
                : $this->call('score', ['game' => $game, 'cat' => $st['open'][0]], $t)[3];
            $this->assertSame('ok', $j['status'], json_encode($j));
            $st = $j['state'];
        }
        return $st;
    }

    /** TC-API-323 | FR-YATZY-09: against the computer the game starts at once and the computers play until it is your turn; the token is needed */
    public function test_computer_game_starts_at_once()
    {
        $j = $this->newGame();
        $st = $j['state'];
        $this->assertSame(['active', 0, 0], [$st['status'], $st['you'], $st['host']]);
        $this->assertSame(['human', 'computer', 'computer'], array_column($st['seats'], 'kind'));
        $this->assertSame(['Tester', 'Computer 1', 'Computer 2'], array_column($st['seats'], 'name'));
        $this->assertSame([0, 0, null], [$st['turn'], $st['rolls'], $st['dice']], 'the computers have played until it is your turn');
        $this->assertSame(range(0, 14), $st['open']);
        $this->assertStringNotContainsString($j['token'], json_encode($st));
        [$status, , , $e] = $this->call('state', null, null, "&game={$j['game']}");
        $this->assertSame([403, 'bad_token'], [$status, $e['error']]);
        $this->assertSame(1, $this->rows('hakoo_yatzy_games', 'id = ?', [$j['game']]));
        // Alone: one seat, it is your turn at once.
        $solo = $this->newGame(['players' => 1])['state'];
        $this->assertSame([['human'], 0, []], [array_column($solo['seats'], 'kind'), $solo['turn'], $solo['events']]);
    }

    /** TC-API-324 | FR-YATZY-01, FR-YATZY-12: the server rolls; kept dice stay; at most three rolls; scores go to the sheet; the turn passes */
    public function test_rolling_keeping_and_scoring()
    {
        $j = $this->newGame(['players' => 1]);
        [$game, $t] = [$j['game'], $j['token']];
        $this->assertSame([409, 'roll_first'], $this->pick($this->call('score', ['game' => $game, 'cat' => 13], $t)));
        $this->assertSame([400, 'bad_hold'], $this->pick($this->call('roll', ['game' => $game, 'hold' => [0]], $t)), 'nothing to keep on the first roll');
        $st = $this->call('roll', ['game' => $game, 'hold' => []], $t)[3]['state'];
        $this->assertSame(1, $st['rolls']);
        $this->assertCount(5, $st['dice']);
        foreach ($st['dice'] as $d) $this->assertTrue($d >= 1 && $d <= 6);
        $first = $st['dice'];
        $st = $this->call('roll', ['game' => $game, 'hold' => [0, 2, 4]], $t)[3]['state'];
        $this->assertSame([$first[0], $first[2], $first[4]], [$st['dice'][0], $st['dice'][2], $st['dice'][4]], 'the kept dice stay');
        $this->assertSame(['s' => 0, 'k' => 'roll', 'h' => [0, 2, 4], 'n' => 2], array_diff_key(end($st['events']), ['ms' => 1, 'd' => 1]));
        $this->assertSame([400, 'bad_hold'], $this->pick($this->call('roll', ['game' => $game, 'hold' => [0, 1, 2, 3, 4]], $t)), 'nothing left to roll');
        $this->assertSame([400, 'bad_hold'], $this->pick($this->call('roll', ['game' => $game, 'hold' => [7]], $t)));
        $this->assertSame([400, 'bad_hold'], $this->pick($this->call('roll', ['game' => $game, 'hold' => 'all'], $t)));
        $this->call('roll', ['game' => $game, 'hold' => []], $t);
        $this->assertSame([409, 'no_rolls_left'], $this->pick($this->call('roll', ['game' => $game, 'hold' => []], $t)));
        $dice = $this->call('state', null, $t, "&game=$game")[3]['state']['dice'];
        $st = $this->call('score', ['game' => $game, 'cat' => 13], $t)[3]['state'];
        $this->assertSame([array_sum($dice), 0, null], [$st['sheets'][0][13], $st['rolls'], $st['dice']], 'chance: the sum');
        $this->assertNotContains(13, $st['open']);
        $this->call('roll', ['game' => $game, 'hold' => []], $t);
        $this->assertSame([400, 'bad_cat'], $this->pick($this->call('score', ['game' => $game, 'cat' => 13], $t)), 'a row is written once');
        $this->assertSame([400, 'bad_cat'], $this->pick($this->call('score', ['game' => $game, 'cat' => '3'], $t)));
        $this->assertSame([400, 'bad_cat'], $this->pick($this->call('score', ['game' => $game, 'cat' => 15], $t)));
    }

    /** TC-API-325 | FR-YATZY-10, FR-YATZY-11: friends join with the link and press Ready; the starter starts; open seats become computers; played to the end */
    public function test_lobby_join_ready_start()
    {
        $j = $this->newGame(['mode' => 'friends', 'players' => 4, 'name' => 'Host', 'rules' => ['order' => 'forced']]);
        [$game, $host, $invite] = [$j['game'], $j['token'], $j['invite']];
        $this->assertSame('lobby', $j['state']['status']);
        $this->assertSame(['human', 'open', 'open', 'open'], array_column($j['state']['seats'], 'kind'));
        $this->assertSame([403, 'bad_invite'], $this->pick($this->call('join', ['game' => $game, 'invite' => str_repeat('0', 32), 'name' => 'Eve'])));
        $ann = $this->call('join', ['game' => $game, 'invite' => $invite, 'name' => 'Ann'])[3];
        $bo = $this->call('join', ['game' => $game, 'invite' => $invite, 'name' => ''])[3];
        $this->assertSame([1, 2, 'Player 3'], [$ann['state']['you'], $bo['state']['you'], $bo['state']['seats'][2]['name']]);
        $this->assertSame([409, 'not_ready'], $this->pick($this->call('start', ['game' => $game], $host)));
        $this->call('ready', ['game' => $game, 'ready' => true], $ann['token']);
        $this->call('ready', ['game' => $game, 'ready' => true], $bo['token']);
        $this->assertSame([403, 'not_host'], $this->pick($this->call('start', ['game' => $game], $ann['token'])));
        $st = $this->call('start', ['game' => $game], $host)[3]['state'];
        $this->assertSame('active', $st['status']);
        $this->assertSame(['human', 'human', 'human', 'computer'], array_column($st['seats'], 'kind'));
        $this->assertSame([410, 'already_started'], $this->pick($this->call('join', ['game' => $game, 'invite' => $invite, 'name' => 'Late'])));
        $tokens = [0 => $host, 1 => $ann['token'], 2 => $bo['token']];
        $turn = $st['turn'];
        $other = $turn === 0 ? 1 : 0;
        $this->assertSame([409, 'not_your_turn'], $this->pick($this->call('roll', ['game' => $game, 'hold' => []], $tokens[$other])));
        $this->assertSame([[0]], [$st['open']], 'forced order: ones first');
        $st = $this->playOut($game, $tokens);
        $this->assertSame(['finished', 'done'], [$st['status'], $st['reason']]);
        foreach ($st['sheets'] as $sheet) $this->assertNotContains(null, $sheet);
        $totals = array_column($st['totals'], 'total');
        $this->assertSame(array_keys($totals, max($totals)), $st['winners']);
        $this->assertSame([409, 'game_over'], $this->pick($this->call('roll', ['game' => $game, 'hold' => []], $host)));
    }

    /** TC-API-326 | FR-YATZY-13: leaving lets the computer play the seat; after 90 s without a move the others may let it take over */
    public function test_leave_and_takeover()
    {
        $j = $this->newGame(['mode' => 'friends', 'players' => 3]);
        [$game, $host] = [$j['game'], $j['token']];
        $a = $this->call('join', ['game' => $game, 'invite' => $j['invite'], 'name' => 'Ann'])[3];
        $b = $this->call('join', ['game' => $game, 'invite' => $j['invite'], 'name' => 'Bo'])[3];
        $this->call('ready', ['game' => $game, 'ready' => true], $a['token']);
        $this->call('ready', ['game' => $game, 'ready' => true], $b['token']);
        $st = $this->call('start', ['game' => $game], $host)[3]['state'];
        $tokens = [$host, $a['token'], $b['token']];
        $waiting = $st['turn'];
        $other = ($waiting + 1) % 3;
        $this->assertSame([409, 'not_idle'], $this->pick($this->call('takeover', ['game' => $game], $tokens[$other])));
        $this->assertSame([409, 'not_idle'], $this->pick($this->call('takeover', ['game' => $game], $tokens[$waiting])), 'not yourself');
        $this->pdo->prepare("UPDATE hakoo_yatzy_games SET events = '[]', updated_at = NOW() - INTERVAL 2 MINUTE WHERE id = ?")->execute([$game]);
        $st = $this->call('takeover', ['game' => $game], $tokens[$other])[3]['state'];
        $this->assertSame([true, 'computer'], [$st['seats'][$waiting]['left'], $st['seats'][$waiting]['kind']]);
        $this->assertSame($other, $st['turn'], 'the computer played that turn');
        $this->assertCount(1, array_filter($st['sheets'][$waiting], fn($v) => $v !== null), 'one row written for them');
        // The rest leave: with no people left the game ends.
        foreach ([0, 1, 2] as $s) if ($s !== $waiting) $last = $this->call('leave', ['game' => $game], $tokens[$s])[3]['state'];
        $this->assertSame('ended', $last['status']);
        $this->assertSame([403, 'bad_token'], $this->pick($this->call('roll', ['game' => $game, 'hold' => []], $tokens[$waiting])), 'the seat is the computer\'s now');
    }

    /** TC-API-327 | NFR-SEC-01 (negative): wrong input is refused and nothing is stored; changes need POST; an old lobby runs out */
    public function test_invalid_input()
    {
        $base = ['mode' => 'computer', 'players' => 2, 'difficulty' => 'easy', 'rules' => ['order' => 'free']];
        foreach ([[['mode' => 'solo'], 'bad_mode'], [['players' => 0], 'bad_players'], [['players' => 7], 'bad_players'], [['players' => '2'], 'bad_players'],
                  [['mode' => 'friends', 'players' => 1], 'bad_players'], [['difficulty' => 'insane'], 'bad_difficulty'],
                  [['rules' => ['order' => 'random']], 'bad_rules'], [['rules' => 'x'], 'bad_rules'], [['name' => '<b>x</b>'], 'bad_name'],
                  [['name' => "a'; DROP TABLE hakoo_yatzy_games; --"], 'bad_name']] as [$over, $code]) {
            [$status, , , $e] = $this->call('new', $over + $base);
            $this->assertSame([400, $code], [$status, $e['error'] ?? null], json_encode($over));
        }
        $this->assertSame(0, $this->rows('hakoo_yatzy_games', '1'));
        $this->assertSame([405, 'post_required'], $this->pick($this->call('roll', null, null, '&game=0123456789abcdef')));
        $this->assertSame([404, 'not_found'], $this->pick($this->call('state', null, 'x', "&game=" . rawurlencode("1' OR '1'='1"))));
        $j = $this->newGame(['mode' => 'friends']);
        $this->pdo->prepare('UPDATE hakoo_yatzy_games SET invite_expires_at = NOW() - INTERVAL 1 MINUTE WHERE id = ?')->execute([$j['game']]);
        $this->assertSame('ended', $this->call('state', null, $j['token'], "&game={$j['game']}")[3]['state']['status']);
        $this->assertSame([410, 'invite_expired'], $this->pick($this->call('join', ['game' => $j['game'], 'invite' => $j['invite'], 'name' => 'Late'])));
    }

    /** TC-API-328 | FR-YATZY-14, FR-GAMES-20: a member's choices are kept with the account when a game starts, are in "Download my data" and go with "Delete" */
    public function test_member_choices_are_remembered()
    {
        $id = '100000000000000077';                                    // a fictional member with no data yet
        $cookie = $this->sessionCookie($id);
        $body = ['mode' => 'computer', 'players' => 4, 'difficulty' => 'hard', 'name' => 'Carol', 'rules' => ['order' => 'forced']];
        [$status] = $this->http('POST', 'yatzy.php?action=new', $body, $cookie);
        $this->assertSame(200, $status);
        $me = $this->http('GET', 'yatzy.php?action=me', null, $cookie)[3];
        $this->assertSame(['players' => 4, 'difficulty' => 'hard', 'name' => 'Carol', 'rules' => ['order' => 'forced']], $me['yatzySetup']);
        [$status] = $this->http('POST', 'yatzy.php?action=new', ['name' => '<x>'] + $body, $cookie);
        $this->assertSame(400, $status);
        $this->assertSame('Carol', $this->http('GET', 'yatzy.php?action=me', null, $cookie)[3]['yatzySetup']['name'], 'a refused game keeps nothing');
        $before = $this->rows('hakoo_board_settings', '1');
        $this->newGame();
        $this->assertSame($before, $this->rows('hakoo_board_settings', '1'), 'a visitor: nothing kept');
        $json = $this->http('GET', 'mydata.php?action=export', null, $cookie)[3];
        $this->assertSame('hard', json_decode($json['games_and_settings']['hakoo_board_settings'][0]['yatzy_setup'], true)['difficulty']);
        $this->http('POST', 'mydata.php?action=delete', ['confirm' => 'DELETE'], $cookie);
        $this->assertSame(0, $this->rows('hakoo_board_settings', 'discord_id = ?', [$id]));
        // The dice settings through yatzy.php are the same as the board games'.
        $c2 = $this->sessionCookie(self::CAROL);
        [$status, , , $s] = $this->http('POST', 'yatzy.php?action=settings', ['diceRoll' => 'button'], $c2);
        $this->assertSame([200, 'button'], [$status, $s['diceRoll']]);
        $this->assertSame('button', $this->http('GET', 'ludo.php?action=me', null, $c2)[3]['diceRoll']);
    }
}
