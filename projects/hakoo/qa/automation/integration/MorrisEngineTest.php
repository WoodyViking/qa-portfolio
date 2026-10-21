<?php
/**
 * Component tests of the Nine men's morris rules and computer opponent (src/morris-engine.inc.php).
 * No database or HTTP: the engine is pure logic.
 * Traceability: FR-MORRIS-01..06.
 */
require_once '/var/www/html/morris-engine.inc.php';

use PHPUnit\Framework\TestCase;

class MorrisEngineTest extends TestCase
{
    private function assertError(string $code, callable $fn): void
    {
        try {
            $fn();
            $this->fail("expected MorrisError $code");
        } catch (MorrisError $e) {
            $this->assertSame($code, $e->getMessage());
        }
    }

    /** A game in the moving phase: $white and $black are the points of each side's stones; $side is to move. */
    private function moving(array $white, array $black, int $side = 1, bool $flying = true): MorrisGame
    {
        $g = new MorrisGame($flying);
        $p = $g->pos;
        foreach ($white as $x) $p->cells[$x] = 1;
        foreach ($black as $x) $p->cells[$x] = -1;
        $p->hand = [1 => 0, -1 => 0];
        $p->onBoard = [1 => count($white), -1 => count($black)];
        $p->side = $side;
        return $g;
    }

    /** TC-UNIT-MM-01 | FR-MORRIS-01: 24 points, 16 mills, 32 lines between neighbours; points named a1..g7 */
    public function test_board()
    {
        MorrisBoard::init();
        $this->assertCount(24, MorrisBoard::XY);
        $this->assertCount(16, MorrisBoard::MILLS);
        $this->assertSame(64, array_sum(array_map('count', MorrisBoard::$adj)), 'every line counted from both ends');
        $this->assertSame([2, 4, 3], [count(MorrisBoard::$adj[0]), count(MorrisBoard::$adj[4]), count(MorrisBoard::$adj[9])]);
        $this->assertSame(['a7', 'd6', 'd3', 'g1'], array_map([MorrisBoard::class, 'name'], [0, 4, 16, 23]));
        foreach (MorrisBoard::$millsOf as $p => $mills) $this->assertCount(2, $mills, "point $p is on two lines");
    }

    /** TC-UNIT-MM-02 | FR-MORRIS-02: white places first, nine stones each; taken points, moving while placing and wrong turns are refused */
    public function test_placing()
    {
        $g = new MorrisGame();
        $this->assertSame('white', $g->toMove());
        $this->assertCount(24, $g->pos->steps());
        $g->play('white', -1, 4);
        $this->assertSame(['black', '....w...................', 8, 9], [$g->toMove(), $g->board(), $g->pos->hand[1], $g->pos->hand[-1]]);
        $this->assertError('not_your_turn', fn() => $g->play('white', -1, 5));
        $this->assertError('occupied', fn() => $g->play('black', -1, 4));
        $this->assertError('place_first', fn() => $g->play('black', 4, 5));
        $this->assertError('bad_point', fn() => $g->play('black', -1, 24));
        for ($k = 0; $k < 17; $k++) {                                       // the rest of the 18, away from any mill
            $free = array_values(array_filter($g->pos->steps(), fn($s) => !$s[2]));
            $g->play($g->toMove(), -1, $free[0][1]);
        }
        $this->assertSame([0, 0, 9, 9], [$g->pos->hand[1], $g->pos->hand[-1], $g->pos->left(1), $g->pos->left(-1)]);
        $this->assertNotContains(-1, array_column($g->pos->steps(), 0), 'now stones move');
    }

    /** TC-UNIT-MM-03 | FR-MORRIS-03: a mill takes a stone: one not in a mill, unless all are; no take without a mill */
    public function test_mills_and_taking()
    {
        $g = new MorrisGame();
        $g->play('white', -1, 0);
        $g->play('black', -1, 9);
        $g->play('white', -1, 1);
        $g->play('black', -1, 10);
        $this->assertSame([[-1, 2, true]], array_values(array_filter($g->pos->steps(), fn($s) => $s[1] === 2)));
        $this->assertError('must_take', fn() => $g->play('white', -1, 2));
        $this->assertError('not_their_stone', fn() => $g->play('white', -1, 2, 11));
        $this->assertError('no_mill', fn() => $g->play('white', -1, 3, 9));
        $g->play('white', -1, 2, 9);
        $this->assertSame([[0, 1, 2]], $g->mills());
        $this->assertSame(8, $g->pos->left(-1));

        // Stones in a mill are safe while the player has others; when all are in mills, any may go.
        $g = $this->moving([0, 1, 14], [9, 10, 21, 22, 23]);
        $this->assertSame([9, 10], MorrisBoard::takeable($g->pos->cells, -1));
        $this->assertError('in_mill', fn() => $g->play('white', 14, 2, 21));
        $g->play('white', 14, 2, 10);
        $this->assertSame('www......b...........bbb', $g->board());
        $g = $this->moving([0, 1, 14], [3, 10, 18, 21, 22, 23]);
        $this->assertSame([3, 10, 18, 21, 22, 23], MorrisBoard::takeable($g->pos->cells, -1));
    }

