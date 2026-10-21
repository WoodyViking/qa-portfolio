<?php
/**
 * Unit tests of Runborg's rules and computer (src/runborg-engine.inc.php), without the database.
 * Traceability: FR-RUNBORG-01..05, FR-RUNBORG-16, FR-RUNBORG-17.
 */
require_once '/var/www/html/runborg-engine.inc.php';

class RunborgEngineTest extends PHPUnit\Framework\TestCase
{
    private function rand(): callable { return fn(int $k) => random_int(0, $k - 1); }

    /** A game with a known hand for the player (the cards given by id). */
    private function game(string $board, string $level, array $hand, array $me = []): array
    {
        $s = Runborg::start($board, $level, $this->rand());
        $s['me']['hand'] = $hand;
        foreach ($me as $k => $v) $s['me'][$k] = $v;
        return $s;
    }
    private function id(string $name): int
    {
        foreach (Runborg::CARDS as $id => $c) if ($c[1] === $name) return $id;
        $this->fail("no card $name");
    }

    /** TC-UNIT-RB-01 | FR-RUNBORG-01: every board starts from its own numbers, the opponent with its estate; 6 cards each, the player's production given */
    public function test_start_per_board_and_opponent()
    {
        $s = Runborg::start('winter', 'farmer', $this->rand());
        $this->assertSame([15, 10, 10 + 2, 5 + 2, 5 + 2], [$s['me']['hall'], $s['me']['pal'], $s['me']['t'], $s['me']['r'], $s['me']['w']]);
        $this->assertSame([15, 10, 10, 5, 5], [$s['them']['hall'], $s['them']['pal'], $s['them']['t'], $s['them']['r'], $s['them']['w']]);
        $this->assertCount(6, $s['me']['hand']);
        $this->assertCount(6, $s['them']['hand']);
        $this->assertCount(72 - 12, $s['deck']);
        $k = Runborg::start('fjord', 'king', $this->rand());
        $this->assertSame([28, 15, 9, 3, 3, 4], [$k['them']['hall'], $k['them']['pal'], $k['them']['w'], $k['them']['forest'], $k['them']['stone'], $k['them']['mead']]);
        $this->assertSame(20, $k['me']['hall']);                               // the player has no estate bonus
        $this->expectException(RunborgError::class);
        Runborg::start('mars', 'farmer', $this->rand());
    }

    /** TC-UNIT-RB-02 | FR-RUNBORG-02: a card is paid for; one that cannot be paid for is refused (it can be thrown away); damage takes the palisade first */
    public function test_paying_and_damage()
    {
        $s = $this->game('fjord', 'farmer', [$this->id('Longship'), $this->id('Levy')], ['w' => 8]);
        $s['them']['pal'] = 4; $s['them']['hall'] = 20;
        Runborg::act($s, 'me', 0, false, $this->rand());                       // Longship: 10 damage
        $this->assertSame([0, 0, 14], [$s['me']['w'], $s['them']['pal'], $s['them']['hall']]);
        $this->assertCount(2, $s['me']['hand']);                              // one played, one drawn
        $s = $this->game('fjord', 'farmer', [$this->id("Valhalla's Call")], ['w' => 3]);
        try { Runborg::act($s, 'me', 0, false, $this->rand()); $this->fail('paid'); }
        catch (RunborgError $e) { $this->assertSame('cannot_pay', $e->getMessage()); }
        Runborg::act($s, 'me', 0, true, $this->rand());                        // thrown away: allowed
        $this->assertSame(3, $s['me']['w']);
        $s = $this->game('fjord', 'farmer', [$this->id("Thor's Hammer")], ['r' => 9]);
        $s['them']['pal'] = 30;
        Runborg::act($s, 'me', 0, false, $this->rand());                       // past the palisade
        $this->assertSame([30, 12], [$s['them']['pal'], $s['them']['hall']]);
    }

