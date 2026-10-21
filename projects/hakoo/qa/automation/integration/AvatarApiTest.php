<?php
/**
 * System tests of the member's viking (src/avatar.php, hakoo-avatar.inc.php): what is free, what unlocks what
 * (Runborg titles, games won, the puzzle streak, the roles), that a locked item cannot be worn, the login corner,
 * and where the viking shows. Fixture: Bob has three Runborg titles (Farmer, Viking, Shieldmaiden), Easy wins in
 * many games and a saved viking with an axe; Alice has won on every level; Carol has done nothing yet.
 * Traceability: FR-AVATAR-01..13, REG-GDPR-15, REG-GDPR-17.
 */
class AvatarApiTest extends HakooTestCase
{
    private function me($who, $name = 'Tester')
    {
        return $this->http('GET', 'avatar.php?action=me', null, $this->sessionCookie($who, $name))[3];
    }
    private function save($who, array $cfg, bool $corner = false)
    {
        return $this->http('POST', 'avatar.php?action=save', ['cfg' => $cfg, 'corner' => $corner], $this->sessionCookie($who))[3];
    }

    /** TC-API-266 | FR-AVATAR-01: logged out there is no viking to change; the login corner asks without an error */
    public function test_logged_out()
    {
        [$status, , , $j] = $this->http('GET', 'avatar.php?action=me');
        $this->assertSame([401, 'not_logged_in'], [$status, $j['error']]);
        [$status, , , $j] = $this->http('GET', 'avatar.php?action=corner');
        $this->assertSame([200, null], [$status, $j['cfg']]);
    }

    /** TC-API-267 | FR-AVATAR-02, FR-AVATAR-03: what each member has unlocked follows from what they have done */
    public function test_unlocked_by_what_was_done()
    {
        $this->assertSame([], $this->me(self::CAROL)['unlocked']);
        $bob = $this->me(self::BOB);
        // Three titles (the Farmer gives two things), Easy in 5+ games, and the Shieldmaiden beaten at Winter Dusk
        // and a colour for each game won on Easy
        $this->assertSame(['head:straw', 'right:pitchfork', 'right:axe', 'left:shield', 'colour:purple', 'bg:dusk',
                           'colour:gold', 'colour:ivory', 'colour:teal', 'colour:crimson', 'colour:orange', 'colour:navy', 'colour:black', 'colour:olive', 'colour:pink', 'colour:sky', 'colour:lilac'], $bob['unlocked']);
        $this->assertSame(['farmer', 'viking', 'shieldmaiden'], $bob['facts']['titles']);
        $this->assertSame(['dusk'], $bob['facts']['boards']);
        $this->assertTrue($bob['saved']);
        $this->assertSame(['axe', 'red'], [$bob['cfg']['right'], $bob['cfg']['hair']]);
        $alice = $this->me(self::ALICE);
        $this->assertSame(['colour:purple', 'pattern', 'trim', 'shield:buckler', 'shield:wall', 'shieldrim:silver', 'shieldrim:gold',
                           'shieldpattern:runering', 'shieldpattern:valknut', 'shieldpattern:tree', 'neck:stones', 'neck:knight', 'back:cape', 'neck:kingpiece',
                           'back:flag', 'waist:dice', 'neck:scarf', 'waist:mill', 'colour:gold', 'colour:ivory', 'colour:teal', 'colour:crimson', 'colour:orange', 'colour:navy', 'colour:black', 'colour:olive', 'colour:pink', 'colour:sky', 'colour:lilac'], $alice['unlocked']);   // every level of every game
        $this->assertSame(['gomoku', 'chess', 'othello', 'hnefatafl', 'stratego', 'backgammon', 'checkers', 'morris'], $alice['facts']['hard_games']);
        // The roles and the streak
        $this->pdo->exec("INSERT INTO hakoo_roles (discord_id, role) VALUES (" . self::CAROL . ", 'tester'), (" . self::CAROL . ", 'og')");
        for ($d = 0; $d < 7; $d++) {
            $this->pdo->prepare("INSERT INTO hakoo_nonogram_daily (discord_id, day, level, name, seconds, solved_at) VALUES (?, CURDATE() - INTERVAL ? DAY, 'easy', 'Carol', 60, NOW())")
                ->execute([self::CAROL, $d]);
        }
        $carol = $this->me(self::CAROL);
        $this->assertSame(7, $carol['facts']['streak']);
        $this->assertSame(['left:lantern', 'right:hammer', 'neck:owl', 'shield:buckler'], $carol['unlocked']);
    }

