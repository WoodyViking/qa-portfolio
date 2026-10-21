<?php
/**
 * Component tests of the chess rules and computer opponent (src/chess-engine.inc.php).
 * No database or HTTP: the engine is pure logic.
 * Traceability: FR-CHESS-01..04, FR-CHESS-12..14 (Fog of War).
 */
require_once '/var/www/html/chess-engine.inc.php';

use PHPUnit\Framework\TestCase;

class ChessEngineTest extends TestCase
{
    /** Count the positions after $depth plies (perft): the standard check of a move generator. */
    private function perft(ChessPosition $p, int $depth): int
    {
        if ($depth === 0) return 1;
        $n = 0;
        foreach ($p->legalMoves() as $m) {
            $p->make($m);
            $n += $this->perft($p, $depth - 1);
            $p->unmake();
        }
        return $n;
    }

    private function play(array $moves, ?string $fen = null): ChessGame
    {
        return ChessGame::fromMoves($moves, $fen);
    }

    private function assertError(string $code, callable $fn): void
    {
        try {
            $fn();
            $this->fail("expected ChessError $code");
        } catch (ChessError $e) {
            $this->assertSame($code, $e->getMessage());
        }
    }

    /** TC-CHS-001 | FR-CHESS-01: the start position, white to move with 20 legal moves */
    public function test_start_position()
    {
        $g = new ChessGame();
        $this->assertSame('white', $g->toMove());
        $this->assertCount(20, $g->legalUci());
        $this->assertSame('rnbqkbnr', $g->pos->rows()[0]);
        $this->assertSame('RNBQKBNR', $g->pos->rows()[7]);
    }

    /** TC-CHS-002 | FR-CHESS-01: move generation matches the published perft counts (incl. castling, en passant, promotion) */
    public function test_perft()
    {
        $this->assertSame(8902, $this->perft(ChessPosition::fromFen(ChessPosition::START), 3));
        // "Kiwipete": castling both ways, en passant, pins, promotions near
        $this->assertSame(2039, $this->perft(ChessPosition::fromFen('r3k2r/p1ppqpb1/bn2pnp1/3PN3/1p2P3/2N2Q1p/PPPBBPPP/R3K2R w KQkq - 0 1'), 2));
        // en passant that would expose the king, and checks along ranks
        $this->assertSame(2812, $this->perft(ChessPosition::fromFen('8/2p5/3p4/KP5r/1R3p1k/8/4P1P1/8 w - - 0 1'), 3));
        // promotions with capture and check
        $this->assertSame(1486, $this->perft(ChessPosition::fromFen('rnbq1k1r/pp1Pbppp/2p5/8/2B5/8/PPP1NnPP/RNBQK2R w KQ - 1 8'), 2));
    }

    /** TC-CHS-003 | FR-CHESS-03: checkmate ends the game and the mover wins (fool's mate) */
    public function test_checkmate()
    {
        $g = $this->play(['f2f3', 'e7e5', 'g2g4', 'd8h4']);
        $this->assertTrue($g->isOver());
        $this->assertSame('checkmate', $g->result());
        $this->assertSame('black', $g->winner());
        $this->assertSame(['f3', 'e5', 'g4', 'Qh4#'], $g->sanMoves());
        $this->assertSame('e1', $g->checkSquare());
        $this->assertSame([], $g->legalUci());
        $this->assertError('game_over', fn() => $g->play('white', 'a2a3'));
    }

    /** TC-CHS-004 | FR-CHESS-03: stalemate is a draw */
    public function test_stalemate()
    {
        $g = $this->play(['f6f7'], '7k/8/5QK1/8/8/8/8/8 w - - 0 1');
        $this->assertSame('stalemate', $g->result());
        $this->assertNull($g->winner());
    }

    /** TC-CHS-005 | FR-CHESS-03: automatic draws: repetition, fifty moves, too little material */
    public function test_automatic_draws()
    {
        $g = $this->play(['g1f3', 'g8f6', 'f3g1', 'f6g8', 'g1f3', 'g8f6', 'f3g1']);
        $this->assertFalse($g->isOver(), 'twice is not enough');
        $g->play('black', 'f6g8');
        $this->assertSame('repetition', $g->result());

        $g = $this->play(['a1a2'], '8/8/8/4k3/8/8/8/R3K3 w - - 99 80');
        $this->assertSame('fifty', $g->result());

        $g = $this->play(['a1b2'], 'k7/8/8/8/8/8/1r6/KB6 w - - 0 1');     // king takes the rook: K+B v K
        $this->assertSame('material', $g->result());
        $this->assertSame([['r', 'white']], $g->captured());

        $this->assertFalse(ChessPosition::fromFen('k7/8/8/8/8/8/8/KN1n4 w - - 0 1')->insufficientMaterial(), 'K+N v K+N can still mate');
        $this->assertTrue(ChessPosition::fromFen('k7/8/8/8/8/8/8/KB1b4 w - - 0 1')->insufficientMaterial(), 'bishops on one colour');
    }

