<?php
// Kunglig Vikingludo: the rules engine on its own (component level, no database), modules/ludo-engine.inc.php.
// State transition testing (nest -> ring -> way home -> goal), boundary values (the last ring square, the exact
// goal, the last feather) and a decision table for landing (crown or not x another colour there x coming out).
// Traceability: US-15, K-42, K-43, K-44, K-47, K-49, AK-30, AK-32, AK-33, AK-35, AK-36.
use PHPUnit\Framework\TestCase;

require_once '/srv/mysteries/modules/ludo-engine.inc.php';

final class LudoEngineTest extends TestCase
{
    private const N = LudoRoyal::NEST;

    /** A game of $players with the pieces at $pos (missing players stay in the nest) and player $turn to roll. */
    private function game(int $players, array $pos = [], int $turn = 0): LudoRoyal
    {
        $g = new LudoRoyal($players, $turn);
        foreach ($pos as $pl => $ps) $g->pos[$pl] = $ps;
        return $g;
    }

    /** TC-INT-001 (K-42, AK-35): the board and the arms follow the number of players; 1 and 7 players are refused */
    public function testBoardsAndArms(): void
    {
        $g = new LudoRoyal(2);
        $this->assertSame(['royal4', 56, 54, 60, [0, 2]], [$g->toState()['board'], $g->ring(), $g->lastRing(), $g->goal(), $g->seat]);
        $this->assertSame([0, 1, 2, 3], (new LudoRoyal(4))->seat);
        $g = new LudoRoyal(5);
        $this->assertSame(['royal6', 84, 82, 88, [0, 1, 2, 3, 4]], [$g->toState()['board'], $g->ring(), $g->lastRing(), $g->goal(), $g->seat]);
        $this->assertSame(6, count((new LudoRoyal(6))->seat));
        $this->assertSame(28, $this->game(2)->square(1, 0));            // two players sit opposite: arm 2 starts on square 28
        $this->assertSame(42, $this->game(6)->square(3, 0));
        $this->assertSame(54, $this->game(4)->square(0, 54));           // the last ring square of player 0
        $this->assertNull($this->game(4)->square(0, 55));               // then the way home: off the ring
        foreach ([1, 7] as $n) {
            try { new LudoRoyal($n); $this->assertSame('refused', "accepted $n"); }
            catch (LudoRoyalError $e) { $this->assertSame('bad_players', $e->getMessage()); }
        }
    }

    /** TC-INT-002 (K-43): the special squares are at 0 and 9 (crown) and 5 and 12 (feather) of every arm of 14 */
    public function testSpecialSquares(): void
    {
        $crowns = array_values(array_filter(range(0, 55), [LudoRoyal::class, 'isCrown']));
        $feathers = array_values(array_filter(range(0, 55), [LudoRoyal::class, 'isFeather']));
        $this->assertSame([0, 9, 14, 23, 28, 37, 42, 51], $crowns);
        $this->assertSame([5, 12, 19, 26, 33, 40, 47, 54], $feathers);
    }

    /** TC-INT-003 (K-47): out of the nest only on a 6; three tries with no piece out; a 6 rolls again */
    public function testComingOut(): void
    {
        $g = $this->game(2);
        foreach ([1, 2, LudoRoyal::CROWN, 4, LudoRoyal::FEATHER] as $d) $this->assertSame([], $g->legal(0, $d), "face $d");
        $g->roll(2); $this->assertSame([0, 1], [$g->turn, $g->tries]);   // first try
        $g->roll(4); $this->assertSame([0, 2], [$g->turn, $g->tries]);   // second
        $g->roll(1); $this->assertSame([1, 0], [$g->turn, $g->tries]);   // third: the turn passes
        $g->roll(6);                                                      // every piece in the nest goes to the same place: it moves at once
        $this->assertSame([0, self::N, self::N, self::N], $g->pos[1]);
        $this->assertSame([1, null], [$g->turn, $g->roll]);               // and a 6 rolls again
        $g->roll(2);
        $this->assertSame([2, self::N, self::N, self::N], $g->pos[1]);
        $this->assertSame(0, $g->turn);                                   // a piece out: no more tries

        $g = $this->game(2, [[5, self::N, self::N, self::N]]);
        $g->roll(1);                                                      // with a piece out a miss is not a try
        $this->assertSame([6, 1], [$g->pos[0][0], $g->turn]);
        $g = $this->game(2, [[60, self::N, self::N, self::N]]);           // a piece in the goal is not "out"
        $g->roll(3);
        $this->assertSame([0, 1], [$g->turn, $g->tries]);
    }

