<?php
/**
 * System tests of the Chess API (src/chess.php on board-game-api.inc.php) over HTTP.
 * Traceability: FR-CHESS-01..07, FR-CHESS-12..15 (Fog of War), FR-GAMES-01/02.
 */
class ChessApiTest extends HakooTestCase
{
    private function api(string $action, array $body = [], ?string $token = null, string $query = ''): array
    {
        $headers = $token ? ['X-Game-Token: ' . $token] : [];
        $method = $action === 'state' ? 'GET' : 'POST';
        return $this->http($method, "chess.php?action=$action$query", $method === 'POST' ? $body : null, null, $headers);
    }

    private function computerGame(string $color = 'white', string $level = 'easy'): array
    {
        [$status, , , $j] = $this->api('new', ['mode' => 'computer', 'color' => $color, 'difficulty' => $level, 'name' => 'Tester']);
        $this->assertSame(200, $status);
        return $j;
    }

    /** A started friend game: [game id, white token, black token]. */
    private function friendGame(): array
    {
        [, , , $a] = $this->api('new', ['mode' => 'friend', 'color' => 'white', 'name' => 'Alice']);
        [, , , $b] = $this->api('join', ['game' => $a['game'], 'invite' => $a['invite'], 'name' => 'Bob']);
        return [$a['game'], $a['token'], $b['token']];
    }

    private function move(string $game, ?string $token, string $move): array
    {
        return $this->api('move', ['game' => $game, 'move' => $move], $token);
    }

    /** TC-API-089 | FR-CHESS-01, FR-CHESS-04: a computer game starts from the start position, white to move */
    public function test_new_computer_game()
    {
        $j = $this->computerGame();
        $this->assertMatchesRegularExpression('/^[0-9a-f]{16}$/', $j['game']);
        $s = $j['state'];
        $this->assertSame(['active', 'white', 'black', 'white'], [$s['status'], $s['you'], $s['computer'], $s['toMove']]);
        $this->assertSame(['black' => 'Computer', 'white' => 'Tester'], $s['names']);
        $this->assertSame('rnbqkbnr', $s['board'][0]);
        $this->assertSame('RNBQKBNR', $s['board'][7]);
        $this->assertSame([], $s['moves']);
        $this->assertCount(20, $s['legal']);
        $this->assertNull($s['check']);
    }

    /** TC-API-090 | FR-CHESS-04: when the computer plays white it opens the game */
    public function test_computer_opens_when_it_is_white()
    {
        $j = $this->computerGame('black');
        $this->assertCount(1, $j['state']['moves']);
        $this->assertCount(1, $j['state']['san']);
        $this->assertSame('black', $j['state']['toMove']);
        $this->assertCount(20, $j['state']['legal'], 'black has 20 answers to any first move');
    }

    /** TC-API-091 | FR-CHESS-02, FR-CHESS-04: a move is stored with its time and the computer answers in the same call */
    public function test_move_and_computer_reply()
    {
        $j = $this->computerGame('white', 'hard');
        [$status, , , $m] = $this->move($j['game'], $j['token'], 'e2e4');
        $this->assertSame(200, $status);
        $this->assertCount(2, $m['state']['moves']);
        $this->assertSame('e2e4', $m['state']['moves'][0]);
        $this->assertSame('e4', $m['state']['san'][0]);
        $this->assertSame('white', $m['state']['toMove']);
        $this->assertSame('....P...', $m['state']['board'][4]);

        $stored = json_decode($this->pdo->query("SELECT moves FROM hakoo_chess_games WHERE id = '{$j['game']}'")->fetchColumn(), true);
        $this->assertSame('e2e4', $stored[0][0]);
        $this->assertEqualsWithDelta(time() * 1000, $stored[0][1], 60_000);
    }

    /** TC-API-092 | FR-CHESS-02: illegal, badly formed and out-of-turn moves are refused and change nothing */
    public function test_bad_moves_are_refused()
    {
        [$game, $white, $black] = $this->friendGame();
        foreach ([['e2e5', 409, 'illegal_move'], ['e7e5', 409, 'not_your_piece'], ['e2', 400, 'bad_move']] as [$mv, $code, $err]) {
            [$status, , , $e] = $this->move($game, $white, $mv);
            $this->assertSame([$code, $err], [$status, $e['error']], $mv);
        }
        [$status, , , $e] = $this->api('move', ['game' => $game], $white);
        $this->assertSame([400, 'bad_request'], [$status, $e['error']]);
        [$status, , , $e] = $this->move($game, $black, 'e7e5');
        $this->assertSame([409, 'not_your_turn'], [$status, $e['error']]);
        [, , , $s] = $this->api('state', [], $white, "&game=$game");
        $this->assertSame([], $s['state']['moves']);
    }

