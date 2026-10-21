<?php
// Escape room content: "The Runesmith's Locks" and the checker that proves a room sound (component level, no database),
// modules/escape-content.inc.php and content/runesmith/. Seeded deals of every length for 2-6 players, each checked
// and played through, and fault seeding: broken copies and broken deals that the checker must reject.
// The full 10 000 deals per length and player count are run by qa/tools/escape-check.php (K-74).
// Traceability: US-18, K-28, K-72..K-76, K-87, NFR-10, AK-59, AK-61.
use PHPUnit\Framework\TestCase;

require_once '/srv/mysteries/modules/escape.inc.php';

final class EscapeContentTest extends TestCase
{
    private const LANGS = ['en', 'sv', 'da', 'nl'];

    private function texts(): array
    {
        $t = [];
        foreach (self::LANGS as $l) $t[$l] = my_mystery_json(my_escape_dir('runesmith') . "/$l.json");
        return $t;
    }

    private function seeded(int $seed): Random\Randomizer
    {
        return new Random\Randomizer(new Random\Engine\Mt19937($seed));
    }

    private function assertProblem(string $needle, array $problems, string $why): void
    {
        $this->assertNotEmpty(array_filter($problems, fn($p) => str_contains($p, $needle)), "$why: expected \"$needle\" in " . json_encode(array_slice($problems, 0, 5)));
    }

    /** TC-INT-026 (K-72, K-75, K-77): the room is data: rooms, items, eight locks of the lock types, two lengths; nothing wrong */
    public function testStructure(): void
    {
        $r = my_escape_load('runesmith');
        $this->assertSame(['runesmith', 'escape', 1], [$r['id'], $r['category'], $r['format']]);
        $this->assertContains('runesmith', my_escape_list());
        $this->assertSame(['min' => 2, 'max' => 6], $r['players']);
        $this->assertSame(['smithy', 'storeroom', 'boathouse'], array_column($r['rooms'], 'id'));
        $this->assertSame(['bellows', 'tools', 'runes', 'scale', 'shields', 'chest', 'chart', 'knots'], array_column($r['locks'], 'id'));
        $this->assertSame(['together', 'hidden', 'code', 'weights', 'order', 'keys', 'pieces', 'pull'], array_column($r['locks'], 'type'));
        $this->assertSame([30, 60], array_column($r['lengths'], 'minutes'), 'K-75: 90 min comes with the weaving room');
        $this->assertSame(['bellows', 'tools', 'runes'], array_keys(my_escape_locks($r, 30)), 'K-75: 30 min is the smithy');
        $this->assertCount(8, my_escape_locks($r, 60));
        $this->assertSame(16, count($r['runes']), 'the younger futhark');
        $this->assertSame([], my_escape_problems('runesmith'));
    }

    /** TC-INT-027 (K-74, AK-59): 200 seeded deals of each length for each of 2-6 players: each sound and played through */
    public function testManyDealsAreSound(): void
    {
        $this->assertSame([], my_escape_sound('runesmith', 200));
        $this->assertTrue(my_escape_playable('runesmith'));
    }

    /** TC-INT-028 (K-74, AK-59): a false shield line, a false scale note and a false tool note are found, and named */
    public function testFalseLinesAreFound(): void
    {
        $r = my_escape_get('runesmith');
        $game = my_escape_deal($r, 60, 3, $this->seeded(7));
        $this->assertSame([], my_escape_check_game($r, 60, $game, 3));

        $answer = $game['locks']['shields']['sec']['answer'];
        foreach ($game['frags'] as $k => $f) {
            if ($f['lock'] !== 'shields') continue;
            $bad = $game;
            $bad['frags'][$k] = ['id' => $f['id'], 'lock' => 'shields', 'k' => 'left', 'a' => $answer[3], 'p' => $f['p'], 'in' => null];   // the rightmost said to be leftmost
            $line = count(array_filter(array_slice($game['frags'], 0, $k), fn($x) => $x['lock'] === 'shields')) + 1;
            $this->assertProblem("shields: line $line is false: left {$answer[3]}", my_escape_check_game($r, 60, $bad, 3), 'AK-59: the false line is named');
            break;
        }

        foreach ($game['frags'] as $k => $f) {
            if ($f['lock'] !== 'scale' || $f['k'] !== 'abs') continue;
            $bad = $game;
            $bad['frags'][$k]['n']++;
            $this->assertProblem('scale:', my_escape_check_game($r, 60, $bad, 3), 'a note with the wrong weight');
        }

        foreach ($game['frags'] as $k => $f) {
            if ($f['lock'] !== 'tools') continue;
            $bad = $game;
            $bad['frags'][$k]['spot'] = array_values(array_diff($r['locks'][1]['spots'], [$f['spot']]))[0];
            $this->assertNotEmpty(my_escape_check_game($r, 60, $bad, 3), 'a note that sends you to the wrong spot');
            break;
        }
    }