    /** TC-UNIT-RB-03 | FR-RUNBORG-03: each board's goal wins (Winter: hall 40 and palisade 20 at once), and the enemy hall at 0 everywhere */
    public function test_winning_per_board()
    {
        $s = $this->game('winter', 'farmer', [$this->id('Carve a Rune')], ['hall' => 40, 'pal' => 19, 'r' => 5]);
        Runborg::act($s, 'me', 0, false, $this->rand());                       // hall 42 but palisade 19: not yet
        $this->assertNull($s['over']);
        $s = $this->game('winter', 'farmer', [$this->id('Split Logs')], ['hall' => 40, 'pal' => 18, 't' => 5]);
        Runborg::act($s, 'me', 0, false, $this->rand());                       // palisade 21 with hall 40
        $this->assertSame(['me', 'hall:40,pal:20'], [$s['over'], $s['why']]);
        $s = $this->game('fjord', 'farmer', [$this->id('Carve a Rune')], ['hall' => 48, 'r' => 5]);
        Runborg::act($s, 'me', 0, false, $this->rand());
        $this->assertSame(['me', 'hall:50'], [$s['over'], $s['why']]);
        $s = $this->game('winter', 'farmer', [$this->id('Carve a Rune')], ['hall' => 48, 'r' => 5]);
        Runborg::act($s, 'me', 0, false, $this->rand());                       // a hall of 50 is not Winter's goal
        $this->assertNull($s['over']);
        $s = $this->game('dusk', 'farmer', [$this->id('Wolf Warriors')], ['w' => 7]);
        $s['them']['hall'] = 5;
        Runborg::act($s, 'me', 0, false, $this->rand());
        $this->assertSame(['me', 'fell'], [$s['over'], $s['why']]);
        try { Runborg::act($s, 'me', 0, false, $this->rand()); $this->fail('played on'); }
        catch (RunborgError $e) { $this->assertSame('game_over', $e->getMessage()); }
    }

    /** TC-UNIT-RB-04 | FR-RUNBORG-02: "again" keeps the turn; passing gives the other side its production */
    public function test_again_and_turns()
    {
        $s = $this->game('fjord', 'farmer', [$this->id('Shield Wall'), $this->id('Split Logs')], ['t' => 10]);
        $this->assertTrue(Runborg::act($s, 'me', 0, false, $this->rand()));
        $this->assertFalse(Runborg::act($s, 'me', 0, false, $this->rand()));
        $t = $s['them']['t'];
        Runborg::pass($s);
        $this->assertSame(['them', $t + $s['them']['forest']], [$s['turn'], $s['them']['t']]);
        try { Runborg::act($s, 'me', 0, false, $this->rand()); $this->fail('out of turn'); }
        catch (RunborgError $e) { $this->assertSame('not_your_turn', $e->getMessage()); }
    }

    /** TC-UNIT-RB-05 | FR-RUNBORG-04: every opponent only plays what it can pay for; a whole game always ends with a winner */
    public function test_computer_plays_by_the_rules()
    {
        foreach (Runborg::LEVELS as $level) {
            foreach (['fjord', 'winter'] as $board) {
                $s = Runborg::start($board, $level, $this->rand());
                while ($s['over'] === null && $s['turns'] < 400) {
                    $who = $s['turn'];
                    [$i, $d] = Runborg::choose($s, $who, $this->rand(), $who === 'me' ? 'jarl' : null);
                    if (!$d) $this->assertTrue(Runborg::canPay($s[$who], $s[$who]['hand'][$i]), "$level paid for nothing");
                    $again = Runborg::act($s, $who, $i, $d, $this->rand());
                    if (!$again && $s['over'] === null) Runborg::pass($s);
                }
                $this->assertNotNull($s['over'], "$level on $board never ended");
            }
        }
    }

    /** TC-UNIT-RB-06 | FR-RUNBORG-04: the ladder: over many games each opponent beats the one below it (self-play, as qa/tools/runborg-balance.php) */
    public function test_each_opponent_beats_the_one_below()
    {
        $L = Runborg::LEVELS;
        for ($i = 1; $i < count($L); $i++) {
            $win = 0;
            for ($g = 0; $g < 120; $g++) {
                [$a, $b] = $g % 2 ? [$L[$i], $L[$i - 1]] : [$L[$i - 1], $L[$i]];
                $s = Runborg::start(Runborg::BOARDS[$g % 5], $b, $this->rand(), $a);
                while ($s['over'] === null && $s['turns'] < 400) {
                    $who = $s['turn'];
                    [$k, $d] = Runborg::choose($s, $who, $this->rand(), $who === 'me' ? $a : $b);
                    $again = Runborg::act($s, $who, $k, $d, $this->rand());
                    if (!$again && $s['over'] === null) Runborg::pass($s);
                }
                if ($s['over'] !== null && ($s['over'] === 'me' ? $a : $b) === $L[$i]) $win++;
            }
            $this->assertGreaterThan(60, $win, "{$L[$i]} should beat {$L[$i - 1]} (won $win of 120)");
        }
    }

