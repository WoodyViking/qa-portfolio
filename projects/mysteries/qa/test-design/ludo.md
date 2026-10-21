# Test design: Kunglig Vikingludo (US-15)

Test basis: K-42..K-48 and AK-29..AK-35 in [the requirements](../requirements/kravsammanstallning.md).
The rules engine (`modules/ludo-engine.inc.php`) is tested on its own, without a database (TC-INT); the module over
the API (TC-API); the screens in real browsers (TC-E2E). The server rolls the die (K-48), so the API and browser
tests play on until what they look for has happened; the exact rules are tested in the engine, where the die is given.

## State transitions of a piece (TC-INT-003, 007)

nest → (6) start square, ring 0 → ring up to 50 (four arms; 76 with six) → way home 51..55 → goal 56 (82), exact roll.
Invalid: out of the nest without a 6; past the goal; a piece in the goal moving.

## Boundary values

| What | Values tested | Test |
|---|---|---|
| Players | 1, 2, 4, 5, 6, 7 | TC-INT-001, TC-API-016 |
| Last ring square / way home | 50 → 51 | TC-INT-001, 007 |
| Goal | exactly 56, 57 refused | TC-INT-007 |
| Feather / crown face | the last one on the ring (49, 46): no move | TC-INT-004, 005 |
| Tries to come out | 1st, 2nd, 3rd miss; a piece in the goal is not "out" | TC-INT-003 |
| Die | 0 and 7 refused | TC-INT-008 |

## Decision table: landing (TC-INT-006)

| Square | Another colour there | Mover comes out of the nest | Result |
|---|---|---|---|
| Plain | yes | – | the other piece is knocked home |
| Crown | yes | no | the mover goes home itself; the other stays (AK-32) |
| Own start (crown) | yes | yes | the other piece is knocked home (Danish rule) |
| Any | own colour | – | both stand there |

## Who may do what (TC-API-017, 018)

| Who | Lobby: colour | Lobby: way of rolling | Game: colour | Game: way of rolling | Game: roll / move |
|---|---|---|---|---|---|
| Player, free colour | yes | yes | no (`not_lobby`) | yes | only on their turn (`not_your_turn`) |
| Player, taken colour | no (`colour_taken`) | | | | |
| Board | no (`not_player`) | no | no | no | no |
| Anybody while paused | | | | | no (`not_playing`) |

## A player who leaves (K-49): state transitions of a place (TC-API-019, 020, TC-E2E-010)

here → (its player leaves) away → (host: the computer) bot → (the player comes back) here
away or bot → (host: a player at the table without a place) here, with the new player's name, the same colour and pieces
away → (the host is gone too, at the next poll) bot
Invalid: the host's choice for a place that is here (`seat_taken`), the computer for a bot place (`seat_taken`),
a player who already has a place (`has_seat`), a list that changed under the host (`changed`), anyone but the host (`not_host`).

The computer's pick (TC-INT-010): into the goal > knock someone > out of the nest > onto the way home > onto a crown >
the piece furthest ahead; never onto another colour's piece on a crown (it would go home itself).
