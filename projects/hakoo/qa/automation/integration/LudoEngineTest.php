<?php
/**
 * Component tests of the Ludo (Fia med knuff) rules and computer players (src/ludo-engine.inc.php).
 * No database or HTTP: the engine is pure logic.
 * Test design: the rule variants (exit 6 / 6 or 1; finish exact / bounce / reach; three tries) and
 * their boundaries (the last ring square, the home column, the goal).
 * Kunglig Vikingludo (TC-UNIT-LUDO-10..15): the two boards, the crown and feather squares and faces
 * (boundaries: the last ring square, the last crown and feather, the exact goal) and a decision table for
 * landing (crown or not × another colour there × coming out of the nest).
 * The classic board is the royal board of 4 arms without crowns and feathers (TC-UNIT-LUDO-16, FR-LUDO-02).
 * Traceability: FR-LUDO-01..06, FR-LUDO-19, FR-LUDO-20, FR-LUDO-23.
 */
require_once '/var/www/html/ludo-engine.inc.php';

use PHPUnit\Framework\TestCase;

class LudoEngineTest extends TestCase
{
    private function game(array $rules = [], array $seats = [0, 2], int $first = 0): LudoGame
    {
        return new LudoGame(LudoRules::fromArray($rules), $seats, $first);
    }

    private function assertError(string $code, callable $fn): void
    {
        try {
            $fn();
            $this->fail("expected LudoError $code");
        } catch (LudoError $e) {
            $this->assertSame($code, $e->getMessage());
        }
    }

    /** TC-UNIT-LUDO-01 | FR-LUDO-02: a piece leaves the nest on a 6, or also on a 1 when chosen; otherwise the turn passes */
    public function test_leaving_the_nest()
    {
        $g = $this->game(['exit' => '6', 'tries' => false]);
        $g->roll(1);
        $this->assertSame(['roll', 'pass'], array_column($g->events, 'k'));
        $this->assertSame(2, $g->turn);
        $g = $this->game(['exit' => '6or1', 'tries' => false]);
        $g->roll(1);                                         // four pieces in the nest: one choice, so it moves at once
        $this->assertSame([0, -1, -1, -1], $g->pos[0]);
        $this->assertSame(2, $g->turn);
        $g = $this->game(['exit' => '6']);
        $g->roll(6);
        $this->assertSame(0, $g->pos[0][0]);
        $this->assertSame(0, $g->turn, 'a 6 gives another roll');
    }

    /** TC-UNIT-LUDO-02 | FR-LUDO-03: landing on another colour knocks it home; own pieces share a square; the home column is safe */
    public function test_knocking_out()
    {
        $g = $this->game([], [0, 2]);
        $g->pos[0] = [25, 30, -1, -1];
        $g->pos[2] = [0, -1, -1, -1];                        // seat 2's start square is ring square 28 = seat 0's progress 28
        $g->roll(3, fn() => 0);                              // 25 + 3 = 28
        $this->assertSame([-1, -1, -1, -1], $g->pos[2]);
        $this->assertSame([[2, 0]], end($g->events)['x']);
        $g = $this->game([], [0, 2], 0);
        $g->pos[0] = [5, 2, -1, -1];
        $g->roll(3, fn() => 1);
        $this->assertSame([5, 5, -1, -1], $g->pos[0], 'own pieces may stand together');
        // A piece in its home column is never on the ring, so nobody can land on it.
        $this->assertNull($g->at(2, 57));
    }

    /** TC-UNIT-LUDO-03 | FR-LUDO-04: into the goal with an exact roll, bouncing back, or reaching it */
    public function test_finishing_variants()
    {
        foreach ([['exact', 5, null], ['exact', 3, 60], ['bounce', 5, 58], ['bounce', 6, 57], ['reach', 6, 60], ['reach', 2, 59]] as [$finish, $d, $want]) {
            $g = $this->game(['finish' => $finish]);
            $g->pos[0] = [57, -1, -1, -1];
            $this->assertSame($want, $g->target(0, 0, $d), "$finish with $d");
        }
        // The last ring square (54) turns into the home column; the square before the start (55) is never stepped on.
        $g = $this->game();
        $g->pos[0] = [54, -1, -1, -1];
        $this->assertSame(55, $g->target(0, 0, 1));
        $this->assertSame(54, $g->at(0, 54));
        $this->assertNull($g->at(0, 55));
        $this->assertSame(12, $g->at(1, 54), 'seat 1 turns in after ring square 12');
    }

