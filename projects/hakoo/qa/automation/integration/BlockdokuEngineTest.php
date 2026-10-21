<?php
/**
 * Component tests of the Blockdoku rules (src/blockdoku-engine.inc.php): the blocks, where they fit, what is
 * cleared, the points and the end of a game. No database or HTTP.
 * Traceability: FR-BLOCKDOKU-01..04.
 */
require_once '/var/www/html/blockdoku-engine.inc.php';

use PHPUnit\Framework\TestCase;

class BlockdokuEngineTest extends TestCase
{
    /** A board with the squares listed as "r,c" filled. */
    private function board(array $filled): string
    {
        $b = Blockdoku::emptyBoard();
        foreach ($filled as [$r, $c]) $b[$r * 9 + $c] = '1';
        return $b;
    }
    private function row(int $r, array $except = []): array
    {
        return array_values(array_filter(array_map(fn($c) => [$r, $c], range(0, 8)), fn($s) => !in_array($s[1], $except, true)));
    }

    /** TC-UNIT-BD-01 | FR-BLOCKDOKU-01: the blocks are the seven Tetris pieces in every turn, four squares each; three are dealt */
    public function test_the_blocks()
    {
        $this->assertCount(19, Blockdoku::SHAPES);
        $kinds = array_unique(array_map(fn($k) => $k[0], array_keys(Blockdoku::SHAPES)));
        sort($kinds);
        $this->assertSame(['I', 'J', 'L', 'O', 'S', 'T', 'Z'], $kinds);
        foreach (Blockdoku::SHAPES as $k => $cells) {
            $this->assertCount(4, $cells, $k);
            $this->assertCount(4, array_unique(array_map(fn($s) => implode(',', $s), $cells)), "$k: four different squares");
            $this->assertSame(0, min(array_column($cells, 0)), "$k starts at the top");
            $this->assertSame(0, min(array_column($cells, 1)), "$k starts at the left");
        }
        $hand = Blockdoku::deal();
        $this->assertCount(3, $hand);
        foreach ($hand as $k) $this->assertArrayHasKey($k, Blockdoku::SHAPES);
    }

    /** TC-UNIT-BD-02 | FR-BLOCKDOKU-02: a block fits only on empty squares inside the board */
    public function test_fits()
    {
        $b = $this->board([[4, 4]]);
        $this->assertTrue(Blockdoku::fits($b, 'O', 0, 0));
        $this->assertTrue(Blockdoku::fits($b, 'O', 7, 7));
        $this->assertFalse(Blockdoku::fits($b, 'O', 8, 8), 'over the edge');
        $this->assertFalse(Blockdoku::fits($b, 'I1', 0, 6), 'over the edge');
        $this->assertFalse(Blockdoku::fits($b, 'O', 3, 3), 'on a filled square');
        $this->assertFalse(Blockdoku::fits($b, 'nope', 0, 0));
        $this->expectException(BlockdokuError::class);
        Blockdoku::put($b, 'O', 3, 3);
    }

    /** TC-UNIT-BD-03 | FR-BLOCKDOKU-02, FR-BLOCKDOKU-03: full rows, columns and 3 x 3 boxes are cleared, and each counts */
    public function test_clearing()
    {
        // A row: the long block finishes row 4
        [$b, $n] = Blockdoku::put($this->board($this->row(4, [5, 6, 7, 8])), 'I1', 4, 5);
        $this->assertSame([Blockdoku::emptyBoard(), 1], [$b, $n]);
        // A column: the square block finishes column 6 (rows 0 and 1)
        $col = array_map(fn($r) => [$r, 6], range(2, 8));
        [$b, $n] = Blockdoku::put($this->board($col), 'O', 0, 6);
        $this->assertSame(1, $n);
        $this->assertSame('1', $b[0 * 9 + 7], 'the square block\'s other column stays');
        $this->assertSame('0', $b[5 * 9 + 6]);
        // A box: the middle box, all but the T
        $box = [[3, 3], [3, 4], [3, 5], [4, 3], [5, 3], [5, 4]];
        [$b, $n] = Blockdoku::put($this->board($box), 'T1', 4, 4);   // (4,4) (4,5) (4,6) (5,5)
        $this->assertSame(1, $n);
        $this->assertSame('1', $b[4 * 9 + 6], 'the square outside the box stays');
        // A row and a column at once (they share a square)
        $cross = array_merge($this->row(6, [3]), array_map(fn($r) => [$r, 3], [0, 1, 2, 3, 4, 8]));
        [$b, $n] = Blockdoku::put($this->board($cross), 'L1', 5, 3);  // (5,3) (6,3) (7,3) (7,4)
        $this->assertSame(2, $n);
        $this->assertSame('1', $b[7 * 9 + 4]);
        $this->assertSame(1, substr_count($b, '1'));
    }

