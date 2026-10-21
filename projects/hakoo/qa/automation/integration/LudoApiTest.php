<?php
/**
 * System tests of the Ludo API (src/ludo.php): new games, the lobby (join, ready, start, leave),
 * rolling and moving, the computer taking over, and wrong input.
 * Traceability: FR-LUDO-01, FR-LUDO-08..12, FR-LUDO-19, FR-LUDO-20, FR-GAMES-13, NFR-SEC-01.
 */
class LudoApiTest extends HakooTestCase
{
    private const RULES = ['exit' => '6or1', 'finish' => 'reach', 'tries' => true];

    private function call(string $action, ?array $body, ?string $token = null, string $get = ''): array
    {
        return $this->http($body === null ? 'GET' : 'POST', "ludo.php?action=$action$get", $body, null, $token ? ["X-Ludo-Token: $token"] : []);
    }

    private function newGame(array $over = []): array
    {
        [$status, , , $j] = $this->call('new', $over + ['mode' => 'computer', 'players' => 4, 'color' => 3, 'difficulty' => 'easy',
            'name' => 'Tester', 'rules' => self::RULES]);
        $this->assertSame(200, $status, json_encode($j));
        return $j;
    }

    /** Play a game to the end: every person rolls and takes the first piece it may move. */
    private function playOut(string $game, array $tokens): array
    {
        $st = $this->call('state', null, reset($tokens), "&game=$game")[3]['state'];
        for ($n = 0; $n < 4000 && $st['status'] === 'active'; $n++) {
            $t = $tokens[$st['turn']];
            $j = $st['roll'] === null
                ? $this->call('roll', ['game' => $game], $t)[3]
                : $this->call('move', ['game' => $game, 'piece' => (int)array_key_first($st['legal'])], $t)[3];
            $this->assertSame('ok', $j['status'], json_encode($j));
            $st = $j['state'];
        }
        return $st;
    }

    /** TC-API-180 | FR-LUDO-08: against the computer the game starts at once; tokens are never shown; the token header is needed */
    public function test_computer_game_starts_at_once()
    {
        $j = $this->newGame(['players' => 3, 'color' => 1]);
        $st = $j['state'];
        $this->assertSame(['active', 1, 1], [$st['status'], $st['you'], $st['host']]);
        $this->assertSame(['none', 'human', 'computer', 'computer'], array_column($st['seats'], 'kind'));
        $this->assertSame(1, $st['turn'], 'the computers have played until it is your turn');
        $this->assertStringNotContainsString($j['token'], json_encode($st));
        $this->assertNull($st['pos'][0]);
        [$status, , , $e] = $this->call('state', null, null, "&game={$j['game']}");
        $this->assertSame([403, 'bad_token'], [$status, $e['error']]);
        $this->assertSame(1, $this->rows('hakoo_ludo_games', 'id = ?', [$j['game']]));
    }

    /** TC-API-181 | FR-LUDO-09, FR-LUDO-10: friends join with the link and press Ready; the starter starts; open seats become computers */
    public function test_lobby_join_ready_start()
    {
        $j = $this->newGame(['mode' => 'friends', 'players' => 4, 'color' => 0, 'name' => 'Host']);
        [$game, $host, $invite] = [$j['game'], $j['token'], $j['invite']];
        $this->assertSame('lobby', $j['state']['status']);
        $this->assertSame(['human', 'open', 'open', 'open'], array_column($j['state']['seats'], 'kind'));
        [$status, , , $e] = $this->call('join', ['game' => $game, 'invite' => str_repeat('0', 32), 'name' => 'Eve']);
        $this->assertSame([403, 'bad_invite'], [$status, $e['error']]);
        $ann = $this->call('join', ['game' => $game, 'invite' => $invite, 'name' => 'Ann'])[3];
        $bo = $this->call('join', ['game' => $game, 'invite' => $invite, 'name' => ''])[3];
        $this->assertSame([1, 2, 'Player 3'], [$ann['state']['you'], $bo['state']['you'], $bo['state']['seats'][2]['name']]);
        $this->assertSame([409, 'not_ready'], array_slice($this->pick($this->call('start', ['game' => $game], $host)), 0, 2));
        $this->call('ready', ['game' => $game, 'ready' => true], $ann['token']);
        $this->call('ready', ['game' => $game, 'ready' => true], $bo['token']);
        $this->assertSame([403, 'not_host'], array_slice($this->pick($this->call('start', ['game' => $game], $ann['token'])), 0, 2));
        $j = $this->call('start', ['game' => $game], $host)[3];
        $this->assertSame('active', $j['state']['status']);
        $this->assertSame(['human', 'human', 'human', 'computer'], array_column($j['state']['seats'], 'kind'));
        [$status, , , $e] = $this->call('join', ['game' => $game, 'invite' => $invite, 'name' => 'Late']);
        $this->assertSame([410, 'already_started'], [$status, $e['error']]);
        // Everybody sees the same board.
        $a = $this->call('state', null, $ann['token'], "&game=$game")[3]['state'];
        $this->assertSame($j['state']['pos'], $a['pos']);
        $this->assertSame(1, $a['you']);
        $st = $this->playOut($game, [0 => $host, 1 => $ann['token'], 2 => $bo['token']]);
        $this->assertSame(['finished', 'goal'], [$st['status'], $st['reason']]);
        $this->assertSame(4, count(array_filter($st['pos'][$st['winner']], fn($p) => $p === 60)));
    }

