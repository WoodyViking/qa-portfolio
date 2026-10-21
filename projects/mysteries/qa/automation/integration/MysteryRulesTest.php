<?php
// The rules the host picks in the lobby for a murder mystery (component level, no database), modules/mystery.inc.php:
// a decision table over the five choices (against/together x turns/free x out/penalty x ask x free/doors), state
// transitions for a question (asked -> answered or taken back) and a vote (proposed -> settled), and boundary values
// for the penalty (2 turns sat out; the 120 s wait one second before and after). The tests set the drawn variant
// and whose turn it is themselves, so nothing depends on chance.
// Traceability: US-17, K-20, K-58, K-60, K-65..K-70, AK-51..AK-57.
use PHPUnit\Framework\TestCase;

require_once '/srv/mysteries/modules/mystery.inc.php';

final class MysteryRulesTest extends TestCase
{
    private const T0 = 1_000_000.0;                      // "now" in the tests
    private const PLAYERS = [['id' => 'a', 'name' => 'Anna'], ['id' => 'b', 'name' => 'Bob'], ['id' => 'c', 'name' => 'Cleo']];
    private const RIGHT = ['culprit' => 'oskar', 'weapon' => 'poker'];
    private const WRONG = ['culprit' => 'agnes', 'weapon' => 'poker'];

    /** A started game of "The Last Toast" (60 min) with these rules, Anna (the host) to play, Oskar with the poker drawn. */
    private function game(array $rules = [], int $players = 3): array
    {
        $lobby = ['mystery' => 'last-toast'];
        if ($rules) {
            $r = my_mystery_setup($lobby, ['rules' => $rules]);
            $this->assertSame('ok', $r[0], json_encode($r));
            $lobby = $r[1];
        }
        $r = my_mystery_start(array_slice(self::PLAYERS, 0, $players), $lobby, ['length_min' => 60, 'host_id' => 'a']);
        $this->assertArrayNotHasKey('error', $r);
        [$pub, $private] = $r;
        $pub['variant'] = self::RIGHT;
        if ($pub['turn'] !== null) $pub['turn'] = 0;
        return [$pub, $private];
    }

    /** A move by player $who (a, b or c) that must work: [public, private]. */
    private function act(array $pub, array &$private, string $who, array $body, float $now = self::T0): array
    {
        $r = my_mystery_act($pub, $private[$who], '', $body, $who, $now);
        $this->assertSame('ok', $r[0], "$who " . json_encode($body) . ': ' . json_encode($r));
        $private[$who] = $r[2];
        return $r[1];
    }

    /** A move that must be refused with $error. */
    private function refused(string $error, array $pub, array $mine, string $who, array $body, float $now = self::T0): void
    {
        $this->assertSame(['err', $error], my_mystery_act($pub, $mine, '', $body, $who, $now), "$who " . json_encode($body));
    }

    /** TC-INT-019 (K-65, AK-51): the host sets the rules in the lobby; every screen sees them; they hold from the start */
    public function testRulesInTheLobby(): void
    {
        $lobby = ['mystery' => 'last-toast'];
        $this->assertSame(['play' => 'against', 'turns' => 'turns', 'wrong' => 'out', 'ask' => 'yes', 'move' => 'free'],
                          my_mystery_public($lobby)['rules'], 'the defaults');
        [$ok, $lobby] = my_mystery_setup($lobby, ['rules' => ['play' => 'together', 'move' => 'doors']]);
        $this->assertSame('ok', $ok);
        [$ok, $lobby] = my_mystery_setup($lobby, ['rules' => ['turns' => 'free']]);
        $this->assertSame(['play' => 'together', 'turns' => 'free', 'wrong' => 'out', 'ask' => 'yes', 'move' => 'doors'],
                          my_mystery_public($lobby)['rules'], 'a second change keeps the first');
        foreach ([[], ['rules' => []], ['rules' => ['play' => 'alone']], ['rules' => ['speed' => 'fast']], ['rules' => 'together']] as $bad) {
            $this->assertSame(['err', 'bad_rule'], my_mystery_setup($lobby, $bad), json_encode($bad));
        }
        [$pub] = my_mystery_start(self::PLAYERS, $lobby, ['length_min' => 60, 'host_id' => 'a']);
        $this->assertSame('free', my_mystery_public($pub)['rules']['turns']);
        $this->assertNull($pub['turn'], 'no turns');
        $this->assertSame(['lobby', 'lobby', 'lobby'], array_column($pub['players'], 'at'), 'with doors everyone starts in the lobby');
        $this->assertSame(['err', 'not_lobby'], my_mystery_setup($pub, ['rules' => ['turns' => 'turns']]), 'not after the start');
    }

