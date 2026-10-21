<?php
/**
 * System tests of the Nine men's morris API (src/morris.php on board-game-api.inc.php) over HTTP.
 * Traceability: FR-MORRIS-01..08, FR-GAMES-07, FR-GAMES-08, US-36.
 */
require_once '/var/www/html/morris-engine.inc.php';

class MorrisApiTest extends HakooTestCase
{
    private function api(string $action, array $body = [], ?string $token = null, string $query = '', ?string $cookie = null): array
    {
        $headers = $token ? ['X-Game-Token: ' . $token] : [];
        $method = in_array($action, ['state', 'me', 'crowns'], true) ? 'GET' : 'POST';
        return $this->http($method, "morris.php?action=$action$query", $method === 'POST' ? $body : null, $cookie, $headers);
    }

    /** A turn: [to] places, [from, to] moves, a third value takes. */
    private function turn(string $game, ?string $token, int $from, int $to, int $take = -1): array
    {
        return $this->api('move', ['game' => $game, 'to' => $to] + ($from >= 0 ? ['from' => $from] : []) + ($take >= 0 ? ['take' => $take] : []), $token);
    }

    /**
     * A game (seeded, the same every run) in which white, played by Medium, beats random black turns.
     * Returns the turns [from, to, take]; the last one is white's winning turn.
     */
    private function whiteWins(): array
    {
        for ($seed = 1; ; $seed++) {
            $rng = new \Random\Randomizer(new \Random\Engine\Mt19937($seed));
            $g = new MorrisGame(true);
            $turns = [];
            while (!$g->isOver() && count($turns) < 200) {
                if ($g->toMove() === 'white') $t = (new MorrisAI('medium', $rng))->choose($g);
                else { $all = $g->pos->turns(); $t = $all[$rng->getInt(0, count($all) - 1)]; }
                $g->play($g->toMove(), ...$t);
                $turns[] = $t;
            }
            if ($g->winner === 'white') return $turns;
        }
    }

    /** TC-API-221 | FR-MORRIS-01, FR-MORRIS-07: new games: white first, flying on by default or off; the computer opens when it is white */
    public function test_new_games()
    {
        [, , , $j] = $this->api('new', ['mode' => 'computer', 'color' => 'white', 'difficulty' => 'easy']);
        $s = $j['state'];
        $this->assertSame([true, 'white', [], str_repeat('.', 24)], [$s['flying'], $s['toMove'], $s['moves'], $s['board']]);
        $this->assertSame(['white' => 9, 'black' => 9], $s['hand']);
        $this->assertCount(24, $s['legal']);
        $this->assertSame([-1, 0, false], $s['legal'][0]);

        [, , , $j] = $this->api('new', ['mode' => 'computer', 'color' => 'black', 'difficulty' => 'hard', 'flying' => false]);
        $this->assertSame([false, 'black'], [$j['state']['flying'], $j['state']['toMove']]);
        $this->assertCount(1, $j['state']['moves'], 'the computer placed the first stone');
        $this->assertSame('0', (string)$this->pdo->query("SELECT flying FROM hakoo_morris_games WHERE id = '{$j['game']}'")->fetchColumn());

        [$status, , , $e] = $this->api('new', ['mode' => 'computer', 'flying' => 'yes']);
        $this->assertSame([400, 'bad_request'], [$status, $e['error']]);
    }

    /** TC-API-222 | FR-MORRIS-02, FR-MORRIS-06: a stone is placed and stored, and the computer answers in the same call; bad turns are refused */
    public function test_turns_and_bad_requests()
    {
        [, , , $j] = $this->api('new', ['mode' => 'computer', 'color' => 'white', 'difficulty' => 'medium']);
        [$status, , , $m] = $this->turn($j['game'], $j['token'], -1, 4);
        $this->assertSame(200, $status);
        $this->assertSame([-1, 4, -1], $m['state']['moves'][0]);
        $this->assertCount(2, $m['state']['moves']);
        $this->assertSame(['white' => 8, 'black' => 8], $m['state']['hand']);
        $stored = json_decode($this->pdo->query("SELECT moves FROM hakoo_morris_games WHERE id = '{$j['game']}'")->fetchColumn(), true);
        $this->assertCount(4, $stored[0], 'from, to, take and time of each turn');

        $taken = $m['state']['moves'][1][1];
        [$status, , , $e] = $this->turn($j['game'], $j['token'], -1, $taken);
        $this->assertSame([409, 'occupied'], [$status, $e['error']]);
        [$status, , , $e] = $this->turn($j['game'], $j['token'], 4, 1);
        $this->assertSame([409, 'place_first'], [$status, $e['error']]);
        [$status, , , $e] = $this->api('move', ['game' => $j['game']], $j['token']);
        $this->assertSame([400, 'bad_request'], [$status, $e['error']]);
        [$status, , , $e] = $this->api('move', ['game' => $j['game'], 'to' => '5'], $j['token']);
        $this->assertSame([400, 'bad_request'], [$status, $e['error']]);
        [$status, , , $e] = $this->turn($j['game'], $j['token'], -1, 30);
        $this->assertSame([400, 'bad_point'], [$status, $e['error']]);
        [$status] = $this->turn($j['game'], 'wrong', -1, 5);
        $this->assertSame(403, $status);
    }

