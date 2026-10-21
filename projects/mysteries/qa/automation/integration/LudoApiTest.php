<?php
// Kunglig Vikingludo over the API (system level): the lobby choices, the start, whose turn it is, the server's die,
// and what the board may see. Every test starts its own session.
// Traceability: US-15, K-42, K-44, K-45, K-46, K-48, K-49, K-13, NFR-05, AK-29, AK-30, AK-31, AK-35, AK-36, AK-37, AK-38.
use PHPUnit\Framework\TestCase;

final class LudoApiTest extends TestCase
{
    private const BOB  = [TEST_BOB, 'Bob'];
    private const ERIK = [TEST_ERIK, 'Erik'];
    private const ALICE = [TEST_ALICE, 'Alice'];

    /** A Ludo session with Bob as host and player, a board and Erik let in: [host token, board token, Erik's token]. */
    private function table(): array
    {
        [$status, $g] = my_api('POST', 'create', ['category' => 'ludo', 'lengthMin' => 30, 'waitMin' => 5, 'hostPlays' => true, 'boardLang' => 'en'], self::BOB);
        $this->assertSame(200, $status, json_encode($g));
        [, $b] = my_api('POST', 'join', ['code' => $g['code'], 'kind' => 'board', 'name' => 'TV']);
        [, $e] = my_api('POST', 'join', ['code' => $g['code'], 'kind' => 'player'], self::ERIK);
        my_api('POST', 'approve', ['device' => $b['view']['you']['id'], 'ok' => true], self::BOB, $g['token']);
        my_api('POST', 'approve', ['device' => $e['view']['you']['id'], 'ok' => true], self::BOB, $g['token']);
        return [$g['token'], $b['token'], $e['token']];
    }

    /** A player joins the session of the board token and Bob lets them in: their token. */
    private function newcomer(string $host, string $board, array $who): string
    {
        [, $v] = my_api('GET', 'state', null, null, $board);
        [, $j] = my_api('POST', 'join', ['code' => $v['view']['session']['code'], 'kind' => 'player'], $who);
        my_api('POST', 'approve', ['device' => $j['view']['you']['id'], 'ok' => true], self::BOB, $host);
        return $j['token'];
    }

    /** The places as the board sees them: [[name, colour, place], ...]. */
    private function places(string $board): array
    {
        [, $v] = my_api('GET', 'state', null, null, $board);
        return array_map(fn($p) => [$p['name'], $p['colour'], $p['place']], $v['view']['board']['players']);
    }

    /** TC-API-016 (K-42, AK-35): Ludo needs 2 to 6 players; one player cannot start */
    public function testLudoNeedsTwoPlayers(): void
    {
        [, $g] = my_api('POST', 'create', ['category' => 'ludo', 'lengthMin' => 30, 'waitMin' => 5, 'boardLang' => 'en'], self::BOB);
        [$status, $j] = my_api('POST', 'start', [], self::BOB, $g['token']);
        $this->assertSame([409, 'ludo_players'], [$status, $j['error']]);
        [, $v] = my_api('GET', 'state', null, self::BOB, $g['token']);
        $this->assertSame('lobby', $v['view']['session']['status']);
    }

