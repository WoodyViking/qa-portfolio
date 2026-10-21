<?php
// The core (ADR-001): sessions, devices and their approval, what each device may see, pause.
// Every test starts its own session, so the tests do not depend on each other.
// Traceability: US-01..US-04, US-06, K-01..K-03, K-06..K-09, K-12, K-13, K-15, K-28, K-29, NFR-04, NFR-05, AK-01, AK-03, AK-06, AK-08.
use PHPUnit\Framework\TestCase;

final class SessionApiTest extends TestCase
{
    private const ALICE = [TEST_ALICE, 'Alice'];
    private const BOB   = [TEST_BOB, 'Bob'];
    private const CAROL = [TEST_CAROL, 'Carol'];
    private const ERIK  = [TEST_ERIK, 'Erik'];

    private function create(array $as = self::BOB, array $over = []): array
    {
        [$status, $j] = my_api('POST', 'create', $over + ['category' => 'demo', 'lengthMin' => 60, 'waitMin' => 5,
            'hostPlays' => true, 'boardLang' => 'sv'], $as);
        $this->assertSame(200, $status, json_encode($j));
        return $j;
    }

    /** A session with Bob as host, an approved board and Erik approved: [code, host token, board token, Erik's token]. */
    private function table(): array
    {
        $g = $this->create();
        [, $b] = my_api('POST', 'join', ['code' => $g['code'], 'kind' => 'board', 'name' => 'TV at Mum\'s']);
        [, $e] = my_api('POST', 'join', ['code' => $g['code'], 'kind' => 'player'], self::ERIK);
        my_api('POST', 'approve', ['device' => $b['view']['you']['id'], 'ok' => true], self::BOB, $g['token']);
        my_api('POST', 'approve', ['device' => $e['view']['you']['id'], 'ok' => true], self::BOB, $g['token']);
        return [$g['code'], $g['token'], $b['token'], $e['token']];
    }

    /** TC-API-006 (K-41, K-06..K-09, NFR-04): only testers start a game; the settings are checked at their boundaries */
    public function testCreateNeedsATesterAndValidSettings(): void
    {
        [$status, $j] = my_api('POST', 'create', ['category' => 'demo', 'lengthMin' => 60]);
        $this->assertSame([401, 'not_logged_in'], [$status, $j['error']]);
        [$status, $j] = my_api('POST', 'create', ['category' => 'demo', 'lengthMin' => 60], self::CAROL);
        $this->assertSame([403, 'not_tester'], [$status, $j['error']]);
        [$status] = my_api('GET', 'create', null, self::BOB);
        $this->assertSame(405, $status);

        $bad = fn(array $over) => my_api('POST', 'create', $over + ['category' => 'demo', 'lengthMin' => 60, 'waitMin' => 5, 'boardLang' => 'en'], self::BOB);
        foreach ([[['lengthMin' => 45], 'bad_length'], [['lengthMin' => 0], 'bad_length'], [['lengthMin' => 210], 'bad_length'],
                  [['waitMin' => 0], 'bad_wait'], [['waitMin' => 11], 'bad_wait'], [['category' => 'chess'], 'bad_category'],
                  [['boardLang' => 'fr'], 'bad_lang']] as [$over, $error]) {
            [$status, $j] = $bad($over);
            $this->assertSame([400, $error], [$status, $j['error'] ?? null], json_encode($over));
        }
        foreach ([[30, 1], [180, 10]] as [$len, $wait]) {                       // the boundaries themselves are fine
            $g = $this->create(self::BOB, ['lengthMin' => $len, 'waitMin' => $wait]);
            $this->assertSame([$len, $wait], [$g['view']['session']['lengthMin'], $g['view']['session']['waitMin']]);
        }

        $g = $this->create(self::ALICE, ['hostPlays' => false]);                 // the owner, as a game master only
        $this->assertMatchesRegularExpression('/^[A-HJ-NP-Z2-9]{8}$/', $g['code']);
        $this->assertSame(['master', 'approved', true], [$g['view']['you']['kind'], $g['view']['you']['status'], $g['view']['you']['host']]);
        $this->assertSame(['lobby', 'sv', false], [$g['view']['session']['status'], $g['view']['session']['boardLang'], $g['view']['session']['hostPlays']]);
        $hash = get_pdo()->query("SELECT token_hash FROM mysteries_devices ORDER BY id DESC LIMIT 1")->fetchColumn();
        $this->assertSame(hash('sha256', $g['token']), $hash);                    // only the hash is stored
    }

