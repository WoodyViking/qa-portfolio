<?php
/**
 * System tests of saved games (board-game-api.inc.php: first wins, games between members,
 * action=saved; saved-games.php: list, star, delete; hakoo-users.inc.php: export and delete).
 * Fixture: Alice kept her first Gomoku win on Hard (id 1) and a chess game against Bob (id 2,
 * starred); Bob has his copy of that chess game (id 3).
 * Traceability: FR-SAVED-01..07, US-26.
 */
class SavedGamesTest extends HakooTestCase
{
    private function game(string $game, string $action, array $body = [], ?string $cookie = null, ?string $token = null, string $query = ''): array
    {
        $method = in_array($action, ['state', 'saved'], true) ? 'GET' : 'POST';
        $headers = $token ? [($game === 'gomoku' ? 'X-Gomoku-Token: ' : 'X-Game-Token: ') . $token] : [];
        return $this->http($method, "$game.php?action=$action$query", $method === 'POST' ? $body : null, $cookie, $headers);
    }

    private function saved(string $action, array $body = [], ?string $cookie = null): array
    {
        return $this->http($action === 'list' ? 'GET' : 'POST', "saved-games.php?action=$action", $action === 'list' ? null : $body, $cookie);
    }

    private function copies(string $where, array $params = []): array
    {
        $s = $this->pdo->prepare("SELECT * FROM hakoo_saved_games WHERE $where ORDER BY id");
        $s->execute($params);
        return $s->fetchAll(PDO::FETCH_ASSOC);
    }

    /** A friend game of chess: [game, white token, black token]; each player logged in or not. */
    private function friendChess(?string $whiteCookie, ?string $blackCookie): array
    {
        [, , , $a] = $this->game('chess', 'new', ['mode' => 'friend', 'color' => 'white', 'name' => 'Wendy'], $whiteCookie);
        [, , , $b] = $this->game('chess', 'join', ['game' => $a['game'], 'invite' => $a['invite'], 'name' => 'Bert'], $blackCookie);
        return [$a['game'], $a['token'], $b['token']];
    }

    private function foolsMate(string $game, string $white, string $black): array
    {
        foreach ([[$white, 'f2f3'], [$black, 'e7e5'], [$white, 'g2g4']] as [$t, $m]) $this->game('chess', 'move', ['game' => $game, 'move' => $m], null, $t);
        [, , , $r] = $this->game('chess', 'move', ['game' => $game, 'move' => 'd8h4'], null, $black);
        return $r;
    }

    /** TC-API-130 | FR-SAVED-01: the game that first beats the computer on a level is saved as a first win; later ones as games against the computer */
    public function test_first_win_is_saved()
    {
        $carol = $this->sessionCookie(self::CAROL);
        $win = function () use ($carol) {
            [, , , $j] = $this->game('gomoku', 'new', ['mode' => 'computer', 'color' => 'black', 'difficulty' => 'easy'], $carol);
            $moves = [];
            for ($k = 0; $k < 4; $k++) { $moves[] = [7, $k, 0]; $moves[] = [0, $k * 2, 0]; }
            $this->pdo->prepare('UPDATE hakoo_gomoku_games SET moves = ? WHERE id = ?')->execute([json_encode($moves), $j['game']]);
            [, , , $m] = $this->game('gomoku', 'move', ['game' => $j['game'], 'row' => 7, 'col' => 4], null, $j['token']);
            return [$j['game'], $m];
        };
        [$game, $m] = $win();
        $this->assertSame('easy', $m['unlocked']);
        $c = $this->copies('discord_id = ?', [self::CAROL]);
        $this->assertCount(1, $c);
        $this->assertSame(['gomoku', $game, 'black', 'first_win', 'easy'], [$c[0]['game'], $c[0]['source_id'], $c[0]['seat'], $c[0]['reason'], $c[0]['level']]);
        $snap = json_decode($c[0]['snapshot'], true);
        $this->assertSame('five', $snap['end_reason']);
        $this->assertArrayNotHasKey('black_token', $snap, 'no tokens in a saved game');
        $this->assertArrayNotHasKey('discord_id', $snap);

        [$second] = $win();
        $c = $this->copies('discord_id = ?', [self::CAROL]);
        $this->assertSame(['first_win', 'computer'], array_column($c, 'reason'), 'a second win on Easy is an ordinary game against the computer');
        $this->assertSame([$second, 'easy'], [$c[1]['source_id'], $c[1]['level']]);
    }