    /** TC-API-017 (K-45, K-46, AK-31, K-13): one player per colour; the way of rolling; the board sees names and colours, never ids */
    public function testColoursAndWayOfRolling(): void
    {
        [$host, $board, $erik] = $this->table();
        [$status, $j] = my_api('POST', 'choose', ['colour' => 'fjord'], self::ERIK, $erik);
        $this->assertSame(200, $status, json_encode($j));
        $this->assertSame(['colour' => 'fjord', 'roll' => 'tap', 'player' => null], $j['view']['mine']);
        [$status, $j] = my_api('POST', 'choose', ['colour' => 'fjord'], self::BOB, $host);
        $this->assertSame([409, 'colour_taken'], [$status, $j['error']]);                // AK-31
        foreach ([['colour' => 'pink'], ['colour' => 3], ['roll' => 'throw']] as $bad) {
            [$status] = my_api('POST', 'choose', $bad, self::BOB, $host);
            $this->assertSame(400, $status, json_encode($bad));
        }
        [$status, $j] = my_api('POST', 'choose', ['colour' => 'blood', 'roll' => 'shake'], self::BOB, $host);
        $this->assertSame(['blood', 'shake'], [$j['view']['mine']['colour'], $j['view']['mine']['roll']]);
        [$status, $j] = my_api('POST', 'choose', ['colour' => 'moss'], null, $board);
        $this->assertSame([403, 'not_player'], [$status, $j['error']]);                  // a board chooses nothing
        [$status] = my_api('POST', 'choose', ['colour' => 'moss'], self::ERIK, $host);   // Bob's device, Erik's login
        $this->assertSame(401, $status);

        [, $v] = my_api('GET', 'state', null, null, $board);
        $taken = $v['view']['board']['taken'];
        usort($taken, fn($a, $b) => strcmp($a['name'], $b['name']));
        $this->assertSame([['name' => 'Bob', 'colour' => 'blood'], ['name' => 'Erik', 'colour' => 'fjord']], $taken);
        $this->assertArrayNotHasKey('mine', $v['view']);
        $raw = json_encode($v);
        $this->assertStringNotContainsString(TEST_BOB, $raw);                             // no Discord ids on the board
        $this->assertStringNotContainsString(TEST_ERIK, $raw);

        my_api('POST', 'start', [], self::BOB, $host);
        [$status, $j] = my_api('POST', 'choose', ['colour' => 'moss'], self::ERIK, $erik);
        $this->assertSame([409, 'not_lobby'], [$status, $j['error']]);                   // colours are fixed once the game is on
        [$status, $j] = my_api('POST', 'choose', ['roll' => 'shake'], self::ERIK, $erik);  // the way of rolling is not
        $this->assertSame([200, 'shake', 'fjord'], [$status, $j['view']['mine']['roll'], $j['view']['mine']['colour']]);
        [, $v] = my_api('GET', 'state', null, null, $board);
        $this->assertSame(['blood', 'fjord'], array_map(fn($p) => $p['colour'], $v['view']['board']['players']));
        $this->assertStringNotContainsString(TEST_ERIK, json_encode($v));
    }

    /** TC-API-018 (K-44, K-48, AK-29, AK-30, NFR-05): only the player whose turn it is rolls; the server rolls; a move is checked */
    public function testTurnsAndTheServersDie(): void
    {
        [$host, $board, $erik] = $this->table();
        [, $j] = my_api('POST', 'start', [], self::BOB, $host);
        $game = $j['view']['board']['game'];
        $this->assertSame(['royal4', [0, 2], null], [$game['board'], $game['seat'], $game['winner']]);
        $players = [[self::BOB, $host], [self::ERIK, $erik]];
        $faces = [];
        for ($n = 0; $n < 40; $n++) {
            [, $v] = my_api('GET', 'state', null, null, $board);
            $g = $v['view']['board']['game'];
            [$as, $token] = $players[$g['turn']];
            [$other, $otherToken] = $players[1 - $g['turn']];
            [$status, $j] = my_api('POST', 'act', ['do' => 'roll'], $other, $otherToken);
            $this->assertSame([400, 'not_your_turn'], [$status, $j['error']]);          // AK-30: not your turn
            if ($g['roll'] !== null) {
                [$status, $j] = my_api('POST', 'act', ['do' => 'roll'], $as, $token);
                $this->assertSame([400, 'move_first'], [$status, $j['error']]);
                $legal = [];
                foreach ($g['pos'][$g['turn']] as $i => $p) $legal[] = $i;
                [$status, $j] = my_api('POST', 'act', ['do' => 'move', 'piece' => 9], $as, $token);
                $this->assertSame([400, 'bad_piece'], [$status, $j['error']]);
                [$status, $j] = my_api('POST', 'act', ['do' => 'move', 'piece' => '0'], $as, $token);
                $this->assertSame([400, 'bad_piece'], [$status, $j['error']]);           // a number, not a string
                foreach ($legal as $i) {
                    [$status, $j] = my_api('POST', 'act', ['do' => 'move', 'piece' => $i], $as, $token);
                    if ($status === 200) break;
                }
                $this->assertSame(200, $status, 'one piece may move');
                continue;
            }
            [$status, $j] = my_api('POST', 'act', ['do' => 'roll', 'v' => 6, 'value' => 6], $as, $token);   // asking for a 6 does nothing
            $this->assertSame(200, $status, json_encode($j));
            $rolls = array_values(array_filter($j['view']['board']['log'], fn($e) => $e['k'] === 'roll'));
            $faces[] = end($rolls)['v'];
        }
        $this->assertGreaterThan(5, count($faces));
        foreach ($faces as $f) $this->assertSame(true, $f >= 1 && $f <= 6);
        $this->assertSame(true, count(array_unique($faces)) > 1, 'the server rolls, not the request');
        [, $v] = my_api('GET', 'state', null, null, $board);
        $this->assertLessThanOrEqual(20, count($v['view']['board']['log']));             // the log stays short
        $this->assertSame(true, $v['view']['board']['n'] >= count($faces));

        my_api('POST', 'pause', [], self::BOB, $host);                                   // K-15: no moves while paused
        $g = $v['view']['board']['game'];
        [$as, $token] = $players[$g['turn']];
        [$status, $j] = my_api('POST', 'act', ['do' => 'roll'], $as, $token);
        $this->assertSame([409, 'not_playing'], [$status, $j['error']]);
        [$status, $j] = my_api('POST', 'act', ['do' => 'cheat'], $as, $token);
        $this->assertSame(409, $status);
        my_api('POST', 'resume', [], self::BOB, $host);
        [$status, $j] = my_api('POST', 'act', ['do' => 'cheat'], $as, $token);
        $this->assertSame([400, 'bad_action'], [$status, $j['error']]);
    }

