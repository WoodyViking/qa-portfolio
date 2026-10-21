<?php
/**
 * System tests of src/admin.php (the admin page), the admin role in codes.php and texts-admin.php,
 * and the play statistics (hakoo_count in the game APIs).
 * Alice is the owner (DISCORD_ADMIN_IDS in the test config); the Discord API is the stub
 * (qa/automation/mocks/wg-api.php): every id is a member called "Member <last 4 digits>",
 * except ids ending in 404 (not on the server) and 777 (a bot).
 * Traceability: FR-ADMIN-01..08, REG-GDPR-15, REG-GDPR-17.
 */
class AdminApiTest extends HakooTestCase
{
    const BLOCKED = '/var/www/html/hakoo_codes_blocked.json';   // the web root's loot files (gitignored)
    const MOCK = 'http://wgmock:8000/discord';
    const TESTERS = '900000000000000010';                       // the role "Hakoo testers" in the stub

    protected function setUp(): void
    {
        parent::setUp();
        file_get_contents(self::MOCK . '/_reset', false, stream_context_create(['http' => ['method' => 'POST']]));
    }

    /** The role changes the website asked Discord for since setUp: ["PUT <member>", "DELETE <member>"]. */
    private function discordRoles(): array
    {
        $out = [];
        foreach (json_decode(file_get_contents(self::MOCK . '/_log'), true) ?: [] as $c) {
            if (preg_match('~/members/(\d+)/roles/' . self::TESTERS . '$~', $c['path'], $m)) $out[] = $c['method'] . ' ' . $m[1];
        }
        return $out;
    }
    /** The members list as [id => [name, discord, roles]]. */
    private function people(array $j): array
    {
        $out = [];
        foreach ($j['members'] as $p) $out[$p['id']] = [$p['name'], $p['discord'], array_keys($p['roles'])];
        return $out;
    }

    protected function tearDown(): void
    {
        @unlink(self::BLOCKED);
    }

    private function as($who, $name = 'Tester')
    {
        return $this->sessionCookie($who, $name);
    }
    private function state($who)
    {
        return $this->http('GET', 'admin.php?action=state', null, $this->as($who));
    }
    private function role($who, $member, $role, $grant = true)
    {
        return $this->http('POST', 'admin.php?action=role', ['discord_id' => $member, 'role' => $role, 'grant' => $grant, 'name' => 'Typed'], $this->as($who));
    }

    /** TC-API-239 | FR-ADMIN-01: the page is for the owner and the admins; anyone else gets nothing */
    public function test_only_admins_see_the_page()
    {
        [$status, , , $j] = $this->http('GET', 'admin.php?action=state');
        $this->assertSame([401, 'not_logged_in'], [$status, $j['error']]);
        [$status, , , $j] = $this->state(self::CAROL);
        $this->assertSame([403, 'not_admin'], [$status, $j['error']]);
        $this->assertArrayNotHasKey('stats', $j);
        [$status, , , $j] = $this->http('GET', 'admin.php?action=me', null, $this->as(self::CAROL));
        $this->assertSame([200, false, false], [$status, $j['admin'], $j['owner']]);

        [$status, , , $j] = $this->state(self::ALICE);
        $this->assertSame(200, $status);
        $this->assertTrue($j['me']['owner']);
        $this->assertTrue($j['installed']);
        $this->assertSame([], $j['members']);
        $this->assertTrue($j['names']);
        $this->assertSame(['codes', 'blocked', 'submitted', 'reports'], array_keys($j['loot']));
    }

    /** TC-API-240 | FR-ADMIN-02: the owner makes a member an admin (the name comes from Discord); the admin sees the page but does not give Admin */
    public function test_owner_gives_and_takes_the_admin_role()
    {
        [$status, , , $j] = $this->role(self::ALICE, self::BOB, 'admin');
        $this->assertSame(200, $status);
        $this->assertSame([self::BOB => ['', 'Member 0002', ['admin']]], $this->people($j));
        $this->assertNull($j['discord']);                                     // only Tester has a Discord role
        $this->assertSame(1, $this->rows('hakoo_roles', 'discord_id = ? AND granted_by = ?', [self::BOB, self::ALICE]));

        [$status, , , $j] = $this->state(self::BOB);
        $this->assertSame(200, $status);
        $this->assertFalse($j['me']['owner']);
        [$status, , , $j] = $this->http('GET', 'admin.php?action=me', null, $this->as(self::BOB));
        $this->assertSame([true, false], [$j['admin'], $j['owner']]);
        [$status, , , $j] = $this->role(self::BOB, self::CAROL, 'admin');      // only the owner and the founders give Admin
        $this->assertSame([403, 'founder_only'], [$status, $j['error']]);
        $this->assertSame(0, $this->rows('hakoo_roles', 'discord_id = ?', [self::CAROL]));

        $this->role(self::ALICE, self::BOB, 'admin', false);
        [$status] = $this->state(self::BOB);
        $this->assertSame(403, $status);
    }