    /** TC-CHS-006 | FR-CHESS-02: the server refuses illegal moves with a reason */
    public function test_illegal_moves_are_refused()
    {
        $g = new ChessGame();
        $this->assertError('not_your_turn', fn() => $g->play('black', 'e7e5'));
        $this->assertError('illegal_move', fn() => $g->play('white', 'e2e5'));
        $this->assertError('not_your_piece', fn() => $g->play('white', 'e7e5'));
        $this->assertError('not_your_piece', fn() => $g->play('white', 'e4e5'));
        $this->assertError('bad_move', fn() => $g->play('white', 'e2-e4'));
        $this->assertSame(0, $g->moveCount(), 'a refused move changes nothing');

        // A pinned knight may not move, and the king may not step into check.
        $g = new ChessGame('4k3/4r3/8/8/8/8/4N3/4K3 w - - 0 1');
        $this->assertError('illegal_move', fn() => $g->play('white', 'e2c3'));
        $g = new ChessGame('4k3/3r4/8/8/8/8/8/4K3 w - - 0 1');
        $this->assertError('illegal_move', fn() => $g->play('white', 'e1d1'));
    }

    /** TC-CHS-007 | FR-CHESS-01: castling both ways, and not out of, through or into check, nor after the king moved */
    public function test_castling()
    {
        $fen = 'r3k2r/pppppppp/8/8/8/8/PPPPPPPP/R3K2R w KQkq - 0 1';
        $g = $this->play(['e1g1', 'e8c8'], $fen);
        $this->assertSame('R....RK.', $g->pos->rows()[7]);
        $this->assertSame('..kr...r', $g->pos->rows()[0]);
        $this->assertSame(['O-O', 'O-O-O'], $g->sanMoves());

        $through = new ChessGame('r3k2r/8/8/8/8/8/5r2/R3K2R w KQkq - 0 1');   // the rook on f2 attacks f1
        $this->assertNotContains('e1g1', $through->legalUci());
        $this->assertContains('e1c1', $through->legalUci());

        $moved = $this->play(['e1f1', 'a7a6', 'f1e1', 'a6a5'], 'r3k2r/pppppppp/8/8/8/8/PPPPPPPP/R3K2R w KQkq - 0 1');
        $this->assertNotContains('e1g1', $moved->legalUci(), 'the king has moved');
    }

    /** TC-CHS-008 | FR-CHESS-01: en passant only right after the double step */
    public function test_en_passant()
    {
        $g = $this->play(['e2e4', 'a7a6', 'e4e5', 'd7d5']);
        $this->assertContains('e5d6', $g->legalUci());
        $g->play('white', 'e5d6');
        $this->assertSame('exd6', $g->sanMoves()[4]);
        $this->assertSame('p..P....', $g->pos->rows()[2]);
        $this->assertSame('........', $g->pos->rows()[3], 'the passed pawn is gone');

        $late = $this->play(['e2e4', 'a7a6', 'e4e5', 'd7d5', 'h2h3', 'h7h6']);
        $this->assertNotContains('e5d6', $late->legalUci());
    }

    /** TC-CHS-009 | FR-CHESS-01: promotion to any piece; the piece must be named */
    public function test_promotion()
    {
        $fen = '8/4P3/8/8/8/8/k7/4K3 w - - 0 1';
        $this->assertError('illegal_move', fn() => (new ChessGame($fen))->play('white', 'e7e8'));
        $g = $this->play(['e7e8n'], $fen);
        $this->assertSame('....N...', $g->pos->rows()[0]);
        $this->assertSame('e8=N', $g->sanMoves()[0]);
        $g = $this->play(['e7e8q'], $fen);
        $this->assertSame('e8=Q', $g->sanMoves()[0]);
    }

    /** TC-CHS-010 | FR-CHESS-01: SAN tells two knights apart */
    public function test_san_disambiguation()
    {
        $g = $this->play(['b1d2'], '4k3/8/8/8/8/5N2/8/1N2K3 w - - 0 1');
        $this->assertSame('Nbd2', $g->sanMoves()[0]);
    }

