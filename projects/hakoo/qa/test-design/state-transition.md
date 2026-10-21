# State transition testing

CTFL 4.2.4: a state transition diagram shows the **states** of a system, the **events** that
move it between states and any **guards** (conditions). Coverage levels: all states,
**valid transitions** (0-switch coverage), and invalid transitions.

## 1. Start-page consent (FR-START-02..05)

States:
- **S0 No consent**: "Remember my choice" is off, nothing stored.
- **S1 Consent pending**: "Remember my choice" is on, nothing stored yet.
- **S2 Choice saved**: `hakoo_start` stored.

```
            toggle on                     pick page
   ┌────┐ ───────────► ┌────┐ ─────────────────────► ┌────┐
   │ S0 │              │ S1 │                        │ S2 │
   └────┘ ◄─────────── └────┘                        └────┘
     ▲  │   toggle off                                │  │
     │  │ pick page (nothing stored)                  │  │ open start page
     │  └──► S0                                       │  └──► redirect, stays S2
     │                                                │
     └──────────── "Forget my choice" / toggle off ◄──┘
```

| # | From | Event [guard] | To | Expected | Test |
|---|---|---|---|---|---|
| T1 | S0 | toggle "Remember" | S1 | button shows ✅, `aria-pressed=true` | TC-E2E-010 |
| T2 | S1 | toggle "Remember" | S0 | nothing stored | TC-E2E-008 |
| T3 | S0 | pick a page | S0 | page opens, nothing stored | TC-E2E-001/002 |
| T4 | S1 | pick a page | S2 | page opens, choice stored | TC-E2E-003/004 |
| T5 | S2 | open start page [no `?choose`] | S2 | redirect to the saved page | TC-E2E-005 |
| T6 | S2 | open start page [`?choose=1`] | S2 | chooser + "Your saved start page" | TC-E2E-006 |
| T7 | S2 | "Forget my choice" | S0 | storage removed, toggle off | TC-E2E-007 |

All 3 states and all 7 valid transitions are covered (100 % 0-switch coverage).
Invalid transition to test: "Forget my choice" in S0 must not be possible (the button is hidden): TC-E2E-009.

## 2. Member lifecycle (FR-USER, FR-GDPR)

States: **Unknown** (no row) → **Member** (row exists) → **Deleted** (no row, hash in `hakoo_deletions`).

| # | From | Event | To | Test |
|---|---|---|---|---|
| M1 | Unknown | first login or first save | Member | TC-INT-001, TC-API-026 |
| M2 | Member | login | Member (`last_login_at` updated) | TC-INT-002 |
| M3 | Member | delete my data | Deleted | TC-INT-004, TC-API-005 |
| M4 | Deleted | backup restored | Member (wrongly) | TC-INT-010 (setup) |
| M5 | Member [hash in log] | re-apply deletions | Deleted | TC-INT-010 |
| M6 | Deleted | logs in again | Member (a new, empty member) | TC-MAN-001 |

M6 is intentional: deletion does not ban a person, and logging in again starts from zero.

## 3. Access to a page that needs a Wargaming account (FR-LINK-04, FR-LINK-05)

States of a visitor opening the WoT tracker:
**A** not logged in · **B** logged in, nothing linked · **C** linked, no valid token in this browser ·
**D** linked + valid token (tracker usable).

| # | From | Event | To | Expected | Test |
|---|---|---|---|---|---|
| L1 | A | open tracker | A (on profile) | redirect to `profile.html?need=wot&return=…`, login button keeps need/return | TC-E2E-050 |
| L2 | A | log in with Discord | B (or C/D) | back on the profile with the same need/return | TC-API-052 (+ TC-MAN-001 live) |
| L3 | B | open tracker | B (on profile) | banner "needs your Wargaming account" | TC-E2E-051 |
| L4 | B | link via Wargaming | D | back on the tracker, token in localStorage | TC-E2E-052, TC-API-042 |
| L5 | C | open tracker | C (on profile) | banner "needs a fresh Wargaming sign-in" | TC-E2E-055 |
| L6 | C | connect Wargaming | D | back on the tracker | TC-E2E-052 (same flow) |
| L7 | D | token expires / rejected by Wargaming | C | `reconnectWargaming()` → profile | code review (needs a real expired token) |
| L8 | any | forged/foreign-token callback | unchanged | refused, `link_error=wot` | TC-API-040, TC-API-041, TC-E2E-054 |

The WoWs tracker uses the same states without C: public WoWs data needs no token (TC-E2E-056).

## 4. A shadow in Fog of War (FR-CHESS-16, FR-CHESS-17)

What a player remembers about one square that holds a piece of the opponent.

| State | Event | New state | Test |
|---|---|---|---|
| In sight (the piece is on the board) | the piece leaves sight (it moves away, or my piece that saw it moves) | **Shadow** (the piece where it was last seen) | TC-CHS-022 |
| Shadow | the square is in sight again and the piece is there | In sight | TC-CHS-023 |
| Shadow | the square is in sight again and it is empty | No shadow | TC-CHS-024 |
| Shadow | the same kind of piece is seen on another square | No shadow here (the nearest copy is dropped) | TC-CHS-024 |
| Shadow | the opponent moves unseen | Shadow (nothing changes) | TC-CHS-027, TC-API-164 |
| My piece | it is taken | **Shadow of the taker** on that square, square marked | TC-CHS-025, TC-CHS-026 |
| Marked square | I make my move | Not marked; the shadow stays | TC-CHS-025, TC-E2E-155 |

Invalid transitions (must not happen): a shadow appears on a square in sight; a shadow shows something the
player has not seen (checked by running the same game with different unseen moves, even a promotion, and
requiring the same view: TC-CHS-027, TC-API-164); the player who took the piece is marked.

## 5. A Yatzy turn (FR-YATZY-01, FR-YATZY-05, FR-YATZY-07)

| State | Event | New state | Test |
|---|---|---|---|
| Waiting (0 rolls) | roll (keeping nothing) | Rolled 1 | TC-UNIT-YATZY-05, TC-API-324 |
| Rolled 1 / Rolled 2 | roll, keeping 0..4 dice | Rolled 2 / Rolled 3 (the kept dice stay) | TC-UNIT-YATZY-05, TC-API-324, TC-E2E-255 |
| Rolled 1..3 | write in an empty row | the next player's Waiting; the last row of the last sheet: **Game over** | TC-UNIT-YATZY-05, TC-UNIT-YATZY-07 |
| Waiting, the player to move is a person | 90 s without a roll or a score, another person presses "let the computer play" | the computer plays that seat | TC-API-326 |

Invalid transitions (refused): write while Waiting (`roll_first`); keep dice on the first roll, or all five later (`bad_hold`);
a 4th roll (`no_rolls_left`); a row written again or out of the forced order (`bad_cat`); any move by a player whose turn it
is not (`not_your_turn`) or after Game over (`game_over`). Tests: TC-UNIT-YATZY-05..07, TC-API-324, TC-API-325.
