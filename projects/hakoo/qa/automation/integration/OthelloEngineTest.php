<?php
/**
 * Component tests of the Othello rules and computer opponent (src/othello-engine.inc.php).
 * No database or HTTP: the engine is pure logic.
 * Traceability: FR-OTHELLO-01..04.
 */
require_once '/var/www/html/othello-engine.inc.php';

use PHPUnit\Framework\TestCase;

class OthelloEngineTest extends TestCase
{
    private function assertError(string $code, callable $fn): void
    {
        try {
            $fn();
            $this->fail("expected OthelloError $code");
        } catch (OthelloError $e) {
            $this->assertSame($code, $e->getMessage());
        }
    }

    /** Positions after $depth moves, a pass counting as a move (perft). */
    private function perft(OthelloBoard $b, int $who, int $depth, bool $passed = false): int
    {
        if ($depth === 0) return 1;
        $moves = $b->moves($who);
        if (!$moves) return $passed ? 1 : $this->perft($b, 3 - $who, $depth - 1, true);
        $n = 0;
        foreach ($moves as $i) {
            $f = $b->flips($i, $who);
            $b->place($i, $who, $f);
            $n += $this->perft($b, 3 - $who, $depth - 1);
            $b->undo($i, $who, $f);
        }
        return $n;
    }

    /** TC-OTH-001 | FR-OTHELLO-01: the start position; black moves first and has four moves */
    public function test_start_position()
    {
        $g = new OthelloGame();
        $this->assertSame('black', $g->toMove());
        $this->assertSame(['........', '........', '........', '...wb...', '...bw...', '........', '........', '........'], $g->board->rows());
        $this->assertSame([[2, 3], [3, 2], [4, 5], [5, 4]], $g->legal());
        $this->assertSame(['black' => 2, 'white' => 2], $g->discs());
    }

    /** TC-OTH-002 | FR-OTHELLO-01: the move generator matches the published perft counts */
    public function test_perft()
    {
        $b = new OthelloBoard();
        $this->assertSame([4, 12, 56, 244, 1396, 8200], array_map(fn($d) => $this->perft($b, OthelloBoard::BLACK, $d), range(1, 6)));
    }

    /** TC-OTH-003 | FR-OTHELLO-01: every outflanked line is flipped, in every direction */
    public function test_flips_in_every_direction()
    {
        $g = new OthelloGame([
            'b..b..b.',
            '.w.w.w..',
            '..www...',
            'bww.wwwb',
            '..www...',
            '.w.w.w..',
            'b..b..b.',
            '........',
        ]);
        $g->play('black', 3, 3);
        $this->assertSame(['b..b..b.', '.b.b.b..', '..bbb...', 'bbbbbbbb', '..bbb...', '.b.b.b..', 'b..b..b.', '........'], $g->board->rows());
        $this->assertCount(17, $g->lastFlips());
    }

    /** TC-OTH-004 | FR-OTHELLO-02: a move must flip something; taken squares, turns and the board edge are checked */
    public function test_illegal_moves_are_refused()
    {
        $g = new OthelloGame();
        $this->assertError('no_flips', fn() => $g->play('black', 0, 0));
        $this->assertError('occupied', fn() => $g->play('black', 3, 3));
        $this->assertError('not_your_turn', fn() => $g->play('white', 2, 4));
        $this->assertError('outside', fn() => $g->play('black', 8, 0));
        $this->assertSame(0, $g->moveCount(), 'a refused move changes nothing');
    }

    /** TC-OTH-005 | FR-OTHELLO-01: a player without a move passes; the other moves again */
    public function test_pass()
    {
        // Black takes c1; white's only disc left (b8) cannot outflank anything, so white passes.
        $g = new OthelloGame(['bw......', '........', '........', '........', '........', '........', '........', 'bw......']);
        $g->play('black', 0, 2);
        $this->assertFalse($g->isOver());
        $this->assertSame('black', $g->toMove());
        $this->assertSame('white', $g->passed());
        $this->assertSame(['color' => 'white', 'after' => 1], $g->lastPass());
        $this->assertSame([1], $g->passes(), 'every pass, for the move list');
        $g->play('black', 7, 2);
        $this->assertTrue($g->isOver(), 'white has no discs left');
        $this->assertSame('discs', $g->result());
        $this->assertSame('black', $g->winner());
        $this->assertSame(['black' => 6, 'white' => 0], $g->discs());
    }

