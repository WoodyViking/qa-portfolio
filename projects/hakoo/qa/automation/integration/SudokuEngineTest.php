<?php
/**
 * Component tests of the Sudoku puzzles (src/sudoku-engine.inc.php): the solver that never guesses,
 * the generator and the check. No database or HTTP.
 * Traceability: FR-SUDOKU-01..04.
 */
require_once '/var/www/html/sudoku-engine.inc.php';

use PHPUnit\Framework\TestCase;

class SudokuEngineTest extends TestCase
{
    /** Every solution of $grid, up to $limit (plain backtracking, independent of the engine). */
    private function solutions(string $grid, int $limit = 2): int
    {
        $i = strpos($grid, '0');
        if ($i === false) return 1;
        [$r, $c] = [intdiv($i, 9), $i % 9];
        $n = 0;
        for ($d = 1; $d <= 9 && $n < $limit; $d++) {
            $ok = true;
            for ($k = 0; $k < 9 && $ok; $k++) {
                $box = (intdiv($r, 3) * 3 + intdiv($k, 3)) * 9 + intdiv($c, 3) * 3 + $k % 3;
                if ($grid[$r * 9 + $k] === "$d" || $grid[$k * 9 + $c] === "$d" || $grid[$box] === "$d") $ok = false;
            }
            if ($ok) { $grid[$i] = "$d"; $n += $this->solutions($grid, $limit - $n); }
        }
        return $n;
    }

    /** TC-UNIT-SD-01 | FR-SUDOKU-01: the tricks solve a known puzzle; easy tricks get stuck where harder ones are needed */
    public function test_solver_tricks()
    {
        $easy = '910000802005020160800130754071000300500000001003000940149076008057080400308000097';
        $s = Sudoku::solve($easy, 1);
        $this->assertSame(['914657832735428169862139754471295386596843271283761945149376528657982413328514697', 1], [$s['grid'], $s['trick']]);
        $hard = Sudoku::generate('hard', 5)['grid'];
        $this->assertNull(Sudoku::solve($hard, 2), 'a Hard puzzle needs more than pairs');
        $this->assertNotNull(Sudoku::solve($hard, 3));
        $this->assertNull(Sudoku::solve('11' . str_repeat('0', 79)), 'two 1s in a row');
    }

    /** TC-UNIT-SD-02 | FR-SUDOKU-02: every level makes puzzles with one solution that its tricks solve, and the easier tricks do not */
    public function test_generated_puzzles_are_unique_and_need_their_level()
    {
        foreach (['easy' => 1, 'medium' => 2, 'hard' => 3] as $level => $trick) {
            $t = microtime(true);
            for ($seed = 1; $seed <= 6; $seed++) {
                $p = Sudoku::generate($level, $seed);
                $this->assertSame($trick, $p['trick'], "$level $seed");
                $this->assertSame(1, $this->solutions($p["grid"]), "$level $seed: one solution");
                $this->assertSame($p['solution'], Sudoku::solve($p['grid'], $trick)['grid']);
                if ($trick > 1) $this->assertNull(Sudoku::solve($p['grid'], $trick - 1), "$level $seed: not easier");
                $given = 81 - substr_count($p['grid'], '0');
                $this->assertGreaterThanOrEqual(Sudoku::LEVELS[$level][1], $given);
                for ($i = 0; $i < 81; $i++) $this->assertSame($p['grid'][$i] === '0', $p['grid'][80 - $i] === '0', 'symmetric');
            }
            $this->assertLessThan(6.0, microtime(true) - $t, "$level: 6 puzzles in time");
        }
    }

    /** TC-UNIT-SD-03 | FR-SUDOKU-03, FR-SUDOKU-04: the same seed gives the same puzzle; a grid is checked against the rules and the givens */
    public function test_seeds_and_the_check()
    {
        $a = Sudoku::generate('medium', 12345);
        $this->assertSame($a, Sudoku::generate('medium', 12345));
        $this->assertNotSame($a['grid'], Sudoku::generate('medium', 12346)['grid']);
        $this->assertTrue(Sudoku::solves($a, $a['solution']));
        $wrong = $a['solution'];
        [$wrong[0], $wrong[1]] = [$wrong[1], $wrong[0]];
        $this->assertFalse(Sudoku::solves($a, $wrong), 'two digits swapped');
        $this->assertFalse(Sudoku::solves($a, substr($a['solution'], 1)), 'too short');
        $this->assertFalse(Sudoku::solves($a, $a['grid']), 'not filled');
        try {
            Sudoku::generate('huge', 1);
            $this->fail('expected bad_level');
        } catch (SudokuError $e) {
            $this->assertSame('bad_level', $e->getMessage());
        }
        $d = Sudoku::dailySeed('easy', '2026-10-07');
        $this->assertSame($d, Sudoku::dailySeed('easy', '2026-10-07'));
        $this->assertNotSame($d, Sudoku::dailySeed('easy', '2026-10-08'));
        $this->assertNotSame($d, Sudoku::dailySeed('hard', '2026-10-07'));
        $this->assertNotSame($d, Sudoku::dailySeed('easy', '2026-10-07', true), 'the guests get another puzzle');
    }
}
