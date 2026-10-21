<?php
/**
 * Component tests of the Backgammon rules and computer opponent (src/backgammon-engine.inc.php).
 * No database or HTTP: the engine is pure logic.
 * Test design: equivalence classes of a step (free point, blot, blocked point, the bar, bearing off)
 * and the boundaries of the dice rules (both dice, only one, the higher one, doubles, no move).
 * Traceability: FR-BACKGAMMON-01..05.
 */
require_once '/var/www/html/backgammon-engine.inc.php';

use PHPUnit\Framework\TestCase;

class BackgammonEngineTest extends TestCase
{
    /** A position: [point => checkers] per seat in the seat's own numbering; the rest are borne off. */
    private function game(array $black, array $white, string $toMove = 'black'): BackgammonGame
    {
        $pts = [];
        foreach (['black' => $black, 'white' => $white] as $seat => $on) {
            $a = array_fill(0, 26, 0);
            foreach ($on as $p => $n) $a[$p] = $n;
            $a[0] = 15 - array_sum($a);
            $pts[$seat] = $a;
        }
        return new BackgammonGame($toMove, $pts);
    }

    private function assertError(string $code, callable $fn): void
    {
        try {
            $fn();
            $this->fail("expected BackgammonError $code");
        } catch (BackgammonError $e) {
            $this->assertSame($code, $e->getMessage());
        }
    }

    /** Every legal play as a sorted list of step lists (order inside a play kept). */
    private function plays(BackgammonGame $g, int $a, int $b): array
    {
        $p = array_values($g->plays($g->toMove, $a, $b));
        sort($p);
        return $p;
    }

    /** TC-UNIT-BG-01 | FR-BACKGAMMON-01: the start position, the pip count and the opening 3-1 */
    public function test_start_position()
    {
        $g = new BackgammonGame('white');
        $this->assertSame('white', $g->toMove);
        $this->assertSame(167, $g->pips('black'));
        $this->assertSame(167, $g->pips('white'));
        $this->assertTrue($g->contact());
        $plays = $g->plays('white', 3, 1);
        $this->assertArrayHasKey((function () { $h = new BackgammonGame('white'); $h->apply('white', 8, 5); $h->apply('white', 6, 5); return $h->key(); })(), $plays,
            '8/5 6/5 makes the five point');
        $g->play(3, 1, [[8, 5], [6, 5]]);
        $this->assertSame('black', $g->toMove);
        $this->assertSame(2, $g->pts['white'][5]);
        $this->assertSame([[3, 1, [[8, 5], [6, 5]]]], $g->turns);
    }

    /** TC-UNIT-BG-02 | FR-BACKGAMMON-02: a point held by two is closed; a blot is hit to the bar */
    public function test_blocked_points_and_hits()
    {
        // White holds its 20 point (= black's 5) and has a blot on its 22 (= black's 3).
        $g = $this->game([8 => 1, 6 => 14], [20 => 2, 22 => 1, 1 => 12]);
        $this->assertSame([[8, 4], [6, 2]], $g->steps('black', 4));
        $this->assertSame([], array_filter($g->steps('black', 3), fn($s) => $s === [8, 5]), 'the 5 point is closed');
        $this->assertContains([6, 3], $g->steps('black', 3));
        $g->play(3, 2, [[6, 3], [8, 6]]);
        $this->assertSame(1, $g->pts['white'][25], 'the blot went to the bar');
        $this->assertSame(0, $g->pts['white'][22]);
        $this->assertSame([[3]], $g->hits);
    }