    /** TC-OTH-006 | FR-OTHELLO-01: when neither player can move, the same number of discs is a draw */
    public function test_draw()
    {
        $g = new OthelloGame(['bw......', '........', '........', '........', '........', '........', '........', 'www.....']);
        $g->play('black', 0, 2);
        $this->assertSame('draw', $g->result());
        $this->assertNull($g->winner());
        $this->assertSame(['black' => 3, 'white' => 3], $g->discs());
    }

    /** TC-OTH-007 | FR-OTHELLO-01: a game can be replayed from its moves (passes are implied) */
    public function test_replay_from_moves()
    {
        $g = new OthelloGame();
        $moves = [];
        $ai = new OthelloAI('easy', new \Random\Randomizer(new \Random\Engine\Mt19937(7)));
        while (!$g->isOver()) {
            [$r, $c] = $ai->choose($g->board, $g->toMove());
            $g->play($g->toMove(), $r, $c);
            $moves[] = [$r, $c];
        }
        $again = OthelloGame::fromMoves($moves);
        $this->assertSame($g->board->rows(), $again->board->rows());
        $this->assertSame($g->result(), $again->result());
        $this->assertSame(64, array_sum($g->discs()) + count(array_filter(str_split(implode('', $g->board->rows())), fn($ch) => $ch === '.')));
    }

    /** TC-OTH-008 | FR-OTHELLO-03: Medium and Hard take a corner */
    public function test_medium_and_hard_take_a_corner()
    {
        $rows = ['.wb.....', '..w.....', '..wbw...', '...bw...', '...bbw..', '.....w..', '......b.', '........'];
        foreach (['medium', 'hard'] as $level) {
            $g = new OthelloGame($rows, 'black');
            $this->assertContains([0, 0], $g->legal());
            $this->assertSame([0, 0], (new OthelloAI($level))->choose($g->board, 'black'), $level);
        }
    }

    /** TC-OTH-009 | FR-OTHELLO-03, NFR: every level answers with a legal move within 3 seconds, the board unchanged */
    public function test_answers_in_time_with_a_legal_move()
    {
        $g = new OthelloGame();                                 // a middle game: 20 moves by Easy
        $easy = new OthelloAI('easy', new \Random\Randomizer(new \Random\Engine\Mt19937(3)));
        for ($k = 0; $k < 20; $k++) $g->play($g->toMove(), ...$easy->choose($g->board, $g->toMove()));
        foreach (OthelloAI::LEVELS as $level) {
            $before = $g->board->rows();
            $t = microtime(true);
            $m = (new OthelloAI($level))->choose($g->board, $g->toMove());
            $this->assertLessThan(3.0, microtime(true) - $t, $level);
            $this->assertContains($m, $g->legal(), $level);
            $this->assertSame($before, $g->board->rows(), 'the search leaves the board as it was');
        }
    }

    /** TC-OTH-010 | FR-OTHELLO-03: harder levels beat easier ones (Medium v Easy, a whole game with each colour) */
    public function test_medium_beats_easy()
    {
        foreach (['black', 'white'] as $k => $mediumPlays) {
            $rng = fn() => new \Random\Randomizer(new \Random\Engine\Mt19937(11 + $k));   // the same games every run
            $easyPlays = $mediumPlays === 'black' ? 'white' : 'black';
            $ai = [$mediumPlays => new OthelloAI('medium', $rng()), $easyPlays => new OthelloAI('easy', $rng())];
            $g = new OthelloGame();
            while (!$g->isOver()) {
                [$r, $c] = $ai[$g->toMove()]->choose($g->board, $g->toMove());
                $g->play($g->toMove(), $r, $c);
            }
            $this->assertSame($mediumPlays, $g->winner(), "Medium as $mediumPlays");
        }
    }
}
