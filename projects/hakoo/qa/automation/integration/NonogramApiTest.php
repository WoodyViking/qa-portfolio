<?php
/**
 * System tests of the Nonogram API (src/nonogram.php) over HTTP.
 * Traceability: FR-NONOGRAM-03..07, FR-GAMES-07, US-37.
 */
require_once '/var/www/html/nonogram-engine.inc.php';

class NonogramApiTest extends HakooTestCase
{
    private function api(string $action, ?array $body = null, string $query = '', ?string $cookie = null): array
    {
        return $this->http($body === null ? 'GET' : 'POST', "nonogram.php?action=$action$query", $body, $cookie);
    }

    private function today(): string
    {
        return (new DateTimeImmutable('now', new DateTimeZone('Europe/Stockholm')))->format('Y-m-d');
    }

    private function answer(string $level, int $seed): string
    {
        return implode('', Nonogram::generate($level, $seed)['grid']);
    }

    /** TC-API-226 | FR-NONOGRAM-03: a practice puzzle has clues but never the solution; a seed gives the same puzzle again */
    public function test_practice_puzzles()
    {
        [$status, $headers, , $j] = $this->api('puzzle', null, '&level=easy');
        $this->assertSame(200, $status);
        $p = $j['puzzle'];
        $this->assertSame(['easy', 10, null], [$p['level'], $p['size'], $p['day']]);
        $this->assertCount(10, $p['rows']);
        $this->assertArrayNotHasKey('grid', $p, 'the solution stays on the server');
        $this->assertEmpty(array_filter($headers, fn($h) => stripos($h, 'Set-Cookie:') === 0));
        [, , , $again] = $this->api('puzzle', null, "&level=easy&seed={$p['seed']}");
        $this->assertSame($p, $again['puzzle']);
        [, , , $hard] = $this->api('puzzle', null, '&level=hard&seed=42');
        $this->assertSame(20, $hard['puzzle']['size']);

        [$status, , , $e] = $this->api('puzzle', null, '&level=giant');
        $this->assertSame([400, 'bad_level'], [$status, $e['error']]);
        [$status, , , $e] = $this->api('puzzle', null, '&level=easy&seed=0');
        $this->assertSame([400, 'bad_seed'], [$status, $e['error']]);
    }

    /** TC-API-227 | FR-NONOGRAM-04: the check: the right squares solve it, a wrong grid does not; bad requests are refused */
    public function test_solve_anonymous()
    {
        $grid = $this->answer('easy', 7);
        [, $headers, , $j] = $this->api('solve', ['level' => 'easy', 'seed' => 7, 'grid' => $grid]);
        $this->assertSame([true, null], [$j['solved'], $j['unlocked']]);
        $this->assertEmpty(array_filter($headers, fn($h) => stripos($h, 'Set-Cookie:') === 0));
        $grid[0] = $grid[0] === '1' ? '0' : '1';
        [, , , $j] = $this->api('solve', ['level' => 'easy', 'seed' => 7, 'grid' => $grid]);
        $this->assertSame(['status' => 'ok', 'solved' => false], $j);
        foreach ([['level' => 'easy', 'seed' => '7', 'grid' => $grid], ['level' => 'easy', 'seed' => 7], ['level' => 'easy', 'seed' => -3, 'grid' => $grid]] as $bad) {
            [$status, , , $e] = $this->api('solve', $bad);
            $this->assertSame([400, 'bad_request'], [$status, $e['error']], json_encode($bad));
        }
        [$status] = $this->api('solve', null, '&level=easy');
        $this->assertSame(405, $status);
    }

    /** TC-API-228 | FR-NONOGRAM-05: the guests' puzzle of the day is the same for every guest, not the members'; nothing is stored; the members' one needs a login; yesterday's is over */
    public function test_daily_anonymous()
    {
        [, , , $a] = $this->api('daily', ['level' => 'medium']);
        [, , , $b] = $this->api('daily', ['level' => 'medium']);
        $this->assertSame($a['puzzle'], $b['puzzle']);
        $this->assertSame([$this->today(), Nonogram::dailySeed('medium', $this->today(), true), false],
                          [$a['puzzle']['day'], $a['puzzle']['seed'], $a['loggedIn']]);
        $member = Nonogram::dailySeed('medium', $this->today());
        [$status, , , $e] = $this->api('solve', ['level' => 'medium', 'seed' => $member, 'grid' => $this->answer('medium', $member), 'day' => $this->today()]);
        $this->assertSame([401, 'login_needed'], [$status, $e['error']], 'the members\' puzzle is not checked logged out');
        $grid = $this->answer('medium', $a['puzzle']['seed']);
        [, , , $s] = $this->api('solve', ['level' => 'medium', 'seed' => $a['puzzle']['seed'], 'grid' => $grid, 'day' => $this->today()]);
        $this->assertTrue($s['solved']);
        $this->assertArrayNotHasKey('board', $s);
        $this->assertSame(0, $this->rows('hakoo_nonogram_daily', '1 = 1 AND day = ?', [$this->today()]));

        [$status, , , $e] = $this->api('solve', ['level' => 'medium', 'seed' => $a['puzzle']['seed'], 'grid' => $grid, 'day' => '2026-01-01']);
        $this->assertSame([409, 'day_over'], [$status, $e['error']]);
        [, , , $t] = $this->api('today');
        $this->assertSame([false, $this->today()], [$t['loggedIn'], $t['day']]);
        [$status, , , $e] = $this->api('board', null, '&level=medium');
        $this->assertSame([401, 'not_logged_in'], [$status, $e['error']]);
    }

