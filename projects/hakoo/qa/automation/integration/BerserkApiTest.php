<?php
/**
 * System tests of Path of Berserk's server (src/berserk.php, berserk.inc.php): runs for guests and members, the score
 * the server counts, the reports it refuses (equivalence partitions and boundary values: qa/test-design/
 * equivalence-partitioning-bva.md §13), the month's and year's lists with names only for members, the first three
 * members of a month and a year that are over, and the member's data.
 * Traceability: FR-BERSERK-08, FR-BERSERK-09, FR-BERSERK-10, FR-BERSERK-14, FR-BERSERK-15, FR-GAMES-20.
 */
class BerserkApiTest extends HakooTestCase
{
    /** The whole level beaten: every enemy and barrel, the Jarl too. */
    const ALL = ['thug' => 14, 'axeman' => 6, 'shieldman' => 3, 'jarl' => 1];

    private function api(string $action, ?array $body = null, ?string $cookie = null, ?string $token = null, string $query = ''): array
    {
        $headers = $token ? ['X-Game-Token: ' . $token] : [];
        return $this->http($body === null ? 'GET' : 'POST', "berserk.php?action=$action$query", $body, $cookie, $headers);
    }

    /** A new run that began $ago seconds ago (the server's clock times it). */
    private function newRun(?string $cookie = null, int $ago = 120): array
    {
        [$status, , , $j] = $this->api('new', [], $cookie);
        $this->assertSame(200, $status, json_encode($j));
        $this->pdo->prepare('UPDATE hakoo_berserk_runs SET created_at = NOW() - INTERVAL ? SECOND WHERE id = ?')->execute([$ago, $j['run']]);
        return $j;
    }

    private function finish(array $run, array $report): array
    {
        return $this->api('finish', $report + ['run' => $run['run']], null, $run['token']);
    }

    private function won(int $health = 80, int $lives = 2): array
    {
        return ['won' => true, 'parts' => 4, 'kills' => self::ALL, 'breaks' => 6, 'health' => $health, 'lives' => $lives];
    }

