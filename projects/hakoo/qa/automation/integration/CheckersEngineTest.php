<?php
/**
 * Component tests of the Checkers rules and computer opponent (src/checkers-engine.inc.php).
 * No database or HTTP: the engine is pure logic.
 * Traceability: FR-CHECKERS-01..06.
 */
require_once '/var/www/html/checkers-engine.inc.php';

use PHPUnit\Framework\TestCase;

class CheckersEngineTest extends TestCase
{
    private function assertError(string $code, callable $fn): void
    {
        try {
            $fn();
            $this->fail("expected CheckersError $code");
        } catch (CheckersError $e) {
            $this->assertSame($code, $e->getMessage());
        }
    }

    /** Positions after $depth moves (perft). */
    private function perft(array $cells, int $side, bool $intl, int $depth): int
    {
        if ($depth === 0) return 1;
        $n = 0;
        foreach (CheckersRules::moves($cells, $side, $intl) as $m) {
            $c = $cells;
            CheckersRules::apply($c, $m);
            $n += $this->perft($c, -$side, $intl, $depth - 1);
        }
        return $n;
    }

    /** The legal moves as lists of [row, col]. */
    private function paths(CheckersGame $g): array
    {
        $out = array_map(fn($m) => array_map(fn($i) => CheckersGame::rc($i), $m[0]), $g->legalMoves());
        sort($out);
        return $out;
    }

    /** TC-UNIT-CK-01 | FR-CHECKERS-01: the start position; English: black first, the move generator matches the published perft counts */
    public function test_start_and_english_perft()
    {
        $g = new CheckersGame('english');
        $this->assertSame('black', $g->toMove());
        $this->assertSame(['.b.b.b.b', 'b.b.b.b.', '.b.b.b.b', '........', '........', 'w.w.w.w.', '.w.w.w.w', 'w.w.w.w.'], $g->rows());
        $this->assertSame(['black' => 12, 'white' => 12], $g->pieces());
        $this->assertSame([7, 49, 302, 1469, 7361, 36768],
            array_map(fn($d) => $this->perft(CheckersRules::start(), 1, false, $d), range(1, 6)));
    }

    /** TC-UNIT-CK-02 | FR-CHECKERS-01: flying kings: white moves first; the published perft counts of 8x8 draughts with flying kings */
    public function test_international_start_and_perft()
    {
        $g = new CheckersGame('international');
        $this->assertSame('white', $g->toMove());
        $this->assertSame([7, 49, 302, 1469, 7473],
            array_map(fn($d) => $this->perft(CheckersRules::start(), -1, true, $d), range(1, 5)));
        $this->assertError('bad_variant', fn() => new CheckersGame('russian'));
    }

    /** TC-UNIT-CK-03 | FR-CHECKERS-02: taking is compulsory; wrong turns, empty squares and the other side's pieces are refused */
    public function test_taking_is_compulsory()
    {
        $rows = ['........', '........', '........', '........', '...b....', '..w...w.', '........', '........'];
        $g = new CheckersGame('english', $rows, 'white');
        $this->assertSame([[[5, 2], [3, 4]]], $this->paths($g));
        $this->assertError('must_take', fn() => $g->play('white', [[5, 6], [4, 7]]));
        $this->assertError('must_take', fn() => $g->play('white', [[5, 2], [4, 1]]));
        $this->assertError('not_your_turn', fn() => $g->play('black', [[4, 3], [5, 4]]));
        $this->assertError('no_piece', fn() => $g->play('white', [[5, 4], [4, 5]]));
        $this->assertError('not_your_piece', fn() => $g->play('white', [[4, 3], [5, 4]]));
        $this->assertError('bad_path', fn() => $g->play('white', [[5, 2]]));
        $this->assertError('bad_path', fn() => $g->play('white', [[5, 2], [3, 9]]));
        $g->play('white', [[5, 2], [3, 4]]);
        $this->assertSame(['black' => 0, 'white' => 2], $g->pieces());
        $this->assertSame(['no_pieces', 'white'], [$g->result, $g->winner]);
    }

    /** TC-UNIT-CK-04 | FR-CHECKERS-02, FR-CHECKERS-03: a jump must go on while it can; English allows the shorter jump, flying kings must take the most */
    public function test_multi_jumps_and_the_majority_rule()
    {
        $rows = ['........', '........', '........', '....b...', '........', '..b...b.', '.w...w..', '........'];
        $g = new CheckersGame('english', $rows, 'white');
        $this->assertSame([[[6, 5], [4, 7]], [[6, 1], [4, 3], [2, 5]]], $this->paths($g));          // shorter paths sort first
        $this->assertError('must_continue', fn() => $g->play('white', [[6, 1], [4, 3]]));
        $g->play('white', [[6, 5], [4, 7]]);                               // English: any jump
        $this->assertSame(['........', '........', '........', '....b...', '.......w', '..b.....', '.w......', '........'], $g->rows());

        $g = new CheckersGame('international', $rows, 'white');
        $this->assertSame([[[6, 1], [4, 3], [2, 5]]], $this->paths($g));
        $this->assertError('must_take_most', fn() => $g->play('white', [[6, 5], [4, 7]]));
        $g->play('white', [[6, 1], [4, 3], [2, 5]]);
        $this->assertSame([[5, 2], [3, 4]], array_map(fn($i) => CheckersGame::rc($i), end($g->history)[1]));
    }