    /** TC-UNIT-LUDO-04 | FR-LUDO-05: a player with no piece on the ring gets three tries, otherwise one */
    public function test_three_tries()
    {
        $g = $this->game(['exit' => '6', 'tries' => true]);
        $g->roll(2); $this->assertSame([0, 1], [$g->turn, $g->tries]);
        $g->roll(3); $this->assertSame([0, 2], [$g->turn, $g->tries]);
        $g->roll(4); $this->assertSame([2, 0], [$g->turn, $g->tries], 'the third miss passes the turn');
        $g = $this->game(['exit' => '6', 'tries' => true], [0, 2], 0);
        $g->pos[0] = [60, 60, 59, -1];                       // in the goal and the home column: none on the ring
        $g->roll(5);
        $this->assertSame(0, $g->turn);
        $g = $this->game(['exit' => '6', 'tries' => false]);
        $g->roll(2);
        $this->assertSame(2, $g->turn);
    }

    /** TC-UNIT-LUDO-05 | FR-LUDO-01, FR-LUDO-06: a choice waits for move(); wrong requests are refused; all four home wins */
    public function test_choices_errors_and_winning()
    {
        $g = $this->game();
        $g->pos[0] = [3, 10, -1, -1];
        $g->roll(4);
        $this->assertSame(4, $g->roll, 'two pieces can move: the player chooses');
        $this->assertError('move_first', fn() => $g->roll(2));
        $this->assertError('bad_piece', fn() => $g->move(2));   // in the nest, a 4 does not bring it out
        $this->assertError('bad_piece', fn() => $g->move(7));
        $g->move(1);
        $this->assertSame([[3, 14, -1, -1], 2, null], [$g->pos[0], $g->turn, $g->roll]);
        $this->assertError('roll_first', fn() => $g->move(0));
        $g = $this->game([], [0, 2], 0);
        $g->pos[0] = [60, 60, 60, 57];
        $g->roll(3);
        $this->assertSame(0, $g->winner);
        $this->assertSame('win', end($g->events)['k']);
        $this->assertError('game_over', fn() => $g->roll(1));
        $this->assertError('bad_rules', fn() => LudoRules::fromArray(['finish' => 'fly']));
        $this->assertError('bad_rules', fn() => LudoRules::fromArray(['tries' => 'yes']));
        $this->assertError('bad_seats', fn() => new LudoGame(new LudoRules(), [0], 0));
    }

