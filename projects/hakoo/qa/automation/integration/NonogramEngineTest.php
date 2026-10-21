<?php
/**
 * Component tests of the Nonogram puzzles (src/nonogram-engine.inc.php): the clues, the line
 * solver, the generator and the check. No database or HTTP.
 * Traceability: FR-NONOGRAM-01..03.
 */
require_once '/var/www/html/nonogram-engine.inc.php';

use PHPUnit\Framework\TestCase;

class NonogramEngineTest extends TestCase
{
    /** TC-UNIT-NG-01 | FR-NONOGRAM-01: a clue is the runs of filled squares in order; an empty line has none */
    public function test_clues()
    {
        $this->assertSame([3, 1], Nonogram::clue([0, 1, 1, 1, 0, 0, 1, 0]));
        $this->assertSame([], Nonogram::clue([0, 0, 0]));
        $this->assertSame([5], Nonogram::clue([1, 1, 1, 1, 1]));
    }

    /** TC-UNIT-NG-02 | FR-NONOGRAM-02: the line logic finds what every placement agrees on, and sees a contradiction */
    public function test_line_logic()
    {
        $u = array_fill(0, 10, -1);
        $this->assertSame([-1, -1, 1, 1, 1, 1, 1, 1, -1, -1], Nonogram::deduce([8], $u), 'a run of 8 in 10 covers the middle 6');
        $this->assertSame(array_fill(0, 10, 0), Nonogram::deduce([], $u), 'no runs: all empty');
        $this->assertSame([1, 1, 1, 0, 1, 1, 1, 1, 0, 1], Nonogram::deduce([3, 4, 1], $u), 'the runs fill the line exactly');
        $this->assertSame([1, 1, 0, 0, 0], Nonogram::deduce([2], [1, -1, -1, -1, -1]), 'a run that starts at a known square');
        $this->assertNull(Nonogram::deduce([3], [-1, 0, -1, 0, -1]), 'no gap of three');
        $this->assertNull(Nonogram::deduce([1], [1, -1, 1]), 'two filled squares for one run of 1');
    }

    /** TC-UNIT-NG-03 | FR-NONOGRAM-02: every level makes square puzzles of its size that the line logic solves to the same picture */
    public function test_generated_puzzles_are_unique_and_solvable_by_logic()
    {
        foreach (['easy' => 10, 'medium' => 15, 'hard' => 20] as $level => $n) {
            $t = microtime(true);
            for ($seed = 1; $seed <= 15; $seed++) {
                $p = Nonogram::generate($level, $seed);
                $this->assertSame($n, $p['size']);
                $this->assertCount($n, $p['rows']);
                $this->assertCount($n, $p['cols']);
                $solved = Nonogram::solve($p['rows'], $p['cols']);
                $this->assertSame($p['grid'], array_map(fn($r) => implode('', $r), $solved), "$level $seed");
                $fill = substr_count(implode('', $p['grid']), '1') / ($n * $n);
                $this->assertGreaterThanOrEqual(0.3, $fill);
                $this->assertLessThanOrEqual(0.7, $fill);
            }
            $this->assertLessThan(3.0, microtime(true) - $t, "$level: 15 puzzles in time");
        }
    }

    /** TC-UNIT-NG-04 | FR-NONOGRAM-03, FR-NONOGRAM-04: the same seed gives the same puzzle; a grid is checked against the clues */
    public function test_seeds_and_the_check()
    {
        $a = Nonogram::generate('medium', 12345);
        $this->assertSame($a, Nonogram::generate('medium', 12345));
        $this->assertNotSame($a['grid'], Nonogram::generate('medium', 12346)['grid']);
        $grid = implode('', $a['grid']);
        $this->assertTrue(Nonogram::solves($a, $grid));
        $wrong = $grid;
        $wrong[7] = $wrong[7] === '1' ? '0' : '1';
        $this->assertFalse(Nonogram::solves($a, $wrong));
        $this->assertFalse(Nonogram::solves($a, substr($grid, 1)), 'too short');
        $this->assertFalse(Nonogram::solves($a, str_replace('1', '2', $grid)), 'not 0/1');
        try {
            Nonogram::generate('huge', 1);
            $this->fail('expected bad_level');
        } catch (NonogramError $e) {
            $this->assertSame('bad_level', $e->getMessage());
        }
    }

    /** TC-UNIT-NG-05 | FR-NONOGRAM-05: the puzzle of the day is the same for everyone, and differs per day and level */
    public function test_daily_seed()
    {
        $s = Nonogram::dailySeed('easy', '2026-10-05');
        $this->assertSame($s, Nonogram::dailySeed('easy', '2026-10-05'));
        $this->assertNotSame($s, Nonogram::dailySeed('easy', '2026-10-06'));
        $this->assertNotSame($s, Nonogram::dailySeed('hard', '2026-10-05'));
        $this->assertNotSame($s, Nonogram::dailySeed('easy', '2026-10-05', true), 'the guests get another puzzle');
        $this->assertSame($s, Nonogram::dailySeed('easy', '2026-10-05', false));
        $this->assertGreaterThanOrEqual(1, $s);
        $this->assertLessThanOrEqual(Nonogram::MAX_SEED, $s);
    }
}
