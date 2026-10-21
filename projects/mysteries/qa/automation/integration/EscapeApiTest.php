<?php
// The escape room over the API (system level): creating and starting "The Runesmith's Locks", what each device may see,
// answers and items, the pause, the host's end and a player who leaves. The test reads the drawn game from the
// database (the oracle), as no device can. Traceability: US-18, US-19, K-73, K-77..K-87, K-13, K-15, AK-60..AK-64.
use PHPUnit\Framework\TestCase;

final class EscapeApiTest extends TestCase
{
    private const BOB  = [TEST_BOB, 'Bob'];
    private const ERIK = [TEST_ERIK, 'Erik'];

    /** An escape room with Bob as host and player, a board and Erik let in: [host token, board token, Erik's token, code]. */
    private function table(int $len = 60, string $room = 'runesmith'): array
    {
        [$status, $g] = my_api('POST', 'create', ['category' => 'escape', 'room' => $room, 'lengthMin' => $len, 'waitMin' => 5,
                                                  'hostPlays' => true, 'boardLang' => 'sv'], self::BOB);
        $this->assertSame(200, $status, json_encode($g));
        [, $b] = my_api('POST', 'join', ['code' => $g['code'], 'kind' => 'board', 'name' => 'TV']);
        [, $e] = my_api('POST', 'join', ['code' => $g['code'], 'kind' => 'player'], self::ERIK);
        my_api('POST', 'approve', ['device' => $b['view']['you']['id'], 'ok' => true], self::BOB, $g['token']);
        my_api('POST', 'approve', ['device' => $e['view']['you']['id'], 'ok' => true], self::BOB, $g['token']);
        return [$g['token'], $b['token'], $e['token'], $g['code']];
    }

    private function start(string $host, string $code): array
    {
        [$status, $j] = my_api('POST', 'start', [], self::BOB, $host);
        $this->assertSame(200, $status, json_encode($j));
        return $this->secret($code);
    }

    private function secret(string $code): array
    {
        $s = get_pdo()->prepare('SELECT public_state FROM mysteries_sessions WHERE code = ?');
        $s->execute([$code]);
        return json_decode($s->fetchColumn(), true);
    }

    private function view(?array $as, string $token): array
    {
        [$status, $v] = my_api('GET', 'state', null, $as, $token);
        $this->assertSame(200, $status, json_encode($v));
        return $v['view'];
    }

    /** Light the forge (or the lamp): Bob and Erik pump in turn. */
    private function light(string $host, string $erik): void
    {
        for ($i = 0; $i < 20; $i++) {
            [$status, $j] = $i % 2 ? my_api('POST', 'act', ['do' => 'press'], self::ERIK, $erik) : my_api('POST', 'act', ['do' => 'press'], self::BOB, $host);
            $this->assertSame(200, $status, json_encode($j));
        }
    }

    /** Every key anywhere in a nested array. */
    private function keys(array $a): array
    {
        $k = [];
        $walk = function ($x) use (&$walk, &$k) { foreach ($x as $key => $v) { $k[] = $key; if (is_array($v)) $walk($v); } };
        $walk($a);
        return $k;
    }

    /** TC-API-033 (K-77, K-75, K-74): an escape room is created in one of its lengths and started with 2-6 players */
    public function testCreateAndStartLimits(): void
    {
        [$status, $j] = my_api('POST', 'create', ['category' => 'escape', 'room' => 'runesmith', 'lengthMin' => 90, 'waitMin' => 5, 'boardLang' => 'en'], self::BOB);
        $this->assertSame([400, 'bad_length'], [$status, $j['error']], '90 min is not written yet');
        [$status, $j] = my_api('POST', 'create', ['category' => 'escape', 'room' => '../last-toast', 'lengthMin' => 60, 'waitMin' => 5, 'boardLang' => 'en'], self::BOB);
        $this->assertSame([400, 'bad_room'], [$status, $j['error']]);
        [$status, $g] = my_api('POST', 'create', ['category' => 'escape', 'lengthMin' => 30, 'waitMin' => 5, 'boardLang' => 'en'], self::BOB);
        $this->assertSame(200, $status, 'the first room is the default');
        $first = basename(dirname(glob('/srv/mysteries/content/*/escape.json')[0]));   // in the order of their names
        $this->assertSame(['room' => $first, 'rules' => ['hints' => 'on']], $this->view(self::BOB, $g['token'])['board']);
        [$status, $j] = my_api('POST', 'start', [], self::BOB, $g['token']);
        $this->assertSame([409, 'escape_players'], [$status, $j['error']], 'one player is too few');
        [$status, $j] = test_get('/mysteries/content/runesmith/escape.json');
        $this->assertSame(403, $status, 'the room file is never sent (K-73)');
    }

