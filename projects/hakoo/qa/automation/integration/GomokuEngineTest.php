<?php
/**
 * Component tests of the Gomoku rules and computer opponent (src/gomoku-engine.inc.php).
 * No database or HTTP: the engine is pure logic.
 * Traceability: FR-GOMOKU-01..04 (school spec FR-06, FR-08, FR-20, SR-01.3, NFR-02.3).
 */
require_once '/var/www/html/gomoku-engine.inc.php';

use PHPUnit\Framework\TestCase;

class GomokuEngineTest extends TestCase
{
    /** Alternate black/white moves; $black and $white are lists of [row, col]. */
    private function play(array $black, array $white, int $size = 15): GomokuGame
    {
        $g = new GomokuGame($size);
        for ($i = 0; $i < max(count($black), count($white)); $i++) {
            if (isset($black[$i])) $g->play(Stone::Black, ...$black[$i]);
            if (isset($white[$i])) $g->play(Stone::White, ...$white[$i]);
        }
        return $g;
    }

    /** White stones far away that never form a line. */
    private function filler(int $n): array
    {
        return array_map(fn($k) => [14, $k * 2], range(0, $n - 1));
    }

    /** TC-GOM-001 | FR-08.8, FR-08.9: five in a row wins, in all four directions */
    public function test_exactly_five_wins_in_every_direction()
    {
        foreach ([[0, 1], [1, 0], [1, 1], [1, -1]] as [$dr, $dc]) {
            $black = array_map(fn($k) => [5 + $k * $dr, 5 + $k * $dc], range(0, 4));
            $g = $this->play($black, $this->filler(4));
            $this->assertTrue($g->isOver(), "direction $dr,$dc");
            $this->assertSame(Stone::Black, $g->winner());
            $this->assertSame('five', $g->result());
            $this->assertCount(5, $g->winLine());
        }
    }

    /** TC-GOM-002 | FR-08.15, SR-01.3: six in a row is not a win (overline) */
    public function test_six_in_a_row_is_not_a_win()
    {
        // Black: 0-2 and 4-5 on row 3, then fills column 3 -> six in a row.
        $g = $this->play([[3, 0], [3, 1], [3, 2], [3, 4], [3, 5], [3, 3]], $this->filler(5));
        $this->assertFalse($g->isOver());
        $this->assertNull($g->winner());
    }

    /** TC-GOM-003 | FR-08.14, AC-02-06b: filling a gap to exactly five wins */
    public function test_filling_a_gap_to_exactly_five_wins()
    {
        $g = $this->play([[3, 0], [3, 1], [3, 3], [3, 4], [3, 2]], $this->filler(4));
        $this->assertSame(Stone::Black, $g->winner());
        $this->assertSame([[3, 0], [3, 1], [3, 2], [3, 3], [3, 4]], $g->winLine());
    }

    /** TC-GOM-004 | FR-08.14/16: an overline in one direction does not hide an exact five in another */
    public function test_exact_five_counts_even_when_the_stone_also_makes_an_overline()
    {
        $black = [[7, 1], [7, 2], [7, 3], [7, 5], [7, 6], [3, 4], [4, 4], [5, 4], [6, 4], [7, 4]];
        $white = [[0, 0], [0, 2], [0, 6], [0, 8], [0, 10], [0, 12], [2, 0], [2, 2], [2, 10]];
        $g = $this->play($black, $white);
        $this->assertSame(Stone::Black, $g->winner());
        $this->assertSame([[3, 4], [4, 4], [5, 4], [6, 4], [7, 4]], $g->winLine());
    }

    /** TC-GOM-005 | FR-08.2: an occupied point is rejected and the turn does not change */
    public function test_occupied_point_is_rejected()
    {
        $g = $this->play([[7, 7]], []);
        try {
            $g->play(Stone::White, 7, 7);
            $this->fail('expected occupied');
        } catch (GomokuError $e) {
            $this->assertSame('occupied', $e->getMessage());
        }
        $this->assertSame(Stone::White, $g->toMove());
        $this->assertSame(1, $g->moveCount());
    }