    /** [status, error] of a response */
    private function pick(array $r): array
    {
        return [$r[0], $r[3]['error'] ?? null];
    }

    /** TC-API-182 | FR-LUDO-01, FR-LUDO-11: only the player to move may roll; the server rolls 1..6; a wrong piece is refused */
    public function test_rolling_and_moving()
    {
        $j = $this->newGame(['mode' => 'friends', 'players' => 2, 'color' => 3]);
        [$game, $host] = [$j['game'], $j['token']];
        $guest = $this->call('join', ['game' => $game, 'invite' => $j['invite'], 'name' => 'Guest'])[3];
        $this->assertSame(1, $guest['state']['you'], 'two players sit opposite: seats 3 and 1');
        $this->call('ready', ['game' => $game, 'ready' => true], $guest['token']);
        $st = $this->call('start', ['game' => $game], $host)[3]['state'];
        $tokens = [3 => $host, 1 => $guest['token']];
        $other = $st['turn'] === 3 ? 1 : 3;
        $this->assertSame([409, 'not_your_turn'], $this->pick($this->call('roll', ['game' => $game], $tokens[$other])));
        $seen = [];
        for ($n = 0; $n < 60; $n++) {
            $r = $this->call('roll', ['game' => $game, 'from' => 0], $tokens[$st['turn']])[3];
            $this->assertSame('ok', $r['status'], json_encode($r));
            $this->assertContains($r['value'], [1, 2, 3, 4, 5, 6]);
            $seen[$r['value']] = true;
            $st = $r['state'];
            if ($st['roll'] !== null) {
                $illegal = array_values(array_diff([0, 1, 2, 3], array_keys($st['legal'])));
                if ($illegal) $this->assertSame([400, 'bad_piece'], $this->pick($this->call('move', ['game' => $game, 'piece' => $illegal[0]], $tokens[$st['turn']])));
                $this->assertSame([409, 'move_first'], $this->pick($this->call('roll', ['game' => $game], $tokens[$st['turn']])));
                $st = $this->call('move', ['game' => $game, 'piece' => (int)array_key_first($st['legal'])], $tokens[$st['turn']])[3]['state'];
            }
        }
        $this->assertGreaterThanOrEqual(5, count($seen), 'the server rolls all values');
        $this->assertSame([400, 'bad_piece'], $this->pick($this->call('move', ['game' => $game, 'piece' => '1'], $tokens[$st['turn']])));
        // `from` returns only the newer events.
        $all = $this->call('state', null, $host, "&game=$game")[3]['state'];
        $tail = $this->call('state', null, $host, "&game=$game&from=" . ($all['eventCount'] - 1))[3]['state'];
        $this->assertSame([end($all['events'])], $tail['events']);
        $this->assertSame(['ok', false], [$this->call('state', null, $host, "&game=$game&since={$all['version']}")[3]['status'],
            $this->call('state', null, $host, "&game=$game&since={$all['version']}")[3]['changed']]);
    }