    /** TC-API-268 | FR-AVATAR-02, FR-AVATAR-04: the free choices are kept; a locked item, or something made up, is set back */
    public function test_save_keeps_only_what_is_allowed()
    {
        $j = $this->save(self::CAROL, ['body' => 'woman', 'skin' => 'dark', 'hair' => 'black', 'clothes' => 'green', 'head' => 'crown',
                                       'right' => 'axe', 'left' => 'shield', 'pattern' => true, 'shoulder' => 'dragon']);
        $this->assertSame(['woman', 'dark', 'black', 'green'], [$j['cfg']['body'], $j['cfg']['skin'], $j['cfg']['hair'], $j['cfg']['clothes']]);
        $this->assertSame(['horns', 'none', 'none', 'none', 'none'], [$j['cfg']['head'], $j['cfg']['right'], $j['cfg']['left'], $j['cfg']['pattern'], $j['cfg']['shoulder']]);
        $j = $this->save(self::BOB, ['body' => 'man', 'head' => 'straw', 'left' => 'shield', 'clothes' => 'purple', 'right' => 'hammer']);
        $this->assertSame(['straw', 'shield', 'purple', 'none'], [$j['cfg']['head'], $j['cfg']['left'], $j['cfg']['clothes'], $j['cfg']['right']]);
        $this->assertSame(404, $this->http('GET', 'avatar.php?action=save', null, $this->sessionCookie(self::BOB))[0]);   // saving needs a POST
    }

    /** TC-API-271 | FR-AVATAR-07: winning a board game on Hard unlocks its own thing; it can be worn, the others not */
    public function test_hard_items()
    {
        $this->pdo->exec('INSERT INTO hakoo_gomoku_profile (discord_id, won_easy, won_hard) VALUES (' . self::CAROL . ', NOW(), NOW())');
        $this->pdo->exec('INSERT INTO hakoo_morris_profile (discord_id, won_hard) VALUES (' . self::CAROL . ', NOW())');
        $carol = $this->me(self::CAROL);
        $this->assertSame(['trim', 'shieldrim:gold', 'neck:stones', 'waist:mill', 'colour:gold'], $carol['unlocked']);   // Gomoku on Easy too: gold; Hard: the gold rim
        $j = $this->save(self::CAROL, ['neck' => 'stones', 'waist' => 'mill', 'back' => 'cape']);
        $this->assertSame(['stones', 'mill', 'none'], [$j['cfg']['neck'], $j['cfg']['waist'], $j['cfg']['back']]);
        $j = $this->save(self::BOB, ['waist' => 'dice', 'neck' => 'knight']);                 // Bob has won on Easy only
        $this->assertSame(['none', 'none'], [$j['cfg']['waist'], $j['cfg']['neck']]);
    }

    /** TC-API-279 | FR-AVATAR-09, FR-AVATAR-10: trousers, a skirt, a long tunic or a dress, their colour and the feet are free; a board's sky is won on that board */
    public function test_legs_feet_and_backgrounds()
    {
        $j = $this->save(self::CAROL, ['legs' => 'dress', 'legcolor' => 'red', 'feet' => 'barefoot', 'bg' => 'fjord']);
        $this->assertSame(['dress', 'red', 'barefoot', 'site'], [$j['cfg']['legs'], $j['cfg']['legcolor'], $j['cfg']['feet'], $j['cfg']['bg']]);
        $j = $this->save(self::CAROL, ['legs' => 'kilt', 'legcolor' => 'gold', 'feet' => 'skates']);
        $this->assertSame(['trousers', 'grey', 'boots'], [$j['cfg']['legs'], $j['cfg']['legcolor'], $j['cfg']['feet']]);
        $j = $this->save(self::BOB, ['bg' => 'dusk', 'right' => 'pitchfork']);             // he beat the Shieldmaiden at Winter Dusk
        $this->assertSame(['dusk', 'pitchfork'], [$j['cfg']['bg'], $j['cfg']['right']]);
        $this->assertSame('site', $this->save(self::BOB, ['bg' => 'winter'])['cfg']['bg']);   // only the Viking beaten there
        $this->assertSame(['trousers', 'boots', 'site'], [$this->me(self::ALICE)['cfg']['legs'], $this->me(self::ALICE)['cfg']['feet'], $this->me(self::ALICE)['cfg']['bg']]);
    }