    /** TC-UNIT-CK-05 | FR-CHECKERS-04: crowning: English stops the jump on the far row; with flying kings a man jumps on (backwards) and is only crowned where it ends */
    public function test_crowning()
    {
        $rows = ['........', '..b.b...', '.w......', '........', '........', '........', '........', '........'];
        $g = new CheckersGame('english', $rows, 'white');
        $this->assertSame([[[2, 1], [0, 3]]], $this->paths($g));
        $g->play('white', [[2, 1], [0, 3]]);
        $this->assertSame('...W....', $g->rows()[0]);
        $this->assertFalse($g->isOver());

        $g = new CheckersGame('international', $rows, 'white');
        $this->assertSame([[[2, 1], [0, 3], [2, 5]]], $this->paths($g));
        $g->play('white', [[2, 1], [0, 3], [2, 5]]);
        $this->assertSame('.....w..', $g->rows()[2], 'it passed the far row: still a man');
        $this->assertSame(['no_pieces', 'white'], [$g->result, $g->winner]);
    }

    /** TC-UNIT-CK-06 | FR-CHECKERS-03, FR-CHECKERS-04: kings: English steps one square; a flying king moves and takes at any distance; men take backwards */
    public function test_kings_and_backward_takes()
    {
        $g = new CheckersGame('english', ['.......b', '........', '........', '........', '........', '........', '........', 'W.......'], 'white');
        $this->assertSame([[[7, 0], [6, 1]]], $this->paths($g));
        $g = new CheckersGame('international', ['.......b', '........', '........', '........', '........', '........', '........', 'W.......'], 'white');
        $this->assertCount(6, $g->legalMoves(), 'a flying king goes as far as the board is empty');

        $rows = ['........', '........', '........', '....b...', '.......w', '......b.', '........', 'W.......'];
        $g = new CheckersGame('international', $rows, 'white');
        $this->assertSame([[[4, 7], [6, 5]], [[7, 0], [0, 7]], [[7, 0], [1, 6]], [[7, 0], [2, 5]]], $this->paths($g));
        $g = new CheckersGame('english', $rows, 'white');
        $this->assertNotContains([[4, 7], [6, 5]], $this->paths($g), 'English men do not take backwards');
        $this->assertSame([], array_filter($g->legalMoves(), fn($m) => $m[1]), 'nothing to take: the king is too far away');
    }

    /** TC-UNIT-CK-07 | FR-CHECKERS-05: a player who cannot move loses; the third repetition and 40 moves each without taking or a man moving are draws */
    public function test_game_ends()
    {
        $g = new CheckersGame('english', ['.b......', '........', '........', '........', '........', '....b...', '.....b.b', '......w.'], 'black');
        $g->play('black', [[0, 1], [1, 2]]);
        $this->assertSame(['no_moves', 'black'], [$g->result, $g->winner]);
        $this->assertError('game_over', fn() => $g->play('white', [[7, 6], [6, 5]]));

        $g = new CheckersGame('english', ['.......B', '........', '........', '........', '........', '........', '........', 'W.......'], 'white');
        $shuffle = [['white', [[7, 0], [6, 1]]], ['black', [[0, 7], [1, 6]]], ['white', [[6, 1], [7, 0]]], ['black', [[1, 6], [0, 7]]]];
        foreach ([...$shuffle, ...$shuffle] as $k => [$who, $m]) {
            $this->assertFalse($g->isOver(), "move $k");
            $g->play($who, $m);
        }
        $this->assertSame(['repetition', null], [$g->result, $g->winner]);

        $g = new CheckersGame('english', ['.......B', '........', '........', '........', '........', '........', '........', 'W.......'], 'white');
        $g->quiet = CheckersGame::QUIET_LIMIT - 1;
        $g->play('white', [[7, 0], [6, 1]]);
        $this->assertSame('draw', $g->result);
    }

    /** TC-UNIT-CK-08 | FR-CHECKERS-06: every level answers with a legal move in time and takes the bigger catch when it can */
    public function test_computer()
    {
        foreach (['english', 'international'] as $variant) {
            foreach (CheckersAI::LEVELS as $level) {
                $g = new CheckersGame($variant);
                $t = microtime(true);
                $m = (new CheckersAI($level))->choose($g);
                $this->assertLessThan(3.0, microtime(true) - $t, "$variant $level");
                $g->play($g->toMove(), $m);
                $this->assertCount(1, $g->history);
            }
        }
        // English: white may take one piece or two (and loses a man either way): Medium and Hard take two.
        $rows = ['........', '........', '........', '....b...', '........', '..b...b.', '.w...w..', '........'];
        foreach (['medium', 'hard'] as $level) {
            $g = new CheckersGame('english', $rows, 'white');
            $this->assertSame([[6, 1], [4, 3], [2, 5]], (new CheckersAI($level))->choose($g), $level);
        }
    }
}
