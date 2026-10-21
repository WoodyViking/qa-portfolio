<?php
/**
 * System tests of problem reports (src/report.php) over HTTP: the HAKOO bot posts them in
 * #reports-from-hakoo-eu (the Discord stub in wgmock records what it gets).
 * Each test reports as a member of its own, as the limit per member is kept between runs.
 * Traceability: FR-REPORT-01..03, US-39.
 */
class ReportApiTest extends HakooTestCase
{
    const MOCK = 'http://wgmock:8000/discord';

    protected function setUp(): void
    {
        parent::setUp();
        file_get_contents(self::MOCK . '/_reset', false, stream_context_create(['http' => ['method' => 'POST']]));
    }

    private function posts(): array
    {
        $calls = json_decode(file_get_contents(self::MOCK . '/_log'), true) ?: [];
        return array_values(array_filter($calls, fn($c) => $c['method'] === 'POST' && str_ends_with($c['path'], '/messages')));
    }

    private function member(): string
    {
        return (string)(300000000000000000 + random_int(1, 99999999999));
    }

    /** TC-API-250 | FR-REPORT-02: a member's report is posted by the bot in #reports-from-hakoo-eu, with their name, pinging nobody */
    public function test_report_is_posted()
    {
        $id = $this->member();
        [$status, $headers, , $j] = $this->http('POST', 'report.php', [
            'kind' => 'bug', 'what' => "The dice did not roll\nafter the computer's turn", 'expected' => 'The cup shakes',
            'steps' => 'Backgammon, Hard', 'page' => 'http://localhost:8080/games/backgammon.html',
            'device' => 'Chrome/130 · Android 14 · 412×915 (touch) · en', 'errors' => ['TypeError: x is null (dice.js:12)', '', 42],
        ], $this->sessionCookie($id, 'Dice Tester'));
        $this->assertSame([200, ['status' => 'ok', 'sent' => true]], [$status, $j]);
        $this->assertEmpty(array_filter($headers, fn($h) => stripos($h, 'Set-Cookie:') === 0), 'no cookie');
        $posts = $this->posts();
        $this->assertCount(1, $posts);
        $this->assertSame('/channels/900000000000000003/messages', $posts[0]['path'], 'the channel named reports-from-hakoo-eu');
        $m = $posts[0]['body'];
        $this->assertSame(["Report from <@$id>", ['parse' => []]], [$m['content'], $m['allowed_mentions']]);
        $e = $m['embeds'][0];
        $this->assertSame(['🐞 Something does not work', "The dice did not roll\nafter the computer's turn", 'Dice Tester · from hakoo.eu'],
                          [$e['title'], $e['description'], $e['footer']['text']]);
        $this->assertSame(['What they expected', 'What they did just before', 'Page', 'Browser and screen', 'Errors on the page'],
                          array_column($e['fields'], 'name'));
        $this->assertSame("```\nTypeError: x is null (dice.js:12)\n```", $e['fields'][4]['value'], 'empty and non-text errors are left out');
    }

    /** TC-API-251 | FR-REPORT-01: only a logged-in member can report, with a kind and a few words; nothing else is posted */
    public function test_report_needs_a_member_and_text()
    {
        [$status, , , $e] = $this->http('POST', 'report.php', ['kind' => 'bug', 'what' => 'Something broke']);
        $this->assertSame([401, 'not_logged_in'], [$status, $e['error']]);
        $cookie = $this->sessionCookie($this->member(), 'Tester');
        foreach ([['kind' => 'bug', 'what' => 'ok'], ['kind' => 'rant', 'what' => 'Something broke'], ['what' => 'Something broke'], ['kind' => 'bug', 'what' => ['x']]] as $bad) {
            [$status, , , $e] = $this->http('POST', 'report.php', $bad, $cookie);
            $this->assertSame([400, 'bad_request'], [$status, $e['error']], json_encode($bad));
        }
        [$status] = $this->http('GET', 'report.php', null, $cookie);
        $this->assertSame(405, $status);
        $this->assertSame([], $this->posts());
    }

    /** TC-API-252 | FR-REPORT-03: at most five reports an hour per member; another member can still report */
    public function test_report_limit()
    {
        $cookie = $this->sessionCookie($this->member(), 'Busy');
        for ($i = 1; $i <= 5; $i++) {
            [$status] = $this->http('POST', 'report.php', ['kind' => 'idea', 'what' => "Idea number $i"], $cookie);
            $this->assertSame(200, $status);
        }
        [$status, , , $e] = $this->http('POST', 'report.php', ['kind' => 'idea', 'what' => 'Idea number 6'], $cookie);
        $this->assertSame([429, 'too_many'], [$status, $e['error']]);
        [$status] = $this->http('POST', 'report.php', ['kind' => 'other', 'what' => 'Someone else'], $this->sessionCookie($this->member(), 'Other'));
        $this->assertSame(200, $status);
        $this->assertCount(6, $this->posts());
    }
}
