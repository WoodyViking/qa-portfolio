<?php
// The murder mystery over the API (system level): creating and starting "The Last Toast", searching, showing,
// accusing and the end, and above all what each device may see: the board and the players never get the drawn
// culprit, the weapon or the order of the clues, and a player never gets another player's clues.
// The test reads the drawn variant from the database (the oracle), as no device can.
// Traceability: US-17, K-13, K-53, K-54, K-55..K-62, K-64, AK-43..AK-50.
use PHPUnit\Framework\TestCase;

final class MysteryApiTest extends TestCase
{
    private const BOB  = [TEST_BOB, 'Bob'];
    private const ERIK = [TEST_ERIK, 'Erik'];
    private const ALICE = [TEST_ALICE, 'Alice'];
    private const SUSPECTS = ['oskar', 'agnes', 'niels', 'willem', 'margaret'];
    private const WEAPONS = ['falcon', 'poker', 'telescope'];

    /** A mystery with Bob as host and player, a board and Erik let in (and $more): [host token, board token, Erik's token, code, [more tokens]]. */
    private function table(int $len = 60, array $more = []): array
    {
        [$status, $g] = my_api('POST', 'create', ['category' => 'mystery', 'mystery' => 'last-toast', 'lengthMin' => $len, 'waitMin' => 5,
                                                  'hostPlays' => true, 'boardLang' => 'sv'], self::BOB);
        $this->assertSame(200, $status, json_encode($g));
        [, $b] = my_api('POST', 'join', ['code' => $g['code'], 'kind' => 'board', 'name' => 'TV']);
        [, $e] = my_api('POST', 'join', ['code' => $g['code'], 'kind' => 'player'], self::ERIK);
        my_api('POST', 'approve', ['device' => $b['view']['you']['id'], 'ok' => true], self::BOB, $g['token']);
        my_api('POST', 'approve', ['device' => $e['view']['you']['id'], 'ok' => true], self::BOB, $g['token']);
        $tokens = [];
        foreach ($more as $who) {
            [, $j] = my_api('POST', 'join', ['code' => $g['code'], 'kind' => 'player'], $who);
            my_api('POST', 'approve', ['device' => $j['view']['you']['id'], 'ok' => true], self::BOB, $g['token']);
            $tokens[] = $j['token'];
        }
        return [$g['token'], $b['token'], $e['token'], $g['code'], $tokens];
    }

    /** Start, and return the session's server-side public state (the oracle). */
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

    /** [who has the turn as [login, token], the other] */
    private function turn(string $code, array $tokens): array
    {
        $who = $this->secret($code)['players'][$this->secret($code)['turn']]['id'];
        return $who === TEST_BOB ? [[self::BOB, $tokens[0]], [self::ERIK, $tokens[1]]] : [[self::ERIK, $tokens[1]], [self::BOB, $tokens[0]]];
    }

    private function view(?array $as, string $token): array
    {
        [$status, $v] = my_api('GET', 'state', null, $as, $token);
        $this->assertSame(200, $status, json_encode($v));
        return $v['view'];
    }

    /** TC-API-022 (K-55, AK-50, K-51): a mystery is created in one of its lengths, sound, and started with 2-6 players */
    public function testCreateAndStartLimits(): void
    {
        foreach ([30, 120] as $len) {
            [$status, $j] = my_api('POST', 'create', ['category' => 'mystery', 'mystery' => 'last-toast', 'lengthMin' => $len, 'waitMin' => 5, 'boardLang' => 'en'], self::BOB);
            $this->assertSame([400, 'bad_length'], [$status, $j['error']], "$len min");
        }
        [$status, $j] = my_api('POST', 'create', ['category' => 'mystery', 'mystery' => '../ludo', 'lengthMin' => 60, 'waitMin' => 5, 'boardLang' => 'en'], self::BOB);
        $this->assertSame([400, 'bad_mystery'], [$status, $j['error']]);
        [$status, $g] = my_api('POST', 'create', ['category' => 'mystery', 'lengthMin' => 90, 'waitMin' => 5, 'boardLang' => 'en'], self::BOB);
        $this->assertSame(200, $status, 'the first mystery is the default');
        [$status, $j] = my_api('POST', 'start', [], self::BOB, $g['token']);
        $this->assertSame([409, 'mystery_players'], [$status, $j['error']], 'one player is too few (AK-50)');
        $this->assertSame('lobby', $this->view(self::BOB, $g['token'])['session']['status']);
    }

