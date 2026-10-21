<?php
/**
 * Component integration tests: src/hakoo-users.inc.php against a real MariaDB.
 * Test level: component integration. Traceability: FR-GDPR-*, REG-GDPR-17, REG-GDPR-20.
 */
class UsersLibraryTest extends HakooTestCase
{
    /** TC-INT-001 | FR-USER-01 */
    public function test_ensure_user_creates_the_row_only_once()
    {
        $this->assertSame(0, $this->rows('hakoo_users', 'discord_id = ?', [self::DAVE]));
        hakoo_ensure_user($this->pdo, self::DAVE);
        hakoo_ensure_user($this->pdo, self::DAVE);
        $this->assertSame(1, $this->rows('hakoo_users', 'discord_id = ?', [self::DAVE]));
    }

    /** TC-INT-002 | FR-USER-02 */
    public function test_ensure_user_at_login_updates_last_login()
    {
        hakoo_ensure_user($this->pdo, self::CAROL, true);
        $s = $this->pdo->prepare('SELECT last_login_at FROM hakoo_users WHERE discord_id = ?');
        $s->execute([self::CAROL]);
        $this->assertGreaterThan(strtotime('2026-09-01 09:00:00'), strtotime($s->fetchColumn()));
    }

    /** TC-INT-003 | FR-GDPR-01 */
    public function test_collect_user_returns_every_registered_table()
    {
        $d = hakoo_collect_user($this->pdo, self::ALICE);
        $this->assertSame(array_keys(HAKOO_PERSONAL_TABLES), array_keys($d['tables']));
        $this->assertCount(2, $d['tables']['hakoo_links']);
        $this->assertCount(2, $d['tables']['hakoo_ign']);
        $this->assertCount(2, $d['tables']['hakoo_builds']);
        $this->assertCount(1, $d['tables']['hakoo_code_reports']);
        $this->assertCount(1, $d['tables']['hakoo_gomoku_profile']);
        $this->assertCount(1, $d['tables']['hakoo_gomoku_games']);
        $this->assertCount(2, $d['tables']['hakoo_saved_games']);
        foreach (['chess', 'othello', 'hnefatafl', 'stratego', 'backgammon', 'checkers', 'morris'] as $game) {
            $this->assertCount(1, $d['tables']["hakoo_{$game}_profile"]);
            $this->assertCount(1, $d['tables']["hakoo_{$game}_games"]);
        }
        $this->assertCount(1, $d['tables']['hakoo_nonogram_profile']);
        $this->assertSame([['day' => '2026-01-01', 'level' => 'hard', 'name' => 'Alice', 'created_at' => '2026-01-01 10:00:00',
                            'solved_at' => '2026-01-01 10:12:34', 'seconds' => 754]], $d['tables']['hakoo_nonogram_daily']);
        $this->assertCount(1, $d['tables']['hakoo_sudoku_profile']);
        $this->assertCount(1, $d['tables']['hakoo_sudoku_daily']);
        $this->assertCount(1, $d['tables']['hakoo_owldoku_profile']);
        $this->assertSame([['day' => '2026-01-03', 'level' => 'easy', 'name' => 'Alice', 'created_at' => '2026-01-03 10:00:00',
                            'solved_at' => '2026-01-03 10:01:40', 'seconds' => 100, 'mistakes' => 1]], $d['tables']['hakoo_owldoku_daily']);
        $this->assertSame(['500000011'], $d['wot']['account_ids']);
        $this->assertCount(1, $d['wot']['access']);
        $this->assertCount(2, $d['wot']['snapshots']);
        $this->assertNotNull($d['user']);
    }

    /** TC-INT-004 | FR-GDPR-02, REG-GDPR-17 */
    public function test_delete_user_removes_all_data_of_that_user()
    {
        hakoo_delete_user($this->pdo, self::ALICE);
        foreach (array_keys(HAKOO_PERSONAL_TABLES) as $t) {
            $this->assertSame(0, $this->rows($t, 'discord_id = ?', [self::ALICE]), "rows left in $t");
        }
        $this->assertSame(0, $this->rows('hakoo_users', 'discord_id = ?', [self::ALICE]));
        $this->assertSame(0, $this->rows('wot_access', 'account_id = 500000011'));
        $this->assertSame(0, $this->rows('wot_snapshots', 'account_id = 500000011'));
    }

