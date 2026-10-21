<?php
/**
 * System tests of the Owldoku API (src/owldoku.php) over HTTP.
 * Traceability: FR-OWLDOKU-03..07, FR-PUZZLE-01, FR-GAMES-07, US-38.
 */
require_once '/var/www/html/owldoku-engine.inc.php';

class OwldokuApiTest extends HakooTestCase
{
    private function api(string $action, ?array $body = null, string $query = '', ?string $cookie = null): array
    {
        return $this->http($body === null ? 'GET' : 'POST', "owldoku.php?action=$action$query", $body, $cookie);
    }

    private function today(): string
    {
        return (new DateTimeImmutable('now', new DateTimeZone('Europe/Stockholm')))->format('Y-m-d');
    }

    /** The owls of a puzzle as the page sends them, and a square that is not an owl. */
    private function owls(array $p): string
    {
        $n = $p['size'];
        $g = str_repeat('0', $n * $n);
        foreach ($p['owls'] as $r => $c) $g[$r * $n + $c] = '1';
        return $g;
    }
    private function miss(array $p, int $r): int
    {
        return ($p['owls'][$r] + 3) % $p['size'];
    }

    /** TC-API-235 | FR-OWLDOKU-03, FR-OWLDOKU-04: a practice puzzle has the regions but not the owls; every owl placed is checked; nothing is stored */
    public function test_practice_place_and_solve()
    {
        $p = Owldoku::generate('easy', 3);
        [$status, $headers, , $j] = $this->api('puzzle', null, '&level=easy&seed=3');
        $this->assertSame(200, $status);
        $this->assertSame(['level' => 'easy', 'seed' => 3, 'size' => 6, 'regions' => $p['regions'], 'hearts' => 3, 'day' => null], $j['puzzle']);
        $this->assertEmpty(array_filter($headers, fn($h) => stripos($h, 'Set-Cookie:') === 0));
        [, , , $yes] = $this->api('place', ['level' => 'easy', 'seed' => 3, 'r' => 0, 'c' => $p['owls'][0]]);
        [, , , $no] = $this->api('place', ['level' => 'easy', 'seed' => 3, 'r' => 0, 'c' => $this->miss($p, 0)]);
        $this->assertSame([['status' => 'ok', 'owl' => true], ['status' => 'ok', 'owl' => false]], [$yes, $no], 'no hearts counted anonymously');
        foreach ([['level' => 'easy', 'seed' => 3, 'r' => 6, 'c' => 0], ['level' => 'easy', 'seed' => 3, 'r' => '0', 'c' => 1], ['level' => 'easy', 'seed' => 3]] as $bad) {
            [$status, , , $e] = $this->api('place', $bad);
            $this->assertSame([400, 'bad_request'], [$status, $e['error']], json_encode($bad));
        }
        [$status] = $this->api('place', null, '&level=easy');
        $this->assertSame(405, $status);
        [, , , $s] = $this->api('solve', ['level' => 'easy', 'seed' => 3, 'grid' => $this->owls($p)]);
        $this->assertSame([true, null], [$s['solved'], $s['unlocked']]);
        [, , , $s] = $this->api('solve', ['level' => 'easy', 'seed' => 3, 'grid' => str_repeat('0', 36)]);
        $this->assertSame(['status' => 'ok', 'solved' => false], $s);
        [$status, , , $e] = $this->api('place', ['level' => 'easy', 'seed' => 3, 'r' => 0, 'c' => 0, 'day' => '2026-01-01']);
        $this->assertSame([409, 'day_over'], [$status, $e['error']]);
    }

