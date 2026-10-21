# Risk register

Risk-based testing (CTFL 5.2). **Risk level = likelihood × impact**, each on a scale of 1–5.
Level ≥ 12 = **high** (test at more than one level, automate), 6–11 = **medium**, ≤ 5 = **low**.

The risks below come from section 8 of the [requirements](requirements/kravsammanstallning.md).
Likelihood (L) and impact (I) are **not yet assessed**: the owner sets them in the first review.

## Product risks

| ID | Risk | L | I | Level | Mitigation (test response) | Tests |
|---|---|---|---|---|---|---|
| R-01 | Not every login provider exposes whether 2FA is on, so few providers can be allowed (K-22, K-23). | | | | Spike per provider before building login | |
| R-02 | A player sees another player's private clues through the network or dev tools (NFR-05, K-13). | | | | Server sends each device only its own data (my_view); a player's token works only with their own login | TC-API-009, TC-API-010, TC-E2E-003 |
| R-03 | Actions take longer than 5 s to reach every board (NFR-02), or polling loads the shared host too much (ADR-001: about 7 requests a second for 10 devices). | | | | Poll every 1.5 s only while playing (3 s waiting or paused, 10 s hidden tab); answer an unchanged game with one line; load test with the maximum number of devices (still undefined) | TC-API-012, TC-E2E-003 |
| R-04 | A paused game cannot be resumed after a long time or a release (NFR-03). | | | | Saved-game regression tests across versions | |
| R-05 | Personal data survives account deletion after the 14-day undo window (K-25, GDPR Art. 17). | | | | Integration test per table, like hakoo's R-01 | |
| R-06 | Disconnect/vote/AI-player flows dead-lock or lose clues (K-17..K-20). | | | | State-transition test design | |
| R-07 | Accessibility is not good enough because NFR-09 has no measure. | | | | Pick a WCAG level; test with assistive tech | |
| R-08 | A Ludo player leaves or loses the connection mid-game, and the game waits for them for ever (US-15, K-16). | | | | K-49: the host gives the place to the computer or a new player; with the host gone the computer takes it. A lost connection (no leave) is not detected yet (K-17) | TC-API-019, TC-API-020, TC-E2E-010 |
| R-09 | A mystery has a logical hole: more than one suspect fits, the real one is ruled out, or a clue that is never found leaves the case open (K-51, K-52). | | | | The logic is data and every variant × length is solved by the checker, also with the spare clues missing; fault seeding shows the checker catches holes | TC-INT-012, TC-INT-013, TC-INT-016 |
| R-10 | Players read the solution: the content files are downloaded, or a card gives away which variant is played (K-53, K-54, NFR-05). | | | | content/.htaccess denies the folder (verify live after upload); cards carry the same title, place and source in every variant and never the version key | TC-API-021, TC-INT-014 |

## Project risks

| ID | Risk | L | I | Level | Mitigation |
|---|---|---|---|---|---|
| R-20 | Very large scope (all categories, all devices, app, five logins from the start). | | | | Build and test in the agreed priority order (A-01, K-22, K-04) |
| R-21 | Family-sharing verification for under-13s is not technically possible (US-08). | | | | Feasibility spike; drop US-08 if not possible |
| R-22 | Regulations not yet researched (EAA/WCAG, DSA, PEGI, consumer law, bookkeeping). | | | | Write regulatory requirements before launch, like hakoo's REG-* |
| R-23 | Legal validity of creator content passing to the game on account deletion (K-27). | | | | Legal review of the terms |