    /** TC-API-281 | FR-AVATAR-12: a colour won in a game can be given to the clothes, the legs or the pattern; a pattern is one of a list */
    public function test_won_colours_and_patterns()
    {
        $j = $this->save(self::BOB, ['clothes' => 'teal', 'legcolor' => 'gold', 'patterncolor' => 'pink', 'pattern' => 'runes']);
        $this->assertSame(['teal', 'gold', 'pink', 'none'], [$j['cfg']['clothes'], $j['cfg']['legcolor'], $j['cfg']['patterncolor'], $j['cfg']['pattern']]);   // no rune pattern won
        $j = $this->save(self::CAROL, ['clothes' => 'teal', 'legcolor' => 'purple', 'patterncolor' => 'gold']);   // nothing won
        $this->assertSame(['blue', 'grey', 'white'], [$j['cfg']['clothes'], $j['cfg']['legcolor'], $j['cfg']['patterncolor']]);
        $j = $this->save(self::ALICE, ['pattern' => 'runes', 'patterncolor' => 'gold']);
        $this->assertSame(['runes', 'gold'], [$j['cfg']['pattern'], $j['cfg']['patterncolor']]);
        $this->assertSame('runes', $this->save(self::ALICE, ['pattern' => true])['cfg']['pattern']);    // saved before there were more
        $this->assertSame('none', $this->save(self::ALICE, ['pattern' => 'tartan'])['cfg']['pattern']);
        $this->pdo->prepare('UPDATE hakoo_avatar SET cfg = ? WHERE discord_id = ?')
            ->execute(['{"clothes":"purple","pattern":true}', self::BOB]);                          // an old save
        [, , , $c] = $this->http('GET', 'members.php?action=card&id=' . self::BOB, null, $this->sessionCookie(self::CAROL, 'Carol'));
        $this->assertSame(['purple', 'runes'], [$c['card']['viking']['clothes'], $c['card']['viking']['pattern']]);
    }

    /** TC-API-294 | FR-AVATAR-13: the shield's shape, colour, pattern, pattern colour and rim; what is not won is set back */
    public function test_the_shield()
    {
        $shield = ['shield' => 'wall', 'shieldcolor' => 'crimson', 'shieldpattern' => 'valknut', 'shieldpatterncolor' => 'gold', 'shieldrim' => 'gold'];
        $pick = fn($cfg) => array_intersect_key($cfg, $shield);
        // Alice: every board game on Medium (the shield-wall shield, the Valknut), Hard (gold), all the colours
        $this->assertSame($shield, $pick($this->save(self::ALICE, $shield)['cfg']));
        $u = $this->me(self::ALICE)['unlocked'];
        foreach (['shield:wall', 'shield:buckler', 'shieldpattern:valknut', 'shieldpattern:runering', 'shieldpattern:tree', 'shieldrim:silver', 'shieldrim:gold'] as $item) {
            $this->assertContains($item, $u);
        }
        $this->assertNotContains('shield:kite', $u, 'the Jarl not beaten');
        $this->assertSame('round', $this->save(self::ALICE, ['shield' => 'kite'] + $shield)['cfg']['shield']);
        // Carol has won nothing: the free choices only
        $this->assertSame(['shield' => 'round', 'shieldcolor' => 'clothes', 'shieldpattern' => 'cross', 'shieldpatterncolor' => 'white', 'shieldrim' => 'wood'],
            $pick($this->save(self::CAROL, $shield)['cfg']));
        $free = ['shield' => 'round', 'shieldcolor' => 'green', 'shieldpattern' => 'quarters', 'shieldpatterncolor' => 'black', 'shieldrim' => 'iron'];
        $this->assertSame($free, $pick($this->save(self::CAROL, $free)['cfg']));
        $this->assertSame('cross', $this->save(self::CAROL, ['shieldpattern' => 'tartan'])['cfg']['shieldpattern']);
        // A viking saved before there were shield choices gets the shield it had: round, the clothes' colour, a cross
        $this->pdo->prepare('UPDATE hakoo_avatar SET cfg = ? WHERE discord_id = ?')->execute(['{"left":"shield","clothes":"red"}', self::BOB]);
        [, , , $c] = $this->http('GET', 'members.php?action=card&id=' . self::BOB, null, $this->sessionCookie(self::CAROL, 'Carol'));
        $this->assertSame(['round', 'clothes', 'cross', 'wood'], [$c['card']['viking']['shield'], $c['card']['viking']['shieldcolor'],
            $c['card']['viking']['shieldpattern'], $c['card']['viking']['shieldrim']]);
    }

