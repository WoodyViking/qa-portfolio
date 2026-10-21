<?php
// The role-playing engine (component level, no database), modules/rpg.inc.php: the party rules, the dice, knocked out
// heroes, votes, what each device sees, the game master, a player who leaves, the pause, runes and buttons, the options
// of a fight turn and the end of an adventure. The heroes here are never saved, so nothing touches the database.
// Traceability: US-20, US-22, K-89..K-93, K-97..K-107, AK-71..AK-73, AK-75..AK-81.
use PHPUnit\Framework\TestCase;

require_once '/srv/mysteries/modules/rpg.inc.php';

final class RpgEngineTest extends TestCase
{
    private function rnd(int $seed = 1): Random\Randomizer
    {
        return new Random\Randomizer(new Random\Engine\Mt19937($seed));
    }

    private function players(int $n): array
    {
        return array_map(fn($i) => ['id' => "m$i", 'name' => "P$i"], range(0, $n - 1));
    }

    private function lobby(array $classes, array $owners): array
    {
        $l = [];
        foreach ($classes as $k => $c) $l[] = ['p' => "m{$owners[$k]}", 'name' => "H$k", 'cls' => $c, 'folk' => 'dwarf', 'attr' => null, 'level' => 1, 'items' => [], 'saved' => null];
        return $l;
    }

    /** A started game in the village (the road vote), the host m0 plays unless $master. */
    private function game(array $classes, array $owners, bool $master = false, int $seed = 1): array
    {
        $host = $master ? 'm9' : 'm0';
        return my_rpg_begin('stolen-hammer', 60, $this->players(max($owners) + 1), $this->lobby($classes, $owners), $host, $master, $this->rnd($seed), 0.0);
    }

    /** The same game in the wolves' fight of the pass. */
    private function fight(array $classes, array $owners, bool $master = false, int $seed = 1): array
    {
        $pub = $this->game($classes, $owners, $master, $seed);
        $pub['scene'] = 'pass';
        $pub['step'] = 1;
        return my_rpg_fight_begin($pub, my_rpg_scene(my_rpg_get('stolen-hammer'), 'pass')['steps'][1], $this->rnd($seed), 0.0);
    }

    /** Make it hero $hi's turn. */
    private function turnOf(array $pub, int $hi): array
    {
        $pub['fight']['at'] = array_search(['h', $hi], $pub['fight']['order'], true);
        $pub['fight']['react'] = null;
        return $pub;
    }

    private function keys(array $a): array
    {
        $k = [];
        $walk = function ($x) use (&$walk, &$k) { foreach ($x as $key => $v) { $k[] = $key; if (is_array($v)) $walk($v); } };
        $walk($a);
        return $k;
    }

    /** TC-INT-047 (K-90, AK-71): 4-6 heroes, the first four of different classes, at most two a player, every player a hero */
    public function testPartyRules(): void
    {
        $lobby = fn($classes, $owners) => ['adv' => 'stolen-hammer', 'len' => 60, 'heroes' => $this->lobby($classes, $owners)];
        $three = ['m0', 'm1', 'm2'];
        $pub = $lobby(['farmer', 'farmer', 'viking', 'volva'], [0, 1, 2, 2]);
        $this->assertSame([['k' => 'rpg.party_same', 'classes' => ['farmer']]], my_rpg_party_problems($pub, $three), 'two farmers among the first four');
        $this->assertSame(['error' => 'rpg_party'], my_rpg_start($this->players(3), $pub, ['length_min' => 60, 'host_id' => 'm0', 'host_plays' => 1]));
        $this->assertSame(['err', 'rpg_two'], my_rpg_choose($pub, 'm2', 'P2', ['do' => 'new', 'cls' => 'skald', 'folk' => 'norse', 'name' => 'Ylva']), 'a third hero');
        $pub = $lobby(['farmer', 'viking', 'volva', 'skald'], [0, 0, 1, 1]);
        $this->assertSame([], my_rpg_party_problems($pub, ['m0', 'm1']), 'two players with two different heroes each');
        [$game, $private] = my_rpg_start($this->players(2), $pub, ['length_min' => 60, 'host_id' => 'm0', 'host_plays' => 1], $this->rnd());
        $this->assertCount(4, $game['heroes']);
        $this->assertSame(['m0' => [], 'm1' => []], $private, 'nothing private outside the public state');
        $this->assertSame([['k' => 'rpg.party_few', 'n' => 3, 'min' => 4], ['k' => 'rpg.party_nohero', 'member' => 'm2']],
                          my_rpg_party_problems($lobby(['farmer', 'viking', 'volva'], [0, 0, 1]), $three));
        $this->assertSame('rpg.tip_noheal', my_rpg_party_tip($lobby(['viking', 'berserker', 'hunter', 'outlaw'], [0, 0, 1, 1])), 'no protector and no support');
        $this->assertNull(my_rpg_party_tip($pub));
        $this->assertSame(['error' => 'bad_length'], my_rpg_start($this->players(2), $pub, ['length_min' => 30]));
    }