    /** TC-INT-004 (K-47, AK-33): a feather jumps on to the next feather, but not past the last ring square */
    public function testFeathers(): void
    {
        $g = $this->game(2, [[1, self::N, self::N, self::N]]);
        $this->assertSame([0 => 12], $g->legal(0, 4));                    // 1 + 4 = the feather on the corner square: on to 12
        $this->assertSame(19, $this->game(2, [[8, self::N, self::N, self::N]])->target(0, 0, 4));
        $this->assertSame(54, $this->game(2, [[50, self::N, self::N, self::N]])->target(0, 0, 4));   // the last feather: stays
        $this->assertSame(16, $this->game(2, [[12, self::N, self::N, self::N]])->target(0, 0, 4));    // from a feather: an ordinary move
        // the feather face: to the next feather; none left, or on the way home: no move
        $this->assertSame(26, $this->game(2, [[19, self::N, self::N, self::N]])->target(0, 0, LudoRoyal::FEATHER));
        $this->assertNull($this->game(2, [[54, self::N, self::N, self::N]])->target(0, 0, LudoRoyal::FEATHER));
        $this->assertNull($this->game(2, [[56, self::N, self::N, self::N]])->target(0, 0, LudoRoyal::FEATHER));

        $g = $this->game(2, [[1, 6, self::N, self::N]]);
        $g->roll(4);                                                      // two choices: the game waits
        $this->assertSame(4, $g->roll);
        $g->move(0);
        $this->assertSame(['k' => 'move', 'i' => 0, 'f' => 1, 't' => 12, 'j' => 5], array_intersect_key(end($g->events), array_flip(['k', 'i', 'f', 't', 'j'])));
    }

    /** TC-INT-005 (K-47): the crown face moves to the nearest crown ahead; never out of the nest, never on the way home */
    public function testCrownFace(): void
    {
        $g = $this->game(2, [[1, 9, 51, self::N]]);
        $this->assertSame([0 => 9, 1 => 14], $g->legal(0, LudoRoyal::CROWN));   // 51 is the last crown: no move for piece 3
        $this->assertNull($this->game(2, [[57, self::N, self::N, self::N]])->target(0, 0, LudoRoyal::CROWN));
    }

    /** TC-INT-006 (K-47, AK-32): decision table for landing on another colour's piece */
    public function testLanding(): void
    {
        // a plain square: the piece standing there is knocked to its nest
        $g = $this->game(2, [[29, self::N, self::N, self::N], [3, self::N, self::N, self::N]]);   // player 1 (arm 2) on square 31
        $g->roll(2);
        $this->assertSame([31, self::N], [$g->pos[0][0], $g->pos[1][0]]);
        $this->assertSame([[1, 0]], $g->events[1]['x']);

        // a crown: the piece standing there is safe, the one landing goes home itself
        $g = $this->game(2, [[35, self::N, self::N, self::N], [9, self::N, self::N, self::N]]);   // player 1 on square 37, a crown
        $g->roll(2);
        $this->assertSame([self::N, 9], [$g->pos[0][0], $g->pos[1][0]]);
        $this->assertTrue($g->events[1]['home']);

        // the crown face onto a crown with another colour: home itself too
        $g = $this->game(2, [[30, self::N, self::N, self::N], [9, self::N, self::N, self::N]]);
        $g->roll(LudoRoyal::CROWN);
        $this->assertSame([self::N, 9], [$g->pos[0][0], $g->pos[1][0]]);

        // coming out onto your own start square knocks whoever stands there
        $g = $this->game(2, [[self::N, self::N, self::N, self::N], [28, self::N, self::N, self::N]]);   // player 1 on square 0
        $g->roll(6);
        $this->assertSame([0, self::N], [$g->pos[0][0], $g->pos[1][0]]);

        // own pieces share a square
        $g = $this->game(2, [[2, 4, self::N, self::N]]);
        $g->roll(2);
        $g->move(0);
        $this->assertSame([4, 4], [$g->pos[0][0], $g->pos[0][1]]);
    }

    /** TC-INT-007 (K-47): the way home, the exact goal and the win */
    public function testHomeAndWin(): void
    {
        $g = $this->game(2, [[54, 60, 60, 60]]);
        $this->assertSame([0 => 55], $g->legal(0, 1));                    // from the last ring square onto the way home
        $this->assertSame([0 => 60], $g->legal(0, 6));                    // exactly into the goal
        $this->assertSame([], $this->game(2, [[57, 60, 60, 60]])->legal(0, 4));   // past the goal: no move
        $g->roll(6);
        $this->assertSame([0, 'win'], [$g->winner, end($g->events)['k']]);
        try { $g->roll(1); $this->assertSame('refused', 'rolled after the win'); }
        catch (LudoRoyalError $e) { $this->assertSame('game_over', $e->getMessage()); }
        $this->assertNull($this->game(2, [[60, self::N, self::N, self::N]])->target(0, 0, 1));   // a piece in the goal stays
    }