    /** TC-CHS-011 | FR-CHESS-04: every level takes a mate in one */
    public function test_every_level_takes_mate_in_one()
    {
        foreach (ChessAI::LEVELS as $level) {
            $g = new ChessGame('6k1/5ppp/8/8/8/8/5PPP/3R2K1 w - - 0 1');
            $this->assertSame('d1d8', (new ChessAI($level))->choose($g->pos), $level);
        }
    }

    /** TC-CHS-012 | FR-CHESS-04: Medium and Hard take a queen that is left hanging */
    public function test_medium_and_hard_take_a_free_queen()
    {
        foreach (['medium', 'hard'] as $level) {
            $g = new ChessGame('rnb1kbnr/pppp1ppp/8/4p3/5P1q/6P1/PPPPP2P/RNBQKBNR w KQkq - 1 3');
            $this->assertSame('g3h4', (new ChessAI($level))->choose($g->pos), $level);
        }
    }

    /** TC-CHS-013 | FR-CHESS-04: Hard sees a mate in two (a ladder mate with two rooks) */
    public function test_hard_finds_mate_in_two()
    {
        $g = new ChessGame('7k/8/8/8/8/8/1R6/R5K1 w - - 0 1');
        $g->play('white', (new ChessAI('hard'))->choose($g->pos));
        foreach ($g->legalUci() as $reply) {                     // whatever black answers, white mates next
            $after = clone $g;
            $after->play('black', $reply);
            $mates = array_filter($after->legalUci(), function ($m) use ($after) {
                $x = clone $after;
                $x->play('white', $m);
                return $x->result() === 'checkmate';
            });
            $this->assertNotEmpty($mates, "no mate after $reply");
        }
    }

    /** TC-CHS-014 | FR-CHESS-04, NFR: every level answers with a legal move within 3 seconds, the position unchanged */
    public function test_answers_in_time_with_a_legal_move()
    {
        $moves = ['e2e4', 'e7e5', 'g1f3', 'b8c6', 'f1b5', 'a7a6', 'b5a4', 'g8f6', 'e1g1', 'f8e7'];
        foreach (ChessAI::LEVELS as $level) {
            $g = $this->play($moves);
            $before = $g->pos->rows();
            $t = microtime(true);
            $m = (new ChessAI($level))->choose($g->pos);
            $this->assertLessThan(3.0, microtime(true) - $t, $level);
            $this->assertContains($m, $g->legalUci(), $level);
            $this->assertSame($before, $g->pos->rows(), 'the search leaves the position as it was');
        }
    }

    /** TC-CHS-015 | FR-CHESS-04: harder levels beat easier ones (Medium v Easy, a whole game with each colour) */
    public function test_medium_beats_easy()
    {
        foreach (['white', 'black'] as $k => $mediumPlays) {
            $rng = fn() => new \Random\Randomizer(new \Random\Engine\Mt19937(42 + $k));   // the same games every run
            $easyPlays = $mediumPlays === 'white' ? 'black' : 'white';
            $ai = [$mediumPlays => new ChessAI('medium', $rng()), $easyPlays => new ChessAI('easy', $rng())];
            $g = new ChessGame();
            while (!$g->isOver() && $g->moveCount() < 300) $g->play($g->toMove(), $ai[$g->toMove()]->choose($g->pos));
            $this->assertSame($mediumPlays, $g->winner(), "Medium as $mediumPlays: " . ($g->result() ?? 'no result'));
        }
    }

    // ---------------------------------------------------------------- Fog of War

    /** TC-CHS-016 | FR-CHESS-12: a player sees their own pieces, the squares they can move to, and in front of their pawns */
    public function test_fog_what_a_player_sees()
    {
        $g = new ChessGame(null, true);
        $this->assertSame(['????????', '????????', '????????', '????????', '........', '........', 'PPPPPPPP', 'RNBQKBNR'], $g->visibleRows('white'));
        $g->play('white', 'e2e4');
        $this->assertSame('????????', $g->visibleRows('black')[4], 'black cannot see e4');
        $g->play('black', 'd7d5');
        $white = $g->visibleRows('white');
        $this->assertSame('?.?p.??.', $white[3], 'd5 can be taken, so it is seen; so are b5 and h5 (bishop, queen) and e5 (in front of the pawn)');
        $this->assertSame('....?...', $white[5], 'e3 is behind the pawn: nobody can go there');
    }

