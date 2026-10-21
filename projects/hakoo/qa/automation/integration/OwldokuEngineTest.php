<?php
/**
 * Component tests of the Owldoku puzzles (src/owldoku-engine.inc.php): the solver that never guesses,
 * the generator and the checks. No database or HTTP.
 * Traceability: FR-OWLDOKU-01..04.
 */
require_once '/var/www/html/owldoku-engine.inc.php';

use PHPUnit\Framework\TestCase;

class OwldokuEngineTest extends TestCase
{
    /** TC-UNIT-OD-01 | FR-OWLDOKU-01, FR-OWLDOKU-02: every level makes square puzzles with one owl per row, column and region, none touching, one solution */
    public function test_generated_puzzles()
    {
        foreach (['easy' => 6, 'medium' => 8, 'hard' => 10] as $level => $n) {
            $t = microtime(true);
            for ($seed = 1; $seed <= 8; $seed++) {
                $p = Owldoku::generate($level, $seed);
                $this->assertSame($n, $p['size']);
                $this->assertCount($n, $p['regions']);
                $reg = Owldoku::regionsOf($p['regions']);
                $regions = array_values(array_unique($reg));
                sort($regions);
                $this->assertSame(range(0, $n - 1), $regions, "$level $seed: n regions");
                // The owls: one per row (by construction), column and region, never touching.
                $cols = $p['owls'];
                sort($cols);
                $this->assertSame(range(0, $n - 1), $cols, 'one per column');
                $this->assertCount($n, array_unique(array_map(fn($r) => $reg[$r * $n + $p['owls'][$r]], range(0, $n - 1))), 'one per region');
                for ($r = 1; $r < $n; $r++) $this->assertGreaterThanOrEqual(2, abs($p['owls'][$r] - $p['owls'][$r - 1]), 'never touching');
                // Every region in one piece.
                foreach (array_unique($reg) as $g) {
                    $cells = array_keys(array_filter($reg, fn($x) => $x === $g));
                    $seen = [$cells[0] => true];
                    $todo = [$cells[0]];
                    while ($todo) {
                        $i = array_pop($todo);
                        foreach ([$i - $n, $i + $n, $i % $n ? $i - 1 : -1, ($i + 1) % $n ? $i + 1 : -1] as $j) {
                            if ($j >= 0 && $j < $n * $n && $reg[$j] === $g && !isset($seen[$j])) { $seen[$j] = true; $todo[] = $j; }
                        }
                    }
                    $this->assertCount(count($cells), $seen, "$level $seed: region $g in one piece");
                }
                $this->assertNull(Owldoku::otherSolution($n, $reg, $p['owls']), "$level $seed: one solution");
                $s = Owldoku::solve($n, $reg);
                $this->assertSame([$p['owls'], Owldoku::LEVELS[$level][1]], [$s['owls'], $s['trick']], "$level $seed: solved by its tricks");
            }
            $this->assertLessThan(15.0, microtime(true) - $t, "$level: 8 puzzles in time");
        }
    }

    /** TC-UNIT-OD-02 | FR-OWLDOKU-02: the solver gets stuck (returns null) on a board with two solutions */
    public function test_solver_never_guesses()
    {
        // 4 x 4 with every row its own region: rows give no information, so two solutions exist (1,3,0,2 and 2,0,3,1).
        $reg = [0, 0, 0, 0, 1, 1, 1, 1, 2, 2, 2, 2, 3, 3, 3, 3];
        $this->assertNull(Owldoku::solve(4, $reg));
        $this->assertNotNull(Owldoku::otherSolution(4, $reg, [1, 3, 0, 2]));
    }

    /** TC-UNIT-OD-03 | FR-OWLDOKU-03, FR-OWLDOKU-04: the same seed gives the same puzzle; owls and solutions are checked */
    public function test_seeds_and_the_checks()
    {
        $a = Owldoku::generate('medium', 12345);
        $this->assertSame($a, Owldoku::generate('medium', 12345));
        $this->assertNotSame($a['regions'], Owldoku::generate('medium', 12346)['regions']);
        $n = $a['size'];
        $this->assertTrue(Owldoku::isOwl($a, 0, $a['owls'][0]));
        $this->assertFalse(Owldoku::isOwl($a, 0, ($a['owls'][0] + 1) % $n));
        $grid = str_repeat('0', $n * $n);
        foreach ($a['owls'] as $r => $c) $grid[$r * $n + $c] = '1';
        $this->assertTrue(Owldoku::solves($a, $grid));
        $this->assertFalse(Owldoku::solves($a, str_repeat('0', $n * $n)), 'no owls');
        $wrong = $grid;
        $wrong[$a['owls'][0]] = '0';
        $wrong[($a['owls'][0] + 1) % $n] = '1';
        $this->assertFalse(Owldoku::solves($a, $wrong), 'one owl moved');
        $this->assertFalse(Owldoku::solves($a, substr($grid, 1)), 'too short');
        try {
            Owldoku::generate('huge', 1);
            $this->fail('expected bad_level');
        } catch (OwldokuError $e) {
            $this->assertSame('bad_level', $e->getMessage());
        }
        $d = Owldoku::dailySeed('easy', '2026-10-07');
        $this->assertSame($d, Owldoku::dailySeed('easy', '2026-10-07'));
        $this->assertNotSame($d, Owldoku::dailySeed('easy', '2026-10-08'));
        $this->assertNotSame($d, Owldoku::dailySeed('hard', '2026-10-07'));
        $this->assertNotSame($d, Owldoku::dailySeed('easy', '2026-10-07', true), 'the guests get another puzzle');
    }
}
