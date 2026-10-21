<?php
/**
 * System tests of the Hnefatafl API (src/hnefatafl.php on board-game-api.inc.php) over HTTP.
 * Seats: black = the attackers (move first), white = the defenders with the king.
 * Traceability: FR-HNEFATAFL-01..08, FR-GAMES-07, FR-GAMES-08, US-25.
 */
require_once '/var/www/html/hnefatafl-engine.inc.php';

class HnefataflApiTest extends HakooTestCase
{
    private function api(string $action, array $body = [], ?string $token = null, string $query = '', ?string $cookie = null): array
    {
        $headers = $token ? ['X-Game-Token: ' . $token] : [];
        $method = in_array($action, ['state', 'me', 'crowns'], true) ? 'GET' : 'POST';
        return $this->http($method, "hnefatafl.php?action=$action$query", $method === 'POST' ? $body : null, $cookie, $headers);
    }

    private function move(string $game, ?string $token, array $m): array
    {
        return $this->api('move', ['game' => $game, 'from' => [$m[0], $m[1]], 'to' => [$m[2], $m[3]]], $token);
    }

    /**
     * Moves of a game (seeded, the same every run) after which the defenders (white) are to move
     * and the king can walk to a corner. Returns [the moves, the winning king move].
     */
    private function kingEscapes(): array
    {
        for ($seed = 1; ; $seed++) {
            $rng = new \Random\Randomizer(new \Random\Engine\Mt19937($seed));
            $g = new HnefataflGame();
            $moves = [];
            while (!$g->isOver() && count($moves) < 80) {
                if ($g->toMove() === 'white') {
                    foreach ($g->legal() as $m) {
                        if (isset(HnefataflBoard::CORNERS[$m[2] * 11 + $m[3]])) return [$moves, $m];
                    }
                }
                $legal = $g->legal();
                $moves[] = $legal[$rng->getInt(0, count($legal) - 1)];
                $g->play($g->toMove(), ...end($moves));
            }
        }
    }

    /** TC-API-114 | FR-HNEFATAFL-01, FR-HNEFATAFL-05: the start position, the attackers first; the computer opens when it attacks */
    public function test_new_games()
    {
        [, , , $j] = $this->api('new', ['mode' => 'computer', 'color' => 'black', 'difficulty' => 'easy']);
        $s = $j['state'];
        $this->assertSame(['black', 'black', 'white'], [$s['toMove'], $s['you'], $s['computer']]);
        $this->assertSame(HnefataflBoard::START, $s['board']);
        $this->assertCount(116, $s['legal']);
        $this->assertSame(['black' => 24, 'white' => 13], $s['pieces']);

        [, , , $j] = $this->api('new', ['mode' => 'computer', 'color' => 'white', 'difficulty' => 'hard']);
        $this->assertCount(1, $j['state']['moves']);
        $this->assertSame('white', $j['state']['toMove']);
    }

    /** TC-API-115 | FR-HNEFATAFL-01, FR-HNEFATAFL-05: a move is stored and the computer answers in the same call */
    public function test_move_and_computer_answers()
    {
        [, , , $j] = $this->api('new', ['mode' => 'computer', 'color' => 'black', 'difficulty' => 'medium']);
        [$status, , , $m] = $this->move($j['game'], $j['token'], [3, 0, 2, 0]);
        $this->assertSame(200, $status);
        $this->assertSame([3, 0, 2, 0], $m['state']['moves'][0]);
        $this->assertCount(2, $m['state']['moves']);
        $this->assertSame('black', $m['state']['toMove']);
        $this->assertSame('a', $m['state']['board'][2][0]);

        $stored = json_decode($this->pdo->query("SELECT moves FROM hakoo_hnefatafl_games WHERE id = '{$j['game']}'")->fetchColumn(), true);
        $this->assertCount(5, $stored[0], 'from, to and time of each move');
    }

    /** TC-API-116 | FR-HNEFATAFL-02: illegal moves, bad requests and wrong turns are refused and change nothing */
    public function test_bad_moves_are_refused()
    {
        [, , , $a] = $this->api('new', ['mode' => 'friend', 'color' => 'black']);
        [, , , $b] = $this->api('join', ['game' => $a['game'], 'invite' => $a['invite']]);
        $game = $a['game'];
        $cases = [[[2, 2, 2, 3], 409, 'no_piece'], [[3, 5, 3, 4], 409, 'not_your_piece'], [[3, 0, 2, 1], 400, 'not_straight'],
                  [[5, 0, 5, 2], 409, 'blocked'], [[0, 3, 0, 0], 409, 'restricted'], [[0, 3, 0, 11], 400, 'outside']];
        foreach ($cases as [$m, $code, $err]) {
            [$status, , , $e] = $this->move($game, $a['token'], $m);
            $this->assertSame([$code, $err], [$status, $e['error']], implode(',', $m));
        }
        [$status, , , $e] = $this->api('move', ['game' => $game, 'from' => [3, '0'], 'to' => [2, 0]], $a['token']);
        $this->assertSame([400, 'bad_request'], [$status, $e['error']]);
        [$status, , , $e] = $this->move($game, $b['token'], [3, 5, 3, 4]);
        $this->assertSame([409, 'not_your_turn'], [$status, $e['error']]);
        [$status] = $this->move($game, 'wrong', [3, 0, 2, 0]);
        $this->assertSame(403, $status);
        [, , , $s] = $this->api('state', [], $a['token'], "&game=$game");
        $this->assertSame([], $s['state']['moves']);
    }