    /** TC-INT-020 (K-67, AK-52): without turns everyone searches whenever they like */
    public function testFreeTurns(): void
    {
        [$pub, $private] = $this->game(['turns' => 'free']);
        foreach (['b', 'b', 'c', 'a'] as $who) $pub = $this->act($pub, $private, $who, ['do' => 'search', 'place' => 'tower']);
        $this->assertNull($pub['turn']);
        $this->assertCount(2, $private['b']['clues']);
        foreach (['a', 'b', 'c'] as $who) {
            $you = my_mystery_you($pub, $who, self::PLAYERS, false, self::T0);
            $this->assertTrue($you['turn'] && $you['may'], $who);
        }
    }

    /** TC-INT-021 (K-68, AK-53): with penalty and turns, a wrong accusation sits out the next 2 turns, then may try again */
    public function testPenaltyTurns(): void
    {
        [$pub, $private] = $this->game(['wrong' => 'penalty'], 2);
        $pub = $this->act($pub, $private, 'a', ['do' => 'accuse'] + self::WRONG);
        $this->assertFalse($pub['players'][0]['out']);
        $this->assertSame(1, $pub['turn']);
        $pub = $this->act($pub, $private, 'b', ['do' => 'search', 'place' => 'tower']);
        $this->assertSame([1, 1], [$pub['turn'], $pub['players'][0]['skip']], 'Anna sat out one turn');
        $this->refused('not_your_turn', $pub, $private['a'], 'a', ['do' => 'search', 'place' => 'tower']);
        $pub = $this->act($pub, $private, 'b', ['do' => 'search', 'place' => 'tower']);
        $this->assertSame([1, 0], [$pub['turn'], $pub['players'][0]['skip']], 'and a second');
        $this->assertSame(['sits_out', 'search', 'sits_out'], array_column(array_slice($pub['log'], -3), 'k'));
        $pub = $this->act($pub, $private, 'b', ['do' => 'search', 'place' => 'tower']);
        $this->assertSame(0, $pub['turn'], 'then it is her turn again');
        $pub = $this->act($pub, $private, 'a', ['do' => 'accuse'] + self::RIGHT);
        $this->assertSame([true, 0, 'solved'], [$pub['over'], $pub['winner'], $pub['how']], 'and she may try again');
    }

    /** TC-INT-022 (K-68, AK-53): with penalty and no turns, a wrong accusation waits 120 s (boundary: 119 s and 120 s) */
    public function testPenaltyWait(): void
    {
        [$pub, $private] = $this->game(['wrong' => 'penalty', 'turns' => 'free']);
        $pub = $this->act($pub, $private, 'b', ['do' => 'accuse'] + self::WRONG);
        $this->assertSame(120, my_mystery_you($pub, 'b', self::PLAYERS, false, self::T0)['wait']);
        $this->assertTrue(my_mystery_public($pub, self::PLAYERS, self::T0)['players'][1]['wait']);
        $this->refused('penalty', $pub, $private['b'], 'b', ['do' => 'search', 'place' => 'tower'], self::T0 + 119);
        $this->refused('penalty', $pub, $private['b'], 'b', ['do' => 'accuse'] + self::RIGHT, self::T0 + 119);
        $this->assertNull(my_mystery_tick($pub, self::PLAYERS, 'a', self::T0 + 119), 'nothing happens before');
        $pub = my_mystery_tick($pub, self::PLAYERS, 'a', self::T0 + 120);
        $this->assertSame(['back', 1], [end($pub['log'])['k'], end($pub['log'])['p']], 'the screens learn that Bob is back');
        $pub = $this->act($pub, $private, 'b', ['do' => 'search', 'place' => 'tower'], self::T0 + 120);
        $this->assertFalse(my_mystery_public($pub, self::PLAYERS, self::T0 + 120)['players'][1]['wait']);
    }

