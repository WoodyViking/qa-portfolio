<?php
/**
 * System tests of challenges between members (board-game-api.inc.php: action=challenge / accept,
 * hakoo_challenges; the bot's posts through src/hakoo-discord.inc.php to the Discord stub in wgmock).
 * Traceability: FR-GAMES-CH-01..06.
 */
class ChallengeApiTest extends HakooTestCase
{
    const MOCK = 'http://wgmock:8000/discord';
    const CHANNEL = '900000000000000001';                 // #board-game-challenges in the stub

    protected function setUp(): void
    {
        parent::setUp();
        file_get_contents(self::MOCK . '/_reset', false, stream_context_create(['http' => ['method' => 'POST']]));
    }

    /** Every call the website made to Discord since setUp. */
    private function discord(): array
    {
        return json_decode(file_get_contents(self::MOCK . '/_log'), true) ?: [];
    }

    private function messages(string $method): array
    {
        return array_values(array_filter($this->discord(), fn($c) => $c['method'] === $method && strpos($c['path'], '/messages') !== false));
    }

    private function api(string $game, string $action, array $body = [], ?string $cookie = null, ?string $token = null, array $headers = []): array
    {
        if ($token) $headers[] = ($game === 'gomoku' ? 'X-Gomoku-Token: ' : 'X-Game-Token: ') . $token;
        $method = $action === 'state' ? 'GET' : 'POST';
        $query = $action === 'state' ? '&game=' . $body['game'] : '';
        return $this->http($method, "$game.php?action=$action$query", $method === 'POST' ? $body : null, $cookie, $headers);
    }

    private function alice(): string { return $this->sessionCookie(self::ALICE, 'Alice'); }
    private function bob(): string { return $this->sessionCookie(self::BOB, 'Bob'); }

    /** Alice challenges Bob to chess (she plays white). */
    private function challenge(string $game = 'chess'): array
    {
        [$status, , , $j] = $this->api($game, 'challenge', ['opponent' => self::BOB, 'color' => 'white'], $this->alice());
        $this->assertSame(200, $status, json_encode($j));
        return $j;
    }

    /** TC-API-204 | FR-GAMES-CH-01, FR-GAMES-CH-03: a challenge waits 24 hours for Bob, and the bot posts it in the challenge channel, pinging only him */
    public function test_a_challenge_is_posted_for_the_challenged_member()
    {
        $j = $this->challenge();
        $this->assertSame(['waiting', 'white', true], [$j['state']['status'], $j['state']['you'], $j['notified']]);
        $this->assertGreaterThan(23 * 3600, $j['state']['inviteExpiresIn']);
        $this->assertSame('Member 0002', $j['state']['challenge']['opponent']);          // the name on the server, from Discord
        $this->assertArrayNotHasKey('invite', $j);                                      // no link anyone could use
        $posts = $this->messages('POST');
        $this->assertCount(1, $posts);
        $this->assertSame('/channels/' . self::CHANNEL . '/messages', $posts[0]['path']);
        $this->assertStringContainsString('<@' . self::BOB . '>, **Alice** challenges you to a game of **Chess**', $posts[0]['body']['content']);
        $this->assertSame(['users' => [self::BOB]], $posts[0]['body']['allowed_mentions']);
        $url = $posts[0]['body']['components'][0]['components'][0]['url'];
        $this->assertStringEndsWith("/games/chess.html?challenge={$j['game']}", $url);
        $row = $this->pdo->query("SELECT * FROM hakoo_challenges")->fetch(PDO::FETCH_ASSOC);
        $this->assertSame(['chess', $j['game'], self::ALICE, self::BOB, 'white', 'open', self::CHANNEL],
            [$row['game'], $row['game_id'], (string)$row['discord_id'], (string)$row['opponent_id'], $row['seat'], $row['status'], (string)$row['channel_id']]);
    }