    /** TC-UNIT-LUDO-07 | FR-LUDO-14: with blocks, two or more pieces of a colour make a block: others pass it, one landing on it goes home */
    public function test_blocks()
    {
        $g = $this->game(['block' => true], [0, 2], 0);
        $g->pos[2] = [5, 5, -1, -1];                         // seat 2's progress 5 = ring square 33 = seat 0's progress 33
        $g->pos[0] = [29, -1, -1, -1];
        $this->assertSame(34, $g->target(0, 0, 5), 'passing the block is fine');
        $g->roll(4);                                         // lands on the block
        $this->assertSame([-1, -1, -1, -1], $g->pos[0], 'the one who landed goes home');
        $this->assertSame([5, 5, -1, -1], $g->pos[2], 'the block stays');
        $this->assertSame([[0, 0]], end($g->events)['x']);
        // Without the rule the landing knocks both pieces out.
        $g = $this->game([], [0, 2], 0);
        $g->pos[2] = [5, 5, -1, -1];
        $g->pos[0] = [30, -1, -1, -1];
        $g->roll(3);
        $this->assertSame([-1, -1, -1, -1], $g->pos[2]);
        // One piece alone is no block: it is knocked out as usual.
        $g = $this->game(['block' => true], [0, 2], 0);
        $g->pos[2] = [5, -1, -1, -1];
        $g->pos[0] = [30, -1, -1, -1];
        $g->roll(3);
        $this->assertSame([[33, -1, -1, -1], [-1, -1, -1, -1]], [$g->pos[0], $g->pos[2]]);
        // Any number of own pieces may share a square, and three are a block as well as two.
        $g = $this->game(['block' => true], [0, 2], 0);
        $g->pos[0] = [0, 0, -1, 3];
        $this->assertSame(0, $g->target(0, 2, 6), 'a third piece onto the start square');
        $g->pos[0] = [7, 7, 7, 4];
        $this->assertSame(7, $g->target(0, 3, 3), 'a fourth piece');
        $g->pos[2] = [32, -1, -1, -1];                       // seat 2's progress 32 = ring square 4; seat 0's 7 = ring square 7
        $g->turn = 2;
        $g->roll(3);
        $this->assertSame([[-1, -1, -1, -1], [7, 7, 7, 4]], [$g->pos[2], $g->pos[0]], 'landing on a block of three: home');
        // Medium never lands on a block.
        $g = $this->game(['block' => true], [0, 2], 0);
        $g->pos[2] = [5, 5, -1, -1];
        $g->pos[0] = [29, 10, -1, -1];
        $this->assertSame(1, (new LudoAI('medium'))->choose($g, 0, 4, $g->legal(0, 4)));
    }
    /** TC-UNIT-LUDO-08 | FR-LUDO-15: with a safe start, a piece on its own start square is safe: whoever lands there goes home */
    public function test_safe_start()
    {
        $g = $this->game(['safeStart' => true], [0, 2], 0);
        $g->pos[2] = [0, -1, -1, -1];                        // on its start square: ring square 28
        $g->pos[0] = [25, -1, -1, -1];
        $g->roll(3);
        $this->assertSame([-1, -1, -1, -1], $g->pos[0], 'the one who landed goes home');
        $this->assertSame([0, -1, -1, -1], $g->pos[2]);
        $this->assertSame([[0, 0]], end($g->events)['x']);
        // The start square is only safe with its own colour on it.
        $g = $this->game(['safeStart' => true], [0, 2], 0);
        $g->pos[2] = [3, -1, -1, -1];
        $g->pos[0] = [28, -1, -1, -1];                       // seat 0 stands on seat 2's start square (28)
        $g->turn = 2;
        $g->roll(6, fn() => 1);                              // seat 2 comes out onto its start square
        $this->assertSame([-1, -1, -1, -1], $g->pos[0], 'coming out knocks the visitor away');
        // Medium never walks onto a safe start square.
        $g = $this->game(['safeStart' => true], [0, 2], 0);
        $g->pos[2] = [0, -1, -1, -1];
        $g->pos[0] = [25, 5, -1, -1];
        $this->assertSame(1, (new LudoAI('medium'))->choose($g, 0, 3, $g->legal(0, 3)));
    }

    /** TC-UNIT-LUDO-09 | FR-LUDO-16: Fia med hutt: whoever is knocked out owes a sup, and the game waits for it */
    public function test_hutt()
    {
        $g = $this->game(['hutt' => true], [0, 2], 0);
        $g->pos[2] = [0, -1, -1, -1];
        $g->pos[0] = [25, -1, -1, -1];
        $g->roll(3);
        $this->assertSame([2], $g->drink);
        $this->assertError('drink_first', fn() => $g->roll(4));
        $this->assertError('no_sup', fn() => $g->drank(0));
        $g->drank(2);
        $this->assertSame([[], 'skal'], [$g->drink, end($g->events)['k']]);
        $g->roll(4);                                         // the game goes on
        $this->assertSame([2, 'pass'], [end($g->events)['s'], end($g->events)['k']], 'knocked home, a 4 cannot bring it out');
        // With a safe start the one who landed drinks; without hutt nobody does.
        $g = $this->game(['hutt' => true, 'safeStart' => true], [0, 2], 0);
        $g->pos[2] = [0, -1, -1, -1];
        $g->pos[0] = [25, -1, -1, -1];
        $g->roll(3);
        $this->assertSame([0], $g->drink);
        $g = $this->game([], [0, 2], 0);
        $g->pos[2] = [0, -1, -1, -1];
        $g->pos[0] = [25, -1, -1, -1];
        $g->roll(3);
        $this->assertSame([], $g->drink);
        $this->assertSame(['exit' => '6or1', 'finish' => 'exact', 'tries' => true, 'block' => true, 'safeStart' => false, 'hutt' => true, 'board' => 'classic'],
            LudoRules::fromArray(['block' => true, 'hutt' => true])->toArray());
        $this->assertError('bad_rules', fn() => LudoRules::fromArray(['hutt' => 1]));
    }

