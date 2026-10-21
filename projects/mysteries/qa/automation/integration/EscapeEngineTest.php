<?php
// The escape room's game engine (component level, no database), modules/escape.inc.php: the bellows, the tool wall,
// answers right, wrong and too fast, the chest and its keys, the chart, hints, stars, a player who leaves, the pause,
// and what every device may see. The answers come from the engine's own solver (my_escape_moves), the way the checker
// plays a room through. Traceability: US-19, K-73, K-78..K-86, AK-60..AK-67.
use PHPUnit\Framework\TestCase;

require_once '/srv/mysteries/modules/escape.inc.php';

final class EscapeEngineTest extends TestCase
{
    private const T0 = 1_000_000.0;

    /** A started 60-minute game for $n players a, b, c…, hints on (or the rules given). */
    private function game(int $n = 2, array $rules = ['hints' => 'on']): array
    {
        $players = array_map(fn($i) => ['id' => chr(97 + $i), 'name' => 'P' . chr(65 + $i)], range(0, $n - 1));
        [$pub] = my_escape_start($players, ['room' => 'runesmith', 'rules' => $rules], ['length_min' => 60, 'host_id' => 'a']);
        return $pub;
    }

    /** One move by player number $p: ['ok', public] or ['err', code]. */
    private function act(array $pub, int $p, array $body, float $now): array
    {
        return my_escape_act($pub, [], 'P' . chr(65 + $p), $body, chr(97 + $p), $now);
    }

    private function ok(array $pub, int $p, array $body, float $now): array
    {
        $r = $this->act($pub, $p, $body, $now);
        $this->assertSame('ok', $r[0], json_encode([$body, $r[1] ?? null]));
        return $r[1];
    }

    /** Open a lock with the solver's moves. */
    private function solve(array $pub, string $lid, float &$now): array
    {
        foreach (my_escape_moves($pub, $lid) as [$p, $body]) $pub = $this->ok($pub, $p, $body, $now += MY_ESCAPE_GAP);
        $this->assertTrue($pub['locks'][$lid]['open'], "$lid opened");
        return $pub;
    }

    private function lit(array $pub, float &$now): array
    {
        return $this->solve($pub, 'bellows', $now);
    }

    /** Every key anywhere in a nested array. */
    private function keys(array $a): array
    {
        $k = [];
        array_walk_recursive($a, function () {});
        $walk = function ($x) use (&$walk, &$k) { foreach ($x as $key => $v) { $k[] = $key; if (is_array($v)) $walk($v); } };
        $walk($a);
        return $k;
    }

    /** TC-INT-033 (K-85, AK-65): one player alone only makes the embers flicker; two in turn light the forge and start the clock */
    public function testBellows(): void
    {
        $pub = $this->game();
        $now = self::T0;
        for ($i = 0; $i < 25; $i++) $pub = $this->ok($pub, 0, ['do' => 'press'], $now += 1);
        $this->assertSame(1, $pub['locks']['bellows']['pub']['n']);
        $this->assertFalse($pub['locks']['bellows']['open']);
        $this->assertNull($pub['clock']['start'], 'the fire is not lit: no clock');
        $this->assertSame(['err', 'not_yet'], $this->act($pub, 0, ['do' => 'search', 'lock' => 'tools', 'spot' => 'forge'], $now));
        for ($i = 1; $i < 20; $i++) $pub = $this->ok($pub, $i % 2, ['do' => 'press'], $now += 1);
        $this->assertTrue($pub['locks']['bellows']['open']);
        $this->assertSame($now, $pub['clock']['start']);
        $this->assertSame(['err', 'bad_lock'], $this->act($pub, 0, ['do' => 'press'], $now), 'a late pump changes nothing');
        $this->assertTrue(my_escape_ready($pub, 'tools') && my_escape_ready($pub, 'runes'));
    }