    /** TC-INT-029 (K-74, AK-61): deals that break the rules of a fair deal are found */
    public function testUnfairDealsAreFound(): void
    {
        $r = my_escape_get('runesmith');
        $game = my_escape_deal($r, 60, 2, $this->seeded(11));
        $bad = $game;
        foreach ($bad['frags'] as $k => $f) if ($f['lock'] === 'runes') $bad['frags'][$k]['p'] = 0;
        $this->assertProblem('runes: every fragment is with one player', my_escape_check_game($r, 60, $bad, 2), 'one player alone');

        $bad = $game;
        foreach ($bad['frags'] as $k => $f) if ($f['p'] === 1) { $bad['frags'][$k]['p'] = 0; break; }
        foreach ($bad['frags'] as $k => $f) if ($f['p'] === 1 && $f['lock'] !== 'runes') { $bad['frags'][$k]['p'] = 0; break; }
        $this->assertProblem('uneven deal', my_escape_check_game($r, 60, $bad, 2), 'two more for one player');

        $bad = $game;
        $door = $bad['locks']['runes']['pub']['door'];
        $bad['locks']['runes']['pub']['door'] = [$door[1], $door[0], $door[2], $door[3]];
        if ($door[0] !== $door[1] && $game['locks']['runes']['sec']['map'][$door[0]] !== $game['locks']['runes']['sec']['map'][$door[1]]) {
            $this->assertProblem('runes: the parts give', my_escape_check_game($r, 60, $bad, 2), 'a door that does not match the code');
        }
    }

    /** TC-INT-030 (AK-61, K-73): with two players the rune row is split in two halves, and neither has the whole door */
    public function testTheRuneRowIsSplit(): void
    {
        $r = my_escape_get('runesmith');
        for ($s = 1; $s <= 200; $s++) {
            $game = my_escape_deal($r, 60, 2, $this->seeded($s));
            $parts = array_values(array_filter($game['frags'], fn($f) => $f['lock'] === 'runes'));
            $this->assertCount(2, $parts);
            $this->assertNotSame($parts[0]['p'], $parts[1]['p']);
            $a = array_column($parts[0]['pairs'], 0);
            $b = array_column($parts[1]['pairs'], 0);
            $this->assertSame([], array_intersect($a, $b), "seed $s: a rune on both phones");
            $all = array_merge($a, $b);
            sort($all);
            $runes = $r['runes'];
            sort($runes);
            $this->assertSame($runes, $all, "seed $s: all sixteen runes");
            foreach ([$a, $b] as $part) $this->assertNotEmpty(array_diff($game['locks']['runes']['pub']['door'], $part), "seed $s: one holds the whole door");
        }
    }

    /** TC-INT-031 (K-73, K-36): the draws differ from game to game */
    public function testDealsDiffer(): void
    {
        $r = my_escape_get('runesmith');
        $codes = $orders = $courses = [];
        for ($s = 1; $s <= 50; $s++) {
            $g = my_escape_deal($r, 60, 3, $this->seeded($s));
            $codes[] = $g['locks']['runes']['sec']['code'];
            $orders[] = implode(',', $g['locks']['shields']['sec']['answer']);
            $courses[] = implode(',', $g['locks']['chart']['sec']['course']);
        }
        $this->assertGreaterThan(40, count(array_unique($codes)));
        $this->assertGreaterThan(10, count(array_unique($orders)));
        $this->assertGreaterThan(20, count(array_unique($courses)));
    }

    /** TC-INT-032 (K-87, K-28, K-76, NFR-10): every text in four languages, every picture there, no runes or digits in a picture name */
    public function testTextsAndPictures(): void
    {
        $r = my_escape_get('runesmith');
        $texts = $this->texts();
        $this->assertSame([], my_escape_check($r, $texts, my_escape_media('runesmith')));
        foreach (self::LANGS as $l) {
            $this->assertSame(array_keys($texts['en']), array_keys($texts[$l]), "$l has the same keys as English");
            foreach ($texts[$l] as $k => $v) $this->assertNotSame('', trim($v), "$l: $k is empty");
        }
        $this->assertCount(33, my_escape_media('runesmith'));

        $broken = $texts;
        unset($broken['da']['lock.scale.about']);
        $this->assertProblem('lock.scale.about', my_escape_check($r, $broken, my_escape_media('runesmith')), 'a text missing in Danish');
        $this->assertNotEmpty(my_escape_check($r, $texts, array_values(array_diff(my_escape_media('runesmith'), ['sea-chart']))), 'a picture missing');
    }
}
