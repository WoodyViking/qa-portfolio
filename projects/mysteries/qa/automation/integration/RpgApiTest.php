<?php
// The role-playing game over the API (system level): creating "The Stolen Hammer", the heroes in the lobby saved on
// the account, the start, what each device may see, the pure game master and an adventure played to its end that
// raises the saved heroes one level. The test reads and sets the game in the database (the oracle), as no device can.
// Traceability: US-20, US-21, US-22, K-89, K-90, K-92, K-93, K-95, K-96, K-99, K-101, K-102, AK-71, AK-76, AK-78.
use PHPUnit\Framework\TestCase;

require_once '/srv/mysteries/modules/rpg.inc.php';

final class RpgApiTest extends TestCase
{
    private const ALICE = [TEST_ALICE, 'Alice'];
    private const BOB   = [TEST_BOB, 'Bob'];
    private const ERIK  = [TEST_ERIK, 'Erik'];

    protected function setUp(): void
    {
        get_pdo()->prepare('DELETE FROM mysteries_heroes WHERE discord_id IN (?, ?)')->execute([TEST_BOB, TEST_ERIK]);
    }

    /** An adventure with $host as host (playing, or as a pure game master), a board, and Bob and Erik let in: [host, board, bob, erik, code]. */
    private function table(array $host, bool $plays = true): array
    {
        [$status, $g] = my_api('POST', 'create', ['category' => 'rpg', 'adventure' => 'stolen-hammer', 'lengthMin' => 60, 'waitMin' => 5,
                                                  'hostPlays' => $plays, 'boardLang' => 'sv'], $host);
        $this->assertSame(200, $status, json_encode($g));
        [, $b] = my_api('POST', 'join', ['code' => $g['code'], 'kind' => 'board', 'name' => 'TV']);
        my_api('POST', 'approve', ['device' => $b['view']['you']['id'], 'ok' => true], $host, $g['token']);
        $t = ['bob' => $host === self::BOB ? $g['token'] : null, 'erik' => null];
        foreach (['bob' => self::BOB, 'erik' => self::ERIK] as $k => $as) {
            if ($t[$k]) continue;
            [, $j] = my_api('POST', 'join', ['code' => $g['code'], 'kind' => 'player'], $as);
            my_api('POST', 'approve', ['device' => $j['view']['you']['id'], 'ok' => true], $host, $g['token']);
            $t[$k] = $j['token'];
        }
        return [$g['token'], $b['token'], $t['bob'], $t['erik'], $g['code']];
    }

    private function hero(array $as, string $token, string $cls, string $name, string $folk = 'dwarf', ?string $attr = 'wits'): array
    {
        return my_api('POST', 'choose', ['do' => 'new', 'cls' => $cls, 'folk' => $folk, 'name' => $name, 'attr' => $attr], $as, $token);
    }

    /** Bob: a farmer and a viking; Erik: a völva and a skald. */
    private function party(string $bob, string $erik): void
    {
        foreach ([[self::BOB, $bob, 'farmer', 'Ragnar'], [self::BOB, $bob, 'viking', 'Sigrid'],
                  [self::ERIK, $erik, 'volva', 'Groa'], [self::ERIK, $erik, 'skald', 'Brage']] as [$as, $token, $cls, $name]) {
            [$status, $j] = $this->hero($as, $token, $cls, $name);
            $this->assertSame(200, $status, json_encode($j));
        }
    }

    private function start(array $host, string $token): void
    {
        [$status, $j] = my_api('POST', 'start', [], $host, $token);
        $this->assertSame(200, $status, json_encode($j));
    }

    private function secret(string $code): array
    {
        $s = get_pdo()->prepare('SELECT public_state FROM mysteries_sessions WHERE code = ?');
        $s->execute([$code]);
        return json_decode($s->fetchColumn(), true);
    }

    private function put(string $code, array $pub): void
    {
        get_pdo()->prepare('UPDATE mysteries_sessions SET public_state = ?, version = version + 1 WHERE code = ?')->execute([json_encode($pub), $code]);
    }

    private function view(?array $as, string $token): array
    {
        [$status, $v] = my_api('GET', 'state', null, $as, $token);
        $this->assertSame(200, $status, json_encode($v));
        return $v['view'];
    }

    private function saved(string $member): array
    {
        $s = get_pdo()->prepare('SELECT name, class, level, items FROM mysteries_heroes WHERE discord_id = ? ORDER BY id');
        $s->execute([$member]);
        return $s->fetchAll(PDO::FETCH_ASSOC);
    }

    private function keys(array $a): array
    {
        $k = [];
        $walk = function ($x) use (&$walk, &$k) { foreach ($x as $key => $v) { $k[] = $key; if (is_array($v)) $walk($v); } };
        $walk($a);
        return $k;
    }