    /** TC-INT-005 | FR-GDPR-03 (negative: nobody else is affected) */
    public function test_delete_user_leaves_other_people_untouched()
    {
        hakoo_delete_user($this->pdo, self::ALICE);
        $this->assertSame(1, $this->rows('hakoo_users', 'discord_id = ?', [self::BOB]));
        $this->assertSame(1, $this->rows('hakoo_links', 'discord_id = ?', [self::BOB]));
        $this->assertSame(1, $this->rows('hakoo_ign', 'discord_id = ?', [self::BOB]));
        $this->assertSame(1, $this->rows('hakoo_code_reports', 'discord_id = ?', [self::BOB]));
        $this->assertSame(1, $this->rows('hakoo_gomoku_profile', 'discord_id = ?', [self::BOB]));
        $this->assertSame(1, $this->rows('hakoo_chess_profile', 'discord_id = ?', [self::BOB]));
        $this->assertSame(1, $this->rows('hakoo_othello_profile', 'discord_id = ?', [self::BOB]));
        $this->assertSame(1, $this->rows('hakoo_hnefatafl_profile', 'discord_id = ?', [self::BOB]));
        $this->assertSame(1, $this->rows('hakoo_stratego_profile', 'discord_id = ?', [self::BOB]));
        $this->assertSame(1, $this->rows('hakoo_backgammon_profile', 'discord_id = ?', [self::BOB]));
        $this->assertSame(1, $this->rows('hakoo_checkers_profile', 'discord_id = ?', [self::BOB]));
        $this->assertSame(1, $this->rows('hakoo_morris_profile', 'discord_id = ?', [self::BOB]));
        $this->assertSame(1, $this->rows('hakoo_nonogram_profile', 'discord_id = ?', [self::BOB]));
        $this->assertSame(1, $this->rows('hakoo_sudoku_profile', 'discord_id = ?', [self::BOB]));
        $this->assertSame(1, $this->rows('hakoo_owldoku_profile', 'discord_id = ?', [self::BOB]));
        $this->assertSame(1, $this->rows('wot_snapshots', 'account_id = 500000012'));
        $this->assertSame(1, $this->rows('wot_snapshots', 'account_id = ?', [self::WOT_ONLY]));
        $this->assertSame(1, $this->rows('wot_access', 'account_id = ?', [self::WOT_ONLY]));
    }

    /** TC-INT-006 | FR-GDPR-05, REG-GDPR-5 (data minimisation of the log) */
    public function test_deletion_log_stores_a_hash_not_the_id()
    {
        hakoo_delete_user($this->pdo, self::ALICE);
        $hashes = $this->pdo->query('SELECT id_hash FROM hakoo_deletions')->fetchAll(PDO::FETCH_COLUMN);
        $this->assertSame([hakoo_id_hash(self::ALICE)], $hashes);
        $this->assertStringNotContainsString(self::ALICE, $hashes[0]);
    }

    /** TC-INT-007 | FR-USER-03 (database-level cascade) */
    public function test_deleting_the_user_row_cascades_to_every_table()
    {
        $this->pdo->prepare('DELETE FROM hakoo_users WHERE discord_id = ?')->execute([self::ALICE]);
        foreach (['hakoo_links', 'hakoo_ign', 'hakoo_builds', 'hakoo_code_reports'] as $t) {
            $this->assertSame(0, $this->rows($t, 'discord_id = ?', [self::ALICE]), "cascade missing on $t");
        }
    }

    /** TC-INT-008 | FR-USER-04 (negative: data without a user row is rejected) */
    public function test_member_data_without_a_user_row_is_rejected()
    {
        $this->expectException(PDOException::class);
        $this->expectExceptionCode('23000');   // integrity constraint violation
        $this->pdo->prepare("INSERT INTO hakoo_ign (discord_id, game, ign) VALUES (?, 'wot', 'Ghost')")->execute([self::DAVE]);
    }

    /** TC-INT-009 | FR-GDPR-04 */
    public function test_delete_user_cleans_the_json_files()
    {
        file_put_contents(HAKOO_LIB_DIR . '/wot_boxes.json', json_encode(['boxes' => ['500000011' => 7, '500000012' => 3], 'period' => '2026-09']));
        file_put_contents(HAKOO_LIB_DIR . '/discord_overrides.json', json_encode(['TestTanker_Alice' => 'alice', 'SomeoneElse' => 'x']));

        $counts = hakoo_delete_user($this->pdo, self::ALICE);

        $boxes = json_decode(file_get_contents(HAKOO_LIB_DIR . '/wot_boxes.json'), true);
        $over = json_decode(file_get_contents(HAKOO_LIB_DIR . '/discord_overrides.json'), true);
        $this->assertSame(['500000012' => 3], $boxes['boxes']);
        $this->assertSame('2026-09', $boxes['period']);
        $this->assertSame(['SomeoneElse' => 'x'], $over);
        $this->assertSame(1, $counts['wot_boxes.json']);
        $this->assertSame(1, $counts['discord_overrides.json']);
    }

    /** TC-INT-010 | FR-GDPR-06 (backup restore must not bring deleted people back) */
    public function test_reapply_deletions_after_restoring_a_backup()
    {
        hakoo_delete_user($this->pdo, self::ALICE);
        self::resetDatabase($this->pdo, false);                  // "restore" data, keep the deletion log
        $this->assertSame(1, $this->rows('hakoo_users', 'discord_id = ?', [self::ALICE]));

        $this->assertSame(1, hakoo_reapply_deletions($this->pdo));
        $this->assertSame(0, $this->rows('hakoo_users', 'discord_id = ?', [self::ALICE]));
        $this->assertSame(0, $this->rows('hakoo_ign', 'discord_id = ?', [self::ALICE]));
        $this->assertSame(1, $this->rows('hakoo_users', 'discord_id = ?', [self::BOB]));
    }

    /** TC-INT-011 | FR-GDPR-02 (deleting someone with no data still works) */
    public function test_delete_user_with_no_data()
    {
        $counts = hakoo_delete_user($this->pdo, self::CAROL);
        $this->assertSame(1, $counts['hakoo_users']);
        $this->assertSame(0, $counts['hakoo_links']);
    }
}
