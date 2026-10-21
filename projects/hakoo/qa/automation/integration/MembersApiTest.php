<?php
/**
 * System tests of src/members.php: who sees whose profile, what a profile holds, the member's own choices,
 * and the profile in the member's data. Fixture: Bob shows his profile with his linked accounts; Alice (the owner,
 * an admin) and Carol keep theirs hidden.
 * Traceability: FR-PROFILE-01..06, REG-GDPR-15, REG-GDPR-17.
 */
class MembersApiTest extends HakooTestCase
{
    private function get(string $query, $who, string $name = 'Tester')
    {
        return $this->http('GET', "members.php?$query", null, $who ? $this->sessionCookie($who, $name) : null);
    }

    /** TC-API-258 | FR-PROFILE-01, FR-PROFILE-02: logged out gets nothing; a member sees the shown profiles and their own; an admin everyone, with the last login */
    public function test_who_sees_the_list()
    {
        [$status, , , $j] = $this->get('action=list', null);
        $this->assertSame([401, 'not_logged_in'], [$status, $j['error']]);
        $this->assertArrayNotHasKey('members', $j);

        [$status, , , $j] = $this->get('action=list', self::CAROL, 'Carol');
        $this->assertSame([200, false], [$status, $j['admin']]);
        $this->assertSame(['Bob', 'Carol'], array_column($j['members'], 'name'));         // Alice hides hers
        $this->assertArrayNotHasKey('lastLogin', $j['members'][0]);
        $this->assertSame([false, true], array_column($j['members'], 'own'));

        [, , , $j] = $this->get('action=list', self::ALICE, 'Alice');
        $this->assertTrue($j['admin']);
        $this->assertSame(['Alice', 'Bob', 'Carol'], array_column($j['members'], 'name'));
        $this->assertSame([false, true, false], array_column($j['members'], 'visible'));
        $this->assertSame('2026-09-21 19:30:00', $j['members'][1]['lastLogin']);
    }

    /** TC-API-259 | FR-PROFILE-03, FR-PROFILE-04: a shown profile holds the games, Runborg and the accounts the member shows; a hidden one is private, except for an admin */
    public function test_a_profile()
    {
        [$status, , , $j] = $this->get('action=profile&id=' . self::BOB, self::CAROL, 'Carol');
        $this->assertSame(200, $status);
        $p = $j['profile'];
        $this->assertSame(['Bob', true, false], [$p['name'], $p['visible'], $p['own']]);
        $this->assertSame(['easy' => true, 'medium' => false, 'hard' => false], array_column($p['games'], 'won', 'key')['gomoku']);
        $this->assertSame(['farmer', 'viking', 'shieldmaiden'], $p['runborg']['titles']);
        $this->assertSame(['viking'], $p['runborg']['wins']['winter']);
        $this->assertSame([['kind' => 'World of Tanks', 'name' => 'TestTanker_Bob'], ['kind' => 'World of Warships', 'name' => 'BobSails']], $p['accounts']);
        $this->assertArrayNotHasKey('lastLogin', $p);

        [$status, , , $e] = $this->get('action=profile&id=' . self::ALICE, self::CAROL, 'Carol');
        $this->assertSame([403, 'private'], [$status, $e['error']]);
        $this->assertArrayNotHasKey('profile', $e);

        [$status, , , $j] = $this->get('action=profile&id=' . self::CAROL, self::ALICE, 'Alice');       // an admin: a hidden one too
        $this->assertSame([200, false, '2026-09-01 09:00:00'], [$status, $j['profile']['visible'], $j['profile']['lastLogin']]);
        [, , , $j] = $this->get('action=profile&id=' . self::ALICE, self::ALICE, 'Alice');
        $this->assertTrue($j['profile']['own']);
        $this->assertSame([], $j['profile']['accounts']);                              // Alice does not show hers
        $this->assertSame(404, $this->get('action=profile&id=' . self::DAVE, self::CAROL)[0]);
    }

    /** TC-API-260 | FR-PROFILE-05: the member's own choices: shown or not, and the accounts with it */
    public function test_own_choices()
    {
        $carol = $this->sessionCookie(self::CAROL, 'Carol');
        [, , , $j] = $this->http('GET', 'members.php?action=settings', null, $carol);
        $this->assertSame([false, false], [$j['visible'], $j['showAccounts']]);
        [, , , $j] = $this->http('POST', 'members.php?action=settings', ['visible' => true, 'showAccounts' => false], $carol);
        $this->assertSame([true, false], [$j['visible'], $j['showAccounts']]);
        [, , , $j] = $this->get('action=profile&id=' . self::CAROL, self::BOB, 'Bob');
        $this->assertSame('Carol', $j['profile']['name']);
        $this->http('POST', 'members.php?action=settings', ['visible' => false], $carol);
        $this->assertSame(403, $this->get('action=profile&id=' . self::CAROL, self::BOB, 'Bob')[0]);
    }

    /** TC-API-261 | FR-PROFILE-06, REG-GDPR-15, REG-GDPR-17: the Discord name is kept from the session; the profile choices are in the member's data and go with it */
    public function test_name_and_data()
    {
        $this->get('action=list', self::CAROL, 'Caz');                                // she changed her Discord name
        $this->assertSame(1, $this->rows('hakoo_users', "discord_id = ? AND name = 'Caz'", [self::CAROL]));
        [, , , $x] = $this->http('GET', 'mydata.php?action=export', null, $this->sessionCookie(self::BOB, 'Bob'));
        $this->assertSame(['Bob', 1, 1], [$x['account']['name'], (int)$x['account']['profile_visible'], (int)$x['account']['show_accounts']]);
        $this->assertArrayNotHasKey('discord_id', $x['account']);
        $this->http('POST', 'mydata.php?action=delete', ['confirm' => 'DELETE'], $this->sessionCookie(self::BOB, 'Bob'));
        [, , , $j] = $this->get('action=list', self::CAROL, 'Carol');
        $this->assertSame(['Carol'], array_column($j['members'], 'name'));
    }
}