    /** TC-API-183 | FR-LUDO-12: leaving: a friend frees the seat, the starter calls the game off; during play the computer takes over */
    public function test_leaving()
    {
        $j = $this->newGame(['mode' => 'friends', 'players' => 3, 'color' => 2]);
        [$game, $host, $invite] = [$j['game'], $j['token'], $j['invite']];
        $ann = $this->call('join', ['game' => $game, 'invite' => $invite, 'name' => 'Ann'])[3];
        $st = $this->call('leave', ['game' => $game], $ann['token'])[3]['state'];
        $this->assertSame('open', $st['seats'][$ann['state']['you']]['kind']);
        $st = $this->call('leave', ['game' => $game], $host)[3]['state'];
        $this->assertSame('ended', $st['status']);
        $this->assertSame([410, 'invite_expired'], $this->pick($this->call('join', ['game' => $game, 'invite' => $invite, 'name' => 'Bo'])));
        // During the game.
        $j = $this->newGame(['mode' => 'friends', 'players' => 2, 'color' => 0]);
        $bo = $this->call('join', ['game' => $j['game'], 'invite' => $j['invite'], 'name' => 'Bo'])[3];
        $this->call('ready', ['game' => $j['game'], 'ready' => true], $bo['token']);
        $this->call('start', ['game' => $j['game']], $j['token']);
        $st = $this->call('leave', ['game' => $j['game']], $bo['token'])[3]['state'];
        $this->assertSame(['active', 'computer', true, 'Bo'], [$st['status'], $st['seats'][2]['kind'], $st['seats'][2]['left'], $st['seats'][2]['name']]);
        $this->assertSame(0, $st['turn'], 'the computer played Bo\'s turns');
        $st = $this->call('leave', ['game' => $j['game']], $j['token'])[3]['state'];
        $this->assertSame('ended', $st['status'], 'no people left');
    }

    /** TC-API-184 | FR-LUDO-12: after 90 s without a move the others may let the computer play for that player, not before */
    public function test_takeover_after_idle()
    {
        $j = $this->newGame(['mode' => 'friends', 'players' => 2, 'color' => 0]);
        [$game, $host] = [$j['game'], $j['token']];
        $bo = $this->call('join', ['game' => $game, 'invite' => $j['invite'], 'name' => 'Bo'])[3];
        $this->call('ready', ['game' => $game, 'ready' => true], $bo['token']);
        $st = $this->call('start', ['game' => $game], $host)[3]['state'];
        $tokens = [0 => $host, 2 => $bo['token']];
        $waiting = $st['turn'];
        $other = $waiting === 0 ? 2 : 0;
        $this->assertSame([409, 'not_idle'], $this->pick($this->call('takeover', ['game' => $game], $tokens[$other])));
        $this->assertSame([409, 'not_idle'], $this->pick($this->call('takeover', ['game' => $game], $tokens[$waiting])), 'not yourself');
        $this->pdo->prepare("UPDATE hakoo_ludo_games SET events = '[]', updated_at = NOW() - INTERVAL 2 MINUTE WHERE id = ?")->execute([$game]);
        $st = $this->call('takeover', ['game' => $game], $tokens[$other])[3]['state'];
        $this->assertSame('computer', $st['seats'][$waiting]['kind']);
        $this->assertSame($other, $st['turn']);
    }

    /** TC-API-185 | NFR-SEC-01 (negative): wrong input is refused and nothing is stored; actions that change something need POST */
    public function test_invalid_input()
    {
        $base = ['mode' => 'computer', 'players' => 4, 'color' => 3, 'difficulty' => 'easy', 'rules' => self::RULES];
        foreach ([[['mode' => 'solo'], 'bad_mode'], [['players' => 5], 'bad_players'], [['players' => 1], 'bad_players'], [['players' => '4'], 'bad_players'],
                  [['color' => 4], 'bad_players'], [['difficulty' => 'insane'], 'bad_difficulty'], [['rules' => ['exit' => '5']], 'bad_rules'],
                  [['rules' => ['finish' => 'fly']], 'bad_rules'], [['rules' => 'x'], 'bad_rules'], [['name' => '<b>x</b>'], 'bad_name'],
                  [['name' => "a'; DROP TABLE hakoo_ludo_games; --"], 'bad_name']] as [$over, $code]) {
            [$status, , , $e] = $this->call('new', $over + $base);
            $this->assertSame([400, $code], [$status, $e['error'] ?? null], json_encode($over));
        }
        $this->assertSame(0, $this->rows('hakoo_ludo_games', '1'));
        $this->assertSame([405, 'post_required'], $this->pick($this->call('roll', null, null, '&game=0123456789abcdef')));
        $this->assertSame([404, 'not_found'], $this->pick($this->call('state', null, 'x', '&game=nothex')));
        // A lobby nobody starts runs out.
        $j = $this->newGame(['mode' => 'friends']);
        $this->pdo->prepare('UPDATE hakoo_ludo_games SET invite_expires_at = NOW() - INTERVAL 1 MINUTE WHERE id = ?')->execute([$j['game']]);
        $this->assertSame('ended', $this->call('state', null, $j['token'], "&game={$j['game']}")[3]['state']['status']);
    }

