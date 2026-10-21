<?php
/**
 * System tests of the board-game settings (the move speed and the dice: how to roll, how much shaking):
 * ?action=settings and ?action=me of the board games (board-game-api.inc.php), stored in
 * hakoo_board_settings for all games together.
 * Test design: equivalence partitions and boundaries of moveMs (qa/test-design/equivalence-partitioning-bva.md);
 * diceRoll / diceShake: each allowed value, and invalid values (unknown, wrong case, wrong type, injection).
 * Traceability: FR-GAMES-11, FR-GAMES-13, REG-GDPR-15, REG-GDPR-17, NFR-SEC-01.
 */
class BoardSettingsApiTest extends HakooTestCase
{
    private function settings($body, ?string $cookie, string $game = 'othello'): array
    {
        return $this->http('POST', "$game.php?action=settings", $body, $cookie);
    }

    private function me(string $cookie, string $game = 'othello'): array
    {
        return $this->http('GET', "$game.php?action=me", null, $cookie)[3];
    }

    /** TC-API-165 | FR-GAMES-11, NFR-SEC-01 (negative): saving needs a login and a POST */
    public function test_settings_need_login_and_post()
    {
        [$status, , , $j] = $this->settings(['moveMs' => 800], null);
        $this->assertSame([401, 'not_logged_in'], [$status, $j['error']]);
        [$status, , , $j] = $this->http('GET', 'othello.php?action=settings&moveMs=800', null, $this->sessionCookie(self::CAROL));
        $this->assertSame([405, 'post_required'], [$status, $j['error']]);
        $this->assertSame(0, $this->rows('hakoo_board_settings', '1'));
    }

    /** TC-API-166 | FR-GAMES-11: a member who never chose a speed gets null (the page uses 600 ms) */
    public function test_no_speed_until_chosen()
    {
        $j = $this->me($this->sessionCookie(self::CAROL));
        $this->assertTrue($j['loggedIn']);
        $this->assertArrayHasKey('moveMs', $j);
        $this->assertNull($j['moveMs']);
        $this->assertArrayHasKey('diceRoll', $j);
        $this->assertNull($j['diceRoll']);
        $this->assertArrayHasKey('diceShake', $j);
        $this->assertNull($j['diceShake']);
    }

    /** TC-API-177 | FR-GAMES-13: the dice settings are saved, hold in every board game, and only what is sent changes */
    public function test_dice_settings_are_saved_one_at_a_time()
    {
        $cookie = $this->sessionCookie(self::DAVE, 'Dave');
        $this->settings(['moveMs' => 900], $cookie);
        foreach (['button', 'cup', 'motion'] as $roll) {
            [$status, , , $j] = $this->settings(['diceRoll' => $roll], $cookie);
            $this->assertSame(200, $status);
            $this->assertSame($roll, $j['diceRoll']);
        }
        foreach (['easy', 'hard', 'normal'] as $shake) {
            [$status, , , $j] = $this->settings(['diceShake' => $shake], $cookie, 'chess');
            $this->assertSame(200, $status);
            $this->assertSame($shake, $j['diceShake']);
        }
        foreach (['gomoku', 'chess', 'othello', 'hnefatafl', 'stratego', 'checkers', 'morris'] as $game) {
            $j = $this->me($cookie, $game);
            $this->assertSame([900, 'motion', 'normal'], [$j['moveMs'], $j['diceRoll'], $j['diceShake']], $game);
        }
        [, , , $j] = $this->settings(['moveMs' => 300, 'diceRoll' => 'button', 'diceShake' => 'easy'], $cookie);
        $this->assertSame([300, 'button', 'easy'], [$j['moveMs'], $j['diceRoll'], $j['diceShake']]);
        $this->assertSame(1, $this->rows('hakoo_board_settings', 'discord_id = ?', [self::DAVE]));
    }

    /** TC-API-178 | FR-GAMES-11, FR-GAMES-13: choosing only a dice setting leaves the move speed not chosen (null, the page uses 600 ms) */
    public function test_dice_only_leaves_speed_unchosen()
    {
        $cookie = $this->sessionCookie(self::CAROL);
        [, , , $j] = $this->settings(['diceShake' => 'hard'], $cookie);
        $this->assertSame([null, null, 'hard'], [$j['moveMs'], $j['diceRoll'], $j['diceShake']]);
    }

