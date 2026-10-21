<?php
/**
 * System tests of the site appearance (appearance.php) over HTTP, stored in hakoo_appearance.
 * Test design: equivalence partitions and boundaries of a look (qa/test-design/equivalence-partitioning-bva.md §9).
 * Traceability: FR-SITE-02, FR-SITE-03, FR-SITE-05, REG-GDPR-15, REG-GDPR-17, NFR-SEC-01.
 */
class AppearanceApiTest extends HakooTestCase
{
    private function post(string $action, $body, ?string $cookie): array
    {
        return $this->http('POST', "appearance.php?action=$action", $body, $cookie);
    }

    /** TC-API-170 | FR-SITE-03, NFR-SEC-01 (negative): no login, no look; saving needs a login and a POST */
    public function test_login_and_post_are_needed()
    {
        [$status, , , $j] = $this->http('GET', 'appearance.php?action=me');
        $this->assertSame([200, false, null], [$status, $j['loggedIn'], $j['look']]);
        [$status, , , $j] = $this->post('save', ['theme' => 'light'], null);
        $this->assertSame([401, 'not_logged_in'], [$status, $j['error']]);
        [$status, , , $j] = $this->http('GET', 'appearance.php?action=save&theme=light', null, $this->sessionCookie(self::CAROL));
        $this->assertSame([405, 'post_required'], [$status, $j['error']]);
        $this->assertSame(0, $this->rows('hakoo_appearance', '1'));
    }

    /** TC-API-171 | FR-SITE-02, FR-SITE-03: every theme and pattern, own colours (any case) and the theme's (null) are kept */
    public function test_valid_looks_are_saved()
    {
        $cookie = $this->sessionCookie(self::DAVE, 'Dave');             // no member row yet: it is created
        foreach (['dark', 'light', 'contrast', 'hakoo'] as $theme) {
            foreach (['none', 'dots', 'grid', 'lines'] as $pattern) {
                [$status, , , $j] = $this->post('save', ['theme' => $theme, 'pattern' => $pattern], $cookie);
                $this->assertSame(200, $status, "$theme $pattern");
            }
        }
        $look = ['theme' => 'dark', 'bg' => '#102030', 'text' => '#F0F0F0', 'accent' => '#FFAA00', 'pattern' => 'dots'];
        [$status, , , $j] = $this->post('save', $look, $cookie);
        $this->assertSame(200, $status);
        $want = ['theme' => 'dark', 'pattern' => 'dots', 'effect' => 'none', 'gradient' => null, 'bg' => '#102030', 'text' => '#f0f0f0',
                 'accent' => '#ffaa00', 'box' => null];
        $this->assertEquals($want, $j['look']);
        $me = $this->http('GET', 'appearance.php?action=me', null, $cookie)[3];
        $this->assertEquals($want, $me['look']);
        $this->assertSame(1, $this->rows('hakoo_appearance', 'discord_id = ?', [self::DAVE]));
    }

    /** TC-API-172 | FR-SITE-02 (negative): unknown names, bad colours and injection attempts are refused */
    public function test_bad_looks_are_refused()
    {
        $cookie = $this->sessionCookie(self::CAROL);
        foreach ([[], ['theme' => 'neon'], ['theme' => null], ['theme' => 'light', 'pattern' => 'stars'],
                  ['theme' => 'light', 'bg' => '#fff'], ['theme' => 'light', 'bg' => 'white'], ['theme' => 'light', 'text' => '#12345g'],
                  ['theme' => 'light', 'accent' => '#1234567'], ['theme' => 'light', 'bg' => 123456],
                  ['theme' => "light'; DROP TABLE hakoo_appearance; --"], ['theme' => 'light', 'bg' => "#000000' OR '1'='1"]] as $body) {
            [$status, , , $j] = $this->post('save', $body, $cookie);
            $this->assertSame([400, 'bad_look'], [$status, $j['error']], json_encode($body));
        }
        $this->assertSame(0, $this->rows('hakoo_appearance', '1'));
    }

    /** TC-API-173 | FR-SITE-02 (negative): text below 4.5:1 or an accent below 3:1 against the background is refused */
    public function test_hard_to_read_is_refused()
    {
        $cookie = $this->sessionCookie(self::CAROL);
        foreach ([['theme' => 'dark', 'text' => '#30363d'],                         // grey on the dark background
                  ['theme' => 'light', 'bg' => '#1f2328'],                          // the light theme's text colour as background
                  ['theme' => 'dark', 'accent' => '#1c2128'],                       // an accent that disappears
                  ['theme' => 'dark', 'bg' => '#777777', 'text' => '#ffffff', 'accent' => '#000000']] as $body) {   // text 4.48:1, just below
            [$status, , , $j] = $this->post('save', $body, $cookie);
            $this->assertSame([400, 'hard_to_read'], [$status, $j['error']], json_encode($body));
        }
        [$status] = $this->post('save', ['theme' => 'dark', 'bg' => '#767676', 'text' => '#ffffff', 'accent' => '#000000'], $cookie);   // text 4.54:1, just above
        $this->assertSame(200, $status);
    }

