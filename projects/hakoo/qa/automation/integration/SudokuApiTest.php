<?php
/**
 * System tests of the Sudoku API (src/sudoku.php) over HTTP.
 * Traceability: FR-SUDOKU-03..06, FR-PUZZLE-01, FR-GAMES-07, US-38.
 */
require_once '/var/www/html/sudoku-engine.inc.php';

class SudokuApiTest extends HakooTestCase
{
    private function api(string $action, ?array $body = null, string $query = '', ?string $cookie = null): array
    {
        return $this->http($body === null ? 'GET' : 'POST', "sudoku.php?action=$action$query", $body, $cookie);
    }

    private function today(): string
    {
        return (new DateTimeImmutable('now', new DateTimeZone('Europe/Stockholm')))->format('Y-m-d');
    }

    /** TC-API-231 | FR-SUDOKU-03: a practice puzzle has the given digits but never the solution; a seed gives the same puzzle again */
    public function test_practice_puzzles()
    {
        [$status, $headers, , $j] = $this->api('puzzle', null, '&level=easy&seed=7');
        $this->assertSame(200, $status);
        $p = $j['puzzle'];
        $this->assertSame(['easy', 7, Sudoku::generate('easy', 7)['grid'], null], [$p['level'], $p['seed'], $p['grid'], $p['day']]);
        $this->assertArrayNotHasKey('solution', $p, 'the solution stays on the server');
        $this->assertEmpty(array_filter($headers, fn($h) => stripos($h, 'Set-Cookie:') === 0));
        [, , , $any] = $this->api('puzzle', null, '&level=hard');
        $this->assertMatchesRegularExpression('/^[0-9]{81}$/', $any['puzzle']['grid']);
        [$status, , , $e] = $this->api('puzzle', null, '&level=giant');
        $this->assertSame([400, 'bad_level'], [$status, $e['error']]);
        [$status, , , $e] = $this->api('puzzle', null, '&level=easy&seed=0');
        $this->assertSame([400, 'bad_seed'], [$status, $e['error']]);
    }

    /** TC-API-232 | FR-SUDOKU-04: the check: the solution solves it, a grid that breaks a rule does not; bad requests are refused */
    public function test_solve_anonymous()
    {
        $sol = Sudoku::generate('easy', 7)['solution'];
        [, $headers, , $j] = $this->api('solve', ['level' => 'easy', 'seed' => 7, 'grid' => $sol]);
        $this->assertSame([true, null], [$j['solved'], $j['unlocked']]);
        $this->assertEmpty(array_filter($headers, fn($h) => stripos($h, 'Set-Cookie:') === 0));
        $wrong = $sol;
        $wrong[3] = $wrong[3] === '9' ? '1' : (string)((int)$wrong[3] + 1);
        [, , , $j] = $this->api('solve', ['level' => 'easy', 'seed' => 7, 'grid' => $wrong]);
        $this->assertSame(['status' => 'ok', 'solved' => false], $j);
        foreach ([['level' => 'easy', 'seed' => '7', 'grid' => $sol], ['level' => 'easy', 'seed' => 7], ['level' => 'easy', 'seed' => -3, 'grid' => $sol]] as $bad) {
            [$status, , , $e] = $this->api('solve', $bad);
            $this->assertSame([400, 'bad_request'], [$status, $e['error']], json_encode($bad));
        }
        [$status] = $this->api('solve', null, '&level=easy');
        $this->assertSame(405, $status);
    }