    /** TC-UNIT-LUDO-06 | FR-LUDO-07: the computer players always choose a legal piece, finish games, and Medium knocks out when it can */
    public function test_computer_players()
    {
        foreach (['easy', 'medium', 'hard'] as $level) {
            $ai = new LudoAI($level);
            $g = new LudoGame(new LudoRules('6or1', 'bounce', true), [0, 1, 2, 3], 0);
            for ($n = 0; $n < 5000 && !$g->isOver(); $n++) {
                $g->roll(random_int(1, 6), function ($game, $seat, $d, $legal) use ($ai) {
                    $i = $ai->choose($game, $seat, $d, $legal);
                    $this->assertArrayHasKey($i, $legal);
                    return $i;
                });
            }
            $this->assertTrue($g->isOver(), "$level finishes a game");
        }
        $g = $this->game([], [0, 2], 0);
        $g->pos[0] = [25, 5, -1, -1];
        $g->pos[2] = [0, -1, -1, -1];
        $this->assertSame(0, (new LudoAI('medium'))->choose($g, 0, 3, $g->legal(0, 3)), 'knocks out the piece on square 28');
    }

    // ── Kunglig Vikingludo ─────────────────────────────────────────────────────────────────────

    private function viking(array $seats = [0, 2], int $first = 0, array $pos = []): LudoGame
    {
        $g = $this->game(['board' => 'viking'], $seats, $first);
        foreach ($pos as $s => $ps) $g->pos[$s] = $ps;
        return $g;
    }

    /** TC-UNIT-LUDO-10 | FR-LUDO-19, FR-LUDO-20: the boards by number of players; the viking rules are fixed; seats outside the board are refused */
    public function test_viking_boards()
    {
        $g = $this->viking();
        $this->assertSame([4, 56, 55, 60], [$g->arms, $g->ring(), $g->steps(), $g->goal()]);
        $this->assertSame([28, 54, null], [$g->at(2, 0), $g->at(0, 54), $g->at(0, 55)]);   // 55: the first home square
        $g = $this->viking([0, 1, 2, 3, 4]);
        $this->assertSame([6, 84, 88, 6], [$g->arms, $g->ring(), $g->goal(), count($g->pos)]);
        $this->assertSame(69, $g->at(4, 13));
        $this->assertNull($g->pos[5]);
        $this->assertSame(['exit' => '6', 'finish' => 'exact', 'tries' => true, 'block' => false, 'safeStart' => false, 'hutt' => true, 'board' => 'viking'],
            LudoRules::fromArray(['board' => 'viking', 'exit' => '6or1', 'finish' => 'reach', 'tries' => false, 'block' => true, 'safeStart' => true, 'hutt' => true])->toArray());
        $this->assertError('bad_rules', fn() => LudoRules::fromArray(['board' => 'hex']));
        $this->assertError('bad_seats', fn() => $this->viking([0, 4]));                   // two players: the royal board has seats 0-3
        $this->assertError('bad_seats', fn() => $this->game([], [0, 1, 2, 3, 4]));          // the classic board has four seats
        $this->assertSame([0, 9, 14, 23], array_values(array_filter(range(0, 27), [LudoGame::class, 'isCrown'])));
        $this->assertSame([5, 12, 19, 26], array_values(array_filter(range(0, 27), [LudoGame::class, 'isFeather'])));   // 5: the corner square
    }

    /** TC-UNIT-LUDO-11 | FR-LUDO-19: the crown and feather faces; landing on a feather jumps on; never out of the nest or on the home squares */
    public function test_viking_faces_and_feathers()
    {
        $N = LudoGame::NEST;
        $g = $this->viking(pos: [0 => [1, 10, 50, 2]]);
        $this->assertSame([0 => 12, 1 => 14, 2 => 54, 3 => 6], $g->legal(0, 4));         // 1+4 is the corner feather 5: on to 12; 50+4 is the last feather
        $this->assertSame(19, $g->target(0, 1, 2));                                       // 10+2 is feather 12: on to 19
        $this->assertSame([0 => 9, 1 => 14, 2 => 51, 3 => 9], $g->legal(0, LudoGame::CROWN));
        $this->assertSame([0 => 5, 1 => 12, 2 => 54, 3 => 5], $g->legal(0, LudoGame::FEATHER));
        $g = $this->viking(pos: [0 => [51, 54, 57, $N]]);
        $this->assertNull($g->target(0, 0, LudoGame::CROWN));                             // no crown left on its lap
        $this->assertNull($g->target(0, 1, LudoGame::FEATHER));                           // no feather left
        $this->assertNull($g->target(0, 2, LudoGame::CROWN));                             // nothing special on the home squares
        $this->assertSame([59, null], [$g->target(0, 2, 2), $g->target(0, 2, 4)]);        // the goal (60) needs the exact roll
        $this->assertSame([null, null, 0], [$g->target(0, 3, LudoGame::CROWN), $g->target(0, 3, 1), $g->target(0, 3, 6)]);   // out only on a 6
    }

