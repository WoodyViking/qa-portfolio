<?php
/**
 * System tests of the players on HAKOO Games (src/members.php ?action=players and ?action=card): who is in the list,
 * what a player's card holds (their viking, their puzzle medals, and their profile only when they show it), and
 * leaving the list. Fixture: Bob shows his profile; Alice (an admin) keeps hers hidden; Carol has left the list.
 * The Discord stub (qa/automation/mocks/wg-api.php) gives the server's roles and member list.
 * Traceability: FR-PLAYERS-01..04, FR-PLAYERS-06..08.
 */
class PlayersApiTest extends HakooTestCase
{
    private function get(string $query, $who, string $name = 'Tester')
    {
        return $this->http('GET', "members.php?$query", null, $who ? $this->sessionCookie($who, $name) : null);
    }

    /** TC-API-274 | FR-PLAYERS-01: logged out gets nothing; a member sees who has logged in, by name, without those who left the list (but always themselves) */
    public function test_the_list()
    {
        [$status, , , $j] = $this->get('action=players', null);
        $this->assertSame([401, 'not_logged_in'], [$status, $j['error']]);
        $this->assertArrayNotHasKey('players', $j);

        [$status, , , $j] = $this->get('action=players', self::BOB, 'Bob');
        $this->assertSame(200, $status);
        $this->assertSame(['Alice', 'Bob'], array_column($j['players'], 'name'));            // Carol left the list
        $this->assertSame([false, true], array_column($j['players'], 'shared'));
        $this->assertSame([false, true], array_column($j['players'], 'own'));
        $this->assertSame([null, 'axe'], array_map(fn($p) => $p['viking']['right'] ?? null, $j['players']));

        [, , , $j] = $this->get('action=players', self::CAROL, 'Carol');
        $this->assertSame(['Alice', 'Bob', 'Carol'], array_column($j['players'], 'name'));   // herself, though she left it
        [, , , $j] = $this->get('action=players', self::ALICE, 'Alice');
        $this->assertSame(['Alice', 'Bob'], array_column($j['players'], 'name'));            // the same for an admin
    }

    /** TC-API-278 | FR-PLAYERS-06, FR-PLAYERS-07, FR-RANK-01: only who has played; grouped by the site's owner and admins, then by rank (tier); the Discord name colour, the rank and the site roles as tags */
    public function test_groups_and_tags()
    {
        // The Discord stub has Alice, Bob (an Officer: purple) and Carol. Carol is an admin here, without a name yet.
        $this->pdo->exec("UPDATE hakoo_users SET players_hidden = 0, name = '' WHERE discord_id = " . self::CAROL);
        $this->pdo->exec("INSERT INTO hakoo_roles (discord_id, role) VALUES (" . self::BOB . ", 'tester'), (" . self::BOB . ", 'og'), (" . self::CAROL . ", 'admin')");
        $this->pdo->exec("INSERT INTO hakoo_users (discord_id, name, created_at, last_login_at) VALUES (" . self::DAVE . ", 'Dave', NOW(), NOW())");
        [, , , $j] = $this->get('action=players', self::ALICE, 'Alice');
        $this->assertSame(['Alice', 'Bob'], array_column($j['players'], 'name'));            // Carol and Dave have not played
        $this->assertSame(403, $this->get('action=card&id=' . self::DAVE, self::BOB, 'Bob')[0]);
        $this->assertSame(200, $this->get('action=card&id=' . self::DAVE, self::ALICE, 'Alice')[0]);   // an admin still can

        foreach ([self::CAROL, self::DAVE] as $who) {                                       // now they have
            $this->pdo->prepare("INSERT INTO hakoo_sudoku_daily (discord_id, day, level, name, seconds, solved_at) VALUES (?, CURDATE(), 'easy', 'x', 60, NOW())")->execute([$who]);
        }
        $this->pdo->exec("UPDATE hakoo_users SET tier = 'vip' WHERE discord_id = " . self::DAVE);
        [, , , $j] = $this->get('action=players', self::ALICE, 'Alice');
        $this->assertSame([['owner', 'King'], ['admin', 'Admin'], ['jarl', 'Jarls'], ['free', 'Members']],   // the owner is the King
                          array_map(fn($g) => [$g['id'], $g['name']], $j['groups']));
        $this->assertSame(['admin' => true, 'owner' => true, 'founder' => false], $j['viewer']);
        $p = array_column($j['players'], null, 'id');
        $this->assertSame(['owner', ['owner'], ''], [$p[self::ALICE]['group'], $p[self::ALICE]['badges'], $p[self::ALICE]['rank']]);   // the owner, not also "admin"; no rank picked
        $this->assertSame(['free', '', '#9b59b6', ['tester', 'og'], 'Bob'],
                          [$p[self::BOB]['group'], $p[self::BOB]['rank'], $p[self::BOB]['color'], $p[self::BOB]['badges'], $p[self::BOB]['name']]);
        $this->assertSame(['admin', 'Carol', ['admin']], [$p[self::CAROL]['group'], $p[self::CAROL]['name'], $p[self::CAROL]['badges']]);   // her name from Discord
        $this->assertSame(['jarl', 'jarl', 'Dave', ''], [$p[self::DAVE]['group'], $p[self::DAVE]['rank'], $p[self::DAVE]['name'], $p[self::DAVE]['color']]);   // not on the server
        $this->pdo->exec("UPDATE hakoo_users SET tier = 'paying', rank_alt = 1 WHERE discord_id = " . self::BOB);
        [, , , $j] = $this->get('action=players', self::BOB, 'Bob');
        $this->assertSame(['admin' => false, 'owner' => false, 'founder' => false], $j['viewer']);
        $p = array_column($j['players'], null, 'id');
        $this->assertSame(['viking', 'shieldmaiden'], [$p[self::BOB]['group'], $p[self::BOB]['rank']]);   // Bob's own pick (an old save)
        [, , , $c] = $this->get('action=card&id=' . self::BOB, self::CAROL, 'Carol');
        $this->assertSame(['#9b59b6', ['tester', 'og'], 'shieldmaiden', 'paying'], [$c['card']['color'], $c['card']['badges'], $c['card']['rank'], $c['card']['tier']]);
    }

