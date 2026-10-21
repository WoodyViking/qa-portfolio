<?php
/**
 * System tests of the puzzle reminders (reminders.php: me, save, test, tick; the bot's messages through
 * src/hakoo-discord.inc.php to the Discord stub in wgmock; hakoo-users.inc.php: export and delete).
 * Traceability: FR-REMIND-01..06, FR-REMIND-08.
 */
class RemindersApiTest extends HakooTestCase
{
    const MOCK = 'http://wgmock:8000/discord';
    const CHANNEL = '900000000000000004';                 // #reminders-spams in the stub
    const NO_DM = '100000000000000403';                   // the stub: takes no direct messages

    protected function setUp(): void
    {
        parent::setUp();
        file_get_contents(self::MOCK . '/_reset', false, stream_context_create(['http' => ['method' => 'POST']]));
    }

    /** The messages the website posted to Discord since setUp: [channel id, content, allowed users]. */
    private function sent(): array
    {
        $log = json_decode(file_get_contents(self::MOCK . '/_log'), true) ?: [];
        $msgs = array_filter($log, fn($c) => $c['method'] === 'POST' && preg_match('~^/channels/\d+/messages$~', $c['path']));
        return array_values(array_map(fn($c) => [explode('/', $c['path'])[2], $c['body']['content'], $c['body']['allowed_mentions']['users'] ?? null], $msgs));
    }

    private function api(string $action, ?array $body = null, ?string $cookie = null, array $headers = []): array
    {
        return $this->http($action === 'me' ? 'GET' : 'POST', "reminders.php?action=$action", $action === 'me' ? null : ($body ?? []), $cookie, $headers);
    }

    private function tick(string $now): array
    {
        [$status, , , $j] = $this->api('tick', ['now' => $now], null, ['<BOT_AUTH_HEADER>: ' . <BOT_SECRET>]);
        $this->assertSame(200, $status);
        return $j;
    }

    private function save(string $id, array $body): array
    {
        return $this->api('save', $body + ['tz' => 'Europe/Stockholm', 'via' => 'channel', 'consent' => false], $this->sessionCookie($id));
    }

    /** Today in Sweden (the puzzles' day). */
    private function today(): string
    {
        return (new DateTime('now', new DateTimeZone('Europe/Stockholm')))->format('Y-m-d');
    }

    /** TC-API-288 | FR-REMIND-01: a member saves one reminder for all puzzles or one per puzzle; wrong choices are refused */
    public function test_save_and_read_the_reminders()
    {
        [$status, , , $j] = $this->api('me');
        $this->assertSame([200, false], [$status, $j['loggedIn']]);
        [, , , $j] = $this->api('me', null, $this->sessionCookie(self::CAROL));
        $this->assertSame(['channel', [], 'reminders-spams', false], [$j['via'], $j['reminders'], $j['channel'], $j['consent']]);

        [$status, , , $j] = $this->save(self::CAROL, ['reminders' => [['game' => 'sudoku', 'time' => '07:30'], ['game' => 'nonogram', 'time' => '20:00']]]);
        $this->assertSame(200, $status);
        $this->assertSame([['game' => 'nonogram', 'time' => '20:00'], ['game' => 'sudoku', 'time' => '07:30']], $j['reminders']);
        [, , , $j] = $this->save(self::CAROL, ['reminders' => [['game' => 'all', 'time' => '18:00']], 'tz' => 'America/New_York']);
        $this->assertSame([[['game' => 'all', 'time' => '18:00']], 'America/New_York'], [$j['reminders'], $j['tz']]);

        foreach ([
            [['reminders' => [['game' => 'all', 'time' => '18:00'], ['game' => 'sudoku', 'time' => '09:00']]], 'bad_reminders'],
            [['reminders' => [['game' => 'chess', 'time' => '18:00']]], 'bad_reminders'],
            [['reminders' => [['game' => 'sudoku', 'time' => '25:00']]], 'bad_time'],
            [['reminders' => [], 'tz' => 'Mars/Olympus'], 'bad_tz'],
            [['reminders' => [], 'via' => 'pigeon'], 'bad_via'],
        ] as [$body, $error]) {
            [$status, , , $e] = $this->save(self::CAROL, $body);
            $this->assertSame([400, $error], [$status, $e['error']]);
        }
        [$status] = $this->api('save', ['via' => 'channel', 'tz' => 'Europe/Stockholm', 'reminders' => []]);
        $this->assertSame(401, $status);
        [, , , $j] = $this->save(self::CAROL, ['reminders' => []]);
        $this->assertSame([], $j['reminders'], 'an empty list turns them off');
    }