    /** TC-API-272 | FR-AVATAR-08: how tall and how strongly built: 0-100, kept as numbers; anything else is the middle */
    public function test_height_and_build()
    {
        $j = $this->save(self::CAROL, ['height' => 80, 'build' => '25']);
        $this->assertSame([80, 25], [$j['cfg']['height'], $j['cfg']['build']]);
        $j = $this->save(self::CAROL, ['height' => 400, 'build' => -9]);
        $this->assertSame([100, 0], [$j['cfg']['height'], $j['cfg']['build']]);
        $j = $this->save(self::CAROL, ['height' => 'tall']);
        $this->assertSame([50, 50], [$j['cfg']['height'], $j['cfg']['build']]);
        $this->assertSame(50, $this->me(self::BOB)['cfg']['height']);                       // saved before there were sizes
    }

    /** TC-API-269 | FR-AVATAR-05: the viking in the login corner (when chosen), on the members' profiles and at the Runborg table */
    public function test_where_it_shows()
    {
        [, , , $j] = $this->http('GET', 'avatar.php?action=corner', null, $this->sessionCookie(self::BOB, 'Bob'));
        $this->assertSame([self::BOB, 'axe'], [$j['id'], $j['cfg']['right']]);
        $this->save(self::CAROL, ['body' => 'woman', 'hair' => 'red'], false);
        [, , , $j] = $this->http('GET', 'avatar.php?action=corner', null, $this->sessionCookie(self::CAROL));
        $this->assertNull($j['cfg']);                                           // not chosen for the corner
        [, , , $m] = $this->http('GET', 'members.php?action=profile&id=' . self::BOB, null, $this->sessionCookie(self::CAROL, 'Carol'));
        $this->assertSame('axe', $m['profile']['viking']['right']);
        [, , , $l] = $this->http('GET', 'members.php?action=list', null, $this->sessionCookie(self::ALICE, 'Alice'));
        $this->assertSame([null, 'axe', 'woman'], array_map(fn($x) => $x['viking'] ? ($x['viking']['right'] !== 'none' ? $x['viking']['right'] : $x['viking']['body']) : null, $l['members']));
        [, , , $r] = $this->http('GET', 'runborg.php?action=me', null, $this->sessionCookie(self::BOB, 'Bob'));
        $this->assertSame('red', $r['viking']['hair']);
    }

    /** TC-API-270 | FR-AVATAR-06, REG-GDPR-15, REG-GDPR-17: the viking is in the member's data and goes with it */
    public function test_in_the_data()
    {
        [, , , $x] = $this->http('GET', 'mydata.php?action=export', null, $this->sessionCookie(self::BOB, 'Bob'));
        $this->assertSame(1, (int)$x['games_and_settings']['hakoo_avatar'][0]['corner']);
        $this->http('POST', 'mydata.php?action=delete', ['confirm' => 'DELETE'], $this->sessionCookie(self::BOB, 'Bob'));
        $this->assertSame(0, $this->rows('hakoo_avatar', 'discord_id = ?', [self::BOB]));
    }
}
