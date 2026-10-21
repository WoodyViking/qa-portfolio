<?php
/**
 * System tests of the Stratego API (src/stratego.php on board-game-api.inc.php) over HTTP.
 * Seats: black = Red (moves first), white = Blue. The set-up comes first; hidden pieces never leave the server.
 * Traceability: FR-STRATEGO-01..09, FR-GAMES-07, FR-GAMES-08, US-27.
 */
require_once '/var/www/html/stratego-engine.inc.php';

class StrategoApiTest extends HakooTestCase
{
    /** Red's set-up, board rows 7-10 from the top (as in StrategoEngineTest). */
    const RED = '2222222233' . '3334444555' . '5666677788' . '9MSBBBBBBF';
    /** Blue's set-up, board rows 1-4 from the top. */
    const BLUE = '9MSBBBBBBF' . '5666677788' . '3334444555' . '2222222233';

    private function api(string $action, array $body = [], ?string $token = null, string $query = '', ?string $cookie = null): array
    {
        $headers = $token ? ['X-Game-Token: ' . $token] : [];
        $method = in_array($action, ['state', 'me', 'crowns'], true) ? 'GET' : 'POST';
        return $this->http($method, "stratego.php?action=$action$query", $method === 'POST' ? $body : null, $cookie, $headers);
    }

    private function move(string $game, ?string $token, array $m): array
    {
        return $this->api('move', ['game' => $game, 'from' => [$m[0], $m[1]], 'to' => [$m[2], $m[3]]], $token);
    }

    private function sendSetup(string $game, ?string $token, string $s): array
    {
        return $this->api('move', ['game' => $game, 'setup' => $s], $token);
    }

    /** The codes of one seat's pieces in a state. */
    private function codes(array $state, string $seat): array
    {
        return array_column(array_filter($state['pieces'], fn($p) => $p[2] === $seat), 3);
    }

    /** TC-API-140 | FR-STRATEGO-01, FR-STRATEGO-06: a new game starts with the set-up; the computer sets up at once and opens as Red */
    public function test_new_games()
    {
        [, , , $j] = $this->api('new', ['mode' => 'computer', 'color' => 'black', 'difficulty' => 'easy']);
        $s = $j['state'];
        $this->assertSame([false, ['black' => false, 'white' => true], [], []], [$s['started'], $s['ready'], $s['pieces'], $s['legal']]);
        $this->assertSame('black', $s['toMove']);

        [, , , $j] = $this->api('new', ['mode' => 'computer', 'color' => 'white', 'difficulty' => 'hard']);
        $s = $j['state'];
        $this->assertSame(['black' => true, 'white' => false], $s['ready']);
        $this->assertSame([], $s['moves'], 'the computer waits for your set-up before it moves');
        [, , , $r] = $this->sendSetup($j['game'], $j['token'], self::BLUE);
        $this->assertTrue($r['state']['started']);
        $this->assertCount(1, $r['state']['moves'], 'then Red (the computer) opens');
        $this->assertSame('white', $r['state']['toMove']);
    }

    /** TC-API-141 | FR-STRATEGO-04: the other army is never sent: no set-up, only '?' for pieces not yet seen */
    public function test_hidden_pieces_stay_on_the_server()
    {
        [, , , $j] = $this->api('new', ['mode' => 'computer', 'color' => 'black', 'difficulty' => 'medium']);
        [, , $raw, $r] = $this->sendSetup($j['game'], $j['token'], self::RED);
        $this->assertStringNotContainsString('setup"', $raw);
        $this->assertSame(array_fill(0, 40, '?'), $this->codes($r['state'], 'white'));
        $this->assertCount(40, array_filter($this->codes($r['state'], 'black'), fn($c) => $c !== '?'));
        $stored = $this->pdo->query("SELECT white_setup FROM hakoo_stratego_games WHERE id = '{$j['game']}'")->fetchColumn();
        $this->assertTrue(StrategoGame::validSetup($stored));
        $this->assertStringNotContainsString($stored, $raw);

        [$status, , , $m] = $this->move($j['game'], $j['token'], [6, 8, 5, 8]);                // a Miner steps out; the computer answers
        $this->assertSame(200, $status);
        $this->assertCount(2, $m['state']['moves']);
        $seen = array_filter($m['state']['pieces'], fn($p) => $p[2] === 'white' && $p[3] !== '?');
        foreach ($seen as $p) $this->assertTrue($p[5], 'an enemy piece is shown only when it has been seen');
        // Looking back during the game is just as secret.
        [, , , $b] = $this->api('state', [], $j['token'], "&game={$j['game']}&ply=0");
        $this->assertSame(array_fill(0, 40, '?'), $this->codes($b['state'], 'white'));
    }

