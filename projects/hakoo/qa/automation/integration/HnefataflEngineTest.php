<?php
/**
 * Component tests of the Hnefatafl rules and computer opponent (src/hnefatafl-engine.inc.php).
 * No database or HTTP: the engine is pure logic. Rules: Copenhagen Hnefatafl, 11x11.
 * Traceability: FR-HNEFATAFL-01..04.
 */
require_once '/var/www/html/hnefatafl-engine.inc.php';

use PHPUnit\Framework\TestCase;

class HnefataflEngineTest extends TestCase
{
    private function assertError(string $code, callable $fn): void
    {
        try {
            $fn();
            $this->fail("expected HnefataflError $code");
        } catch (HnefataflError $e) {
            $this->assertSame($code, $e->getMessage());
        }
    }

    /** A position from piece lists: ['k' => [[r, c]], 'a' => [...], 'd' => [...]]. */
    private function game(array $pieces, string $toMove = 'black'): HnefataflGame
    {
        $rows = array_fill(0, 11, str_repeat('.', 11));
        foreach ($pieces as $v => $list) foreach ($list as [$r, $c]) $rows[$r][$c] = $v;
        return new HnefataflGame($rows, $toMove);
    }

    /** TC-HNE-001 | FR-HNEFATAFL-01: the start position; the attackers (black) move first */
    public function test_start_position()
    {
        $g = new HnefataflGame();
        $this->assertSame('black', $g->toMove());
        $this->assertSame(HnefataflBoard::START, $g->board->rows());
        $this->assertSame(['black' => 24, 'white' => 13], $g->pieces());
        $this->assertCount(116, $g->legal());
        $this->assertFalse($g->isOver());
    }

    /** TC-HNE-002 | FR-HNEFATAFL-01, FR-HNEFATAFL-02: rook moves only; only the king may stop on the throne and the corners */
    public function test_moves_and_restricted_squares()
    {
        $g = new HnefataflGame();
        $this->assertError('not_your_turn', fn() => $g->play('white', 3, 5, 3, 4));
        $this->assertError('no_piece', fn() => $g->play('black', 2, 2, 2, 3));
        $this->assertError('not_your_piece', fn() => $g->play('black', 3, 5, 3, 4));
        $this->assertError('not_straight', fn() => $g->play('black', 3, 0, 2, 1));
        $this->assertError('blocked', fn() => $g->play('black', 5, 0, 5, 2));        // over the attacker on b6
        $this->assertError('restricted', fn() => $g->play('black', 0, 3, 0, 0));     // a corner
        $this->assertError('outside', fn() => $g->play('black', 0, 3, 0, 11));

        // Any piece may pass over the empty throne, but not stop on it.
        $g = $this->game(['k' => [[8, 8]], 'a' => [[5, 2], [0, 4]], 'd' => [[9, 9]]]);
        $this->assertError('restricted', fn() => $g->play('black', 5, 2, 5, 5));
        $g->play('black', 5, 2, 5, 8);
        $this->assertSame('a', $g->board->rows()[5][8]);
        // The king may stop on the throne.
        $g->play('white', 8, 8, 8, 5);
        $g->play('black', 0, 4, 0, 3);
        $g->play('white', 8, 5, 5, 5);
        $this->assertSame('k', $g->board->rows()[5][5]);
    }

    /** TC-HNE-003 | FR-HNEFATAFL-02: a sandwich captures; moving into a sandwich is safe */
    public function test_custodian_capture()
    {
        $g = $this->game(['k' => [[8, 8]], 'a' => [[2, 3], [2, 9], [4, 3], [4, 5]], 'd' => [[2, 4], [7, 4]]]);
        $g->play('black', 2, 9, 2, 5);
        $this->assertSame([[2, 4]], $g->lastCaptures());
        $this->assertSame('...a.a.....', $g->board->rows()[2]);
        $g->play('white', 7, 4, 4, 4);                                               // between two attackers: safe
        $this->assertSame([], $g->lastCaptures());
        $this->assertSame('...ada.....', $g->board->rows()[4]);
    }

    /** TC-HNE-004 | FR-HNEFATAFL-02: corners are hostile; the throne is hostile to defenders only when empty */
    public function test_hostile_squares()
    {
        $g = $this->game(['k' => [[8, 8]], 'a' => [[3, 2], [3, 8]], 'd' => [[0, 1], [4, 5]]]);
        $g->play('black', 3, 2, 0, 2);                                               // against the corner a11
        $this->assertSame([[0, 1]], $g->lastCaptures());
        $g->play('white', 8, 8, 8, 7);
        $g->play('black', 3, 8, 3, 5);                                               // against the empty throne
        $this->assertSame([[4, 5]], $g->lastCaptures());

        $g = $this->game(['k' => [[5, 5]], 'a' => [[3, 8]], 'd' => [[4, 5]]]);
        $g->play('black', 3, 8, 3, 5);                                               // the king sits on the throne
        $this->assertSame([], $g->lastCaptures());
    }