    /** TC-UNIT-LUDO-12 | FR-LUDO-19: decision table for landing: a plain square knocks; a crown sends the lander home; coming out onto your own start knocks */
    public function test_viking_landing()
    {
        $N = LudoGame::NEST;
        $g = $this->viking(pos: [0 => [29, $N, $N, $N], 2 => [3, $N, $N, $N]]);        // seat 2 on ring square 31
        $g->roll(2);
        $this->assertSame([31, $N], [$g->pos[0][0], $g->pos[2][0]]);
        $this->assertSame([[2, 0]], end($g->events)['x']);
        $g = $this->viking(pos: [0 => [35, $N, $N, $N], 2 => [9, $N, $N, $N]]);        // seat 2 safe on crown 37
        $g->roll(2);
        $this->assertSame([$N, 9], [$g->pos[0][0], $g->pos[2][0]]);
        $this->assertSame([[0, 0]], end($g->events)['x']);                               // the lander itself went home
        $g = $this->viking(pos: [0 => [34, $N, $N, $N], 2 => [9, $N, $N, $N]]);
        $g->roll(LudoGame::CROWN);                                                         // the crown face onto it: home too
        $this->assertSame([$N, 9], [$g->pos[0][0], $g->pos[2][0]]);
        $g = $this->viking(pos: [0 => [$N, $N, $N, $N], 2 => [28, $N, $N, $N]]);        // seat 2 on seat 0's start (a crown)
        $g->roll(6);
        $this->assertSame([0, $N], [$g->pos[0][0], $g->pos[2][0]]);                       // coming out knocks it
        $g = $this->viking(pos: [0 => [1, 6, $N, $N]]);
        $g->roll(4);
        $g->move(0);
        $this->assertSame(['f' => 1, 't' => 12, 'j' => 5], array_intersect_key(end($g->events), array_flip(['f', 't', 'j'])));   // jumped from the corner feather 5
    }

    /** TC-UNIT-LUDO-13 | FR-LUDO-19: three tries with no piece out (a piece on the home squares is out, one in the goal is not); the win */
    public function test_viking_tries_and_win()
    {
        $N = LudoGame::NEST;
        $g = $this->viking(pos: [0 => [60, $N, $N, $N]]);
        $g->roll(1);
        $this->assertSame([0, 1], [$g->turn, $g->tries]);                                 // still all in the nest or the goal: another try
        $g = $this->viking(pos: [0 => [58, $N, $N, $N]]);
        $g->roll(4);
        $this->assertSame(2, $g->turn);                                                   // a piece out: one roll only
        $g = $this->viking(pos: [0 => [54, 60, 60, 60]]);
        $g->roll(6);
        $this->assertSame([0, 'win'], [$g->winner, end($g->events)['k']]);
    }

    /** TC-UNIT-LUDO-14 | FR-LUDO-23: the computers finish Vikingludo on both boards and never land on someone safe on a crown */
    public function test_viking_computer_players()
    {
        $N = LudoGame::NEST;
        foreach (['easy', 'medium', 'hard'] as $level) {
            foreach ([[0, 2], [0, 1, 2, 3, 4, 5]] as $seats) {
                $ai = new LudoAI($level);
                $g = $this->viking($seats, 0);
                for ($n = 0; $n < 20000 && !$g->isOver(); $n++) {
                    $g->roll(random_int(1, 6), function ($game, $seat, $d, $legal) use ($ai) {
                        $i = $ai->choose($game, $seat, $d, $legal);
                        $this->assertArrayHasKey($i, $legal);
                        return $i;
                    });
                }
                $this->assertTrue($g->isOver(), "$level finishes a game of " . count($seats));
            }
        }
        $g = $this->viking(pos: [0 => [7, 1, $N, $N], 2 => [37, $N, $N, $N]]);         // seat 2 safe on crown 9 (37 + 28 = 65 -> 9)
        foreach (['medium', 'hard'] as $level) {
            $this->assertSame(1, (new LudoAI($level))->choose($g, 0, 2, $g->legal(0, 2)), "$level does not walk onto the crown");
        }
    }