    /** TC-GOM-006 | FR-08.3, FR-04.3: black moves first; a move out of turn is rejected */
    public function test_move_out_of_turn_is_rejected()
    {
        $g = new GomokuGame(15);
        $this->assertSame(Stone::Black, $g->toMove());
        $this->expectExceptionMessage('not_your_turn');
        $g->play(Stone::White, 7, 7);
    }

    /** TC-GOM-007 | FR-08.13: no moves after the game is over */
    public function test_no_moves_after_a_win()
    {
        $g = $this->play(array_map(fn($k) => [1, $k], range(0, 4)), $this->filler(4));
        $this->expectExceptionMessage('game_over');
        $g->play(Stone::White, 10, 10);
    }

    /** TC-GOM-008 | FR-08.11, FR-20.2: a full board without five is a draw */
    public function test_full_board_without_five_is_a_draw()
    {
        // Colour = (col div 2 + row) mod 2: longest run is 2 in every direction; 113 black, 112 white.
        $black = $white = [];
        for ($r = 0; $r < 15; $r++) for ($c = 0; $c < 15; $c++) {
            if ((intdiv($c, 2) + $r) % 2 === 0) $black[] = [$r, $c]; else $white[] = [$r, $c];
        }
        $g = $this->play($black, $white);
        $this->assertTrue($g->isOver());
        $this->assertSame('draw', $g->result());
        $this->assertNull($g->winner());
        $this->assertSame(225, $g->moveCount());
    }

    /** TC-GOM-009 | FR-03.2: only 15x15 and 19x19 boards; moves must be on the board */
    public function test_board_size_and_bounds()
    {
        $this->assertSame(19, (new GomokuGame(19))->size);
        try { new GomokuGame(13); $this->fail('13x13 accepted'); } catch (GomokuError $e) { $this->assertSame('bad_size', $e->getMessage()); }
        $this->expectExceptionMessage('outside');
        (new GomokuGame(15))->play(Stone::Black, 15, 0);
    }

    /** TC-GOM-010 | FR-06.3: the computer only plays on empty points, on every level */
    public function test_computer_plays_a_legal_move_on_every_level()
    {
        foreach (GomokuAI::LEVELS as $level) {
            $g = $this->play([[7, 7], [7, 8]], [[8, 8]]);
            [$r, $c] = (new GomokuAI($level))->choose($g->board, Stone::White);
            $this->assertTrue($g->board->inside($r, $c), $level);
            $this->assertTrue($g->board->isEmpty($r, $c), $level);
        }
    }

    /** TC-GOM-011 | FR-06.2: the computer opens in the centre when it plays black */
    public function test_computer_opens_in_the_centre()
    {
        $this->assertSame([7, 7], (new GomokuAI('hard'))->choose(new GomokuBoard(15), Stone::Black));
        $this->assertSame([9, 9], (new GomokuAI('easy'))->choose(new GomokuBoard(19), Stone::Black));
    }

    /** TC-GOM-012 | FR-06.5: the computer takes its own five, on every level */
    public function test_computer_completes_its_own_five()
    {
        foreach (GomokuAI::LEVELS as $level) {
            // White (computer) has four on row 2; black threatens elsewhere.
            $g = $this->play([[10, 1], [10, 2], [10, 3], [12, 12], [13, 13]], [[2, 3], [2, 4], [2, 5], [2, 6]]);
            $move = (new GomokuAI($level))->choose($g->board, Stone::White);
            $this->assertContains($move, [[2, 2], [2, 7]], $level);
        }
    }

    /** TC-GOM-013 | medium and hard block the opponent's four */
    public function test_computer_blocks_a_four()
    {
        foreach (['medium', 'hard'] as $level) {
            $g = $this->play([[5, 5], [6, 5], [7, 5], [8, 5]], [[5, 6], [0, 0], [0, 14]]);
            // Black has four in column 5, open at (4,5) and (9,5): white must block one end.
            $move = (new GomokuAI($level))->choose($g->board, Stone::White);
            $this->assertContains($move, [[4, 5], [9, 5]], $level);
        }
    }