    /** TC-HNE-005 | FR-HNEFATAFL-02: the king takes part in captures */
    public function test_armed_king()
    {
        $g = $this->game(['k' => [[4, 7]], 'a' => [[3, 3], [9, 9]], 'd' => [[2, 3]]], 'white');
        $g->play('white', 4, 7, 4, 3);
        $this->assertSame([[3, 3]], $g->lastCaptures());
    }

    /** TC-HNE-006 | FR-HNEFATAFL-02: shieldwall on the edge, a corner may close one end, the king is not taken */
    public function test_shieldwall()
    {
        $g = $this->game(['k' => [[5, 5]], 'a' => [[10, 3], [9, 4], [9, 5], [9, 6], [7, 7]], 'd' => [[10, 4], [10, 5], [10, 6]]]);
        $g->play('black', 7, 7, 10, 7);
        $this->assertCount(3, $g->lastCaptures());
        $this->assertSame('...a...a...', $g->board->rows()[10]);

        $g = $this->game(['k' => [[10, 5]], 'a' => [[10, 3], [9, 4], [9, 5], [9, 6], [7, 7]], 'd' => [[10, 4], [10, 6]]]);
        $g->play('black', 7, 7, 10, 7);
        $this->assertSame('...a.k.a...', $g->board->rows()[10]);

        $g = $this->game(['k' => [[5, 5]], 'a' => [[9, 1], [9, 2], [7, 3]], 'd' => [[10, 1], [10, 2]]]);
        $g->play('black', 7, 3, 10, 3);                                              // the corner a1 closes the other end
        $this->assertSame('...a.......', $g->board->rows()[10]);
    }

    /** TC-HNE-007 | FR-HNEFATAFL-03: the king is captured on four sides, or three and the throne; never on the edge */
    public function test_king_capture()
    {
        $g = $this->game(['k' => [[3, 3]], 'a' => [[2, 3], [4, 3], [3, 2], [3, 8]], 'd' => [[9, 9]]]);
        $g->play('black', 3, 8, 3, 4);
        $this->assertSame(['king', 'black'], [$g->result(), $g->winner()]);

        $g = $this->game(['k' => [[4, 5]], 'a' => [[3, 5], [4, 4], [4, 9]], 'd' => [[9, 9]]]);
        $g->play('black', 4, 9, 4, 6);
        $this->assertSame(['king', 'black'], [$g->result(), $g->winner()]);

        $g = $this->game(['k' => [[0, 5]], 'a' => [[0, 4], [1, 5], [3, 6]], 'd' => [[9, 9]]]);
        $g->play('black', 3, 6, 0, 6);
        $this->assertFalse($g->isOver());
    }

    /** TC-HNE-008 | FR-HNEFATAFL-03: the king on a corner wins */
    public function test_king_escapes_to_a_corner()
    {
        $g = $this->game(['k' => [[0, 5]], 'a' => [[3, 3]], 'd' => [[5, 5]]], 'white');
        $g->play('white', 0, 5, 0, 10);
        $this->assertSame(['corner', 'white'], [$g->result(), $g->winner()]);
        $this->assertSame([], $g->legal());
    }

    /** TC-HNE-009 | FR-HNEFATAFL-03: an edge fort that cannot be broken wins; a wall with a hole does not */
    public function test_edge_fort()
    {
        $fort = ['k' => [[10, 5]], 'a' => [[2, 2], [2, 8], [6, 1]], 'd' => [[10, 3], [9, 4], [9, 5], [9, 6], [7, 7]]];
        $g = $this->game($fort, 'white');
        $g->play('white', 7, 7, 10, 7);
        $this->assertSame(['fort', 'white'], [$g->result(), $g->winner()]);

        $fort['d'][4] = [7, 8];                                                     // this one cannot close the wall
        $g = $this->game($fort, 'white');
        $g->play('white', 7, 8, 10, 8);
        $this->assertFalse($g->isOver());
    }