    /** TC-UNIT-BG-03 | FR-BACKGAMMON-02: a checker on the bar must come in first; a closed board means no move */
    public function test_entering_from_the_bar()
    {
        // Black on the bar; white holds its 1, 2 and 3 (= black's entry points 24, 23, 22).
        $g = $this->game([25 => 1, 13 => 14], [1 => 2, 2 => 2, 3 => 2, 6 => 9]);
        $this->assertSame([], $g->steps('black', 1));
        $this->assertSame([[25, 21]], $g->steps('black', 4));
        $this->assertSame([[[25, 21], [13, 12]], [[25, 21], [21, 20]]], $this->plays($g, 4, 1), 'nothing else moves while on the bar');
        $this->assertSame([], $g->plays('black', 1, 2));
        $this->assertError('must_move', fn() => $g->pass(4, 1));
        $g->pass(1, 2);
        $this->assertSame([[1, 2, []]], $g->turns);
        $this->assertSame('white', $g->toMove);
        // A closed board: no roll can come in.
        $c = $this->game([25 => 2, 13 => 13], [1 => 2, 2 => 2, 3 => 2, 4 => 2, 5 => 2, 6 => 2, 13 => 3]);
        foreach ([[6, 6], [1, 5], [3, 4]] as [$a, $b]) $this->assertSame([], $c->plays('black', $a, $b));
    }

    /** TC-UNIT-BG-04 | FR-BACKGAMMON-03: as many dice as possible; with only one usable, the higher one */
    public function test_dice_must_be_used()
    {
        // Black's 8 is closed (white's 17): 6-5 can only be played as 13/7/2, and both dice must be used.
        $g = $this->game([13 => 1, 1 => 14], [17 => 2, 2 => 13]);
        $this->assertSame([[[13, 7], [7, 2]]], $this->plays($g, 6, 5));
        $this->assertError('use_all_dice', fn() => $g->play(6, 5, [[13, 7]]));
        // Black's 2 is closed (white's 23): 13/7 or 13/8, never both; the higher die must be played.
        $k = $this->game([13 => 1, 1 => 14], [23 => 2, 2 => 13]);
        $this->assertSame([[[13, 7]]], $this->plays($k, 6, 5), 'the 6, not the 5');
        $this->assertError('illegal_play', fn() => $k->play(6, 5, [[13, 8]]));
        // Black's 7 is closed too (white's 18): the 6 cannot be played at all, so the 5 is.
        $h = $this->game([13 => 1, 1 => 14], [18 => 2, 23 => 2, 2 => 11]);
        $this->assertSame([[[13, 8]]], $this->plays($h, 6, 5));
    }

    /** TC-UNIT-BG-05 | FR-BACKGAMMON-03: a double is played four times */
    public function test_doubles()
    {
        $g = new BackgammonGame('black');
        $plays = $g->plays('black', 6, 6);
        foreach ($plays as $p) $this->assertCount(4, $p);
        $g->play(6, 6, [[24, 18], [24, 18], [13, 7], [13, 7]]);
        $this->assertSame([2, 2, 3], [$g->pts['black'][18], $g->pts['black'][7], $g->pts['black'][13]]);
        $this->assertError('too_many_steps', fn() => (new BackgammonGame('black'))->play(3, 1, [[8, 5], [6, 5], [6, 5]]));
        $this->assertError('illegal_play', fn() => (new BackgammonGame('black'))->play(3, 1, [[8, 4], [6, 5]]));
        $this->assertError('bad_step', fn() => (new BackgammonGame('black'))->play(3, 1, [[8, 5], ['6', 5]]));
    }

    /** TC-UNIT-BG-06 | FR-BACKGAMMON-04: bearing off: exact die, a higher die from the highest point, none while a checker is out */
    public function test_bearing_off()
    {
        $g = $this->game([5 => 2, 3 => 1], [19 => 15]);
        $this->assertSame([[5, 0]], array_values(array_filter($g->steps('black', 6), fn($s) => $s[1] === 0)), 'a 6 bears off from the highest point (5)');
        $this->assertContains([3, 0], $g->steps('black', 3));
        $this->assertNotContains([3, 0], $g->steps('black', 4), 'a 4 must move from the 5 point first');
        $this->assertContains([5, 1], $g->steps('black', 4));
        $out = $this->game([7 => 1, 2 => 2], [19 => 15]);
        $this->assertSame([], array_filter($out->steps('black', 2), fn($s) => $s[1] === 0), 'no bearing off with a checker on the 7');
    }

