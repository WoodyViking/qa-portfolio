<?php
// The puzzles on the phones in the escape room engine (component level, no database), with "The Lighthouse in the
// Storm": sliding pictures, the gas pipe, the valves, the lens rings and the signal, what each device sees, and a
// player who leaves. The moves come from the engine's own solver (my_escape_moves), as when the checker plays a room.
// Traceability: US-24, US-25, K-111..K-118, AK-85..AK-89, AK-91, AK-92.
use PHPUnit\Framework\TestCase;

require_once '/srv/mysteries/modules/escape.inc.php';

final class PuzzleEngineTest extends TestCase
{
    private const T0 = 1_000_000.0;

    private function game(int $n = 2, array $rules = ['hints' => 'on']): array
    {
        $players = array_map(fn($i) => ['id' => chr(97 + $i), 'name' => 'P' . chr(65 + $i)], range(0, $n - 1));
        [$pub] = my_escape_start($players, ['room' => 'lighthouse', 'rules' => $rules], ['length_min' => 60, 'host_id' => 'a']);
        return $pub;
    }

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

    private function solve(array $pub, string $lid, float &$now): array
    {
        foreach (my_escape_moves($pub, $lid) as [$p, $body]) {
            if ($pub['locks'][$lid]['open']) break;                    // as the checker: the gas may run through before the last turn
            $pub = $this->ok($pub, $p, $body, $now += MY_ESCAPE_GAP);
        }
        $this->assertTrue($pub['locks'][$lid]['open'], "$lid opened");
        return $pub;
    }

    /** A game with the locks before $upto open. */
    private function upTo(string $upto, int $n, float &$now): array
    {
        $pub = $this->game($n);
        foreach (['lamp', 'bricks', 'door', 'gas', 'valves', 'hatch', 'rings', 'signal'] as $l) {
            if ($l === $upto) return $pub;
            $pub = $this->solve($pub, $l, $now);
        }
        return $pub;
    }

    private function keys(array $a): array
    {
        $k = [];
        $walk = function ($x) use (&$walk, &$k) { foreach ($x as $key => $v) { $k[] = $key; if (is_array($v)) $walk($v); } };
        $walk($a);
        return $k;
    }