    /** TC-INT-034 (K-81, K-80, AK-62): the tool wall: a wrong spot costs a minute, a spot once, the right hook only */
    public function testToolWall(): void
    {
        $pub = $this->game();
        $now = self::T0;
        $pub = $this->lit($pub, $now);
        $lock = my_escape_get('runesmith')['locks'][1];
        $at = $pub['locks']['tools']['sec']['at'];
        $empty = array_values(array_diff($lock['spots'], $at))[0];
        $pub = $this->ok($pub, 0, ['do' => 'search', 'lock' => 'tools', 'spot' => $empty], $now += 1);
        $this->assertSame(60, $pub['clock']['penalty']);
        $this->assertSame(['err', 'searched'], $this->act($pub, 1, ['do' => 'search', 'lock' => 'tools', 'spot' => $empty], $now += 1));
        $tool = array_key_first($at);
        $pub = $this->ok($pub, 1, ['do' => 'search', 'lock' => 'tools', 'spot' => $at[$tool]], $now += 1);
        $this->assertTrue(my_escape_has($pub, $tool, 1), 'the tool goes to the one who found it (K-80)');
        $hook = array_search($tool, $lock['tools'], true);
        $this->assertSame(['err', 'not_yours'], $this->act($pub, 0, ['do' => 'hang', 'lock' => 'tools', 'item' => $tool, 'hook' => $hook], $now));
        $this->assertSame(['err', 'wrong_hook'], $this->act($pub, 1, ['do' => 'hang', 'lock' => 'tools', 'item' => $tool, 'hook' => ($hook + 1) % 6], $now));
        $pub = $this->ok($pub, 1, ['do' => 'hang', 'lock' => 'tools', 'item' => $tool, 'hook' => $hook], $now += 1);
        $this->assertContains($tool, $pub['locks']['tools']['pub']['hung']);
        $pub = $this->solve($pub, 'tools', $now);
        $this->assertSame(1, count(array_filter($pub['items'], fn($i) => $i['id'] === 'key_iron')), 'the wall gives the iron key');
    }

    /** TC-INT-035 (K-81, AK-62): the rune lock: a wrong code costs two minutes, a second answer within 5 s is refused, the right one opens */
    public function testRuneLock(): void
    {
        $pub = $this->game();
        $now = self::T0;
        $pub = $this->lit($pub, $now);
        $code = $pub['locks']['runes']['sec']['code'];
        $wrong = str_pad((string)(((int)$code + 1) % 10000), 4, '0', STR_PAD_LEFT);
        $this->assertSame(['err', 'bad_answer'], $this->act($pub, 0, ['do' => 'answer', 'lock' => 'runes', 'value' => '12a4'], $now += 10));
        $pub = $this->ok($pub, 0, ['do' => 'answer', 'lock' => 'runes', 'value' => $wrong], $now += 10);
        $this->assertFalse($pub['locks']['runes']['open']);
        $this->assertSame([120, 1, 1], [$pub['clock']['penalty'], $pub['wrong'], $pub['locks']['runes']['wrong']]);
        $this->assertSame(['err', 'too_fast'], $this->act($pub, 1, ['do' => 'answer', 'lock' => 'runes', 'value' => $code], $now + 4.9), 'AK-62: within 5 s');
        $pub = $this->ok($pub, 1, ['do' => 'answer', 'lock' => 'runes', 'value' => $code], $now + 5);
        $this->assertTrue($pub['locks']['runes']['open']);
        $this->assertSame('open', end($pub['log'])['k']);
        $this->assertSame(['err', 'already_open'], $this->act($pub, 0, ['do' => 'answer', 'lock' => 'runes', 'value' => $code], $now + 20));
        $this->assertTrue(my_escape_ready($pub, 'scale') && my_escape_ready($pub, 'shields') && my_escape_ready($pub, 'chest'));
    }

    /** TC-INT-036 (K-80, AK-63): the chest opens only for the one who carries all three keys; giving moves a key */
    public function testChestAndKeys(): void
    {
        $pub = $this->game(3);
        $now = self::T0;
        $pub = $this->lit($pub, $now);
        $pub['locks']['runes']['open'] = true;
        $pub['items'] = [['id' => 'key_iron', 'p' => 0], ['id' => 'key_silver', 'p' => 1], ['id' => 'key_bronze', 'p' => 0]];
        $this->assertSame(['err', 'need_items'], $this->act($pub, 0, ['do' => 'open', 'lock' => 'chest'], $now += 1));
        $this->assertSame(['err', 'need_items'], $this->act($pub, 1, ['do' => 'open', 'lock' => 'chest'], $now += 1));
        $this->assertSame(['err', 'not_yours'], $this->act($pub, 0, ['do' => 'give', 'item' => 'key_silver', 'to' => 0], $now));
        $this->assertSame(['err', 'bad_player'], $this->act($pub, 1, ['do' => 'give', 'item' => 'key_silver', 'to' => 7], $now));
        $pub = $this->ok($pub, 1, ['do' => 'give', 'item' => 'key_silver', 'to' => 0], $now += 1);
        $this->assertSame(['k' => 'give', 'p' => 1, 'to' => 0, 'item' => 'key_silver'], array_diff_key(end($pub['log']), ['n' => 1]));
        $pub = $this->ok($pub, 0, ['do' => 'open', 'lock' => 'chest'], $now += 1);
        $this->assertTrue($pub['locks']['chest']['open']);
        $this->assertSame([['id' => 'sword', 'p' => 0]], $pub['items'], 'the keys stay in the chest; the sword comes out');
        $inside = array_filter($pub['frags'], fn($f) => $f['lock'] === 'chart' && $f['slot'] === 1);
        $this->assertSame(0, array_values($inside)[0]['p'], 'the piece of the chart in the chest goes to the opener');
    }

