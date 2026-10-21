<?php
/**
 * System tests of the Chess and Othello rewards and looks (actions new/move/me/look/crowns
 * of chess.php and othello.php) and of the shared crown list (crowns.php).
 * Fixture: Alice has won on every level of every game (crowns shown), Bob on Easy only, Carol on none.
 * Traceability: FR-CHESS-08..10, FR-OTHELLO-07..09, FR-GAMES-06, US-21, US-22.
 */
require_once '/var/www/html/othello-engine.inc.php';

class BoardGameRewardsTest extends HakooTestCase
{
    private function api(string $game, string $action, array $body = [], ?string $cookie = null, ?string $token = null): array
    {
        $method = in_array($action, ['me', 'crowns', 'state'], true) ? 'GET' : 'POST';
        $headers = $token ? ['X-Game-Token: ' . $token] : [];
        return $this->http($method, "$game.php?action=$action", $method === 'POST' ? $body : null, $cookie, $headers);
    }

    /** A chess game against the computer where white (the player) mates with Qxf7 (scholar's mate). */
    private function winnableChess(string $level, ?string $cookie): array
    {
        [, , , $j] = $this->api('chess', 'new', ['mode' => 'computer', 'color' => 'white', 'difficulty' => $level], $cookie);
        $moves = array_map(fn($m) => [$m, 0], ['e2e4', 'e7e5', 'f1c4', 'b8c6', 'd1h5', 'g8f6']);
        $this->pdo->prepare('UPDATE hakoo_chess_games SET moves = ? WHERE id = ?')->execute([json_encode($moves), $j['game']]);
        return [$j['game'], $j['token'], $j['state']];
    }

    private function winChess(string $game, string $token): array
    {
        [, , , $m] = $this->api('chess', 'move', ['game' => $game, 'move' => 'h5f7'], null, $token);
        $this->assertSame(['finished', 'white', 'checkmate'], [$m['state']['status'], $m['state']['winner'], $m['state']['reason']]);
        return $m;
    }

    /**
     * Moves of a whole Othello game that black wins with the last move, found with the engine
     * (seeded, so the same game every run). Returns [moves before the last, the last move].
     */
    private function blackWinsOthello(): array
    {
        for ($seed = 1; ; $seed++) {
            $rng = new \Random\Randomizer(new \Random\Engine\Mt19937($seed));
            $g = new OthelloGame();
            $moves = [];
            $last = null;
            while (!$g->isOver()) {
                $legal = $g->legal();
                $last = $g->toMove();
                $moves[] = $legal[$rng->getInt(0, count($legal) - 1)];
                $g->play($last, ...end($moves));
            }
            if ($g->winner() === 'black' && $last === 'black') return [array_slice($moves, 0, -1), end($moves)];
        }
    }

    /**
     * Moves of a whole Othello game that black wins although white fills the last square (the computer's forced
     * answer). Returns [moves before black's last move, black's last move].
     */
    private function blackWinsOthelloOnWhitesMove(): array
    {
        for ($seed = 1; ; $seed++) {
            $rng = new \Random\Randomizer(new \Random\Engine\Mt19937($seed));
            $g = new OthelloGame();
            $moves = [];
            $who = [];
            while (!$g->isOver()) {
                $legal = $g->legal();
                $who[] = $g->toMove();
                $moves[] = $legal[$rng->getInt(0, count($legal) - 1)];
                $g->play(end($who), ...end($moves));
            }
            if ($g->winner() === 'black' && array_sum($g->discs()) === 64 && array_slice($who, -2) === ['black', 'white']) {
                return [array_slice($moves, 0, -2), $moves[count($moves) - 2]];
            }
        }
    }

