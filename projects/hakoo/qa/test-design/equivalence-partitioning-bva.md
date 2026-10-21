# Equivalence partitioning and boundary value analysis

Black-box techniques (CTFL 4.2.1 and 4.2.2). **Equivalence partitioning (EP)** divides input into
partitions that the system should treat the same way; one value per partition is enough.
**Boundary value analysis (BVA)** tests the edges of ordered partitions, where defects cluster.
CTFL v4.0 describes **2-value BVA** (the boundary and its closest neighbour in the next partition)
and **3-value BVA** (the boundary and both neighbours).

## 1. Roster: in-game name (FR-ROSTER-02)

Rule: trimmed, 1–64 characters.

| Partition | Values | Valid? |
|---|---|---|
| P1 empty after trim | `""`, `"   "` | ✗ |
| P2 length 1–64 | `"A"` … 64 chars | ✓ |
| P3 length ≥ 65 | 65 chars | ✗ |

**2-value BVA:** 0 ✗ · 1 ✓ · 64 ✓ · 65 ✗ → **TC-API-011..014** (`RosterApiTest::test_in_game_name_length`).
Error guessing adds "spaces only" (trim must happen before the length check).

## 2. Roster: note (FR-ROSTER-03)

Rule: optional, cut to 120 characters (not rejected).

| Partition | Expected |
|---|---|
| 0–120 characters | stored as typed |
| ≥ 121 characters | stored, cut to 120 |

**BVA:** 120 → 120 · 121 → 120 → **TC-API-015**.

## 3. Roster: Mastery Rank (FR-ROSTER-04)

Rule: optional; clamped to 0–40.

| Partition | Values | Expected |
|---|---|---|
| empty | `""` | stored as "none" (NULL) |
| < 0 | −1 | stored as 0 |
| 0–40 | 0 … 40 | stored as typed |
| > 40 | 41 | stored as 40 |

**3-value BVA** around both boundaries: −1, 0, 1 and 39, 40, 41 → **TC-API-016..021**.

## 4. Roster: game (FR-ROSTER-05)

| Partition | Values | Valid? |
|---|---|---|
| supported, lower case | `warframe`, `wot`, `wows` | ✓ |
| supported, other case | `WOT` | ✓ (lower-cased) |
| unsupported | `helldivers` | ✗ |

→ **TC-API-022..025**. A non-ordered set has no boundaries, so only EP applies.

## 5. Warframe builds (FR-BUILDS-02, FR-BUILDS-03)

| Input | Valid partition | Invalid partitions | BVA values |
|---|---|---|---|
| title | 1–80 chars | empty, ≥ 81 | 0, 1, 80, 81 |
| frame | `[a-z0-9-]{1,32}` | empty, 33+, other characters (`rhino!`) | 0, 1, 32, 33 |
| code | base64, 1–512 | empty, 513+, non-base64 (`@@`) | 0, 1, 512, 513 |
| builds per member | 0–19 before saving → added | 20 before saving → oldest replaced | 19, 20 |

Status: designed, **not yet automated** (backlog: `BuildsApiTest`).

## 6. Loot codes (FR-LOOT-02, FR-LOOT-04)

| Input | Valid | Invalid | BVA |
|---|---|---|---|
| code length | 3–40 | ≤ 2, ≥ 41 | 2, 3, 40, 41 |
| reporters | 1–4 → not blocked | 5 → blocked | 4, 5 |

Status: designed, **not yet automated** (backlog: `CodesApiTest`).

## 7. Language code (FR-I18N-03, NFR-SEC-04)