    /** TC-INT-048 (K-89, AK-72): the server rolls; a 20 always succeeds, a 1 always fails; a die from the phone is ignored */
    public function testDice(): void
    {
        $pub = $this->game(['farmer', 'viking', 'volva', 'skald'], [0, 0, 1, 1]);
        $pub['heroes'][0]['passives'] = array_values(array_diff($pub['heroes'][0]['passives'], ['sunlight']));   // a dwarf out of the sun
        $twenty = $one = false;
        for ($s = 1; $s < 3000 && !($twenty && $one); $s++) {
            [, $r] = my_rpg_roll($pub, 0, 'wits', 20, [], [], -10, $this->rnd($s));
            if ($r['d'] === [20]) { $this->assertTrue($r['ok'], 'a 20 succeeds against 20 even with -10'); $twenty = true; }
            [, $r] = my_rpg_roll($pub, 0, 'wits', 5, [], [], 30, $this->rnd($s));
            if ($r['d'] === [1]) { $this->assertFalse($r['ok'], 'a 1 fails against 5 even with +30'); $one = true; }
        }
        $this->assertTrue($twenty && $one);
        [, $r] = my_rpg_roll($pub, 0, 'wits', 12, ['farmer'], [], 0, $this->rnd(4));
        $this->assertCount(2, $r['d'], 'advantage: two dice');
        $this->assertSame(max($r['d']), $r['pick']);
        $pub = my_rpg_decide($pub, 'pass', $this->rnd(), 0.0);              // the pass: everyone looks for tracks
        $this->assertSame(['pass', 'check'], [$pub['scene'], $pub['st']['type']]);
        $r = my_rpg_act($pub, [], 'P0', ['hero' => 0, 'do' => 'roll', 'd' => [20], 'pick' => 20, 'total' => 99, 'ok' => true], 'm0', 1.0, $this->rnd(5));
        $this->assertSame('ok', $r[0]);
        $roll = $r[1]['st']['rolls'][0];
        $this->assertSame($roll['pick'] + $r[1]['heroes'][0]['attrs']['wits'], $roll['total'], 'the phone\'s 99 is ignored');
        $this->assertSame(['err', 'not_yours'], my_rpg_act($pub, [], 'P0', ['hero' => 2, 'do' => 'roll'], 'm0', 1.0), 'another player\'s hero');
    }

    /** TC-INT-049 (K-91, AK-73): 3 Liv and 5 damage: knocked out at 0; a friend helps it up; after the fight everyone has at least 1 Liv */
    public function testKnockedOutNeverDead(): void
    {
        $pub = $this->fight(['farmer', 'skald', 'volva', 'hunter'], [0, 0, 1, 1]);
        $pub['heroes'][0]['hp'] = 3;
        $pub = my_rpg_hurt($pub, 0, 5);
        $this->assertSame([0, true], [$pub['heroes'][0]['hp'], $pub['heroes'][0]['down']]);
        $pub = $this->turnOf($pub, 1);
        $this->assertContains(['do' => 'help', 'targets' => [0]], my_rpg_options($pub, 1));
        [$ok, $pub] = my_rpg_fight_act($pub, 1, ['do' => 'help', 'target' => 0], $this->rnd(), 0.0);
        $this->assertSame('ok', $ok);
        $this->assertSame([1, false], [$pub['heroes'][0]['hp'], $pub['heroes'][0]['down']], 'up again');
        $pub = my_rpg_hurt(my_rpg_hurt($pub, 0, 9), 2, 99);
        $pub = my_rpg_fight_end($pub, false, $this->rnd(), 0.0);
        $this->assertNull($pub['fight']);
        foreach ($pub['heroes'] as $x) $this->assertGreaterThanOrEqual(1, $x['hp'], 'after the fight nobody is out');
        foreach ($pub['heroes'] as $x) $this->assertFalse($x['down']);
        $this->assertNotContains('dead', $this->keys($pub));
    }