    /** TC-API-174 | FR-SITE-03, REG-GDPR-15, REG-GDPR-17: reset removes the look; it is exported and deleted with the member */
    public function test_reset_export_and_delete()
    {
        $cookie = $this->sessionCookie(self::DAVE, 'Dave');
        $this->post('save', ['theme' => 'contrast', 'pattern' => 'grid'], $cookie);
        [$status, , , $j] = $this->post('reset', [], $cookie);
        $this->assertSame([200, null], [$status, $j['look']]);
        $this->assertSame(0, $this->rows('hakoo_appearance', 'discord_id = ?', [self::DAVE]));
        $this->post('save', ['theme' => 'hakoo'], $cookie);
        $d = hakoo_collect_user($this->pdo, self::DAVE);
        $this->assertSame('hakoo', $d['tables']['hakoo_appearance'][0]['theme']);
        [$status] = $this->http('POST', 'mydata.php?action=delete', ['confirm' => 'DELETE'], $cookie);
        $this->assertSame(200, $status);
        $this->assertSame(0, $this->rows('hakoo_appearance', 'discord_id = ?', [self::DAVE]));
    }

    /** TC-API-175 | FR-SITE-05: a gradient of 2-4 colours, a straight line (0-360 degrees) or a circle from a place, is kept */
    public function test_valid_gradients_are_saved()
    {
        $cookie = $this->sessionCookie(self::DAVE, 'Dave');
        $two = [['c' => '#0B2A4A', 'p' => 0], ['c' => '#3a0a5e', 'p' => 100]];
        $four = [['c' => '#0b2a4a', 'p' => 0], ['c' => '#3a0a5e', 'p' => 30], ['c' => '#3a0a5e', 'p' => 30], ['c' => '#14532d', 'p' => 100]];
        foreach ([['type' => 'linear', 'angle' => 0, 'stops' => $two], ['type' => 'linear', 'angle' => 360, 'stops' => $four],
                  ['type' => 'radial', 'at' => 'bottom right', 'stops' => $two], ['type' => 'radial', 'at' => 'center', 'stops' => $four]] as $g) {
            [$status, , , $j] = $this->post('save', ['theme' => 'dark', 'bg' => '#000000', 'gradient' => $g], $cookie);
            $this->assertSame(200, $status, json_encode($g));
            $this->assertNull($j['look']['bg']);                                       // several colours replace the one colour
        }
        $me = $this->http('GET', 'appearance.php?action=me', null, $cookie)[3];
        $this->assertEquals(['type' => 'radial', 'at' => 'center', 'stops' => $four], $me['look']['gradient']);
        $this->post('save', ['theme' => 'dark', 'gradient' => ['type' => 'linear', 'angle' => 90, 'stops' => $two]], $cookie);
        $me = $this->http('GET', 'appearance.php?action=me', null, $cookie)[3];
        $this->assertSame('#0b2a4a', $me['look']['gradient']['stops'][0]['c']);       // stored in lower case
    }