    /** TC-API-241 | FR-ADMIN-03: the badges; only members of the HAKOO server, no bots, known roles */
    public function test_badges_and_bad_requests()
    {
        $this->role(self::ALICE, self::CAROL, 'og');
        [, , , $j] = $this->role(self::ALICE, self::CAROL, 'tester');
        $this->assertSame([self::CAROL => ['', 'Member 0003', ['tester', 'og']]], $this->people($j));   // several roles, in the page's order
        [, , , $j] = $this->state(self::CAROL);
        $this->assertSame('not_admin', $j['error']);                         // a badge is not the admin role

        $this->assertSame(404, $this->role(self::ALICE, '100000000000000404', 'og')[0]);   // not on the server
        $this->assertSame(400, $this->role(self::ALICE, '100000000000000777', 'og')[0]);   // a bot
        $this->assertSame(400, $this->role(self::ALICE, self::BOB, 'owner')[0]);
        $this->assertSame(400, $this->role(self::ALICE, 'robert', 'og')[0]);
        $this->assertSame(0, $this->rows('hakoo_roles', 'discord_id <> ?', [self::CAROL]));
    }

    /** TC-API-242 | FR-ADMIN-04: an admin blocks and unblocks loot codes and approves translations; a member cannot */
    public function test_admin_runs_loot_codes_and_translations()
    {
        $code = ['game' => 'wot', 'code' => 'ADMINTEST1'];
        [$status] = $this->http('POST', 'codes.php?action=block', $code, $this->as(self::BOB));
        $this->assertSame(403, $status);
        $this->role(self::ALICE, self::BOB, 'admin');

        $this->http('POST', 'codes.php?action=report', $code, $this->as(self::CAROL));
        [$status, , , $j] = $this->http('POST', 'codes.php?action=block', $code, $this->as(self::BOB));
        $this->assertSame([200, true], [$status, $j['blocked']]);
        [, , , $j] = $this->state(self::BOB);
        $this->assertSame(['wot', 'ADMINTEST1', 'removed_by_admin'], [$j['loot']['blocked'][0]['game'], $j['loot']['blocked'][0]['code'], $j['loot']['blocked'][0]['reason']]);
        $this->assertSame(1, $j['loot']['reports'][0]['n']);
        [, , , $j] = $this->http('GET', 'codes.php?action=state', null, $this->as(self::BOB));
        $this->assertTrue($j['admin']);

        [$status] = $this->http('POST', 'codes.php?action=unblock', $code, $this->as(self::CAROL));
        $this->assertSame(403, $status);
        [$status, , , $j] = $this->http('POST', 'codes.php?action=unblock', $code, $this->as(self::BOB));
        $this->assertSame([200, false], [$status, $j['blocked']]);
        $this->assertSame([], json_decode(file_get_contents(self::BLOCKED), true)['blocked']);
        $this->assertSame(0, $this->rows('hakoo_code_reports', "code = 'ADMINTEST1'"));   // so it is not blocked again at once
        [$status, , , $j] = $this->http('POST', 'codes.php?action=unblock', $code, $this->as(self::BOB));
        $this->assertSame([404, 'not_blocked'], [$status, $j['error']]);

        [, , , $j] = $this->http('GET', 'texts-admin.php?action=state', null, $this->as(self::BOB));
        $this->assertTrue($j['me']['admin']);                                 // the translations too
    }