    /** TC-API-205 | FR-GAMES-CH-02, FR-GAMES-CH-04: only Bob, logged in, can take the seat; the post says the game is on and Alice is told */
    public function test_only_the_challenged_member_can_accept()
    {
        $j = $this->challenge();
        [$status, , , $e] = $this->api('chess', 'accept', ['game' => $j['game']]);
        $this->assertSame([401, 'not_logged_in'], [$status, $e['error']]);
        [$status, , , $e] = $this->api('chess', 'accept', ['game' => $j['game']], $this->sessionCookie(self::CAROL, 'Carol'));
        $this->assertSame([403, 'not_for_you'], [$status, $e['error']]);
        [, , , $e] = $this->api('chess', 'join', ['game' => $j['game'], 'invite' => '', 'name' => 'Eve']);
        $this->assertSame('invite_invalid', $e['error']);                               // the friend-invite way is closed

        [$status, , , $b] = $this->api('chess', 'accept', ['game' => $j['game']], $this->bob());
        $this->assertSame(200, $status);
        $this->assertSame(['active', 'black', 'Bob', 'Alice'],
            [$b['state']['status'], $b['state']['you'], $b['state']['names']['black'], $b['state']['names']['white']]);
        [, , , $s] = $this->api('chess', 'state', ['game' => $j['game']], null, $j['token']);   // Alice sees it start
        $this->assertSame('active', $s['state']['status']);

        $edit = $this->messages('PATCH');
        $this->assertCount(1, $edit);
        $this->assertStringContainsString('The game is on', $edit[0]['body']['content']);
        $reply = array_slice($this->messages('POST'), -1)[0];
        $this->assertStringContainsString('<@' . self::ALICE . '>, **Member 0002** accepted your challenge', $reply['body']['content']);
        $this->assertSame(['users' => [self::ALICE]], $reply['body']['allowed_mentions']);
        $this->assertSame('accepted', $this->pdo->query("SELECT status FROM hakoo_challenges")->fetchColumn());
    }

    /** TC-API-206 | FR-GAMES-CH-04: the same link brings either member back to their seat on another device (the older link stops working) */
    public function test_both_members_get_their_seat_back_with_the_link()
    {
        $j = $this->challenge();
        [, , , $b] = $this->api('chess', 'accept', ['game' => $j['game']], $this->bob());
        [$status, , , $a2] = $this->api('chess', 'accept', ['game' => $j['game']], $this->alice());
        $this->assertSame([200, 'white'], [$status, $a2['state']['you']]);
        [$status, , , $e] = $this->api('chess', 'state', ['game' => $j['game']], null, $j['token']);
        $this->assertSame([403, 'bad_token'], [$status, $e['error']]);
        [$status] = $this->api('chess', 'move', ['game' => $j['game'], 'move' => 'e2e4'], null, $a2['token']);
        $this->assertSame(200, $status);
        [$status, , , $b2] = $this->api('chess', 'accept', ['game' => $j['game']], $this->bob());
        $this->assertSame([200, 'black', ['e2e4']], [$status, $b2['state']['you'], $b2['state']['moves']]);
        $this->assertCount(1, $this->messages('PATCH'));                                // coming back changes nothing in Discord
    }

    /** TC-API-207 | FR-GAMES-CH-05: the post follows the game: the result, a withdrawn challenge, an expired one */
    public function test_the_post_follows_the_game()
    {
        $j = $this->challenge();
        [, , , $b] = $this->api('chess', 'accept', ['game' => $j['game']], $this->bob());
        $this->api('chess', 'resign', ['game' => $j['game']], null, $b['token']);
        $this->assertStringContainsString('**Alice** won (the other side resigned).', array_slice($this->messages('PATCH'), -1)[0]['body']['content']);
        $this->assertSame('over', $this->pdo->query("SELECT status FROM hakoo_challenges WHERE game_id = '{$j['game']}'")->fetchColumn());

        $w = $this->challenge();                                                         // withdrawn
        $this->api('chess', 'cancel', ['game' => $w['game']], null, $w['token']);
        $this->assertStringContainsString('withdrew the challenge', array_slice($this->messages('PATCH'), -1)[0]['body']['content']);
        [$status, , , $e] = $this->api('chess', 'accept', ['game' => $w['game']], $this->bob());
        $this->assertSame([410, 'challenge_cancelled'], [$status, $e['error']]);

        $x = $this->challenge();                                                         // nobody answered in time
        $this->pdo->exec("UPDATE hakoo_chess_games SET invite_expires_at = NOW() - INTERVAL 1 MINUTE WHERE id = '{$x['game']}'");
        [$status, , , $e] = $this->api('chess', 'accept', ['game' => $x['game']], $this->bob());
        $this->assertSame([410, 'challenge_expired'], [$status, $e['error']]);
        $last = array_slice($this->messages('PATCH'), -1)[0]['body'];
        $this->assertStringContainsString('expired without an answer', $last['content']);
        $this->assertSame([], $last['components']);                                     // no button left to press
    }