    /** TC-API-233 | FR-SUDOKU-05, FR-PUZZLE-01: the guests' puzzle of the day (nothing stored) is not the members'; the members' one needs a login; a member's time, list, streak and reward */
    public function test_daily()
    {
        [, , , $a] = $this->api('daily', ['level' => 'medium']);
        [, , , $b] = $this->api('daily', ['level' => 'medium']);
        $this->assertSame($a['puzzle'], $b['puzzle']);
        $guest = Sudoku::dailySeed('medium', $this->today(), true);
        $this->assertSame([$this->today(), $guest, false], [$a['puzzle']['day'], $a['puzzle']['seed'], $a['loggedIn']]);
        [, , , $s] = $this->api('solve', ['level' => 'medium', 'seed' => $guest, 'grid' => Sudoku::generate('medium', $guest)['solution'], 'day' => $this->today()]);
        $this->assertTrue($s['solved']);
        $this->assertArrayNotHasKey('board', $s);
        $this->assertSame(0, $this->rows('hakoo_sudoku_daily', 'day = ?', [$this->today()]));
        $seed = Sudoku::dailySeed('medium', $this->today());
        $this->assertNotSame($guest, $seed, 'members get another puzzle');
        $sol = Sudoku::generate('medium', $seed)['solution'];
        [$status, , , $e] = $this->api('solve', ['level' => 'medium', 'seed' => $seed, 'grid' => $sol, 'day' => $this->today()]);
        $this->assertSame([401, 'login_needed'], [$status, $e['error']], 'the members\' puzzle is not checked logged out');
        [$status, , , $e] = $this->api('solve', ['level' => 'medium', 'seed' => $seed, 'grid' => $sol, 'day' => '2026-01-01']);
        $this->assertSame([409, 'day_over'], [$status, $e['error']]);

        $carol = $this->sessionCookie(self::CAROL, 'Carol');
        [, , , $o] = $this->api('daily', ['level' => 'medium'], '', $carol);
        $this->assertSame([true, $seed, null, 0, []], [$o['loggedIn'], $o['puzzle']['seed'], $o['you']['seconds'], $o['streak'], $o['board']]);
        $this->pdo->prepare('UPDATE hakoo_sudoku_daily SET created_at = NOW() - INTERVAL 70 SECOND WHERE discord_id = ?')->execute([self::CAROL]);
        $this->api('daily', ['level' => 'medium'], '', $carol);                 // opening again does not restart the clock
        [, , , $s] = $this->api('solve', ['level' => 'medium', 'seed' => $seed, 'grid' => $sol, 'day' => $this->today()], '', $carol);
        $this->assertSame(['medium', 1, [['name' => 'Carol', 'title' => '', 'seconds' => $s['seconds'], 'you' => true]]], [$s['unlocked'], $s['streak'], $s['board']]);
        $this->assertGreaterThanOrEqual(70, $s['seconds']);
        $this->assertLessThan(100, $s['seconds']);
        [, , , $again] = $this->api('solve', ['level' => 'medium', 'seed' => $seed, 'grid' => $sol, 'day' => $this->today()], '', $carol);
        $this->assertSame([null, $s['seconds']], [$again['unlocked'], $again['seconds']], 'the time counts once; the reward once');
        [, , , $t] = $this->api('today', null, '', $carol);
        $this->assertSame([$s['seconds'], 1, 1], [$t['levels']['medium']['you']['seconds'], $t['levels']['medium']['solvers'], $t['streak']]);
        $this->assertNull($t['levels']['easy']['you'], 'looking at the start page starts no clock');
        [, , , $bob] = $this->api('board', null, '&level=medium', $this->sessionCookie(self::BOB, 'Bob'));
        $this->assertSame([['name' => 'Carol', 'title' => '', 'seconds' => $s['seconds'], 'you' => false]], $bob['board']);
    }

    /** TC-API-234 | FR-SUDOKU-06, FR-GAMES-07: rewards and look like the board games; Alice's Hard puzzle gives a crown */
    public function test_rewards_look_and_crown()
    {
        $carol = $this->sessionCookie(self::CAROL);
        [, , , $s] = $this->api('solve', ['level' => 'easy', 'seed' => 7, 'grid' => Sudoku::generate('easy', 7)['solution']], '', $carol);
        $this->assertSame('easy', $s['unlocked']);
        [, , , $me] = $this->api('me', null, '', $carol);
        $this->assertSame(['easy' => true, 'medium' => false, 'hard' => false], $me['unlocked']);
        [$status, , , $ok] = $this->api('look', ['digit' => 'teal', 'mark' => 'pink', 'board' => 'night'], '', $carol);
        $this->assertSame([200, 'teal', 'night'], [$status, $ok['look']['digit'], $ok['look']['board']]);
        [$status, , , $e] = $this->api('look', ['digit' => 'teal', 'mark' => 'pink', 'cells3d' => true], '', $carol);
        $this->assertSame([403, 'locked'], [$status, $e['error']]);
        [$status, , , $e] = $this->api('look', ['digit' => 'teal', 'mark' => 'teal'], '', $carol);
        $this->assertSame([400, 'same_colors'], [$status, $e['error']]);
        [, , , $c] = $this->api('crowns');
        $this->assertSame([self::ALICE], $c['ids']);
        [, , , $pop] = $this->http('GET', 'games-popular.php');
        $this->assertArrayHasKey('sudoku', $pop['counts']);
    }