    /** TC-UNIT-RB-08 | FR-RUNBORG-16: a raid's loot is yours: what the enemy loses, you get; never more than they have */
    public function test_raids_steal_what_they_take()
    {
        $s = $this->game('fjord', 'farmer', [$this->id('Plunder')], ['t' => 1, 'r' => 1, 'w' => 6]);
        $s['them']['t'] = 10; $s['them']['r'] = 2; $s['them']['w'] = 4;
        Runborg::act($s, 'me', 0, false, $this->rand());                       // Plunder: up to 4 of each
        $this->assertSame([6, 0, 0], [$s['them']['t'], $s['them']['r'], $s['them']['w']]);
        $this->assertSame([1 + 4, 1 + 2, 6 - 6 + 4], [$s['me']['t'], $s['me']['r'], $s['me']['w']]);   // only the 2 runes they had
        $s = $this->game('fjord', 'farmer', [$this->id('Timber Raid')], ['t' => 2]);
        $s['them']['t'] = 9;
        Runborg::act($s, 'me', 0, false, $this->rand());
        $this->assertSame([5, 4], [$s['them']['t'], $s['me']['t']]);
        $s = $this->game('fjord', 'farmer', [$this->id("Loki's Trick")], ['r' => 4, 't' => 0, 'w' => 0]);
        $s['them']['t'] = 3; $s['them']['w'] = 8;
        Runborg::act($s, 'me', 0, false, $this->rand());
        $this->assertSame([0, 4, 3, 4], [$s['them']['t'], $s['them']['w'], $s['me']['t'], $s['me']['w']]);
    }

    /** TC-UNIT-RB-09 | FR-RUNBORG-17: Seeress's Vision throws away the card the player picks (its place before playing), and plays again; a wrong pick is refused */
    public function test_vision_throws_away_the_picked_card()
    {
        $hand = [$this->id('Levy'), $this->id("Seeress's Vision"), $this->id('Windfall'), $this->id("Valhalla's Call")];
        $s = $this->game('fjord', 'farmer', $hand, ['r' => 2]);
        $again = Runborg::act($s, 'me', 1, false, $this->rand(), 2);          // keep Valhalla's Call, throw away Windfall
        $this->assertTrue($again);
        $this->assertContains($this->id("Valhalla's Call"), $s['me']['hand']);
        $this->assertContains($this->id('Levy'), $s['me']['hand']);
        $this->assertSame($this->id('Windfall'), $s['used'][count($s['used']) - 1]);
        $this->assertCount(4, $s['me']['hand']);                               // two out, two drawn
        $s = $this->game('fjord', 'farmer', $hand, ['r' => 2]);
        Runborg::act($s, 'me', 1, false, $this->rand(), 0);                    // a card before the Vision in the hand
        $this->assertSame($this->id('Levy'), $s['used'][count($s['used']) - 1]);
        $s = $this->game('fjord', 'farmer', $hand, ['r' => 2]);
        Runborg::act($s, 'me', 1, false, $this->rand());                       // no pick (the computer): the furthest from paid for
        $this->assertSame($this->id("Valhalla's Call"), $s['used'][count($s['used']) - 1]);
        foreach ([[1, 1, false], [1, 9, false], [0, 2, false], [1, 2, true]] as [$i, $toss, $discard]) {
            $s = $this->game('fjord', 'farmer', $hand, ['r' => 2, 'w' => 5]);
            try { Runborg::act($s, 'me', $i, $discard, $this->rand(), $toss); $this->fail("toss $toss with card $i"); }
            catch (RunborgError $e) { $this->assertSame('bad_toss', $e->getMessage()); }
        }
    }

    /** TC-UNIT-RB-07 | FR-RUNBORG-05: what the page may see: the numbers of both sides, never a hand */
    public function test_snap_has_no_hands()
    {
        $s = Runborg::start('aurora', 'jarl', $this->rand());
        $snap = Runborg::snap($s);
        $this->assertArrayNotHasKey('hand', $snap['them']);
        $this->assertSame(array_keys(Runborg::START), array_keys($snap['me']));
    }
}
