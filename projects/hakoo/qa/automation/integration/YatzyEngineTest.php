<?php
/**
 * Component tests of the Yatzy rules, scores and computer players (src/yatzy-engine.inc.php).
 * No database or HTTP: the engine is pure logic.
 * Test design (qa/test-design/): equivalence partitions per row (fits / does not fit) with the boundary
 * hands (five of a kind for two pairs and full house, the two straights, a pair beside three of a kind),
 * boundary values for the bonus (62 / 63) and the rolls of a turn (0, 1, 3, a 4th), and the state
 * transitions of a turn (roll -> roll -> roll -> write; write before a roll; keeping dice on the first roll).
 * Traceability: FR-YATZY-01..07.
 */
require_once '/var/www/html/yatzy-engine.inc.php';

use PHPUnit\Framework\TestCase;

class YatzyEngineTest extends TestCase
{
    private function cat(string $name): int
    {
        return array_search($name, Yatzy::CATS, true);
    }

    private function assertError(string $code, callable $fn): void
    {
        try {
            $fn();
            $this->fail("expected YatzyError $code");
        } catch (YatzyError $e) {
            $this->assertSame($code, $e->getMessage());
        }
    }

    /** TC-UNIT-YATZY-01 | FR-YATZY-02: ones to sixes count only their own number; a missing number scores 0 */
    public function test_upper_rows()
    {
        $this->assertSame(12, Yatzy::score($this->cat('fours'), [4, 4, 4, 2, 6]));
        $this->assertSame(30, Yatzy::score($this->cat('sixes'), [6, 6, 6, 6, 6]));
        $this->assertSame(1, Yatzy::score($this->cat('ones'), [1, 2, 3, 4, 5]));
        $this->assertSame(0, Yatzy::score($this->cat('twos'), [1, 3, 3, 5, 6]));
    }

    /** TC-UNIT-YATZY-02 | FR-YATZY-03: pairs, two pairs, three and four of a kind (the highest; two pairs of different numbers) */
    public function test_equal_dice_rows()
    {
        [$pair, $two, $three, $four] = [$this->cat('pair'), $this->cat('twoPairs'), $this->cat('three'), $this->cat('four')];
        $this->assertSame(10, Yatzy::score($pair, [3, 3, 5, 5, 1]), 'the highest pair');
        $this->assertSame(12, Yatzy::score($pair, [6, 6, 6, 2, 2]), 'a pair out of three of a kind');
        $this->assertSame(0, Yatzy::score($pair, [1, 2, 3, 4, 6]));
        $this->assertSame(16, Yatzy::score($two, [5, 5, 3, 3, 1]));
        $this->assertSame(22, Yatzy::score($two, [6, 6, 5, 5, 5]), 'a full house holds two pairs');
        $this->assertSame(0, Yatzy::score($two, [4, 4, 4, 4, 2]), 'four of a kind is not two pairs');
        $this->assertSame(0, Yatzy::score($two, [4, 4, 4, 4, 4]), 'five of a kind is not two pairs');
        $this->assertSame(6, Yatzy::score($three, [2, 2, 2, 6, 4]));
        $this->assertSame(15, Yatzy::score($three, [5, 5, 5, 5, 5]), 'five of a kind counts as three');
        $this->assertSame(0, Yatzy::score($three, [2, 2, 6, 6, 4]));
        $this->assertSame(24, Yatzy::score($four, [6, 6, 6, 6, 1]));
        $this->assertSame(0, Yatzy::score($four, [6, 6, 6, 1, 1]));
    }