    /** TC-API-243 | FR-ADMIN-05: the statistics count games started (only numbers) and the members */
    public function test_statistics_count_games_and_members()
    {
        $this->http('POST', 'gomoku.php?action=new', ['mode' => 'computer', 'color' => 'black', 'difficulty' => 'easy']);
        $this->http('POST', 'gomoku.php?action=new', ['mode' => 'friend', 'color' => 'black']);
        $this->http('POST', 'chess.php?action=new', ['mode' => 'computer', 'color' => 'white', 'difficulty' => 'easy']);
        $this->http('GET', 'nonogram.php?action=puzzle&level=easy');                       // a new practice puzzle
        $this->http('GET', 'nonogram.php?action=puzzle&level=easy&seed=7');                // a reload: not counted again
        $this->pdo->exec("INSERT INTO hakoo_play_stats (day, what, how, n) VALUES (CURDATE() - INTERVAL 40 DAY, 'gomoku', 'computer', 5), (CURDATE(), 'login', '', 3)");

        [, , , $j] = $this->state(self::ALICE);
        $games = array_column($j['stats']['games'], null, 'game');
        $this->assertSame(['d7' => 2, 'd30' => 2, 'total' => 7, 'how' => ['computer' => 6, 'friend' => 1]],
            array_intersect_key($games['gomoku'], array_flip(['d7', 'd30', 'total', 'how'])));
        $this->assertSame(1, $games['chess']['d7']);
        $this->assertSame(['practice' => 1], $games['nonogram']['how']);
        $this->assertSame('gomoku', $j['stats']['games'][0]['game']);           // most played first
        $this->assertCount(30, $j['stats']['days']);
        $this->assertSame(['day' => date('Y-m-d'), 'logins' => 3], end($j['stats']['days']));
        $this->assertSame(3, $j['stats']['members']['total']);                  // Alice, Bob, Carol
        // No member id anywhere in the counts.
        $this->assertSame(['day', 'what', 'how', 'n'], array_keys($this->pdo->query('SELECT * FROM hakoo_play_stats LIMIT 1')->fetch(PDO::FETCH_ASSOC)));
    }

    /** TC-API-245 | FR-ADMIN-07: the Tester badge gives and takes the Discord role "Hakoo testers"; the other roles do not touch Discord */
    public function test_tester_gets_the_discord_role()
    {
        [, , , $j] = $this->role(self::ALICE, self::BOB, 'tester');
        $this->assertSame('given', $j['discord']);
        $this->role(self::ALICE, self::BOB, 'og');
        $this->role(self::ALICE, self::BOB, 'og', false);
        [, , , $j] = $this->role(self::ALICE, self::BOB, 'tester', false);
        $this->assertSame('taken', $j['discord']);
        [, , , $j] = $this->role(self::ALICE, self::BOB, 'tester', false);      // had none: Discord is not asked
        $this->assertNull($j['discord']);
        $this->assertSame(['PUT ' . self::BOB, 'DELETE ' . self::BOB], $this->discordRoles());
    }

    /** TC-API-282 | FR-ADMIN-09, FR-RANK-01, FR-RANK-02: an admin gives every role but Admin, and sets a member's rank; the member picks which of the two ranks; others cannot */
    public function test_admins_give_roles_and_ranks()
    {
        $this->role(self::ALICE, self::BOB, 'admin');
        [$status, , , $j] = $this->role(self::BOB, self::CAROL, 'og');               // an admin gives OG HAKOO
        $this->assertSame(200, $status);
        $this->assertSame(1, $this->rows('hakoo_roles', "discord_id = ? AND role = 'og' AND granted_by = ?", [self::CAROL, self::BOB]));
        [$status] = $this->role(self::BOB, self::CAROL, 'og', false);
        $this->assertSame([200, 0], [$status, $this->rows('hakoo_roles', 'discord_id = ?', [self::CAROL])]);

        $tier = fn($who, $id, $t) => $this->http('POST', 'admin.php?action=tier', ['discord_id' => $id, 'tier' => $t], $this->as($who));
        [$status, , , $j] = $tier(self::BOB, self::CAROL, 'vip');
        $this->assertSame([200, 'vip', 'jarl'], [$status, $j['tier'], $j['rank']]);
        $this->assertSame([400, 'bad_tier'], [$tier(self::BOB, self::CAROL, 'gold')[0], $tier(self::BOB, self::CAROL, 'gold')[3]['error']]);
        $this->assertSame(404, $tier(self::BOB, self::DAVE, 'paying')[0]);              // has never logged in
        [$status, , , $j] = $tier(self::CAROL, self::BOB, 'vip');                       // not an admin
        $this->assertSame([403, 'not_admin'], [$status, $j['error']]);

        $tier(self::ALICE, self::CAROL, 'paying');
        $carol = $this->as(self::CAROL);
        [, , , $j] = $this->http('GET', 'members.php?action=settings', null, $carol);
        $this->assertSame(['paying', 0, 'viking', [[3, 'viking'], [4, 'shieldmaiden']]], [$j['tier'], $j['rankAlt'], $j['rank'], $j['choices']]);
        $pick = fn($n) => $this->http('POST', 'members.php?action=settings', ['visible' => false, 'rankAlt' => $n], $carol)[3];
        $this->assertSame([4, 'shieldmaiden'], [$pick(4)['rankAlt'], $pick(4)['rank']]);
        $this->assertSame('shieldmaiden', $pick(5)['rank']);                          // Jarl is for VIPs: refused
        $tier(self::ALICE, self::CAROL, 'free');
        $j = $pick(1);
        $this->assertSame(['soldier', [[0, ''], [1, 'soldier'], [2, 'farmer']]], [$j['rank'], $j['choices']]);
        $this->assertSame('farmer', $pick(2)['rank']);
        $this->assertSame('', $pick(0)['rank']);                                      // free, and nothing by her name
        $pick(1);
        [, , , $x] = $this->http('GET', 'mydata.php?action=export', null, $carol);
        $this->assertSame(['free', 1], [$x['account']['tier'], (int)$x['account']['rank_alt']]);
    }

