# Test design: mystery content and the checker (US-16)

Test basis: K-50..K-54, AK-39..AK-42, K-28, K-36 and AK-23 in [the requirements](../requirements/kravsammanstallning.md).
The format: [docs/mystery-format.md](../../docs/mystery-format.md). The module `modules/mystery-content.inc.php` is
tested on its own, without a database (TC-INT, `MysteryContentTest.php`); the deny rule for the content folder over
HTTP (TC-API-021, `GateTest.php`).

Contains spoilers for The Last Toast.

## Exhaustive testing of the variants (TC-INT-012, AK-39)

The Last Toast has 5 culprits × 3 weapons = 15 variants, and 2 lengths. All 30 are solved; each must leave exactly
the drawn culprit and weapon. Small enough to test every one, so no sampling.

## One clue missing (TC-INT-013, AK-41)

60 min allows one private clue to be missing (`spare: 1`): each of its 27 private clues is dropped in turn, in all
15 variants (405 cases). In 90 min (`spare: 0`) the critical clues are the weapon pair (the tower room and the
wound): without the wound three weapons are left, which shows that 90 min has no shortcut for the weapon.

| Length | Shortcuts | What the players must work out |
|---|---|---|
| 60 min | the doctor's notebook (the time of the murder), something out of place (the weapon) | who has an alibi, or the traces |
| 90 min | none; five more clues, all colour | the time of the murder from the phone call, the doctor and the alarm; the weapon from the wound and the tower room |

## How each suspect is ruled out

Two independent routes, so one clue that is never found leaves the culprit certain. The window of the murder
(22.50–23.15) comes from the call, the doctor and the alarm (all three known in 90 min; the notebook states it in 60).

| Suspect | Route 1: alibi in the window (innocent version) | Route 2: traces in the tower room |
|---|---|---|
| Oskar | on the phone in the booth beside Signe | the blow (right-handed when not his) or the scent (lavender or pipe) |
| Agnes | seen through the office door | blow (left-handed) or scent (violets or pipe) |
| Niels | out of the front door (Signe) and tracks only to the shed (Pelle) | blow (left) or scent (violets or lavender) |
| Willem | never left the bridge table (Ruth) | blow (right) or scent (violets or pipe) |
| Margaret | never left Hilma's kitchen table | blow (left) or scent (lavender or pipe) |

The guilty version of each check gives no alibi and shows the gap (a missed call, a locked silent office, tracks up
the tower stairs, twenty minutes away from the table, a notebook that never came back). The handedness pairs
(left: Oskar, Willem) and the scent pairs (violets: Oskar, Margaret; lavender: Agnes, Willem; pipe: Niels) overlap
so that only both traces together point at one person.

## Fault seeding (TC-INT-016, AK-40)

Broken copies of The Last Toast that the checker must reject, with the message it must give:

| Fault | Expected message |
|---|---|
| the guilty version of Oskar's check gives him an alibi | `the real culprit oskar is ruled out` |
| the blow, the scent and Margaret's check removed | `90 min, agnes + falcon: culprit not certain, still agnes, margaret`; the rules that can no longer fire; the texts nothing uses |
| a version of the blow no longer names Niels | `clue blow: niels is in 0 versions` |
| `alibi_wilem` instead of `alibi_willem` | `fact alibi_wilem is never used by a rule` |
| a clue in the attic | `unknown place attic` |
| a Swedish text missing, a stale Danish text | `sv: missing clue.scent.pipe`, `da: not used clue.old_clue` |
| the clue that gives the weapon away in 60 min removed | `60 min, oskar + falcon without wound: weapon not certain` |

## Equivalence classes for the texts (TC-INT-015)

Every key in en, sv, da and nl, nothing extra; no text left in English except the names of people (and `place.salon`
in Dutch, the same word). An unknown language gets English (K-28).

## What a card shows (TC-INT-014, K-53)

For every variant and both lengths, the cards (id, title, place, source) are the same; only the text differs, and
no card carries the version key. The folder card has the same title for two culprits and different texts.

## The draw (TC-INT-017, K-36, AK-23)

300 draws: every draw is a valid variant, and every suspect and every weapon comes up (the chance that one of the 5
suspects never comes up in 300 fair draws is about 5 × 0.8³⁰⁰, far below 10⁻²⁸).

## The content folder is not served (TC-API-021, K-54, AK-42)

`mystery.json`, `en.json` and `sv.json` of The Last Toast give 403 without login and as the owner, and the body has
no solution; the folder itself gives 403. Checked by hand that the test fails without `content/.htaccess` (200).
On the live host the same `.htaccess` must be verified after the upload.