    /** TC-INT-058 (K-111, K-112, AK-86): a sliding picture: moves that do not solve it, or cannot be made, are refused; a solved one gives its digit to its player only */
    public function testSlidingPictures(): void
    {
        $now = self::T0;
        $pub = $this->upTo('bricks', 2, $now);
        $puzzles = array_values(array_filter($pub['frags'], fn($f) => $f['lock'] === 'bricks' && $f['k'] === 'slide'));
        $this->assertCount(4, $puzzles, 'K-120: four puzzles for two players');
        $this->assertSame([2, 2], array_values(array_count_values(array_column($puzzles, 'p'))), 'two each');
        $f = $puzzles[0];
        $walk = $pub['locks']['bricks']['sec']['walk'][$f['pic']];
        $send = fn($moves, $p = null) => $this->act($pub, $p ?? $f['p'], ['do' => 'slide', 'lock' => 'bricks', 'piece' => $f['id'], 'moves' => $moves], $now);

        $this->assertSame(['err', 'not_solved'], $send(array_slice($walk, 0, -1)), 'one move short');
        $this->assertSame(['err', 'bad_moves'], $send([...$walk, 99]), 'a tile that is not there');
        $gap = array_search(0, $f['tiles'], true);
        $far = array_values(array_filter(range(0, 8), fn($i) => abs($i % 3 - $gap % 3) + abs(intdiv($i, 3) - intdiv($gap, 3)) > 1))[0];
        $this->assertSame(['err', 'bad_moves'], $send([$far]), 'a tile not beside the gap');
        $this->assertSame(['err', 'not_yours'], $send($walk, 1 - $f['p']), "another player's puzzle");
        $this->assertSame(['err', 'bad_moves'], $send(array_fill(0, MY_ESCAPE_MOVES + 1, $walk[0])), 'too many moves');

        $before = array_column(my_escape_you($pub, chr(97 + (1 - $f['p'])))['frags'], 'id');
        $pub = $this->ok($pub, $f['p'], ['do' => 'slide', 'lock' => 'bricks', 'piece' => $f['id'], 'moves' => $walk], $now += 1);
        $digit = end($pub['frags']);
        $this->assertSame(['digit', $f['pic'], $pub['locks']['bricks']['sec']['digits'][$f['pic']], $f['p']], [$digit['k'], $digit['pic'], $digit['d'], $digit['p']]);
        $this->assertContains($digit['id'], array_column(my_escape_you($pub, chr(97 + $f['p']))['frags'], 'id'), 'the player has the digit');
        $this->assertSame($before, array_column(my_escape_you($pub, chr(97 + (1 - $f['p'])))['frags'], 'id'), 'the other phone gets nothing');
        $view = my_escape_public($pub, [], $now)['locks']['bricks'];
        $this->assertSame([true, false, false, false], array_column($view['puzzles'], 'done'), 'K-116: the TV shows who is done');
        $this->assertNotContains('d', $this->keys($view), 'K-116: not the digit');
        $this->assertSame(['slid', $f['p'], $f['pic']], [end($pub['log'])['k'], end($pub['log'])['p'], end($pub['log'])['pic']]);
        $this->assertSame(0, $pub['clock']['penalty'], 'K-111: no minutes');
        $this->assertSame(['err', 'already_solved'], $this->act($pub, $f['p'], ['do' => 'slide', 'lock' => 'bricks', 'piece' => $f['id'], 'moves' => $walk], $now));

        $pub = $this->solve($pub, 'bricks', $now);                 // the rest; the door wants the four digits
        $code = implode('', array_map(fn($s) => $pub['locks']['bricks']['sec']['digits'][$s], $pub['locks']['door']['pub']['door']));
        $this->assertSame($code, $pub['locks']['door']['sec']['code']);
    }

    /** TC-INT-059 (K-113, K-111, AK-87): the gas pipe: only the owner turns a square, ten moves a second, and the last right turn opens it for free */
    public function testGasPipe(): void
    {
        $now = self::T0;
        $pub = $this->upTo('gas', 3, $now);
        $cells = $pub['locks']['gas']['pub']['cells'];
        $owned = array_map(fn($p) => count(array_filter($cells, fn($c) => $c[2] === $p)), [0, 1, 2]);
        $this->assertSame([20, 6, 7], [array_sum($owned), min($owned), max($owned)], 'K-113: twenty squares, evenly');
        $c = 0;
        $this->assertSame(['err', 'not_yours'], $this->act($pub, ($cells[$c][2] + 1) % 3, ['do' => 'turn', 'lock' => 'gas', 'cell' => $c], $now));
        $this->assertSame(['err', 'bad_cell'], $this->act($pub, 0, ['do' => 'turn', 'lock' => 'gas', 'cell' => 20], $now));
        $this->assertSame(['err', 'bad_action'], $this->act($pub, 0, ['do' => 'flip', 'lock' => 'gas', 'cell' => 0], $now), 'a valve move on the pipe');
        $once = $this->ok($pub, $cells[$c][2], ['do' => 'turn', 'lock' => 'gas', 'cell' => $c], $now += 1);
        $this->assertSame(($cells[$c][1] + 1) % 4, $once['locks']['gas']['pub']['cells'][$c][1], 'a quarter turn');
        $this->assertSame(['err', 'too_fast'], $this->act($once, $cells[$c][2], ['do' => 'turn', 'lock' => 'gas', 'cell' => $c], $now + 0.05), 'K-111: ten a second');
        $this->assertSame(my_escape_public($once, [], $now)['locks']['gas']['cells'][$c], $once['locks']['gas']['pub']['cells'][$c], 'K-116: the screens see the turn');

        $pub = $this->solve($pub, 'gas', $now);
        $this->assertSame([0, 0], [$pub['clock']['penalty'], $pub['wrong']]);
        $this->assertTrue(my_escape_pipes_flow(my_escape_get('lighthouse')['locks'][3], $pub['locks']['gas']['pub']['cells']));
    }

