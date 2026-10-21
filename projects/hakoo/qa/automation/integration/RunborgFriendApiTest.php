<?php
/**
 * System tests of Runborg between two players (src/runborg.php, Runborg::FRIEND): the invite link, the challenge
 * through the bot (the Discord stub), the same start for both, turns in order, the waiting player's view, giving up.
 * Traceability: FR-RUNBORG-10..14.
 */
class RunborgFriendApiTest extends HakooTestCase
{
    const MOCK = 'http://wgmock:8000/discord';

    protected function setUp(): void
    {
        parent::setUp();
        file_get_contents(self::MOCK . '/_reset', false, stream_context_create(['http' => ['method' => 'POST']]));
    }
    private function api(string $action, ?array $body = null, ?string $token = null, ?string $cookie = null, string $query = ''): array
    {
        return $this->http($body === null ? 'GET' : 'POST', "runborg.php?action=$action$query", $body, $cookie, $token ? ['X-Game-Token: ' . $token] : []);
    }
    /** A friend's game, joined: [game, host token, guest token, host's state, guest's state]. */
    private function joined(string $board = 'fjord'): array
    {
        [, , , $h] = $this->api('new', ['board' => $board, 'level' => 'friend', 'name' => 'Astrid']);
        [$status, , , $g] = $this->api('join', ['game' => $h['game'], 'invite' => $h['invite'], 'name' => 'Bjorn']);
        $this->assertSame(200, $status, json_encode($g));
        return [$h['game'], $h['token'], $g['token'], $h['state'], $g['state']];
    }
    /** The token of the player whose turn it is, and the other's. */
    private function whoseTurn(string $game, string $a, string $b): array
    {
        [, , , $s] = $this->api('state', null, $a, null, "&game=$game");
        return $s['state']['turn'] === 'me' ? [$a, $b] : [$b, $a];
    }

    /** TC-API-262 | FR-RUNBORG-10, FR-RUNBORG-11: an invite link gives the second seat once; both start the same, the one who goes second +1 of each resource */
    public function test_invite_and_the_same_start()
    {
        [, , , $h] = $this->api('new', ['board' => 'winter', 'level' => 'friend', 'name' => 'Astrid']);
        $this->assertSame(['friend', 'waiting'], [$h['state']['mode'], $h['state']['status']]);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{32}$/', $h['invite']);
        [$status, , , $e] = $this->api('play', ['game' => $h['game'], 'i' => 0, 'discard' => true], $h['token']);
        $this->assertSame([409, 'waiting'], [$status, $e['error']]);
        [$status, , , $e] = $this->api('join', ['game' => $h['game'], 'invite' => 'nope']);
        $this->assertSame([404, 'bad_invite'], [$status, $e['error']]);
        [, , , $g] = $this->api('join', ['game' => $h['game'], 'invite' => $h['invite'], 'name' => 'Bjorn']);
        $this->assertSame(['active', ['me' => 'Bjorn', 'them' => 'Astrid']], [$g['state']['status'], $g['state']['names']]);
        [$status, , , $e] = $this->api('join', ['game' => $h['game'], 'invite' => $h['invite']]);
        $this->assertSame([409, 'invite_used'], [$status, $e['error']]);
        // The same start: Winter's numbers for both, no estate; the second +1 of each resource; the first's production given.
        $s = $g['state'];
        foreach (['hall' => 15, 'pal' => 10, 'forest' => 2, 'stone' => 2, 'mead' => 2] as $k => $n) {
            $this->assertSame([$n, $n], [$s['me'][$k], $s['them'][$k]], $k);
        }
        [$first, $second] = $s['turn'] === 'me' ? [$s['me'], $s['them']] : [$s['them'], $s['me']];
        $this->assertSame([12, 7, 7], [$first['t'], $first['r'], $first['w']]);          // 10 + 2 timber, 5 + 2, 5 + 2
        $this->assertSame([11, 6, 6], [$second['t'], $second['r'], $second['w']]);       // 10 + 1, 5 + 1, 5 + 1
        [, , , $hs] = $this->api('state', null, $h['token'], null, "&game={$h['game']}");    // the host sees the same turn, turned around
        $this->assertNotSame($s['turn'], $hs['state']['turn']);
    }