    /** TC-API-275 | FR-PLAYERS-02: a card has the viking and the medals; the profile only when it is shown (or one's own, or for an admin) */
    public function test_a_card()
    {
        [$status, , , $j] = $this->get('action=card&id=' . self::BOB, self::CAROL, 'Carol');
        $this->assertSame(200, $status);
        $c = $j['card'];
        $this->assertSame(['Bob', true, false, 'axe'], [$c['name'], $c['shared'], $c['own'], $c['viking']['right']]);
        $this->assertSame(['nonogram' => 'bronze', 'sudoku' => 'bronze', 'owldoku' => 'bronze'], $c['medals']);
        $this->assertSame(['farmer', 'viking', 'shieldmaiden'], $c['profile']['runborg']['titles']);

        [, , , $j] = $this->get('action=card&id=' . self::ALICE, self::BOB, 'Bob');         // hidden profile: the viking and medals only
        $this->assertSame([false, null, null], [$j['card']['shared'], $j['card']['profile'], $j['card']['viking']]);
        $this->assertSame(['nonogram' => 'gold', 'sudoku' => 'gold', 'owldoku' => 'gold'], $j['card']['medals']);

        [$status, , , $e] = $this->get('action=card&id=' . self::CAROL, self::BOB, 'Bob');   // not in the list
        $this->assertSame([403, 'private'], [$status, $e['error']]);
        $this->assertArrayNotHasKey('card', $e);
        [, , , $j] = $this->get('action=card&id=' . self::CAROL, self::CAROL, 'Carol');      // her own
        $this->assertSame([true, 'Carol'], [$j['card']['own'], $j['card']['profile']['name']]);
        [$status, , , $j] = $this->get('action=card&id=' . self::CAROL, self::ALICE, 'Alice'); // an admin
        $this->assertSame([200, 'Carol'], [$status, $j['card']['profile']['name']]);
        $this->assertSame(403, $this->get('action=card&id=' . self::DAVE, self::BOB)[0]);    // never logged in
        $this->assertSame(400, $this->get('action=card&id=x', self::BOB)[0]);
    }

    /** TC-API-276 | FR-PLAYERS-03: the puzzle medals: the best level won in each puzzle, and the longest run of puzzles of the day */
    public function test_medals()
    {
        [, , , $j] = $this->get('action=card&id=' . self::CAROL, self::CAROL, 'Carol');
        $this->assertSame([], $j['card']['medals']);
        $this->pdo->exec('INSERT INTO hakoo_sudoku_profile (discord_id, won_easy, won_medium) VALUES (' . self::CAROL . ', NOW(), NOW())');
        for ($d = 0; $d < 30; $d++) {
            $this->pdo->prepare("INSERT INTO hakoo_owldoku_daily (discord_id, day, level, name, seconds, solved_at) VALUES (?, CURDATE() - INTERVAL ? DAY, 'easy', 'Carol', 60, NOW())")
                ->execute([self::CAROL, $d]);
        }
        [, , , $j] = $this->get('action=card&id=' . self::CAROL, self::CAROL, 'Carol');
        $this->assertSame(['sudoku' => 'silver', 'streak' => 30], $j['card']['medals']);
        [, , , $j] = $this->get('action=profile&id=' . self::CAROL, self::CAROL, 'Carol');  // on the profile too
        $this->assertSame(['sudoku' => 'silver', 'streak' => 30], $j['profile']['medals']);
    }