    /** TC-INT-037 (K-82, K-79): the chart: a piece in the wrong place slides back for free; hint 3 lays the rest and shows the course */
    public function testChart(): void
    {
        $pub = $this->game(2);
        $now = self::T0;
        foreach (['bellows', 'tools', 'runes', 'scale', 'shields', 'chest'] as $l) $pub = $this->solve($pub, $l, $now);
        $piece = array_values(array_filter($pub['frags'], fn($f) => $f['lock'] === 'chart' && $f['p'] !== null))[0];
        $this->assertSame(['err', 'wrong_slot'], $this->act($pub, $piece['p'], ['do' => 'place', 'lock' => 'chart', 'piece' => $piece['id'], 'slot' => ($piece['slot'] + 1) % 6], $now));
        $this->assertSame(['err', 'not_yours'], $this->act($pub, 1 - $piece['p'], ['do' => 'place', 'lock' => 'chart', 'piece' => $piece['id'], 'slot' => $piece['slot']], $now));
        $pub = $this->ok($pub, $piece['p'], ['do' => 'place', 'lock' => 'chart', 'piece' => $piece['id'], 'slot' => $piece['slot']], $now += 1);
        $this->assertTrue($pub['locks']['chart']['pub']['placed'][$piece['slot']]);
        $this->assertSame(0, $pub['wrong']);
        $view = my_escape_public($pub, [], $now)['locks']['chart'];
        $this->assertArrayNotHasKey('course', $view, 'AK-60: no course before the chart is whole');
        for ($k = 1; $k <= 3; $k++) $pub = $this->ok($pub, 0, ['do' => 'hint', 'lock' => 'chart'], $now += 1);
        $this->assertTrue($pub['locks']['chart']['open']);
        $this->assertSame($pub['locks']['chart']['sec']['course'], my_escape_public($pub, [], $now)['locks']['chart']['course']);
        $this->assertTrue(my_escape_ready($pub, 'knots'));
    }

    /** TC-INT-038 (K-82, K-83, K-86, AK-66, AK-67): hints and stars, the rule hints off, and the end by the host */
    public function testHintsAndStars(): void
    {
        $pub = $this->game(2, ['hints' => 'off']);
        $now = self::T0;
        $pub = $this->lit($pub, $now);
        $this->assertSame(['err', 'no_hints'], $this->act($pub, 0, ['do' => 'hint', 'lock' => 'runes'], $now));

        $pub = $this->game();
        $now = self::T0;
        $pub = $this->lit($pub, $now);
        $pub = $this->ok($pub, 1, ['do' => 'hint', 'lock' => 'runes'], $now += 1);
        $pub = $this->ok($pub, 0, ['do' => 'hint', 'lock' => 'runes'], $now += 1);
        $pub = $this->ok($pub, 0, ['do' => 'hint', 'lock' => 'runes'], $now += 1);
        $this->assertSame(['err', 'no_more_hints'], $this->act($pub, 0, ['do' => 'hint', 'lock' => 'runes'], $now));
        $this->assertSame(['hint.runes.3', ['code' => $pub['locks']['runes']['sec']['code']]], [$pub['locks']['runes']['hints'][2]['k'], $pub['locks']['runes']['hints'][2]['p']]);
        $this->assertSame(3, $pub['hints']);
        $this->assertSame(3, count(my_escape_public($pub, [], $now)['locks']['runes']['hints']), 'every screen sees the hints');

        $fast = $pub;                                             // AK-66: within the time with three hints: two stars
        foreach (['tools', 'runes', 'scale', 'shields', 'chest', 'chart', 'knots'] as $l) $fast = $this->solve($fast, $l, $now);
        $this->assertTrue($fast['over']);
        $this->assertSame(['out', 2], [$fast['how'], my_escape_public($fast, [], $now + 9999)['stars']]);
        $this->assertSame($fast['clock']['end'], $now, 'the clock stops when the last lock opens');

        $slow = $this->game();                                    // AK-67: the soft clock: on after the fire is out
        $now = self::T0;
        $slow = $this->lit($slow, $now);
        $now += 3601;
        foreach (['tools', 'runes', 'scale', 'shields', 'chest', 'chart', 'knots'] as $l) $slow = $this->solve($slow, $l, $now);
        $this->assertSame(2, my_escape_stars($slow, $now), 'over the time, no hints');
        $slow['hints'] = 3;
        $this->assertSame(1, my_escape_stars($slow, $now), 'over the time and many hints');
        $slow['hints'] = 0;
        $slow['clock']['end'] = $slow['clock']['start'] + 1800;
        $this->assertSame(3, my_escape_stars($slow, $now), 'within the time, no hints');

        $ended = my_escape_end($this->lit($this->game(), $now));
        $this->assertSame(['host', true, 0], [$ended['how'], my_escape_over($ended), my_escape_stars($ended, $now)]);
    }