    /** TC-API-023 (K-56, K-53, AK-43): after the start no device gets the culprit, the weapon or the order of the clues */
    public function testTheVariantStaysOnTheServer(): void
    {
        [$host, $board, $erik, $code] = $this->table();
        $secret = $this->start($host, $code);
        $this->assertContains($secret['variant']['culprit'], self::SUSPECTS);
        $this->assertContains($secret['variant']['weapon'], self::WEAPONS);
        foreach ([[null, $board], [self::BOB, $host], [self::ERIK, $erik]] as [$as, $token]) {
            $v = $this->view($as, $token);
            $raw = json_encode($v);
            foreach (['variant', 'culprit', 'stacks', '"v"', 'guilty', 'innocent', TEST_BOB, TEST_ERIK] as $never) {
                $this->assertStringNotContainsString($never, $raw, "the view of " . ($as[1] ?? 'the board') . " has $never");
            }
            $this->assertCount(5, $v['board']['shown'], 'the five statements are known to all (K-57)');
            $this->assertNull($v['board']['shown'][0]['by']);
            $this->assertSame(['en', 'sv', 'da', 'nl'], array_keys($v['board']['shown'][0]['text']), 'K-64');
            $this->assertSame(['lobby' => 5, 'salon' => 1, 'library' => 3, 'office' => 2, 'corridor' => 0, 'kitchen' => 3,
                               'tower' => 8, 'rooms' => 3, 'outside' => 2], $v['board']['left']);
            $this->assertArrayNotHasKey('solution', $v['board']);
            [, $c] = my_api('GET', 'content', null, $as, $token, '&lang=sv');
            $raw = json_encode($c);
            $this->assertSame('Den sista skålen', $c['content']['texts']['title']);
            foreach (['solution', 'epilogue', 'clue.', 'guilty'] as $never) $this->assertStringNotContainsString($never, $raw, "content has $never");
        }
        $this->assertArrayNotHasKey('private', $this->view(null, $board));
    }

    /** TC-API-024 (K-58, K-13, AK-44, AK-45): the player whose turn it is searches a place and only they get its clue */
    public function testSearch(): void
    {
        [$host, $board, $erik, $code] = $this->table();
        $secret = $this->start($host, $code);
        [[$me, $mine], [$other, $theirs]] = $this->turn($code, [$host, $erik]);
        [$status, $j] = my_api('POST', 'act', ['do' => 'search', 'place' => 'tower'], $other, $theirs);
        $this->assertSame([400, 'not_your_turn'], [$status, $j['error']]);                               // AK-45
        foreach ([['do' => 'search', 'place' => 'cellar'], ['do' => 'search'], ['do' => 'dance']] as $bad) {
            [$status] = my_api('POST', 'act', $bad, $me, $mine);
            $this->assertSame(400, $status, json_encode($bad));
        }
        [$status, $j] = my_api('POST', 'act', ['do' => 'search', 'place' => 'corridor'], $me, $mine);
        $this->assertSame([400, 'place_empty'], [$status, $j['error']]);

        [$status, $j] = my_api('POST', 'act', ['do' => 'search', 'place' => 'tower'], $me, $mine);
        $this->assertSame(200, $status, json_encode($j));
        $card = $j['view']['private']['clues'][0];
        $this->assertSame($secret['stacks']['tower'][0], $card['id'], 'the first clue of the shuffled stack');
        $this->assertSame(['id', 'title', 'text', 'place', 'from', 'image'], array_keys($card));
        $this->assertSame('tower', $card['place']);
        $this->assertNotSame('', $card['text']['sv']);

        $tv = $this->view(null, $board);
        $this->assertSame(7, $tv['board']['left']['tower']);
        $this->assertSame('tower', $tv['board']['players'][$secret['turn']]['at']);
        $this->assertSame(['k' => 'search', 'p' => $secret['turn'], 'place' => 'tower', 'n' => 1], $tv['board']['log'][0]);
        $this->assertStringNotContainsString($card['id'], json_encode($tv), 'the board does not know which clue was found');
        $this->assertSame([], $this->view($other, $theirs)['private']['clues'], 'K-13');
        $this->assertSame(1 - $secret['turn'], $tv['board']['turn'], 'the turn passes');
        $this->assertTrue($this->view($other, $theirs)['mine']['turn']);
    }

