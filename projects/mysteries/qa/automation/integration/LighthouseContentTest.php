<?php
// "The Lighthouse in the Storm" as data, and the checker's solvers for the puzzle lock types (component level, no
// database): modules/escape-content.inc.php and content/lighthouse/. Seeded deals of every length for 2-6 players, each
// checked and played through, and fault seeding: broken puzzles that the checker must reject and name.
// The full 10 000 deals per length and player count are run by qa/tools/escape-check.php (K-74, K-114).
// Traceability: US-24, US-25, K-28, K-109, K-112..K-114, K-117, K-119, K-120, AK-84.
use PHPUnit\Framework\TestCase;

require_once '/srv/mysteries/modules/escape.inc.php';

final class LighthouseContentTest extends TestCase
{
    private const LANGS = ['en', 'sv', 'da', 'nl'];

    private function seeded(int $seed): Random\Randomizer
    {
        return new Random\Randomizer(new Random\Engine\Mt19937($seed));
    }

    private function assertProblem(string $needle, array $problems, string $why): void
    {
        $this->assertNotEmpty(array_filter($problems, fn($p) => str_contains($p, $needle)), "$why: expected \"$needle\" in " . json_encode(array_slice($problems, 0, 5)));
    }

    /** TC-INT-065 (K-109, K-119, K-120): the room is data: three rooms, eight locks of the puzzle types, 30 and 60 minutes; nothing wrong */
    public function testStructure(): void
    {
        $r = my_escape_load('lighthouse');
        $this->assertSame(['lighthouse', 'escape', 1], [$r['id'], $r['category'], $r['format']]);
        $this->assertContains('lighthouse', my_escape_list());
        $this->assertSame(['min' => 2, 'max' => 6], $r['players']);
        $this->assertSame(['cottage', 'engine', 'lantern'], array_column($r['rooms'], 'id'));
        $this->assertSame(['lamp', 'bricks', 'door', 'gas', 'valves', 'hatch', 'rings', 'signal'], array_column($r['locks'], 'id'));
        $this->assertSame(['together', 'slide', 'code', 'pipes', 'toggle', 'weights', 'rings', 'rhythm'], array_column($r['locks'], 'type'));
        $this->assertSame(['digits' => 'bricks', 'look' => 'dial'], array_intersect_key($r['locks'][2], ['digits' => 1, 'look' => 1]), 'K-109: the dial lock takes the puzzles\' digits');
        $this->assertSame([30, 60], array_column($r['lengths'], 'minutes'), 'K-119: 90 min comes later');
        $this->assertSame(['lamp', 'bricks', 'door', 'rings'], array_keys(my_escape_locks($r, 30)));
        $this->assertSame(2, my_escape_locks($r, 30)['rings']['rings'], 'K-119: two rings at 30 min');
        $this->assertCount(8, my_escape_locks($r, 60));
        $this->assertSame([], my_escape_problems('lighthouse'));
        foreach ([2 => 4, 4 => 4, 5 => 5, 6 => 6] as $n => $want) {     // K-120: four puzzles, or one each when more
            $g = my_escape_deal($r, 60, $n, $this->seeded($n));
            $this->assertCount($want, array_filter($g['frags'], fn($f) => $f['k'] === 'slide'), "$n players");
        }
    }

    /** TC-INT-066 (K-74, K-114, AK-84): 200 seeded deals of each length for each of 2-6 players: each sound and played through */
    public function testManyDealsAreSound(): void
    {
        $this->assertSame([], my_escape_sound('lighthouse', 200));
        $this->assertTrue(my_escape_playable('lighthouse'));
    }