    /** TC-INT-023 (K-69, AK-54, AK-55): a question about a place, answered with a clue that only the one who asked sees */
    public function testAskAndAnswer(): void
    {
        [$pub, $private] = $this->game();
        $pub['turn'] = 1;                                                             // Bob finds a clue in the tower
        $pub = $this->act($pub, $private, 'b', ['do' => 'search', 'place' => 'tower']);
        $found = $private['b']['clues'][0]['id'];
        $this->assertSame(2, $pub['turn']);
        $pub['turn'] = 0;
        $this->refused('bad_ask', $pub, $private['a'], 'a', ['do' => 'ask', 'to' => 0, 'place' => 'tower']);
        $this->refused('bad_ask', $pub, $private['a'], 'a', ['do' => 'ask', 'to' => 7, 'place' => 'tower']);
        $this->refused('bad_place', $pub, $private['a'], 'a', ['do' => 'ask', 'to' => 1, 'place' => 'cellar']);
        $pub = $this->act($pub, $private, 'a', ['do' => 'ask', 'to' => 1, 'place' => 'tower']);
        $this->assertSame(0, $pub['turn'], 'the turn waits for the answer');
        $this->refused('busy', $pub, $private['a'], 'a', ['do' => 'search', 'place' => 'tower']);
        $this->assertSame([['p' => 0, 'place' => 'tower']], my_mystery_you($pub, 'b', self::PLAYERS)['answer']);
        $this->refused('no_ask', $pub, $private['c'], 'c', ['do' => 'answer', 'clue' => null]);
        $this->refused('must_show', $pub, $private['b'], 'b', ['do' => 'answer', 'clue' => null]);
        $this->refused('not_yours', $pub, $private['b'], 'b', ['do' => 'answer', 'clue' => 'statement_oskar']);
        $pub = $this->act($pub, $private, 'b', ['do' => 'answer', 'clue' => $found]);
        $this->assertSame(1, $pub['turn'], 'answered: the next turn');
        $this->assertSame([$found], array_column(my_mystery_you($pub, 'a', self::PLAYERS)['given'], 'id'), 'Anna sees it');
        $this->assertSame('Bob', my_mystery_you($pub, 'a', self::PLAYERS)['given'][0]['by']);
        $this->assertSame([], my_mystery_you($pub, 'c', self::PLAYERS)['given'], 'Cleo does not');
        $this->assertStringNotContainsString($found, json_encode(my_mystery_public($pub, self::PLAYERS)), 'nor any screen');
        $this->assertSame(['k' => 'answer', 'p' => 1, 'to' => 0, 'any' => true], array_diff_key(end($pub['log']), ['n' => 1]));
        $this->refused('not_yours', $pub, $private['a'], 'a', ['do' => 'show', 'clue' => $found]);

        $pub = $this->act($pub, $private, 'b', ['do' => 'ask', 'to' => 2, 'place' => 'kitchen']);   // Cleo has nothing there
        $pub = $this->act($pub, $private, 'c', ['do' => 'answer', 'clue' => null]);
        $this->assertSame([2, false], [$pub['turn'], end($pub['log'])['any']]);

        $pub = $this->act($pub, $private, 'c', ['do' => 'ask', 'to' => 0, 'place' => 'salon']);     // Anna leaves before she answers
        $gone = [self::PLAYERS[1], self::PLAYERS[2]];
        $pub = my_mystery_tick($pub, $gone, 'a', self::T0);
        $this->assertSame([[], 2, 'ask_gone'], [$pub['asks'], $pub['turn'], end($pub['log'])['k']], 'taken back, Cleo plays on');

        [$pub, $private] = $this->game(['ask' => 'no']);
        $this->refused('bad_action', $pub, $private['a'], 'a', ['do' => 'ask', 'to' => 1, 'place' => 'tower']);
        [$pub, $private] = $this->game(['play' => 'together']);
        $this->refused('bad_action', $pub, $private['a'], 'a', ['do' => 'ask', 'to' => 1, 'place' => 'tower']);
    }

    /** TC-INT-024 (K-70, AK-56): with doors only where you stand and one door away, else anywhere with clues left */
    public function testDoors(): void
    {
        [$pub, $private] = $this->game(['move' => 'doors', 'turns' => 'free']);
        $this->assertSame(['lobby', 'salon', 'library', 'office', 'rooms', 'outside'], my_mystery_reach($pub, 0));
        $this->refused('not_reachable', $pub, $private['a'], 'a', ['do' => 'search', 'place' => 'tower']);
        $pub = $this->act($pub, $private, 'a', ['do' => 'search', 'place' => 'library']);
        $this->assertSame(['lobby', 'salon', 'library', 'tower'], my_mystery_reach($pub, 0), 'the corridor has no clues in 60 min');
        $pub = $this->act($pub, $private, 'a', ['do' => 'search', 'place' => 'tower']);
        $pub['stacks']['tower'] = $pub['stacks']['library'] = $pub['stacks']['outside'] = [];
        $this->assertSame(['lobby', 'salon', 'office', 'kitchen', 'rooms'], my_mystery_reach($pub, 0), 'nothing left in reach: anywhere');
        [$pub] = $this->game();
        $this->assertCount(8, my_mystery_reach($pub, 0), 'without doors: every place with clues');
    }