    /** TC-API-019 (K-49, AK-36, AK-38): a player leaves; only the host picks the computer; it plays; the player comes back */
    public function testTheComputerTakesOver(): void
    {
        [$host, $board, $erik] = $this->table();
        my_api('POST', 'choose', ['colour' => 'fjord'], self::ERIK, $erik);
        my_api('POST', 'start', [], self::BOB, $host);
        my_api('POST', 'leave', [], self::ERIK, $erik);
        $this->assertSame([['Bob', 'blood', 'here'], ['Erik', 'fjord', 'away']], $this->places($board));
        $alice = $this->newcomer($host, $board, self::ALICE);
        [$status, $j] = my_api('POST', 'seat', ['seat' => 1, 'to' => 'computer'], self::ALICE, $alice);
        $this->assertSame([403, 'not_host'], [$status, $j['error']]);                      // AK-38
        [, $v] = my_api('GET', 'state', null, self::BOB, $host);
        $this->assertSame([['seat' => 1, 'name' => 'Erik', 'colour' => 'fjord', 'place' => 'away']], $v['view']['mine']['vacant']);
        $this->assertSame([['k' => 1, 'name' => 'Alice']], $v['view']['mine']['free']);   // Erik left: Bob 0, Alice 1
        $this->assertArrayNotHasKey('vacant', my_api('GET', 'state', null, self::ALICE, $alice)[1]['view']['mine']);   // the host's only

        [$status, $j] = my_api('POST', 'seat', ['seat' => 1, 'to' => 'computer'], self::BOB, $host);
        $this->assertSame(200, $status, json_encode($j));
        [$status, $j] = my_api('POST', 'seat', ['seat' => 1, 'to' => 'computer'], self::BOB, $host);
        $this->assertSame([409, 'seat_taken'], [$status, $j['error']]);
        $this->assertSame(['Erik', 'fjord', 'bot'], $this->places($board)[1]);
        $log = my_api('GET', 'state', null, null, $board)[1]['view']['board']['log'];
        $this->assertSame(['k' => 'seat', 'by' => 'bot', 'was' => 'Erik'], array_intersect_key(end($log), array_flip(['k', 'by', 'was'])));

        $computerRolled = false;                                                            // Bob plays; the computer plays Erik
        for ($n = 0; $n < 60 && !$computerRolled; $n++) {
            [, $v] = my_api('GET', 'state', null, null, $board);
            $g = $v['view']['board']['game'];
            foreach ($v['view']['board']['log'] as $e) if ($e['k'] === 'roll' && $e['p'] === 1) $computerRolled = true;
            if ($g['turn'] === 0) {
                if ($g['roll'] === null) my_api('POST', 'act', ['do' => 'roll'], self::BOB, $host);
                else for ($i = 0; $i < 4; $i++) if (my_api('POST', 'act', ['do' => 'move', 'piece' => $i], self::BOB, $host)[0] === 200) break;
            } else {
                [$status, $j] = my_api('POST', 'act', ['do' => 'roll'], self::ALICE, $alice);   // a watcher cannot roll for the computer
                $this->assertSame([400, 'not_in_game'], [$status, $j['error']]);
                usleep(1_100_000);
            }
        }
        $this->assertTrue($computerRolled, 'the computer rolled for Erik');

        $erik = $this->newcomer($host, $board, self::ERIK);                                 // Erik comes back: his place again
        $this->assertSame(['Erik', 'fjord', 'here'], $this->places($board)[1]);
        [, $v] = my_api('GET', 'state', null, self::ERIK, $erik);
        $this->assertSame(1, $v['view']['mine']['player']);
        $this->assertSame([], my_api('GET', 'state', null, self::BOB, $host)[1]['view']['mine']['vacant']);
    }