    /** TC-API-038 (K-95, K-96, K-90): an adventure in one of its lengths, the first one by default; the files are never sent; 2-6 players */
    public function testCreateAndStartLimits(): void
    {
        [$status, $j] = my_api('POST', 'create', ['category' => 'rpg', 'lengthMin' => 30, 'waitMin' => 5, 'boardLang' => 'en'], self::BOB);
        $this->assertSame([400, 'bad_length'], [$status, $j['error']], '60 and 90 min only');
        [$status, $j] = my_api('POST', 'create', ['category' => 'rpg', 'adventure' => '../last-toast', 'lengthMin' => 60, 'waitMin' => 5, 'boardLang' => 'en'], self::BOB);
        $this->assertSame([400, 'bad_adventure'], [$status, $j['error']]);
        [$status, $g] = my_api('POST', 'create', ['category' => 'rpg', 'lengthMin' => 90, 'waitMin' => 5, 'boardLang' => 'en'], self::BOB);
        $this->assertSame(200, $status, 'the first adventure is the default');
        $this->assertSame(['stolen-hammer', 90], [$g['view']['board']['adv'], $g['view']['board']['len']]);
        $this->assertSame(['lobby' => true, 'mine' => [], 'saved' => [], 'host' => true], $g['view']['mine']);
        [$status, $j] = my_api('POST', 'start', [], self::BOB, $g['token']);
        $this->assertSame([409, 'rpg_players'], [$status, $j['error']], 'one player is too few');
        foreach (['stolen-hammer/adventure.json', 'vikings/heroes.json', 'stolen-hammer/sv.json'] as $f) {
            [$status] = test_get("/mysteries/content/$f");
            $this->assertSame(403, $status, "$f is never sent");
        }
    }

    /** TC-API-039 (K-90, K-92, K-93, AK-71): heroes made in the lobby are saved on the account; a third is refused; the host renames */
    public function testLobbyHeroesAreSaved(): void
    {
        [$host, $board, $bob, $erik, $code] = $this->table(self::BOB);
        $this->hero(self::BOB, $bob, 'farmer', 'Ragnar');
        $this->hero(self::BOB, $bob, 'farmer', 'Torgrim');
        [$status, $j] = $this->hero(self::BOB, $bob, 'viking', 'Sigrid');
        $this->assertSame([400, 'rpg_two'], [$status, $j['error']], 'at most two heroes a player');
        [$status, $j] = $this->hero(self::ERIK, $erik, 'volva', 'Groa', 'norse', null);
        $this->assertSame([400, 'bad_attr'], [$status, $j['error']], 'a norse hero picks the attribute for the +1');
        $this->hero(self::ERIK, $erik, 'volva', 'Groa', 'norse', 'wits');
        $this->assertSame([['name' => 'Ragnar', 'class' => 'farmer', 'level' => 1, 'items' => '[]'], ['name' => 'Torgrim', 'class' => 'farmer', 'level' => 1, 'items' => '[]']],
                          $this->saved(TEST_BOB));
        $tv = $this->view(null, $board)['board'];
        $this->assertSame(['Bob', 'Bob', 'Erik'], array_column($tv['heroes'], 'player'));
        $this->assertSame(['rpg.party_few'], array_column($tv['problems'], 'k'), 'three heroes (two farmers are named from four)');
        [$status, $j] = my_api('POST', 'start', [], self::BOB, $host);
        $this->assertSame([409, 'rpg_party'], [$status, $j['error']]);
        my_api('POST', 'choose', ['do' => 'drop', 'i' => 1], self::BOB, $bob);
        $this->hero(self::BOB, $bob, 'viking', 'Sigrid');
        $this->hero(self::ERIK, $erik, 'skald', 'Brage');
        [$status] = my_api('POST', 'setup', ['rename' => ['i' => 0, 'name' => 'Ragnar <Lodbrok>']], self::BOB, $host);
        $this->assertSame(200, $status);
        $this->assertSame('Ragnar Lodbrok', $this->saved(TEST_BOB)[0]['name'], 'K-93: renamed on the account too, without markup');
        $this->assertSame([], $this->view(null, $board)['board']['problems']);
        $this->start(self::BOB, $host);
        $this->assertSame(['village', 'vote'], [$this->view(null, $board)['board']['scene'], $this->view(null, $board)['board']['step']['type']]);
        [$status, $j] = my_api('POST', 'choose', ['do' => 'new', 'cls' => 'hunter', 'folk' => 'dwarf', 'name' => 'Ulf'], self::ERIK, $erik);
        $this->assertSame([409, 'not_lobby'], [$status, $j['error']]);
        $this->assertCount(3, $this->saved(TEST_BOB), 'Torgrim stays saved for another time');
        $s = get_pdo()->query("SELECT DELETE_RULE FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE TABLE_NAME = 'mysteries_heroes'");
        $this->assertSame('CASCADE', $s->fetchColumn(), 'a deleted account takes its heroes along');
    }

