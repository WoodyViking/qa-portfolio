<?php
/**
 * System tests of the Othello API (src/othello.php on board-game-api.inc.php) over HTTP.
 * Traceability: FR-OTHELLO-01..07, FR-GAMES-01/02.
 */
class OthelloApiTest extends HakooTestCase
{
    private function api(string $action, array $body = [], ?string $token = null, string $query = ''): array
    {
        $headers = $token ? ['X-Game-Token: ' . $token] : [];
        $method = $action === 'state' ? 'GET' : 'POST';
        return $this->http($method, "othello.php?action=$action$query", $method === 'POST' ? $body : null, null, $headers);
    }

    private function move(string $game, ?string $token, int $row, int $col): array
    {
        return $this->api('move', ['game' => $game, 'row' => $row, 'col' => $col], $token);
    }

    /** TC-API-097 | FR-OTHELLO-01, FR-OTHELLO-04: the start position, black first; the computer opens when it is black */
    public function test_new_games()
    {
        [, , , $j] = $this->api('new', ['mode' => 'computer', 'color' => 'black', 'difficulty' => 'easy']);
        $s = $j['state'];
        $this->assertSame(['black', 'black', 'white'], [$s['toMove'], $s['you'], $s['computer']]);
        $this->assertSame('...wb...', $s['board'][3]);
        $this->assertSame([[2, 3], [3, 2], [4, 5], [5, 4]], $s['legal']);
        $this->assertSame(['black' => 2, 'white' => 2], $s['discs']);

        [, , , $j] = $this->api('new', ['mode' => 'computer', 'color' => 'white', 'difficulty' => 'hard']);
        $this->assertCount(1, $j['state']['moves']);
        $this->assertSame('white', $j['state']['toMove']);
        $this->assertSame(['black' => 4, 'white' => 1], $j['state']['discs']);
    }

    /** TC-API-098 | FR-OTHELLO-01, FR-OTHELLO-04: a move flips discs and the computer answers in the same call */
    public function test_move_flips_and_computer_answers()
    {
        [, , , $j] = $this->api('new', ['mode' => 'computer', 'color' => 'black', 'difficulty' => 'medium']);
        [$status, , , $m] = $this->move($j['game'], $j['token'], 2, 3);
        $this->assertSame(200, $status);
        $this->assertSame([2, 3], $m['state']['moves'][0]);
        $this->assertCount(2, $m['state']['moves']);
        $this->assertSame('black', $m['state']['toMove']);
        $this->assertSame(64, array_sum($m['state']['discs']) + substr_count(implode('', $m['state']['board']), '.'));

        $stored = json_decode($this->pdo->query("SELECT moves FROM hakoo_othello_games WHERE id = '{$j['game']}'")->fetchColumn(), true);
        $this->assertCount(3, $stored[0], 'row, col and time of each move');
    }

    /** TC-API-099 | FR-OTHELLO-02: moves that flip nothing, taken squares, bad requests and wrong turns are refused */
    public function test_bad_moves_are_refused()
    {
        [, , , $a] = $this->api('new', ['mode' => 'friend', 'color' => 'black']);
        [, , , $b] = $this->api('join', ['game' => $a['game'], 'invite' => $a['invite']]);
        $game = $a['game'];
        foreach ([[0, 0, 409, 'no_flips'], [3, 3, 409, 'occupied'], [9, 9, 400, 'outside']] as [$r, $c, $code, $err]) {
            [$status, , , $e] = $this->move($game, $a['token'], $r, $c);
            $this->assertSame([$code, $err], [$status, $e['error']], "$r,$c");
        }
        [$status, , , $e] = $this->api('move', ['game' => $game, 'row' => '2', 'col' => 3], $a['token']);
        $this->assertSame([400, 'bad_request'], [$status, $e['error']]);
        [$status, , , $e] = $this->move($game, $b['token'], 2, 4);
        $this->assertSame([409, 'not_your_turn'], [$status, $e['error']]);
        [$status] = $this->move($game, 'wrong', 2, 3);
        $this->assertSame(403, $status);
    }

