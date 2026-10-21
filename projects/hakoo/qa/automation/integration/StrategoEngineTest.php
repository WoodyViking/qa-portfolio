<?php
/**
 * Component tests of the Stratego rules, hidden information and computer opponent
 * (src/stratego-engine.inc.php). No database or HTTP: the engine is pure logic.
 * Traceability: FR-STRATEGO-01..05.
 */
require_once '/var/www/html/stratego-engine.inc.php';

use PHPUnit\Framework\TestCase;

class StrategoEngineTest extends TestCase
{
    /** Red's set-up, board rows 7-10 from the top: Scouts and two Miners in front, Flag and Bombs at the back. */
    const RED = '2222222233' . '3334444555' . '5666677788' . '9MSBBBBBBF';
    /** Blue's set-up, board rows 1-4 from the top (its back row first). */
    const BLUE = '9MSBBBBBBF' . '5666677788' . '3334444555' . '2222222233';

    private function assertError(string $code, callable $fn): void
    {
        try {
            $fn();
            $this->fail("expected StrategoError $code");
        } catch (StrategoError $e) {
            $this->assertSame($code, $e->getMessage());
        }
    }

    /** A started game with only these pieces: [[row, col, 'black'|'white', code], ...]. */
    private function arena(array $pieces, string $toMove = 'black'): StrategoGame
    {
        $g = new StrategoGame(self::RED, self::BLUE);
        $g->board->cells = array_fill(0, 100, 0);
        foreach ($pieces as [$r, $c, $seat, $code]) {
            $g->board->cells[$r * 10 + $c] = (StrategoGame::sideOf($seat) << 4) | array_search($code, StrategoBoard::CODES, true);
        }
        if ($toMove === 'white') {                                   // let Red pass one move with a spare piece
            $g->board->cells[99] = (1 << 4) | 5;
            $g->play('black', 9, 9, 8, 9);
        }
        return $g;
    }

    private function codeAt(StrategoGame $g, string $who, int $r, int $c): ?string
    {
        foreach ($g->pieces($who) as [$pr, $pc, , $code]) if ($pr === $r && $pc === $c) return $code;
        return null;
    }

    /** TC-STR-001 | FR-STRATEGO-01: set-ups must have the 40 pieces; both must be set up before the first move */
    public function test_setup()
    {
        $this->assertTrue(StrategoGame::validSetup(self::RED));
        $this->assertFalse(StrategoGame::validSetup(substr(self::RED, 1)));
        $this->assertFalse(StrategoGame::validSetup('M' . substr(self::RED, 1)));     // two Marshals, no Scout
        $g = new StrategoGame();
        $this->assertError('bad_setup', fn() => $g->setup('black', str_repeat('2', 40)));
        $g->setup('black', self::RED);
        $this->assertError('already_set_up', fn() => $g->setup('black', self::RED));
        $this->assertError('not_set_up', fn() => $g->play('black', 6, 0, 5, 0));
        $this->assertSame([], $g->pieces('white'), 'the other army is not shown during the set-up');
        $this->assertCount(40, $g->pieces('black'));
        $g->setup('white', self::BLUE);
        $this->assertTrue($g->started());
        $this->assertSame('black', $g->toMove());
        $this->assertCount(14, $g->legal());                                          // 4 Scout lanes x 3 + 2 Miners
    }

    /** TC-STR-002 | FR-STRATEGO-01, FR-STRATEGO-02: moves are checked and refused with a reason */
    public function test_move_rules()
    {
        $g = new StrategoGame(self::RED, self::BLUE);
        $this->assertError('not_your_turn', fn() => $g->play('white', 3, 0, 4, 0));
        $this->assertError('no_piece', fn() => $g->play('black', 5, 0, 4, 0));
        $this->assertError('not_your_piece', fn() => $g->play('black', 3, 0, 4, 0));
        $this->assertError('cannot_move', fn() => $g->play('black', 9, 3, 8, 3));    // a Bomb
        $this->assertError('lake', fn() => $g->play('black', 6, 2, 5, 2));
        $this->assertError('own_piece', fn() => $g->play('black', 7, 0, 6, 0));
        $this->assertError('not_straight', fn() => $g->play('black', 6, 0, 5, 1));
        $this->assertError('one_step', fn() => $g->play('black', 6, 8, 4, 8));       // a Miner
        $this->assertError('blocked', fn() => $g->play('black', 6, 0, 2, 0));        // a Scout cannot jump
        $this->assertError('outside', fn() => $g->play('black', 6, 0, 10, 0));
        $g->play('black', 6, 0, 4, 0);                                               // a Scout runs two squares
        $this->assertSame('white', $g->toMove());
    }