    /** TC-API-007 (K-12, AK-03): a board joins with the code and sees nothing until the host approves it; only the host approves */
    public function testBoardWaitsForTheHost(): void
    {
        $g = $this->create();
        [$status, $j] = my_api('POST', 'join', ['code' => 'ZZZZZZZZ', 'kind' => 'board']);
        $this->assertSame([404, 'no_session'], [$status, $j['error']]);
        [$status, $b] = my_api('POST', 'join', ['code' => strtolower($g['code']), 'kind' => 'board', 'name' => "  TV at\tMum's <b>  "]);
        $this->assertSame(200, $status);
        $this->assertSame(['board', 'waiting', "TV at Mum's b"], [$b['view']['you']['kind'], $b['view']['you']['status'], $b['view']['you']['name']]);
        [, $v] = my_api('GET', 'state', null, null, $b['token']);
        $this->assertSame(['session', 'you'], array_keys($v['view']));          // nothing about the game yet
        $this->assertSame(['code', 'status'], array_keys($v['view']['session']));

        [, $h] = my_api('GET', 'state', null, self::BOB, $g['token']);
        $this->assertSame([["TV at Mum's b", 'board']], array_map(fn($w) => [$w['name'], $w['kind']], $h['view']['waiting']));
        $id = $h['view']['waiting'][0]['id'];

        [, $e] = my_api('POST', 'join', ['code' => $g['code'], 'kind' => 'player'], self::ERIK);
        [$status, $j] = my_api('POST', 'approve', ['device' => $id, 'ok' => true], self::ERIK, $e['token']);
        $this->assertSame([403, 'not_host'], [$status, $j['error']]);
        [$status] = my_api('POST', 'approve', ['device' => $id, 'ok' => true], self::BOB, $g['token']);
        $this->assertSame(200, $status);
        [, $v] = my_api('GET', 'state', null, null, $b['token']);
        $this->assertSame('approved', $v['view']['you']['status']);
        $this->assertSame([['Bob', 'player']], array_map(fn($p) => [$p['name'], $p['kind']], $v['view']['players']));   // Erik still waits
        $this->assertArrayNotHasKey('waiting', $v['view']);                      // only the host sees who waits
    }

    /** TC-API-008 (K-12): a refused device learns it, and nothing more */
    public function testRefusedDevice(): void
    {
        $g = $this->create();
        [, $b] = my_api('POST', 'join', ['code' => $g['code'], 'kind' => 'board']);
        my_api('POST', 'approve', ['device' => $b['view']['you']['id'], 'ok' => false], self::BOB, $g['token']);
        [$status, $v] = my_api('GET', 'state', null, null, $b['token']);
        $this->assertSame([200, 'refused', ['session', 'you']], [$status, $v['view']['you']['status'], array_keys($v['view'])]);
    }

