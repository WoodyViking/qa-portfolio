<?php
/**
 * System tests of Blockdoku (src/blockdoku.php, blockdoku-day.inc.php): games for guests and members, moves that only
 * the token can make, today's high scores with names only for members, the day's first three and their viking
 * colours (hakoo-avatar.inc.php), Blockdoku in the reminders (reminders.php), and the member's data.
 * Traceability: FR-BLOCKDOKU-02..08.
 */
require_once '/var/www/html/blockdoku-engine.inc.php';

class BlockdokuApiTest extends HakooTestCase
{
    private function api(string $action, ?array $body = null, ?string $cookie = null, ?string $token = null, string $query = ''): array
    {
        $headers = $token ? ['X-Game-Token: ' . $token] : [];
        return $this->http($body === null ? 'GET' : 'POST', "blockdoku.php?action=$action$query", $body, $cookie, $headers);
    }

    /** A new game; its board and hand set as given (the test decides the blocks). */
    private function game(?string $cookie, string $board = null, array $hand = ['O', 'O', 'O']): array
    {
        [, , , $j] = $this->api('new', [], $cookie);
        $this->pdo->prepare('UPDATE hakoo_blockdoku_games SET board = ?, hand = ? WHERE id = ?')
            ->execute([$board ?? Blockdoku::emptyBoard(), json_encode($hand), $j['game']]);
        return [$j['game'], $j['token']];
    }