    /** TC-INT-050 (K-97, K-20, AK-75): one vote a hero; 2 against 2 waits for the host; 3 against 1 is decided */
    public function testVotes(): void
    {
        $vote = fn($pub, $hi, $opt) => my_rpg_act($pub, [], "P$hi", ['hero' => $hi, 'do' => 'vote', 'option' => $opt], "m$hi", 1.0, $this->rnd())[1];
        $pub = $this->game(['farmer', 'viking', 'volva', 'skald'], [0, 1, 2, 3]);
        foreach (['pass', 'pass', 'wood'] as $hi => $o) $pub = $vote($pub, $hi, $o);
        $this->assertSame('village', $pub['scene'], 'one hero has not voted');
        $tie = $vote($pub, 3, 'wood');
        $this->assertSame(['pass', 'wood'], $tie['st']['tie']);
        $this->assertSame(['pass', 'wood'], my_rpg_you($tie, 'm0', [], true)['decide'], 'the host is asked');
        $this->assertSame(['err', 'not_host'], my_rpg_act($tie, [], 'P1', ['do' => 'decide', 'option' => 'wood'], 'm1'));
        $this->assertSame('wood', my_rpg_act($tie, [], 'P0', ['do' => 'decide', 'option' => 'wood'], 'm0', 1.0, $this->rnd())[1]['scene']);
        $this->assertSame('pass', $vote($pub, 3, 'pass')['scene'], 'three against one');
        $view = my_rpg_public($tie, $this->players(4));
        $this->assertSame(['pass' => 2, 'wood' => 2], $view['step']['votes']);
    }

    /** TC-INT-051 (K-99, K-101, K-102, AK-76): the TV and the phones never see a draw, an enemy's Liv or another hero's task; a game master sees all */
    public function testWhatEachDeviceSees(): void
    {
        $pub = $this->fight(['farmer', 'viking', 'volva', 'skald'], [0, 0, 1, 1], true);
        $here = $this->players(2);
        $tv = my_rpg_public($pub, $here);
        $keys = $this->keys($tv);
        foreach (['draw', 'task', 'clues', 'secrets', 'rolls'] as $never) $this->assertNotContains($never, $keys, "the TV sees $never");
        foreach ($tv['fight']['foes'] as $f) $this->assertSame(['kind', 'health', 'down', 'frozen', 'dis'], array_keys($f), 'an enemy in quarters only');
        $you = my_rpg_you($pub, 'm1', $here);
        $this->assertSame([2, 3], array_column($you['heroes'], 'i'), 'only their own heroes');
        $this->assertNotNull($you['heroes'][0]['task']);
        $this->assertNotContains('draw', $this->keys($you));
        $m = my_rpg_master($pub, $here);
        $this->assertSame($pub['draw'], $m['state']['draw']);
        $this->assertSame($pub['fight']['foes'][0]['hp'], $m['state']['fight']['foes'][0]['hp'], 'the game master sees the Liv');
        $this->assertSame(['err', 'rpg_not_leading'], my_rpg_lead($pub, ['do' => 'say', 'text' => 'Hej']));
        [, $led] = my_rpg_lead($pub, ['do' => 'take']);
        [, $led] = my_rpg_lead($led, ['do' => 'say', 'text' => "<b>Trym</b> ler\x07 brett"]);
        $this->assertSame('bTrym/b ler brett', my_rpg_public($led, $here)['gm']['say']['text'], 'shown as written, without markup (K-103)');
        [, $led] = my_rpg_lead($led, ['do' => 'roll', 'dice' => [1, 20]]);
        $this->assertNotContains('rolls', $this->keys(my_rpg_public($led, $here)['gm']), 'a hidden roll is hidden');
        $plays = $this->fight(['farmer', 'viking', 'volva', 'skald'], [0, 0, 1, 1], false);
        $this->assertSame(['err', 'not_master'], my_rpg_lead($plays, ['do' => 'take']), 'a game master who plays may not take over');
    }