    /** TC-UNIT-MM-04 | FR-MORRIS-04: stones move along a line to an empty neighbour; with flying, three stones may jump anywhere */
    public function test_moving_and_flying()
    {
        $g = $this->moving([0, 4, 9, 12], [2, 14, 20, 23]);
        $this->assertError('occupied', fn() => $g->play('white', 0, 9));
        $this->assertError('not_adjacent', fn() => $g->play('white', 0, 21));
        $this->assertError('no_stone', fn() => $g->play('white', 1, 0));
        $this->assertError('not_your_stone', fn() => $g->play('white', 2, 1));
        $this->assertError('no_stones_left', fn() => $g->play('white', -1, 1));
        $g->play('white', 4, 1);
        $this->assertSame('black', $g->toMove());

        $g = $this->moving([0, 4, 12], [2, 14, 20, 23], 1, true);
        $this->assertTrue($g->pos->flies(1));
        $g->play('white', 0, 22);
        $g = $this->moving([0, 4, 12], [2, 14, 20, 23], 1, false);
        $this->assertFalse($g->pos->flies(1));
        $this->assertError('not_adjacent', fn() => $g->play('white', 0, 22));
    }

    /** TC-UNIT-MM-05 | FR-MORRIS-05: down to two stones loses; no move loses; the third repetition and 50 turns without taking are draws */
    public function test_game_ends()
    {
        $g = $this->moving([0, 1, 9, 14], [3, 5, 23]);
        $g->play('white', 14, 2, 23);                                        // closes a7-d7-g7 and takes: black has two left
        $this->assertSame(['stones', 'white'], [$g->result, $g->winner]);
        $this->assertError('game_over', fn() => $g->play('black', 3, 4));

        $g = $this->moving([1, 9, 14, 19], [0, 2, 21, 23]);
        $g->play('white', 19, 22);
        $this->assertSame(['blocked', 'white'], [$g->result, $g->winner]);

        $g = $this->moving([0, 2, 4], [19, 21, 23]);
        $shuffle = [['white', 4, 7], ['black', 19, 16], ['white', 7, 4], ['black', 16, 19]];
        foreach ([...$shuffle, ...$shuffle, $shuffle[0]] as $k => [$who, $f, $t]) {         // the first turn's position comes up a third time
            $this->assertFalse($g->isOver(), "turn $k");
            $g->play($who, $f, $t);
        }
        $this->assertSame(['repetition', null], [$g->result, $g->winner]);

        $g = $this->moving([0, 2, 4], [19, 21, 23]);
        $g->quiet = MorrisGame::QUIET_LIMIT - 1;
        $g->play('white', 4, 7);
        $this->assertSame('draw', $g->result);
    }

    /** TC-UNIT-MM-06 | FR-MORRIS-06: every level answers with a legal turn in time; Medium and Hard close a mill when they can */
    public function test_computer()
    {
        foreach (MorrisAI::LEVELS as $level) {
            $g = new MorrisGame();
            $t = microtime(true);
            $g->play('white', ...(new MorrisAI($level))->choose($g));
            $this->assertLessThan(3.0, microtime(true) - $t, $level);
            $this->assertCount(1, $g->turns);
        }
        foreach (['medium', 'hard'] as $level) {
            $g = new MorrisGame();
            foreach ([[-1, 0], [-1, 9], [-1, 1], [-1, 10]] as $k => [$f, $to]) $g->play($k % 2 ? 'black' : 'white', $f, $to);
            [$from, $to, $take] = (new MorrisAI($level))->choose($g);
            $this->assertSame([-1, 2], [$from, $to], $level);
            $this->assertContains($take, [9, 10], 'and it takes a stone of the threatened mill');
        }
    }
}
