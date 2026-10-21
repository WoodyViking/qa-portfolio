# Decision table testing

CTFL 4.2.3: a decision table lists every combination of **conditions** and the resulting
**actions**. Each column is a **rule**; the minimum coverage is one test per feasible rule.

## 1. Start page: saving the choice (FR-START-02, FR-START-03)

| | R1 | R2 | R3 | R4 |
|---|---|---|---|---|
| **Condition:** "Remember my choice" is on | F | F | T | T |
| **Condition:** picked page | Community | Games | Community | Games |
| **Action:** store `hakoo_start` | — | — | page=community | page=games |
| **Action:** open the picked page | ✓ | ✓ | ✓ | ✓ |
| Test | TC-E2E-001 | TC-E2E-002 | TC-E2E-003 | TC-E2E-004 |

## 2. Start page: what happens when it is opened (FR-START-03, FR-START-04)

| | R1 | R2 | R3 | R4 |
|---|---|---|---|---|
| **Condition:** a choice is saved | F | F | T | T |
| **Condition:** URL has `?choose=1` | F | T | F | T |
| **Action:** redirect to the saved page | — | — | ✓ | — |
| **Action:** show the chooser | ✓ | ✓ | — | ✓ |
| **Action:** show "Your saved start page" + Forget | — | — | — | ✓ |
| Test | TC-E2E-009 | TC-E2E-022 | TC-E2E-005 | TC-E2E-006 |

## 3. Deleting my data (FR-GDPR-02, FR-GDPR-07, NFR-SEC-01)

| | R1 | R2 | R3 | R4 | R5 |
|---|---|---|---|---|---|
| **Condition:** valid session | F | T | T | T | T |
| **Condition:** method is POST | – | F | T | T | T |
| **Condition:** confirm = `DELETE` | – | – | F | T | T |
| **Condition:** database error during delete | – | – | – | F | T |
| **Action:** HTTP status | 401 | 400 | 400 | 200 | 500 |
| **Action:** data deleted | no | no | no | **all** | **none** (rollback) |
| **Action:** logged out | no | no | no | yes | no |
| Test | TC-API-001 | TC-API-006 | TC-API-004 | TC-API-005 | TC-MAN-008 |

"–" = does not matter (the rule is decided by an earlier condition). R5 needs fault injection
(for example, a locked table) and is a manual test for now.

## 4. Loot code blocking (FR-LOOT-02, FR-LOOT-03)

| | R1 | R2 | R3 |
|---|---|---|---|
| **Condition:** reporter is admin and uses "block" | F | F | T |
| **Condition:** distinct reporters after this report | ≤ 4 | 5 | – |
| **Action:** code blocked for everyone | no | yes | yes |

Status: designed, not yet automated.

## 5. Turning the board (FR-GAMES-10, FR-STRATEGO-12)

| | R1 | R2 | R3 | R4 | R5 |
|---|---|---|---|---|---|
| **Condition:** logged in | F | T | T | T | T |
| **Condition:** Medium won in this game | – | F | T | T | T |
| **Condition:** turned from own side (Stratego) | – | – | ≤ 90° | > 90° | – |
| **Condition:** pointer moved ≥ 6 px before release | – | – | – | – | T |
| **Action:** turning buttons shown, board turns | no | no | yes | yes | yes |
| **Action:** own Stratego ranks shown | yes | yes | yes | **no** (backs) | – |
| **Action:** a move is sent | – | – | on click | on click | **no** (it was a drag) |
| Test | TC-E2E-156 | TC-E2E-156 | TC-E2E-157, TC-E2E-159 | TC-E2E-159 | TC-E2E-158 |

The enemy's unseen ranks are not a condition here: they never reach the browser (FR-STRATEGO-04), so
no view can show them; TC-E2E-159 checks that no Blue rank is in the page while the board is turned.

## 6. Kunglig Vikingludo: where a piece lands (FR-LUDO-19)

`LudoGame::move()` in Vikingludo. "Another colour" means a piece of another colour on the ring square where the move ends.

| | R1 | R2 | R3 | R4 | R5 |
|---|---|---|---|---|---|
| **Condition:** the square is a crown | F | T | T | T (own start) | – |
| **Condition:** another colour stands there | T | T | T | T | – |
| **Condition:** the piece comes out of the nest | F | F | F (crown face) | T | F |
| **Condition:** the square is a feather | F | F | F | F | T |
| **Action:** the other colour goes to its nest | yes | no | no | yes | – |
| **Action:** the moving piece goes to its own nest | no | **yes** | **yes** | no | no |
| **Action:** the piece jumps on to the next feather (event `j`) | – | – | – | – | yes |
| Test | TC-UNIT-LUDO-12 | TC-UNIT-LUDO-12 | TC-UNIT-LUDO-12 | TC-UNIT-LUDO-12 | TC-UNIT-LUDO-12 |

The page shows the same decision before the move, on the piece's button ("knocks out Red", "safe on a crown: back to
your nest", "a feather: on to the next one"): TC-E2E-242.