    /** TC-INT-052 (K-101, K-104, AK-77): the game master holds the next scene; when they are gone the computer goes on from there */
    public function testGameMasterGoneComputerGoesOn(): void
    {
        $pub = $this->game(['farmer', 'viking', 'volva', 'skald'], [0, 0, 1, 1], true);
        [, $pub] = my_rpg_lead($pub, ['do' => 'take']);
        $pub = my_rpg_decide($pub, 'wood', $this->rnd(), 0.0);
        $this->assertSame(['village', 'wait', 'wood'], [$pub['scene'], $pub['st']['type'], $pub['gm']['pending']], 'waits for the game master');
        $this->assertSame(['err', 'rpg_gm_decides'], my_rpg_move($pub, 0, ['do' => 'roll'], $this->rnd(), 1.0));
        $still = my_rpg_tick($pub, $this->players(2), 'm9', 2.0, ['master' => true]);
        $this->assertSame('village', ($still ?? $pub)['scene'], 'the game master is still here');
        $gone = my_rpg_tick($pub, $this->players(2), 'm9', 3.0, ['master' => false]);
        $this->assertFalse($gone['gm']['on']);
        $this->assertSame('wood', $gone['scene'], 'the computer goes on where it was, no pause');
        [, $pub] = my_rpg_lead($pub, ['do' => 'next', 'scene' => 'pass']);
        $this->assertSame('pass', $pub['scene'], 'or the game master picks another scene');
    }

    /** TC-INT-053 (K-107, AK-79): 3 players, 4 heroes; the one with one hero leaves: the other with one takes it over, with its things, and gives it back */
    public function testLeaverHeroesTakenOver(): void
    {
        $pub = $this->game(['farmer', 'viking', 'volva', 'skald'], [0, 0, 1, 2]);
        $pub['heroes'][3]['items'] = ['armring'];
        $three = $this->players(3);
        $left = my_rpg_tick($pub, array_slice($three, 0, 2), 'm0', 1.0);
        $this->assertSame([0, 0, 1, 1], array_column($left['heroes'], 'p'), 'P1 had one hero: now two');
        $this->assertCount(4, $left['heroes']);
        $this->assertSame(['armring'], $left['heroes'][3]['items']);
        $back = my_rpg_tick($left, $three, 'm0', 2.0);
        $this->assertSame([0, 0, 1, 2], array_column($back['heroes'], 'p'), 'back to its player');
        $alone = my_rpg_tick($pub, [$three[0]], 'm0', 1.0);
        $this->assertSame([0, 0, null, null], array_column($alone['heroes'], 'p'), 'nobody with fewer than two: the computer');
    }

    /** TC-INT-054 (K-106, AK-81): a pause in the middle of a fight changes nothing; after it, the same turn, Liv, resources and enemies */
    public function testPauseKeepsTheFight(): void
    {
        $pub = $this->fight(['farmer', 'hunter', 'volva', 'skald'], [0, 0, 1, 1]);
        $pub = $this->turnOf($pub, 1);
        $this->assertSame($pub, my_rpg_hold($pub, true, 50.0), 'pausing changes nothing');
        $after = my_rpg_hold($pub, false, 99.0);
        foreach (['heroes', 'log'] as $k) $this->assertSame($pub[$k], $after[$k]);
        $this->assertSame(array_diff_key($pub['fight'], ['since' => 1]), array_diff_key($after['fight'], ['since' => 1]));
        $this->assertSame(['h', 1], my_rpg_turn($after), 'still the hunter\'s turn');
        $this->assertSame(99.0, $after['fight']['since'], 'the computer waits a full turn again');
    }