    /** TC-API-286 | FR-SAVED-08: a member's game against the computer is saved when it ends with a move; not without one, not for guests */
    public function test_games_against_the_computer_are_saved()
    {
        $carol = $this->sessionCookie(self::CAROL);
        $play = function (?string $cookie, bool $move) {
            [, , , $j] = $this->game('gomoku', 'new', ['mode' => 'computer', 'color' => 'black', 'difficulty' => 'medium'], $cookie);
            if ($move) $this->game('gomoku', 'move', ['game' => $j['game'], 'row' => 7, 'col' => 7], null, $j['token']);
            [, , , $r] = $this->game('gomoku', 'resign', ['game' => $j['game']], null, $j['token']);
            $this->assertSame('finished', $r['state']['status']);
            return $j['game'];
        };
        $game = $play($carol, true);
        $c = $this->copies('source_id = ?', [$game]);
        $this->assertCount(1, $c);
        $this->assertSame([self::CAROL, 'black', 'computer', 'medium'], [(string)$c[0]['discord_id'], $c[0]['seat'], $c[0]['reason'], $c[0]['level']]);
        $snap = json_decode($c[0]['snapshot'], true);
        $this->assertSame(['resign', 'white'], [$snap['end_reason'], $snap['winner']]);
        $this->assertArrayNotHasKey('discord_id', $snap);

        [, , , $l] = $this->saved('list', [], $carol);
        $this->assertSame(['gomoku', 'computer', 'medium', 'lost'], [$l['games'][0]['game'], $l['games'][0]['reason'], $l['games'][0]['level'], $l['games'][0]['result']]);

        $this->assertSame([], $this->copies('source_id = ?', [$play($carol, false)]), 'no move: nothing kept');
        $this->assertSame([], $this->copies('source_id = ?', [$play(null, true)]), 'a guest: nothing kept');
    }

    /** TC-API-131 | FR-SAVED-02: a game between two logged-in members is saved for both; with one guest it is not */
    public function test_game_between_members_is_saved_for_both()
    {
        $carol = $this->sessionCookie(self::CAROL);
        $dave = '100000000000000079';
        [$game, $w, $b] = $this->friendChess($carol, $this->sessionCookie($dave));
        $this->assertCount(2, $this->copies('source_id = ? AND snapshot IS NULL', [$game]), 'while it is played: who sits where');
        $this->foolsMate($game, $w, $b);
        $c = $this->copies('source_id = ?', [$game]);
        $this->assertCount(2, $c);
        $this->assertSame([self::CAROL, 'white', $dave], [(string)$c[0]['discord_id'], $c[0]['seat'], (string)$c[0]['opponent_discord_id']]);
        $this->assertSame([$dave, 'black', self::CAROL], [(string)$c[1]['discord_id'], $c[1]['seat'], (string)$c[1]['opponent_discord_id']]);
        $this->assertSame('checkmate', json_decode($c[0]['snapshot'], true)['end_reason']);

        [$game, $w, $b] = $this->friendChess($carol, null);            // a guest plays black
        $this->foolsMate($game, $w, $b);
        $this->assertSame([], $this->copies('source_id = ?', [$game]), 'not two members: nothing kept');
    }

    /** TC-API-132 | FR-SAVED-03: a member watches only their own saved games, and can look back through them */
    public function test_watch_a_saved_game()
    {
        $alice = $this->sessionCookie(self::ALICE);
        [$status, , , $j] = $this->game('chess', 'saved', [], $alice, null, '&id=2');
        $this->assertSame(200, $status);
        $this->assertSame(['finished', 'white', 'checkmate', 'white'], [$j['state']['status'], $j['state']['you'], $j['state']['reason'], $j['state']['winner']]);
        $this->assertSame(['black' => 'Bob', 'white' => 'Alice'], $j['state']['names']);
        $this->assertCount(7, $j['state']['moves']);
        $this->assertSame(['id' => 2, 'reason' => 'friend', 'level' => null, 'starred' => true], array_intersect_key($j['saved'], array_flip(['id', 'reason', 'level', 'starred'])));

        [, , , $k] = $this->game('chess', 'saved', [], $alice, null, '&id=2&ply=2');
        $this->assertSame(2, $k['state']['ply']);
        $this->assertSame(['e2e4', 'e7e5'], $k['state']['moves']);

        [, , , $g] = $this->game('gomoku', 'saved', [], $alice, null, '&id=1');
        $this->assertCount(5, $g['state']['winLine']);

        foreach ([[$this->sessionCookie(self::BOB), '&id=2', 404], [$alice, '&id=3', 404], [null, '&id=2', 401]] as [$cookie, $q, $code]) {
            [$status] = $this->game('chess', 'saved', [], $cookie, null, $q);
            $this->assertSame($code, $status, "someone else's copy, or not logged in ($q)");
        }
        [$status] = $this->game('gomoku', 'saved', [], $alice, null, '&id=2');
        $this->assertSame(404, $status, 'a chess game is not a Gomoku game');
    }