    /** A finished run with a score, in a month (this one unless given). */
    private function scored(?string $member, int $score, ?string $month = null, string $name = ''): void
    {
        $month ??= (new DateTime('now', new DateTimeZone('Europe/Stockholm')))->format('Y-m');
        $this->pdo->prepare("INSERT INTO hakoo_berserk_runs (id, token, discord_id, name, month, year, ended_at, seconds, score)
                             VALUES (?, ?, ?, ?, ?, ?, NOW(), 200, ?)")
            ->execute([bin2hex(random_bytes(8)), str_repeat('0', 64), $member, $name, $month, (int)substr($month, 0, 4), $score]);
    }

    /** TC-API-304 | FR-BERSERK-08, FR-BERSERK-09: a run is started and finished once, with the token; the server counts the score */
    public function test_a_run_and_its_score()
    {
        $run = $this->newRun(null, 150);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{16}$/', $run['run']);
        $this->assertSame(['raid', true], [$run['level'], is_int($run['seed'])]);
        $this->assertSame(1, $this->rows('hakoo_berserk_runs', 'id = ? AND discord_id IS NULL AND token <> ?', [$run['run'], $run['token']]), 'only the hash is kept');

        [$status, , , $e] = $this->api('finish', $this->won() + ['run' => $run['run']], null, 'not-the-token');
        $this->assertSame([403, 'bad_token'], [$status, $e['error']]);
        [$status, , , $j] = $this->finish($run, $this->won(80, 2));
        $this->assertSame(200, $status, json_encode($j));
        // 14·100 + 6·200 + 3·300 + 3000 + 6·50 = 6800; won: 1000 + (300 − seconds)·10 + 80·10 + 2·1000
        $this->assertGreaterThanOrEqual(150, $j['seconds']);
        $this->assertSame(6800 + 1000 + max(0, 300 - $j['seconds']) * 10 + 800 + 2000, $j['score']);
        $this->assertSame(1, $j['places']['month']);
        [$status, , , $e] = $this->finish($run, $this->won());
        $this->assertSame([409, 'finished'], [$status, $e['error']], 'once only');

        // Lost in the forest: only what was beaten counts; health and lives give nothing
        $run = $this->newRun();
        [, , , $j] = $this->finish($run, ['won' => false, 'parts' => 3, 'kills' => ['thug' => 9, 'axeman' => 4], 'breaks' => 3, 'health' => 50, 'lives' => 2]);
        $this->assertSame(900 + 800 + 150, $j['score']);
        $this->assertSame(1, $this->rows('hakoo_berserk_runs', "id = ? AND health = 0 AND lives = 0 AND won = 0 AND kills = ?",
            [$run['run'], json_encode(['thug' => 9, 'axeman' => 4, 'shieldman' => 0, 'jarl' => 0])]));

        // A member's run: their totals are kept
        $cookie = $this->sessionCookie(self::CAROL, 'Carol');
        $run = $this->newRun($cookie);
        $this->finish($run, $this->won(100, 3));
        $this->assertSame(1, $this->rows('hakoo_berserk_profile', 'discord_id = ? AND runs = 1 AND wins = 1 AND flawless = 1 AND kills = 24', [self::CAROL]));
        $this->assertSame(1, $this->rows('hakoo_berserk_runs', "id = ? AND discord_id = ? AND name = 'Carol'", [$run['run'], self::CAROL]));

        [$status] = $this->api('finish');
        $this->assertSame(405, $status, 'GET is refused');
        [$status, , , $e] = $this->api('finish', ['run' => "x' OR '1'='1"] + $this->won(), null, 'abc');
        $this->assertSame([404, 'not_found'], [$status, $e['error']]);
    }

    /** TC-API-305 | FR-BERSERK-09: reports the level cannot have are refused, and nothing is counted for them */
    public function test_impossible_reports()
    {
        $bad = [
            'one thug too many'        => ['kills' => ['thug' => 15] + self::ALL],
            'a negative count'         => ['kills' => ['axeman' => -1] + self::ALL],
            'a count as text'          => ['kills' => ['thug' => '14'] + self::ALL],
            'an enemy the level lacks' => ['kills' => self::ALL + ['dragon' => 1]],
            'a won level without the Jarl' => ['kills' => ['jarl' => 0] + self::ALL],
            'health 0 at a win'        => ['health' => 0],
            'health 101'               => ['health' => 101],
            'four lives'               => ['lives' => 4],
            'no lives at a win'        => ['lives' => 0],
            'a fifth part'             => ['parts' => 5],
            'a won level in part 3'    => ['parts' => 3],
            'seven barrels'            => ['breaks' => 7],
            'won as text'              => ['won' => 'yes'],
        ];
        foreach ($bad as $what => $change) {
            $run = $this->newRun();
            [$status, , , $e] = $this->finish($run, $change + $this->won());
            $this->assertSame([400, 'bad_report'], [$status, $e['error'] ?? null], $what);
            $this->assertSame(1, $this->rows('hakoo_berserk_runs', 'id = ? AND ended_at IS NULL AND score IS NULL', [$run['run']]), "$what: not counted");
        }
        // The boundaries that are allowed: health 1 and 100, lives 1 and 3, all six barrels
        foreach ([[1, 1], [100, 3]] as [$h, $l]) {
            [$status] = $this->finish($this->newRun(), $this->won($h, $l));
            $this->assertSame(200, $status, "health $h, lives $l");
        }
        // Time: a win in 59 seconds is refused, in 60 it counts; a run older than 6 hours is over
        [$status, , , $e] = $this->finish($this->newRun(null, 59), $this->won());
        $this->assertSame([409, 'too_fast'], [$status, $e['error']]);
        [$status, , , $j] = $this->finish($this->newRun(null, 60), $this->won(100, 3));
        $this->assertSame([200, 6800 + 1000 + 2400 + 1000 + 3000], [$status, $j['score']]);
        [$status, , , $e] = $this->finish($this->newRun(null, 6 * 3600 + 5), $this->won());
        $this->assertSame([409, 'expired'], [$status, $e['error']]);
        // A lost run may be short: losing fast is no cheat
        [$status] = $this->finish($this->newRun(null, 5), ['won' => false, 'parts' => 1, 'kills' => ['thug' => 1], 'breaks' => 0]);
        $this->assertSame(200, $status);
    }

    /** TC-API-306 | FR-BERSERK-10: the month's and the year's lists: a member's best once, guests as Anonymous, names only when logged in; the first three members of a month and a year that are over */
    public function test_the_lists_and_the_first_three()
    {
        $this->pdo->exec('DELETE FROM hakoo_berserk_runs');
        $this->pdo->exec('DELETE FROM hakoo_berserk_podium');
        $this->scored(self::ALICE, 9000, null, 'Alice');
        $this->scored(self::ALICE, 12000, null, 'Alice');
        $this->scored(null, 10000);
        $this->scored(self::BOB, 8000, null, 'Bob');
        [, , , $j] = $this->api('top');
        $this->assertSame([[12000, true, null], [10000, false, null], [8000, true, null]],
            array_map(fn($e) => [$e['score'], $e['member'], $e['name']], $j['top']), 'logged out: no names');
        [, , , $j] = $this->api('top', null, $this->sessionCookie(self::BOB));
        $this->assertSame(['Alice', null, 'Bob'], array_column($j['top'], 'name'), 'a guest stays anonymous');
        $this->assertSame([3, true], [$j['you']['place'], $j['you']['you']]);
        [, , , $j] = $this->api('top', null, null, null, '&period=year');
        $this->assertSame(['year', 3], [$j['period'], count($j['top'])]);
        [, , , $j] = $this->api('top', null, null, null, '&period=' . rawurlencode("year' OR 1=1"));
        $this->assertSame('month', $j['period'], 'anything else is the month');

        // Last month: a guest at the top does not take a place; the members are 1-3, the fourth member none
        $last = (new DateTime('first day of last month', new DateTimeZone('Europe/Stockholm')))->format('Y-m');
        $this->scored(null, 20000, $last);
        foreach ([[self::ALICE, 15000], [self::BOB, 14000], [self::CAROL, 13000], ['100000000000000004', 12000]] as [$m, $s]) {
            $this->pdo->prepare('INSERT IGNORE INTO hakoo_users (discord_id) VALUES (?)')->execute([$m]);
            $this->scored($m, $s, $last, 'M');
        }
        [, , , $j] = $this->api('top', null, $this->sessionCookie(self::CAROL));
        $this->assertSame($last, $j['lastLabel']);
        $this->assertSame([[1, 15000, false], [2, 14000, false], [3, 13000, true]], array_map(fn($e) => [$e['place'], $e['score'], $e['you']], $j['last']));
        $this->assertSame(1, $this->rows('hakoo_berserk_podium', 'period = ? AND place = 3 AND discord_id = ?', [$last, self::CAROL]));
        $this->assertSame(0, $this->rows('hakoo_berserk_podium', 'discord_id = ?', ['100000000000000004']));
        // A year that is over keeps its three as well
        $year = (string)((int)date('Y') - 1);
        $this->scored(self::BOB, 5000, "$year-06");
        $this->api('top');
        $this->assertSame(1, $this->rows('hakoo_berserk_podium', 'period = ? AND place = 1 AND discord_id = ?', [$year, self::BOB]));
        $this->assertSame(1, $this->rows('hakoo_berserk_podium', 'period = ? AND place = 1', ["$year-06"]));
    }

    /** TC-API-307 | FR-BERSERK-14: a member's runs, places and totals are in "Download my data" and go with "Delete all my data" */
    public function test_export_and_delete()
    {
        $cookie = $this->sessionCookie(self::CAROL, 'Carol');
        $this->finish($this->newRun($cookie), $this->won());
        $last = (new DateTime('first day of last month', new DateTimeZone('Europe/Stockholm')))->format('Y-m');
        $this->pdo->prepare('INSERT INTO hakoo_berserk_podium (period, place, discord_id, score) VALUES (?, 1, ?, 9999)')->execute([$last, self::CAROL]);
        $export = hakoo_collect_user($this->pdo, self::CAROL);
        $this->assertSame('Carol', $export['tables']['hakoo_berserk_runs'][0]['name']);
        $this->assertArrayNotHasKey('token', $export['tables']['hakoo_berserk_runs'][0], 'never the token hash');
        $this->assertSame(1, (int)$export['tables']['hakoo_berserk_profile'][0]['wins']);
        $this->assertSame($last, $export['tables']['hakoo_berserk_podium'][0]['period']);
        $this->http('POST', 'mydata.php?action=delete', ['confirm' => 'DELETE'], $cookie);
        foreach (['hakoo_berserk_runs', 'hakoo_berserk_podium', 'hakoo_berserk_profile'] as $t) {
            $this->assertSame(0, $this->rows($t, 'discord_id = ?', [self::CAROL]), $t);
        }
    }

    const KEYS = ['left' => ['ArrowLeft', 'KeyA'], 'right' => ['ArrowRight', 'KeyD'], 'up' => ['ArrowUp', 'KeyW'], 'down' => ['ArrowDown', 'KeyS'],
                  'attack' => ['KeyF', null], 'jump' => ['KeyK', 'Space'], 'berserk' => ['KeyL', 'KeyC'], 'pause' => ['KeyP', 'Escape']];

    /** TC-API-308 | FR-BERSERK-15, FR-GAMES-20: a member's keys are kept with the account (the same on every device); unsound keys are refused */
    public function test_a_members_keys_follow_them()
    {
        [, , , $j] = $this->api('controls');
        $this->assertSame([false, null], [$j['loggedIn'], $j['controls']], 'a guest: the page keeps them');
        [$status, , , $e] = $this->api('controls', ['controls' => self::KEYS]);
        $this->assertSame([401, 'not_logged_in'], [$status, $e['error']]);

        $carol = $this->sessionCookie(self::CAROL, 'Carol');
        [, , , $j] = $this->api('controls', null, $carol);
        $this->assertSame([true, true, null], [$j['loggedIn'], $j['kept'], $j['controls']], 'none yet: the default keys');
        [$status, , , $j] = $this->api('controls', ['controls' => self::KEYS], $carol);
        $this->assertSame([200, self::KEYS], [$status, $j['controls']]);
        // Another device: a new session for the same member gets the same keys
        [, , , $j] = $this->api('controls', null, $this->sessionCookie(self::CAROL, 'Carol'));
        $this->assertSame(self::KEYS, $j['controls']);
        [, , , $j] = $this->api('controls', null, $this->sessionCookie(self::BOB));
        $this->assertNull($j['controls'], 'not the keys of another member');

        $bad = [
            'a key twice'          => ['attack' => ['KeyF', 'KeyA']] + self::KEYS,
            'Ctrl'                 => ['attack' => ['ControlLeft', null]] + self::KEYS,
            'Tab'                  => ['attack' => ['Tab', null]] + self::KEYS,
            'an action without'    => ['attack' => [null, null]] + self::KEYS,
            'an action missing'    => array_diff_key(self::KEYS, ['pause' => 1]),
            'an action too many'   => self::KEYS + ['fly' => ['KeyQ', null]],
            'three keys'           => ['attack' => ['KeyF', 'KeyG', 'KeyH']] + self::KEYS,
            'a key as a number'    => ['attack' => [5, null]] + self::KEYS,
            'markup in a key'      => ['attack' => ['<b>', null]] + self::KEYS,
            'not a list'           => 'KeyF',
        ];
        foreach ($bad as $what => $k) {
            [$status, , , $e] = $this->api('controls', ['controls' => $k], $carol);
            $this->assertSame([400, 'bad_controls'], [$status, $e['error'] ?? null], $what);
        }
        [, , , $j] = $this->api('controls', null, $carol);
        $this->assertSame(self::KEYS, $j['controls'], 'the refused ones changed nothing');

        $export = hakoo_collect_user($this->pdo, self::CAROL);
        $this->assertSame(self::KEYS, json_decode($export['tables']['hakoo_berserk_profile'][0]['controls'], true));
        [, , , $j] = $this->api('controls', ['controls' => null], $carol);
        $this->assertNull($j['controls']);
        $this->assertSame(1, $this->rows('hakoo_berserk_profile', 'discord_id = ? AND controls IS NULL', [self::CAROL]), 'Default keys: nothing kept');
        $this->api('controls', ['controls' => self::KEYS], $carol);
        $this->http('POST', 'mydata.php?action=delete', ['confirm' => 'DELETE'], $carol);
        $this->assertSame(0, $this->rows('hakoo_berserk_profile', 'discord_id = ?', [self::CAROL]));
    }
}