    /** TC-INT-067 (K-114, AK-84): broken puzzles are found and named: no way through, a chart already reached, a picture already lying right, a ring facing right, two rows alike */
    public function testBrokenPuzzlesAreFound(): void
    {
        $r = my_escape_get('lighthouse');
        $game = my_escape_deal($r, 60, 3, $this->seeded(5));
        $this->assertSame([], my_escape_check_game($r, 60, $game, 3));

        $bad = $game;                                             // only straight pipes: the gas cannot turn
        foreach ($bad['locks']['gas']['pub']['cells'] as $c => $cell) $bad['locks']['gas']['pub']['cells'][$c][0] = 'I';
        $this->assertProblem('gas: no way through', my_escape_check_game($r, 60, $bad, 3), 'AK-84: a pipe grid without a way');

        $bad = $game;                                             // the chart is the panel as it stands
        $on = $bad['locks']['valves']['pub']['on'];
        $bad['locks']['valves']['sec']['target'] = $on;
        foreach ($bad['frags'] as $k => $f) if ($f['lock'] === 'valves') $bad['frags'][$k]['cells'] = array_slice($on, $f['row'] * 3, 3);
        $this->assertProblem('valves: only 0 turns away', my_escape_check_game($r, 60, $bad, 3), 'AK-84: a chart that needs no turn');

        $bad = $game;
        foreach ($bad['frags'] as $k => $f) if ($f['k'] === 'slide') { $bad['frags'][$k]['tiles'] = [1, 2, 3, 4, 5, 6, 7, 8, 0]; $pic = $f['pic']; break; }
        $this->assertProblem("bricks: $pic lies too near solved", my_escape_check_game($r, 60, $bad, 3), 'a sliding picture already whole');

        $bad = $game;
        $bad['locks']['rings']['pub']['pos'][0] = $bad['locks']['rings']['sec']['target'][0];
        $this->assertProblem('rings: ring 0 faces right at the start', my_escape_check_game($r, 60, $bad, 3), 'a ring already right');

        $bad = $game;
        $rows = array_keys(array_filter($bad['frags'], fn($f) => $f['lock'] === 'signal'));
        $bad['frags'][$rows[1]]['s'] = $bad['frags'][$rows[0]]['s'];
        $this->assertProblem('signal: two rows share a signal or an answer', my_escape_check_game($r, 60, $bad, 3), 'two rows alike');

        $bad = $game;                                             // every pipe square to one player
        foreach ($bad['locks']['gas']['pub']['cells'] as $c => $cell) $bad['locks']['gas']['pub']['cells'][$c][2] = 0;
        $this->assertProblem('gas: the pieces are not owned evenly', my_escape_check_game($r, 60, $bad, 3), 'K-113');

        $bad = $game;                                             // a ring's line given to its owner
        foreach ($bad['frags'] as $k => $f) if ($f['lock'] === 'rings') { $bad['frags'][$k]['p'] = $bad['locks']['rings']['pub']['owners'][$f['ring']]; $ring = $f['ring']; break; }
        $this->assertProblem("rings: the line for ring $ring is with its owner", my_escape_check_game($r, 60, $bad, 3), 'K-113');
    }

    /** TC-INT-068 (K-28, K-87, K-117, NFR-10): every text in four languages, every picture there, the room's own screen words, and where on a picture the app draws */
    public function testTextsAndPictures(): void
    {
        $r = my_escape_get('lighthouse');
        $texts = [];
        foreach (self::LANGS as $l) $texts[$l] = my_mystery_json(my_escape_dir('lighthouse') . "/$l.json");
        $this->assertSame([], my_escape_check($r, $texts, my_escape_media('lighthouse')));
        foreach (self::LANGS as $l) {
            $this->assertSame(array_keys($texts['en']), array_keys($texts[$l]), "$l has the same keys as English");
            foreach ($texts[$l] as $k => $v) $this->assertNotSame('', trim($v), "$l: $k is empty");
        }
        $this->assertCount(24, my_escape_media('lighthouse'));
        $this->assertSame('Fyren lyser!', $texts['sv']['screen.out']);

        $broken = $texts;
        unset($broken['nl']['note.signal.row']);
        $this->assertProblem('note.signal.row', my_escape_check($r, $broken, my_escape_media('lighthouse')), 'a text missing in Dutch');
        $broken = $texts;
        $broken['en']['screen.forge'] = 'The forge';
        $this->assertNotEmpty(my_escape_check($r, $broken, my_escape_media('lighthouse')), 'a screen word that does not exist');
        $this->assertNotEmpty(my_escape_check($r, $texts, array_values(array_diff(my_escape_media('lighthouse'), ['seamark-cairn']))), 'a picture missing');

        $pins = array_column(array_filter($r['locks'], fn($l) => isset($l['pin'])), 'pin', 'id');
        $this->assertSame(['hatch', 'signal'], array_keys($pins), 'K-117: the weights\' dots on the brass plate, the ship\'s lamp on its mast');
        foreach ([[50], [50, 101], ['50', 50], [-1, 50]] as $bad) {
            $broken = $r;
            $broken['locks'][5]['pin'] = $bad;
            $this->assertProblem('lock hatch: bad pin', my_escape_check($broken, $texts, my_escape_media('lighthouse')), 'a pin off the picture: ' . json_encode($bad));
        }
        $broken = $r;
        unset($broken['locks'][7]['image']);
        $this->assertProblem('lock signal: bad pin', my_escape_check($broken, $texts, my_escape_media('lighthouse')), 'a pin without a picture');
    }
}