    /** TC-API-133 | FR-SAVED-04: the list on the profile; star and delete only one's own copies */
    public function test_list_star_and_delete()
    {
        $alice = $this->sessionCookie(self::ALICE);
        [, , , $l] = $this->saved('list', [], $alice);
        $this->assertSame([2, 1], array_column($l['games'], 'id'), 'newest first');
        $this->assertSame(['chess', 'friend', 'Bob', 'won', true], [$l['games'][0]['game'], $l['games'][0]['reason'], $l['games'][0]['opponent'], $l['games'][0]['result'], $l['games'][0]['starred']]);
        $this->assertSame(['gomoku', 'first_win', 'hard', 15, 9], [$l['games'][1]['game'], $l['games'][1]['reason'], $l['games'][1]['level'], $l['games'][1]['size'], $l['games'][1]['moves']]);

        $this->saved('star', ['id' => 2, 'starred' => false], $alice);
        $this->assertSame('0', (string)$this->copies('id = 2')[0]['starred']);
        [$status] = $this->saved('delete', ['id' => 3], $alice);
        $this->assertSame(404, $status, "Bob's copy is not Alice's to delete");
        [$status] = $this->saved('delete', ['id' => 2], $alice);
        $this->assertSame(200, $status);
        $this->assertSame([], $this->copies('id = 2'));
        $this->assertCount(1, $this->copies('id = 3'), 'Bob keeps his copy');

        [$status, , , $e] = $this->saved('list');
        $this->assertSame([401, 'not_logged_in'], [$status, $e['error']]);
    }