    /** TC-API-034 (K-73, K-79, K-87, AK-60, AK-61): after the start no device gets an answer; each phone gets only its own fragments */
    public function testTheAnswersStayOnTheServer(): void
    {
        [$host, $board, $erik, $code] = $this->table();
        $secret = $this->start($host, $code);
        $runes = [];
        foreach ([[null, $board, null], [self::BOB, $host, 0], [self::ERIK, $erik, 1]] as [$as, $token, $seat]) {
            $v = $this->view($as, $token);
            $keys = $this->keys($v['board']);
            foreach (['sec', 'at', 'map', 'code', 'w', 'answer', 'course', 'signs', 'frags', 'items'] as $never) $this->assertNotContains($never, $keys, ($as[1] ?? 'the board') . " sees $never");
            $raw = json_encode($v);
            foreach ([TEST_BOB, TEST_ERIK] as $never) $this->assertStringNotContainsString($never, $raw);
            if ($seat === null) { $this->assertArrayNotHasKey('mine', $v); continue; }
            $this->assertSame($seat, $v['mine']['player']);
            $own = array_values(array_filter($secret['frags'], fn($f) => $f['p'] === $seat));
            $this->assertSame(array_column($own, 'id'), array_column($v['mine']['frags'], 'id'), 'K-79: only the own fragments');
            foreach ($v['mine']['frags'] as $f) if ($f['lock'] === 'runes') $runes[$seat] = array_column($f['pairs'], 0);
        }
        $this->assertCount(16, array_unique(array_merge($runes[0], $runes[1])), 'AK-61: all sixteen runes');
        $this->assertSame([], array_intersect($runes[0], $runes[1]), 'AK-61: none on both phones');

        [, $c] = my_api('GET', 'content', null, self::ERIK, $erik, '&lang=da');
        $this->assertSame('Runesmedens låse', $c['content']['texts']['title'], 'K-87');
        $this->assertCount(8, $c['content']['locks']);
        $raw = json_encode($c);
        foreach (['"sec"', '"answer"', '"inside"', '"lengths":[{'] as $never) $this->assertStringNotContainsString($never, $raw, "content has $never");
    }

    /** TC-API-035 (K-81, K-80, K-78, AK-62): no turns; a wrong code costs two minutes, the right one opens; an item can be given */
    public function testAnswersAndGiving(): void
    {
        [$host, $board, $erik, $code] = $this->table();
        $this->start($host, $code);
        [$status, $j] = my_api('POST', 'act', ['do' => 'answer', 'lock' => 'runes', 'value' => '0000'], self::BOB, $host);
        $this->assertSame([400, 'not_yet'], [$status, $j['error']], 'the door waits for the fire');
        $this->light($host, $erik);
        $secret = $this->secret($code);
        $this->assertNotNull($secret['clock']['start']);
        $right = $secret['locks']['runes']['sec']['code'];
        $wrong = str_pad((string)(((int)$right + 1) % 10000), 4, '0', STR_PAD_LEFT);
        [$status] = my_api('POST', 'act', ['do' => 'answer', 'lock' => 'runes', 'value' => $wrong], self::ERIK, $erik);
        $this->assertSame(200, $status);
        $tv = $this->view(null, $board)['board'];
        $this->assertSame([1, 120, false], [$tv['wrong'], $tv['clock']['penalty'], $tv['locks']['runes']['open']]);
        [$status, $j] = my_api('POST', 'act', ['do' => 'answer', 'lock' => 'runes', 'value' => $right], self::BOB, $host);
        $this->assertSame([400, 'too_fast'], [$status, $j['error']], 'one answer per lock in 5 s');
        sleep(5);
        [$status] = my_api('POST', 'act', ['do' => 'answer', 'lock' => 'runes', 'value' => $right], self::BOB, $host);
        $this->assertSame(200, $status);
        $tv = $this->view(null, $board)['board'];
        $this->assertTrue($tv['locks']['runes']['open']);
        $this->assertSame('Bob', $tv['locks']['runes']['by']);

        $pdo = get_pdo();                                         // the oracle gives Bob a key to give away
        $pub = $this->secret($code);
        $pub['items'][] = ['id' => 'key_iron', 'p' => 0];
        $pdo->prepare('UPDATE mysteries_sessions SET public_state = ? WHERE code = ?')->execute([json_encode($pub), $code]);
        [$status, $j] = my_api('POST', 'act', ['do' => 'give', 'item' => 'key_iron', 'to' => 1], self::ERIK, $erik);
        $this->assertSame([400, 'not_yours'], [$status, $j['error']]);
        [$status] = my_api('POST', 'act', ['do' => 'give', 'item' => 'key_iron', 'to' => 1], self::BOB, $host);
        $this->assertSame(200, $status);
        $this->assertSame(['key_iron'], $this->view(self::ERIK, $erik)['mine']['items']);
        $this->assertSame([], $this->view(self::BOB, $host)['mine']['items']);
    }