    /** TC-GOM-014 | medium and hard block an open three */
    public function test_computer_blocks_an_open_three()
    {
        foreach (['medium', 'hard'] as $level) {
            $g = $this->play([[7, 5], [7, 6], [7, 7]], [[6, 6], [0, 14]]);
            $move = (new GomokuAI($level))->choose($g->board, Stone::White);
            $this->assertContains($move, [[7, 3], [7, 4], [7, 8], [7, 9]], $level);
        }
    }

    /** TC-GOM-015 | SR-01.3: the computer does not treat a move that makes six as a win */
    public function test_computer_does_not_count_an_overline_as_a_win()
    {
        // White has 0-3 and 5 on row 4: (4,4) would make six. The only real five is on row 9.
        $g = $this->play([[12, 0], [12, 2], [12, 4], [12, 6], [12, 8], [12, 10]],
                         [[4, 0], [4, 1], [4, 2], [4, 3], [4, 5], [9, 1]]);
        $b = $g->board;
        foreach ([[9, 2], [9, 3], [9, 4]] as [$r, $c]) $b->place($r, $c, Stone::White);   // white: 9,1-4
        $move = (new GomokuAI('medium'))->choose($b, Stone::White);
        $this->assertContains($move, [[9, 0], [9, 5]]);
    }

    /** TC-GOM-016 | NFR-02.3: the hardest level answers within 3 seconds on a busy 19x19 board */
    public function test_hard_level_answers_within_three_seconds()
    {
        $rng = new \Random\Randomizer(new \Random\Engine\Mt19937(42));
        $g = new GomokuGame(19);
        while ($g->moveCount() < 60 && !$g->isOver()) {
            $r = $rng->getInt(3, 15);
            $c = $rng->getInt(3, 15);
            if ($g->board->isEmpty($r, $c)) $g->play($g->toMove(), $r, $c);
        }
        $t = microtime(true);
        (new GomokuAI('hard'))->choose($g->board, $g->toMove());
        $this->assertLessThan(3.0, microtime(true) - $t);
    }

    /** TC-GOM-017 | FR-05.1: harder levels are stronger (computer against computer, seeded) */
    public function test_harder_levels_are_stronger()
    {
        [$won, $lost] = $this->duel('medium', 'easy', 6);
        $this->assertGreaterThanOrEqual(5, $won, 'medium vs easy');
        [$won, $lost] = $this->duel('hard', 'medium', 12);
        $this->assertGreaterThan($lost, $won, 'hard vs medium');
    }

    /**
     * [wins, losses] for level $a against $b, alternating colours. Each game opens
     * with a seeded random black stone near the centre, so the games differ.
     */
    private function duel(string $a, string $b, int $games): array
    {
        $won = $lost = 0;
        for ($i = 0; $i < $games; $i++) {
            $open = new \Random\Randomizer(new \Random\Engine\Mt19937(7000 + $i));
            $ai = [new GomokuAI($a, new \Random\Randomizer(new \Random\Engine\Mt19937(100 + $i))),
                   new GomokuAI($b, new \Random\Randomizer(new \Random\Engine\Mt19937(200 + $i)))];
            $aColor = $i % 2 === 0 ? Stone::Black : Stone::White;
            $g = new GomokuGame(15);
            $g->play(Stone::Black, $open->getInt(5, 9), $open->getInt(5, 9));
            while (!$g->isOver()) {
                $who = $g->toMove();
                $g->play($who, ...$ai[$who === $aColor ? 0 : 1]->choose($g->board, $who));
            }
            if ($g->winner() === $aColor) $won++;
            elseif ($g->winner() !== null) $lost++;
        }
        return [$won, $lost];
    }
}