    /** TC-INT-039 (K-84, AK-64): a player who leaves leaves their fragments and items to the one who holds the least */
    public function testLeaver(): void
    {
        $pub = $this->game(3);
        $now = self::T0;
        $pub = $this->lit($pub, $now);
        $pub['items'][] = ['id' => 'key_iron', 'p' => 2];
        $held = fn($p) => count(array_filter($pub['frags'], fn($f) => $f['p'] === $p)) + count(array_filter($pub['items'], fn($i) => $i['p'] === $p));
        $least = $held(0) <= $held(1) ? 0 : 1;
        $cs = array_keys(array_filter($pub['frags'], fn($f) => $f['p'] === 2));
        $this->assertNull(my_escape_tick($pub, [['id' => 'a'], ['id' => 'b'], ['id' => 'c']], 'a', $now), 'nobody left: nothing happens');
        $pub = my_escape_tick($pub, [['id' => 'a'], ['id' => 'b']], 'a', $now);
        foreach ($cs as $k) $this->assertSame($least, $pub['frags'][$k]['p']);
        $this->assertTrue(my_escape_has($pub, 'key_iron', $least));
        $this->assertSame(['k' => 'left', 'p' => 2, 'to' => $least, 'frags' => count($cs), 'items' => ['key_iron']], array_diff_key(end($pub['log']), ['n' => 1]));
        $this->assertNull(my_escape_tick($pub, [['id' => 'a'], ['id' => 'b']], 'a', $now), 'only once');
        $pub = $this->solve($pub, 'runes', $now);                 // AK-64: the rune lock can still be solved
        $view = my_escape_you($pub, 'c', [], false);
        $this->assertSame([], $view['frags']);
    }

    /** TC-INT-040 (K-73, K-79, AK-60): no screen gets an unopened lock's answer; a phone gets only its own fragments and items */
    public function testWhatTheScreensSee(): void
    {
        $pub = $this->game(3);
        $now = self::T0;
        $pub = $this->lit($pub, $now);
        $view = my_escape_public($pub, [['id' => 'a'], ['id' => 'b'], ['id' => 'c']], $now);
        $keys = $this->keys($view);
        foreach (['sec', 'at', 'map', 'code', 'w', 'answer', 'course', 'signs', 'frags', 'items', 'id'] as $k) $this->assertNotContains($k, $keys, "the public view has no $k");
        $this->assertSame(['here', 'here', 'here'], array_column($view['players'], 'place'));
        $this->assertSame(4, count($view['locks']['runes']['door']));

        $mine = my_escape_you($pub, 'b', [], false);
        $this->assertSame(1, $mine['player']);
        $own = array_values(array_filter($pub['frags'], fn($f) => $f['p'] === 1));
        $this->assertSame(array_column($own, 'id'), array_column($mine['frags'], 'id'));
        foreach ($mine['frags'] as $f) $this->assertArrayNotHasKey('p', $f);
        $this->assertSame(['player' => null], my_escape_you($pub, 'x', [], false), 'not at the table');

        $pub = $this->solve($pub, 'runes', $now);
        $this->assertArrayNotHasKey('code', my_escape_public($pub, [], $now)['locks']['runes']);
        $pub = $this->solve($pub, 'shields', $now);
        $this->assertSame($pub['locks']['shields']['sec']['answer'], my_escape_public($pub, [], $now)['locks']['shields']['answer'], 'the order once it is open');
    }

    /** TC-INT-041 (K-81, K-15): a pause holds the fire; the time left is the length minus the time played and the penalty */
    public function testPauseHoldsTheFire(): void
    {
        $pub = $this->game();
        $now = self::T0;
        $pub = $this->lit($pub, $now);
        $start = $now;
        $pub = my_escape_hold($pub, true, $start + 100);
        $this->assertSame(100, my_escape_public($pub, [], $start + 900)['clock']['elapsed']);
        $this->assertTrue(my_escape_public($pub, [], $start + 900)['clock']['paused']);
        $pub = my_escape_hold($pub, false, $start + 1000);
        $this->assertSame(160, my_escape_public($pub, [], $start + 1060)['clock']['elapsed']);
        $this->assertSame(3600, my_escape_public($pub, [], $start + 1060)['clock']['limit']);
    }
}