    /** TC-CHS-017 | FR-CHESS-13: no check: a king may walk into attack, and taking the king wins */
    public function test_fog_taking_the_king_wins()
    {
        $g = ChessGame::fromMoves(['e2e4', 'f7f6', 'd1h5'], null, true);
        $this->assertSame('Qh5', $g->sanMoves()[2], 'no "+": that would tell black');
        $this->assertNull($g->checkSquare());
        $this->assertContains('e8f7', $g->legalUci(), 'the king may step into the queen\'s line');
        $g->play('black', 'e8f7');
        $g->play('white', 'h5f7');
        $this->assertSame(['king', 'white'], [$g->result(), $g->winner()]);
        $this->assertSame([['k', 'white']], $g->captured());

        $castle = new ChessGame('r3k2r/8/8/8/8/8/5r2/R3K2R w KQkq - 0 1', true);
        $this->assertContains('e1g1', $castle->legalUci(), 'castling through an attacked square: nobody knows');
        $this->assertFalse((new ChessGame('k7/8/8/8/8/8/8/K7 w - - 0 1', true))->isOver(), 'K v K goes on: a king can still be taken');
    }

    /** TC-CHS-018 | FR-CHESS-14: the computer's picture: what it sees, plus where it last saw the opponent's pieces */
    public function test_fog_believed_position_and_memory()
    {
        // The knight on f3 saw the pawn arrive on e5, then went back to g1: e5 is in the fog again.
        $g = ChessGame::fromMoves(['g1f3', 'e7e5', 'f3g1', 'a7a6'], null, true);
        $this->assertSame('????????', $g->visibleRows('white')[3]);
        $b = $g->believed('white');
        $this->assertSame(-1, $b->sq[ChessPosition::index('e5')], 'remembered where it was last seen');
        $this->assertSame(0, $b->sq[ChessPosition::index('e7')], 'and no longer on e7');
        $this->assertSame(-1, $b->sq[ChessPosition::index('a7')], 'a6 was never seen, so the pawn is still believed on a7');
        $this->assertSame(0, $b->sq[ChessPosition::index('a6')]);
        $this->assertSame(ChessPosition::index('e8'), $b->king[-1]);
        $this->assertSame(array_map([ChessPosition::class, 'uci'], $b->moves()), $g->legalUci(), 'its own moves are the real ones here');
    }

    /** TC-CHS-019 | FR-CHESS-14: every level takes the king when it can, and only plays real moves */
    public function test_fog_computer_takes_the_king()
    {
        $g = ChessGame::fromMoves(['e2e4', 'f7f6', 'd1h5', 'e8f7'], null, true);
        foreach (ChessAI::LEVELS as $level) {
            $this->assertSame('h5f7', (new ChessAI($level))->choose($g->believed('white'), $g->legalUci()), $level);
        }
    }

    /** TC-CHS-020 | FR-CHESS-14: the fair computer on Medium beats Easy in the fog (a whole game with each colour) */
    public function test_fog_medium_beats_easy()
    {
        foreach (['white', 'black'] as $k => $mediumPlays) {
            $rng = fn() => new \Random\Randomizer(new \Random\Engine\Mt19937(7 + $k));
            $easyPlays = $mediumPlays === 'white' ? 'black' : 'white';
            $ai = [$mediumPlays => new ChessAI('medium', $rng()), $easyPlays => new ChessAI('easy', $rng())];
            $g = new ChessGame(null, true);
            while (!$g->isOver() && $g->moveCount() < 300) {
                $m = $ai[$g->toMove()]->choose($g->believed($g->toMove()), $g->legalUci());
                $this->assertContains($m, $g->legalUci());
                $g->play($g->toMove(), $m);
            }
            $this->assertSame($mediumPlays, $g->winner(), "Medium as $mediumPlays: " . ($g->result() ?? 'no result'));
        }
    }

    // ---------------------------------------------------------------- Fog of War: memory (ghosts)

    /** TC-CHS-021 | FR-CHESS-16: at the start each side remembers the other's pieces on their start squares, none on squares in sight */
    public function test_fog_ghosts_at_the_start()
    {
        $g = new ChessGame(null, true);
        $this->assertSame(['rnbqkbnr', 'pppppppp', '........', '........', '........', '........', '........', '........'], $g->ghostRows('white'));
        $this->assertSame(['........', '........', '........', '........', '........', '........', 'PPPPPPPP', 'RNBQKBNR'], $g->ghostRows('black'));
        $this->assertNull($g->tookSquare('white'));
    }

