<?php
/**
 * System tests of Wargaming account linking (src/link.php) against the
 * Wargaming stub (qa/automation/mocks/wg-api.php, service `wgmock`).
 * Traceability: US-13, US-14, FR-LINK-01..06, NFR-SEC-09, DEF-010.
 */
class LinkApiTest extends HakooTestCase
{
    private function locationOf(array $headers)
    {
        foreach ($headers as $h) if (stripos($h, 'Location:') === 0) return trim(substr($h, 9));
        return null;
    }

    private function ownerOf($wotId)
    {
        $s = $this->pdo->prepare("SELECT discord_id FROM hakoo_links WHERE kind = 'wot' AND ext_id = ?");
        $s->execute([$wotId]);
        $id = $s->fetchColumn();
        return $id === false ? false : (string)$id;   // BIGINT comes back as int with native prepares
    }

    /** TC-API-040 | FR-LINK-02, DEF-010 regression: no token, no link */
    public function test_callback_without_token_is_refused()
    {
        [$status, $headers] = $this->http('GET', 'link.php?provider=wot&status=ok&account_id=500000012&nickname=Hijacked', null, $this->sessionCookie(self::ALICE));
        $this->assertSame(302, $status);
        $this->assertStringContainsString('link_error=wot', $this->locationOf($headers));
        $this->assertSame(self::BOB, $this->ownerOf('500000012'));
    }

    /** TC-API-041 | FR-LINK-02, DEF-010 regression: a valid token for ANOTHER account proves nothing */
    public function test_valid_token_for_another_account_is_refused()
    {
        [, $headers] = $this->http('GET', 'link.php?provider=wot&status=ok&account_id=500000012&access_token=valid-token-500000011', null, $this->sessionCookie(self::ALICE));
        $this->assertStringContainsString('link_error=wot', $this->locationOf($headers));
        $this->assertSame(self::BOB, $this->ownerOf('500000012'));
    }

    /** TC-API-042 | FR-LINK-01, FR-LINK-03, FR-LINK-04 */
    public function test_verified_token_links_the_account_and_hands_the_token_to_the_browser()
    {
        [$status, $headers] = $this->http('GET', 'link.php?provider=wot&return=wot-tracker.html&status=ok&account_id=500000077'
            . '&access_token=valid-token-500000077&nickname=NameFromUrl&expires_at=1999999999', null, $this->sessionCookie(self::CAROL));
        $this->assertSame(302, $status);
        $loc = $this->locationOf($headers);
        $this->assertMatchesRegularExpression('~/profile\.html\?return=wot-tracker\.html&linked=wot#wg=[A-Za-z0-9_-]+$~', $loc);

        $this->assertSame(self::CAROL, $this->ownerOf('500000077'));
        $s = $this->pdo->prepare("SELECT ext_name FROM hakoo_links WHERE ext_id = '500000077'");
        $s->execute();
        $this->assertSame('MockTanker_500000077', $s->fetchColumn(), 'nickname must come from Wargaming, not from the URL');

        $handoff = json_decode(base64_decode(strtr(explode('#wg=', $loc)[1], '-_', '+/')), true);
        $this->assertSame('valid-token-500000077', $handoff['t']);
        $this->assertSame('500000077', $handoff['a']);
        $this->assertSame(1999999999, $handoff['e']);
    }

    /** TC-API-043 | FR-LINK-05: the return page survives the Wargaming round trip */
    public function test_wot_start_passes_the_return_page_to_wargaming()
    {
        [$status, $headers] = $this->http('GET', 'link.php?action=wot_start&return=WoWs-tracker.html', null, $this->sessionCookie(self::CAROL));
        $this->assertSame(302, $status);
        $loc = $this->locationOf($headers);
        $this->assertStringStartsWith('http://wgmock:8000/wot/auth/login/', $loc);
        parse_str(parse_url($loc, PHP_URL_QUERY), $q);
        $this->assertSame('http://web/link.php?provider=wot&return=WoWs-tracker.html', $q['redirect_uri']);
    }

    /** TC-API-044 | NFR-SEC-10: open redirect, a foreign return address is dropped */
    public function test_foreign_return_address_is_ignored()
    {
        [, $headers] = $this->http('GET', 'link.php?action=wot_start&return=' . rawurlencode('https://evil.example/x.html'), null, $this->sessionCookie(self::CAROL));
        parse_str(parse_url($this->locationOf($headers), PHP_URL_QUERY), $q);
        $this->assertSame('http://web/link.php?provider=wot', $q['redirect_uri']);
    }

    /** TC-API-045 | FR-LINK-06: without a Discord session nothing is linked */
    public function test_callback_without_discord_session_links_nothing()
    {
        [, $headers] = $this->http('GET', 'link.php?provider=wot&return=wot-tracker.html&status=ok&account_id=500000077&access_token=valid-token-500000077');
        $this->assertStringContainsString('/profile.html?return=wot-tracker.html', $this->locationOf($headers));
        $this->assertFalse($this->ownerOf('500000077'));
    }
}