    /** TC-INT-060 (K-115, AK-91): the valves: the third hint lays the panel right, opens the lock and counts as a hint */
    public function testThirdHintOnTheValves(): void
    {
        $now = self::T0;
        $pub = $this->upTo('valves', 2, $now);
        $this->assertNotSame($pub['locks']['valves']['sec']['target'], $pub['locks']['valves']['pub']['on']);
        $pub = $this->ok($pub, 0, ['do' => 'flip', 'lock' => 'valves', 'cell' => 4], $now += 1);   // anyone turns a wheel
        $hints = $pub['hints'];
        for ($k = 1; $k <= 3; $k++) $pub = $this->ok($pub, 1, ['do' => 'hint', 'lock' => 'valves'], $now += 1);
        $this->assertSame('hint.valves.2', $pub['locks']['valves']['hints'][1]['k']);
        $this->assertMatchesRegularExpression('/^[A-C][1-3]$/', $pub['locks']['valves']['hints'][1]['p']['cell']);
        $this->assertTrue($pub['locks']['valves']['open']);
        $this->assertSame($pub['locks']['valves']['sec']['target'], my_escape_public($pub, [], $now)['locks']['valves']['on'], 'every screen sees it right');
        $this->assertSame($hints + 3, $pub['hints']);
    }

    /** TC-INT-061 (K-113, AK-88): the lens rings: with two players the line for each ring is with the other one; only the owner turns */
    public function testLensRings(): void
    {
        for ($i = 0; $i < 20; $i++) {
            $now = self::T0;
            $pub = $this->upTo('rings', 2, $now);
            $owners = $pub['locks']['rings']['pub']['owners'];
            foreach (array_filter($pub['frags'], fn($f) => $f['lock'] === 'rings') as $f) $this->assertNotSame($owners[$f['ring']], $f['p'], 'AK-88');
        }
        $this->assertSame(['err', 'not_yours'], $this->act($pub, 1 - $owners[0], ['do' => 'spin', 'lock' => 'rings', 'ring' => 0, 'dir' => 1], $now));
        $this->assertSame(['err', 'bad_ring'], $this->act($pub, $owners[0], ['do' => 'spin', 'lock' => 'rings', 'ring' => 0, 'dir' => 2], $now));
        $spun = $this->ok($pub, $owners[0], ['do' => 'spin', 'lock' => 'rings', 'ring' => 0, 'dir' => -1], $now += 1);
        $this->assertSame(($pub['locks']['rings']['pub']['pos'][0] + 7) % 8, $spun['locks']['rings']['pub']['pos'][0]);
        $this->assertSame(array_slice($pub['locks']['rings']['pub']['pos'], 1), array_slice($spun['locks']['rings']['pub']['pos'], 1), 'not coupled at 60 min');
        $this->assertArrayNotHasKey('target', my_escape_public($spun, [], $now)['locks']['rings']);
        $pub = $this->solve($pub, 'rings', $now);
        $this->assertSame(0, $pub['clock']['penalty']);
    }