| Partition | Values | Expected |
|---|---|---|
| enabled language | `sv`, `de` | that language |
| unknown language | `xx` | `en` |
| malicious | ``en`; DROP TABLE hakoo_texts; --`` | `en`, table intact |

→ **TC-API-031..034**.

## 8. Move speed (FR-GAMES-11)

`moveMs` in `POST ?action=settings` (any board game). Valid: a whole number 100–2000.

| Partition | Values | Expected |
|---|---|---|
| below the range | `0`, `-600`, `99` (boundary − 1) | 400 `bad_settings`, nothing stored |
| valid | `100` (lower boundary), `700`, `2000` (upper boundary) | stored, `me` returns it in every game |
| above the range | `2001` (boundary + 1) | 400 `bad_settings` |
| not a whole number | `600.5`, `"600"`, `null`, missing | 400 `bad_settings` |
| malicious | `"600; DROP TABLE hakoo_board_settings"` | 400, table intact |
| no login / GET | — | 401 `not_logged_in` / 405 `post_required` |

→ **TC-API-165..168** (export and delete: TC-API-169). The page offers only 100–2000 in steps of 100.

## 9. Appearance (FR-SITE-02)

`POST appearance.php?action=save {theme, bg, text, accent, pattern}`.

| Partition | Values | Expected |
|---|---|---|
| valid names | each of the 4 themes × each of the 4 patterns | stored |
| own colours | `#102030`, `#FFAA00` (upper case), `null` (the theme's) | stored, in lower case |
| bad colour | `#fff` (3 digits), `white`, `#12345g`, `#1234567`, the number `123456` | 400 `bad_look` |
| bad name / missing | `neon`, `null`, a pattern `stars`, `{}` | 400 `bad_look` |
| malicious | a theme or colour with SQL in it | 400, table intact |
| text contrast | 4.48:1 (`#ffffff` on `#777777`, just below 4.5) / 4.54:1 (on `#767676`, just above) | 400 `hard_to_read` / stored |
| accent contrast | an accent close to the background (below 3:1) | 400 `hard_to_read` |

→ **TC-API-171..173**. The page checks the same contrast before it shows a look (TC-E2E-169).

## 10. A background of several colours (FR-SITE-05)

`gradient` in `POST appearance.php?action=save`.

| Partition | Values | Expected |
|---|---|---|
| type | `linear`, `radial` / `conic`, a CSS string | stored / 400 `bad_look` |
| angle (linear) | `0`, `360` (boundaries), `90` / `-1`, `361`, `"90"` (a string) | stored / 400 |
| place (radial) | `center`, `bottom right` / `middle` | stored / 400 |
| number of colours | 2 and 4 (boundaries) / 1 and 5 | stored / 400 |
| positions | 0, 30, 30, 100 (equal neighbours allowed) / 60 then 40 (going down), 101 | stored / 400 |
| colour | `#0B2A4A` (upper case, stored lower) / `navy` | stored / 400 |
| stops not a list | `{"a": …, "b": …}` | 400 |
| readability | light text on a gradient ending in beige; the HAKOO theme with a text colour readable on its deep purple but not on its brighter purple | 400 `hard_to_read` |

→ **TC-API-175, TC-API-176**; in the page TC-E2E-174.

## 11. Keeping a look that is hard to read (FR-SITE-02, 2026-10-03)

`confirmed` in `POST appearance.php?action=save` on a look below the contrast limits.

| Partition | Values | Expected |
|---|---|---|
| not confirmed | missing, `false`, `"yes"`, `1` | 400 `hard_to_read` |
| confirmed | `true` (exactly) | stored; `confirmed` itself is not stored |
| confirmed but invalid | `true` with an unknown theme | 400 `bad_look` (confirming skips only the contrast check) |

On the page (state transitions): warning → Save → question → Keep (saved) / Go back, Escape or 5 s without an answer
(back to the saved look, suggestion offered) → TC-E2E-180, TC-E2E-181. → **TC-API-197**.

## 12. Players and colour in Vikingludo (FR-LUDO-20, 2026-10-15)

`players` and `color` in `POST ludo.php?action=new` with `rules.board = 'viking'`.

| Partition | Values | Expected |
|---|---|---|
| royal board | players 2–4, colour 0–3 | 200, 4 seats |
| six-armed board | players 5–6, colour 0–5 | 200, 6 seats |
| too few / too many players | 1, 7 | 400 `bad_players` |
| colour with no seat | colour 4 with 4 players, colour 6 with 6 players | 400 `bad_players` |
| 5 players on the classic board | players 5, no board | 400 `bad_players` |
| unknown board | `'hex'` | 400 `bad_rules` |