    /** TC-API-093 | FR-CHESS-06, security: only the token's owner can see or play; only hashes are stored */
    public function test_wrong_token_is_refused()
    {
        $j = $this->computerGame();
        foreach (['', 'x', str_repeat('0', 32)] as $token) {
            [$status, , , $e] = $this->move($j['game'], $token, 'e2e4');
            $this->assertSame([403, 'bad_token'], [$status, $e['error']]);
        }
        [$status] = $this->http('GET', "chess.php?action=state&game={$j['game']}", null, null, ['X-Gomoku-Token: ' . $j['token']]);
        $this->assertSame(403, $status, 'the Gomoku header is not a chess seat');
        $this->assertStringNotContainsString($j['token'], (string)$this->pdo->query("SELECT CONCAT_WS(',', black_token, white_token) FROM hakoo_chess_games WHERE id = '{$j['game']}'")->fetchColumn());
    }

    /** TC-API-094 | FR-CHESS-02, FR-CHESS-03: checkmate ends the game with a winner; no move after that */
    public function test_checkmate_ends_the_game()
    {
        [$game, $white, $black] = $this->friendGame();
        $this->move($game, $white, 'f2f3');
        $this->move($game, $black, 'e7e5');
        $this->move($game, $white, 'g2g4');
        [, , , $m] = $this->move($game, $black, 'd8h4');
        $s = $m['state'];
        $this->assertSame(['finished', 'black', 'checkmate'], [$s['status'], $s['winner'], $s['reason']]);
        $this->assertSame('e1', $s['check']);
        $this->assertSame([], $s['legal']);
        [$status, , , $e] = $this->move($game, $white, 'a2a3');
        $this->assertSame([409, 'game_over'], [$status, $e['error']]);
    }

    /** TC-API-095 | FR-CHESS-07: invite, join, and the friend's move arrives by polling */
    public function test_friend_game_and_polling()
    {
        [, , , $a] = $this->api('new', ['mode' => 'friend', 'color' => 'black', 'name' => 'Alice']);
        $this->assertSame('waiting', $a['state']['status']);
        [$status, , , $b] = $this->api('join', ['game' => $a['game'], 'invite' => $a['invite'], 'name' => 'Bob']);
        $this->assertSame(200, $status);
        $this->assertSame(['white', 'active'], [$b['state']['you'], $b['state']['status']]);
        [, , , $e] = $this->api('join', ['game' => $a['game'], 'invite' => $a['invite'], 'name' => 'Eve']);
        $this->assertSame('game_full', $e['error']);

        [, , , $s] = $this->api('state', [], $a['token'], "&game={$a['game']}");
        $v = $s['state']['version'];
        [, , , $same] = $this->api('state', [], $a['token'], "&game={$a['game']}&since=$v");
        $this->assertSame(['status' => 'ok', 'changed' => false], $same);
        $this->move($a['game'], $b['token'], 'd2d4');
        [, , , $new] = $this->api('state', [], $a['token'], "&game={$a['game']}&since=$v");
        $this->assertSame(['d2d4'], $new['state']['moves']);
        $this->assertSame('black', $new['state']['toMove']);
    }

    /** TC-API-096 | FR-CHESS-06, FR-GAMES-02: resign and end; the API sets no cookie */
    public function test_resign_end_and_no_cookies()
    {
        [$game, $white] = $this->friendGame();
        [, , , $r] = $this->api('resign', ['game' => $game], $white);
        $this->assertSame(['finished', 'black', 'resign'], [$r['state']['status'], $r['state']['winner'], $r['state']['reason']]);

        [, $headers, , $j] = $this->api('new', ['mode' => 'computer']);
        $this->assertEmpty(array_filter($headers, fn($h) => stripos($h, 'Set-Cookie:') === 0));
        [, , , $r] = $this->api('end', ['game' => $j['game']], $j['token']);
        $this->assertSame('ended', $r['state']['status']);
        $this->assertNull($r['state']['winner']);
    }