    /** TC-UNIT-LUDO-15 | FR-LUDO-20: a game begun on the boards of 13 squares an arm goes on with every piece on the same square */
    public function test_viking_upgrade_from_13_an_arm()
    {
        $N = LudoGame::NEST;
        $state = ['pos' => [[$N, 4, 5, 51], null, [12, 13, 31, 56], null], 'seats' => [0, 2], 'turn' => 0, 'roll' => null, 'tries' => 0, 'winner' => null, 'drink' => []];
        $events = [['s' => 0, 'k' => 'roll', 'v' => 4], ['s' => 0, 'k' => 'move', 'p' => 1, 'f' => 0, 't' => 10, 'j' => 4, 'x' => []]];
        LudoGame::upgrade(LudoRules::fromArray(['board' => 'viking']), $state, $events);
        // 4 stays; 5 and on move one up (the corner square); a new arm every 14; home squares and goal at the new ring
        $this->assertSame([[$N, 4, 6, 55], null, [13, 14, 34, 60], null], $state['pos']);
        $this->assertSame(['f' => 0, 't' => 11, 'j' => 4], array_intersect_key($events[1], array_flip(['f', 't', 'j'])));
        $this->assertSame(['s' => 0, 'k' => 'roll', 'v' => 4], $events[0], 'a roll is not a place');
        $again = $state;
        LudoGame::upgrade(LudoRules::fromArray(['board' => 'viking']), $again, $events);
        $this->assertSame($state, $again, 'only once');
        $g = LudoGame::fromState(LudoRules::fromArray(['board' => 'viking']), $state);
        $this->assertSame(14, $g->toState()['arm']);
        $nest = [$N, $N, $N, $N];
        $six = ['pos' => [[77, 78, 66, $N], $nest, $nest, $nest, $nest, null], 'seats' => [0, 1, 2, 3, 4]];
        $e = [];
        LudoGame::upgrade(LudoRules::fromArray(['board' => 'viking']), $six, $e);
        $this->assertSame([83, 84, 71], array_slice($six['pos'][0], 0, 3), 'the six-armed board: its first two home squares, and 66 = 13·5 + 1 -> 14·5 + 1');
    }

    /** TC-UNIT-LUDO-16 | FR-LUDO-02: the classic board is the royal board without crowns and feathers; a game begun on the board of 40 goes on */
    public function test_classic_board_of_14_an_arm()
    {
        $N = LudoGame::NEST;
        $g = $this->game([], [0, 1, 2, 3]);
        $this->assertSame([4, 56, 55, 60], [$g->arms, $g->ring(), $g->steps(), $g->goal()]);
        $this->assertSame([14, 28, 42], [$g->at(1, 0), $g->at(2, 0), $g->at(3, 0)]);
        $g->pos[0] = [1, 4, 7, $N];
        $this->assertSame([0 => 5, 1 => 8, 2 => 11], $g->legal(0, 4));                   // 5 and 12 are plain squares here: no jump
        $this->assertSame([0 => 4, 1 => 7, 2 => 10], $g->legal(0, 3));                    // and a 3 is a 3
        $g->pos[2] = [9, $N, $N, $N];                                                      // on ring square 37 (a crown in Vikingludo)
        $g->pos[0] = [34, $N, $N, $N];
        $g->roll(3);
        $this->assertSame([37, $N], [$g->pos[0][0], $g->pos[2][0]]);                       // knocked out as anywhere else

        // A game from before 2026-10-20: 10 squares an arm, 4 home squares, the goal at 44.
        $state = ['pos' => [[$N, 4, 5, 39], null, [10, 20, 40, 44], null], 'seats' => [0, 2], 'turn' => 0, 'roll' => null, 'tries' => 0, 'winner' => null, 'drink' => []];
        $events = [['s' => 0, 'k' => 'roll', 'v' => 6], ['s' => 0, 'k' => 'move', 'p' => 1, 'f' => 0, 't' => 9, 'x' => []]];
        LudoGame::upgrade(LudoRules::fromArray([]), $state, $events);
        // the same stretch of each arm; ring square 39 (the last) -> 54; home squares 40..43 -> 56..59; the goal 44 -> 60
        $this->assertSame([[$N, 4, 6, 54], null, [14, 28, 56, 60], null], $state['pos']);
        $this->assertSame(['f' => 0, 't' => 12], array_intersect_key($events[1], array_flip(['f', 't'])));
        $this->assertSame(14, $state['arm']);
        $again = $state;
        LudoGame::upgrade(LudoRules::fromArray([]), $again, $events);
        $this->assertSame($state, $again, 'only once');
        $g = LudoGame::fromState(LudoRules::fromArray([]), $state);
        $this->assertSame([$g->at(0, 4), $g->at(2, 14)], [4, 42]);                        // the pieces stand where the board says
        $this->assertSame(14, $g->toState()['arm']);
    }
}