    /** TC-UNIT-BG-07 | FR-BACKGAMMON-05: the win, worth 1 (single), 2 (gammon) or 3 (backgammon) */
    public function test_results()
    {
        $single = $this->game([1 => 1], [6 => 1]);
        $single->play(2, 1, [[1, 0]]);
        $this->assertSame(['black', 'single'], [$single->winner, $single->result]);

        $gammon = $this->game([2 => 2], [6 => 15]);
        $gammon->play(2, 2, [[2, 0], [2, 0]]);
        $this->assertSame(['black', 'gammon'], [$gammon->winner, $gammon->result]);

        $bg = $this->game([1 => 1], [6 => 14, 22 => 1]);           // white's 22 = black's home board 3
        $bg->play(1, 2, [[1, 0]]);
        $this->assertSame('backgammon', $bg->result);
        $this->assertError('game_over', fn() => $bg->play(1, 2, []));
        $this->assertSame(BackgammonGame::RESULTS['backgammon'], 3);
    }

    /** TC-UNIT-BG-08 | FR-BACKGAMMON-01: a game is the same after storing and replaying its turns */
    public function test_replay_from_turns()
    {
        $g = new BackgammonGame('white');
        $ai = new BackgammonAI('medium');
        mt_srand(3);
        for ($i = 0; $i < 30 && !$g->isOver(); $i++) {
            [$a, $b] = [mt_rand(1, 6), mt_rand(1, 6)];
            $steps = $ai->choose($g, $a, $b);
            $steps ? $g->play($a, $b, $steps) : $g->pass($a, $b);
        }
        $r = BackgammonGame::fromTurns('white', json_decode(json_encode($g->turns), true));
        $this->assertSame($g->key(), $r->key());
        $this->assertSame($g->toMove, $r->toMove);
        $this->assertSame($g->hits, $r->hits);
    }

    /**
     * TC-UNIT-BG-09 | FR-BACKGAMMON-06: every level plays only legal moves, whole games end with a winner,
     * and every checker is always somewhere (15 per side).
     */
    public function test_computer_plays_whole_games()
    {
        mt_srand(11);
        foreach (['easy', 'medium', 'hard'] as $level) {
            $g = new BackgammonGame('black');
            $ai = ['black' => new BackgammonAI($level), 'white' => new BackgammonAI('easy')];
            for ($i = 0; $i < 600 && !$g->isOver(); $i++) {
                [$a, $b] = [mt_rand(1, 6), mt_rand(1, 6)];
                $steps = $ai[$g->toMove]->choose($g, $a, $b);
                $steps ? $g->play($a, $b, $steps) : $g->pass($a, $b);
                $this->assertSame(15, array_sum($g->pts['black']));
                $this->assertSame(15, array_sum($g->pts['white']));
            }
            $this->assertTrue($g->isOver(), "$level: the game ends");
            $this->assertContains($g->result, ['single', 'gammon', 'backgammon']);
        }
    }

    /** TC-UNIT-BG-10 | FR-BACKGAMMON-06: Medium beats Easy most of the time, and hits a blot it can hit safely */
    public function test_medium_is_better_than_easy()
    {
        mt_srand(7);
        $wins = 0;
        for ($n = 0; $n < 20; $n++) {
            $g = new BackgammonGame($n % 2 ? 'black' : 'white');
            $ai = ['black' => new BackgammonAI('medium'), 'white' => new BackgammonAI('easy')];
            while (!$g->isOver()) {
                [$a, $b] = [mt_rand(1, 6), mt_rand(1, 6)];
                $steps = $ai[$g->toMove]->choose($g, $a, $b);
                $steps ? $g->play($a, $b, $steps) : $g->pass($a, $b);
            }
            if ($g->winner === 'black') $wins++;
        }
        $this->assertGreaterThanOrEqual(14, $wins, "medium won $wins of 20");
    }
}