    // ---------------------------------------------------------------- Fog of War

    /** TC-API-108 | FR-CHESS-15: a player is only sent what they see: fog, hidden opponent moves, no legal moves off turn */
    public function test_fog_hides_what_a_player_cannot_see()
    {
        [, , , $a] = $this->api('new', ['mode' => 'friend', 'color' => 'white', 'variant' => 'fog']);
        [, , , $b] = $this->api('join', ['game' => $a['game'], 'invite' => $a['invite']]);
        $this->assertSame('fog', $b['state']['variant']);
        $this->move($a['game'], $a['token'], 'e2e4');

        [, , , $w] = $this->api('state', [], $a['token'], "&game={$a['game']}");
        $this->assertSame(['e2e4'], $w['state']['moves']);
        $this->assertSame('????????', $w['state']['board'][0], 'white cannot see the black pieces');
        $this->assertSame([], $w['state']['legal'], 'not white\'s turn: nothing about black\'s moves');

        [, , , $s] = $this->api('state', [], $b['token'], "&game={$a['game']}");
        $this->assertSame([''], $s['state']['moves']);
        $this->assertSame(['?'], $s['state']['san']);
        $this->assertSame('????????', $s['state']['board'][4], 'black cannot see e4');
        $this->assertStringNotContainsString('P', implode('', array_slice($s['state']['board'], 0, 6)));
        $this->assertCount(20, $s['state']['legal']);
        $this->assertNull($s['state']['check']);

        [, , , $r] = $this->api('resign', ['game' => $a['game']], $b['token']);          // over: the fog lifts
        $this->assertSame(['e2e4'], $r['state']['moves']);
        $this->assertSame('....P...', $r['state']['board'][4]);
    }

    /** TC-API-109 | FR-CHESS-13, FR-CHESS-15: taking the king wins; a fog game unlocks no reward */
    public function test_fog_king_capture_and_no_reward()
    {
        [, , , $j] = $this->http('POST', 'chess.php?action=new', ['mode' => 'computer', 'color' => 'white', 'difficulty' => 'easy', 'variant' => 'fog'],
            $this->sessionCookie(self::CAROL));
        $moves = array_map(fn($m) => [$m, 0], ['e2e4', 'f7f6', 'd1h5', 'e8f7']);
        $this->pdo->prepare('UPDATE hakoo_chess_games SET moves = ? WHERE id = ?')->execute([json_encode($moves), $j['game']]);
        [, , , $m] = $this->move($j['game'], $j['token'], 'h5f7');
        $this->assertSame(['finished', 'white', 'king'], [$m['state']['status'], $m['state']['winner'], $m['state']['reason']]);
        $this->assertNull($m['unlocked']);
        $this->assertSame(0, $this->rows('hakoo_chess_profile', 'discord_id = ?', [self::CAROL]));
    }

    /** TC-API-110 | FR-CHESS-14: against the computer in the fog, every call ends on the player's turn with a real move played */
    public function test_fog_computer_game()
    {
        foreach (['easy', 'medium', 'hard'] as $level) {
            [, , , $j] = $this->api('new', ['mode' => 'computer', 'color' => 'black', 'difficulty' => $level, 'variant' => 'fog']);
            $this->assertSame([''], $j['state']['moves'], "$level: the computer opened, hidden");
            $move = $j['state']['legal'][0];
            [$status, , , $m] = $this->move($j['game'], $j['token'], $move);
            $this->assertSame(200, $status);
            $this->assertSame(['', $move, ''], $m['state']['moves']);
            $this->assertSame('black', $m['state']['toMove']);
        }
    }

    /** TC-API-111 | FR-CHESS-12: an unknown variant is refused */
    public function test_unknown_variant_is_refused()
    {
        [$status, , , $e] = $this->api('new', ['mode' => 'computer', 'variant' => 'atomic']);
        $this->assertSame([400, 'bad_variant'], [$status, $e['error']]);
        [, , , $j] = $this->api('new', ['mode' => 'computer', 'color' => 'white']);
        $this->assertSame('standard', $j['state']['variant']);
    }