    /** Put a game in a known position: $pos per seat, $seat to move with roll $roll waiting. */
    private function setPosition(string $game, array $pos, int $seat, int $roll): void
    {
        $s = $this->pdo->prepare('SELECT state FROM hakoo_ludo_games WHERE id = ?');
        $s->execute([$game]);
        $st = json_decode($s->fetchColumn(), true);
        $st = array_merge($st, ['pos' => $pos, 'turn' => $seat, 'roll' => $roll, 'tries' => 0, 'drink' => []]);
        $this->pdo->prepare('UPDATE hakoo_ludo_games SET state = ? WHERE id = ?')->execute([json_encode($st), $game]);
    }

    /** TC-API-187 | FR-LUDO-16: Fia med hutt: the knocked-out person must say Skål before anyone rolls; a computer drinks at once */
    public function test_hutt_waits_for_the_sup()
    {
        $rules = self::RULES + ['hutt' => true];
        $j = $this->newGame(['mode' => 'friends', 'players' => 2, 'color' => 0, 'rules' => $rules]);
        [$game, $host] = [$j['game'], $j['token']];
        $guest = $this->call('join', ['game' => $game, 'invite' => $j['invite'], 'name' => 'Guest'])[3];
        $this->call('ready', ['game' => $game, 'ready' => true], $guest['token']);
        $this->call('start', ['game' => $game], $host);
        $this->assertTrue($this->call('state', null, $host, "&game=$game")[3]['state']['rules']['hutt']);
        $this->setPosition($game, [[25, -1, -1, -1], null, [0, -1, -1, -1], null], 0, 3);   // the host lands on the guest's piece
        $st = $this->call('move', ['game' => $game, 'piece' => 0], $host)[3]['state'];
        $this->assertSame([[2], 2], [$st['drink'], $st['turn']]);
        $this->assertSame([409, 'drink_first'], $this->pick($this->call('roll', ['game' => $game], $guest['token'])));
        $this->assertSame([409, 'no_sup'], $this->pick($this->call('skal', ['game' => $game], $host)));
        $st = $this->call('skal', ['game' => $game], $guest['token'])[3]['state'];
        $this->assertSame([], $st['drink']);
        $this->assertSame(['s' => 2, 'k' => 'skal'], array_diff_key(end($st['events']), ['ms' => 1]));
        $this->assertSame('ok', $this->call('roll', ['game' => $game], $guest['token'])[3]['status']);
        // Against the computer: it takes its sup by itself and plays on.
        $j = $this->newGame(['players' => 2, 'color' => 0, 'rules' => $rules]);
        $this->setPosition($j['game'], [[25, -1, -1, -1], null, [0, -1, -1, -1], null], 0, 3);
        $st = $this->call('move', ['game' => $j['game'], 'piece' => 0, 'from' => 0], $j['token'])[3]['state'];
        $this->assertNotContains(2, $st['drink'], 'the computer owes nothing');
        $this->assertContains(['s' => 2, 'k' => 'skal'], array_map(fn($e) => array_diff_key($e, ['ms' => 1]), $st['events']));
        $this->assertTrue($st['turn'] === 0 || $st['drink'] === [0], 'the computer played on until the person is needed');
    }