    private function profile(string $game, string $id): ?array
    {
        $s = $this->pdo->prepare("SELECT * FROM hakoo_{$game}_profile WHERE discord_id = ?");
        $s->execute([$id]);
        return $s->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /** TC-API-102 | FR-CHESS-08: a logged-in member's first checkmate on a level unlocks its reward, once; anonymous wins unlock nothing */
    public function test_chess_first_win_unlocks_the_reward()
    {
        $carol = $this->sessionCookie(self::CAROL);
        [$game, $token, $state] = $this->winnableChess('easy', $carol);
        $this->assertTrue($state['rewardGame']);
        $this->assertSame('easy', $this->winChess($game, $token)['unlocked']);
        $this->assertNotNull($this->profile('chess', self::CAROL)['won_easy']);

        [$game, $token] = $this->winnableChess('easy', $carol);
        $this->assertNull($this->winChess($game, $token)['unlocked'], 'a second win on the same level unlocks nothing new');
        [$game, $token] = $this->winnableChess('hard', $carol);
        $this->assertSame('hard', $this->winChess($game, $token)['unlocked']);

        [$game, $token, $state] = $this->winnableChess('medium', null);
        $this->assertFalse($state['rewardGame']);
        $this->assertNull($this->winChess($game, $token)['unlocked']);
        $this->assertSame(3, (int)$this->pdo->query('SELECT COUNT(*) FROM hakoo_chess_profile')->fetchColumn(),
            'Alice and Bob (fixture) and Carol; nothing for the anonymous win');
    }

    /** TC-API-103 | FR-OTHELLO-07: winning an Othello game on the board unlocks the reward; a game lost by resigning does not */
    public function test_othello_win_unlocks_the_reward()
    {
        [$before, [$r, $c]] = $this->blackWinsOthello();
        $carol = $this->sessionCookie(self::CAROL);
        [, , , $j] = $this->api('othello', 'new', ['mode' => 'computer', 'color' => 'black', 'difficulty' => 'medium'], $carol);
        $this->pdo->prepare('UPDATE hakoo_othello_games SET moves = ? WHERE id = ?')->execute([json_encode($before), $j['game']]);
        [, , , $m] = $this->api('othello', 'move', ['game' => $j['game'], 'row' => $r, 'col' => $c], null, $j['token']);
        $this->assertSame(['finished', 'black', 'discs'], [$m['state']['status'], $m['state']['winner'], $m['state']['reason']]);
        $this->assertSame('medium', $m['unlocked']);
        $this->assertNotNull($this->profile('othello', self::CAROL)['won_medium']);

        [, , , $j] = $this->api('othello', 'new', ['mode' => 'computer', 'color' => 'black', 'difficulty' => 'easy'], $carol);
        [, , , $x] = $this->api('othello', 'resign', ['game' => $j['game']], null, $j['token']);
        $this->assertSame('white', $x['state']['winner']);
        $this->assertNull($this->profile('othello', self::CAROL)['won_easy']);
    }

    /** TC-API-103 | FR-OTHELLO-07: a game that ends on the computer's move with the player ahead unlocks the reward too */
    public function test_othello_win_on_the_computers_last_disc_unlocks_the_reward()
    {
        [$before, [$r, $c]] = $this->blackWinsOthelloOnWhitesMove();
        $carol = $this->sessionCookie(self::CAROL);
        [, , , $j] = $this->api('othello', 'new', ['mode' => 'computer', 'color' => 'black', 'difficulty' => 'easy'], $carol);
        $this->pdo->prepare('UPDATE hakoo_othello_games SET moves = ? WHERE id = ?')->execute([json_encode($before), $j['game']]);
        [, , , $m] = $this->api('othello', 'move', ['game' => $j['game'], 'row' => $r, 'col' => $c], null, $j['token']);
        $this->assertSame(['finished', 'black', 'discs'], [$m['state']['status'], $m['state']['winner'], $m['state']['reason']]);
        $this->assertSame(count($before) + 2, count($m['state']['moves']), 'the computer put the last disc');
        $this->assertSame('easy', $m['unlocked']);
        $this->assertNotNull($this->profile('othello', self::CAROL)['won_easy']);
        $this->assertSame(1, $this->rows('hakoo_saved_games', "discord_id = ? AND reason = 'first_win' AND game = 'othello'", [self::CAROL]),
            'the game that unlocked it is saved');
    }

    /** TC-API-104 | FR-CHESS-09, FR-OTHELLO-08: "me" reports unlocks and the look; locked parts fall back to the default */
    public function test_me_reports_unlocks_and_look()
    {
        [, , , $out] = $this->api('chess', 'me');
        $this->assertSame(['status' => 'ok', 'loggedIn' => false], $out);

        [, , , $a] = $this->api('chess', 'me', [], $this->sessionCookie(self::ALICE));
        $this->assertSame(['easy' => true, 'medium' => true, 'hard' => true], $a['unlocked']);
        $this->assertSame(['pieceWhite' => 'gold', 'pieceBlack' => 'purple', 'pieces3d' => true, 'board' => 'hakoo', 'showCrown' => true], $a['look']);
        [, , , $a] = $this->api('othello', 'me', [], $this->sessionCookie(self::ALICE));
        $this->assertSame(['discBlack' => 'teal', 'discWhite' => 'gold', 'discs3d' => true, 'board' => 'ocean', 'showCrown' => true], $a['look']);

        $this->pdo->exec('UPDATE hakoo_othello_profile SET discs_3d = 1 WHERE discord_id = ' . self::BOB);   // not unlocked: ignored
        [, , , $b] = $this->api('othello', 'me', [], $this->sessionCookie(self::BOB));
        $this->assertSame(['easy' => true, 'medium' => false, 'hard' => false], $b['unlocked']);
        $this->assertFalse($b['look']['discs3d']);
        $this->assertFalse($b['look']['showCrown']);
    }

    /** TC-API-105 | FR-CHESS-09, FR-OTHELLO-08: only unlocked parts of the look can be changed; the board is free */
    public function test_look_respects_unlocks()
    {
        $carol = $this->sessionCookie(self::CAROL);
        $base = ['pieceWhite' => 'white', 'pieceBlack' => 'black', 'pieces3d' => false, 'board' => 'green', 'showCrown' => true];
        [$status, , , $r] = $this->api('chess', 'look', $base, $carol);
        $this->assertSame(200, $status);
        $this->assertSame('green', $r['look']['board']);
        [$status, , , $e] = $this->api('chess', 'look', ['pieceWhite' => 'red'] + $base, $carol);
        $this->assertSame([403, 'locked'], [$status, $e['error']]);
        [$status, , , $e] = $this->api('chess', 'look', ['pieces3d' => true] + $base, $carol);
        $this->assertSame([403, 'locked'], [$status, $e['error']]);

        $bob = $this->sessionCookie(self::BOB);
        [$status, , , $r] = $this->api('chess', 'look', ['pieceWhite' => 'gold', 'pieceBlack' => 'teal'] + $base, $bob);
        $this->assertSame(200, $status);
        $this->assertSame(['gold', 'teal'], [$r['look']['pieceWhite'], $r['look']['pieceBlack']]);
        [$status, , , $e] = $this->api('chess', 'look', ['pieceWhite' => 'red', 'pieceBlack' => 'red'] + $base, $bob);
        $this->assertSame([400, 'same_colors'], [$status, $e['error']]);
        [$status, , , $e] = $this->api('chess', 'look', ['board' => 'felt'] + $base, $bob);
        $this->assertSame([400, 'bad_look'], [$status, $e['error']], 'felt is an Othello board, not a chess board');

        $othello = ['discBlack' => 'black', 'discWhite' => 'white', 'discs3d' => false, 'board' => 'wood', 'showCrown' => true];
        [$status, , , $r] = $this->api('othello', 'look', $othello, $carol);
        $this->assertSame([200, 'wood'], [$status, $r['look']['board']]);
        [$status, , , $e] = $this->api('othello', 'look', $othello);
        $this->assertSame([401, 'not_logged_in'], [$status, $e['error']]);
    }

    /** TC-API-106 | FR-GAMES-07: the crown list names every game won on Hard; hiding one game's crown only removes that game */
    public function test_crowns_over_all_games()
    {
        [, $headers, , $c] = $this->http('GET', 'crowns.php');
        $this->assertSame([self::ALICE], $c['ids']);
        $this->assertSame(['gomoku', 'chess', 'othello', 'hnefatafl', 'stratego', 'backgammon', 'checkers', 'morris', 'nonogram', 'sudoku', 'owldoku'], $c['games'][self::ALICE]);
        $this->assertEmpty(array_filter($headers, fn($h) => stripos($h, 'Set-Cookie:') === 0));

        $alice = $this->sessionCookie(self::ALICE);
        $this->api('chess', 'look', ['pieceWhite' => 'gold', 'pieceBlack' => 'purple', 'pieces3d' => true, 'board' => 'hakoo', 'showCrown' => false], $alice);
        [, , , $c] = $this->http('GET', 'crowns.php');
        $this->assertSame(['gomoku', 'othello', 'hnefatafl', 'stratego', 'backgammon', 'checkers', 'morris', 'nonogram', 'sudoku', 'owldoku'], $c['games'][self::ALICE]);
        [, , , $one] = $this->api('chess', 'crowns');
        $this->assertSame([], $one['ids']);

        $this->pdo->exec('UPDATE hakoo_gomoku_profile SET show_crown = 0');
        $this->pdo->exec('UPDATE hakoo_othello_profile SET show_crown = 0');
        $this->pdo->exec('UPDATE hakoo_hnefatafl_profile SET show_crown = 0');
        $this->pdo->exec('UPDATE hakoo_stratego_profile SET show_crown = 0');
        $this->pdo->exec('UPDATE hakoo_backgammon_profile SET show_crown = 0');
        $this->pdo->exec('UPDATE hakoo_checkers_profile SET show_crown = 0');
        $this->pdo->exec('UPDATE hakoo_morris_profile SET show_crown = 0');
        $this->pdo->exec('UPDATE hakoo_nonogram_profile SET show_crown = 0');
        $this->pdo->exec('UPDATE hakoo_sudoku_profile SET show_crown = 0');
        $this->pdo->exec('UPDATE hakoo_owldoku_profile SET show_crown = 0');
        [, , , $c] = $this->http('GET', 'crowns.php');
        $this->assertSame([], $c['ids'], 'all crowns hidden: no crown');
    }

    /** TC-API-107 | FR-CHESS-10, FR-OTHELLO-09: deleting a member removes their rewards and their computer games */
    public function test_delete_removes_rewards_and_games()
    {
        $dave = '100000000000000078';                               // a member only this test uses
        [$game, $token] = $this->winnableChess('easy', $this->sessionCookie($dave));
        $this->winChess($game, $token);
        [, , , $o] = $this->api('othello', 'new', ['mode' => 'computer', 'color' => 'black'], $this->sessionCookie($dave));
        $this->api('othello', 'look', ['discBlack' => 'black', 'discWhite' => 'white', 'discs3d' => false, 'board' => 'ice', 'showCrown' => true], $this->sessionCookie($dave));
        $this->assertNotNull($this->profile('chess', $dave));
        $this->assertNotNull($this->profile('othello', $dave));

        $export = hakoo_collect_user($this->pdo, $dave);
        $this->assertCount(1, $export['tables']['hakoo_chess_games']);
        $this->assertCount(1, $export['tables']['hakoo_othello_games']);

        hakoo_delete_user($this->pdo, $dave);
        $this->assertNull($this->profile('chess', $dave));
        $this->assertNull($this->profile('othello', $dave));
        $this->assertSame(0, $this->rows('hakoo_chess_games', 'id = ?', [$game]));
        $this->assertSame(0, $this->rows('hakoo_othello_games', 'id = ?', [$o['game']]));
    }
}