    /** TC-API-009 (K-41, US-07): a player must be logged in and a tester; the player's token only works with their own login */
    public function testPlayersAreLoggedInTesters(): void
    {
        $g = $this->create();
        [$status, $j] = my_api('POST', 'join', ['code' => $g['code'], 'kind' => 'player']);
        $this->assertSame([401, 'not_logged_in'], [$status, $j['error']]);
        [$status, $j] = my_api('POST', 'join', ['code' => $g['code'], 'kind' => 'player'], self::CAROL);
        $this->assertSame([403, 'not_tester'], [$status, $j['error']]);
        [$status, $e] = my_api('POST', 'join', ['code' => $g['code'], 'kind' => 'player'], self::ERIK);
        $this->assertSame([200, 'Erik', 'waiting'], [$status, $e['view']['you']['name'], $e['view']['you']['status']]);

        $this->assertSame(401, my_api('GET', 'state', null, null, $e['token'])[0]);              // no login
        $this->assertSame(401, my_api('GET', 'state', null, self::BOB, $e['token'])[0]);         // someone else's login
        $this->assertSame(401, my_api('GET', 'state', null, self::ERIK, 'x' . substr($e['token'], 1))[0]);   // a forged token
        $this->assertSame(401, my_api('GET', 'state', null, self::ERIK)[0]);                     // no token
        $this->assertSame(400, my_api('POST', 'join', ['code' => $g['code'], 'kind' => 'master'], self::ERIK)[0]);
    }

    /** TC-API-010 (K-13, AK-06): every player sees only their own cards; the board none */
    public function testPrivateCardsStayPrivate(): void
    {
        [$code, $host, $board, $erik] = $this->table();
        [$status, $j] = my_api('POST', 'start', null, self::ERIK, $erik);
        $this->assertSame([403, 'not_host'], [$status, $j['error']]);
        [$status] = my_api('POST', 'start', null, self::BOB, $host);
        $this->assertSame(200, $status);

        [, $b] = my_api('GET', 'state', null, self::BOB, $host);
        [, $e] = my_api('GET', 'state', null, self::ERIK, $erik);
        [, $t] = my_api('GET', 'state', null, null, $board);
        $bobCards = $b['view']['private']['cards'];
        $erikCards = $e['view']['private']['cards'];
        $this->assertCount(2, $bobCards);
        $this->assertCount(2, $erikCards);
        $this->assertSame([], array_intersect($bobCards, $erikCards));
        $this->assertArrayNotHasKey('private', $t['view']);
        foreach ($bobCards as $card) {                                           // not anywhere in what the others get
            $this->assertStringNotContainsString($card, json_encode($e['view']));
            $this->assertStringNotContainsString($card, json_encode($t['view']));
        }
        $this->assertSame('playing', $t['view']['session']['status']);
        $this->assertSame(['Bob', 'Erik'], array_column($t['view']['players'], 'name'));
    }

    /** TC-API-011 (AK-01, K-14): a card shown to all reaches the board; only a card of your own, only as game input */
    public function testShowACardToAll(): void
    {
        [$code, $host, $board, $erik] = $this->table();
        my_api('POST', 'start', null, self::BOB, $host);
        [, $e] = my_api('GET', 'state', null, self::ERIK, $erik);
        [$card, $other] = $e['view']['private']['cards'];
        [, $b] = my_api('GET', 'state', null, self::BOB, $host);

        [$status, $j] = my_api('POST', 'act', ['do' => 'reveal', 'card' => $b['view']['private']['cards'][0]], self::ERIK, $erik);
        $this->assertSame([400, 'not_yours'], [$status, $j['error']]);
        [$status, $j] = my_api('POST', 'act', ['do' => 'chat', 'text' => 'hi'], self::ERIK, $erik);
        $this->assertSame([400, 'bad_action'], [$status, $j['error']]);
        [$status, $j] = my_api('POST', 'act', ['do' => 'reveal', 'card' => $card], null, $board);   // a board does not play
        $this->assertSame([403, 'not_player'], [$status, $j['error']]);

        [$status, $j] = my_api('POST', 'act', ['do' => 'reveal', 'card' => $card], self::ERIK, $erik);
        $this->assertSame(200, $status);
        $this->assertSame([$other], $j['view']['private']['cards']);
        [, $t] = my_api('GET', 'state', null, null, $board);
        $this->assertSame([['card' => $card, 'by' => 'Erik']], $t['view']['board']['revealed']);
    }

