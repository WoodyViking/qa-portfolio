<?php
/**
 * System tests of src/texts-admin.php (the translation page).
 * Traceability: US-11, FR-TEXTS-01..09, NFR-SEC-02, NFR-SEC-04, REG-GDPR-17.
 */
class TextsAdminApiTest extends HakooTestCase
{
    const KEY = 'roster.h_me';      // English and Swedish filled, German empty in the seed
    private $before;

    protected function setUp(): void
    {
        parent::setUp();
        $s = $this->pdo->prepare('SELECT en, sv, de, updated_by FROM hakoo_texts WHERE text_key = ?');
        $s->execute([self::KEY]);
        $this->before = $s->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * The tests change one site text; put it back so other tests see the seed. The fixture is
     * reloaded too: the last test deletes Alice, and the E2E tests need her after this suite.
     */
    protected function tearDown(): void
    {
        $this->pdo->prepare('UPDATE hakoo_texts SET en = ?, sv = ?, de = ?, updated_by = ? WHERE text_key = ?')
            ->execute([$this->before['en'], $this->before['sv'], $this->before['de'], $this->before['updated_by'], self::KEY]);
        self::resetDatabase($this->pdo, true);
    }

    private function text($lang)
    {
        $s = $this->pdo->prepare("SELECT `$lang` FROM hakoo_texts WHERE text_key = ?");
        $s->execute([self::KEY]);
        return $s->fetchColumn();
    }
    private function post($action, $body, $who, $name = 'Tester')
    {
        return $this->http('POST', 'texts-admin.php?action=' . $action, $body, $this->sessionCookie($who, $name));
    }
    /** Bob asks for the language role and Alice (admin) gives it. */
    private function makeBobTranslator()
    {
        $this->post('role_request', [], self::BOB, 'Bob');
        $this->post('role_set', ['discord_id' => self::BOB, 'grant' => true], self::ALICE);
    }

    /** TC-API-146 | FR-TEXTS-01: the page is for logged-in members only */
    public function test_state_needs_a_login()
    {
        [$status, , , $json] = $this->http('GET', 'texts-admin.php?action=state');
        $this->assertSame(401, $status);
        $this->assertSame('not_logged_in', $json['error']);
        $this->assertArrayNotHasKey('texts', $json);
    }

    /** TC-API-147 | FR-TEXTS-01, FR-TEXTS-02: a member reads every text in every language but has no role */
    public function test_member_reads_all_texts_and_has_no_role()
    {
        [$status, , , $json] = $this->http('GET', 'texts-admin.php?action=state', null, $this->sessionCookie(self::CAROL, 'Carol'));
        $this->assertSame(200, $status);
        $this->assertFalse($json['me']['admin']);
        $this->assertSame('none', $json['me']['role']);
        $this->assertSame(['en', 'sv', 'da', 'nl', 'de'], array_column($json['languages'], 'code'));
        $this->assertGreaterThan(600, count($json['texts']));
        $this->assertSame(['en', 'sv', 'da', 'nl', 'de'], array_keys($json['texts'][0]['t']));
        $this->assertSame([], $json['roles']);
    }

    /** TC-API-148 | FR-TEXTS-03: a member asks for the role, the admin gives it, the admin can remove it */
    public function test_role_is_requested_then_given_then_removed()
    {
        [, , , $json] = $this->post('role_request', [], self::CAROL, 'Carol');
        $this->assertSame('requested', $json['role']);
        [, , , $state] = $this->http('GET', 'texts-admin.php?action=state', null, $this->sessionCookie(self::ALICE));
        $this->assertSame([['discord_id' => self::CAROL, 'name' => 'Carol', 'status' => 'requested']],
            array_map(function ($r) { unset($r['requested_at']); return $r; }, $state['roles']));

        [, , , $json] = $this->post('role_set', ['discord_id' => self::CAROL, 'grant' => true], self::ALICE);
        $this->assertSame('granted', $json['role']);
        [, , , $json] = $this->post('role_set', ['discord_id' => self::CAROL, 'grant' => false], self::ALICE);
        $this->assertSame('none', $json['role']);
        $this->assertSame(0, $this->rows('hakoo_text_roles', 'discord_id = ?', [self::CAROL]));
    }

    /** TC-API-149 | FR-TEXTS-03 (negative): only the admin gives the role, and only to someone who asked */
    public function test_only_the_admin_gives_the_role()
    {
        $this->post('role_request', [], self::BOB, 'Bob');
        [$status, , , $json] = $this->post('role_set', ['discord_id' => self::BOB, 'grant' => true], self::BOB);
        $this->assertSame(403, $status);
        $this->assertSame('admin_only', $json['error']);
        $this->assertSame(1, $this->rows('hakoo_text_roles', "discord_id = ? AND status = 'requested'", [self::BOB]));

        [$status] = $this->post('role_set', ['discord_id' => self::CAROL, 'grant' => true], self::ALICE);
        $this->assertSame(404, $status);
    }

    /** TC-API-150 | FR-TEXTS-04 (negative): without the role a member cannot suggest */
    public function test_suggesting_needs_the_role()
    {
        $this->post('role_request', [], self::BOB, 'Bob');          // asked, not given yet
        [$status, , , $json] = $this->post('suggest', ['key' => self::KEY, 'lang' => 'de', 'text' => 'Deine Namen'], self::BOB);
        $this->assertSame(403, $status);
        $this->assertSame('role_required', $json['error']);
        $this->assertSame(0, $this->rows('hakoo_text_suggestions', '1 = 1'));
    }

    /** TC-API-151 | FR-TEXTS-04, FR-TEXTS-05: a suggestion waits; the site text does not change */
    public function test_a_suggestion_waits_and_changes_nothing()
    {
        $this->makeBobTranslator();
        [$status] = $this->post('suggest', ['key' => self::KEY, 'lang' => 'de', 'text' => 'Erster Versuch'], self::BOB, 'Bob');
        $this->assertSame(200, $status);
        $this->post('suggest', ['key' => self::KEY, 'lang' => 'de', 'text' => 'Deine Ingame-Namen'], self::BOB, 'Bob');

        $this->assertSame(1, $this->rows('hakoo_text_suggestions', "status = 'pending'"), 'a new suggestion replaces the old one');
        $this->assertEmpty($this->text('de'));
        [, , , $site] = $this->http('GET', 'texts.php?lang=de&page=roster');
        $this->assertSame($this->before['en'], $site['texts']['h_me']);

        [, , , $mine] = $this->http('GET', 'texts-admin.php?action=state', null, $this->sessionCookie(self::BOB, 'Bob'));
        $this->assertSame('Deine Ingame-Namen', $mine['suggestions'][0]['text']);
        $this->assertTrue($mine['suggestions'][0]['mine']);
        [, , , $other] = $this->http('GET', 'texts-admin.php?action=state', null, $this->sessionCookie(self::CAROL));
        $this->assertSame([], $other['suggestions'], 'another member does not see it');
    }

    /** TC-API-152 | FR-TEXTS-06: English is changed by the admin only */
    public function test_a_translator_cannot_suggest_english()
    {
        $this->makeBobTranslator();
        [$status, , , $json] = $this->post('suggest', ['key' => self::KEY, 'lang' => 'en', 'text' => 'Hacked'], self::BOB);
        $this->assertSame(403, $status);
        $this->assertSame('english_admin_only', $json['error']);
        $this->assertSame($this->before['en'], $this->text('en'));
    }

    /** TC-API-153 | FR-TEXTS-05: approving writes the text and the site shows it */
    public function test_admin_approves_a_suggestion()
    {
        $this->makeBobTranslator();
        [, , , $json] = $this->post('suggest', ['key' => self::KEY, 'lang' => 'de', 'text' => 'Deine Ingame-Namen'], self::BOB, 'Bob');
        [$status] = $this->post('decide', ['id' => $json['id'], 'approve' => true], self::BOB);
        $this->assertSame(403, $status, 'a translator cannot approve their own suggestion');

        [$status] = $this->post('decide', ['id' => $json['id'], 'approve' => true], self::ALICE);
        $this->assertSame(200, $status);
        $this->assertSame('Deine Ingame-Namen', $this->text('de'));
        $this->assertSame(1, $this->rows('hakoo_text_suggestions', "status = 'approved' AND decided_by = ?", [self::ALICE]));
        [, , , $site] = $this->http('GET', 'texts.php?lang=de&page=roster');
        $this->assertSame('Deine Ingame-Namen', $site['texts']['h_me']);
    }

    /** TC-API-154 | FR-TEXTS-05: rejecting closes the suggestion and changes nothing */
    public function test_admin_rejects_a_suggestion()
    {
        $this->makeBobTranslator();
        [, , , $json] = $this->post('suggest', ['key' => self::KEY, 'lang' => 'sv', 'text' => 'Fel text'], self::BOB, 'Bob');
        [$status] = $this->post('decide', ['id' => $json['id'], 'approve' => false], self::ALICE);
        $this->assertSame(200, $status);
        $this->assertSame($this->before['sv'], $this->text('sv'));
        $this->assertSame(1, $this->rows('hakoo_text_suggestions', "status = 'rejected'"));
        [$status] = $this->post('decide', ['id' => $json['id'], 'approve' => true], self::ALICE);
        $this->assertSame(404, $status, 'a closed suggestion cannot be decided again');
    }

    /** TC-API-155 | FR-TEXTS-07: the admin changes any text directly; nobody else can */
    public function test_only_the_admin_saves_directly()
    {
        $this->makeBobTranslator();
        [$status, , , $json] = $this->post('save', ['key' => self::KEY, 'lang' => 'de', 'text' => 'Direkt'], self::BOB);
        $this->assertSame(403, $status);
        $this->assertSame('admin_only', $json['error']);
        $this->assertEmpty($this->text('de'));

        [$status] = $this->post('save', ['key' => self::KEY, 'lang' => 'en', 'text' => '✏ Your names in the games'], self::ALICE);
        $this->assertSame(200, $status);
        $this->assertSame('✏ Your names in the games', $this->text('en'));
        $this->assertSame(1, $this->rows('hakoo_texts', 'text_key = ? AND updated_by = ?', [self::KEY, self::ALICE]));
    }

    /** TC-API-156 | FR-TEXTS-07 (BVA: empty text): English cannot be emptied, a translation can */
    public function test_empty_english_is_rejected_but_a_translation_can_be_cleared()
    {
        [$status, , , $json] = $this->post('save', ['key' => self::KEY, 'lang' => 'en', 'text' => '  '], self::ALICE);
        $this->assertSame(400, $status);
        $this->assertSame('en_required', $json['error']);
        $this->assertSame($this->before['en'], $this->text('en'));

        [$status] = $this->post('save', ['key' => self::KEY, 'lang' => 'sv', 'text' => ''], self::ALICE);
        $this->assertSame(200, $status);
        $this->assertNull($this->text('sv'));
    }

    /** TC-API-157 | NFR-SEC-04: the language is a column name and never comes straight from the request */
    public function test_sql_injection_in_lang_and_unknown_key_are_rejected()
    {
        [$status, , , $json] = $this->post('save', ['key' => self::KEY, 'lang' => "en` = 'x', `sv", 'text' => 'x'], self::ALICE);
        $this->assertSame(400, $status);
        $this->assertSame('bad_lang', $json['error']);
        [$status] = $this->post('save', ['key' => self::KEY, 'lang' => 'note', 'text' => 'x'], self::ALICE);
        $this->assertSame(400, $status, 'a real column that is not a language');
        [$status] = $this->post('save', ['key' => 'no.such_key', 'lang' => 'sv', 'text' => 'x'], self::ALICE);
        $this->assertSame(404, $status);
        $this->assertSame($this->before['en'], $this->text('en'));
        $this->assertSame($this->before['sv'], $this->text('sv'));
    }

    /** TC-API-158 | NFR-SEC-02: changes need a POST with a JSON body (a form on another site cannot send one) */
    public function test_changes_need_a_json_post()
    {
        $cookie = $this->sessionCookie(self::ALICE);
        [$status] = $this->http('GET', 'texts-admin.php?action=save&key=' . self::KEY . '&lang=sv&text=x', null, $cookie);
        $this->assertSame(400, $status);
        [$status] = $this->http('POST', 'texts-admin.php?action=save', null, $cookie,
            ['Content-Type: application/x-www-form-urlencoded']);
        $this->assertSame(400, $status);
        $this->assertSame($this->before['sv'], $this->text('sv'));
    }

    /** TC-API-159 | FR-TEXTS-08: a translator takes back their own suggestion, not someone else's */
    public function test_withdraw_only_my_own_suggestion()
    {
        $this->makeBobTranslator();
        [, , , $json] = $this->post('suggest', ['key' => self::KEY, 'lang' => 'de', 'text' => 'Deine Namen'], self::BOB, 'Bob');
        [$status] = $this->post('withdraw', ['id' => $json['id']], self::CAROL);
        $this->assertSame(404, $status);
        [$status] = $this->post('withdraw', ['id' => $json['id']], self::BOB);
        $this->assertSame(200, $status);
        $this->assertSame(0, $this->rows('hakoo_text_suggestions', '1 = 1'));
    }

    /** TC-API-160 | FR-TEXTS-09, REG-GDPR-17: deleting a member removes role and suggestions; approved texts stay */
    public function test_deleting_a_member_removes_role_and_suggestions()
    {
        $this->makeBobTranslator();
        [, , , $a] = $this->post('suggest', ['key' => self::KEY, 'lang' => 'de', 'text' => 'Deine Ingame-Namen'], self::BOB, 'Bob');
        $this->post('decide', ['id' => $a['id'], 'approve' => true], self::ALICE);
        $this->post('suggest', ['key' => self::KEY, 'lang' => 'sv', 'text' => 'Dina namn'], self::BOB, 'Bob');

        $data = hakoo_collect_user($this->pdo, self::BOB);
        $this->assertCount(1, $data['tables']['hakoo_text_roles']);
        $this->assertCount(2, $data['tables']['hakoo_text_suggestions']);

        hakoo_delete_user($this->pdo, self::BOB);
        $this->assertSame(0, $this->rows('hakoo_text_roles', '1 = 1'));
        $this->assertSame(0, $this->rows('hakoo_text_suggestions', '1 = 1'));
        $this->assertSame('Deine Ingame-Namen', $this->text('de'));

        hakoo_delete_user($this->pdo, self::ALICE);
        $this->assertSame(0, $this->rows('hakoo_texts', 'updated_by = ?', [self::ALICE]), "the admin's id is cleared from the texts");
    }
}
