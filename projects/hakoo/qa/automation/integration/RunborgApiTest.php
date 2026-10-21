<?php
/**
 * System tests of the Runborg API (src/runborg.php) over HTTP: a game on the server, the hidden cards,
 * a member's wins per board and opponent, and the titles (also in the login corner and on the puzzle lists).
 * Traceability: FR-RUNBORG-05..09, REG-GDPR-15, REG-GDPR-17.
 */
class RunborgApiTest extends HakooTestCase
{
    private function api(string $action, ?array $body = null, ?string $token = null, ?string $cookie = null, string $query = ''): array
    {
        return $this->http($body === null ? 'GET' : 'POST', "runborg.php?action=$action$query", $body, $cookie, $token ? ['X-Game-Token: ' . $token] : []);
    }
    private function newGame(string $board = 'fjord', string $level = 'farmer', ?string $cookie = null): array
    {
        [$status, , , $j] = $this->api('new', ['board' => $board, 'level' => $level], null, $cookie);
        $this->assertSame(200, $status, json_encode($j));
        return $j;
    }
    /** Put the game one card from a win for the player: the first card in hand is Carve a Rune (+2 hall), the hall at goal − 2. */
    private function nearWin(string $game, int $goal): void
    {
        $s = json_decode($this->pdo->query("SELECT state FROM hakoo_runborg_games WHERE id = '$game'")->fetchColumn(), true);
        $s['me']['hand'][0] = 12;                                    // Carve a Rune: 1 rune, +2 hall
        $s['me']['hall'] = $goal - 2;
        $s['me']['r'] = 5;
        $this->pdo->prepare('UPDATE hakoo_runborg_games SET state = ? WHERE id = ?')->execute([json_encode($s), $game]);
    }

    /** TC-API-253 | FR-RUNBORG-05: a game needs no login; the page gets its own hand, never the opponent's or the deck */
    public function test_new_game_hides_the_hidden_cards()
    {
        $j = $this->newGame('aurora', 'jarl');
        $st = $j['state'];
        $this->assertSame(['aurora', 'jarl', 'me', null], [$st['board'], $st['level'], $st['turn'], $st['over']]);
        $this->assertCount(6, $st['me']['hand']);
        $this->assertSame(6, $st['them']['cards']);
        $this->assertArrayNotHasKey('hand', $st['them']);
        $this->assertSame(60, $st['deck']);                           // a number, not the cards
        $this->assertSame([4, 9, 2], [$st['them']['stone'], $st['them']['w'], $st['them']['mead']]);   // the Jarl's estate on the Northern Lights
        [$status, , , $e] = $this->api('new', ['board' => 'mars', 'level' => 'farmer']);
        $this->assertSame([409, 'bad_board'], [$status, $e['error']]);
        [$status, , , $e] = $this->api('new', ['board' => 'fjord', 'level' => 'emperor']);
        $this->assertSame([409, 'bad_level'], [$status, $e['error']]);
    }

    /** TC-API-254 | FR-RUNBORG-05: only the token's holder plays; a card is checked; the computer answers in the same request, step by step */
    public function test_play_with_the_token_and_the_computer_answers()
    {
        $j = $this->newGame();
        [$status] = $this->api('state', null, 'wrong', null, '&game=' . $j['game']);
        $this->assertSame(403, $status);
        [$status, , , $e] = $this->api('play', ['game' => $j['game'], 'i' => 9], $j['token']);
        $this->assertSame([409, 'bad_card'], [$status, $e['error']]);
        [$status, , , $p] = $this->api('play', ['game' => $j['game'], 'i' => 0, 'discard' => true], $j['token']);
        $this->assertSame(200, $status);
        $kinds = array_map(fn($e) => $e['kind'] . ':' . $e['who'], $p['events']);
        $this->assertSame(['card:me', 'produce:them'], array_slice($kinds, 0, 2));
        $this->assertSame('produce:me', end($kinds));                // back to the player, whose generators gave
        foreach ($p['events'] as $e) {
            $this->assertArrayNotHasKey('hand', $e['before']['them']);
            if ($e['kind'] === 'card' && $e['who'] === 'them') $this->assertIsInt($e['id']);   // the card the computer played is shown
        }
        $this->assertSame('me', $p['state']['turn']);
        [, , , $s] = $this->api('state', null, $j['token'], null, '&game=' . $j['game']);
        $this->assertSame($p['state'], $s['state']);
    }