    /** TC-API-176 | FR-SITE-05 (negative): bad gradients are refused; text and accent are checked against every colour (also the HAKOO theme's own) */
    public function test_bad_or_unreadable_gradients_are_refused()
    {
        $cookie = $this->sessionCookie(self::CAROL);
        $ok = [['c' => '#0b2a4a', 'p' => 0], ['c' => '#3a0a5e', 'p' => 100]];
        foreach ([['type' => 'conic', 'angle' => 0, 'stops' => $ok], ['type' => 'linear', 'angle' => -1, 'stops' => $ok],
                  ['type' => 'linear', 'angle' => 361, 'stops' => $ok], ['type' => 'linear', 'angle' => '90', 'stops' => $ok],
                  ['type' => 'radial', 'at' => 'middle', 'stops' => $ok], ['type' => 'linear', 'angle' => 90, 'stops' => [$ok[0]]],
                  ['type' => 'linear', 'angle' => 90, 'stops' => array_fill(0, 5, $ok[0])],
                  ['type' => 'linear', 'angle' => 90, 'stops' => [['c' => '#0b2a4a', 'p' => 60], ['c' => '#3a0a5e', 'p' => 40]]],
                  ['type' => 'linear', 'angle' => 90, 'stops' => [['c' => '#0b2a4a', 'p' => 0], ['c' => '#3a0a5e', 'p' => 101]]],
                  ['type' => 'linear', 'angle' => 90, 'stops' => [['c' => 'navy', 'p' => 0], ['c' => '#3a0a5e', 'p' => 100]]],
                  ['type' => 'linear', 'angle' => 90, 'stops' => ['a' => $ok[0], 'b' => $ok[1]]], 'linear-gradient(red, blue)'] as $g) {
            [$status, , , $j] = $this->post('save', ['theme' => 'dark', 'gradient' => $g], $cookie);
            $this->assertSame([400, 'bad_look'], [$status, $j['error']], json_encode($g));
        }
        // The light text of the dark theme on a gradient that ends in a light colour
        [$status, , , $j] = $this->post('save', ['theme' => 'dark', 'gradient' => ['type' => 'linear', 'angle' => 180,
            'stops' => [['c' => '#0b2a4a', 'p' => 0], ['c' => '#f5f5dc', 'p' => 100]]]], $cookie);
        $this->assertSame([400, 'hard_to_read'], [$status, $j['error']]);
        // The HAKOO theme: a text colour that is readable on its deep purple but not on its brighter purple
        [$status, , , $j] = $this->post('save', ['theme' => 'hakoo', 'text' => '#6a5acd'], $cookie);
        $this->assertSame([400, 'hard_to_read'], [$status, $j['error']]);
        $this->assertSame(0, $this->rows('hakoo_appearance', '1'));
    }

    /** TC-API-189 | FR-SITE-06, FR-SITE-07: own boxes and a moving background are kept; boxes the text cannot be read on, and unknown effects, are refused */
    public function test_boxes_and_moving_background()
    {
        $cookie = $this->sessionCookie(self::DAVE, 'Dave');
        foreach (['none', 'stars', 'net', 'fireflies'] as $effect) {
            [$status, , , $j] = $this->post('save', ['theme' => 'dark', 'effect' => $effect], $cookie);
            $this->assertSame([200, $effect], [$status, $j['look']['effect']]);
        }
        [$status, , , $j] = $this->post('save', ['theme' => 'hakoo', 'box' => '#1A0033', 'effect' => 'stars'], $cookie);
        $this->assertSame(200, $status);
        $me = $this->http('GET', 'appearance.php?action=me', null, $cookie)[3];
        $this->assertSame(['#1a0033', 'stars'], [$me['look']['box'], $me['look']['effect']]);
        foreach ([['theme' => 'dark', 'effect' => 'rain'], ['theme' => 'dark', 'effect' => 3], ['theme' => 'dark', 'box' => 'purple']] as $body) {
            [$status, , , $j] = $this->post('save', $body, $cookie);
            $this->assertSame([400, 'bad_look'], [$status, $j['error']], json_encode($body));
        }
        // The dark theme's light text on light boxes; its yellow accent on yellow boxes
        foreach ([['theme' => 'dark', 'box' => '#e0e0e0'], ['theme' => 'dark', 'box' => '#f0a500']] as $body) {
            [$status, , , $j] = $this->post('save', $body, $cookie);
            $this->assertSame([400, 'hard_to_read'], [$status, $j['error']], json_encode($body));
        }
    }

    /** TC-API-197 | FR-SITE-02: a look that is hard to read is kept when the visitor confirmed it ({confirmed: true}) */
    public function test_hard_to_read_is_kept_when_confirmed()
    {
        $cookie = $this->sessionCookie(self::DAVE, 'Dave');
        $look = ['theme' => 'dark', 'text' => '#30363d'];                              // grey on the dark background
        [$status, , , $j] = $this->post('save', $look, $cookie);
        $this->assertSame([400, 'hard_to_read'], [$status, $j['error']]);
        foreach ([['confirmed' => 'yes'], ['confirmed' => 1], ['confirmed' => false]] as $not) {   // only exactly true counts
            [$status] = $this->post('save', $look + $not, $cookie);
            $this->assertSame(400, $status, json_encode($not));
        }
        [$status, , , $j] = $this->post('save', $look + ['confirmed' => true], $cookie);
        $this->assertSame(200, $status);
        $this->assertSame('#30363d', $j['look']['text']);
        $this->assertArrayNotHasKey('confirmed', $j['look']);                          // not stored
        [$status, , , $j] = $this->post('save', ['theme' => 'neon', 'confirmed' => true], $cookie);
        $this->assertSame([400, 'bad_look'], [$status, $j['error']]);                // confirming never skips the other checks
    }
}