    /** TC-API-113 | FR-GAMES-09, FR-CHESS-15: looking back in a Fog of War game still hides what the player did not see */
    public function test_fog_looking_back_keeps_the_fog()
    {
        [, , , $a] = $this->api('new', ['mode' => 'friend', 'color' => 'white', 'variant' => 'fog']);
        [, , , $b] = $this->api('join', ['game' => $a['game'], 'invite' => $a['invite']]);
        $this->move($a['game'], $a['token'], 'e2e4');
        $this->move($a['game'], $b['token'], 'e7e5');
        [, , , $s] = $this->api('state', [], $b['token'], "&game={$a['game']}&ply=1");
        $this->assertSame([''], $s['state']['moves']);
        $this->assertSame('????????', $s['state']['board'][4]);
        [, , , $s] = $this->api('state', [], $a['token'], "&game={$a['game']}&ply=2");
        $this->assertSame(['e2e4', ''], $s['state']['moves']);
    }

    /** TC-API-162 | FR-CHESS-16, FR-CHESS-17: in Fog of War the state has the ghosts and the square where a piece of the player was taken */
    public function test_fog_state_has_ghosts_and_what_took_a_piece()
    {
        [, , , $a] = $this->api('new', ['mode' => 'friend', 'color' => 'white', 'variant' => 'fog']);
        [, , , $b] = $this->api('join', ['game' => $a['game'], 'invite' => $a['invite']]);
        $this->assertSame(['PPPPPPPP', 'RNBQKBNR'], array_slice($b['state']['ghosts'], 6), 'black remembers where white starts');
        [, , , $w0] = $this->api('state', [], $a['token'], "&game={$a['game']}");
        $this->assertSame(['rnbqkbnr', 'pppppppp'], array_slice($w0['state']['ghosts'], 0, 2));
        foreach ([[$a['token'], 'g1f3'], [$b['token'], 'e7e5'], [$a['token'], 'f3e5']] as [$token, $m]) $this->move($a['game'], $token, $m);

        [, , , $black] = $this->api('state', [], $b['token'], "&game={$a['game']}");
        $this->assertSame('e5', $black['state']['took']);
        $this->assertSame('?', $black['state']['board'][3][4]);
        $this->assertSame('N', $black['state']['ghosts'][3][4]);
        [, , , $white] = $this->api('state', [], $a['token'], "&game={$a['game']}");
        $this->assertNull($white['state']['took']);

        // Looking back keeps the memory of that moment: before the capture nothing was taken.
        [, , , $back] = $this->api('state', [], $b['token'], "&game={$a['game']}&ply=2");
        $this->assertNull($back['state']['took']);
        $this->assertSame('.', $back['state']['ghosts'][3][4]);
        [, , , $back] = $this->api('state', [], $b['token'], "&game={$a['game']}&ply=3");
        $this->assertSame(['e5', 'N'], [$back['state']['took'], $back['state']['ghosts'][3][4]]);

        // Over: the whole board is shown, and there is no memory to show.
        [, , , $r] = $this->api('resign', ['game' => $a['game']], $b['token']);
        $this->assertArrayNotHasKey('ghosts', $r['state']);
        $this->assertArrayNotHasKey('took', $r['state']);
    }

    /** TC-API-163 | FR-CHESS-16: a game that is not Fog of War has no ghosts */
    public function test_standard_game_has_no_ghosts()
    {
        $j = $this->computerGame();
        $this->assertArrayNotHasKey('ghosts', $j['state']);
        $this->assertArrayNotHasKey('took', $j['state']);
    }

    /** TC-API-164 | FR-CHESS-15, FR-CHESS-16: the opponent's hidden moves do not show in what a player is sent (replies on squares white cannot see) */
    public function test_fog_what_is_sent_does_not_depend_on_hidden_moves()
    {
        $views = [];
        foreach (['a7a5', 'h7h6', 'b8c6', 'g8f6'] as $reply) {
            [, , , $a] = $this->api('new', ['mode' => 'friend', 'color' => 'white', 'variant' => 'fog']);
            [, , , $b] = $this->api('join', ['game' => $a['game'], 'invite' => $a['invite']]);
            $this->move($a['game'], $a['token'], 'e2e4');
            $this->move($a['game'], $b['token'], $reply);
            [, , , $w] = $this->api('state', [], $a['token'], "&game={$a['game']}");
            $views[$reply] = [$w['state']['board'], $w['state']['ghosts'], $w['state']['took']];
        }
        $first = reset($views);
        foreach ($views as $reply => $v) $this->assertSame($first, $v, "white's view after black played $reply");
    }
}