    /** TC-UNIT-YATZY-03 | FR-YATZY-04: straights, full house, chance and Yatzy, with the hands just outside them */
    public function test_fixed_and_sum_rows()
    {
        [$small, $large, $house, $chance, $yatzy] = array_map([$this, 'cat'], ['small', 'large', 'house', 'chance', 'yatzy']);
        $this->assertSame(15, Yatzy::score($small, [5, 3, 1, 2, 4]));
        $this->assertSame(0, Yatzy::score($small, [2, 3, 4, 5, 6]), '2-6 is not the small straight');
        $this->assertSame(20, Yatzy::score($large, [6, 4, 2, 5, 3]));
        $this->assertSame(0, Yatzy::score($large, [1, 2, 3, 4, 5]));
        $this->assertSame(0, Yatzy::score($large, [2, 3, 4, 5, 5]));
        $this->assertSame(19, Yatzy::score($house, [3, 3, 3, 5, 5]));
        $this->assertSame(0, Yatzy::score($house, [3, 3, 3, 3, 3]), 'five of a kind is not a full house');
        $this->assertSame(0, Yatzy::score($house, [3, 3, 3, 3, 5]));
        $this->assertSame(21, Yatzy::score($chance, [6, 5, 4, 4, 2]));
        $this->assertSame(5, Yatzy::score($chance, [1, 1, 1, 1, 1]), 'the lowest chance');
        $this->assertSame(50, Yatzy::score($yatzy, [2, 2, 2, 2, 2]));
        $this->assertSame(0, Yatzy::score($yatzy, [2, 2, 2, 2, 3]));
    }

    /** TC-UNIT-YATZY-04 | FR-YATZY-02: the bonus of 50 starts at 63 in ones to sixes (62: none); the best sheet is 374 */
    public function test_bonus_boundary_and_maximum()
    {
        $sheet = array_fill(0, 15, 0);
        [$sheet[0], $sheet[1], $sheet[2], $sheet[3], $sheet[4], $sheet[5]] = [3, 6, 9, 12, 15, 17];   // 62
        $this->assertSame(['upper' => 62, 'bonus' => 0, 'total' => 62], Yatzy::totals($sheet));
        $sheet[5] = 18;                                                                                 // 63
        $this->assertSame(['upper' => 63, 'bonus' => 50, 'total' => 113], Yatzy::totals($sheet));
        $best = [5, 10, 15, 20, 25, 30, 12, 22, 18, 24, 15, 20, 28, 30, 50];
        $this->assertSame(374, Yatzy::totals($best)['total']);
        $this->assertSame(0, Yatzy::totals(array_fill(0, 15, null))['total'], 'empty rows count as 0');
    }

    /** TC-UNIT-YATZY-05 | FR-YATZY-01: a turn is at most three rolls; the first keeps nothing; kept dice stay; a 4th roll is refused */
    public function test_rolls_of_a_turn()
    {
        $g = new YatzyGame(new YatzyRules(), 2, 0);
        $this->assertError('roll_first', fn() => $g->write(0));
        $this->assertError('bad_hold', fn() => $g->roll([0], [1, 2, 3, 4]));
        $g->roll([], [1, 2, 3, 4, 5]);
        $this->assertSame([[1, 2, 3, 4, 5], 1], [$g->dice, $g->rolls]);
        $g->roll([4, 0], [6, 6, 6]);                                   // keeps the 1 and the 5, in any order
        $this->assertSame([1, 6, 6, 6, 5], $g->dice);
        $this->assertError('bad_roll', fn() => $g->roll([0], [6, 6, 6]));   // four new values needed
        $this->assertError('bad_roll', fn() => $g->roll([0, 1, 2, 3], [7]));
        $this->assertError('bad_hold', fn() => $g->roll([5], [1, 1, 1, 1]));
        $this->assertError('bad_hold', fn() => $g->roll([0, 0], [1, 1, 1, 1]));
        $g->roll([1, 2, 3], [6, 6]);
        $this->assertSame(3, $g->rolls);
        $this->assertError('no_rolls_left', fn() => $g->roll([], [1, 1, 1, 1, 1]));
        $g->write($this->cat('yatzy'));
        $this->assertSame([50, 1, null, 0], [$g->sheets[0][14], $g->turn, $g->dice, $g->rolls]);
        $this->assertSame(['roll', 'roll', 'roll', 'score'], array_column($g->events, 'k'));
    }

