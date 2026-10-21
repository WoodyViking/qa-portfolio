# Test design: puzzles on the phone and "The Lighthouse in the Storm" (US-24, US-25)

Test basis: K-109..K-120 and AK-84..AK-93 in [the requirements](../requirements/kravsammanstallning.md) (approved by
Woody 2026-10-19, "ja vi kör på", with the defaults: a puzzle has no wrong answer and costs no minutes; 60 minutes and
five puzzle types first), and K-73, K-74, K-82, K-84, K-87, NFR-01, NFR-02, NFR-05. The content is
`content/lighthouse/`, the format [docs/escape-format.md](../../docs/escape-format.md), the code
`modules/escape-content.inc.php`, `modules/escape.inc.php` and `js/escape.js`.

| Level | File | Cases |
|---|---|---|
| Tool | `qa/tools/escape-check.php lighthouse` | 10 000 deals per length and player count (100 000), each checked and played through (AK-84) |
| Component | `LighthouseContentTest.php` | TC-INT-065..068 |
| Component | `PuzzleEngineTest.php` | TC-INT-058..064 |
| System (API) | `EscapeApiTest.php` | TC-API-044 |
| System (browser) | `lighthouse.spec.js` in chromium, firefox, phone, tablet, tv | TC-E2E-027, TC-E2E-028 |

## The oracle

The engine's solver (`my_escape_moves`) solves each puzzle type the way the players would: the walk back for a sliding
picture (kept in `sec`, never sent), the route through the pipe grid found by search, the fewest valve turns by
elimination over GF(2) from the chart rows, the shortest turn of each ring from its line, and the signal book's row for
the ship's signal. The checker plays every deal through with it. The browser tests read nothing secret: they solve the
sliding pictures from the tiles on the phone (breadth-first search), the valves from the chart rows on both phones,
the rings from the lines, and the signal from the signal book, as a team would. The door, the pipe and the weights use
the third hint (K-115).

## Fault seeding (K-114, AK-84)

The checker must reject and name: a pipe grid of straight pieces only (no way through), a valve chart that is the
panel as it stands (0 turns away), a sliding picture that already lies whole, a ring that faces right at the start,
two signal-book rows with the same signal, every pipe square owned by one player, a ring's line given to its owner, a
text missing in Dutch, a screen word that does not exist, a picture missing, a pin off its picture or without one.

## Boundaries and decision table (K-111, K-113)

| Move | Refused | Accepted |
|---|---|---|
| Slide | one move short (`not_solved`), a tile not there or not beside the gap (`bad_moves`), more than 5 000 moves, another player's puzzle (`not_yours`), a second time (`already_solved`) | the moves that lay it: the digit to that player only, the TV says done, no minutes |
| Turn a pipe square | not the owner (`not_yours`), square 20 of 0..19 (`bad_cell`), a valve move on the pipe (`bad_action`), within 0.1 s of the last move (`too_fast`) | a quarter turn, seen on every screen; the last right turn opens it, no minutes |
| Spin a ring | not the owner, a direction other than ±1 (`bad_ring`) | one step; not coupled at 60 min |
| Signal | not short and long (`bad_answer`), within 3 s of the last (`too_fast`) | wrong: the ship flashes again, no minutes; right: the end |

## State transitions

A sliding picture: unsolved → solved (digit dealt) → the lock opens when none is left. A shared puzzle: any state →
right → open. A leaver's unsolved pictures, pipe squares and rings move to the player holding the least (K-118, AK-92).

## Not automated

Tapping the ship's lamp by the length of the press (the tests use the Short and Long buttons), shaking a real phone
for the lamp, and real TV browsers: manual. That the drawn parts sit right on the pictures (the sea marks on the door,
the dots on the brass plate) is checked by eye in the screenshots (`SHOTS=1`); the ship's lamp on its mast is measured
in TC-E2E-028.
