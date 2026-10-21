<?php
/**
 * System tests of the Backgammon API (src/backgammon.php on board-game-api.inc.php) over HTTP.
 * Traceability: FR-BACKGAMMON-01..08, FR-GAMES-02.
 */
require_once '/var/www/html/backgammon-engine.inc.php';

class BackgammonApiTest extends HakooTestCase
{
    private function api(string $action, array $body = [], ?string $token = null, string $query = ''): array
    {
        $headers = $token ? ['X-Game-Token: ' . $token] : [];
        $method = $action === 'state' ? 'GET' : 'POST';
        return $this->http($method, "backgammon.php?action=$action$query", $method === 'POST' ? $body : null, null, $headers);
    }

    /** One legal play for the player to move in state $s (the first one, or a random one). */
    private function legalPlay(array $s, ?\Random\Randomizer $rng = null): array
    {
        $g = new BackgammonGame($s['toMove'], $s['board']);
        $plays = array_values($g->plays($s['toMove'], $s['dice'][0], $s['dice'][1]));
        return $rng ? $plays[$rng->getInt(0, count($plays) - 1)] : $plays[0];
    }

    /** TC-API-198 | FR-BACKGAMMON-01, FR-BACKGAMMON-07: the start position and the opening roll; the computer opens when it won it */
    public function test_new_games_and_the_opening_roll()
    {
        $seen = [];
        for ($i = 0; $i < 12; $i++) {
            [, , , $j] = $this->api('new', ['mode' => 'computer', 'color' => 'black', 'difficulty' => 'easy']);
            $s = $j['state'];
            $this->assertSame('black', $s['you']);
            if ($s['first'] === 'black') {
                $this->assertSame([], $s['moves']);
                $this->assertSame('black', $s['toMove']);
                $this->assertCount(2, $s['dice']);
                $this->assertGreaterThan($s['dice'][1], $s['dice'][0], 'the opening roll: the higher die first, never a double');
                $this->assertSame(['black' => 167, 'white' => 167], $s['pips']);
                $this->assertSame([0, 0, 0, 0, 0, 0, 5, 0, 3, 0, 0, 0, 0, 5, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 2, 0], $s['board']['black']);
            } else {
                $this->assertCount(1, $s['moves'], 'the computer played its opening at once');
                $this->assertGreaterThan($s['moves'][0][1], $s['moves'][0][0]);
                $this->assertSame('black', $s['toMove']);
                $this->assertNull($s['dice'], 'now the player rolls');
            }
            $seen[$s['first']] = true;
            if (count($seen) === 2) break;
        }
        $this->assertCount(2, $seen, 'both seats win the opening roll sometimes');
    }

    /** TC-API-199 | FR-BACKGAMMON-03, FR-BACKGAMMON-07: wrong turns, rolling twice, playing before rolling and illegal plays are refused */
    public function test_bad_requests_are_refused()
    {
        [, , , $a] = $this->api('new', ['mode' => 'friend', 'color' => 'black']);
        [, , , $b] = $this->api('join', ['game' => $a['game'], 'invite' => $a['invite']]);
        $s = $b['state'];
        $tok = ['black' => $a['token'], 'white' => $b['token']];
        $mover = $s['toMove'];
        $waiter = BackgammonGame::other($mover);
        [$status, , , $e] = $this->api('move', ['game' => $a['game'], 'roll' => true], $tok[$waiter]);
        $this->assertSame([409, 'not_your_turn'], [$status, $e['error']]);
        [$status, , , $e] = $this->api('move', ['game' => $a['game'], 'roll' => true], $tok[$mover]);
        $this->assertSame([409, 'rolled_already'], [$status, $e['error']], 'the opening roll is already there');
        [$status, , , $e] = $this->api('move', ['game' => $a['game'], 'play' => $this->legalPlay($s)], $tok[$waiter]);
        $this->assertSame([409, 'not_your_turn'], [$status, $e['error']]);
        [$status] = $this->api('move', ['game' => $a['game'], 'roll' => true], 'wrong');
        $this->assertSame(403, $status);
        [$status, , , $e] = $this->api('move', ['game' => $a['game'], 'roll' => 'yes'], $tok[$mover]);
        $this->assertSame([400, 'bad_request'], [$status, $e['error']]);
    }

    /** TC-API-200 | FR-BACKGAMMON-03: a play must use both dice, may not move to a closed point, and must be well formed */
    public function test_illegal_plays()
    {
        [, , , $a] = $this->api('new', ['mode' => 'friend', 'color' => 'black']);
        [, , , $b] = $this->api('join', ['game' => $a['game'], 'invite' => $a['invite']]);
        $s = $b['state'];
        $token = $s['toMove'] === 'black' ? $a['token'] : $b['token'];
        $play = $this->legalPlay($s);
        foreach ([[[$play[0]], 'use_all_dice', 409], [[[24, 18], [24, 12], [13, 1]], 'too_many_steps', 409],
                  [[[6, 0], [6, 0]], 'illegal_play', 409], [[['13', 7], [13, 8]], 'bad_step', 400]] as [$steps, $err, $code]) {
            [$status, , , $e] = $this->api('move', ['game' => $a['game'], 'play' => $steps], $token);
            $this->assertSame([$code, $err], [$status, $e['error']], json_encode($steps));
        }
        [$status, , , $e] = $this->api('move', ['game' => $a['game'], 'play' => 'all'], $token);
        $this->assertSame([400, 'bad_request'], [$status, $e['error']]);
        [$status, , , $ok] = $this->api('move', ['game' => $a['game'], 'play' => $play], $token);
        $this->assertSame(200, $status);
        $this->assertCount(1, $ok['state']['moves']);
        $this->assertNull($ok['state']['dice']);
        [$status, , , $e] = $this->api('move', ['game' => $a['game'], 'play' => $play], $token);
        $this->assertSame([409, 'not_your_turn'], [$status, $e['error']]);
    }