    /** TC-API-040 (K-99, K-89, AK-76): the TV and the phones never get a draw, a task of another hero or an account id; a die from a phone is ignored */
    public function testWhatEachDeviceGets(): void
    {
        [$host, $board, $bob, $erik, $code] = $this->table(self::BOB);
        $this->party($bob, $erik);
        $this->start(self::BOB, $host);
        $secret = $this->secret($code);
        foreach ([[null, $board], [self::BOB, $bob], [self::ERIK, $erik]] as [$as, $token]) {
            $v = $this->view($as, $token);
            $this->assertArrayNotHasKey('master', $v);
            $keys = $this->keys($v['board']);
            foreach (['draw', 'task', 'clues', 'secrets', 'riddles', 'spot'] as $never) $this->assertNotContains($never, $keys, ($as[1] ?? 'the TV') . " sees $never");
            $raw = json_encode($v);
            foreach ([TEST_BOB, TEST_ERIK] as $never) $this->assertStringNotContainsString($never, $raw);
            if (!$as) continue;
            $mine = array_column($v['mine']['heroes'], 'i');
            $this->assertSame($as === self::BOB ? [0, 1] : [2, 3], $mine);
            foreach ($v['mine']['heroes'] as $x) $this->assertSame($secret['heroes'][$x['i']]['task'], $x['task'], 'its own task');
        }
        [$status, $j] = my_api('POST', 'lead', ['do' => 'take'], self::BOB, $host);
        $this->assertSame([403, 'not_master'], [$status, $j['error']], 'a host who plays does not lead');
        foreach ([0 => 'pass', 1 => 'pass', 2 => 'pass'] as $hi => $o) my_api('POST', 'act', ['hero' => $hi, 'do' => 'vote', 'option' => $o], $hi < 2 ? self::BOB : self::ERIK, $hi < 2 ? $bob : $erik);
        [$status, $j] = my_api('POST', 'act', ['hero' => 3, 'do' => 'vote', 'option' => 'pass'], self::ERIK, $erik);
        $this->assertSame(200, $status, json_encode($j));
        $this->assertSame(['pass', 'check'], [$j['view']['board']['scene'], $j['view']['board']['step']['type']]);
        [$status, $j] = my_api('POST', 'act', ['hero' => 0, 'do' => 'roll', 'd' => [20], 'total' => 99, 'ok' => true], self::ERIK, $erik);
        $this->assertSame([400, 'not_yours'], [$status, $j['error']]);
        [$status, $j] = my_api('POST', 'act', ['hero' => 2, 'do' => 'roll', 'd' => [20], 'total' => 99, 'ok' => true], self::ERIK, $erik);
        $this->assertSame(200, $status, json_encode($j));
        $roll = $this->secret($code)['st']['rolls'][2] ?? null;
        $this->assertNotNull($roll, 'the roll is on the TV');
        $this->assertSame($roll['pick'] + $secret['heroes'][2]['attrs']['wits'], $roll['total'], 'the server rolled');
    }

    /** TC-API-041 (K-101, K-102, AK-76): a pure game master sees everything and leads; nobody else may */
    public function testThePureGameMaster(): void
    {
        [$alice, $board, $bob, $erik, $code] = $this->table(self::ALICE, false);
        $this->party($bob, $erik);
        $this->start(self::ALICE, $alice);
        $v = $this->view(self::ALICE, $alice);
        $this->assertSame('master', $v['you']['kind']);
        $this->assertSame([], $v['mine']['heroes'], 'the game master has no hero');
        $secret = $this->secret($code);
        $this->assertSame($secret['draw'], $v['master']['state']['draw'], 'the draws');
        $this->assertSame(array_column($secret['heroes'], 'task'), array_column($v['master']['state']['heroes'], 'task'), 'every task');
        $this->assertContains('finale', $v['master']['scenes']);
        foreach ([TEST_BOB, TEST_ERIK] as $never) $this->assertStringNotContainsString($never, json_encode($v), 'no account ids, not even here');
        [$status, $j] = my_api('POST', 'lead', ['do' => 'take'], self::BOB, $bob);
        $this->assertSame([403, 'not_host'], [$status, $j['error']]);
        [$status, $j] = my_api('POST', 'lead', ['do' => 'take'], self::ALICE, $alice);
        $this->assertSame(200, $status, json_encode($j));
        [$status] = my_api('POST', 'lead', ['do' => 'say', 'text' => 'Trym ler brett.'], self::ALICE, $alice);
        $this->assertSame(200, $status);
        $tv = $this->view(null, $board)['board'];
        $this->assertSame([true, 'Trym ler brett.'], [$tv['gm']['on'], $tv['gm']['say']['text']]);
        [$status] = my_api('POST', 'lead', ['do' => 'next', 'scene' => 'wood'], self::ALICE, $alice);
        $this->assertSame(200, $status);
        $this->assertSame('wood', $this->view(null, $board)['board']['scene']);
        [$status, $j] = my_api('POST', 'act', ['hero' => 0, 'do' => 'roll'], self::ALICE, $alice);
        $this->assertSame([403, 'not_player'], [$status, $j['error']]);
    }

