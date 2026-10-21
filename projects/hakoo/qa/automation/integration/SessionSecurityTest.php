<?php
/**
 * Session security: login cookies must be signed with <SESSION_SECRET>, never with
 * <API_SECRET> (a shared secret that must never sign sessions).
 * Traceability: NFR-SEC-02, NFR-SEC-10, FR-AUTH-05, DEF-011.
 */
class SessionSecurityTest extends HakooTestCase
{
    /** TC-API-050 | NFR-SEC-02, DEF-011 regression */
    public function test_cookie_signed_with_the_public_api_secret_is_rejected()
    {
        $this->assertNotSame(<API_SECRET>, <SESSION_SECRET>, 'test config must use two different secrets');
        $forged = $this->sessionCookie(self::ALICE, 'Evil', <API_SECRET>);
        [$status] = $this->http('GET', 'mydata.php?action=export', null, $forged);
        $this->assertSame(401, $status);
        [, , , $me] = $this->http('GET', 'discord-auth.php?action=me', null, $forged);
        $this->assertFalse($me['logged_in']);
    }

    /** TC-API-051 | FR-AUTH-01: a correctly signed cookie is accepted (control case) */
    public function test_cookie_signed_with_the_session_secret_is_accepted()
    {
        [, , , $me] = $this->http('GET', 'discord-auth.php?action=me', null, $this->sessionCookie(self::ALICE, 'Alice'));
        $this->assertTrue($me['logged_in']);
        $this->assertSame(self::ALICE, $me['user']['id']);
    }

    /** TC-API-052 | FR-AUTH-05: login remembers an own page to return to */
    public function test_login_start_remembers_an_own_return_page()
    {
        [$status, $headers] = $this->http('GET', 'discord-auth.php?action=start&return=' . rawurlencode('profile.html?need=wot&return=wot-tracker.html'));
        $this->assertSame(302, $status);
        $this->assertMatchesRegularExpression('/Set-Cookie:\s*hakoo_login_return=profile\.html%3Fneed%3Dwot%26return%3Dwot-tracker\.html/i', implode("\n", $headers));
    }

    /** TC-API-053 | NFR-SEC-10: open redirect, a foreign return address is not remembered */
    public function test_login_start_ignores_a_foreign_return_address()
    {
        [, $headers] = $this->http('GET', 'discord-auth.php?action=start&return=' . rawurlencode('https://evil.example/'));
        $this->assertStringNotContainsString('hakoo_login_return', implode("\n", $headers));
    }

    /** TC-API-088 | FR-AUTH, NFR-SEC-10: logout returns to an own page, never to a foreign address */
    public function test_logout_returns_to_an_own_page_only()
    {
        $location = fn($h) => trim(substr(current(array_filter($h, fn($x) => stripos($x, 'Location:') === 0)) ?: 'Location:', 9));
        [, $h] = $this->http('GET', 'discord-auth.php?action=logout&return=' . rawurlencode('games/gomoku.html'));
        $this->assertSame('/games/gomoku.html', $location($h));
        [, $h] = $this->http('GET', 'discord-auth.php?action=logout&return=' . rawurlencode('//evil.example/x.html'));
        $this->assertSame('/community.html', $location($h));
        [, $h] = $this->http('GET', 'discord-auth.php?action=logout');
        $this->assertSame('/community.html', $location($h));
    }
}
