<?php
/**
 * System tests of src/roster.php, designed with EQUIVALENCE PARTITIONING and
 * BOUNDARY VALUE ANALYSIS (see qa/test-design/equivalence-partitioning-bva.md).
 * Traceability: US-05, FR-ROSTER-01..06.
 */
class RosterApiTest extends HakooTestCase
{
    private function set($who, array $body)
    {
        return $this->http('POST', 'roster.php?action=set', $body, $this->sessionCookie($who, 'Tester'));
    }

    private function stored($who, $game)
    {
        $s = $this->pdo->prepare('SELECT ign, note, mr FROM hakoo_ign WHERE discord_id = ? AND game = ?');
        $s->execute([$who, $game]);
        return $s->fetch(PDO::FETCH_ASSOC);
    }

    /** TC-API-010 | FR-ROSTER-01 (negative) */
    public function test_roster_requires_login()
    {
        [$status] = $this->http('GET', 'roster.php?action=list');
        $this->assertSame(401, $status);
    }

    /** TC-API-011..014 | FR-ROSTER-02: in-game name length 1..64 (BVA, 2-value) */
    public function ignLengths()
    {
        return [
            'EP invalid: empty (0)'      => ['',                    'err'],
            'BVA valid: min (1)'         => ['A',                   'ok'],
            'BVA valid: max (64)'        => [str_repeat('x', 64),   'ok'],
            'BVA invalid: max+1 (65)'    => [str_repeat('x', 65),   'err'],
            'EP invalid: spaces only'    => ['   ',                 'err'],
        ];
    }

    /** @dataProvider ignLengths */
    public function test_in_game_name_length($ign, $expected)
    {
        [, , , $json] = $this->set(self::BOB, ['game' => 'wot', 'ign' => $ign]);
        $this->assertSame($expected, $json['status']);
    }

    /** TC-API-015 | FR-ROSTER-03: note is cut to 120 characters (BVA 120/121) */
    public function test_note_is_truncated_at_120()
    {
        $this->set(self::BOB, ['game' => 'wot', 'ign' => 'Bob', 'note' => str_repeat('n', 120)]);
        $this->assertSame(120, mb_strlen($this->stored(self::BOB, 'wot')['note']));
        $this->set(self::BOB, ['game' => 'wot', 'ign' => 'Bob', 'note' => str_repeat('n', 121)]);
        $this->assertSame(120, mb_strlen($this->stored(self::BOB, 'wot')['note']));
    }

    /** TC-API-016..021 | FR-ROSTER-04: Mastery Rank is clamped to 0..40 (BVA 3-value) */
    public function masteryRanks()
    {
        return [
            'below min (-1) -> 0' => [-1, 0],
            'min (0)'             => [0, 0],
            'min+1 (1)'           => [1, 1],
            'max-1 (39)'          => [39, 39],
            'max (40)'            => [40, 40],
            'above max (41) -> 40'=> [41, 40],
            'empty -> none'       => ['', null],
        ];
    }

    /** @dataProvider masteryRanks */
    public function test_mastery_rank_is_clamped($mr, $expected)
    {
        $this->set(self::BOB, ['game' => 'warframe', 'ign' => 'BobTenno', 'mr' => $mr]);
        $row = $this->stored(self::BOB, 'warframe');
        $this->assertSame($expected, $row['mr'] === null ? null : (int)$row['mr']);
    }

    /** TC-API-022..025 | FR-ROSTER-05: game must be one of warframe / wot / wows (EP) */
    public function games()
    {
        return [
            'valid: warframe'           => ['warframe', 'ok'],
            'valid: wows'               => ['wows', 'ok'],
            'valid: upper case WOT'     => ['WOT', 'ok'],
            'invalid: unknown game'     => ['helldivers', 'err'],
        ];
    }

    /** @dataProvider games */
    public function test_game_must_be_supported($game, $expected)
    {
        [, , , $json] = $this->set(self::BOB, ['game' => $game, 'ign' => 'Bob']);
        $this->assertSame($expected, $json['status']);
    }

    /** TC-API-026 | FR-USER-01 regression: a member with no hakoo_users row can still save */
    public function test_first_write_by_a_new_member_creates_the_user_row()
    {
        [, , , $json] = $this->set(self::DAVE, ['game' => 'wot', 'ign' => 'DaveTanks']);
        $this->assertSame('ok', $json['status']);
        $this->assertSame(1, $this->rows('hakoo_users', 'discord_id = ?', [self::DAVE]));
    }

    /** TC-API-027 | NFR-SEC-03: the list never exposes Discord ids */
    public function test_list_hides_discord_ids()
    {
        [$status, , , $json] = $this->http('GET', 'roster.php?action=list', null, $this->sessionCookie(self::BOB));
        $this->assertSame(200, $status);
        foreach ($json['entries'] as $e) $this->assertArrayNotHasKey('discord_id', $e);
    }
}