    /** TC-API-196 | FR-LUDO-18: a logged-in member's choices are kept when a game starts and come back in ?action=me; a visitor's are not */
    public function test_member_choices_are_remembered()
    {
        $cookie = $this->sessionCookie(self::CAROL);
        $rules = ['exit' => '6', 'finish' => 'bounce', 'tries' => false, 'block' => true, 'safeStart' => true, 'hutt' => true, 'board' => 'classic'];
        $body = ['mode' => 'computer', 'players' => 3, 'color' => 1, 'difficulty' => 'hard', 'name' => 'Carol', 'rules' => $rules];
        [$status] = $this->http('POST', 'ludo.php?action=new', $body, $cookie);
        $this->assertSame(200, $status);
        $me = $this->http('GET', 'ludo.php?action=me', null, $cookie)[3];
        $this->assertSame(['color' => 1, 'players' => 3, 'difficulty' => 'hard', 'name' => 'Carol', 'rules' => $rules], $me['ludoSetup']);
        // A wrong name is refused before anything is kept.
        [$status] = $this->http('POST', 'ludo.php?action=new', ['name' => '<x>'] + $body, $cookie);
        $this->assertSame(400, $status);
        $this->assertSame('Carol', $this->http('GET', 'ludo.php?action=me', null, $cookie)[3]['ludoSetup']['name']);
        // Not logged in: a game, but nothing kept.
        $before = $this->rows('hakoo_board_settings', '1');
        $this->newGame();
        $this->assertSame($before, $this->rows('hakoo_board_settings', '1'));
        // "Download my data" has it.
        $json = $this->http('GET', 'mydata.php?action=export', null, $cookie)[3];
        $this->assertSame('hard', json_decode($json['games_and_settings']['hakoo_board_settings'][0]['ludo_setup'], true)['difficulty']);
    }

    /** TC-API-186 | FR-GAMES-13: the member's dice settings can be read and saved through ludo.php too */
    public function test_member_settings()
    {
        $cookie = $this->sessionCookie(self::CAROL);
        $this->assertSame(['ok', false], [$this->call('me', null)[3]['status'], $this->call('me', null)[3]['loggedIn']]);
        [$status, , , $j] = $this->http('POST', 'ludo.php?action=settings', ['diceRoll' => 'button'], $cookie);
        $this->assertSame([200, 'button'], [$status, $j['diceRoll']]);
        $this->assertSame('button', $this->http('GET', 'othello.php?action=me', null, $cookie)[3]['diceRoll']);
    }

    /** TC-API-301 | FR-LUDO-19, FR-LUDO-20: Vikingludo against five computers on the six-armed board, played to the end */
    public function test_viking_six_players()
    {
        $j = $this->newGame(['players' => 6, 'color' => 4, 'rules' => ['board' => 'viking', 'exit' => '6or1']]);
        $st = $j['state'];
        $this->assertSame(['human', 4], [$st['seats'][4]['kind'], $st['you']]);
        $this->assertSame(['computer', 'computer', 'computer', 'computer', 'human', 'computer'], array_column($st['seats'], 'kind'));
        $this->assertSame(['exit' => '6', 'finish' => 'exact', 'tries' => true, 'block' => false, 'safeStart' => false, 'hutt' => false, 'board' => 'viking'], $st['rules']);
        $this->assertCount(6, $st['pos']);
        $st = $this->playOut($j['game'], [4 => $j['token']]);
        $this->assertSame('finished', $st['status']);
        $this->assertTrue(in_array($st['winner'], [0, 1, 2, 3, 4, 5], true));
        $this->assertSame(88, max(array_map('max', $st['pos'])), 'the goal of the six-armed board');
        $faces = array_unique(array_column(array_filter($st['events'], fn($e) => $e['k'] === 'roll'), 'v'));
        sort($faces);
        $this->assertSame([1, 2, 3, 4, 5, 6], $faces, 'all six faces, crown and feather too');
    }