    /** TC-API-208 | FR-GAMES-CH-01 (negative): no login, oneself, a bad id, someone not on the server, a bot */
    public function test_bad_challenges_are_refused()
    {
        [$status, , , $e] = $this->api('chess', 'challenge', ['opponent' => self::BOB]);
        $this->assertSame([401, 'not_logged_in'], [$status, $e['error']]);
        foreach ([[self::ALICE, 400, 'challenge_self'], ['12345', 400, 'bad_member'], ["1' OR '1'='1", 400, 'bad_member'],
                  ['100000000000000404', 404, 'not_a_member'], ['100000000000000777', 400, 'challenge_bot']] as [$opp, $code, $err]) {
            [$status, , , $e] = $this->api('chess', 'challenge', ['opponent' => $opp], $this->alice());
            $this->assertSame([$code, $err], [$status, $e['error']], $opp);
        }
        $this->assertSame(0, $this->rows('hakoo_challenges', '1'));
        $this->assertSame([], $this->messages('POST'));
    }

    /** TC-API-209 | FR-GAMES-CH-06: the bot challenges for a member with the shared secret; a wrong secret is refused */
    public function test_the_bot_challenges_for_a_member()
    {
        $body = ['by' => self::DAVE, 'byName' => 'Dave **the** <@1>', 'opponent' => self::BOB, 'color' => 'black'];
        [$status, , , $e] = $this->api('gomoku', 'challenge', $body, null, null, ['<BOT_AUTH_HEADER>: <WRONG_SECRET_VALUE>']);
        $this->assertSame([403, 'bad_bot'], [$status, $e['error']]);
        [$status, , , $j] = $this->api('gomoku', 'challenge', $body, null, null, ['<BOT_AUTH_HEADER>: ' . <BOT_SECRET>]);
        $this->assertSame([200, 'black', 'Dave_the_1'], [$status, $j['state']['you'], $j['state']['names']['black']]);
        $this->assertStringContainsString('**Dave the 1** challenges you to a game of **Gomoku**', $this->messages('POST')[0]['body']['content']);
        $this->assertSame(1, $this->rows('hakoo_users', 'discord_id = ?', [self::DAVE]));   // the challenger becomes a member row
        [$status] = $this->api('gomoku', 'state', ['game' => $j['game']], null, $j['token']);
        $this->assertSame(200, $status);                                                  // the link the bot gives Dave works
    }

    /** TC-API-210 | FR-GAMES-CH-06, REG-GDPR-15, REG-GDPR-17: both members find the challenge in their export, and it goes with either of them */
    public function test_challenges_in_the_export_and_deleted_with_the_member()
    {
        $j = $this->challenge();
        [, , , $a] = $this->http('GET', 'mydata.php?action=export', null, $this->alice());
        $sent = $a['games_and_settings']['hakoo_challenges'];
        $this->assertSame([$j['game'], 'Member 0002'], [$sent[0]['game_id'], $sent[0]['opponent_name']]);
        $this->assertArrayNotHasKey('opponent_id', $sent[0]);                           // the other member's id is theirs
        [, , , $b] = $this->http('GET', 'mydata.php?action=export', null, $this->bob());
        $this->assertSame([$j['game'], 'Alice'], [$b['challenges_received'][0]['game_id'], $b['challenges_received'][0]['name']]);
        [$status] = $this->http('POST', 'mydata.php?action=delete', ['confirm' => 'DELETE'], $this->bob());
        $this->assertSame(200, $status);
        $this->assertSame(0, $this->rows('hakoo_challenges', '1'));

        $this->challenge();
        $this->http('POST', 'mydata.php?action=delete', ['confirm' => 'DELETE'], $this->alice());
        $this->assertSame(0, $this->rows('hakoo_challenges', '1'));
    }
}