    /** TC-CHS-022 | FR-CHESS-16: a piece that goes out of sight stays as a ghost where it was last seen; an unseen move changes nothing */
    public function test_fog_a_piece_out_of_sight_stays_where_it_was_seen()
    {
        // The knight on f3 saw the pawn arrive on e5, then went back to g1; the pawn on a7 moved to a6 unseen.
        $g = ChessGame::fromMoves(['g1f3', 'e7e5', 'f3g1', 'a7a6'], null, true);
        $this->assertSame(['rnbqkbnr', 'pppp.ppp', '........', '....p...', '........', '........', '........', '........'], $g->ghostRows('white'),
            'e5: last seen there; e7: seen to be left (the nearest copy is dropped); a7: never seen to move');
    }

    /** TC-CHS-023 | FR-CHESS-16: the ghost is gone when the square is in sight again */
    public function test_fog_a_ghost_goes_when_the_square_is_seen_again()
    {
        $g = ChessGame::fromMoves(['g1f3', 'e7e5', 'f3g1', 'a7a6', 'g1f3'], null, true);
        $this->assertSame('p', $g->visibleRows('white')[3][4], 'e5 is in sight again and the pawn is there');
        $this->assertSame('.', $g->ghostRows('white')[3][4], 'so there is no ghost on it');
    }

    /** TC-CHS-024 | FR-CHESS-16: a piece seen on a new square takes its ghost with it (the nearest hidden copy is dropped) */
    public function test_fog_a_ghost_goes_when_the_piece_is_seen_elsewhere()
    {
        $g = ChessGame::fromMoves(['g1f3', 'e7e5', 'f3g1'], null, true);
        $this->assertSame('p', $g->ghostRows('white')[3][4], 'the pawn is remembered on e5');
        $g->play('black', 'e5e4');                              // white's pawn on e2 sees e4
        $this->assertSame('p', $g->visibleRows('white')[4][4]);
        $this->assertSame('.', $g->ghostRows('white')[3][4], 'it was seen on e4: no ghost on e5');
        $this->assertSame('pppp.ppp', $g->ghostRows('white')[1], 'and the other pawns are still remembered');
    }

    /** TC-CHS-025 | FR-CHESS-17: the player who loses a piece sees what took it, as a ghost on that square; the taker does not */
    public function test_fog_the_loser_sees_what_took_the_piece()
    {
        $g = ChessGame::fromMoves(['g1f3', 'e7e5', 'f3e5'], null, true);
        $this->assertSame('?', $g->visibleRows('black')[3][4], 'black cannot see e5 any more: the pawn is gone and nothing of black can reach it');
        $this->assertSame('N', $g->ghostRows('black')[3][4], 'but the white knight that took it is remembered there');
        $this->assertSame('e5', $g->tookSquare('black'));
        $this->assertNull($g->tookSquare('white'), 'white took it and knows');
        $this->assertSame('RNBQKB.R', $g->ghostRows('black')[7], 'and the knight is no longer remembered on g1 (the nearest copy), but still on b1');
        $g->play('black', 'g8f6');
        $this->assertNull($g->tookSquare('black'), 'only the latest move counts');
        $this->assertSame('N', $g->ghostRows('black')[3][4], 'the ghost stays until e5 is in sight');
    }

    /** TC-CHS-026 | FR-CHESS-17: taking en passant: the taker stands on the square it moved to */
    public function test_fog_taking_en_passant()
    {
        $g = ChessGame::fromMoves(['e2e4', 'a7a6', 'e4e5', 'd7d5', 'e5d6'], null, true);
        $this->assertSame('d6', $g->tookSquare('black'));
        $this->assertSame('P', $g->visibleRows('black')[2][3], "black's c7 pawn could take on d6, so black sees the pawn");
    }

    /** TC-CHS-027 | FR-CHESS-15, FR-CHESS-16: what a player is shown depends only on what they have seen (hidden moves, even a promotion, change nothing) */
    public function test_fog_the_view_does_not_depend_on_what_was_not_seen()
    {
        // White's king on a1 sees a2, b1 and b2. Black moves on the far side of the board: all of these are invisible to white.
        $views = [];
        foreach (['g2g1q', 'g2g1n', 'g2g1r', 'h8g8', 'h8h7'] as $blackMove) {
            $g = new ChessGame('7k/8/8/8/8/8/6p1/K7 b - - 0 1', true);
            $g->play('black', $blackMove);
            $views[$blackMove] = [$g->visibleRows('white'), $g->ghostRows('white'), $g->tookSquare('white')];
        }
        $first = reset($views);
        foreach ($views as $move => $v) $this->assertSame($first, $v, "white's view after $move");
        $this->assertSame('p', $first[1][6][6], 'the pawn is still remembered on g2: with the real piece count a promotion would show as a vanished pawn');
    }
}