    /** TC-UNIT-YATZY-06 | FR-YATZY-05, FR-YATZY-06: a used row cannot be written again; forced order allows only the next row */
    public function test_rows_and_forced_order()
    {
        $g = new YatzyGame(new YatzyRules(), 1, 0);
        $g->roll([], [1, 1, 2, 3, 4]);
        $g->write($this->cat('chance'));
        $this->assertSame(11, $g->sheets[0][13]);
        $g->roll([], [1, 1, 2, 3, 4]);
        $this->assertError('bad_cat', fn() => $g->write($this->cat('chance')));
        $this->assertError('bad_cat', fn() => $g->write(15));
        $g->write($this->cat('sixes'));
        $this->assertSame(0, $g->sheets[0][5], 'struck');

        $f = new YatzyGame(new YatzyRules('forced'), 1, 0);
        $this->assertSame([0], $f->open(0));
        $f->roll([], [6, 6, 6, 6, 6]);
        $this->assertError('bad_cat', fn() => $f->write($this->cat('yatzy')));
        $f->write(0);
        $this->assertSame([[0], [1]], [[$f->sheets[0][0]], $f->open(0)]);
        $this->assertError('bad_rules', fn() => YatzyRules::fromArray(['order' => 'random']));
    }

    /** TC-UNIT-YATZY-07 | FR-YATZY-07: the game ends when every sheet is full; the highest total wins, a tie is shared */
    public function test_end_and_winners()
    {
        $g = new YatzyGame(new YatzyRules(), 2, 1);
        for ($t = 0; $t < 30; $t++) {
            $this->assertFalse($g->over);
            $g->roll([], [2, 2, 2, 2, 2]);
            $g->write($g->open($g->turn)[0]);
        }
        $this->assertTrue($g->over);
        $this->assertSame([0, 1], $g->winners(), 'the same dice: a shared win');
        $this->assertSame(['k' => 'end', 'w' => [0, 1]], end($g->events));
        $this->assertError('game_over', fn() => $g->roll([], [1, 1, 1, 1, 1]));
        $this->assertError('bad_seats', fn() => new YatzyGame(new YatzyRules(), 7, 0));
        $this->assertError('bad_seats', fn() => new YatzyGame(new YatzyRules(), 2, 2));
    }

    /** Play one sheet with the computer on $level, with dice from mt_rand (seeded: the same games every run). */
    private function solo(string $level, int $seed): int
    {
        mt_srand($seed);
        $g = new YatzyGame(new YatzyRules(), 1, 0);
        $ai = new YatzyAI($level);
        while (!$g->over) {
            [$what, $arg] = $ai->decide($g);
            if ($what === 'roll') {
                $this->assertTrue($g->rolls < Yatzy::ROLLS, 'never a 4th roll');
                $fresh = [];
                for ($i = $g->rolls === 0 ? 0 : count($arg); $i < Yatzy::DICE; $i++) $fresh[] = mt_rand(1, 6);
                $g->roll($arg, $fresh);
            } else {
                $this->assertContains($arg, $g->open(0));
                $g->write($arg);
            }
        }
        return Yatzy::totals($g->sheets[0])['total'];
    }

    /** TC-UNIT-YATZY-08 | FR-YATZY-08: the computer only makes legal moves, and Hard scores more than Medium, Medium more than Easy */
    public function test_computer_levels()
    {
        $avg = [];
        foreach (['easy', 'medium', 'hard'] as $level) {
            $sum = 0;
            for ($n = 0; $n < 40; $n++) $sum += $this->solo($level, 1000 + $n);
            $avg[$level] = $sum / 40;
        }
        $this->assertGreaterThan($avg['easy'] + 20, $avg['medium'], json_encode($avg));
        $this->assertGreaterThan($avg['medium'], $avg['hard'], json_encode($avg));
        $this->assertGreaterThan(220, $avg['hard'], json_encode($avg));
    }
}