    /** TC-API-229 | FR-NONOGRAM-05, FR-NONOGRAM-06: a member's time is measured from opening to solving, once; the list, the streak, the first reward */
    public function test_daily_member()
    {
        $carol = $this->sessionCookie(self::CAROL, 'Carol');
        [, , , $o] = $this->api('daily', ['level' => 'hard'], '', $carol);
        $this->assertSame([true, null, 0, []], [$o['loggedIn'], $o['you']['seconds'], $o['streak'], $o['board']]);
        // Opened 95 seconds ago: opening it again does not restart the clock.
        $this->pdo->prepare('UPDATE hakoo_nonogram_daily SET created_at = NOW() - INTERVAL 95 SECOND WHERE discord_id = ?')->execute([self::CAROL]);
        $this->api('daily', ['level' => 'hard'], '', $carol);
        $seed = $o['puzzle']['seed'];
        [, , , $s] = $this->api('solve', ['level' => 'hard', 'seed' => $seed, 'grid' => $this->answer('hard', $seed), 'day' => $this->today()], '', $carol);
        $this->assertTrue($s['solved']);
        $this->assertSame('hard', $s['unlocked'], 'the first Hard puzzle unlocks the crown');
        $this->assertGreaterThanOrEqual(95, $s['seconds']);
        $this->assertLessThan(120, $s['seconds']);
        $this->assertSame([['name' => 'Carol', 'title' => '', 'seconds' => $s['seconds'], 'you' => true]], $s['board']);
        $this->assertSame(1, $s['streak']);

        [, , , $again] = $this->api('solve', ['level' => 'hard', 'seed' => $seed, 'grid' => $this->answer('hard', $seed), 'day' => $this->today()], '', $carol);
        $this->assertSame([null, $s['seconds']], [$again['unlocked'], $again['seconds']], 'the time counts once; the reward once');

        [, , , $t] = $this->api('today', null, '', $carol);
        $this->assertSame([$s['seconds'], 1, 1], [$t['levels']['hard']['you']['seconds'], $t['levels']['hard']['solvers'], $t['streak']]);
        $this->assertNull($t['levels']['easy']['you'], 'looking at the start page starts no clock');
        // Bob sees Carol on today's list, but not as himself.
        [, , , $b] = $this->api('board', null, '&level=hard', $this->sessionCookie(self::BOB, 'Bob'));
        $this->assertSame([['name' => 'Carol', 'title' => '', 'seconds' => $s['seconds'], 'you' => false]], $b['board']);
        // Yesterday solved too: a streak of two.
        $this->pdo->prepare("INSERT INTO hakoo_nonogram_daily (discord_id, day, level, name, seconds) VALUES (?, ? - INTERVAL 1 DAY, 'easy', 'Carol', 60)")
            ->execute([self::CAROL, $this->today()]);
        [, , , $t] = $this->api('today', null, '', $carol);
        $this->assertSame(2, $t['streak']);
    }

    /** TC-API-230 | FR-NONOGRAM-06, FR-GAMES-07: rewards and look like the board games; Alice's Hard puzzle gives a crown */
    public function test_rewards_look_and_crown()
    {
        $carol = $this->sessionCookie(self::CAROL);
        [, , , $s] = $this->api('solve', ['level' => 'easy', 'seed' => 7, 'grid' => $this->answer('easy', 7)], '', $carol);
        $this->assertSame('easy', $s['unlocked']);
        $this->assertArrayNotHasKey('board', $s, 'practice puzzles have no list');
        [, , , $me] = $this->api('me', null, '', $carol);
        $this->assertSame(['easy' => true, 'medium' => false, 'hard' => false], $me['unlocked']);
        [$status, , , $ok] = $this->api('look', ['cellFill' => 'teal', 'cellMark' => 'gold', 'board' => 'night'], '', $carol);
        $this->assertSame([200, 'teal', 'night'], [$status, $ok['look']['cellFill'], $ok['look']['board']]);
        [$status, , , $e] = $this->api('look', ['cellFill' => 'teal', 'cellMark' => 'gold', 'cells3d' => true], '', $carol);
        $this->assertSame([403, 'locked'], [$status, $e['error']]);
        [$status, , , $e] = $this->api('look', ['cellFill' => 'teal', 'cellMark' => 'teal'], '', $carol);
        $this->assertSame([400, 'same_colors'], [$status, $e['error']]);

        [, , , $c] = $this->api('crowns');
        $this->assertSame([self::ALICE], $c['ids']);
        [, , , $all] = $this->http('GET', 'crowns.php');
        $this->assertContains('nonogram', $all['games'][self::ALICE]);
        [, , , $pop] = $this->http('GET', 'games-popular.php');
        $this->assertArrayHasKey('nonogram', $pop['counts']);
    }
}