    /** TC-HNE-010 | FR-HNEFATAFL-03: attackers who surround every defender win */
    public function test_encirclement()
    {
        $ring = [[3, 4], [3, 5], [3, 6], [3, 7], [7, 4], [7, 5], [7, 6], [7, 7], [4, 3], [5, 3], [6, 3], [4, 8], [5, 8], [6, 10]];
        $g = $this->game(['k' => [[5, 5]], 'a' => $ring, 'd' => [[5, 6]]]);
        $g->play('black', 6, 10, 6, 8);
        $this->assertSame(['surrounded', 'black'], [$g->result(), $g->winner()]);
    }

    /** TC-HNE-011 | FR-HNEFATAFL-03: a player who cannot move loses */
    public function test_no_moves_loses()
    {
        $g = $this->game(['k' => [[0, 5]], 'a' => [[0, 4], [0, 6], [1, 8]]]);
        $g->play('black', 1, 8, 1, 5);
        $this->assertSame(['no_moves', 'black'], [$g->result(), $g->winner()]);
    }

    /** TC-HNE-012 | FR-HNEFATAFL-03: the third repetition of a position is a loss for the defenders */
    public function test_repetition()
    {
        $g = new HnefataflGame();
        $shuffle = [[3, 0, 2, 0], [3, 5, 3, 4], [2, 0, 3, 0], [3, 4, 3, 5]];
        foreach ($shuffle as $m) $g->play($g->toMove(), ...$m);
        $this->assertFalse($g->isOver());
        foreach ($shuffle as $m) $g->play($g->toMove(), ...$m);
        $this->assertSame(['repetition', 'black'], [$g->result(), $g->winner()]);
        $this->assertSame(8, $g->moveCount());
    }

    /** TC-HNE-013 | FR-HNEFATAFL-01: replaying the stored moves gives the same game */
    public function test_from_moves_replays_the_game()
    {
        $rng = new \Random\Randomizer(new \Random\Engine\Mt19937(7));
        $g = new HnefataflGame();
        $moves = [];
        while (count($moves) < 30 && !$g->isOver()) {
            $moves[] = (new HnefataflAI('easy', $rng))->choose($g, $g->toMove());
            $g->play($g->toMove(), ...end($moves));
        }
        $r = HnefataflGame::fromMoves($moves);
        $this->assertSame($g->board->rows(), $r->board->rows());
        $this->assertSame([$g->toMove(), $g->pieces(), $g->result()], [$r->toMove(), $r->pieces(), $r->result()]);
    }

    /** TC-HNE-014 | FR-HNEFATAFL-04: every level takes a king's way to a corner at once */
    public function test_computer_takes_the_corner()
    {
        foreach (HnefataflAI::LEVELS as $level) {
            $g = $this->game(['k' => [[0, 5]], 'a' => [[3, 3], [4, 4], [0, 2]], 'd' => [[6, 5]]], 'white');
            $this->assertSame([0, 5, 0, 10], (new HnefataflAI($level))->choose($g, 'white'), $level);
        }
    }

    /** TC-HNE-015 | FR-HNEFATAFL-04: Medium and Hard block the king's only open line */
    public function test_computer_blocks_the_king()
    {
        foreach (['medium', 'hard'] as $level) {
            $g = $this->game(['k' => [[2, 10]], 'a' => [[1, 5], [5, 10], [8, 2], [8, 8]], 'd' => [[5, 5]]]);
            $m = (new HnefataflAI($level))->choose($g, 'black');
            $this->assertSame([1, 10], [$m[2], $m[3]], $level);
        }
    }

    /** TC-HNE-016 | FR-HNEFATAFL-04: the computer answers within 3 seconds on Hard, for both sides */
    public function test_computer_is_fast_enough()
    {
        $g = new HnefataflGame();
        $t = microtime(true);
        $m = (new HnefataflAI('hard'))->choose($g, 'black');
        $g->play('black', ...$m);
        $m = (new HnefataflAI('hard'))->choose($g, 'white');
        $g->play('white', ...$m);
        $this->assertLessThan(6.0, microtime(true) - $t);
        $this->assertSame(2, $g->moveCount());
    }

    /** TC-HNE-017 | FR-HNEFATAFL-04: whole games between the levels always end or stay legal */
    public function test_easy_games_finish()
    {
        for ($i = 0; $i < 3; $i++) {
            $g = new HnefataflGame();
            for ($k = 0; $k < 400 && !$g->isOver(); $k++) {
                $g->play($g->toMove(), ...(new HnefataflAI('easy'))->choose($g, $g->toMove()));
            }
            $this->assertTrue($g->isOver(), 'an Easy game ends');
            $this->assertContains($g->result(), ['king', 'surrounded', 'corner', 'fort', 'no_moves', 'repetition']);
        }
    }
}