    /** TC-API-289 | FR-REMIND-02: a direct message needs the member's consent; choosing the ping takes it back */
    public function test_direct_messages_need_consent()
    {
        [$status, , , $e] = $this->save(self::CAROL, ['via' => 'dm', 'reminders' => [['game' => 'all', 'time' => '18:00']]]);
        $this->assertSame([400, 'consent_needed'], [$status, $e['error']]);
        [, , , $j] = $this->save(self::CAROL, ['via' => 'dm', 'consent' => true, 'reminders' => [['game' => 'all', 'time' => '18:00']]]);
        $this->assertSame(['dm', true], [$j['via'], $j['consent']]);
        $this->assertSame(1, $this->rows('hakoo_reminder_settings', 'discord_id = ? AND dm_consent_at IS NOT NULL', [self::CAROL]));
        [, , , $j] = $this->save(self::CAROL, ['via' => 'channel', 'reminders' => [['game' => 'all', 'time' => '18:00']]]);
        $this->assertFalse($j['consent']);
        $this->assertSame(1, $this->rows('hakoo_reminder_settings', 'discord_id = ? AND dm_consent_at IS NULL', [self::CAROL]));
    }

    /** TC-API-290 | FR-REMIND-03: the bot's tick sends a due reminder once a day, in the member's time zone, only for unsolved puzzles */
    public function test_tick_sends_due_reminders_once()
    {
        $day = $this->today();
        $this->save(self::CAROL, ['reminders' => [['game' => 'sudoku', 'time' => '18:00'], ['game' => 'owldoku', 'time' => '18:00']]]);
        $this->save(self::BOB, ['reminders' => [['game' => 'all', 'time' => '14:00']], 'tz' => 'America/New_York']);   // 19:00 or 20:00 in Sweden
        $this->pdo->exec('UPDATE hakoo_reminders SET last_day = NULL');      // saved after their time on the test machine: start today
        // Carol solved today's Owldoku already: only Sudoku is sent
        $this->pdo->prepare("INSERT INTO hakoo_owldoku_daily (discord_id, day, level, name, seconds) VALUES (?, ?, 'easy', 'Carol', 99)")
            ->execute([self::CAROL, $day]);

        $this->assertSame(0, $this->tick("$day 17:59:00 Europe/Stockholm")['sent'], 'not yet');
        $j = $this->tick("$day 18:00:30 Europe/Stockholm");
        $this->assertSame([1, 1], [$j['sent'], $j['skipped']], 'Sudoku sent; Owldoku solved, so not');
        $m = $this->sent();
        $this->assertCount(1, $m);
        $this->assertSame([self::CHANNEL, [self::CAROL]], [$m[0][0], $m[0][2]]);
        $this->assertStringStartsWith('<@' . self::CAROL . '> ⏰', $m[0][1]);
        $this->assertStringContainsString('**Sudoku**', $m[0][1]);
        $this->assertStringNotContainsString('Owldoku', $m[0][1]);
        $this->assertSame(0, $this->tick("$day 18:01:00 Europe/Stockholm")['sent'], 'once a day');

        // Bob: 14:00 in New York, later than Carol's 18:00 in Sweden
        $ny = (new DateTime("$day 14:00:00", new DateTimeZone('America/New_York')))->setTimezone(new DateTimeZone('Europe/Stockholm'));
        $this->assertSame(1, $this->tick($ny->format('Y-m-d H:i:s') . ' Europe/Stockholm')['sent']);
        $this->assertStringContainsString('**Nonogram**', $this->sent()[1][1]);
        $this->assertStringContainsString('**Owldoku**', $this->sent()[1][1], 'all three puzzles in one message');

        [$status] = $this->api('tick', [], null, ['<BOT_AUTH_HEADER>: <WRONG_SECRET_VALUE>']);
        $this->assertSame(403, $status);
    }

    /** TC-API-291 | FR-REMIND-04: a reminder the bot could not send on time is sent up to 3 hours late, not later */
    public function test_late_reminders()
    {
        $day = $this->today();
        $this->save(self::CAROL, ['reminders' => [['game' => 'all', 'time' => '08:00']]]);
        $this->pdo->exec('UPDATE hakoo_reminders SET last_day = NULL');
        $this->assertSame(0, $this->tick("$day 11:01:00 Europe/Stockholm")['sent'], 'more than 3 hours late: left out');
        $this->assertSame(1, $this->tick("$day 10:59:00 Europe/Stockholm")['sent'], 'less: sent');
    }

    /** TC-API-291 | FR-REMIND-04: a new reminder whose time has passed today starts tomorrow; one whose time stays keeps its day */
    public function test_a_new_time_that_has_passed_waits_for_tomorrow()
    {
        $now = new DateTime('now', new DateTimeZone('Europe/Stockholm'));
        $past = (clone $now)->modify('-2 minutes')->format('H:i');
        if ($past > $now->format('H:i')) $this->markTestSkipped('just after midnight');
        $this->save(self::CAROL, ['reminders' => [['game' => 'sudoku', 'time' => $past]]]);
        $this->assertSame(1, $this->rows('hakoo_reminders', 'discord_id = ? AND last_day = ?', [self::CAROL, $this->today()]));
        $this->assertSame(0, $this->tick('now')['sent']);
    }