    /** TC-INT-008 (K-44, AK-30): a move needs a roll, a piece that may move, and only one roll at a time */
    public function testMoveNeedsARollAndALegalPiece(): void
    {
        $g = $this->game(2, [[3, 9, self::N, self::N]]);
        foreach ([[fn() => $g->move(0), 'roll_first'], [fn() => $g->roll(0), 'bad_roll'], [fn() => $g->roll(7), 'bad_roll']] as [$f, $code]) {
            try { $f(); $this->assertSame('refused', 'accepted'); } catch (LudoRoyalError $e) { $this->assertSame($code, $e->getMessage()); }
        }
        $g->roll(2);
        $before = $g->toState();
        foreach ([[2, 'bad_piece'], [4, 'bad_piece'], [-1, 'bad_piece']] as [$i, $code]) {   // in the nest with a 2; no such piece
            try { $g->move($i); $this->assertSame('refused', "moved $i"); } catch (LudoRoyalError $e) { $this->assertSame($code, $e->getMessage()); }
        }
        try { $g->roll(3); $this->assertSame('refused', 'rolled twice'); } catch (LudoRoyalError $e) { $this->assertSame('move_first', $e->getMessage()); }
        $this->assertSame($before, $g->toState());                        // nothing changed
        $g->move(1);
        $this->assertSame([11, 1], [$g->pos[0][1], $g->turn]);            // 9 + 2 = 11, then the other player
        $this->assertSame($g->toState(), LudoRoyal::fromState($g->toState())->toState());   // the state survives a round trip
    }

    /** TC-INT-009 (K-42): six players on the six-armed board play round the same way */
    public function testSixPlayers(): void
    {
        $g = $this->game(6, [5 => [82, self::N, self::N, self::N]], 5);
        $this->assertSame(68, $g->square(5, 82));                         // (82 + 14 · 5) % 84
        $g->roll(6);                                                      // a 6: piece 1 or one from the nest
        $g->move(0);
        $this->assertSame(88, $g->pos[5][0]);                             // 82 + 6 = 88: the goal of the six-armed board
        $g = $this->game(6, [], 5);
        $g->roll(1); $g->roll(1); $g->roll(1);
        $this->assertSame(0, $g->turn);                                   // after the last player comes the first
    }

    /** TC-INT-010 (K-49, AK-36): the computer's pick: the goal, a knock, out of the nest; never home itself from a crown */
    public function testComputersPick(): void
    {
        $g = $this->game(2, [[54, 3, self::N, self::N]]);
        $g->roll(6);
        $this->assertSame(0, $g->best());                                 // into the goal
        $g = $this->game(2, [[2, 20, self::N, self::N], [32, self::N, self::N, self::N]]);   // player 1 on square 4
        $g->roll(2);
        $this->assertSame(0, $g->best());                                 // knock it rather than walk on
        $g = $this->game(2, [[7, 10, self::N, self::N], [37, self::N, self::N, self::N]]);   // player 1 safe on crown 9
        $g->roll(2);
        $this->assertSame(1, $g->best());                                 // not onto the crown: it would go home
        $g = $this->game(2, [[10, self::N, self::N, self::N]]);
        $g->roll(6);
        $this->assertSame(1, $g->best());                                 // out of the nest
        try { $this->game(2)->best(); $this->assertSame('refused', 'picked without a roll'); }
        catch (LudoRoyalError $e) { $this->assertSame('roll_first', $e->getMessage()); }
    }

    /** TC-INT-069 (K-42, K-43): a game begun with 13 squares an arm goes on with every piece on the same square of the board of 14 */
    public function testOldGameMovesOn(): void
    {
        $old = ['board' => 'royal4', 'arms' => 4, 'seat' => [0, 2], 'turn' => 1, 'roll' => null, 'tries' => 0, 'winner' => null,
                'pos' => [[self::N, 4, 5, 12], [26, 50, 51, 56]]];   // no 'arm': 13 an arm, a ring of 52, the goal at 56
        $st = LudoRoyal::fromState($old)->toState();
        // before the corner square, from it on one more, the last ring square, the way home and the goal moved along
        $this->assertSame([[self::N, 4, 6, 13], [28, 54, 55, 60]], $st['pos']);
        $this->assertSame([14, 1], [$st['arm'], $st['turn']]);
        $this->assertSame($st, LudoRoyal::fromState($st)->toState());   // moved once, never again
        $six = LudoRoyal::fromState(['arms' => 6, 'pos' => array_fill(0, 5, [13, 76, 77, 82])] + $old)->toState();
        $this->assertSame([14, 82, 83, 88], $six['pos'][0]);           // the six-armed board: 78 round becomes 84
    }
}
