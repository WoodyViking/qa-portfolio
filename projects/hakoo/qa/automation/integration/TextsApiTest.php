<?php
/**
 * System tests of src/texts.php (page texts from the database).
 * Traceability: US-09, FR-I18N-01..05, NFR-SEC-04.
 */
class TextsApiTest extends HakooTestCase
{
    /** TC-API-030 | FR-I18N-04 */
    public function test_languages_lists_the_enabled_languages_in_order()
    {
        [$status, , , $json] = $this->http('GET', 'texts.php?action=languages');
        $this->assertSame(200, $status);
        $this->assertSame(['en', 'sv', 'da', 'nl', 'de'], array_column($json['languages'], 'code'));
    }

    /** TC-API-031 | FR-I18N-01 */
    public function test_returns_texts_in_the_requested_language()
    {
        [, , , $json] = $this->http('GET', 'texts.php?lang=sv&page=roster');
        $this->assertSame('sv', $json['lang']);
        $this->assertSame('✏ Dina in-game-namn', $json['texts']['h_me']);
    }

    /** TC-API-032 | FR-I18N-02: missing translation falls back to English */
    public function test_missing_translation_falls_back_to_english()
    {
        [, , , $json] = $this->http('GET', 'texts.php?lang=de&page=roster');
        $this->assertSame('de', $json['lang']);
        $this->assertSame('✏ Your in-game names', $json['texts']['h_me']);
    }

    /** TC-API-033 | FR-I18N-03 (EP invalid: unknown language) */
    public function test_unknown_language_falls_back_to_english()
    {
        [, , , $json] = $this->http('GET', 'texts.php?lang=xx&page=roster');
        $this->assertSame('en', $json['lang']);
    }

    /** TC-API-034 | NFR-SEC-04: SQL injection through the language parameter */
    public function test_sql_injection_in_lang_is_harmless()
    {
        [$status, , , $json] = $this->http('GET', 'texts.php?page=roster&lang=' . rawurlencode("en`; DROP TABLE hakoo_texts; -- "));
        $this->assertSame(200, $status);
        $this->assertSame('en', $json['lang']);
        $this->assertGreaterThan(600, $this->rows('hakoo_texts', '1 = 1'));
    }

    /** TC-API-035 | FR-I18N-05 (EP invalid: page name with path characters) */
    public function test_invalid_page_name_is_rejected()
    {
        [$status] = $this->http('GET', 'texts.php?lang=en&page=' . rawurlencode('../config'));
        $this->assertSame(400, $status);
    }

    /** TC-API-036 | NFR-PERF-02: unchanged texts are answered with 304 Not Modified */
    public function test_etag_gives_304_when_nothing_changed()
    {
        [, $headers] = $this->http('GET', 'texts.php?lang=sv&page=roster');
        $etag = null;
        foreach ($headers as $h) if (stripos($h, 'ETag:') === 0) $etag = trim(substr($h, 5));
        $this->assertNotNull($etag);
        [$status] = $this->http('GET', 'texts.php?lang=sv&page=roster', null, null, ['If-None-Match: ' . $etag]);
        $this->assertSame(304, $status);
    }

    /** TC-API-161 | FR-I18N-11: the start page, HAKOO Games and the board games have their texts (shared ones via "common") */
    public function test_start_page_hub_and_game_pages_have_their_texts()
    {
        [, , , $start] = $this->http('GET', 'texts.php?lang=sv&page=index');
        $this->assertSame('Vart vill du gå?', $start['texts']['sub']);
        $this->assertSame('Where do you want to go?', $this->english('index.sub'));

        [, , , $hub] = $this->http('GET', 'texts.php?lang=de&page=hakoo-games');
        $this->assertSame('🕶 Anonym', $hub['texts']['b_anon']);
        $this->assertSame('👤 Angemeldet als {name}', $hub['texts']['b_in']);

        foreach (['gomoku', 'chess', 'othello', 'hnefatafl', 'stratego', 'checkers', 'morris'] as $game) {
            [, , , $g] = $this->http('GET', "texts.php?lang=sv&page=games/$game");
            $this->assertSame('🎮 Alla spel', $g['texts']['nav_all_games'], $game);
            $this->assertSame('Spela mot datorn', $g['texts']['go_computer'], $game);
            $this->assertArrayNotHasKey('sub', $g['texts'], 'keys of the start page do not leak into a game page');
        }
    }

    /**
     * TC-API-284 | FR-I18N-13: a game page translated by its English gets its own texts and the shared page "games"
     * (keys "~" + the hash of the English), and the English of the texts with {placeholders}; only "games" may be shared.
     */
    public function test_a_game_page_gets_the_shared_game_texts_by_their_english()
    {
        [$status, , , $j] = $this->http('GET', 'texts.php?lang=de&page=games/nonogram&shared=games');
        $this->assertSame(200, $status);
        $this->assertSame('Neu anfangen', $j['texts']['~24299867'], 'games: "Start over"');
        $this->assertSame('{who} hat gewonnen', $j['texts']['~8d8c3d95'], 'games: "{who} won"');
        $this->assertSame('{who} won', $j['src']['~8d8c3d95'], 'the English of a text with a placeholder');
        $this->assertArrayNotHasKey('~24299867', $j['src'], 'no English for a plain text');
        $this->assertSame('🎮 Alle Spiele', $j['texts']['nav_all_games'], 'common still comes');

        [, , , $plain] = $this->http('GET', 'texts.php?lang=de&page=games/nonogram');
        $this->assertArrayNotHasKey('~24299867', $plain['texts'], 'without shared=games, no game texts');
        [$status, , , $other] = $this->http('GET', 'texts.php?lang=de&page=roster&shared=' . rawurlencode('roster'));
        $this->assertSame(200, $status);
        $this->assertArrayNotHasKey('src', $other, 'another page cannot be asked for as shared');
    }

    private function english($key)
    {
        $s = $this->pdo->prepare('SELECT en FROM hakoo_texts WHERE text_key = ?');
        $s->execute([$key]);
        return $s->fetchColumn();
    }
}