    /** TC-API-025 (K-59, AK-46): a player shows one of their clues to all; it is on the board and with every player */
    public function testShow(): void
    {
        [$host, $board, $erik, $code] = $this->table();
        $this->start($host, $code);
        [[$me, $mine], [$other, $theirs]] = $this->turn($code, [$host, $erik]);
        [, $j] = my_api('POST', 'act', ['do' => 'search', 'place' => 'lobby'], $me, $mine);
        $card = $j['view']['private']['clues'][0];
        [$status, $j] = my_api('POST', 'act', ['do' => 'show', 'clue' => 'not_mine'], $me, $mine);
        $this->assertSame([400, 'not_yours'], [$status, $j['error']]);
        [$status, $j] = my_api('POST', 'act', ['do' => 'show', 'clue' => $card['id']], $other, $theirs);
        $this->assertSame([400, 'not_yours'], [$status, $j['error']], 'only the finder can show it');
        [$status] = my_api('POST', 'act', ['do' => 'show', 'clue' => $card['id']], $me, $mine);
        $this->assertSame(200, $status, 'showing needs no turn');
        [$status, $j] = my_api('POST', 'act', ['do' => 'show', 'clue' => $card['id']], $me, $mine);
        $this->assertSame([400, 'already_shown'], [$status, $j['error']]);
        foreach ([[null, $board], [$other, $theirs]] as [$as, $token]) {
            $shown = $this->view($as, $token)['board']['shown'];
            $this->assertCount(6, $shown);
            $this->assertSame($card['text'], $shown[5]['text']);
            $this->assertSame($me[1], $shown[5]['by']);
        }
    }

    /** TC-API-026 (K-60, K-61, AK-48, AK-49): wrong accusations: only the accuser learns it; all wrong ends the game */
    public function testWrongAccusations(): void
    {
        [$host, $board, $erik, $code] = $this->table();
        $secret = $this->start($host, $code);
        $wrong = ['do' => 'accuse', 'culprit' => $secret['variant']['culprit'], 'weapon' => current(array_diff(self::WEAPONS, [$secret['variant']['weapon']]))];
        [[$me, $mine], [$other, $theirs]] = $this->turn($code, [$host, $erik]);
        foreach ([['culprit' => 'viktor', 'weapon' => 'poker'], ['culprit' => 'oskar'], ['culprit' => 'oskar', 'weapon' => 'rope']] as $bad) {
            [$status, $j] = my_api('POST', 'act', ['do' => 'accuse'] + $bad, $me, $mine);
            $this->assertSame([400, 'bad_accusation'], [$status, $j['error']], json_encode($bad));
        }
        [$status, $j] = my_api('POST', 'act', $wrong, $other, $theirs);
        $this->assertSame([400, 'not_your_turn'], [$status, $j['error']]);
        [$status, $j] = my_api('POST', 'act', $wrong, $me, $mine);
        $this->assertSame(200, $status);
        $this->assertFalse($j['view']['private']['accused']['right']);                                  // AK-48
        $this->assertTrue($j['view']['mine']['out']);
        $tv = $this->view(null, $board);
        $this->assertSame('playing', $tv['session']['status']);
        $this->assertTrue($tv['board']['players'][$secret['turn']]['out']);
        $this->assertSame(['k' => 'accuse', 'p' => $secret['turn'], 'right' => false, 'n' => 1], $tv['board']['log'][0], 'never what was guessed');
        $this->assertNull($this->view($other, $theirs)['private']['accused']);

        my_api('POST', 'act', ['do' => 'search', 'place' => 'tower'], $other, $theirs);                // the turn comes back
        [$status, $j] = my_api('POST', 'act', $wrong, $me, $mine);
        $this->assertSame([400, 'already_accused'], [$status, $j['error']]);
        [$status] = my_api('POST', 'act', ['do' => 'search', 'place' => 'tower'], $me, $mine);
        $this->assertSame(200, $status, 'a wrong accuser still searches');

        [$status, $j] = my_api('POST', 'act', $wrong, $other, $theirs);                                   // AK-49
        $this->assertSame(200, $status);
        $tv = $this->view(null, $board);
        $this->assertSame('ended', $tv['session']['status']);
        $this->assertSame([true, null, 'all_wrong'], [$tv['board']['over'], $tv['board']['winner'], $tv['board']['how']]);
        $this->assertSame($secret['variant'], $tv['board']['solution']['variant']);
        $this->assertStringContainsString('Viktor', $tv['board']['solution']['text']['sv']['epilogue']);
        [$status, $j] = my_api('POST', 'act', ['do' => 'search', 'place' => 'tower'], $me, $mine);
        $this->assertSame([409, 'not_playing'], [$status, $j['error']]);
    }