    /** TC-API-036 (K-15, K-81, K-83): a pause holds the fire; only the host ends; the end has no stars */
    public function testPauseAndEnd(): void
    {
        [$host, $board, $erik, $code] = $this->table(30);
        $this->start($host, $code);
        $this->light($host, $erik);
        my_api('POST', 'pause', [], self::BOB, $host);
        $a = $this->view(null, $board)['board']['clock'];
        sleep(2);
        $b = $this->view(null, $board)['board']['clock'];
        $this->assertTrue($b['paused']);
        $this->assertSame($a['elapsed'], $b['elapsed'], 'the fire does not burn during a pause');
        $this->assertSame(1800, $b['limit']);
        [$status, $j] = my_api('POST', 'act', ['do' => 'press'], self::BOB, $host);
        $this->assertNotSame(200, $status, 'no moves during a pause');
        my_api('POST', 'resume', [], self::BOB, $host);
        [$status, $j] = my_api('POST', 'end', [], self::ERIK, $erik);
        $this->assertSame([403, 'not_host'], [$status, $j['error']]);
        [$status] = my_api('POST', 'end', [], self::BOB, $host);
        $this->assertSame(200, $status);
        $v = $this->view(self::ERIK, $erik);
        $this->assertSame(['ended', 'host', 0], [$v['session']['status'], $v['board']['how'], $v['board']['stars']]);
    }

    /** TC-API-037 (K-84, AK-64): Erik leaves; his fragments go to Bob and the board says so */
    public function testLeaverLeavesTheFragments(): void
    {
        [$host, $board, $erik, $code] = $this->table();
        $this->start($host, $code);
        $this->light($host, $erik);
        $his = array_column(array_filter($this->secret($code)['frags'], fn($f) => $f['p'] === 1), 'id');
        my_api('POST', 'leave', [], self::ERIK, $erik);
        $tv = $this->view(null, $board)['board'];
        $this->assertSame('away', $tv['players'][1]['place']);
        $left = end($tv['log']);
        $this->assertSame(['left', 1, 0, count($his)], [$left['k'], $left['p'], $left['to'], $left['frags']]);
        $mine = array_column($this->view(self::BOB, $host)['mine']['frags'], 'id');
        $this->assertSame([], array_diff($his, $mine), 'every one of them is on Bob\'s phone');
    }

    /** TC-API-044 (K-112, K-116, AK-85, AK-86): the lighthouse: no device gets a walk back, a target, an answer or an unsolved digit; a solved picture's digit reaches only its player */
    public function testThePuzzlesKeepTheirSecrets(): void
    {
        [$host, $board, $erik, $code] = $this->table(60, 'lighthouse');
        $this->start($host, $code);
        $this->light($host, $erik);
        $secret = $this->secret($code);
        foreach ([[null, $board], [self::BOB, $host], [self::ERIK, $erik]] as [$as, $token]) {
            $v = $this->view($as, $token);
            $keys = $this->keys($v['board']);
            foreach (['sec', 'walk', 'digits', 'target', 'answer', 'path', 'code', 'frags'] as $never) $this->assertNotContains($never, $keys, ($as[1] ?? 'the board') . " sees $never");
            if ($as) $this->assertNotContains('d', $this->keys($v['mine']), 'no digit before a picture is whole');
        }
        $this->assertCount(4, $this->view(null, $board)['board']['locks']['bricks']['puzzles']);

        $f = array_values(array_filter($secret['frags'], fn($f) => $f['k'] === 'slide' && $f['p'] === 1))[0];
        $walk = $secret['locks']['bricks']['sec']['walk'][$f['pic']];
        [$status, $j] = my_api('POST', 'act', ['do' => 'slide', 'lock' => 'bricks', 'piece' => $f['id'], 'moves' => array_slice($walk, 1)], self::ERIK, $erik);
        $this->assertSame([400, 'bad_moves'], [$status, $j['error']], 'AK-86: moves that cannot be made');
        [$status, $j] = my_api('POST', 'act', ['do' => 'slide', 'lock' => 'bricks', 'piece' => $f['id'], 'moves' => $walk], self::BOB, $host);
        $this->assertSame([400, 'not_yours'], [$status, $j['error']]);
        [$status, $j] = my_api('POST', 'act', ['do' => 'slide', 'lock' => 'bricks', 'piece' => $f['id'], 'moves' => $walk], self::ERIK, $erik);
        $this->assertSame(200, $status, json_encode($j));
        $d = $secret['locks']['bricks']['sec']['digits'][$f['pic']];
        $digits = array_values(array_filter($this->view(self::ERIK, $erik)['mine']['frags'], fn($x) => $x['k'] === 'digit'));
        $this->assertSame([[$f['pic'], $d]], array_map(fn($x) => [$x['pic'], $x['d']], $digits), 'AK-86: Erik gets the digit');
        $this->assertSame([], array_filter($this->view(self::BOB, $host)['mine']['frags'], fn($x) => $x['k'] === 'digit'), 'Bob does not');
        $tv = $this->view(null, $board)['board'];
        $this->assertContains(true, array_column($tv['locks']['bricks']['puzzles'], 'done'), 'K-116: the TV says it is solved');
        $this->assertNotContains('d', $this->keys($tv));
    }
}