    /** TC-API-255 | FR-RUNBORG-06, FR-RUNBORG-07: a member's win is kept per board and opponent and is a title; a guest's win keeps nothing */
    public function test_a_members_win_is_a_title()
    {
        $alice = $this->sessionCookie(self::ALICE, 'Alice');
        $j = $this->newGame('fjord', 'jarl', $alice);
        $this->nearWin($j['game'], 50);
        [, , , $p] = $this->api('play', ['game' => $j['game'], 'i' => 0], $j['token']);
        $this->assertSame(['me', 'hall:50'], [$p['state']['over'], $p['state']['why']]);
        $this->assertSame(['title' => 'jarl', 'first' => true, 'boardFirst' => true], $p['won']);
        [, , , $me] = $this->api('me', null, null, $alice);
        $this->assertSame(['jarl'], $me['titles']);
        $this->assertArrayHasKey('jarl', $me['wins']['fjord']);
        $this->assertSame('', $me['title']);
        // Again on another board: the same title, not new; the second board is new.
        $j = $this->newGame('dusk', 'jarl', $alice);
        $this->nearWin($j['game'], 60);
        [, , , $p] = $this->api('play', ['game' => $j['game'], 'i' => 0], $j['token']);
        $this->assertSame(['title' => 'jarl', 'first' => false, 'boardFirst' => true], $p['won']);
        // A guest: nothing kept.
        $g = $this->newGame('fjord', 'king');
        $this->nearWin($g['game'], 50);
        [, , , $p] = $this->api('play', ['game' => $g['game'], 'i' => 0], $g['token']);
        $this->assertSame(['me', null], [$p['state']['over'], $p['won']]);
        $this->assertSame(2, $this->rows('hakoo_runborg_wins', 'discord_id = ?', [self::ALICE]));   // (Bob's are in the test data)
    }

    /** TC-API-256 | FR-RUNBORG-08: only a title won can be shown; it shows in the login corner and on the puzzle-of-the-day list */
    public function test_the_title_by_the_name()
    {
        $alice = $this->sessionCookie(self::ALICE, 'Alice');
        [$status, , , $e] = $this->api('title', ['title' => 'king'], null, $alice);
        $this->assertSame([403, 'not_won'], [$status, $e['error']]);
        $this->assertSame(401, $this->api('title', ['title' => ''])[0]);
        $j = $this->newGame('winter', 'viking', $alice);
        $s = json_decode($this->pdo->query("SELECT state FROM hakoo_runborg_games WHERE id = '{$j['game']}'")->fetchColumn(), true);
        $s['me']['hand'][0] = 0; $s['me']['hall'] = 40; $s['me']['pal'] = 18; $s['me']['t'] = 5;   // Split Logs: +3 palisade wins Winter
        $this->pdo->prepare('UPDATE hakoo_runborg_games SET state = ? WHERE id = ?')->execute([json_encode($s), $j['game']]);
        $this->api('play', ['game' => $j['game'], 'i' => 0], $j['token']);
        [$status, , , $t] = $this->api('title', ['title' => 'viking'], null, $alice);
        $this->assertSame([200, 'viking'], [$status, $t['title']]);

        [, , , $who] = $this->http('GET', 'discord-auth.php?action=me', null, $alice);
        $this->assertSame('Viking', $who['user']['title']);
        $this->http('POST', 'nonogram.php?action=daily', ['level' => 'easy'], $alice);
        $this->pdo->exec("UPDATE hakoo_nonogram_daily SET seconds = 42, solved_at = NOW() WHERE discord_id = " . self::ALICE);
        [, , , $b] = $this->http('GET', 'nonogram.php?action=board&level=easy', null, $alice);
        $this->assertSame('Viking', $b['board'][0]['title']);
        $this->api('title', ['title' => ''], null, $alice);
        [, , , $who] = $this->http('GET', 'discord-auth.php?action=me', null, $alice);
        $this->assertSame('', $who['user']['title']);
    }

    /** TC-API-257 | FR-RUNBORG-09, REG-GDPR-15, REG-GDPR-17: games, wins and the title are in the member's data and go when they delete it */
    public function test_export_and_delete()
    {
        $alice = $this->sessionCookie(self::ALICE, 'Alice');
        $j = $this->newGame('fjord', 'farmer', $alice);
        $this->nearWin($j['game'], 50);
        $this->api('play', ['game' => $j['game'], 'i' => 0], $j['token']);
        $this->api('title', ['title' => 'farmer'], null, $alice);
        [, , , $x] = $this->http('GET', 'mydata.php?action=export', null, $alice);
        $t = $x['games_and_settings'];
        $this->assertSame([1, 'farmer', 'farmer'], [count($t['hakoo_runborg_games']), $t['hakoo_runborg_wins'][0]['level'], $t['hakoo_runborg_profile'][0]['title']]);
        $this->assertArrayNotHasKey('state', $t['hakoo_runborg_games'][0]);   // the moves stay on the server
        $this->http('POST', 'mydata.php?action=delete', ['confirm' => 'DELETE'], $alice);
        foreach (['hakoo_runborg_games', 'hakoo_runborg_wins', 'hakoo_runborg_profile'] as $table) {
            $this->assertSame(0, $this->rows($table, 'discord_id = ?', [self::ALICE]), $table);
        }
    }
}