    /** TC-API-042 (K-92, AK-78): played to the end, every saved hero is one level up with its gift, and comes back so in the next lobby */
    public function testPlayedToTheEndLevelsUp(): void
    {
        [$host, $board, $bob, $erik, $code] = $this->table(self::BOB);
        $this->party($bob, $erik);
        $this->start(self::BOB, $host);
        $pub = $this->secret($code);                                    // the oracle: the box in the cellar, the hammer found and the party seen
        $pub['scene'] = 'cellar';
        $pub['flags'] += ['revealed' => true, 'hammer' => true];
        $pub = my_rpg_begin_step($pub, 1, new Random\Randomizer(new Random\Engine\Mt19937(2)), microtime(true));
        $this->assertSame(['box', 'check'], [$pub['st']['id'], $pub['st']['type']]);
        $this->put($code, $pub);
        [$status, $j] = my_api('POST', 'act', ['hero' => 2, 'do' => 'roll'], self::ERIK, $erik);
        $this->assertSame(200, $status, json_encode($j));
        $tv = $j['view']['board'];
        $this->assertSame(['ended', true, 'done'], [$j['view']['session']['status'], $tv['over'], $tv['how']]);
        $this->assertSame([2, 2, 2, 2], $tv['end']['levels']);
        $rows = array_merge($this->saved(TEST_BOB), $this->saved(TEST_ERIK));
        $this->assertSame([2, 2, 2, 2], array_map('intval', array_column($rows, 'level')), 'saved on the accounts');
        foreach ($rows as $i => $r) $this->assertSame([$tv['end']['gifts'][$i]], json_decode($r['items'], true));
        [$status, $j] = my_api('POST', 'end', [], self::BOB, $host);
        $this->assertSame([409, 'not_playing'], [$status, $j['error']], 'already ended');

        [$host2, , $bob2] = $this->table(self::BOB);
        $saved = $this->view(self::BOB, $bob2)['mine']['saved'];
        $this->assertSame([2, 2], array_column($saved, 'level'));
        [$status, $j] = my_api('POST', 'choose', ['do' => 'saved', 'id' => $saved[0]['id']], self::BOB, $bob2);
        $this->assertSame(200, $status, json_encode($j));
        $this->assertSame(['Ragnar', 2], [$j['view']['board']['heroes'][0]['name'], $j['view']['board']['heroes'][0]['level']]);
        [$status, $j] = my_api('POST', 'choose', ['do' => 'saved', 'id' => $saved[0]['id']], self::BOB, $bob2);
        $this->assertSame([400, 'rpg_in_party'], [$status, $j['error']]);
        [$status, $j] = my_api('POST', 'choose', ['do' => 'saved', 'id' => (int)get_pdo()->query('SELECT MAX(id) FROM mysteries_heroes')->fetchColumn() + 1], self::BOB, $bob2);
        $this->assertSame([400, 'rpg_no_hero'], [$status, $j['error']], 'no such hero');
    }

    /** TC-API-043 (K-92, K-15): ended by the host: no level; a pause holds the game */
    public function testHostEndGivesNoLevel(): void
    {
        [$host, $board, $bob, $erik, $code] = $this->table(self::BOB);
        $this->party($bob, $erik);
        $this->start(self::BOB, $host);
        my_api('POST', 'pause', [], self::BOB, $host);
        [$status] = my_api('POST', 'act', ['hero' => 0, 'do' => 'vote', 'option' => 'pass'], self::BOB, $bob);
        $this->assertNotSame(200, $status, 'no moves during a pause');
        my_api('POST', 'resume', [], self::BOB, $host);
        [$status, $j] = my_api('POST', 'end', [], self::ERIK, $erik);
        $this->assertSame([403, 'not_host'], [$status, $j['error']]);
        [$status] = my_api('POST', 'end', [], self::BOB, $host);
        $this->assertSame(200, $status);
        $tv = $this->view(null, $board)['board'];
        $this->assertSame(['host', null], [$tv['how'], $tv['end']]);
        $this->assertSame([1, 1], array_map('intval', array_column($this->saved(TEST_BOB), 'level')), 'no level');
    }
}