    /** TC-API-236 | FR-OWLDOKU-05, FR-PUZZLE-01: guests get another puzzle of the day and cannot ask about the members'; a member's: the clock, the list, the streak, the first reward */
    public function test_daily_member()
    {
        $day = $this->today();
        $seed = Owldoku::dailySeed('medium', $day);
        $p = Owldoku::generate('medium', $seed);
        [, , , $anon] = $this->api('daily', ['level' => 'medium']);
        $this->assertSame([$day, Owldoku::dailySeed('medium', $day, true), false], [$anon['puzzle']['day'], $anon['puzzle']['seed'], $anon['loggedIn']]);
        [, , , $g] = $this->api('place', ['level' => 'medium', 'seed' => $anon['puzzle']['seed'], 'r' => 0, 'c' => 0, 'day' => $day]);
        $this->assertArrayNotHasKey('hearts', $g, 'the guests\' puzzle counts no hearts');
        foreach (['place' => ['r' => 0, 'c' => $p['owls'][0]], 'solve' => ['grid' => $this->owls($p)]] as $action => $more) {
            [$status, , , $e] = $this->api($action, ['level' => 'medium', 'seed' => $seed, 'day' => $day] + $more);
            $this->assertSame([401, 'login_needed'], [$status, $e['error']], "$action: the members' puzzle answers members only");
        }
        $this->assertSame(0, $this->rows('hakoo_owldoku_daily', 'day = ?', [$day]), 'nothing stored anonymously');

        $carol = $this->sessionCookie(self::CAROL, 'Carol');
        [, , , $o] = $this->api('daily', ['level' => 'medium'], '', $carol);
        $this->assertSame([true, null, 0, 0, []], [$o['loggedIn'], $o['you']['seconds'], $o['you']['mistakes'], $o['streak'], $o['board']]);
        $this->pdo->prepare('UPDATE hakoo_owldoku_daily SET created_at = NOW() - INTERVAL 40 SECOND WHERE discord_id = ?')->execute([self::CAROL]);
        [, , , $w] = $this->api('place', ['level' => 'medium', 'seed' => $seed, 'r' => 1, 'c' => $this->miss($p, 1), 'day' => $day], '', $carol);
        $this->assertSame([false, 2], [$w['owl'], $w['hearts']], 'a wrong owl costs a heart');
        [, , , $s] = $this->api('solve', ['level' => 'medium', 'seed' => $seed, 'grid' => $this->owls($p), 'day' => $day], '', $carol);
        $this->assertSame(['medium', false, 1], [$s['unlocked'], $s['lost'], $s['streak']]);
        $this->assertGreaterThanOrEqual(40, $s['seconds']);
        $this->assertSame([['name' => 'Carol', 'title' => '', 'seconds' => $s['seconds'], 'you' => true]], $s['board']);
        [, , , $w] = $this->api('place', ['level' => 'medium', 'seed' => $seed, 'r' => 2, 'c' => $this->miss($p, 2), 'day' => $day], '', $carol);
        $this->assertSame(2, $w['hearts'], 'solved: no more hearts lost');
        [, , , $t] = $this->api('today', null, '', $carol);
        $this->assertSame([$s['seconds'], 1, 1, 1], [$t['levels']['medium']['you']['seconds'], $t['levels']['medium']['you']['mistakes'],
                                                  $t['levels']['medium']['solvers'], $t['streak']]);
    }

    /** TC-API-237 | FR-OWLDOKU-05: three wrong owls: no hearts left, no more answers that day; the day's time and the reward do not count */
    public function test_out_of_hearts()
    {
        $day = $this->today();
        $seed = Owldoku::dailySeed('hard', $day);
        $p = Owldoku::generate('hard', $seed);
        $carol = $this->sessionCookie(self::CAROL, 'Carol');
        $this->api('daily', ['level' => 'hard'], '', $carol);
        $hearts = [];
        foreach ([0, 1, 2] as $r) {
            [, , , $w] = $this->api('place', ['level' => 'hard', 'seed' => $seed, 'r' => $r, 'c' => $this->miss($p, $r), 'day' => $day], '', $carol);
            $hearts[] = $w['hearts'];
        }
        $this->assertSame([2, 1, 0], $hearts);
        foreach ([$this->miss($p, 3), $p['owls'][3]] as $c) {
            [$status, , , $e] = $this->api('place', ['level' => 'hard', 'seed' => $seed, 'r' => 3, 'c' => $c, 'day' => $day], '', $carol);
            $this->assertSame([409, 'no_hearts'], [$status, $e['error']], 'no more answers, right or wrong');
        }
        [, , , $s] = $this->api('solve', ['level' => 'hard', 'seed' => $seed, 'grid' => $this->owls($p), 'day' => $day], '', $carol);
        $this->assertSame([true, true, null, null, [], 0], [$s['solved'], $s['lost'], $s['unlocked'], $s['seconds'], $s['board'], $s['streak']]);
        [, , , $t] = $this->api('today', null, '', $carol);
        $this->assertSame([null, 3], [$t['levels']['hard']['you']['seconds'], $t['levels']['hard']['you']['mistakes']]);
        [, , , $me] = $this->api('me', null, '', $carol);
        $this->assertFalse($me['unlocked']['hard'], 'no crown without hearts');
    }

    /** TC-API-238 | FR-OWLDOKU-06, FR-GAMES-07: rewards and look like the board games; Alice's Hard puzzle gives a crown */
    public function test_rewards_look_and_crown()
    {
        $carol = $this->sessionCookie(self::CAROL);
        [, , , $s] = $this->api('solve', ['level' => 'easy', 'seed' => 3, 'grid' => $this->owls(Owldoku::generate('easy', 3))], '', $carol);
        $this->assertSame('easy', $s['unlocked']);
        $this->assertArrayNotHasKey('board', $s, 'practice puzzles have no list');
        [$status, , , $ok] = $this->api('look', ['owl' => 'gold', 'mark' => 'gold', 'board' => 'night'], '', $carol);
        $this->assertSame([200, 'gold', 'night'], [$status, $ok['look']['owl'], $ok['look']['board']]);
        [$status, , , $e] = $this->api('look', ['owl' => 'gold', 'regions3d' => true], '', $carol);
        $this->assertSame([403, 'locked'], [$status, $e['error']]);
        [$status, , , $e] = $this->api('look', ['owl' => 'rainbow'], '', $carol);
        $this->assertSame([400, 'bad_look'], [$status, $e['error']]);
        [, , , $c] = $this->api('crowns');
        $this->assertSame([self::ALICE], $c['ids']);
        [, , , $pop] = $this->http('GET', 'games-popular.php');
        $this->assertArrayHasKey('owldoku', $pop['counts']);
    }
}