    /** TC-API-020 (K-49, AK-37, AK-38): the host gives the place to a new player; refused choices; the host gone too */
    public function testANewPlayerTakesOver(): void
    {
        [$host, $board, $erik] = $this->table();
        my_api('POST', 'start', [], self::BOB, $host);
        my_api('POST', 'leave', [], self::ERIK, $erik);
        $alice = $this->newcomer($host, $board, self::ALICE);
        [, $v] = my_api('GET', 'state', null, null, $board);
        $before = $v['view']['board']['game']['pos'];
        $colour = $v['view']['board']['players'][1]['colour'];
        foreach ([[['seat' => 1, 'to' => 0, 'name' => 'Bob'], 409, 'has_seat'],               // Bob is k 0, Alice k 1
                  [['seat' => 1, 'to' => 1, 'name' => 'Carol'], 409, 'changed'],
                  [['seat' => 0, 'to' => 1, 'name' => 'Alice'], 409, 'seat_taken'],           // Bob is still here
                  [['seat' => 5, 'to' => 1, 'name' => 'Alice'], 400, 'bad_seat'],
                  [['seat' => '1', 'to' => 1, 'name' => 'Alice'], 400, 'bad_seat'],
                  [['seat' => 1, 'to' => 7, 'name' => 'Alice'], 400, 'bad_player'],
                  [['seat' => 1, 'to' => 'Alice'], 400, 'bad_player']] as [$body, $code, $error]) {
            [$status, $j] = my_api('POST', 'seat', $body, self::BOB, $host);
            $this->assertSame([$code, $error], [$status, $j['error'] ?? null], json_encode($body));
        }
        $this->assertSame(['Erik', $colour, 'away'], $this->places($board)[1]);            // nothing changed

        [$status] = my_api('POST', 'seat', ['seat' => 1, 'to' => 1, 'name' => 'Alice'], self::BOB, $host);
        $this->assertSame(200, $status);
        $this->assertSame(['Alice', $colour, 'here'], $this->places($board)[1]);           // AK-37: Erik's colour
        [, $v] = my_api('GET', 'state', null, self::ALICE, $alice);
        $this->assertSame([1, $colour], [$v['view']['mine']['player'], $v['view']['mine']['colour']]);
        $this->assertSame($before, $v['view']['board']['game']['pos']);                    // and his pieces where they stood
        $this->assertStringNotContainsString(TEST_ALICE, json_encode(my_api('GET', 'state', null, null, $board)[1]));

        my_api('POST', 'leave', [], self::BOB, $host);                                      // the host leaves, then Alice:
        my_api('POST', 'leave', [], self::ALICE, $alice);                                   // nobody can choose, the computer plays
        $this->assertSame(['bot', 'bot'], array_column($this->places($board), 2));
    }
}

