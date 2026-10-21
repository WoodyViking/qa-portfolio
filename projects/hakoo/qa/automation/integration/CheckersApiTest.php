<?php
/**
 * System tests of the Checkers API (src/checkers.php on board-game-api.inc.php) over HTTP.
 * Traceability: FR-CHECKERS-01..08, FR-GAMES-07, FR-GAMES-08, US-35.
 */
require_once '/var/www/html/checkers-engine.inc.php';

class CheckersApiTest extends HakooTestCase
{
    private function api(string $action, array $body = [], ?string $token = null, string $query = '', ?string $cookie = null): array
    {
        $headers = $token ? ['X-Game-Token: ' . $token] : [];
        $method = in_array($action, ['state', 'me', 'crowns'], true) ? 'GET' : 'POST';
        return $this->http($method, "checkers.php?action=$action$query", $method === 'POST' ? $body : null, $cookie, $headers);
    }

    private function move(string $game, ?string $token, array $path): array
    {
        return $this->api('move', ['game' => $game, 'path' => $path], $token);
    }

    /**
     * A game (seeded, the same every run) in which white, played by Medium, beats random black moves.
     * Returns the moves as [[row, col], ...] paths; the last one is white's winning move.
     */
    private function whiteWins(string $variant): array
    {
        for ($seed = 1; ; $seed++) {
            $rng = new \Random\Randomizer(new \Random\Engine\Mt19937($seed));
            $g = new CheckersGame($variant);
            $moves = [];
            while (!$g->isOver() && count($moves) < 200) {
                if ($g->toMove() === 'white') $m = (new CheckersAI('medium', $rng))->choose($g);
                else { $l = $g->legalMoves(); $m = array_map(fn($i) => CheckersGame::rc($i), $l[$rng->getInt(0, count($l) - 1)][0]); }
                $g->play($g->toMove(), $m);
                $moves[] = $m;
            }
            if ($g->winner === 'white') return $moves;
        }
    }

    /** TC-API-215 | FR-CHECKERS-01: new games with either rules; who moves first; the computer opens when it is to move */
    public function test_new_games()
    {
        [, , , $j] = $this->api('new', ['mode' => 'computer', 'color' => 'white', 'difficulty' => 'easy', 'variant' => 'international']);
        $s = $j['state'];
        $this->assertSame(['international', 'white', 'white', []], [$s['variant'], $s['first'], $s['toMove'], $s['moves']]);
        $this->assertCount(7, $s['legal']);
        $this->assertSame(['path' => [[5, 0], [4, 1]], 'caps' => []], $s['legal'][0]);
        $this->assertSame(['black' => 12, 'white' => 12], $s['pieces']);

        [, , , $j] = $this->api('new', ['mode' => 'computer', 'color' => 'white', 'difficulty' => 'hard']);
        $s = $j['state'];
        $this->assertSame(['english', 'black', 'white'], [$s['variant'], $s['first'], $s['toMove']]);
        $this->assertCount(1, $s['moves'], 'black moves first in English checkers: the computer opened');
        $this->assertSame('english', $this->pdo->query("SELECT variant FROM hakoo_checkers_games WHERE id = '{$j['game']}'")->fetchColumn());

        [$status, , , $e] = $this->api('new', ['mode' => 'computer', 'variant' => 'russian']);
        $this->assertSame([400, 'bad_variant'], [$status, $e['error']]);
    }

    /** TC-API-216 | FR-CHECKERS-06: a move is stored and the computer answers in the same call */
    public function test_move_and_computer_answers()
    {
        [, , , $j] = $this->api('new', ['mode' => 'computer', 'color' => 'black', 'difficulty' => 'medium']);
        [$status, , , $m] = $this->move($j['game'], $j['token'], [[2, 1], [3, 0]]);
        $this->assertSame(200, $status);
        $this->assertSame([[2, 1], [3, 0]], $m['state']['moves'][0]);
        $this->assertCount(2, $m['state']['moves']);
        $this->assertSame([[], []], $m['state']['took']);
        $this->assertSame('black', $m['state']['toMove']);
        $this->assertSame('b.......', $m['state']['board'][3]);

        $stored = json_decode($this->pdo->query("SELECT moves FROM hakoo_checkers_games WHERE id = '{$j['game']}'")->fetchColumn(), true);
        $this->assertSame([2, 1, 3, 0], $stored[0][0], 'the squares of each move, and its time');
        $this->assertCount(2, $stored[0]);
    }

    /** TC-API-217 | FR-CHECKERS-02: bad requests, illegal moves and wrong turns are refused and change nothing */
    public function test_bad_moves_are_refused()
    {
        [, , , $a] = $this->api('new', ['mode' => 'friend', 'color' => 'black']);
        [, , , $b] = $this->api('join', ['game' => $a['game'], 'invite' => $a['invite']]);
        $game = $a['game'];
        $cases = [[[[3, 0], [4, 1]], 409, 'no_piece'], [[[5, 0], [4, 1]], 409, 'not_your_piece'], [[[2, 1], [4, 3]], 409, 'illegal_move'],
                  [[[2, 1]], 400, 'bad_path'], [[[2, 1], [3, 8]], 400, 'bad_path'], [[[2, 1], ['3', 0]], 400, 'bad_path']];
        foreach ($cases as [$path, $code, $err]) {
            [$status, , , $e] = $this->move($game, $a['token'], $path);
            $this->assertSame([$code, $err], [$status, $e['error']], json_encode($path));
        }
        [$status, , , $e] = $this->api('move', ['game' => $game, 'path' => 'c3-d4'], $a['token']);
        $this->assertSame([400, 'bad_request'], [$status, $e['error']]);
        [$status, , , $e] = $this->move($game, $b['token'], [[5, 0], [4, 1]]);
        $this->assertSame([409, 'not_your_turn'], [$status, $e['error']]);
        [$status] = $this->move($game, 'wrong', [[2, 1], [3, 0]]);
        $this->assertSame(403, $status);
        [, , , $s] = $this->api('state', [], $a['token'], "&game=$game");
        $this->assertSame([], $s['state']['moves']);
    }