    /** TC-API-263 | FR-RUNBORG-12: the turns go in order; each page sees itself as 'me' and its own hand only; the waiting page gets the other's steps */
    public function test_turns_and_the_waiting_view()
    {
        [$game, $a, $b, , $gs] = $this->joined();
        [$now, $next] = $this->whoseTurn($game, $a, $b);
        [$status, , , $e] = $this->api('play', ['game' => $game, 'i' => 0, 'discard' => true], $next);
        $this->assertSame([409, 'not_your_turn'], [$status, $e['error']]);
        [, , , $before] = $this->api('state', null, $next, null, "&game=$game");
        [, , , $p] = $this->api('play', ['game' => $game, 'i' => 0, 'discard' => true], $now);
        $this->assertSame(['card:me', 'produce:them'], array_map(fn($e) => $e['kind'] . ':' . $e['who'], $p['events']));
        $this->assertSame('them', $p['state']['turn']);
        // The other page asks for what is new: the same steps, turned around, and its turn now.
        [, , , $w] = $this->api('state', null, $next, null, "&game=$game&since=" . $before['state']['version']);
        $this->assertSame(['card:them', 'produce:me'], array_map(fn($e) => $e['kind'] . ':' . $e['who'], $w['events']));
        $this->assertSame('me', $w['state']['turn']);
        $this->assertSame($p['events'][0]['before']['me'], $w['events'][0]['before']['them']);
        $this->assertArrayNotHasKey('hand', $w['state']['them']);
        $this->assertCount(6, $w['state']['me']['hand']);
        [, , , $none] = $this->api('state', null, $next, null, "&game=$game&since=" . $w['state']['version']);
        $this->assertSame([], $none['events']);
    }

    /** TC-API-264 | FR-RUNBORG-13: a challenge: the bot posts it, only the challenged member takes the seat; either gets their seat back; no title for a win between players */
    public function test_challenge_through_the_bot()
    {
        $alice = $this->sessionCookie(self::ALICE, 'Alice');
        $bob = $this->sessionCookie(self::BOB, 'Bob');
        [$status, , , $c] = $this->api('challenge', ['board' => 'dusk', 'opponent' => self::BOB], null, $alice);
        $this->assertSame([200, true, 'waiting', 'Member 0002'], [$status, $c['notified'], $c['state']['status'], $c['state']['names']['them']]);
        $posts = array_values(array_filter(json_decode(file_get_contents(self::MOCK . '/_log'), true), fn($x) => $x['method'] === 'POST' && str_contains($x['path'], '/messages')));
        $this->assertStringContainsString('challenges you to **Runborg** on the Winter Dusk board', $posts[0]['body']['content']);
        $this->assertStringContainsString("runborg.html?challenge={$c['game']}", json_encode($posts[0]['body']['components']));
        [$status, , , $e] = $this->api('accept', ['game' => $c['game']], null, $this->sessionCookie(self::CAROL, 'Carol'));
        $this->assertSame([403, 'not_yours'], [$status, $e['error']]);
        $this->assertSame(401, $this->api('accept', ['game' => $c['game']])[0]);
        [$status, , , $g] = $this->api('accept', ['game' => $c['game']], null, $bob);
        $this->assertSame([200, 'active', 'Bob'], [$status, $g['state']['status'], $g['state']['names']['me']]);
        [, , , $x] = $this->http('GET', 'mydata.php?action=export', null, $bob);               // in Bob's data too (REG-GDPR-15)
        $this->assertSame([$c['game']], array_column($x['runborg_as_second_player'], 'id'));
        $edits = array_filter(json_decode(file_get_contents(self::MOCK . '/_log'), true), fn($x) => $x['method'] === 'PATCH');
        $this->assertStringContainsString('The saga is on', end($edits)['body']['content']);
        [, , , $back] = $this->api('accept', ['game' => $c['game']], null, $alice);      // the challenger, on another device
        $this->assertSame(200, $this->api('state', null, $back['token'], null, "&game={$c['game']}")[0]);
        $this->assertSame(403, $this->api('state', null, $c['token'], null, "&game={$c['game']}")[0]);   // the old token is gone
        [$status, , , $e] = $this->api('challenge', ['board' => 'fjord', 'opponent' => self::ALICE], null, $alice);
        $this->assertSame([400, 'challenge_self'], [$status, $e['error']]);
        $this->assertSame(404, $this->api('challenge', ['board' => 'fjord', 'opponent' => '100000000000000404'], null, $alice)[0]);
    }

    /** TC-API-265 | FR-RUNBORG-14: giving up hands the other player the win; a finished game takes no more cards */
    public function test_giving_up()
    {
        [$game, $a, $b] = $this->joined();
        [, , , $r] = $this->api('resign', ['game' => $game], $b);
        $this->assertSame(['them', 'resigned'], [$r['state']['over'], $r['state']['why']]);
        [, , , $s] = $this->api('state', null, $a, null, "&game=$game");
        $this->assertSame(['me', 'over'], [$s['state']['over'], $s['state']['status']]);
        [$status, , , $e] = $this->api('play', ['game' => $game, 'i' => 0, 'discard' => true], $a);
        $this->assertSame([409, 'game_over'], [$status, $e['error']]);
        $this->assertSame(0, $this->rows('hakoo_runborg_wins', 'discord_id NOT IN (?)', [self::BOB]));   // no title from a game between players
    }
}