    /** TC-API-292 | FR-REMIND-02, FR-REMIND-05: a direct message goes to the member; one Discord refuses is shown on the profile */
    public function test_direct_message_and_refused_one()
    {
        $this->save(self::CAROL, ['via' => 'dm', 'consent' => true, 'reminders' => [['game' => 'all', 'time' => '18:00']]]);
        [, , , $j] = $this->api('test', [], $this->sessionCookie(self::CAROL));
        $this->assertTrue($j['sent']);
        $m = $this->sent();
        $this->assertSame('900000000000000050', $m[0][0], 'the DM channel');
        $this->assertStringContainsString('Test reminder', $m[0][1]);
        $this->assertStringStartsWith('⏰', $m[0][1], 'no ping in a direct message');
        [$status, , , $e] = $this->api('test', [], $this->sessionCookie(self::CAROL));
        $this->assertSame([429, 'too_soon'], [$status, $e['error']]);

        $this->save(self::NO_DM, ['via' => 'dm', 'consent' => true, 'reminders' => [['game' => 'sudoku', 'time' => '18:00']]]);
        [, , , $j] = $this->api('test', [], $this->sessionCookie(self::NO_DM));
        $this->assertSame([false, 'dm_closed'], [$j['sent'], $j['error']]);
        [, , , $me] = $this->api('me', null, $this->sessionCookie(self::NO_DM));
        $this->assertSame('dm_closed', $me['lastError']['error']);
        [, , , $me] = $this->save(self::NO_DM, ['via' => 'channel', 'reminders' => [['game' => 'sudoku', 'time' => '18:00']]]);
        $this->assertNull($me['lastError'], 'a new choice starts clean');
    }

    /** TC-API-303 | FR-REMIND-08: reminded every day: the done puzzles are named, and a day with all done still gets one */
    public function test_reminded_every_day()
    {
        $day = $this->today();
        [, , , $j] = $this->api('me', null, $this->sessionCookie(self::CAROL));
        $this->assertFalse($j['always'], 'only on a day with a puzzle left, by default');
        [, , , $j] = $this->save(self::CAROL, ['always' => true, 'reminders' => [['game' => 'sudoku', 'time' => '18:00'], ['game' => 'owldoku', 'time' => '19:00']]]);
        $this->assertTrue($j['always']);
        $this->save(self::BOB, ['reminders' => [['game' => 'sudoku', 'time' => '18:00']]]);
        $this->pdo->exec('UPDATE hakoo_reminders SET last_day = NULL');
        $solved = $this->pdo->prepare("INSERT INTO hakoo_sudoku_daily (discord_id, day, level, name, seconds) VALUES (?, ?, 'easy', 'X', 99)");
        $solved->execute([self::CAROL, $day]);
        $solved->execute([self::BOB, $day]);

        $j = $this->tick("$day 18:00:30 Europe/Stockholm");
        $this->assertSame([1, 1], [$j['sent'], $j['skipped']], 'Carol, reminded every day, gets it; Bob does not');
        $m = $this->sent();
        $this->assertSame([self::CAROL], $m[0][2]);
        $this->assertStringContainsString('You have done all the puzzles of the day', $m[0][1]);
        $this->assertStringContainsString('**Sudoku**', $m[0][1]);

        // Carol's "all": Sudoku done, the rest left; the done one is named as done
        $this->save(self::CAROL, ['always' => true, 'reminders' => [['game' => 'all', 'time' => '20:00']]]);
        $this->pdo->exec('UPDATE hakoo_reminders SET last_day = NULL');
        $this->tick("$day 20:00:30 Europe/Stockholm");
        $text = $this->sent()[1][1];
        $this->assertStringContainsString('the puzzles of the day you have not solved yet', $text);
        $this->assertStringContainsString('**Nonogram**', $text);
        $this->assertStringNotContainsString('**Sudoku**', $text);
        $this->assertStringContainsString('Done today ✅ Sudoku', $text);

        [, , , $j] = $this->save(self::CAROL, ['reminders' => [['game' => 'all', 'time' => '20:00']]]);
        $this->assertFalse($j['always'], 'saved without it: back to the default');
    }

    /** TC-API-293 | FR-REMIND-06: reminders are in "Download my data" and go with "Delete all my data" */
    public function test_export_and_delete()
    {
        $this->save(self::CAROL, ['via' => 'dm', 'consent' => true, 'always' => true, 'reminders' => [['game' => 'all', 'time' => '18:00']]]);
        $export = hakoo_collect_user($this->pdo, self::CAROL);
        $this->assertSame('dm', $export['tables']['hakoo_reminder_settings'][0]['via']);
        $this->assertEquals(1, $export['tables']['hakoo_reminder_settings'][0]['always']);
        $this->assertSame('all', $export['tables']['hakoo_reminders'][0]['game']);
        $this->http('POST', 'mydata.php?action=delete', ['confirm' => 'DELETE'], $this->sessionCookie(self::CAROL));
        $this->assertSame(0, $this->rows('hakoo_reminders', 'discord_id = ?', [self::CAROL]));
        $this->assertSame(0, $this->rows('hakoo_reminder_settings', 'discord_id = ?', [self::CAROL]));
    }
}