    /** TC-API-027 (K-60, K-61, AK-47): the right accusation wins and every screen gets the solution */
    public function testRightAccusationWins(): void
    {
        [$host, $board, $erik, $code, [$alice]] = $this->table(90, [self::ALICE]);
        $secret = $this->start($host, $code);
        $this->assertCount(3, $secret['players']);
        $who = $secret['players'][$secret['turn']]['id'];
        [$as, $token] = [TEST_BOB => [self::BOB, $host], TEST_ERIK => [self::ERIK, $erik], TEST_ALICE => [self::ALICE, $alice]][$who];
        [$status, $j] = my_api('POST', 'act', ['do' => 'accuse'] + $secret['variant'], $as, $token);
        $this->assertSame(200, $status, json_encode($j));
        $this->assertTrue($j['view']['private']['accused']['right']);
        $tv = $this->view(null, $board);
        $this->assertSame('ended', $tv['session']['status']);
        $this->assertSame([$secret['turn'], 'solved'], [$tv['board']['winner'], $tv['board']['how']]);
        $this->assertCount(2, $tv['board']['solution']['text']['en']['parts']);
        $this->assertStringContainsString('killed', $tv['board']['solution']['text']['en']['parts'][0]);
    }

    /** TC-API-028 (K-62, K-13): the notebook is private and kept; only suspects and weapons can be crossed out */
    public function testNotebook(): void
    {
        [$host, $board, $erik, $code] = $this->table();
        $this->start($host, $code);
        [$status, $j] = my_api('POST', 'act', ['do' => 'note', 'id' => 'oskar', 'crossed' => true], self::ERIK, $erik);
        $this->assertSame(200, $status, 'notes need no turn');
        my_api('POST', 'act', ['do' => 'note', 'id' => 'poker', 'crossed' => true], self::ERIK, $erik);
        [, $j] = my_api('POST', 'act', ['do' => 'note', 'id' => 'oskar', 'crossed' => false], self::ERIK, $erik);
        $this->assertSame(['poker'], $j['view']['private']['crossed']);
        [$status] = my_api('POST', 'act', ['do' => 'note', 'id' => 'signe', 'crossed' => true], self::ERIK, $erik);
        $this->assertSame(400, $status, 'a witness is no suspect');
        my_api('POST', 'pause', [], self::BOB, $host);
        my_api('POST', 'resume', [], self::BOB, $host);
        $this->assertSame(['poker'], $this->view(self::ERIK, $erik)['private']['crossed'], 'K-15');
        $this->assertSame([], $this->view(self::BOB, $host)['private']['crossed']);
        $this->assertStringNotContainsString('poker', json_encode($this->view(null, $board)['board']['log']));
    }

    /** TC-API-029 (K-61): only the host ends the game; then everyone gets the solution */
    public function testHostEnds(): void
    {
        [$host, $board, $erik, $code] = $this->table();
        [$status, $j] = my_api('POST', 'end', [], self::BOB, $host);
        $this->assertSame([409, 'not_playing'], [$status, $j['error']], 'not from the lobby');
        $secret = $this->start($host, $code);
        [$status, $j] = my_api('POST', 'end', [], self::ERIK, $erik);
        $this->assertSame([403, 'not_host'], [$status, $j['error']]);
        [$status] = my_api('POST', 'end', [], self::BOB, $host);
        $this->assertSame(200, $status);
        $v = $this->view(self::ERIK, $erik);
        $this->assertSame(['ended', 'host'], [$v['session']['status'], $v['board']['how']]);
        $this->assertSame($secret['variant'], $v['board']['solution']['variant']);
    }