    /** TC-API-283 | FR-RANK-04: an OG HAKOO picks any rank; a founder also Queen (and only the owner makes a founder); the owner shows as the King */
    public function test_og_and_founder_ranks()
    {
        $carol = $this->as(self::CAROL);
        $pick = fn($n) => $this->http('POST', 'members.php?action=settings', ['visible' => false, 'rankAlt' => $n], $carol)[3];
        $this->assertSame('', $pick(5)['rank']);                                      // a free member: no Jarl
        $this->role(self::ALICE, self::CAROL, 'og');
        $j = $pick(5);
        $this->assertSame(['jarl', [0, 1, 2, 3, 4, 5]], [$j['rank'], array_column($j['choices'], 0)]);
        $this->assertSame('jarl', $pick(6)['rank']);                                  // no Queen yet: the pick stays Jarl
        $this->role(self::ALICE, self::BOB, 'admin');
        [$status, , , $e] = $this->role(self::BOB, self::CAROL, 'founder');           // an admin does not make founders
        $this->assertSame([403, 'owner_only'], [$status, $e['error']]);
        $this->role(self::ALICE, self::CAROL, 'founder');
        $j = $pick(6);
        $this->assertSame(['queen', 6], [$j['rank'], end($j['choices'])[0]]);
        $this->pdo->exec("UPDATE hakoo_users SET players_hidden = 0 WHERE discord_id = " . self::CAROL);   // in the list, and she has played
        $this->pdo->exec("INSERT INTO hakoo_sudoku_daily (discord_id, day, level, name, seconds, solved_at) VALUES (" . self::CAROL . ", CURDATE(), 'easy', 'Carol', 60, NOW())");
        [, , , $l] = $this->http('GET', 'members.php?action=players', null, $this->as(self::ALICE, 'Alice'));
        $p = array_column($l['players'], null, 'id');
        $this->assertSame(['queen', 'queen', ['og', 'founder']], [$p[self::CAROL]['group'], $p[self::CAROL]['rank'], $p[self::CAROL]['badges']]);
        $this->assertSame([['owner', 'King'], ['queen', 'Queen']], array_slice(array_map(fn($g) => [$g['id'], $g['name']], $l['groups']), 0, 2));
        $this->role(self::ALICE, self::CAROL, 'founder', false);                       // no longer a founder
        [, , , $j] = $this->http('GET', 'members.php?action=settings', null, $carol);
        $this->assertSame('', $j['rank']);                                            // her pick (Queen) is not hers now: the first choice
    }