    /** TC-INT-062 (K-111, AK-89): the signal: a wrong answer costs nothing, a second within 3 s is refused, the right one ends the game */
    public function testSignal(): void
    {
        $now = self::T0;
        $pub = $this->upTo('signal', 2, $now);
        $right = $pub['locks']['signal']['sec']['answer'];
        $wrong = $right === '...' ? '---' : '...';
        $pub = $this->ok($pub, 1, ['do' => 'answer', 'lock' => 'signal', 'value' => $wrong], $now += 1);
        $this->assertSame([0, 0, false], [$pub['wrong'], $pub['clock']['penalty'], $pub['locks']['signal']['open']]);
        $this->assertSame(['again', 1, 'signal'], [end($pub['log'])['k'], end($pub['log'])['p'], end($pub['log'])['lock']]);
        $this->assertSame(['err', 'too_fast'], $this->act($pub, 0, ['do' => 'answer', 'lock' => 'signal', 'value' => $right], $now + 2.9));
        $this->assertSame(['err', 'bad_answer'], $this->act($pub, 0, ['do' => 'answer', 'lock' => 'signal', 'value' => '.x.'], $now + 4), 'only short and long');
        $pub = $this->ok($pub, 0, ['do' => 'answer', 'lock' => 'signal', 'value' => $right], $now += 3);
        $this->assertTrue($pub['over']);
        $this->assertSame('out', $pub['how']);
        $this->assertSame($right, my_escape_public($pub, [], $now)['locks']['signal']['answer'], 'the answer once it is open');
    }

    /** TC-INT-063 (K-118, AK-92): a player who leaves leaves their unsolved sliding pictures and pipe squares to the one with the least; both still open */
    public function testLeaverLeavesThePuzzles(): void
    {
        $now = self::T0;
        $pub = $this->upTo('bricks', 3, $now);
        $three = [['id' => 'a'], ['id' => 'b'], ['id' => 'c']];
        $theirs = array_keys(array_filter($pub['frags'], fn($f) => $f['p'] === 2 && $f['k'] === 'slide'));
        $this->assertNotEmpty($theirs);
        $held = fn($pub, $p) => count(array_filter($pub['frags'], fn($f) => $f['p'] === $p)) + count(array_filter($pub['items'], fn($i) => $i['p'] === $p));
        $least = $held($pub, 0) <= $held($pub, 1) ? 0 : 1;
        $pub = my_escape_tick($pub, array_slice($three, 0, 2), 'a', $now);
        foreach ($theirs as $k) $this->assertSame($least, $pub['frags'][$k]['p']);
        $this->assertSame('left', end($pub['log'])['k']);
        $pub = $this->solve($pub, 'bricks', $now);
        $pub = $this->solve($pub, 'door', $now);

        $pub = $this->upTo('gas', 3, $now);
        $squares = array_keys(array_filter($pub['locks']['gas']['pub']['cells'], fn($c) => $c[2] === 2));
        $pub = my_escape_tick($pub, array_slice($three, 0, 2), 'a', $now);
        $to = end($pub['log'])['to'];
        foreach ($squares as $c) $this->assertSame($to, $pub['locks']['gas']['pub']['cells'][$c][2], 'K-118: the squares too');
        $this->solve($pub, 'gas', $now);
    }

    /** TC-INT-064 (K-112, AK-85): no screen gets the valve chart, the rings' target, the signal's answer, a walk back or an unsolved digit */
    public function testWhatTheScreensSeeOfThePuzzles(): void
    {
        $now = self::T0;
        $pub = $this->upTo('bricks', 3, $now);
        $view = my_escape_public($pub, [], $now);
        $keys = $this->keys($view);
        foreach (['sec', 'walk', 'digits', 'target', 'answer', 'path', 'code', 'd'] as $never) $this->assertNotContains($never, $keys, "the public view has no $never");
        foreach (['a', 'b', 'c'] as $m) {
            $mine = my_escape_you($pub, $m);
            $this->assertNotContains('d', $this->keys($mine), 'no digit before the puzzle is solved');
            $this->assertNotContains('walk', $this->keys($mine));
        }
        $raw = json_encode($view);
        $this->assertStringNotContainsString(json_encode($pub['locks']['signal']['sec']['answer']), json_encode(array_diff_key($view['locks']['signal'], ['signal' => 1])));
        $this->assertStringNotContainsString('"target"', $raw);
    }
}