    /** TC-INT-025 (K-66, K-20, AK-57): together, every clue is shown at once and the team votes on the accusation */
    public function testTogether(): void
    {
        [$pub, $private] = $this->game(['play' => 'together']);
        $pub = $this->act($pub, $private, 'a', ['do' => 'search', 'place' => 'tower']);
        $this->assertSame([$private['a']['clues'][0]['id'], 0], [end($pub['shown'])['id'], end($pub['shown'])['p']], 'shown to all at once');
        $this->refused('bad_action', $pub, $private['b'], 'b', ['do' => 'accuse'] + self::RIGHT);
        $pub = $this->act($pub, $private, 'b', ['do' => 'propose'] + self::WRONG);
        $this->assertSame(['p' => 1, 'guess' => self::WRONG, 'yes' => [1], 'no' => []], my_mystery_public($pub, self::PLAYERS)['vote']);
        $this->assertSame('todo', my_mystery_you($pub, 'a', self::PLAYERS)['vote']);
        $this->refused('busy', $pub, $private['b'], 'b', ['do' => 'search', 'place' => 'tower']);
        $this->refused('already_voted', $pub, $private['b'], 'b', ['do' => 'vote', 'yes' => true]);
        $pub = $this->act($pub, $private, 'a', ['do' => 'vote', 'yes' => false]);
        $pub = $this->act($pub, $private, 'c', ['do' => 'vote', 'yes' => false]);
        $this->assertSame([null, 'vote_no', 1], [$pub['vote'], end($pub['log'])['k'], $pub['turn']], 'turned down: Bob plays on');
        $this->refused('no_vote', $pub, $private['a'], 'a', ['do' => 'vote', 'yes' => true]);

        $pub = $this->act($pub, $private, 'b', ['do' => 'propose'] + self::WRONG);      // a tie: the host (Anna) decides
        $pub = $this->act($pub, $private, 'a', ['do' => 'vote', 'yes' => false]);
        $this->assertNotNull($pub['vote'], 'Cleo has not voted');
        $after = my_mystery_tick($pub, [self::PLAYERS[0], self::PLAYERS[1]], 'a', self::T0);   // Cleo leaves: 1-1
        $this->assertSame([null, 'vote_no'], [$after['vote'], end($after['log'])['k']], 'the host said no');
        $pub = $this->act($pub, $private, 'c', ['do' => 'vote', 'yes' => true]);
        $this->assertSame([true, 'wrong'], [$pub['over'], $pub['how']], '2-1 yes, wrong, out: the team lost');
        $this->assertSame(['k' => 'accuse', 'p' => 1, 'right' => false, 'team' => true], array_diff_key(end($pub['log']), ['n' => 1]));

        [$pub, $private] = $this->game(['play' => 'together', 'wrong' => 'penalty'], 2);
        $pub = $this->act($pub, $private, 'a', ['do' => 'propose'] + self::WRONG);      // 1-1 and the host (Anna) said yes
        $pub = $this->act($pub, $private, 'b', ['do' => 'vote', 'yes' => false]);
        $this->assertFalse($pub['over'], '1-1 with the host for it: made, wrong, the team sits out');
        $this->assertSame([self::WRONG], $pub['tried']);
        $this->assertSame(['accuse', 'lost', 'lost'], array_column(array_slice($pub['log'], -3), 'k'));
        $this->assertSame(1, $pub['turn'], 'Anna had the turn; Bob and Anna lost theirs');
        $pub = $this->act($pub, $private, 'b', ['do' => 'propose'] + self::RIGHT);
        $pub = $this->act($pub, $private, 'a', ['do' => 'vote', 'yes' => true]);
        $this->assertSame([true, 'solved', null], [$pub['over'], $pub['how'], $pub['winner']], 'right: the team solved it');
    }
}