    /**
     * TC-API-201 | FR-BACKGAMMON-05, FR-BACKGAMMON-06, FR-BACKGAMMON-07: a whole game against Easy through the
     * API: roll, play a random legal play, the computer answers in the same call; after every call it is
     * the player's turn again or the game is over. The stored turns carry the time of each turn.
     */
    public function test_whole_game_against_the_computer()
    {
        [, , , $j] = $this->api('new', ['mode' => 'computer', 'color' => 'white', 'difficulty' => 'easy']);
        $s = $j['state'];
        $rng = new \Random\Randomizer(new \Random\Engine\Mt19937(9));
        for ($k = 0; $k < 400 && $s['status'] === 'active'; $k++) {
            $this->assertSame('white', $s['toMove'], 'never left waiting for the computer');
            if ($s['dice'] === null) {
                [$status, , , $m] = $this->api('move', ['game' => $j['game'], 'roll' => true], $j['token']);
            } else {
                [$status, , , $m] = $this->api('move', ['game' => $j['game'], 'play' => $this->legalPlay($s, $rng)], $j['token']);
            }
            $this->assertSame(200, $status, json_encode($m));
            $s = $m['state'];
            $this->assertSame(15, array_sum($s['board']['black']));
            $this->assertSame(15, array_sum($s['board']['white']));
        }
        $this->assertSame('finished', $s['status']);
        $this->assertContains($s['reason'], ['single', 'gammon', 'backgammon']);
        $this->assertSame(['single' => 1, 'gammon' => 2, 'backgammon' => 3][$s['reason']], $s['points']);
        $this->assertSame(15, $s['board'][$s['winner']][0]);
        $this->assertNull($s['dice']);
        $stored = json_decode($this->pdo->query("SELECT moves FROM hakoo_backgammon_games WHERE id = '{$j['game']}'")->fetchColumn(), true);
        $this->assertCount(4, $stored[0], 'die, die, steps and time of each turn');
        $this->assertSame(count($s['moves']), count($stored));
    }

    /** TC-API-202 | FR-BACKGAMMON-08, FR-GAMES-02: invite, join, the friend sees the play; resign; the API sets no cookie */
    public function test_friend_game_and_no_cookies()
    {
        [, $headers, , $a] = $this->api('new', ['mode' => 'friend', 'color' => 'white', 'name' => 'Alice']);
        $this->assertEmpty(array_filter($headers, fn($h) => stripos($h, 'Set-Cookie:') === 0));
        [, , , $b] = $this->api('join', ['game' => $a['game'], 'invite' => $a['invite'], 'name' => 'Bob']);
        $this->assertSame(['black' => 'Bob', 'white' => 'Alice'], $b['state']['names']);
        $s = $b['state'];
        $tok = ['white' => $a['token'], 'black' => $b['token']];
        $mover = $s['toMove'];
        $play = $this->legalPlay($s);
        $this->api('move', ['game' => $a['game'], 'play' => $play], $tok[$mover]);
        $other = BackgammonGame::other($mover);
        [, , , $seen] = $this->api('state', [], $tok[$other], "&game={$a['game']}");
        $this->assertSame([[$s['dice'][0], $s['dice'][1], $play]], $seen['state']['moves']);
        $this->assertSame($other, $seen['state']['toMove']);
        $this->assertNull($seen['state']['dice'], 'the next player rolls first');
        [, , , $r] = $this->api('move', ['game' => $a['game'], 'roll' => true], $tok[$other]);
        $this->assertTrue($r['state']['dice'] !== null || count($r['state']['moves']) === 2, 'rolled: dice to play, or no move and the turn passed');
        [, , , $x] = $this->api('resign', ['game' => $a['game']], $tok['white']);
        $this->assertSame(['finished', 'black', 'resign'], [$x['state']['status'], $x['state']['winner'], $x['state']['reason']]);
    }

    /** TC-API-203 | FR-GAMES-09: looking back: the board after the first K turns, without dice */
    public function test_state_after_k_turns()
    {
        [, , , $j] = $this->api('new', ['mode' => 'computer', 'color' => 'black', 'difficulty' => 'medium']);
        $s = $j['state'];
        if ($s['dice'] === null) $s = $this->api('move', ['game' => $j['game'], 'roll' => true], $j['token'])[3]['state'];
        while ($s['dice'] === null && $s['status'] === 'active') $s = $this->api('move', ['game' => $j['game'], 'roll' => true], $j['token'])[3]['state'];
        $s = $this->api('move', ['game' => $j['game'], 'play' => $this->legalPlay($s)], $j['token'])[3]['state'];
        $n = count($s['moves']);
        $this->assertGreaterThanOrEqual(2, $n);
        [, , , $v] = $this->api('state', [], $j['token'], "&game={$j['game']}&ply=0");
        $this->assertSame([0, 0, 0, 0, 0, 0, 5, 0, 3, 0, 0, 0, 0, 5, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 2, 0], $v['state']['board']['white']);
        $this->assertSame([], $v['state']['moves']);
        $this->assertNull($v['state']['dice']);
        $this->assertSame($s['first'], $v['state']['toMove']);
        [, , , $v] = $this->api('state', [], $j['token'], "&game={$j['game']}&ply=99");
        $this->assertSame($n, $v['state']['ply'], 'clamped to the turns played');
    }
}
