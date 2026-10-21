# Test design: the escape room "The Runesmith's Locks" (US-18, US-19)

Test basis: K-72..K-87 and AK-59..AK-69 in [the requirements](../requirements/kravsammanstallning.md) (approved by
Woody 2026-10-17, "Ja, bygg", with the defaults: a soft clock only, a leaver's fragments go to the others), and
K-13, K-15, K-28, K-29, K-71, NFR-01, NFR-02, NFR-05. The content is `content/runesmith/`, the format
[docs/escape-format.md](../../docs/escape-format.md), the modules `modules/escape-content.inc.php` and
`modules/escape.inc.php`, the screens `js/escape.js`.

| Level | File | Cases |
|---|---|---|
| Tool | `qa/tools/escape-check.php runesmith` | 10 000 deals per length and player count (100 000), each checked and played through (AK-59) |
| Component | `EscapeContentTest.php` | TC-INT-026..032 |
| Component | `EscapeEngineTest.php` | TC-INT-033..041 |
| System (API) | `EscapeApiTest.php` | TC-API-033..037 |
| System (browser) | `escape.spec.js` in chromium, firefox, phone, tablet, tv | TC-E2E-019..023 |

## The oracle

Every game is drawn at random. The checker and the component tests deal seeded games
(`Random\Engine\Mt19937`), so a failing deal can be dealt again. The engine has a solver (`my_escape_moves`) that
reads what the fragments say, the way the players would; the component tests open locks with it. The API tests read
the drawn game from `mysteries_sessions.public_state` in the test database. The browser tests read nothing secret:
they take the three hints of a lock and use the answer in the third, as a team that is stuck would (AK-66).

## Fault seeding (K-74, AK-59)

The checker must reject broken deals and files, and say where: a shield line made false (the message names the line),
a scale note with the wrong weight, a tool note that sends you to the wrong spot, all the rune parts with one player,
an uneven deal, a door that does not match the code, a text missing in Danish, a picture missing.

## What a device may see (K-73, K-79, AK-60, AK-61)

Error guessing on the board's and both phones' views right after the start: no `sec`, `at`, `map`, `code`, `w`,
`answer`, `course`, `signs`, `frags` or `items` in the public part, no Discord id anywhere; each phone holds exactly its
own fragments; with two players the rune row is split in two halves with no rune on both and nobody holding the whole
door (200 seeded deals and one real game). An answer appears in the public view only once its lock is open
(the shields, the course). `action=content` holds the texts and the lock definitions without any draw.

## Moves: decision table (K-78..K-82, K-85)

| Move | Lock not ready | Ready | Wrong | Within 5 s of the last answer | Lock open | Over |
|---|---|---|---|---|---|---|
| press | `not_yet` | counts if another player pressed last | – | – | `bad_lock` (a late pump) | `game_over` |
| search a spot | `not_yet` | the tool to the searcher | empty: +60 s | – | `already_open` | `game_over` |
| search the same spot again | – | `searched` | – | – | – | – |
| hang a tool | `not_yet` | on its own hook | `wrong_hook`; not carried: `not_yours` | – | – | – |
| answer (code, set, order, ropes) | `not_yet` | opens | +120 s, the lock holds | `too_fast` | `already_open` | `game_over` |
| answer without the items it needs | – | `need_items` | – | – | – | – |
| open the chest | `not_yet` | with every key: opens, the sword and the chart piece inside to the opener | `need_items` | – | `already_open` | – |
| lay a piece | `not_yet` | in its slot | wrong slot: `wrong_slot`, no penalty | – | – | – |
| give an item | any time | to another player at the table | not carried: `not_yours`; self or unknown: `bad_player` | – | – | `game_over` |
| hint | `not_yet` | the next of three, on every screen | hints off: `no_hints`; a fourth: `no_more_hints` | – | `already_open` | – |

## Boundary values

- The bellows: one player pressing 25 times counts 1; two in turn, 20 presses, light it (AK-65).
- Answers: 4.9 s after the last one refused, 5.0 s accepted (AK-62).
- Stars (K-83): within the time and 0 or 2 hints ★★★ (2 is the boundary; the browser test ends with 18 hints ★★);
  one second over the time with 0 hints ★★, with 3 hints ★; ended by the host 0 (AK-66, AK-67).
- The clock: a pause of 900 s does not count; elapsed after resume continues from where it stopped (K-15, K-81).

## State transitions

lobby (the host picks hints on/off) → playing, dark (only the bellows) → the fire burns (the clock runs, paused
during a pause) → over: the last lock opens (`how: out`, stars) or the host ends (`how: host`, no stars). A
player who leaves while playing: their fragments and items go to the one at the table with the least, once (AK-64).

## Not covered automatically

- Shaking a real phone (K-85): the browser tests press; the shake uses the same code path as Ludo's (devicemotion),
  checked by hand on a phone.
- The look on real TV browsers (K-04): manual, as for the murder mystery.
- 90 minutes (K-75): not written yet. A hard clock (K-81): not built.