    /** TC-API-179 | FR-GAMES-13, NFR-SEC-01 (negative): unknown dice values are refused, and one bad value saves nothing */
    public function test_invalid_dice_settings_are_refused()
    {
        $cookie = $this->sessionCookie(self::CAROL);
        foreach ([['diceRoll' => 'throw'], ['diceRoll' => 'Cup'], ['diceRoll' => ''], ['diceRoll' => null], ['diceRoll' => 1],
                  ['diceRoll' => ['cup']], ['diceShake' => 'medium'], ['diceShake' => "normal'; DROP TABLE hakoo_board_settings; --"],
                  ['diceRoll' => 'cup', 'diceShake' => 'wild'], ['diceRoll' => 'cup', 'moveMs' => 5000],
                  ['dice_roll' => 'cup']] as $body) {
            [$status, , , $j] = $this->settings($body, $cookie);
            $this->assertSame([400, 'bad_settings'], [$status, $j['error']], json_encode($body));
        }
        $this->assertSame(0, $this->rows('hakoo_board_settings', '1'));
    }

    /** TC-API-167 | FR-GAMES-11: the boundaries 100 and 2000 are kept, and one choice holds in every board game */
    public function test_valid_speeds_are_saved_for_every_game()
    {
        $cookie = $this->sessionCookie(self::DAVE, 'Dave');          // no hakoo_users row yet: it is created
        foreach ([100, 2000, 700] as $ms) {
            [$status, , , $j] = $this->settings(['moveMs' => $ms], $cookie);
            $this->assertSame(200, $status);
            $this->assertSame($ms, $j['moveMs']);
        }
        foreach (['gomoku', 'chess', 'othello', 'hnefatafl', 'stratego', 'checkers', 'morris'] as $game) {
            $this->assertSame(700, $this->me($cookie, $game)['moveMs'], $game);
        }
        $this->assertSame(1, $this->rows('hakoo_board_settings', 'discord_id = ? AND move_ms = 700', [self::DAVE]));
        $this->assertSame(1, $this->rows('hakoo_users', 'discord_id = ?', [self::DAVE]));
    }

    /** TC-API-168 | FR-GAMES-11 (negative): outside 100..2000, not a whole number, missing or an injection attempt is refused */
    public function test_invalid_speeds_are_refused()
    {
        $cookie = $this->sessionCookie(self::CAROL);
        foreach ([['moveMs' => 99], ['moveMs' => 2001], ['moveMs' => 0], ['moveMs' => -600], ['moveMs' => 600.5],
                  ['moveMs' => '600'], ['moveMs' => null], ['moveMs' => "600; DROP TABLE hakoo_board_settings"], []] as $body) {
            [$status, , , $j] = $this->settings($body, $cookie);
            $this->assertSame([400, 'bad_settings'], [$status, $j['error']], json_encode($body));
        }
        $this->assertSame(0, $this->rows('hakoo_board_settings', '1'));
    }

    /**
     * TC-API-169 | REG-GDPR-15, REG-GDPR-17: the speed is in "Download my data" and goes with "Delete all my data".
     * Dave (not in the fixture) is deleted, not Alice: the database stays as the E2E tests expect it after the last test.
     */
    public function test_speed_is_exported_and_deleted()
    {
        $cookie = $this->sessionCookie(self::DAVE, 'Dave');
        $this->settings(['moveMs' => 1200, 'diceRoll' => 'motion'], $cookie);
        $d = hakoo_collect_user($this->pdo, self::DAVE);
        $this->assertSame('1200', (string)$d['tables']['hakoo_board_settings'][0]['move_ms']);
        $this->assertSame('motion', $d['tables']['hakoo_board_settings'][0]['dice_roll']);
        [$status] = $this->http('POST', 'mydata.php?action=delete', ['confirm' => 'DELETE'], $cookie);
        $this->assertSame(200, $status);
        $this->assertSame(0, $this->rows('hakoo_board_settings', 'discord_id = ?', [self::DAVE]));
        $this->assertSame(0, $this->rows('hakoo_users', 'discord_id = ?', [self::DAVE]));
    }
}