    /** TC-API-320 | FR-PUZZLE-03: a member's squares are kept to go on later on another device; the clock goes on; guests keep nothing */
    public function test_saves()
    {
        $carol = $this->sessionCookie(self::CAROL, 'Carol');
        [, , , $p] = $this->api('puzzle', null, '&level=easy', $carol);
        $seed = $p['puzzle']['seed'];
        $this->assertSame([true, false], [$p['saves'], isset($p['saved'])]);
        [$status, , , $j] = $this->api('save', ['level' => 'easy', 'seed' => $seed, 'state' => 'AbC-_9', 'seconds' => 300], '', $carol);
        $this->assertSame([200, true], [$status, $j['saved']]);

        // Another device: Practice Easy without a number is the same puzzle, its squares, and the clock from 5:00 on
        $this->pdo->prepare('UPDATE hakoo_puzzle_saves SET started_at = started_at - INTERVAL 60 SECOND WHERE discord_id = ?')->execute([self::CAROL]);
        [, , , $p] = $this->api('puzzle', null, '&level=easy', $carol);
        $this->assertSame([$seed, 'AbC-_9'], [$p['puzzle']['seed'], $p['saved']]);
        $this->assertGreaterThanOrEqual(360, $p['clock'], 'the clock went on while away');
        [, , , $t] = $this->api('today', null, '', $carol);
        $this->assertSame(['easy'], array_keys($t['practice']));
        [, , , $n] = $this->api('puzzle', null, '&level=easy&new=1', $carol);
        $this->assertArrayNotHasKey('saved', $n, 'New puzzle: a new one');

        // The puzzle of the day: the squares, and the clock from when it was opened
        $day = $this->today();
        $ds = Sudoku::dailySeed('hard', $day);
        $this->api('daily', ['level' => 'hard'], '', $carol);
        $this->pdo->prepare('UPDATE hakoo_sudoku_daily SET created_at = NOW() - INTERVAL 90 SECOND WHERE discord_id = ?')->execute([self::CAROL]);
        $this->api('save', ['level' => 'hard', 'seed' => $ds, 'day' => $day, 'state' => 'XYZ'], '', $carol);
        [, , , $d] = $this->api('daily', ['level' => 'hard'], '', $carol);
        $this->assertSame('XYZ', $d['saved']);
        $this->assertGreaterThanOrEqual(90, $d['clock']);
        [$status, , , $e] = $this->api('save', ['level' => 'hard', 'seed' => $ds, 'day' => '2026-01-01', 'state' => 'XYZ'], '', $carol);
        $this->assertSame([409, 'day_over'], [$status, $e['error']]);

        // Solved: the practice puzzle is not kept any more
        $this->api('solve', ['level' => 'easy', 'seed' => $seed, 'grid' => Sudoku::generate('easy', $seed)['solution']], '', $carol);
        $this->assertSame(0, $this->rows('hakoo_puzzle_saves', "game = 'sudoku' AND discord_id = ? AND daily = 0", [self::CAROL]));
        $this->assertSame(1, $this->rows('hakoo_puzzle_saves', "game = 'sudoku' AND discord_id = ? AND daily = 1", [self::CAROL]));

        // Guests and bad requests
        [$status, , , $e] = $this->api('save', ['level' => 'easy', 'seed' => $seed, 'state' => 'A']);
        $this->assertSame([401, 'not_logged_in'], [$status, $e['error']]);
        [, , , $g] = $this->api('puzzle', null, '&level=easy');
        $this->assertSame(false, $g['saves']);
        foreach ([['level' => 'easy', 'seed' => $seed, 'state' => 'a b'], ['level' => 'easy', 'seed' => $seed, 'state' => str_repeat('A', 256)],
                  ['level' => 'easy', 'seed' => (string)$seed, 'state' => 'A']] as $bad) {
            [$status, , , $e] = $this->api('save', $bad, '', $carol);
            $this->assertSame([400, 'bad_request'], [$status, $e['error']], json_encode($bad));
        }
        [$status] = $this->api('save', null, '&level=easy', $carol);
        $this->assertSame(405, $status);
    }
}
