# Test design: the Viking role-playing game and "The Stolen Hammer" (US-20..US-23)

Test basis: K-88..K-108 and AK-70..AK-83 in [the requirements](../requirements/kravsammanstallning.md) (approved by
Woody 2026-10-17; build started on his "kör på" 2026-10-18), and K-13, K-15, K-20, K-28, K-38, K-71, NFR-01, NFR-02,
NFR-05, NFR-07, NFR-10. The content is `content/vikings/` (the hero set) and `content/stolen-hammer/` (the adventure),
the format [docs/rpg-format.md](../../docs/rpg-format.md), the modules `modules/rpg-content.inc.php` (the files and
the checker), `modules/rpg.inc.php` (the engine), `modules/rpg-store.inc.php` (saved heroes), the screens `js/rpg.js`,
the table `mysteries_heroes` (`database/migrations/002_mysteries_rpg_heroes.sql`).

| Level | File | Cases |
|---|---|---|
| Tool | `qa/tools/rpg-check.php stolen-hammer` | the files, every text and picture, 200 playthroughs per length and party size 4-6, 10 000 fights per fight and party size with the real engine (K-95, AK-74) |
| Component | `RpgContentTest.php` | TC-INT-042..046 |
| Component | `RpgEngineTest.php` | TC-INT-047..057 |
| System (API) | `RpgApiTest.php` | TC-API-038..043 |
| System (browser) | `rpg.spec.js` in chromium, firefox, phone, tablet, tv | TC-E2E-024..026 |

## The oracle

Dice, tasks, riddles, feast events and the hammer's hiding place are drawn at random. The checker and the component
tests use seeded draws (`Random\Engine\Mt19937`), so a failing run can be repeated with its seed. The engine plays
any hero itself (`my_rpg_auto`), the same code that plays an absent player's hero, so playthroughs and fights in the
checker use the real rules, not a model of them. The API tests read and set the game in
`mysteries_sessions.public_state` (for example straight to the box in the cellar, AK-78). The browser tests read
nothing secret: a small bot on each phone takes the first thing a hero may do, attack first in a fight.

## Fault seeding (K-88, K-94, K-95, AK-70, AK-74)

The checker must stop a broken copy and name the fault: a viking with Kraft +4, a Försvar that is not
10 + Smidighet + rustning, an ability with no rules, a Dutch class text missing; a scene nobody can reach, a lost
fight that leads nowhere, a missing Dutch scene text, a clue text without its `{place}`, the pictures missing.
`my_rpg_balance` names a fight outside 70-95 %.

## Balance (K-95)

Every fight is fought by random level-1 parties of 4, 5 and 6 heroes at full Liv: 10 000 times each in the tool,
300 in TC-INT-046 (65-98 %, room for chance), 150 when a game is created (60-99 %, fixed seeds, the sound answer is
remembered in the temp folder under a hash of the files). Result 2026-10-18, 10 000 fights: 86.8-93.9 % for every fight and party size, see [docs/rpg-format.md](../../docs/rpg-format.md#the-checker-k-95).

## Decision tables

The party (K-90, AK-71):

| Heroes | First four classes | Per player | Every player a hero | Start |
|---|---|---|---|---|
| 3 | – | ≤ 2 | yes | `rpg.party_few` |
| 4 | two farmers | ≤ 2 | yes | `rpg.party_same` |
| a third for one player | – | 3 | – | `rpg_two` |
| 4 | four classes | 2 + 2 (two players) | yes | starts |
| 3 | – | – | one without | `rpg.party_few` + `rpg.party_nohero` |

A fight turn (K-98): only what can be done is offered, and the server refuses anything else (`rpg_cannot`,
`not_your_turn`): no axe from far away (move instead), no ability without its resource, help only for a knocked-out
friend, a reaction only for the hero asked (the shieldmaiden's parry: half the blow).

## Boundary values

- The die (K-89, AK-72): a natural 20 succeeds against 20 with −10, a natural 1 fails against 5 with +30; a die, a
  total or an "ok" sent by a phone is ignored.
- Liv (K-91, AK-73): 3 Liv and 5 damage give 0 and knocked out, never below 0; help gives 1 Liv; after a fight
  every hero has at least 1 Liv. No state anywhere is called dead.
- Votes (K-97, AK-75): 2 against 2 waits for the host (or the game master); 3 against 1 decides at once.
- The game master's text (K-103): at most 200 characters, shown as written, markup and control characters removed.
- Levels (K-92): +1 after an adventure played to its end, at most 5; ended by the host: no level, no gifts.

## State transitions

lobby (heroes made, brought or dropped; the host renames) → playing: scene → step (vote, check, riddles, feast,
search, fight, wait for the game master) → the next scene, or the end. A game master who takes over holds the next
scene until they pick it; when their tablet has not been seen for 20 s the computer goes on from the same scene
(AK-77). A player who leaves: their heroes go to a player with fewer than two, else to the computer, and back when
they return (AK-79). A pause keeps the fight exactly (AK-81).

## What a device may see (K-99, K-101, K-102, AK-76)

The TV and the phones never get the draws, another hero's task, clues or secrets, an enemy's Liv (only quarters) or
a Discord id; a pure game master gets everything except account ids.

## Not covered automatically

- Shaking and tilting a real phone (K-100): the browser tests use the buttons; drawing a rune is tested on the
  server's recogniser (TC-INT-055), the canvas by hand.
- Real TV browsers (K-04) and the look of the pictures: manual.
- The 90-minute adventure in a browser: the checker plays it 200 times per party size; the browsers play 60 min.
- AK-78's second half (the account deleted after the grace period): the foreign key `ON DELETE CASCADE` is checked
  (TC-API-039); hakoo's deletion itself is hakoo's test.