    /** TC-API-301 | FR-LUDO-20: a game saved on the board of 13 squares an arm comes back on the new board, and is saved so */
    public function test_viking_game_from_before_the_corner_square()
    {
        $j = $this->newGame(['players' => 2, 'color' => 0, 'rules' => ['board' => 'viking']]);
        $N = -1;
        // As the database had it before 2026-10-16: no 'arm', Red on old squares 5 and 51 (its first home square).
        $this->pdo->prepare('UPDATE hakoo_ludo_games SET state = ?, events = ? WHERE id = ?')->execute([
            json_encode(['pos' => [[5, 51, $N, $N], null, [$N, $N, $N, $N], null], 'seats' => [0, 2], 'turn' => 0, 'roll' => null, 'tries' => 0, 'winner' => null, 'drink' => []]),
            json_encode([['s' => 0, 'k' => 'move', 'p' => 0, 'f' => 0, 't' => 5, 'x' => [], 'ms' => 1]]), $j['game']]);
        $st = $this->call('state', null, $j['token'], "&game={$j['game']}")[3]['state'];
        $this->assertSame([6, 55, $N, $N], $st['pos'][0]);
        $this->assertSame(6, $st['events'][0]['t']);
        $this->call('roll', ['game' => $j['game']], $j['token']);
        $saved = json_decode($this->pdo->query("SELECT state FROM hakoo_ludo_games WHERE id = '{$j['game']}'")->fetchColumn(), true);
        $this->assertSame(14, $saved['arm'], 'saved on the new board after a move');
    }

    /** TC-API-329 | FR-LUDO-02: a classic game saved on the board of 40 squares comes back on the board of 14 an arm, and is saved so */
    public function test_classic_game_from_the_board_of_40()
    {
        $j = $this->newGame(['players' => 2, 'color' => 0]);
        $N = -1;
        // As the database had it before 2026-10-20: no 'arm', Red on old ring square 9 and home square 41.
        $this->pdo->prepare('UPDATE hakoo_ludo_games SET state = ?, events = ? WHERE id = ?')->execute([
            json_encode(['pos' => [[9, 41, $N, $N], null, [$N, $N, $N, $N], null], 'seats' => [0, 2], 'turn' => 0, 'roll' => null, 'tries' => 0, 'winner' => null, 'drink' => []]),
            json_encode([['s' => 0, 'k' => 'move', 'p' => 0, 'f' => 0, 't' => 9, 'x' => [], 'ms' => 1]]), $j['game']]);
        $st = $this->call('state', null, $j['token'], "&game={$j['game']}")[3]['state'];
        $this->assertSame([12, 57, $N, $N], $st['pos'][0]);
        $this->assertSame(12, $st['events'][0]['t']);
        $this->call('roll', ['game' => $j['game']], $j['token']);
        $saved = json_decode($this->pdo->query("SELECT state FROM hakoo_ludo_games WHERE id = '{$j['game']}'")->fetchColumn(), true);
        $this->assertSame(14, $saved['arm'], 'saved on the new board after a move');
    }

    /** TC-API-302 | FR-LUDO-20: boundary values for Vikingludo: 2-6 players, colours 0-3 on the royal board and 0-5 on the six-armed one */
    public function test_viking_players_and_colours()
    {
        $viking = ['board' => 'viking'];
        foreach ([[2, 2, 200, ['human', 'none', 'none', 'none']], [4, 3, 200, null], [5, 5, 200, null], [5, 4, 200, null],
                  [7, 0, 400, null], [1, 0, 400, null], [4, 4, 400, null], [6, 6, 400, null]] as [$players, $color, $want, $kinds]) {
            [$status, , , $j] = $this->call('new', ['mode' => 'friends', 'players' => $players, 'color' => $color, 'difficulty' => 'easy',
                'name' => 'Tester', 'rules' => $viking]);
            $this->assertSame($want, $status, "$players players, colour $color: " . json_encode($j));
            if ($want !== 200) { $this->assertSame('bad_players', $j['error']); continue; }
            $this->assertCount($players > 4 ? 6 : 4, $j['state']['seats']);
            $this->assertSame($players - 1, count(array_filter($j['state']['seats'], fn($s) => $s['kind'] === 'open')));
        }
        [$status, , , $j] = $this->call('new', ['mode' => 'computer', 'players' => 5, 'color' => 0, 'difficulty' => 'easy', 'rules' => self::RULES]);
        $this->assertSame([400, 'bad_players'], [$status, $j['error']]);                 // five players only in Vikingludo
        [$status, , , $j] = $this->call('new', ['mode' => 'computer', 'players' => 4, 'color' => 0, 'difficulty' => 'easy', 'rules' => ['board' => 'hex']]);
        $this->assertSame([400, 'bad_rules'], [$status, $j['error']]);
    }
}