    /** TC-API-134 | FR-SAVED-05: the latest 100 games against members are kept, plus starred ones and first wins */
    public function test_only_the_latest_hundred_are_kept()
    {
        $carol = $this->sessionCookie(self::CAROL);
        hakoo_ensure_user($this->pdo, self::CAROL);
        $ins = $this->pdo->prepare("INSERT INTO hakoo_saved_games (discord_id, game, source_id, seat, reason, snapshot, starred, saved_at)
                                    VALUES (?, 'chess', ?, 'white', 'friend', '{}', ?, ?)");
        for ($k = 0; $k <= 100; $k++) $ins->execute([self::CAROL, sprintf('%016x', $k + 1), $k === 0 ? 1 : 0, date('Y-m-d H:i:s', strtotime('2026-01-01') + $k * 60)]);
        $this->pdo->prepare("INSERT INTO hakoo_saved_games (discord_id, game, source_id, seat, reason, level, snapshot, saved_at)
                             VALUES (?, 'gomoku', 'ffff000000000001', 'black', 'first_win', 'easy', '{}', '2020-01-01 00:00:00')")->execute([self::CAROL]);

        [$game, $w, $b] = $this->friendChess($carol, $this->sessionCookie('100000000000000079'));
        $this->foolsMate($game, $w, $b);

        $this->assertSame(100, count($this->copies("discord_id = ? AND reason = 'friend' AND starred = 0", [self::CAROL])));
        $this->assertCount(1, $this->copies("discord_id = ? AND starred = 1", [self::CAROL]), 'the starred oldest game stays');
        $this->assertCount(0, $this->copies('source_id = ?', ['0000000000000002']), 'the oldest unstarred one made room');
        $this->assertCount(1, $this->copies("discord_id = ? AND reason = 'first_win'", [self::CAROL]), 'first wins are always kept');
        $this->assertCount(1, $this->copies('source_id = ? AND discord_id = ?', [$game, self::CAROL]));
    }

    /** TC-API-287 | FR-SAVED-05: the latest 100 games against the computer are kept, apart from the games against members */
    public function test_the_latest_hundred_against_the_computer_are_kept()
    {
        $carol = $this->sessionCookie(self::CAROL);
        hakoo_ensure_user($this->pdo, self::CAROL);
        $ins = $this->pdo->prepare("INSERT INTO hakoo_saved_games (discord_id, game, source_id, seat, reason, level, snapshot, starred, saved_at)
                                    VALUES (?, 'gomoku', ?, 'black', ?, 'easy', '{}', ?, ?)");
        for ($k = 0; $k <= 100; $k++) $ins->execute([self::CAROL, sprintf('%016x', $k + 1), 'computer', $k === 0 ? 1 : 0, date('Y-m-d H:i:s', strtotime('2026-01-01') + $k * 60)]);
        $ins->execute([self::CAROL, 'eeee000000000001', 'friend', 0, '2020-01-01 00:00:00']);

        [, , , $j] = $this->game('gomoku', 'new', ['mode' => 'computer', 'color' => 'black', 'difficulty' => 'easy'], $carol);
        $this->game('gomoku', 'move', ['game' => $j['game'], 'row' => 7, 'col' => 7], null, $j['token']);
        $this->game('gomoku', 'resign', ['game' => $j['game']], null, $j['token']);

        $this->assertSame(100, count($this->copies("discord_id = ? AND reason = 'computer' AND starred = 0", [self::CAROL])));
        $this->assertCount(1, $this->copies("discord_id = ? AND starred = 1", [self::CAROL]), 'the starred oldest game stays');
        $this->assertCount(0, $this->copies('source_id = ?', ['0000000000000002']), 'the oldest unstarred one made room');
        $this->assertCount(1, $this->copies("discord_id = ? AND reason = 'friend'", [self::CAROL]), 'games against members are counted apart');
        $this->assertCount(1, $this->copies('source_id = ?', [$j['game']]));
    }

    /** TC-API-135 | FR-SAVED-06: "Delete all my data" removes a member's copies; the other player's copy says "Deleted player" */
    public function test_delete_member_anonymises_the_other_copy()
    {
        $export = hakoo_collect_user($this->pdo, self::ALICE);
        $this->assertCount(2, $export['tables']['hakoo_saved_games']);
        $this->assertArrayNotHasKey('opponent_discord_id', $export['tables']['hakoo_saved_games'][0], "Bob's id is not in Alice's export");

        hakoo_delete_user($this->pdo, self::ALICE);
        $this->assertSame([], $this->copies('discord_id = ?', [self::ALICE]));
        $bob = $this->copies('id = 3')[0];
        $this->assertNull($bob['opponent_discord_id']);
        $snap = json_decode($bob['snapshot'], true);
        $this->assertSame(['Deleted player', 'Bob'], [$snap['white_name'], $snap['black_name']]);

        [, , , $j] = $this->game('chess', 'saved', [], $this->sessionCookie(self::BOB), null, '&id=3');
        $this->assertSame('Deleted player', $j['state']['names']['white']);
    }

    /** TC-API-136 | FR-SAVED-07: a Fog of War game between members is saved and shown whole (the fog has lifted) */
    public function test_fog_game_is_saved_whole()
    {
        $carol = $this->sessionCookie(self::CAROL);
        [, , , $a] = $this->game('chess', 'new', ['mode' => 'friend', 'color' => 'white', 'variant' => 'fog'], $carol);
        [, , , $b] = $this->game('chess', 'join', ['game' => $a['game'], 'invite' => $a['invite']], $this->sessionCookie('100000000000000079'));
        $this->game('chess', 'move', ['game' => $a['game'], 'move' => 'e2e4'], null, $a['token']);
        $this->game('chess', 'resign', ['game' => $a['game']], null, $b['token']);
        $id = (int)$this->copies('source_id = ? AND discord_id = ?', [$a['game'], '100000000000000079'])[0]['id'];
        [, , , $j] = $this->game('chess', 'saved', [], $this->sessionCookie('100000000000000079'), null, "&id=$id");
        $this->assertSame(['e2e4'], $j['state']['moves'], 'black sees white\'s move now');
        $this->assertSame('rnbqkbnr', $j['state']['board'][0]);
    }
}