    /** A game with a score, on a day (today unless given). */
    private function scored(?string $member, int $score, string $day = null, string $name = ''): void
    {
        $this->pdo->prepare("INSERT INTO hakoo_blockdoku_games (id, token, discord_id, name, day, board, hand, score, moves, scored_at)
                             VALUES (?, ?, ?, ?, ?, ?, '[]', ?, 5, NOW())")
            ->execute([bin2hex(random_bytes(8)), str_repeat('0', 64), $member, $name, $day ?? $this->today(), Blockdoku::emptyBoard(), $score]);
    }
    private function today(): string { return (new DateTime('now', new DateTimeZone('Europe/Stockholm')))->format('Y-m-d'); }

    /** TC-API-295 | FR-BLOCKDOKU-02, FR-BLOCKDOKU-05: a guest's game: moves need its token, the server checks them and deals new blocks */
    public function test_a_game_and_its_moves()
    {
        [$status, , , $j] = $this->api('new', []);
        $this->assertSame(200, $status);
        $this->assertSame(Blockdoku::emptyBoard(), $j['state']['board']);
        $this->assertCount(3, $j['state']['hand']);
        $this->assertSame(1, $this->rows('hakoo_blockdoku_games', 'id = ? AND discord_id IS NULL', [$j['game']]));

        $row0 = str_repeat('1', 7) . '00' . str_repeat('0', 72);         // row 0 full but its last two squares
        [$game, $token] = $this->game(null, $row0, ['O', 'I2', 'T1']);
        [$status, , , $e] = $this->api('move', ['game' => $game, 'piece' => 0, 'r' => 0, 'c' => 7], null, 'wrong-token');
        $this->assertSame([403, 'bad_token'], [$status, $e['error']]);
        [$status, , , $e] = $this->api('move', ['game' => $game, 'piece' => 0, 'r' => 0, 'c' => 0], null, $token);
        $this->assertSame([409, 'no_room'], [$status, $e['error']]);
        [, , , $m] = $this->api('move', ['game' => $game, 'piece' => 0, 'r' => 0, 'c' => 7], null, $token);
        $this->assertSame([1, 9, 9, [null, 'I2', 'T1']], [$m['cleared'], $m['points'], $m['state']['score'], $m['state']['hand']]);
        $this->api('move', ['game' => $game, 'piece' => 1, 'r' => 4, 'c' => 0], null, $token);
        [, , , $m] = $this->api('move', ['game' => $game, 'piece' => 2, 'r' => 5, 'c' => 3], null, $token);
        $this->assertCount(3, array_filter($m['state']['hand']), 'all three placed: three new ones');
        [, , , $s] = $this->api('state', null, null, $token, "&game=$game");
        $this->assertSame($m['state'], $s['state'], 'the game again, e.g. after a reload');
        [$status] = $this->api('state', null, null, null, "&game=$game");
        $this->assertSame(403, $status, 'not without the token');
        $this->assertSame(1, $this->rows('hakoo_blockdoku_games', 'id = ? AND score = 9 AND moves = 3 AND scored_at IS NOT NULL', [$game]));
    }

    /** TC-API-296 | FR-BLOCKDOKU-04: no block left fits: the game is over and takes no more moves */
    public function test_game_over()
    {
        $chequer = '';
        for ($i = 0; $i < 81; $i++) $chequer .= (intdiv($i, 9) + $i % 9) % 2 === 0 ? '1' : '0';
        foreach ([0, 1, 2, 3] as $r) $chequer[$r * 9] = '0';
        [$game, $token] = $this->game(null, $chequer, ['I2', 'O', 'O']);
        [, , , $m] = $this->api('move', ['game' => $game, 'piece' => 0, 'r' => 0, 'c' => 0], null, $token);
        $this->assertTrue($m['state']['over']);
        [$status, , , $e] = $this->api('move', ['game' => $game, 'piece' => 1, 'r' => 0, 'c' => 1], null, $token);
        $this->assertSame([409, 'game_over'], [$status, $e['error']]);
    }

    /** TC-API-321 | FR-BLOCKDOKU-10, FR-BLOCKDOKU-04: a member goes on with their game on another device by their login; the colours come along; it ends at midnight */
    public function test_a_member_goes_on_elsewhere()
    {
        $carol = $this->sessionCookie(self::CAROL, 'Carol');
        [, , , $none] = $this->api('mine', null, $carol);
        $this->assertNull($none['game']);
        $row0 = str_repeat('1', 7) . '00' . str_repeat('0', 72);
        [$game, $token] = $this->game($carol, $row0, ['O', 'T1', 'L2']);
        $this->api('move', ['game' => $game, 'piece' => 1, 'r' => 4, 'c' => 0], null, $token);            // on the phone, with the token

        // On the PC: no token, only the login
        [, , , $mine] = $this->api('mine', null, $carol);
        $this->assertSame($game, $mine['game']);
        $this->assertSame('TTT', substr($mine['state']['colours'], 36, 3));
        $this->assertSame('X', $mine['state']['colours'][0], 'squares from before the colours were kept are grey');
        [, , , $m] = $this->api('move', ['game' => $game, 'piece' => 0, 'r' => 0, 'c' => 7], $carol);
        $this->assertSame([1, 9], [$m['cleared'], $m['points']]);
        $this->assertSame(str_repeat('0', 9), substr($m['state']['colours'], 0, 9), 'a cleared row has no colours');
        [, , , $s] = $this->api('state', null, $carol, null, "&game=$game");
        $this->assertSame($m['state'], $s['state']);

        // Nobody else: another member, a guest without the token
        [$status] = $this->api('state', null, $this->sessionCookie(self::BOB, 'Bob'), null, "&game=$game");
        $this->assertSame(403, $status);
        [$status] = $this->api('move', ['game' => $game, 'piece' => 2, 'r' => 8, 'c' => 0]);
        $this->assertSame(403, $status);
        [$status, , , $e] = $this->api('mine');
        $this->assertSame([401, 'not_logged_in'], [$status, $e['error']]);

        // A new day, a new game: yesterday's game is over, not offered, and takes no more moves
        $this->pdo->prepare('UPDATE hakoo_blockdoku_games SET day = day - INTERVAL 1 DAY WHERE id = ?')->execute([$game]);
        [, , , $none] = $this->api('mine', null, $carol);
        $this->assertNull($none['game']);
        [, , , $s] = $this->api('state', null, $carol, null, "&game=$game");
        $this->assertTrue($s['state']['over']);
        [$status, , , $e] = $this->api('move', ['game' => $game, 'piece' => 2, 'r' => 8, 'c' => 0], $carol);
        $this->assertSame([409, 'day_over'], [$status, $e['error']]);
        $this->assertSame(1, $this->rows('hakoo_blockdoku_games', 'id = ? AND ended = 1 AND score = 9', [$game]), 'its score stays on its day');

        // A game of today that is over is not offered either
        [$today] = $this->game($carol);
        $this->pdo->prepare('UPDATE hakoo_blockdoku_games SET ended = 1 WHERE id = ?')->execute([$today]);
        [, , , $none] = $this->api('mine', null, $carol);
        $this->assertNull($none['game']);
    }

    /** TC-API-297 | FR-BLOCKDOKU-06: today's high scores: each member's best once, every guest game; names only for members */
    public function test_todays_high_scores()
    {
        hakoo_ensure_user($this->pdo, self::CAROL);
        $this->scored(self::ALICE, 50, null, 'Alice');
        $this->scored(self::ALICE, 120, null, 'Alice');                     // her best counts, once
        $this->scored(null, 90);                                             // a guest
        $this->scored(self::CAROL, 30, null, 'Carol');
        $this->scored(null, 0);                                              // no points: not listed
        $this->scored(self::BOB, 999, '2020-01-01', 'Bob');                  // another day
        [, , , $guest] = $this->api('top');
        $this->assertSame([[1, 120, true, null], [2, 90, false, null], [3, 30, true, null]],
            array_map(fn($e) => [$e['place'], $e['score'], $e['member'], $e['name']], $guest['top']), 'a guest sees no names');
        $this->assertFalse($guest['loggedIn']);
        [, , , $carol] = $this->api('top', null, $this->sessionCookie(self::CAROL, 'Carol'));
        $this->assertSame(['Alice', null, 'Carol'], array_column($carol['top'], 'name'));
        $this->assertSame([false, false, true], array_column($carol['top'], 'you'));
        $this->assertSame(3, $carol['you']['place']);

        [, , , $j] = $this->api('new', [], $this->sessionCookie(self::CAROL, 'Carol'));   // a member's game has their id and name
        $this->assertSame(1, $this->rows('hakoo_blockdoku_games', 'id = ? AND discord_id = ? AND name = ?', [$j['game'], self::CAROL, 'Carol']));
    }

    /** TC-API-298 | FR-BLOCKDOKU-07: a day that is over keeps its first three; members there win a colour for their viking */
    public function test_yesterdays_first_three_and_their_colours()
    {
        $yesterday = (new DateTime($this->today()))->modify('-1 day')->format('Y-m-d');
        hakoo_ensure_user($this->pdo, self::CAROL);
        $this->scored(null, 200, $yesterday);                                // a guest won
        $this->scored(self::CAROL, 150, $yesterday, 'Carol');                // Carol second
        $this->scored(self::BOB, 100, $yesterday, 'Bob');                    // Bob third
        $this->scored(self::ALICE, 50, $yesterday, 'Alice');                 // Alice fourth
        [, , , $j] = $this->api('top', null, $this->sessionCookie(self::ALICE, 'Alice'));
        $this->assertSame([[1, false, null], [2, true, 'Carol'], [3, true, 'Bob']],
            array_map(fn($e) => [$e['place'], $e['member'], $e['name']], $j['yesterday']));
        $this->assertSame(3, $this->rows('hakoo_blockdoku_podium', 'day = ?', [$yesterday]));
        $this->api('top');                                                   // written once
        $this->assertSame(3, $this->rows('hakoo_blockdoku_podium', 'day = ?', [$yesterday]));

        $me = fn($id) => $this->http('GET', 'avatar.php?action=me', null, $this->sessionCookie($id))[3];
        $this->assertContains('colour:mint', $me(self::CAROL)['unlocked']);
        $this->assertNotContains('colour:magenta', $me(self::CAROL)['unlocked']);
        $this->assertContains('colour:indigo', $me(self::BOB)['unlocked']);
        $this->assertNotContains('colour:indigo', $me(self::ALICE)['unlocked']);
        $saved = $this->http('POST', 'avatar.php?action=save', ['cfg' => ['clothes' => 'mint', 'legcolor' => 'magenta']], $this->sessionCookie(self::CAROL))[3];
        $this->assertSame(['mint', 'grey'], [$saved['cfg']['clothes'], $saved['cfg']['legcolor']], 'only the colour won');
    }

    /** TC-API-299 | FR-BLOCKDOKU-08: Blockdoku in the reminders: chosen, and done once a move is made today */
    public function test_reminders()
    {
        $carol = $this->sessionCookie(self::CAROL, 'Carol');
        [, , , $me] = $this->http('GET', 'reminders.php?action=me', null, $carol);
        $this->assertArrayHasKey('blockdoku', $me['games']);
        [$status, , , $r] = $this->http('POST', 'reminders.php?action=save', ['via' => 'channel', 'tz' => 'Europe/Stockholm', 'consent' => false,
            'reminders' => [['game' => 'blockdoku', 'time' => '18:00']]], $carol);
        $this->assertSame([200, 'blockdoku'], [$status, $r['reminders'][0]['game']]);
        $this->pdo->exec('UPDATE hakoo_reminders SET last_day = NULL');
        $tick = fn($day) => $this->http('POST', 'reminders.php?action=tick', ['now' => "$day 18:00:30 Europe/Stockholm"], null,
            ['<BOT_AUTH_HEADER>: ' . <BOT_SECRET>])[3];
        $day = $this->today();
        $this->pdo->prepare("INSERT INTO hakoo_blockdoku_games (id, token, discord_id, day, board, hand, moves) VALUES ('bd00000000000001', ?, ?, ?, ?, '[]', 1)")
            ->execute([str_repeat('0', 64), self::CAROL, $day, Blockdoku::emptyBoard()]);
        $this->assertSame([0, 1], [($t = $tick($day))['sent'], $t['skipped']], 'played today: no reminder');
    }

    /** TC-API-300 | FR-BLOCKDOKU-08: the member's games and places are in "Download my data" and go with "Delete all my data" */
    public function test_data()
    {
        hakoo_ensure_user($this->pdo, self::CAROL);
        $this->scored(self::CAROL, 40, '2020-01-02', 'Carol');
        $this->api('top');                                                   // 2020-01-02 is over: Carol first
        $d = hakoo_collect_user($this->pdo, self::CAROL);
        $this->assertSame(40, (int)$d['tables']['hakoo_blockdoku_games'][0]['score']);
        $this->assertSame(1, (int)$d['tables']['hakoo_blockdoku_podium'][0]['place']);
        $this->assertArrayNotHasKey('token', $d['tables']['hakoo_blockdoku_games'][0]);
        $this->http('POST', 'mydata.php?action=delete', ['confirm' => 'DELETE'], $this->sessionCookie(self::CAROL));
        $this->assertSame(0, $this->rows('hakoo_blockdoku_games', 'discord_id = ?', [self::CAROL]));
        $this->assertSame(0, $this->rows('hakoo_blockdoku_podium', 'discord_id = ?', [self::CAROL]));
    }
}