    /** TC-API-218 | FR-CHECKERS-02, FR-CHECKERS-07: two friends: a take is compulsory, the state says what each move took; resigning ends it */
    public function test_friend_game_takes()
    {
        [, $headers, , $a] = $this->api('new', ['mode' => 'friend', 'color' => 'black', 'name' => 'Alice']);
        $this->assertEmpty(array_filter($headers, fn($h) => stripos($h, 'Set-Cookie:') === 0));
        [, , , $b] = $this->api('join', ['game' => $a['game'], 'invite' => $a['invite'], 'name' => 'Bob']);
        $game = $a['game'];
        $this->assertSame(200, $this->move($game, $a['token'], [[2, 3], [3, 4]])[0]);
        $this->assertSame(200, $this->move($game, $b['token'], [[5, 2], [4, 3]])[0]);
        [$status, , , $e] = $this->move($game, $a['token'], [[2, 1], [3, 2]]);
        $this->assertSame([409, 'must_take'], [$status, $e['error']]);
        [, , , $s] = $this->api('state', [], $a['token'], "&game=$game");
        $this->assertSame([['path' => [[3, 4], [5, 2]], 'caps' => [[4, 3]]]], $s['state']['legal']);
        $this->move($game, $a['token'], [[3, 4], [5, 2]]);
        [, , , $r] = $this->move($game, $b['token'], [[6, 1], [4, 3]]);
        $this->assertSame([[], [], [[4, 3]], [[5, 2]]], $r['state']['took']);
        $this->assertSame(['black' => 11, 'white' => 11], $r['state']['pieces']);
        [, , , $r] = $this->api('resign', ['game' => $game], $a['token']);
        $this->assertSame(['finished', 'white', 'resign'], [$r['state']['status'], $r['state']['winner'], $r['state']['reason']]);
        $this->assertSame([], $r['state']['legal']);
    }

    /** TC-API-219 | FR-CHECKERS-05, FR-CHECKERS-08: a logged-in win on Easy unlocks piece colours; locked looks are refused */
    public function test_rewards_and_look()
    {
        $carol = $this->sessionCookie(self::CAROL);
        [, , , $j] = $this->api('new', ['mode' => 'computer', 'color' => 'white', 'difficulty' => 'easy', 'variant' => 'international'], null, '', $carol);
        $this->assertTrue($j['state']['rewardGame']);
        $moves = $this->whiteWins('international');
        $win = array_pop($moves);
        $this->pdo->prepare('UPDATE hakoo_checkers_games SET moves = ? WHERE id = ?')
            ->execute([json_encode(array_map(fn($p) => [array_merge(...$p), 0], $moves)), $j['game']]);
        [, , , $r] = $this->move($j['game'], $j['token'], $win);
        $this->assertSame('white', $r['state']['winner']);
        $this->assertContains($r['state']['reason'], ['no_pieces', 'no_moves']);
        $this->assertSame('easy', $r['unlocked']);

        [, , , $me] = $this->api('me', [], null, '', $carol);
        $this->assertSame(['easy' => true, 'medium' => false, 'hard' => false], $me['unlocked']);
        [$status, , , $ok] = $this->api('look', ['pieceBlack' => 'red', 'pieceWhite' => 'gold', 'board' => 'green'], null, '', $carol);
        $this->assertSame([200, 'red', 'green'], [$status, $ok['look']['pieceBlack'], $ok['look']['board']]);
        [$status, , , $e] = $this->api('look', ['pieceBlack' => 'red', 'pieceWhite' => 'gold', 'pieces3d' => true], null, '', $carol);
        $this->assertSame([403, 'locked'], [$status, $e['error']]);
        [$status, , , $e] = $this->api('look', ['pieceBlack' => 'red', 'pieceWhite' => 'red'], null, '', $carol);
        $this->assertSame([400, 'same_colors'], [$status, $e['error']]);
    }

    /** TC-API-220 | FR-CHECKERS-07, FR-GAMES-07: Alice's Hard win gives a crown; looking back shows the board after K moves */
    public function test_crowns_and_looking_back()
    {
        [, , , $c] = $this->api('crowns');
        $this->assertSame([self::ALICE], $c['ids']);
        [, , , $all] = $this->http('GET', 'crowns.php');
        $this->assertContains('checkers', $all['games'][self::ALICE]);

        [, , , $j] = $this->api('new', ['mode' => 'computer', 'color' => 'black', 'difficulty' => 'easy']);
        $this->move($j['game'], $j['token'], [[2, 1], [3, 0]]);
        [, , , $s] = $this->api('state', [], $j['token'], "&game={$j['game']}&ply=1");
        $this->assertSame([[[[2, 1], [3, 0]]], 1, 'b.......'], [$s['state']['moves'], $s['state']['ply'], $s['state']['board'][3]]);
    }
}