    /** TC-API-117 | FR-HNEFATAFL-03, FR-HNEFATAFL-06: two friends; the king escapes to a corner and the defenders win */
    public function test_friend_game_king_escapes()
    {
        [, $headers, , $a] = $this->api('new', ['mode' => 'friend', 'color' => 'white', 'name' => 'Alice']);
        $this->assertEmpty(array_filter($headers, fn($h) => stripos($h, 'Set-Cookie:') === 0));
        [, , , $b] = $this->api('join', ['game' => $a['game'], 'invite' => $a['invite'], 'name' => 'Bob']);
        $this->assertSame(['black' => 'Bob', 'white' => 'Alice'], $b['state']['names']);
        [$moves, $win] = $this->kingEscapes();
        foreach ($moves as $i => $m) {
            [$status] = $this->move($a['game'], $i % 2 ? $a['token'] : $b['token'], $m);
            $this->assertSame(200, $status, "move $i");
        }
        [, , , $r] = $this->move($a['game'], $a['token'], $win);
        $this->assertSame(['finished', 'white', 'corner'], [$r['state']['status'], $r['state']['winner'], $r['state']['reason']]);
        $this->assertSame([], $r['state']['legal']);
        [, , , $s] = $this->api('state', [], $b['token'], "&game={$a['game']}");
        $this->assertSame('corner', $s['state']['reason']);
    }

    /** TC-API-118 | FR-HNEFATAFL-05: a whole game against Easy: after every call it is the player's turn again or the game is over */
    public function test_whole_game_against_the_computer()
    {
        [, , , $j] = $this->api('new', ['mode' => 'computer', 'color' => 'white', 'difficulty' => 'easy']);
        $s = $j['state'];
        $rng = new \Random\Randomizer(new \Random\Engine\Mt19937(3));
        for ($k = 0; $k < 60 && $s['status'] === 'active'; $k++) {
            $this->assertSame('white', $s['toMove'], 'never left waiting for the computer');
            $corner = array_values(array_filter($s['legal'], fn($m) => isset(HnefataflBoard::CORNERS[$m[2] * 11 + $m[3]])));
            $m = $corner[0] ?? $s['legal'][$rng->getInt(0, count($s['legal']) - 1)];   // take a corner when there is one
            [$status, , , $r] = $this->move($j['game'], $j['token'], $m);
            $this->assertSame(200, $status);
            $s = $r['state'];
        }
        if ($s['status'] === 'active') {
            [, , , $r] = $this->api('end', ['game' => $j['game']], $j['token']);
            $s = $r['state'];
        }
        $this->assertContains($s['status'], ['finished', 'ended']);
    }

    /** TC-API-119 | FR-HNEFATAFL-07, FR-HNEFATAFL-08: a logged-in win on Easy unlocks piece colours; locked looks are refused */
    public function test_rewards_and_look()
    {
        $carol = $this->sessionCookie(self::CAROL);
        [, , , $j] = $this->api('new', ['mode' => 'computer', 'color' => 'white', 'difficulty' => 'easy'], null, '', $carol);
        $this->assertTrue($j['state']['rewardGame']);
        [$moves, $win] = $this->kingEscapes();
        $this->pdo->prepare('UPDATE hakoo_hnefatafl_games SET moves = ? WHERE id = ?')
            ->execute([json_encode(array_map(fn($m) => [...$m, 0], $moves)), $j['game']]);
        [, , , $r] = $this->move($j['game'], $j['token'], $win);
        $this->assertSame(['white', 'corner', 'easy'], [$r['state']['winner'], $r['state']['reason'], $r['unlocked']]);

        [, , , $me] = $this->api('me', [], null, '', $carol);
        $this->assertSame(['easy' => true, 'medium' => false, 'hard' => false], $me['unlocked']);
        [$status, , , $ok] = $this->api('look', ['pieceBlack' => 'red', 'pieceWhite' => 'bone', 'board' => 'oak'], null, '', $carol);
        $this->assertSame([200, 'red', 'oak'], [$status, $ok['look']['pieceBlack'], $ok['look']['board']]);
        [$status, , , $e] = $this->api('look', ['pieceBlack' => 'red', 'pieceWhite' => 'bone', 'pieces3d' => true], null, '', $carol);
        $this->assertSame([403, 'locked'], [$status, $e['error']]);
        [$status, , , $e] = $this->api('look', ['pieceBlack' => 'red', 'pieceWhite' => 'red'], null, '', $carol);
        $this->assertSame([400, 'same_colors'], [$status, $e['error']]);
    }

    /** TC-API-120 | FR-HNEFATAFL-08, FR-GAMES-07: Alice's Hard win gives a crown, in the game's list and in crowns.php */
    public function test_crowns()
    {
        [, , , $c] = $this->api('crowns');
        $this->assertSame([self::ALICE], $c['ids']);
        [, , , $all] = $this->http('GET', 'crowns.php');
        $this->assertContains('hnefatafl', $all['games'][self::ALICE]);
    }

    /** TC-API-121 | FR-HNEFATAFL-06: looking back shows the board after K moves */
    public function test_looking_back()
    {
        [, , , $j] = $this->api('new', ['mode' => 'computer', 'color' => 'black', 'difficulty' => 'easy']);
        $this->move($j['game'], $j['token'], [3, 0, 2, 0]);
        [, , , $s] = $this->api('state', [], $j['token'], "&game={$j['game']}&ply=0");
        $this->assertSame([HnefataflBoard::START, [], 0], [$s['state']['board'], $s['state']['moves'], $s['state']['ply']]);
    }
}