    /** TC-INT-055 (K-100, AK-80): a clear rune is recognised, a scribble is not; every movement also has a button */
    public function testRunesAndButtons(): void
    {
        $line = fn($a, $b) => array_map(fn($t) => [$a[0] + ($b[0] - $a[0]) * $t / 6, $a[1] + ($b[1] - $a[1]) * $t / 6], range(0, 6));
        $this->assertTrue(my_rpg_rune_drawn('ᛁ', [$line([0.5, 0.1], [0.5, 0.9])]), 'Is: a straight line down');
        $this->assertTrue(my_rpg_rune_drawn('ᚲ', [array_merge($line([0.8, 0.1], [0.2, 0.5]), $line([0.2, 0.5], [0.8, 0.9]))]), 'Kaun: an angle');
        $this->assertFalse(my_rpg_rune_drawn('ᚲ', [$line([0.5, 0.1], [0.5, 0.9])]), 'a line is not Kaun');
        $this->assertFalse(my_rpg_rune_drawn('ᛉ', [$line([0.1, 0.5], [0.9, 0.5])]));
        $this->assertFalse(my_rpg_rune_drawn('ᛁ', 'line'));
        $this->assertFalse(my_rpg_rune_drawn('ᛁ', array_fill(0, 7, $line([0.5, 0.1], [0.5, 0.9]))), 'at most six strokes');
        $pub = $this->fight(['shieldmaiden', 'farmer', 'volva', 'skald'], [0, 0, 1, 1]);
        $pub['fight']['react'] = ['foe' => 0, 'hero' => 0, 'guard' => 0, 'kind' => 'parry', 'dmg' => 6, 'until' => 10.0];
        $hp = $pub['heroes'][0]['hp'];
        [$ok, $after] = my_rpg_move($pub, 0, ['do' => 'react', 'yes' => true], $this->rnd(), 1.0);
        $this->assertSame([$hp - 3, null], [$after['heroes'][0]['hp'], $after['fight']['react'] ?? null], 'the button parries: half the blow');
        $pub = $this->turnOf($pub, 2);
        $res = $pub['heroes'][2]['res'];
        [$ok, $after] = my_rpg_fight_act($pub, 2, ['do' => 'ability', 'id' => 'kaun', 'target' => 0], $this->rnd(), 1.0);
        $this->assertSame(['ok', $res - 1], [$ok, $after['heroes'][2]['res']], 'a rune without drawing it: the button');
        $this->assertFalse(array_values(array_filter($after['log'], fn($e) => $e['k'] === 'ability'))[0]['clear']);
    }

    /** TC-INT-056 (K-98): only what can be done now: no attack without a weapon for the zone, not out of turn */
    public function testOnlyWhatCanBeDone(): void
    {
        $pub = $this->fight(['berserker', 'farmer', 'volva', 'skald'], [0, 0, 1, 1]);
        $pub = $this->turnOf($pub, 0);
        $pub['heroes'][0]['f']['zone'] = 'far';
        $this->assertSame(['berserk', 'move', 'wait'], array_map(fn($o) => $o['id'] ?? $o['do'], my_rpg_options($pub, 0)), 'the axe does not reach from far away');
        $this->assertSame(['err', 'rpg_cannot'], my_rpg_fight_act($pub, 0, ['do' => 'attack', 'target' => 0], $this->rnd(), 0.0));
        $this->assertSame(['err', 'not_your_turn'], my_rpg_fight_act($pub, 1, ['do' => 'wait'], $this->rnd(), 0.0));
        [, $pub] = my_rpg_fight_act($pub, 0, ['do' => 'move'], $this->rnd(), 0.0);
        $this->assertSame('near', $pub['heroes'][0]['f']['zone']);
        $this->assertNotSame(['h', 0], my_rpg_turn($pub), 'a move ends the turn (only the swift move and still act)');
    }

    /** TC-INT-057 (K-92, K-88): played to the end: a gift each, the stars, one level up (at most 5); ended by the host: no level */
    public function testTheEnd(): void
    {
        $h = my_rpg_heroes('vikings');
        $three = my_rpg_build($h, ['name' => 'Astrid', 'cls' => 'shieldmaiden', 'folk' => 'norse', 'attr' => 'wits', 'level' => 4, 'items' => ['armring']]);
        $this->assertContains('two_reactions', $three['passives'], 'level 3');
        $this->assertSame(['might' => 3, 'agility' => 3, 'wits' => 1, 'spirit' => 0], $three['attrs'], 'the folk\'s +1 on Förstånd, level 4\'s +1 on Kraft (the first below +3)');
        $this->assertSame(10 + 3 + 2 + 1, $three['def'], 'Försvar = 10 + Smidighet + rustning, + the arm ring');
        $pub = $this->game(['farmer', 'viking', 'volva', 'skald'], [0, 0, 1, 1]);
        $pub['heroes'][1]['level'] = 5;
        $end = my_rpg_finish($pub, $this->rnd());
        $this->assertTrue($end['over']);
        $this->assertSame([2, 5, 2, 2], $end['end']['levels']);
        $this->assertCount(4, array_unique($end['end']['gifts']), 'no gift twice when it can be helped');
        foreach ($end['heroes'] as $i => $x) $this->assertSame([$end['end']['gifts'][$i]], $x['items']);
        $this->assertSame(['hammer' => true, 'hidden' => true, 'won' => false], $end['end']['stars']);
        $host = my_rpg_end($pub);
        $this->assertSame(['host', null], [$host['how'], $host['end']], 'ended by the host: no level, no gifts');
        $this->assertTrue(my_rpg_over($host));
    }
}