    /** TC-API-223 | FR-MORRIS-03, FR-MORRIS-07: two friends: a mill must take a stone of the other player, and the state shows it */
    public function test_friend_game_mill()
    {
        [, $headers, , $a] = $this->api('new', ['mode' => 'friend', 'color' => 'white', 'name' => 'Alice']);
        $this->assertEmpty(array_filter($headers, fn($h) => stripos($h, 'Set-Cookie:') === 0));
        [, , , $b] = $this->api('join', ['game' => $a['game'], 'invite' => $a['invite'], 'name' => 'Bob']);
        $game = $a['game'];
        foreach ([[$a, 0], [$b, 9], [$a, 1], [$b, 10]] as [$who, $to]) $this->assertSame(200, $this->turn($game, $who['token'], -1, $to)[0]);
        [$status, , , $e] = $this->turn($game, $b['token'], -1, 2);
        $this->assertSame([409, 'not_your_turn'], [$status, $e['error']]);
        [$status, , , $e] = $this->turn($game, $a['token'], -1, 2);
        $this->assertSame([409, 'must_take'], [$status, $e['error']]);
        [$status, , , $e] = $this->turn($game, $a['token'], -1, 2, 21);
        $this->assertSame([409, 'not_their_stone'], [$status, $e['error']]);
        [, , , $s] = $this->api('state', [], $a['token'], "&game=$game");
        $this->assertSame([[-1, 2, true]], array_values(array_filter($s['state']['legal'], fn($t) => $t[1] === 2)));
        $this->assertSame([9, 10], $s['state']['removable']);
        [, , , $r] = $this->turn($game, $a['token'], -1, 2, 9);
        $this->assertSame([[0, 1, 2]], $r['state']['mills']);
        $this->assertSame([-1, 2, 9], $r['state']['moves'][4]);
        $this->assertSame(['white' => 9, 'black' => 8], $r['state']['left']);
        [, , , $r] = $this->api('end', ['game' => $game], $b['token']);
        $this->assertSame(['ended', null], [$r['state']['status'], $r['state']['winner']]);
    }

    /** TC-API-224 | FR-MORRIS-05, FR-MORRIS-08: a logged-in win on Easy unlocks stone colours; locked looks are refused */
    public function test_rewards_and_look()
    {
        $carol = $this->sessionCookie(self::CAROL);
        [, , , $j] = $this->api('new', ['mode' => 'computer', 'color' => 'white', 'difficulty' => 'easy'], null, '', $carol);
        $this->assertTrue($j['state']['rewardGame']);
        $turns = $this->whiteWins();
        [$f, $t, $x] = array_pop($turns);
        $this->pdo->prepare('UPDATE hakoo_morris_games SET moves = ? WHERE id = ?')
            ->execute([json_encode(array_map(fn($m) => [...$m, 0], $turns)), $j['game']]);
        [, , , $r] = $this->turn($j['game'], $j['token'], $f, $t, $x);
        $this->assertSame('white', $r['state']['winner']);
        $this->assertContains($r['state']['reason'], ['stones', 'blocked']);
        $this->assertSame('easy', $r['unlocked']);

        [, , , $me] = $this->api('me', [], null, '', $carol);
        $this->assertSame(['easy' => true, 'medium' => false, 'hard' => false], $me['unlocked']);
        [$status, , , $ok] = $this->api('look', ['stoneWhite' => 'gold', 'stoneBlack' => 'blue', 'board' => 'slate'], null, '', $carol);
        $this->assertSame([200, 'gold', 'slate'], [$status, $ok['look']['stoneWhite'], $ok['look']['board']]);
        [$status, , , $e] = $this->api('look', ['stoneWhite' => 'gold', 'stoneBlack' => 'blue', 'stones3d' => true], null, '', $carol);
        $this->assertSame([403, 'locked'], [$status, $e['error']]);
        [$status, , , $e] = $this->api('look', ['stoneWhite' => 'gold', 'stoneBlack' => 'gold'], null, '', $carol);
        $this->assertSame([400, 'same_colors'], [$status, $e['error']]);
    }

    /** TC-API-225 | FR-MORRIS-07, FR-GAMES-07: Alice's Hard win gives a crown; looking back shows the board after K turns */
    public function test_crowns_and_looking_back()
    {
        [, , , $c] = $this->api('crowns');
        $this->assertSame([self::ALICE], $c['ids']);
        [, , , $all] = $this->http('GET', 'crowns.php');
        $this->assertContains('morris', $all['games'][self::ALICE]);

        [, , , $j] = $this->api('new', ['mode' => 'computer', 'color' => 'white', 'difficulty' => 'easy']);
        $this->turn($j['game'], $j['token'], -1, 4);
        [, , , $s] = $this->api('state', [], $j['token'], "&game={$j['game']}&ply=1");
        $this->assertSame([[[-1, 4, -1]], 1, '....w...................'], [$s['state']['moves'], $s['state']['ply'], $s['state']['board']]);
    }
}