    /** TC-API-012 (NFR-02): a screen that is up to date gets one small answer; after a change, the new view */
    public function testPollingSince(): void
    {
        [$code, $host, $board] = $this->table();
        [, $t] = my_api('GET', 'state', null, null, $board);
        $v = $t['view']['session']['version'];
        [$status, $j] = my_api('GET', 'state', null, null, $board, "&since=$v");
        $this->assertSame([200, ['status' => 'ok', 'same' => true]], [$status, $j]);
        my_api('POST', 'start', null, self::BOB, $host);
        [, $j] = my_api('GET', 'state', null, null, $board, "&since=$v");
        $this->assertGreaterThan($v, $j['view']['session']['version']);
        $this->assertSame('playing', $j['view']['session']['status']);
    }

    /** TC-API-013 (K-15, AK-08): the host pauses and resumes; nothing is played while paused; the cards stay */
    public function testPauseAndResume(): void
    {
        [$code, $host, $board, $erik] = $this->table();
        $this->assertSame([409, 'not_playing'], [my_api('POST', 'pause', null, self::BOB, $host)[0], my_api('POST', 'pause', null, self::BOB, $host)[1]['error']]);
        my_api('POST', 'start', null, self::BOB, $host);
        [, $before] = my_api('GET', 'state', null, self::ERIK, $erik);
        $this->assertSame(403, my_api('POST', 'pause', null, self::ERIK, $erik)[0]);
        $this->assertSame(200, my_api('POST', 'pause', null, self::BOB, $host)[0]);
        [, $t] = my_api('GET', 'state', null, null, $board);
        $this->assertSame('paused', $t['view']['session']['status']);
        [$status, $j] = my_api('POST', 'act', ['do' => 'reveal', 'card' => $before['view']['private']['cards'][0]], self::ERIK, $erik);
        $this->assertSame([409, 'not_playing'], [$status, $j['error']]);
        $this->assertSame(200, my_api('POST', 'resume', null, self::BOB, $host)[0]);
        [, $after] = my_api('GET', 'state', null, self::ERIK, $erik);
        $this->assertSame(['playing', $before['view']['private']['cards']], [$after['view']['session']['status'], $after['view']['private']['cards']]);
    }

    /** TC-API-014 (US-05): a device that leaves is gone: its token no longer works, and the others see it left */
    public function testLeave(): void
    {
        [$code, $host, $board, $erik] = $this->table();
        $this->assertSame(200, my_api('POST', 'leave', null, null, $board)[0]);
        $this->assertSame(401, my_api('GET', 'state', null, null, $board)[0]);
        $this->assertSame(200, my_api('POST', 'leave', null, self::ERIK, $erik)[0]);
        [, $h] = my_api('GET', 'state', null, self::BOB, $host);
        $this->assertSame([['Bob', 'player']], array_map(fn($p) => [$p['name'], $p['kind']], $h['view']['players']));
        $this->assertSame(0, $h['view']['boards']);
    }

    /** TC-API-015 (K-03): one player, two windows: each window is its own device, approved on its own; the host's own windows need no approval */
    public function testOnePlayerSeveralDevices(): void
    {
        [$code, $host] = $this->table();
        [, $second] = my_api('POST', 'join', ['code' => $code, 'kind' => 'player'], self::ERIK);
        $this->assertSame('waiting', $second['view']['you']['status']);
        [, $mine] = my_api('POST', 'join', ['code' => $code, 'kind' => 'board', 'name' => 'My second screen'], self::BOB);
        $this->assertSame('approved', $mine['view']['you']['status']);          // the host's own board
        [, $h] = my_api('GET', 'state', null, self::BOB, $host);
        $this->assertSame(['Bob', 'Erik'], array_column($h['view']['players'], 'name'));   // Erik once, however many windows
        $this->assertSame(2, $h['view']['boards']);
    }
}
