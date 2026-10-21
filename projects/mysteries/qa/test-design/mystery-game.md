# Test design: playing a murder mystery (US-17)

Test basis: K-55..K-70 and AK-43..AK-57 in [the requirements](../requirements/kravsammanstallning.md) (approved by
Woody 2026-10-16), and K-13, K-15, K-20, K-28, K-29, NFR-02, NFR-05. The module is `modules/mystery.inc.php`,
the screens `js/mystery.js`. Component tests of the rules: `MysteryRulesTest.php` (TC-INT-019..025). API tests:
`MysteryApiTest.php` (TC-API-022..032). Browser tests: `mystery.spec.js` (TC-E2E-011..017) in chromium, firefox,
phone, tablet and tv.

## The oracle

What is drawn (the culprit, the weapon) and the order of the clues are random and secret. The API tests read them from
`mysteries_sessions.public_state` in the test database, as no device can, and then act on them: accuse rightly or
wrongly, expect the first clue of a stack. The browser tests cannot, so they let whoever has the turn act, and check
what follows from what happened (TC-E2E-013 checks a right accusation or two wrong ones, whichever the draw gives).

## What a device may see (K-13, K-53, K-56, AK-43)

Error guessing on the views of the board, the host and a player right after the start: none of them contains
`variant`, `culprit`, `stacks`, a version name (`"v"`, `guilty`, `innocent`) or a Discord id; the case file
(`action=content`) holds no clue text, solution or epilogue. The board never learns which clue was found, only where
someone searched. A player's view never holds another player's clues or accusation.

## Turns and moves: decision table (K-58, K-60, AK-44, AK-45, AK-48)

| Move | Not your turn | Your turn | Already accused (out) | Game over |
|---|---|---|---|---|
| search a place with clues | refused `not_your_turn` | your clue, the turn passes | allowed (on your turn) | refused (session ended) |
| search an empty or unknown place | – | refused `place_empty` / `bad_place` | – | – |
| show one of your clues | allowed | allowed | allowed | refused |
| show a clue you do not have, or twice | refused `not_yours` / `already_shown` | same | same | – |
| accuse | refused `not_your_turn` | right: win, game over; wrong: out, the turn passes | refused `already_accused` | refused |
| accuse with a witness, a missing weapon or an unknown weapon | – | refused `bad_accusation` | – | – |
| cross out in the notebook | allowed | allowed | allowed | refused |

## State transitions (K-60, K-61, AK-47, AK-49)

lobby → playing → ended, by: a right accusation (`how: solved`), the last player still allowed to accuse being wrong
(`all_wrong`), or the host (`host`). The solution appears in the public view only in `ended`. The host can end only a
game that is playing or paused; another player cannot end it.

## Boundary values (K-55, AK-50)

Lengths: 30 and 120 min are refused for The Last Toast, 60 and 90 accepted; the start page offers only 60 and 90.
Players: 1 refused at the start (`mystery_players`), 2 and 3 tested. 7 players is covered by the same check (not
tested: the test data has four testers).

## The rules the host picks (K-65..K-70, AK-51..AK-57)

The five rules are tested on the module itself (no database), where the test sets the drawn variant, whose turn it is
and the clock (`$now`), so nothing depends on chance or on waiting.

Decision table: what a wrong accusation does

| Play | Turns | Wrong: out | Wrong: penalty |
|---|---|---|---|
| against | turns | the player is out; all out ends the game (`all_wrong`) | the player sits out 2 turns (`sits_out`), then may accuse again (TC-INT-021) |
| against | free | as above | the player waits 120 s: refused at 119 s, allowed at 120 s; a poll ends the wait (`back`) (TC-INT-022) |
| together | turns | the team lost (`how: wrong`) (TC-INT-025) | the next 2 turns are lost (`lost`) (TC-INT-025) |
| together | free | as above | nobody may search or accuse for 120 s |

State transitions of a question (K-69): asked → answered with a clue (only the asker's `mine.given` has it; no screen
learns which) or with none (refused `must_show` when the answerer has one) → the turn passes; asked → the answerer
leaves → taken back (`ask_gone`), the asker keeps the turn. Asking oneself, an unknown player or place, twice at once,
with `ask: no` or when playing together is refused (TC-INT-023, TC-API-032, TC-E2E-017).

State transitions of a vote (K-66, K-20): proposed (the proposer votes yes) → everyone at the table has voted →
more yes: the accusation is made for the team; more no: turned down (`vote_no`), the proposer keeps the turn; a tie:
the host's vote decides (TC-INT-025, TC-E2E-016). A player who leaves without voting is not counted (settled at the
next poll). Voting twice or with no vote open is refused.

Doors (K-70), boundary of the reach: from the lobby the five places one door away (and the lobby) with clues left; the
tower two doors away is refused (`not_reachable`); when nothing in reach has clues left, every place that has (TC-INT-024).

The lobby (K-65): only the host, only before the start (`not_lobby`), only known rules and values (`bad_rule`); every
screen sees the rules in words (TC-INT-019, TC-API-031, TC-E2E-015).

## Not tested automatically

- The plan, the pictures and the text size on real TV browsers (K-04): manually.
- K-16 (what happens to the clues of a player who leaves): not built; the turn skips them (TC-API-030).
