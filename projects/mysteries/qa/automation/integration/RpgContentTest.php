<?php
// Role-playing content: the hero set "vikings" and the adventure "The Stolen Hammer", and the checker that proves them
// sound (component level, no database), modules/rpg-content.inc.php, content/vikings/ and content/stolen-hammer/.
// Fault seeding: broken copies the checker must reject and name. A few hundred playthroughs and fights here; the full
// 10 000 fights per fight and party size are run by qa/tools/rpg-check.php (K-95).
// Traceability: US-21, US-23, K-88, K-94..K-96, K-108, AK-70, AK-74.
use PHPUnit\Framework\TestCase;

require_once '/srv/mysteries/modules/rpg.inc.php';

final class RpgContentTest extends TestCase
{
    private function texts(string $id): array
    {
        $t = [];
        foreach (MY_RPG_LANGS as $l) $t[$l] = my_mystery_json(my_rpg_dir($id) . "/$l.json");
        return $t;
    }

    private function assertProblem(string $needle, array $problems, string $why): void
    {
        $this->assertNotEmpty(array_filter($problems, fn($p) => str_contains($p, $needle)), "$why: expected \"$needle\" in " . json_encode(array_slice($problems, 0, 5)));
    }

    /** TC-INT-042 (K-88, K-94, K-96, AK-70, AK-74): 11 classes, 4 folk, the adventure in 60 and 90 min; nothing wrong */
    public function testStructure(): void
    {
        $h = my_rpg_heroes('vikings');
        $this->assertSame(['farmer', 'viking', 'shieldmaiden', 'volva', 'berserker', 'hunter', 'outlaw', 'skald', 'runecarver', 'champion', 'gothi'],
                          array_column($h['classes'], 'id'));
        $this->assertSame(['norse', 'dwarf', 'lightelf', 'giant'], array_column($h['folk'], 'id'));
        foreach ($h['classes'] as $c) {
            $this->assertSame(5, array_sum($c['attrs']), "{$c['id']}: the attributes sum to +5");
            $this->assertSame(10 + $c['attrs']['agility'] + $c['armour'], $c['defence'], "{$c['id']}: Försvar = 10 + Smidighet + rustning");
        }
        $a = my_rpg_get('stolen-hammer');
        $this->assertSame(['stolen-hammer', 'rpg', 'vikings'], [$a['id'], $a['category'], $a['heroes']]);
        $this->assertContains('stolen-hammer', my_rpg_list());
        $this->assertSame([60, 90], array_column($a['lengths'], 'minutes'), 'K-96');
        $this->assertSame(['village', 'pass', 'wood', 'feast', 'cellar', 'finale', 'end'], array_keys(my_rpg_scenes($a, 60)));
        $this->assertSame([], my_rpg_problems('stolen-hammer'));
    }

    /** TC-INT-043 (K-88, AK-70): a viking with Kraft +4, a wrong Försvar, an unknown ability and a missing text are stopped and named */
    public function testBrokenHeroesAreStopped(): void
    {
        $h = my_rpg_heroes('vikings');
        $t = $this->texts('vikings');
        $media = my_rpg_media('vikings');
        $this->assertSame([], my_rpg_check_heroes($h, $t, $media));
        $bad = $h;
        $bad['classes'][1]['attrs']['might'] = 4;
        $this->assertProblem('viking', my_rpg_check_heroes($bad, $t, $media), 'Kraft +4');
        $bad = $h;
        $bad['classes'][2]['defence'] = 16;
        $this->assertProblem('shieldmaiden', my_rpg_check_heroes($bad, $t, $media), 'Försvar 16');
        $bad = $h;
        $bad['classes'][0]['abilities'][] = 'fireball';
        $this->assertProblem('fireball', my_rpg_check_heroes($bad, $t, $media), 'an ability with no rules');
        $bad = $t;
        unset($bad['nl']['class.skald']);
        $this->assertProblem('class.skald', my_rpg_check_heroes($h, $bad, $media), 'a Dutch text is missing (K-108)');
    }

    /** TC-INT-044 (K-94, K-95, AK-74): a scene nobody can reach, a dead end, a missing Dutch text and a clue without its place are stopped */
    public function testBrokenAdventuresAreStopped(): void
    {
        $a = my_rpg_get('stolen-hammer');
        $h = my_rpg_heroes('vikings');
        $t = $this->texts('stolen-hammer');
        $media = my_rpg_media('stolen-hammer');
        $this->assertSame([], my_rpg_check($a, $h, $t, $media));
        $bad = $a;                                                      // the road vote only leads to the pass: the wood is never reached
        $bad['scenes'][0]['steps'][0]['options'][1]['then'] = [['next' => ['pass']]];
        $this->assertProblem('wood cannot be reached', my_rpg_check($bad, $h, $t, $media), 'an unreachable scene');
        $bad = $a;                                                      // a lost fight that leads nowhere
        $bad['scenes'][1]['steps'][1]['lose'] = [['half' => true]];
        $this->assertNotEmpty(my_rpg_check($bad, $h, $t, $media), 'the last step of a scene must lead on, also when the fight is lost');
        $bad = $t;
        unset($bad['nl']['scene.feast.text']);
        $this->assertProblem('nl: missing scene.feast.text', my_rpg_check($a, $h, $bad, $media), 'a Dutch text is missing');
        $bad = $t;
        $bad['da']['clue.not'] = 'Hammeren ligger ikke her.';
        $this->assertProblem('da: clue.not has no {place}', my_rpg_check($a, $h, $bad, $media), 'a clue that names no place');
        $this->assertProblem('picture', my_rpg_check($a, $h, $t, []), 'the pictures are checked');
    }

    /** TC-INT-045 (K-95, AK-74): 20 random playthroughs of each length for 4, 5 and 6 heroes all reach the end */
    public function testPlaythroughsEnd(): void
    {
        $this->assertSame([], my_rpg_playthroughs('stolen-hammer', 20, 3));
    }

    /** TC-INT-046 (K-95): every fight is won by random level-1 parties in roughly 70-95 % (300 fights each here, 10 000 in the tool) */
    public function testFightsAreBalanced(): void
    {
        $rates = my_rpg_fight_rates('stolen-hammer', 300, 11);
        $this->assertSame(['pass.wolves', 'wood.fight', 'gate.fight', 'finale.fight', 'finale.fight.angry'], array_keys($rates));
        foreach ($rates as $fight => $by) foreach ($by as $n => $r) {
            $this->assertGreaterThanOrEqual(0.65, $r, "$fight with $n heroes");
            $this->assertLessThanOrEqual(0.98, $r, "$fight with $n heroes");
        }
        $this->assertSame([], my_rpg_balance(['x' => [4 => 0.8, 5 => 0.9, 6 => 0.95]]));
        $this->assertCount(2, my_rpg_balance(['x' => [4 => 0.69, 5 => 0.9, 6 => 0.96]]), 'outside 70-95 % is named');
        $this->assertTrue(my_rpg_playable('stolen-hammer'));
    }
}