    /** TC-STR-003 | FR-STRATEGO-02: the higher rank wins, equal ranks both go, the lower attacker dies */
    public function test_battles()
    {
        $spare = [[0, 0, 'white', 'F'], [0, 9, 'white', '4'], [9, 0, 'black', 'F']];
        $g = $this->arena([[5, 0, 'black', '7'], [4, 0, 'white', '5'], ...$spare]);
        $g->play('black', 5, 0, 4, 0);
        $this->assertSame(['win', '7', '5'], [$g->lastBattle()['result'], $g->lastBattle()['attacker'], $g->lastBattle()['defender']]);
        $this->assertSame('7', $this->codeAt($g, 'white', 4, 0), 'the winner has been seen');
        $this->assertSame(['black' => [], 'white' => ['5']], $g->fallen());

        $g = $this->arena([[5, 0, 'black', '6'], [4, 0, 'white', '6'], ...$spare]);
        $g->play('black', 5, 0, 4, 0);
        $this->assertSame([null, 'tie'], [$this->codeAt($g, 'white', 4, 0), $g->lastBattle()['result']]);

        $g = $this->arena([[5, 0, 'black', '4'], [4, 0, 'white', '8'], ...$spare]);
        $g->play('black', 5, 0, 4, 0);
        $this->assertSame([null, '8'], [$this->codeAt($g, 'black', 5, 0), $this->codeAt($g, 'black', 4, 0)], 'the defender stays and is seen');
    }

    /** TC-STR-004 | FR-STRATEGO-02: the Spy takes the Marshal only when it attacks; Bombs take all but Miners */
    public function test_special_pieces()
    {
        $spare = [[0, 0, 'white', 'F'], [0, 9, 'white', '4'], [9, 0, 'black', 'F'], [9, 9, 'black', '4']];
        $g = $this->arena([[5, 5, 'black', 'S'], [4, 5, 'white', 'M'], ...$spare]);
        $g->play('black', 5, 5, 4, 5);
        $this->assertSame('win', $g->lastBattle()['result']);

        $g = $this->arena([[5, 5, 'black', 'S'], [4, 5, 'white', 'M'], ...$spare], 'white');
        $g->play('white', 4, 5, 5, 5);
        $this->assertSame('win', $g->lastBattle()['result'], 'the Marshal takes a Spy that does not attack');

        $g = $this->arena([[5, 5, 'black', 'M'], [4, 5, 'white', 'B'], ...$spare]);
        $g->play('black', 5, 5, 4, 5);
        $this->assertSame(['lose', 'B'], [$g->lastBattle()['result'], $this->codeAt($g, 'black', 4, 5)]);

        $g = $this->arena([[5, 5, 'black', '3'], [4, 5, 'white', 'B'], ...$spare]);
        $g->play('black', 5, 5, 4, 5);
        $this->assertSame(['win', '3'], [$g->lastBattle()['result'], $this->codeAt($g, 'white', 4, 5)]);
    }

    /** TC-STR-005 | FR-STRATEGO-03: taking the flag wins; a Scout may attack at the end of its run */
    public function test_capture_the_flag()
    {
        $g = $this->arena([[8, 4, 'black', '2'], [1, 4, 'white', 'F'], [0, 9, 'white', '4'], [9, 0, 'black', 'F']]);
        $g->play('black', 8, 4, 1, 4);
        $this->assertSame(['flag', 'black'], [$g->result(), $g->winner()]);
        $this->assertSame([], $g->legal());
    }

    /** TC-STR-006 | FR-STRATEGO-03: a player left with nothing that can move loses */
    public function test_no_moves_loses()
    {
        $g = $this->arena([[5, 0, 'black', '5'], [4, 0, 'white', '4'], [0, 0, 'white', 'F'], [0, 1, 'white', 'B'], [9, 0, 'black', 'F']]);
        $g->play('black', 5, 0, 4, 0);
        $this->assertSame(['no_moves', 'black'], [$g->result(), $g->winner()]);
    }

    /** TC-STR-007 | FR-STRATEGO-03: two-square rule; 200 moves without an attack are a draw */
    public function test_two_squares_and_no_battles()
    {
        $g = $this->arena([[5, 0, 'black', '5'], [9, 0, 'black', 'F'], [4, 9, 'white', '5'], [0, 0, 'white', 'F']]);
        $g->play('black', 5, 0, 6, 0);
        $g->play('white', 4, 9, 3, 9);
        $g->play('black', 6, 0, 5, 0);
        $g->play('white', 3, 9, 4, 9);
        $g->play('black', 5, 0, 6, 0);
        $g->play('white', 4, 9, 3, 9);
        $this->assertError('two_squares', fn() => $g->play('black', 6, 0, 5, 0));
        $this->assertNotContains([6, 0, 5, 0], $g->legal());

        // Round and round a square of four: no two-square rule, no attack.
        $g = $this->arena([[8, 0, 'black', '5'], [9, 9, 'black', 'F'], [1, 9, 'white', '5'], [0, 0, 'white', 'F']]);
        $red = [[8, 0, 8, 1], [8, 1, 7, 1], [7, 1, 7, 0], [7, 0, 8, 0]];
        $blue = [[1, 9, 1, 8], [1, 8, 2, 8], [2, 8, 2, 9], [2, 9, 1, 9]];
        for ($k = 0; $k < 100; $k++) {
            $g->play('black', ...$red[$k % 4]);
            if (!$g->isOver()) $g->play('white', ...$blue[$k % 4]);
        }
        $this->assertSame(['no_battles', null, 200], [$g->result(), $g->winner(), $g->moveCount()]);
    }