    /**
     * TC-API-100 | FR-OTHELLO-01, FR-OTHELLO-04: a whole game against Easy through the API. After every
     * call it is the player's turn again or the game is over: the computer moves again when the player must pass.
     */
    public function test_whole_game_against_the_computer()
    {
        [, , , $j] = $this->api('new', ['mode' => 'computer', 'color' => 'white', 'difficulty' => 'easy']);
        $s = $j['state'];
        $rng = new \Random\Randomizer(new \Random\Engine\Mt19937(5));
        for ($k = 0; $k < 64 && $s['status'] === 'active'; $k++) {
            $this->assertSame('white', $s['toMove'], 'never left waiting for the computer');
            $this->assertNotEmpty($s['legal']);
            [$r, $c] = $s['legal'][$rng->getInt(0, count($s['legal']) - 1)];
            [$status, , , $m] = $this->move($j['game'], $j['token'], $r, $c);
            $this->assertSame(200, $status);
            $s = $m['state'];
        }
        $this->assertSame('finished', $s['status']);
        $this->assertContains($s['reason'], ['discs', 'draw']);
        $this->assertSame([], $s['legal']);
        $d = $s['discs'];
        $this->assertSame($d['black'] === $d['white'] ? null : ($d['black'] > $d['white'] ? 'black' : 'white'), $s['winner']);
    }

    /** TC-API-101 | FR-OTHELLO-05, FR-OTHELLO-06, FR-GAMES-02: invite, join, resign; the API sets no cookie */
    public function test_friend_game_resign_and_no_cookies()
    {
        [, $headers, , $a] = $this->api('new', ['mode' => 'friend', 'color' => 'white', 'name' => 'Alice']);
        $this->assertEmpty(array_filter($headers, fn($h) => stripos($h, 'Set-Cookie:') === 0));
        [, , , $b] = $this->api('join', ['game' => $a['game'], 'invite' => $a['invite'], 'name' => 'Bob']);
        $this->assertSame(['black' => 'Bob', 'white' => 'Alice'], $b['state']['names']);
        $this->move($a['game'], $b['token'], 2, 3);
        [, , , $s] = $this->api('state', [], $a['token'], "&game={$a['game']}");
        $this->assertSame([[2, 3]], $s['state']['moves']);
        [, , , $r] = $this->api('resign', ['game' => $a['game']], $a['token']);
        $this->assertSame(['finished', 'black', 'resign'], [$r['state']['status'], $r['state']['winner'], $r['state']['reason']]);
    }

    /** TC-API-112 | FR-GAMES-09: looking back: the state after the first K moves */
    public function test_state_after_k_moves()
    {
        [, , , $j] = $this->api('new', ['mode' => 'computer', 'color' => 'black', 'difficulty' => 'easy']);
        [, , , $m] = $this->move($j['game'], $j['token'], 2, 3);
        $this->assertCount(2, $m['state']['moves']);
        $this->assertSame([], $m['state']['passes']);

        [, , , $s] = $this->api('state', [], $j['token'], "&game={$j['game']}&ply=0");
        $this->assertSame(0, $s['state']['ply']);
        $this->assertSame([], $s['state']['moves']);
        $this->assertSame(['black' => 2, 'white' => 2], $s['state']['discs']);
        [, , , $s] = $this->api('state', [], $j['token'], "&game={$j['game']}&ply=1");
        $this->assertSame([[2, 3]], $s['state']['moves']);
        $this->assertSame(['black' => 4, 'white' => 1], $s['state']['discs']);
        [, , , $s] = $this->api('state', [], $j['token'], "&game={$j['game']}&ply=99");
        $this->assertSame(2, $s['state']['ply'], 'clamped to the moves played');
        [$status] = $this->api('state', [], 'wrong', "&game={$j['game']}&ply=0");
        $this->assertSame(403, $status, 'only players can look back');
    }
}
