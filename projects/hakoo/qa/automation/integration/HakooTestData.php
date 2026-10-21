<?php
/**
 * TEST ONLY: reloads the fictional test data in docker/db/init/90-test-data.sql
 * (Alice, Bob, Carol and a WoT-only player).
 * Used by HakooTestCase before every PHPUnit test and by qa/tools/reset-test-data.php
 * before the Playwright run, so neither suite depends on what the other left behind.
 * No PHPUnit dependency, so plain PHP scripts can load it.
 */
class HakooTestData
{
    const DATA_TABLES = ['hakoo_code_reports', 'hakoo_builds', 'hakoo_gomoku_games', 'hakoo_gomoku_profile',
                         'hakoo_chess_games', 'hakoo_chess_profile', 'hakoo_othello_games', 'hakoo_othello_profile',
                         'hakoo_hnefatafl_games', 'hakoo_hnefatafl_profile',
                         'hakoo_stratego_games', 'hakoo_stratego_profile', 'hakoo_backgammon_games', 'hakoo_backgammon_profile', 'hakoo_checkers_games', 'hakoo_checkers_profile', 'hakoo_morris_games', 'hakoo_morris_profile', 'hakoo_nonogram_daily', 'hakoo_nonogram_profile', 'hakoo_sudoku_daily', 'hakoo_sudoku_profile', 'hakoo_owldoku_daily', 'hakoo_owldoku_profile', 'hakoo_puzzle_saves', 'hakoo_ludo_games', 'hakoo_yatzy_games', 'hakoo_runborg_games', 'hakoo_runborg_wins', 'hakoo_runborg_profile', 'hakoo_challenges', 'hakoo_saved_games', 'hakoo_reminders', 'hakoo_reminder_settings', 'hakoo_blockdoku_games', 'hakoo_blockdoku_podium', 'hakoo_berserk_runs', 'hakoo_berserk_podium', 'hakoo_berserk_profile', 'hakoo_board_settings', 'hakoo_appearance', 'hakoo_text_suggestions', 'hakoo_text_roles', 'hakoo_roles', 'hakoo_member_names', 'hakoo_avatar', 'hakoo_play_stats', 'hakoo_ign', 'hakoo_links', 'hakoo_users', 'wot_access', 'wot_snapshots'];

    const FIXTURE = '/fixtures/90-test-data.sql';

    /** Reload the fixture. $clearDeletionLog=false simulates restoring an old backup. */
    public static function reset(PDO $pdo, $clearDeletionLog)
    {
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
        foreach (self::DATA_TABLES as $t) $pdo->exec("TRUNCATE TABLE $t");
        if ($clearDeletionLog) $pdo->exec('TRUNCATE TABLE hakoo_deletions');
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
        foreach (self::statements(self::FIXTURE) as $sql) $pdo->exec($sql);
    }

    /** Split an SQL file into single statements (the fixture has no ';' inside values). */
    private static function statements($file)
    {
        $lines = array_filter(file($file), function ($l) { return strpos(ltrim($l), '--') !== 0; });
        return array_filter(array_map('trim', preg_split('/;\s*$/m', implode('', $lines))));
    }
}