    /** TC-API-285 | FR-ADMIN-10: a founder has the admin page and gives and takes Admin; only the owner makes founders; an admin does not give Admin */
    public function test_founder_gives_and_takes_the_admin_role()
    {
        $this->role(self::ALICE, self::CAROL, 'founder');                               // Carol: founder, not admin
        [$status, , , $j] = $this->state(self::CAROL);
        $this->assertSame([200, false, true], [$status, $j['me']['owner'], $j['me']['founder']]);
        [, , , $j] = $this->http('GET', 'admin.php?action=me', null, $this->as(self::CAROL));
        $this->assertSame([true, false, true], [$j['admin'], $j['owner'], $j['founder']]);
        [, , , $l] = $this->http('GET', 'members.php?action=players', null, $this->as(self::CAROL));
        $this->assertSame(['admin' => true, 'owner' => false, 'founder' => true], $l['viewer']);   // the right-click menu

        [$status, , , $j] = $this->role(self::CAROL, self::BOB, 'admin');               // a founder gives Admin
        $this->assertSame(200, $status);
        $this->assertSame(1, $this->rows('hakoo_roles', "discord_id = ? AND role = 'admin' AND granted_by = ?", [self::BOB, self::CAROL]));
        [$status, , , $j] = $this->role(self::BOB, self::DAVE, 'admin');                 // an admin does not
        $this->assertSame([403, 'founder_only'], [$status, $j['error']]);
        [$status, , , $j] = $this->role(self::CAROL, self::BOB, 'founder');             // only the owner makes founders
        $this->assertSame([403, 'owner_only'], [$status, $j['error']]);
        [$status] = $this->role(self::CAROL, self::BOB, 'admin', false);                 // and a founder takes Admin
        $this->assertSame([200, 0], [$status, $this->rows('hakoo_roles', "discord_id = ? AND role = 'admin'", [self::BOB])]);

        $this->role(self::ALICE, self::CAROL, 'founder', false);                         // no longer a founder: no page
        [$status, , , $j] = $this->state(self::CAROL);
        $this->assertSame([403, 'not_admin'], [$status, $j['error']]);
    }

    /** TC-API-246 | FR-ADMIN-08: the owner gives a member a name of their own (it stays when the Discord name changes); '' removes it */
    public function test_owner_names_a_member()
    {
        $this->role(self::ALICE, self::BOB, 'admin');
        $name = fn($who, $n) => $this->http('POST', 'admin.php?action=name', ['discord_id' => self::BOB, 'name' => $n], $this->as($who));
        [$status, , , $j] = $name(self::ALICE, '  Bobby <the> *Tank*  ');
        $this->assertSame(200, $status);
        $this->assertSame([self::BOB => ['Bobby the Tank', 'Member 0002', ['admin']]], $this->people($j));   // plain text, one line
        [$status, , , $j] = $name(self::BOB, 'Boss');                           // an admin names nobody
        $this->assertSame([403, 'owner_only'], [$status, $j['error']]);
        [, , , $j] = $this->http('POST', 'admin.php?action=name', ['discord_id' => self::CAROL, 'name' => 'Caz'], $this->as(self::ALICE));
        $this->assertSame(['Caz', '', []], $this->people($j)[self::CAROL]);     // a name without a role is listed too
        [, , , $j] = $name(self::ALICE, '');
        $this->assertSame('', $this->people($j)[self::BOB][0]);
        $this->assertSame(400, $this->http('POST', 'admin.php?action=name', ['discord_id' => 'x', 'name' => 'A'], $this->as(self::ALICE))[0]);
    }

    /** TC-API-244 | FR-ADMIN-06, REG-GDPR-15, REG-GDPR-17: a role and a name are in the member's data download and goes when they delete their data */
    public function test_roles_in_the_export_and_deleted_with_the_member()
    {
        $this->role(self::ALICE, self::BOB, 'admin');
        $this->role(self::ALICE, self::BOB, 'og');
        [, , , $j] = $this->http('GET', 'mydata.php?action=export', null, $this->as(self::BOB));
        $this->assertSame(['admin', 'og'], array_column($j['games_and_settings']['hakoo_roles'], 'role'));
        $this->assertArrayNotHasKey('granted_by', $j['games_and_settings']['hakoo_roles'][0]);   // who gave it is the owner's id
        $this->http('POST', 'admin.php?action=name', ['discord_id' => self::BOB, 'name' => 'Bobby'], $this->as(self::ALICE));
        [, , , $j] = $this->http('GET', 'mydata.php?action=export', null, $this->as(self::BOB));
        $this->assertSame('Bobby', $j['games_and_settings']['hakoo_member_names'][0]['name']);

        $this->http('POST', 'mydata.php?action=delete', ['confirm' => 'DELETE'], $this->as(self::ALICE));
        $this->assertSame(2, $this->rows('hakoo_roles', 'discord_id = ? AND granted_by IS NULL', [self::BOB]));   // the giver's id is gone
        $this->assertSame(1, $this->rows('hakoo_member_names', 'discord_id = ? AND updated_by IS NULL', [self::BOB]));
        $this->http('POST', 'mydata.php?action=delete', ['confirm' => 'DELETE'], $this->as(self::BOB));
        $this->assertSame(0, $this->rows('hakoo_roles', '1'));
        $this->assertSame(0, $this->rows('hakoo_member_names', '1'));
    }
}