Boundaries tried: 1/2, 4/5, 6/7 players; colour 3/4 on the royal board and 5/6 on the six-armed one. → **TC-API-302**.
On the page the choices that would give `bad_players` are not shown (TC-E2E-240).

## 13. What a Path of Berserk run may report (FR-BERSERK-09, 2026-10-17)

`berserk.php ?action=finish` (`bk_check()`). The level's most (`bk_totals()` from `js/games/berserk/levels.json`): 14 raiders,
6 axemen, 3 shieldmen, 1 Jarl, 6 barrels and crates, 4 parts.

| Input | Valid partitions | Invalid partitions | Boundary values tested |
|---|---|---|---|
| enemies of a kind | integer 0..the level's most | negative, above the most, text, a kind the level lacks | 14 (ok, a won level), 15, −1, "14", "dragon" |
| barrels | 0..6 | above 6 | 6, 7 |
| parts reached | 1..4; a won level 4 | 5; a won level below 4 | 4, 5, 3 at a win |
| health at a win | 1..100 | 0, above 100 | 1, 100, 0, 101 |
| lives at a win | 1..3 | 0, above 3 | 1, 3, 0, 4 |
| won | true / false | anything else | "yes" |
| seconds to a win (the server's clock) | 60 and more | under 60 | 59, 60 |
| age of the run | up to 6 hours | older | 6 h + 5 s |
| finishing | once, with the token | a second time, a wrong token, no run | twice, "not-the-token", an SQL string as the run id |

A lost run is valid whatever its time and without health or lives (they count 0). Tests: TC-API-304, TC-API-305.


## 14. Yatzy: the rows, the bonus and a new game (FR-YATZY-01..04, FR-YATZY-09, 2026-10-18)

Each row has two classes: the dice fit (they score) or they do not (0). The boundary hands are the ones that almost fit,
or fit two rows. `players` and `hold` in `POST yatzy.php`.

| Input | Valid classes | Invalid classes | Boundary / chosen values |
|---|---|---|---|
| Two pairs | two different numbers twice (also inside a full house) | one pair; four or five of a kind | 5 5 3 3 1 → 16, 6 6 5 5 5 → 22, 4 4 4 4 2 → 0, 4 4 4 4 4 → 0 |
| Full house | 3 + 2 of two numbers | four of a kind + 1; five of a kind | 3 3 3 5 5 → 19, 3 3 3 3 5 → 0, 3 3 3 3 3 → 0 |
| Straights | 1-5 (small), 2-6 (large), any order | one missing, the other straight | 5 3 1 2 4 → 15, 2-6 in small → 0, 1-5 in large → 0, 2 3 4 5 5 → 0 |
| Three / four of a kind | 3 or more / 4 or more equal | fewer | five of a kind in three → 15; 6 6 6 1 1 in four → 0 |
| Upper sum for the bonus | 63 and more | under 63 | 62 (no bonus), 63 (+50) |
| Chance | any hand | — | 1 1 1 1 1 → 5 (the lowest), the best sheet 374 |
| Rolls in a turn | 1..3 | 0 (writing first), 4 | write before rolling, a 4th roll |
| `hold` | distinct ints 0..4; none on the first roll; 0..4 of them later | 5 dice kept, 5 or more as an index, a repeat, not a list | [0, 2, 4], [0..4], [7], [0, 0], "all" |
| `players` | computer 1..6, friends 2..6 (an int) | 0, 7, friends 1, "2" | 1, 6, 0, 7 |
| `cat` | an int 0..14, an empty row (forced order: the first empty one) | written already, 15, "3", Yatzy before ones in forced order | 13 twice, 15, "3" |

Tests: TC-UNIT-YATZY-01..06, TC-API-324, TC-API-327.