    /** TC-API-142 | FR-STRATEGO-02, FR-STRATEGO-06: bad set-ups and moves are refused with a reason */
    public function test_bad_setups_and_moves()
    {
        [, , , $j] = $this->api('new', ['mode' => 'computer', 'color' => 'black', 'difficulty' => 'easy']);
        $g = $j['game']; $t = $j['token'];
        [$status, , , $e] = $this->move($g, $t, [6, 0, 5, 0]);
        $this->assertSame([409, 'not_set_up'], [$status, $e['error']]);
        foreach ([[str_repeat('2', 40), 400, 'bad_setup'], [['x'], 400, 'bad_request']] as [$s, $code, $err]) {
            [$status, , , $e] = $this->api('move', ['game' => $g, 'setup' => $s], $t);
            $this->assertSame([$code, $err], [$status, $e['error']]);
        }
        $this->sendSetup($g, $t, self::RED);
        [$status, , , $e] = $this->sendSetup($g, $t, self::RED);
        $this->assertSame([409, 'already_set_up'], [$status, $e['error']]);
        foreach ([[[9, 3, 8, 3], 'cannot_move'], [[6, 2, 5, 2], 'lake'], [[6, 8, 4, 8], 'one_step'], [[7, 0, 6, 0], 'own_piece']] as [$m, $err]) {
            [$status, , , $e] = $this->move($g, $t, $m);
            $this->assertSame([409, $err], [$status, $e['error']], implode(',', $m));
        }
        [$status, , , $e] = $this->api('move', ['game' => $g, 'from' => [6, '0'], 'to' => [5, 0]], $t);
        $this->assertSame([400, 'bad_request'], [$status, $e['error']]);
    }

    /** TC-API-143 | FR-STRATEGO-06, FR-STRATEGO-07: two friends set up at the same time, in any order, and play */
    public function test_friend_game()
    {
        [, , , $a] = $this->api('new', ['mode' => 'friend', 'color' => 'black', 'name' => 'Alice']);
        [, , , $b] = $this->api('join', ['game' => $a['game'], 'invite' => $a['invite'], 'name' => 'Bob']);
        [$status, , , $r] = $this->sendSetup($a['game'], $b['token'], self::BLUE);          // Blue first, although Red is "to move"
        $this->assertSame([200, ['black' => false, 'white' => true]], [$status, $r['state']['ready']]);
        [, , , $s] = $this->api('state', [], $a['token'], "&game={$a['game']}");
        $this->assertSame([], $this->codes($s['state'], 'white'), 'Red does not see Blue\'s army before both are ready');
        $this->sendSetup($a['game'], $a['token'], self::RED);
        [$status] = $this->move($a['game'], $a['token'], [6, 0, 3, 0]);                  // Scout v Scout: both fall
        $this->assertSame(200, $status);
        [, , , $s] = $this->api('state', [], $b['token'], "&game={$a['game']}");
        $this->assertSame([6, 0, 3, 0, '2', '2', 'tie'], $s['state']['moves'][0]);
        $this->assertSame(['black' => ['2'], 'white' => ['2']], $s['state']['fallen']);
        $this->assertSame('white', $s['state']['toMove']);
        [, , , $r] = $this->api('resign', ['game' => $a['game']], $b['token']);
        $this->assertSame(['finished', 'black', 'resign'], [$r['state']['status'], $r['state']['winner'], $r['state']['reason']]);
        $this->assertNotContains('?', $this->codes($r['state'], 'black'), 'after the game everything is shown');
    }

    /** TC-API-144 | FR-STRATEGO-03, FR-STRATEGO-08: taking the flag wins; a logged-in win on Easy unlocks piece colours */
    public function test_flag_win_and_reward()
    {
        $carol = $this->sessionCookie(self::CAROL);
        [, , , $j] = $this->api('new', ['mode' => 'computer', 'color' => 'black', 'difficulty' => 'easy'], null, '', $carol);
        $this->assertTrue($j['state']['rewardGame']);
        $blue = self::BLUE;
        [$blue[9], $blue[34]] = [$blue[34], $blue[9]];                                  // Blue's flag in the front line, e4... (row 4, col e)
        $this->pdo->prepare('UPDATE hakoo_stratego_games SET black_setup = ?, white_setup = ? WHERE id = ?')
            ->execute([self::RED, $blue, $j['game']]);
        [, , , $r] = $this->move($j['game'], $j['token'], [6, 4, 3, 4]);                // a Scout runs and takes it
        $this->assertSame(['finished', 'black', 'flag', 'easy'], [$r['state']['status'], $r['state']['winner'], $r['state']['reason'], $r['unlocked']]);

        [, , , $me] = $this->api('me', [], null, '', $carol);
        $this->assertSame(['easy' => true, 'medium' => false, 'hard' => false], $me['unlocked']);
        [$status, , , $ok] = $this->api('look', ['pieceBlack' => 'gold', 'pieceWhite' => 'teal', 'board' => 'desert'], null, '', $carol);
        $this->assertSame([200, 'gold', 'desert'], [$status, $ok['look']['pieceBlack'], $ok['look']['board']]);
        [$status, , , $e] = $this->api('look', ['pieceBlack' => 'gold', 'pieceWhite' => 'teal', 'pieces3d' => true], null, '', $carol);
        $this->assertSame([403, 'locked'], [$status, $e['error']]);
        [$status, , , $e] = $this->api('look', ['pieceBlack' => 'gold', 'pieceWhite' => 'gold'], null, '', $carol);
        $this->assertSame([400, 'same_colors'], [$status, $e['error']]);
    }

    /** TC-API-145 | FR-STRATEGO-08, FR-GAMES-07: Alice's Hard win gives a crown, in the game's list and in crowns.php */
    public function test_crowns()
    {
        [, , , $c] = $this->api('crowns');
        $this->assertSame([self::ALICE], $c['ids']);
        [, , , $all] = $this->http('GET', 'crowns.php');
        $this->assertContains('stratego', $all['games'][self::ALICE]);
    }
}
