<?php
/**
 * System tests of the squares kept for the puzzles of the day (puzzle-daily.inc.php: pz_practice, pz_daily_saves,
 * pz_save) in Owldoku and Nonogram over HTTP; Sudoku has its own (SudokuApiTest::test_saves).
 * Traceability: FR-PUZZLE-03, US-38.
 */
require_once '/var/www/html/owldoku-engine.inc.php';
require_once '/var/www/html/nonogram-engine.inc.php';

class PuzzleSavesApiTest extends HakooTestCase
{
    private function api(string $game, string $action, ?array $body = null, string $query = '', ?string $cookie = null): array
    {
        return $this->http($body === null ? 'GET' : 'POST', "$game.php?action=$action$query", $body, $cookie);
    }

    private function today(): string
    {
        return (new DateTimeImmutable('now', new DateTimeZone('Europe/Stockholm')))->format('Y-m-d');
    }

    /** The answer the solve action wants. */
    private function answer(string $game, string $level, int $seed): string
    {
        if ($game === 'nonogram') return implode('', Nonogram::generate($level, $seed)['grid']);
        $p = Owldoku::generate($level, $seed);
        $g = str_repeat('0', $p['size'] ** 2);
        foreach ($p['owls'] as $r => $c) $g[$r * $p['size'] + $c] = '1';
        return $g;
    }

    public static function games(): array
    {
        return ['owldoku' => ['owldoku', Owldoku::class], 'nonogram' => ['nonogram', Nonogram::class]];
    }

    /**
     * TC-API-322 | FR-PUZZLE-03: a member's squares are kept for practice and the puzzle of the day, to go on on another
     * device with the clock going on; Start over keeps an empty grid; a solved practice puzzle is let go; guests keep nothing
     * @dataProvider games
     */
    public function test_squares_kept(string $game, string $engine)
    {
        $carol = $this->sessionCookie(self::CAROL, 'Carol');
        [, , , $p] = $this->api($game, 'puzzle', null, '&level=easy', $carol);
        $seed = $p['puzzle']['seed'];
        $this->assertSame([true, false], [$p['saves'], isset($p['saved'])]);
        [$status, , , $j] = $this->api($game, 'save', ['level' => 'easy', 'seed' => $seed, 'state' => 'Qk-_', 'seconds' => 120], '', $carol);
        $this->assertSame([200, true], [$status, $j['saved']]);
        [, , , $again] = $this->api($game, 'puzzle', null, '&level=easy', $carol);              // another device
        $this->assertSame([$seed, 'Qk-_'], [$again['puzzle']['seed'], $again['saved']]);
        $this->assertGreaterThanOrEqual(120, $again['clock']);
        [, , , $t] = $this->api($game, 'today', null, '', $carol);
        $this->assertSame(['easy'], array_keys($t['practice']));

        // Start over: the grid is kept empty, the clock from 0
        $this->api($game, 'save', ['level' => 'easy', 'seed' => $seed, 'state' => '', 'seconds' => 0], '', $carol);
        [, , , $again] = $this->api($game, 'puzzle', null, '&level=easy', $carol);
        $this->assertSame('', $again['saved']);
        $this->assertLessThan(5, $again['clock']);

        // The puzzle of the day: the squares, and the clock from when it was opened
        $day = $this->today();
        $ds = $engine::dailySeed('medium', $day);
        $this->api($game, 'daily', ['level' => 'medium'], '', $carol);
        $this->pdo->prepare("UPDATE hakoo_{$game}_daily SET created_at = NOW() - INTERVAL 50 SECOND WHERE discord_id = ?")->execute([self::CAROL]);
        $this->api($game, 'save', ['level' => 'medium', 'seed' => $ds, 'day' => $day, 'state' => 'AAE'], '', $carol);
        [, , , $d] = $this->api($game, 'daily', ['level' => 'medium'], '', $carol);
        $this->assertSame([true, 'AAE'], [$d['saves'], $d['saved']]);
        $this->assertGreaterThanOrEqual(50, $d['clock']);
        [, , , $other] = $this->api($game === 'owldoku' ? 'nonogram' : 'owldoku', 'daily', ['level' => 'medium'], '', $carol);
        $this->assertArrayNotHasKey('saved', $other, 'each game keeps its own');

        // Solved: the practice puzzle is not kept any more; the puzzle of the day is
        $this->api($game, 'solve', ['level' => 'easy', 'seed' => $seed, 'grid' => $this->answer($game, 'easy', $seed)], '', $carol);
        $this->assertSame(0, $this->rows('hakoo_puzzle_saves', 'game = ? AND discord_id = ? AND daily = 0', [$game, self::CAROL]));
        $this->assertSame(1, $this->rows('hakoo_puzzle_saves', 'game = ? AND discord_id = ? AND daily = 1', [$game, self::CAROL]));

        // Guests: nothing kept; the guests' puzzle of the day is not kept for a member either
        [$status, , , $e] = $this->api($game, 'save', ['level' => 'easy', 'seed' => $seed, 'state' => 'A']);
        $this->assertSame([401, 'not_logged_in'], [$status, $e['error']]);
        [, , , $j] = $this->api($game, 'save', ['level' => 'medium', 'seed' => $engine::dailySeed('medium', $day, true), 'day' => $day, 'state' => 'A'], '', $carol);
        $this->assertFalse($j['saved']);
        [$status, , , $e] = $this->api($game, 'save', ['level' => 'easy', 'seed' => $seed, 'state' => str_repeat('A', 256)], '', $carol);
        $this->assertSame([400, 'bad_request'], [$status, $e['error']]);
    }
}