    /** TC-STR-008 | FR-STRATEGO-04: each player sees the other army only as far as it has been seen; all after the game */
    public function test_hidden_information()
    {
        $g = new StrategoGame(self::RED, self::BLUE);
        $blue = array_filter($g->pieces('black'), fn($p) => $p[2] === 'white');
        $this->assertCount(40, $blue);
        $this->assertSame(['?'], array_values(array_unique(array_column($blue, 3))));
        $g->play('black', 6, 0, 3, 0);                                               // Scout attacks Scout: both seen, both gone
        $g->play('white', 3, 1, 5, 1);                                               // Blue's Scout runs: it is now known
        $this->assertSame('2', $this->codeAt($g, 'black', 5, 1));
        $g->play('black', 6, 8, 5, 8);                                               // a Miner steps: moved, still unknown
        $moved = array_values(array_filter($g->pieces('white'), fn($p) => $p[0] === 5 && $p[1] === 8))[0];
        $this->assertSame(['?', true, false], [$moved[3], $moved[4], $moved[5]]);
        $this->assertSame('3', $this->codeAt($g, 'black', 5, 8), 'you always see your own pieces');
        $k = $g->knowledge('white');
        $this->assertSame(StrategoBoard::UNKNOWN, StrategoBoard::rank($k['cells'][58]));
        $this->assertSame(7, $k['pool'][StrategoBoard::SCOUT], 'one Red Scout has fallen');
        $this->assertSame('?', $this->codeAt($g, 'white', 9, 1), "Red's Marshal is hidden from Blue");
        $all = array_values(array_filter($g->pieces('white', true), fn($p) => $p[0] === 9 && $p[1] === 1));
        $this->assertSame('M', $all[0][3], 'after the game everything is shown');
    }

    /** TC-STR-009 | FR-STRATEGO-05: the computer plays only from what it may know: hidden ranks do not change its move */
    public function test_computer_does_not_peek()
    {
        foreach (StrategoAI::LEVELS as $level) {
            // Two games that differ only in where Blue's hidden Marshal and a hidden Scout stand.
            $other = self::BLUE;
            [$other[1], $other[39]] = [$other[39], $other[1]];
            $picks = [];
            foreach ([self::BLUE, $other] as $blue) {
                $g = new StrategoGame(self::RED, $blue);
                $g->play('black', 6, 8, 5, 8);
                $g->play('white', 3, 8, 4, 8);
                $rng = new \Random\Randomizer(new \Random\Engine\Mt19937(11));
                $picks[] = (new StrategoAI($level, $rng))->choose($g, 'black');
            }
            $this->assertSame($picks[0], $picks[1], $level);
        }
    }

    /** TC-STR-010 | FR-STRATEGO-05: Medium and Hard take a piece they have seen and beat */
    public function test_computer_uses_what_it_saw()
    {
        foreach (['medium', 'hard'] as $level) {
            $g = $this->arena([[5, 5, 'black', '7'], [3, 5, 'black', '2'], [4, 5, 'white', '5'], [4, 4, 'white', '6'],
                               [0, 0, 'white', 'F'], [9, 0, 'black', 'F']]);
            $g->play('black', 3, 5, 4, 5);                                           // the Scout attacks the 5 and dies: the 5 is seen
            $g->play('white', 4, 4, 3, 4);
            $this->assertSame([5, 5, 4, 5], (new StrategoAI($level))->choose($g, 'black'), "$level takes the seen 5 with its 7");
        }
    }

    /** TC-STR-011 | FR-STRATEGO-05: the computer's set-ups are valid; Medium and Hard hide the Flag on the back row */
    public function test_computer_setup()
    {
        foreach (StrategoAI::LEVELS as $level) {
            foreach (['black', 'white'] as $seat) {
                $s = (new StrategoAI($level))->setup($seat);
                $this->assertTrue(StrategoGame::validSetup($s), "$level $seat");
                if ($level !== 'easy') $this->assertContains(strpos($s, 'F'), $seat === 'black' ? range(30, 39) : range(0, 9));
            }
        }
    }

    /** TC-STR-012 | FR-STRATEGO-05: whole games between the levels end, and every move is fast */
    public function test_computer_games_end()
    {
        $t = microtime(true);
        foreach ([['easy', 'medium'], ['hard', 'easy']] as [$red, $blue]) {
            $g = new StrategoGame((new StrategoAI($red))->setup('black'), (new StrategoAI($blue))->setup('white'));
            for ($k = 0; $k < 3000 && !$g->isOver(); $k++) {
                $who = $g->toMove();
                $g->play($who, ...(new StrategoAI($who === 'black' ? $red : $blue))->choose($g, $who));
            }
            $this->assertTrue($g->isOver(), "$red v $blue ends");
        }
        $this->assertLessThan(20.0, microtime(true) - $t);
    }
}