    /** TC-API-277 | FR-PLAYERS-04: a member leaves the list or comes back; saving the profile choices without it keeps it */
    public function test_leave_the_list()
    {
        $bob = $this->sessionCookie(self::BOB, 'Bob');
        [, , , $j] = $this->http('GET', 'members.php?action=settings', null, $bob);
        $this->assertTrue($j['inPlayers']);
        [, , , $j] = $this->http('POST', 'members.php?action=settings', ['visible' => true, 'showAccounts' => true, 'inPlayers' => false], $bob);
        $this->assertSame([true, false], [$j['visible'], $j['inPlayers']]);
        [, , , $l] = $this->get('action=players', self::ALICE, 'Alice');
        $this->assertSame(['Alice'], array_column($l['players'], 'name'));
        $this->assertSame(403, $this->get('action=card&id=' . self::BOB, self::CAROL, 'Carol')[0]);
        [, , , $j] = $this->http('POST', 'members.php?action=settings', ['visible' => true, 'showAccounts' => true], $bob);
        $this->assertFalse($j['inPlayers']);                                                 // not sent: unchanged
        [, , , $j] = $this->http('POST', 'members.php?action=settings', ['visible' => true, 'inPlayers' => true], $bob);
        $this->assertTrue($j['inPlayers']);
        [, , , $x] = $this->http('GET', 'mydata.php?action=export', null, $bob);
        $this->assertSame(0, (int)$x['account']['players_hidden']);
    }

    /** TC-API-280 | FR-PLAYERS-08: a puzzle's page: who has opened today's puzzle, with today's and yesterday's times, the days in a row and all solved; most solved and fastest first */
    public function test_todays_puzzlers()
    {
        [$status, , , $j] = $this->get('action=day&game=owldoku', null);
        $this->assertSame([401, 'not_logged_in'], [$status, $j['error']]);
        $this->assertSame(400, $this->get('action=day&game=chess', self::BOB, 'Bob')[0]);
        $today = (new DateTimeImmutable('now', new DateTimeZone('Europe/Stockholm')))->format('Y-m-d');
        $yesterday = (new DateTimeImmutable($today))->modify('-1 day')->format('Y-m-d');
        $row = $this->pdo->prepare('INSERT INTO hakoo_owldoku_daily (discord_id, day, level, name, created_at, solved_at, seconds, mistakes) VALUES (?, ?, ?, ?, NOW(), ?, ?, ?)');
        foreach ([[self::BOB, $today, 'easy', 'Bob', 90, 0], [self::BOB, $today, 'medium', 'Bob', null, 3],       // out of hearts
                  [self::BOB, $yesterday, 'easy', 'Bob', 120, 0],
                  [self::ALICE, $today, 'easy', 'Alice', 200, 0], [self::ALICE, $today, 'hard', 'Alice', 400, 1],
                  [self::ALICE, $yesterday, 'hard', 'Alice', null, 0],
                  [self::CAROL, $today, 'easy', 'Carol', 30, 0]] as [$who, $day, $level, $name, $s, $m]) {   // Carol left the list
            $row->execute([$who, $day, $level, $name, $s === null ? null : date('Y-m-d H:i:s'), $s, $m]);
        }
        [$status, , , $j] = $this->get('action=day&game=owldoku', self::BOB, 'Bob');
        $this->assertSame([200, $today, self::BOB], [$status, $j['day'], $j['me']]);
        $this->assertSame(['Alice', 'Bob'], array_column($j['players'], 'name'));            // two solved before one
        [$alice, $bob] = $j['players'];
        $this->assertSame(['easy' => 200, 'hard' => 400], $alice['today']);
        $this->assertSame(['hard' => 'open'], $alice['yesterday']);
        $this->assertSame(['easy' => 90, 'medium' => 'failed'], $bob['today']);
        $this->assertSame(['easy' => 120], $bob['yesterday']);
        $this->assertSame([2, 2, true], [$bob['streak'], $bob['total'], $bob['own']]);      // yesterday and today
        $this->assertSame([1, 3, ['owner']], [$alice['streak'], $alice['total'], $alice['badges']]);   // and one long ago
        [, , , $j] = $this->get('action=day&game=owldoku', self::CAROL, 'Carol');
        $this->assertSame(['Alice', 'Carol', 'Bob'], array_column($j['players'], 'name'));   // herself, though she left it
        [, , , $j] = $this->get('action=day&game=sudoku', self::BOB, 'Bob');
        $this->assertSame([], $j['players']);
    }
}