    /** TC-UNIT-BD-04 | FR-BLOCKDOKU-03: points = 9 x cleared x (cleared + moves in a row that cleared - 1) */
    public function test_points()
    {
        $this->assertSame(0, Blockdoku::points(0, 0));
        $this->assertSame(9, Blockdoku::points(1, 1));
        $this->assertSame(36, Blockdoku::points(2, 1));
        $this->assertSame(81, Blockdoku::points(3, 1));
        $this->assertSame(27, Blockdoku::points(1, 3));
        $this->assertSame(54, Blockdoku::points(2, 2));
    }

    /** TC-UNIT-BD-05 | FR-BLOCKDOKU-03, FR-BLOCKDOKU-04: a move: the run, the score, new blocks when the hand is empty, the end */
    public function test_moves_and_the_end()
    {
        $deal = fn() => ['O', 'O', 'O'];
        $s = ['board' => $this->board($this->row(0, [7, 8])), 'hand' => ['O', 'I2', 'T1'], 'score' => 0, 'streak' => 0, 'moves' => 0, 'over' => false];
        [$s, $done] = Blockdoku::move($s, 0, 0, 7, $deal);           // finishes row 0
        $this->assertSame(['cleared' => 1, 'points' => 9], $done);
        $this->assertSame([9, 1, 1, [null, 'I2', 'T1']], [$s['score'], $s['streak'], $s['moves'], $s['hand']]);
        [$s, $done] = Blockdoku::move($s, 1, 4, 0, $deal);           // clears nothing: the run ends
        $this->assertSame([0, 0, 9], [$done['points'], $s['streak'], $s['score']]);
        [$s] = Blockdoku::move($s, 2, 5, 3, $deal);                  // the last of the three: three new ones
        $this->assertSame(['O', 'O', 'O'], $s['hand']);
        $this->assertFalse($s['over']);
        try { Blockdoku::move($s, 1, 4, 0, $deal); $this->fail('a filled square'); } catch (BlockdokuError $e) { $this->assertSame('no_room', $e->getMessage()); }
        try { Blockdoku::move(['hand' => [null, 'O', 'O']] + $s, 0, 0, 0, $deal); $this->fail('a placed block'); } catch (BlockdokuError $e) { $this->assertSame('no_piece', $e->getMessage()); }

        // The end: a chequered board (no two free squares side by side, so nothing is ever full) with room for one
        // long block in the first column. Placed, it is the last of the hand; the new square blocks fit nowhere.
        $chequer = Blockdoku::emptyBoard();
        for ($i = 0; $i < 81; $i++) if ((intdiv($i, 9) + $i % 9) % 2 === 0) $chequer[$i] = '1';
        foreach ([0, 1, 2, 3] as $r) $chequer[$r * 9] = '0';
        $this->assertFalse(Blockdoku::fitsAnywhere($chequer, 'O'));
        $this->assertTrue(Blockdoku::fitsAnywhere($chequer, 'I2'));
        $end = ['board' => $chequer, 'hand' => ['I2', null, null], 'score' => 5, 'streak' => 0, 'moves' => 3, 'over' => false];
        [$s, $done] = Blockdoku::move($end, 0, 0, 0, $deal);
        $this->assertSame([0, ['O', 'O', 'O']], [$done['cleared'], $s['hand']]);
        $this->assertTrue($s['over'], 'none of the new blocks fits: game over');
        $this->expectException(BlockdokuError::class);
        Blockdoku::move($s, 1, 0, 0, $deal);
    }
}
