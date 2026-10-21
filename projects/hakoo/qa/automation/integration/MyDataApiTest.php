<?php
/**
 * System tests of the GDPR self-service endpoint (src/mydata.php) over HTTP.
 * Traceability: US-06, US-07, FR-GDPR-01..02, REG-GDPR-15/17/20, NFR-SEC-01.
 */
class MyDataApiTest extends HakooTestCase
{
    /** TC-API-001 | NFR-SEC-01 (negative) */
    public function test_export_requires_login()
    {
        [$status, , , $json] = $this->http('GET', 'mydata.php?action=export');
        $this->assertSame(401, $status);
        $this->assertSame('not_logged_in', $json['error']);
    }

    /** TC-API-002 | NFR-SEC-02 (negative: a cookie signed with another secret is rejected) */
    public function test_forged_session_cookie_is_rejected()
    {
        $forged = $this->sessionCookie(self::ALICE, 'Alice', 'not-the-real-secret');
        [$status] = $this->http('GET', 'mydata.php?action=export', null, $forged);
        $this->assertSame(401, $status);
    }

    /** TC-API-003 | FR-GDPR-01, REG-GDPR-15, REG-GDPR-20 */
    public function test_export_contains_every_kind_of_data()
    {
        [$status, $headers, , $json] = $this->http('GET', 'mydata.php?action=export', null, $this->sessionCookie(self::ALICE, 'Alice'));
        $this->assertSame(200, $status);
        $this->assertStringContainsString('attachment', implode("\n", $headers));
        foreach (['discord_session', 'account', 'linked_accounts', 'in_game_names', 'warframe_builds',
                  'loot_code_reports', 'clan_access', 'wot_stat_snapshots', 'other_records'] as $key) {
            $this->assertArrayHasKey($key, $json, "export is missing '$key'");
        }
        $this->assertCount(2, $json['linked_accounts']);
        $this->assertCount(2, $json['in_game_names']);
        $this->assertCount(2, $json['warframe_builds']);
        $this->assertCount(1, $json['loot_code_reports']);
        $this->assertCount(2, $json['wot_stat_snapshots']);
    }

    /**
     * TC-API-188 | FR-GDPR-01, REG-GDPR-15 (DEF: the export left out every game and settings table):
     * every table in the personal-data register is in the export, and a dice setting shows up there.
     */
    public function test_export_has_every_table_of_the_register()
    {
        $cookie = $this->sessionCookie(self::ALICE, 'Alice');
        $this->http('POST', 'othello.php?action=settings', ['diceRoll' => 'motion', 'diceShake' => 'hard'], $cookie);
        $json = $this->http('GET', 'mydata.php?action=export', null, $cookie)[3];
        $shown = ['hakoo_links', 'hakoo_ign', 'hakoo_builds', 'hakoo_code_reports'];   // their own sections
        foreach (array_keys(hakoo_personal_tables($this->pdo)) as $table) {
            if (in_array($table, $shown, true)) continue;
            $this->assertArrayHasKey($table, $json['games_and_settings'], "export is missing table $table");
        }
        $this->assertSame(['motion', 'hard'], [$json['games_and_settings']['hakoo_board_settings'][0]['dice_roll'],
            $json['games_and_settings']['hakoo_board_settings'][0]['dice_shake']]);
    }

    /** TC-API-004 | FR-GDPR-07 (negative: no confirmation, nothing deleted) */
    public function test_delete_without_confirmation_deletes_nothing()
    {
        [$status, , , $json] = $this->http('POST', 'mydata.php?action=delete', ['confirm' => 'yes'], $this->sessionCookie(self::ALICE));
        $this->assertSame(400, $status);
        $this->assertSame('confirm_required', $json['error']);
        $this->assertSame(2, $this->rows('hakoo_ign', 'discord_id = ?', [self::ALICE]));
    }

    /** TC-API-005 | FR-GDPR-02, FR-GDPR-08, REG-GDPR-17 */
    public function test_delete_with_confirmation_erases_everything_and_logs_out()
    {
        [$status, $headers, , $json] = $this->http('POST', 'mydata.php?action=delete', ['confirm' => 'DELETE'], $this->sessionCookie(self::ALICE));
        $this->assertSame(200, $status);
        $this->assertSame('ok', $json['status']);
        $this->assertMatchesRegularExpression('/Set-Cookie:\s*hakoo_auth=deleted|Set-Cookie:\s*hakoo_auth=;/i', implode("\n", $headers));
        foreach (['hakoo_users', 'hakoo_links', 'hakoo_ign', 'hakoo_builds', 'hakoo_code_reports'] as $t) {
            $this->assertSame(0, $this->rows($t, 'discord_id = ?', [self::ALICE]), "rows left in $t");
        }
        $this->assertSame(0, $this->rows('wot_snapshots', 'account_id = 500000011'));
    }

    /** TC-API-006 | FR-GDPR-07 (negative: GET must not delete) */
    public function test_delete_via_get_is_refused()
    {
        [$status] = $this->http('GET', 'mydata.php?action=delete', null, $this->sessionCookie(self::ALICE));
        $this->assertSame(400, $status);
        $this->assertSame(1, $this->rows('hakoo_users', 'discord_id = ?', [self::ALICE]));
    }
}