    /** TC-API-030 (K-58, US-05): when the player whose turn it is leaves, the turn goes to the next one at the table */
    public function testTurnSkipsAPlayerWhoLeft(): void
    {
        [$host, $board, $erik, $code] = $this->table();
        $this->start($host, $code);
        if ($this->secret($code)['players'][$this->secret($code)['turn']]['id'] !== TEST_ERIK) {
            my_api('POST', 'act', ['do' => 'search', 'place' => 'tower'], self::BOB, $host);
        }
        my_api('POST', 'leave', [], self::ERIK, $erik);
        $tv = $this->view(null, $board);
        $this->assertSame('away', $tv['board']['players'][1 - $tv['board']['turn']]['place']);
        $this->assertSame('Bob', $tv['board']['players'][$tv['board']['turn']]['name']);
        $this->assertSame('skip', end($tv['board']['log'])['k']);
    }

    /** TC-API-031 (K-65, AK-51): only the host sets the rules, only in the lobby, and every screen sees them */
    public function testRulesInTheLobby(): void
    {
        [$host, $board, $erik, $code] = $this->table();
        [$status, $j] = my_api('POST', 'setup', ['rules' => ['play' => 'together']], self::ERIK, $erik);
        $this->assertSame([403, 'not_host'], [$status, $j['error']]);
        [$status, $j] = my_api('POST', 'setup', ['rules' => ['play' => 'everyone']], self::BOB, $host);
        $this->assertSame([400, 'bad_rule'], [$status, $j['error']]);
        [$status] = my_api('POST', 'setup', ['rules' => ['play' => 'together', 'turns' => 'free', 'move' => 'doors']], self::BOB, $host);
        $this->assertSame(200, $status);
        $want = ['play' => 'together', 'turns' => 'free', 'wrong' => 'out', 'ask' => 'yes', 'move' => 'doors'];
        $this->assertSame($want, $this->view(null, $board)['board']['rules'], 'the TV');
        $this->assertSame($want, $this->view(self::ERIK, $erik)['board']['rules'], "Erik's phone");
        $this->start($host, $code);
        [$status, $j] = my_api('POST', 'setup', ['rules' => ['play' => 'against']], self::BOB, $host);
        $this->assertSame([409, 'not_lobby'], [$status, $j['error']]);
        [$status] = my_api('POST', 'act', ['do' => 'search', 'place' => 'library'], self::ERIK, $erik);
        $this->assertSame(200, $status, 'no turns: Erik searches at once');
        $v = $this->view(null, $board)['board'];
        $this->assertNull($v['turn']);
        $this->assertSame('Erik', end($v['shown'])['by'], 'together: what Erik found is on the TV');

        [, $g] = my_api('POST', 'create', ['category' => 'ludo', 'lengthMin' => 30, 'waitMin' => 5, 'boardLang' => 'en'], self::BOB);
        [$status, $j] = my_api('POST', 'setup', ['rules' => ['play' => 'together']], self::BOB, $g['token']);
        $this->assertSame([400, 'nothing_to_set'], [$status, $j['error']]);
    }

    /** TC-API-032 (K-69, K-13, AK-54): the clue given in answer to a question reaches only the phone of the one who asked */
    public function testAskOverTheApi(): void
    {
        [$host, $board, $erik, $code] = $this->table();
        $this->start($host, $code);
        [[$me, $mine], [$other, $theirs]] = $this->turn($code, [$host, $erik]);
        my_api('POST', 'act', ['do' => 'search', 'place' => 'tower'], $me, $mine);           // the first finds a clue
        $id = $this->view($me, $mine)['private']['clues'][0]['id'];
        [$status, $j] = my_api('POST', 'act', ['do' => 'ask', 'to' => $this->view($me, $mine)['mine']['player'], 'place' => 'tower'], $other, $theirs);
        $this->assertSame(200, $status, json_encode($j));
        $this->assertSame('tower', $this->view($me, $mine)['mine']['answer'][0]['place']);
        [$status] = my_api('POST', 'act', ['do' => 'answer', 'clue' => $id], $me, $mine);
        $this->assertSame(200, $status);
        $this->assertSame([$id], array_column($this->view($other, $theirs)['mine']['given'], 'id'));
        $this->assertSame([], $this->view($me, $mine)['mine']['given']);
        $this->assertStringNotContainsString($id, json_encode($this->view(null, $board)), 'not on the TV');
    }
}
