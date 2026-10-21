# Traceability matrix

Links each requirement to the user story it comes from and the tests that cover it (CTFL 1.4.4).
It shows **coverage** (which requirements have tests) and supports **impact analysis** (which tests to
re-run when a requirement changes).

**Execution status** (test cycle 1, 2026-09-26, Docker test environment, see
[test-reports/cycle-1-completion-report.md](test-reports/cycle-1-completion-report.md)):
✅ passed · ❌ failed · ⏳ not run · 🔍 checked manually during development · — no test yet.
For rows that also list manual tests (TC-MAN), ✅ refers to the automated tests only.

## Functional

| Requirement | Story | Tests | Status |
|---|---|---|---|
| FR-START-01 | US-01 | TC-E2E-009, TC-E2E-011, TC-MAN-003 | ✅ |
| FR-START-02 | US-02 | TC-E2E-001..004, TC-E2E-008, TC-E2E-022, TC-E2E-150 | ✅ |
| FR-START-03 | US-02 | TC-E2E-005 | ✅ |
| FR-START-04 | US-02 | TC-E2E-006 | ✅ |
| FR-START-05 | US-02 | TC-E2E-007 | ✅ |
| FR-START-06 | US-02 | TC-E2E-010, TC-MAN-005 | ✅ |
| FR-START-07 | US-02 | TC-E2E-009 | ✅ |
| FR-GAMES-01..03 | US-03 | TC-E2E-020, TC-E2E-060, TC-E2E-151, TC-MAN-004 | ✅ |
| FR-GAMES-04 | US-01 | TC-E2E-040 (page loads), review | ✅ |
| FR-GAMES-05 | US-17 | TC-E2E-021, TC-E2E-076..078, TC-API-088 | ✅ |
| FR-GAMES-06 | US-03 | TC-E2E-021, TC-E2E-079 | ✅ |
| FR-GAMES-07 | US-18, US-22 | TC-API-106, TC-API-120, TC-API-145, TC-E2E-098 | ✅ |
| FR-GAMES-08 | US-19, US-20, US-25, US-27 | TC-E2E-099, TC-E2E-040 (pages load) | ✅ |
| FR-GAMES-09 | US-24 | TC-OTH-005, TC-API-112, TC-API-113, TC-E2E-081, TC-E2E-102, TC-E2E-103, TC-E2E-122 | ✅ |
| FR-GAMES-10 | US-28 | TC-E2E-156..158, TC-E2E-195 (on a phone, in ☰); a fresh Medium win turning it on in the same visit: no test yet | ✅ |
| FR-GAMES-11 | US-29, US-30 | TC-API-165..169, TC-E2E-160, TC-E2E-162, TC-E2E-164, TC-E2E-167 | ✅ |
| FR-GAMES-12 | US-29 | TC-E2E-164 (Chess, Gomoku); Hnefatafl and Stratego: review (same code path) | ✅ / 🔍 |
| FR-GAMES-13 | — | TC-API-166, TC-API-169, TC-API-177..179 | ✅ |
| FR-GAMES-14 | — | TC-E2E-188 | ✅ |
| FR-GAMES-15 | — | TC-E2E-188 | ✅ |
| FR-GAMES-16 | — | TC-E2E-191, TC-E2E-198, TC-E2E-153 (the heading and the link in Swedish); review 2026-10-07: every short rule and rules page read against its engine | ✅ |
| FR-GAMES-17 | — | TC-E2E-192, TC-E2E-193, TC-E2E-086, TC-E2E-101, TC-E2E-065, TC-E2E-092, TC-E2E-108, TC-E2E-135; manual (browser 2026-10-05: every picture checked against its rule) | ✅ |
| FR-GAMES-18 | — | TC-E2E-194, TC-E2E-195 | ✅ |
| FR-GAMES-19 | US-28 | TC-E2E-205, TC-E2E-196, TC-E2E-195 | ✅ |
| FR-GAMES-20 | — | Path of Berserk keys: TC-API-308, TC-E2E-252; appearance, board settings and Ludo and Yatzy choices: their own tests (FR-SITE-03, FR-LUDO-18, FR-YATZY-14: TC-E2E-256 in a new browser context); the language and the start page: not yet (browser only) | ⏳ |
| FR-CHESS-01 | US-19 | TC-CHS-001, TC-CHS-002, TC-CHS-007..010, TC-API-089 | ✅ |
| FR-CHESS-02 | US-19 | TC-CHS-006, TC-API-091, TC-API-092, TC-API-094, TC-E2E-082 | ✅ |
| FR-CHESS-03 | US-19 | TC-CHS-003..005, TC-API-094 | ✅ |
| FR-CHESS-04 | US-19 | TC-CHS-011..015, TC-API-089..091, TC-E2E-081 | ✅ |
| FR-CHESS-05 | US-19 | TC-E2E-081, TC-E2E-083, TC-E2E-085 | ✅ |
| FR-CHESS-06 | US-03, US-19 | TC-API-093, TC-API-096, TC-E2E-080, TC-E2E-085, TC-E2E-087; 30-day purge: review of `board-game-api.inc.php` | ✅ / 🔍 |
| FR-CHESS-07 | US-19 | TC-API-095, TC-E2E-085 | ✅ |
| FR-CHESS-08 | US-21 | TC-API-102 | ✅ |
| FR-CHESS-09 | US-21, US-22 | TC-API-104, TC-API-105, TC-E2E-094..097 | ✅ |
| FR-CHESS-10 | — | TC-INT-003..005, TC-API-107 | ✅ |
| FR-CHESS-11 | US-19 | TC-E2E-084, TC-E2E-087 | ✅ |
| FR-CHESS-12 | US-23 | TC-CHS-016, TC-API-111, TC-E2E-100, TC-E2E-101 | ✅ |
| FR-CHESS-13 | US-23 | TC-CHS-017, TC-API-109 | ✅ |
| FR-CHESS-14 | US-23 | TC-CHS-018..020, TC-API-110; Hard (peeks) v Medium 6/6 measured during development | ✅ / 🔍 |
| FR-CHESS-15 | US-23 | TC-API-108, TC-API-109, TC-API-164, TC-CHS-027, TC-E2E-100 | ✅ |
| FR-CHESS-16 | US-23 | TC-CHS-021..024, TC-CHS-027, TC-API-162..164, TC-E2E-100, TC-E2E-155 | ✅ |
| FR-CHESS-17 | US-23 | TC-CHS-025, TC-CHS-026, TC-API-162, TC-E2E-155 | ✅ |
| FR-OTHELLO-01 | US-20 | TC-OTH-001..003, TC-OTH-005..007, TC-API-097, TC-API-098 | ✅ |
| FR-OTHELLO-02 | US-20 | TC-OTH-004, TC-API-099, TC-E2E-090 | ✅ |
| FR-OTHELLO-03 | US-20 | TC-OTH-008..010 | ✅ |
| FR-OTHELLO-04 | US-20 | TC-API-097, TC-API-098, TC-API-100, TC-E2E-089 | ✅ |
| FR-OTHELLO-05 | US-20 | TC-API-101, TC-E2E-091 | ✅ |
| FR-OTHELLO-06 | US-03, US-20 | TC-API-099, TC-API-101, TC-E2E-088, TC-E2E-091, TC-E2E-092; 30-day purge: review | ✅ / 🔍 |
| FR-OTHELLO-07 | US-21 | TC-API-103 | ✅ |
| FR-OTHELLO-08 | US-21, US-22 | TC-API-104, TC-API-105, TC-E2E-094..097 | ✅ |
| FR-OTHELLO-09 | — | TC-INT-003..005, TC-API-107 | ✅ |
| FR-OTHELLO-10 | US-20 | TC-E2E-089, TC-E2E-091 | ✅ |
| FR-OTHELLO-11 | US-20 | TC-E2E-093 | ✅ |
| FR-OTHELLO-12 | US-29 | TC-E2E-157 (lies flat), TC-E2E-163 | ✅ |
| FR-OTHELLO-13 | US-29 | TC-E2E-160, TC-E2E-161 | ✅ |
| FR-HNEFATAFL-01 | US-25 | TC-HNE-001, TC-HNE-002, TC-HNE-013, TC-API-114 | ✅ |
| FR-HNEFATAFL-02 | US-25 | TC-HNE-003..006 | ✅ |
| FR-HNEFATAFL-03 | US-25 | TC-HNE-007..012, TC-API-117 | ✅ |
| FR-HNEFATAFL-04 | US-25 | TC-HNE-014..017; Hard v Medium measured during development | ✅ / 🔍 |
| FR-HNEFATAFL-05 | US-25 | TC-HNE-002, TC-API-114..116, TC-API-118, TC-E2E-105 | ✅ |
| FR-HNEFATAFL-06 | US-03, US-25 | TC-API-117, TC-API-121, TC-E2E-104, TC-E2E-107, TC-E2E-108; 30-day purge: review | ✅ / 🔍 |
| FR-HNEFATAFL-07 | US-25 | TC-API-119 | ✅ |
| FR-HNEFATAFL-08 | US-22, US-25 | TC-API-119, TC-API-120, TC-E2E-110 | ✅ |
| FR-HNEFATAFL-09 | — | TC-INT-003..005 | ✅ |
| FR-HNEFATAFL-10 | US-25 | TC-E2E-105..107 | ✅ |
| FR-HNEFATAFL-11 | US-25 | TC-E2E-109 | ✅ |
| FR-STRATEGO-01 | US-27 | TC-STR-001, TC-API-140 | ✅ |
| FR-STRATEGO-02 | US-27 | TC-STR-002..005, TC-API-142 | ✅ |
| FR-STRATEGO-03 | US-27 | TC-STR-005..007, TC-API-144 | ✅ |
| FR-STRATEGO-04 | US-27 | TC-STR-001, TC-STR-008, TC-API-141, TC-API-143, TC-E2E-131, TC-E2E-134 | ✅ |
| FR-STRATEGO-05 | US-27 | TC-STR-009..012; Hard v Medium 15/20 measured during development | ✅ / 🔍 |
| FR-STRATEGO-06 | US-27 | TC-STR-001, TC-STR-002, TC-API-140, TC-API-142 | ✅ |
| FR-STRATEGO-07 | US-03, US-27 | TC-API-143, TC-E2E-130, TC-E2E-134, TC-E2E-135; 30-day purge: review | ✅ / 🔍 |
| FR-STRATEGO-08 | US-27 | TC-API-144 | ✅ |
| FR-STRATEGO-09 | US-22, US-27 | TC-API-144, TC-API-145, TC-E2E-137, TC-INT-003..005 | ✅ |
| FR-STRATEGO-10 | US-27 | TC-E2E-131..134 | ✅ |
| FR-STRATEGO-11 | US-27 | TC-E2E-134, TC-E2E-136 | ✅ |
| FR-STRATEGO-12 | US-28 | TC-E2E-159 (the enemy ranks never reach the browser: TC-API-141, TC-API-143) | ✅ |
| FR-LUDO-01 | — | TC-API-182 | ✅ |
| FR-LUDO-02 | — | TC-UNIT-LUDO-01, TC-UNIT-LUDO-03, TC-UNIT-LUDO-16, TC-API-329, TC-E2E-258 | ✅ |
| FR-LUDO-03 | — | TC-UNIT-LUDO-02 | ✅ |
| FR-LUDO-04 | — | TC-UNIT-LUDO-03 | ✅ |
| FR-LUDO-05 | — | TC-UNIT-LUDO-01, TC-UNIT-LUDO-04 | ✅ |
| FR-LUDO-06 | — | TC-UNIT-LUDO-05, TC-API-181 | ✅ |
| FR-LUDO-07 | — | TC-UNIT-LUDO-06 | ✅ |
| FR-LUDO-08 | — | TC-API-180 | ✅ |
| FR-LUDO-09 | — | TC-API-181 | ✅ |
| FR-LUDO-10 | — | TC-API-181 | ✅ |
| FR-LUDO-11 | — | manual (browser), no E2E test yet | 🔍 |
| FR-LUDO-12 | — | TC-API-183, TC-API-184 | ✅ |
| FR-LUDO-13 | — | TC-API-180, TC-API-185 | ✅ |
| FR-LUDO-14 | — | TC-UNIT-LUDO-07 | ✅ |
| FR-LUDO-15 | — | TC-UNIT-LUDO-08 | ✅ |
| FR-LUDO-16 | — | TC-UNIT-LUDO-09, TC-API-187 | ✅ |
| FR-LUDO-17 | — | manual (simulation measured: median 1.5 s, 2 turns) | 🔍 |
| FR-LUDO-18 | — | TC-API-196; the form (member and visitor, tick and untick) tried in the browser 2026-10-03 | ✅ |
| FR-LUDO-19 | — | TC-UNIT-LUDO-11, TC-UNIT-LUDO-12, TC-UNIT-LUDO-13, TC-API-301, TC-E2E-240 | ✅ |
| FR-LUDO-20 | — | TC-UNIT-LUDO-10, TC-UNIT-LUDO-15, TC-API-301, TC-API-302, TC-E2E-240, TC-E2E-241 | ✅ |
| FR-LUDO-21 | — | TC-E2E-241, TC-E2E-242, TC-E2E-244 | ✅ |
| FR-LUDO-22 | — | TC-E2E-241, TC-E2E-242; the Swedish texts in a played game: manual (2026-10-15) | ✅ |
| FR-LUDO-23 | — | TC-UNIT-LUDO-14, TC-API-301 | ✅ |
| FR-YATZY-01 | US-54 | TC-UNIT-YATZY-05, TC-API-324 | ✅ |
| FR-YATZY-02 | US-54 | TC-UNIT-YATZY-01, TC-UNIT-YATZY-04 | ✅ |
| FR-YATZY-03 | US-54 | TC-UNIT-YATZY-02 | ✅ |
| FR-YATZY-04 | US-54 | TC-UNIT-YATZY-03, TC-UNIT-YATZY-04 | ✅ |
| FR-YATZY-05 | US-54 | TC-UNIT-YATZY-06, TC-API-324 | ✅ |
| FR-YATZY-06 | US-54 | TC-UNIT-YATZY-06, TC-API-325 | ✅ |
| FR-YATZY-07 | US-54 | TC-UNIT-YATZY-07, TC-API-325 | ✅ |
| FR-YATZY-08 | US-54 | TC-UNIT-YATZY-08 | ✅ |
| FR-YATZY-09 | US-54 | TC-API-323, TC-E2E-255 | ✅ |
| FR-YATZY-10 | US-54 | TC-API-325 | ✅ |
| FR-YATZY-11 | US-54 | TC-API-325 | ✅ |
| FR-YATZY-12 | US-54 | TC-E2E-255; the cup and the phone's motion sensor: manual (as Ludo) | ✅ |
| FR-YATZY-13 | US-54 | TC-API-326 | ✅ |
| FR-YATZY-14 | US-54 | TC-API-328, TC-E2E-256 | ✅ |
| FR-YATZY-15 | US-54 | TC-API-323, TC-API-327, TC-E2E-254, TC-E2E-257, TC-E2E-227, TC-E2E-149 | ✅ |
| FR-BACKGAMMON-01 | US-33 | TC-UNIT-BG-01, TC-UNIT-BG-08, TC-API-198 | ✅ |
| FR-BACKGAMMON-02 | US-33 | TC-UNIT-BG-02, TC-UNIT-BG-03 | ✅ |
| FR-BACKGAMMON-03 | US-33 | TC-UNIT-BG-03, TC-UNIT-BG-04, TC-UNIT-BG-05, TC-API-200 | ✅ |
| FR-BACKGAMMON-04 | US-33 | TC-UNIT-BG-06 | ✅ |
| FR-BACKGAMMON-05 | US-33 | TC-UNIT-BG-07, TC-API-201 | ✅ |
| FR-BACKGAMMON-06 | US-33 | TC-UNIT-BG-09, TC-UNIT-BG-10, TC-API-201; Hard vs Medium measured 2026-10-03 (600 games, 60 %) | ✅ |
| FR-BACKGAMMON-07 | US-33 | TC-API-199, TC-API-200, TC-API-201 | ✅ |
| FR-BACKGAMMON-08 | US-33 | TC-API-202, TC-API-203, TC-API-106, TC-INT-003..005 | ✅ |
| FR-BACKGAMMON-09 | US-33 | TC-E2E-182, TC-E2E-183, TC-E2E-197, TC-E2E-040; manual (browser 2026-10-03: bar entry, the computer's turn moving) | ✅ |
| FR-CHECKERS-01 | US-35 | TC-UNIT-CK-01, TC-UNIT-CK-02 (perft), TC-API-215, TC-E2E-184 | ✅ |
| FR-CHECKERS-02 | US-35 | TC-UNIT-CK-03, TC-UNIT-CK-04, TC-API-217, TC-API-218, TC-E2E-185 | ✅ |
| FR-CHECKERS-03 | US-35 | TC-UNIT-CK-04, TC-UNIT-CK-06 | ✅ |
| FR-CHECKERS-04 | US-35 | TC-UNIT-CK-05, TC-UNIT-CK-06 | ✅ |
| FR-CHECKERS-05 | US-35 | TC-UNIT-CK-03, TC-UNIT-CK-07, TC-API-219 | ✅ |
| FR-CHECKERS-06 | US-35 | TC-UNIT-CK-08, TC-API-216; Hard vs Medium measured 2026-10-04 (English 3–1, flying kings 3–0 and a draw) | ✅ |
| FR-CHECKERS-07 | US-35 | TC-API-218, TC-API-220 | ✅ |
| FR-CHECKERS-08 | US-35 | TC-API-219, TC-API-220, TC-E2E-098, TC-E2E-099 | ✅ |
| FR-CHECKERS-09 | US-35 | TC-E2E-184, TC-E2E-185; manual (browser 2026-10-04: a jump against the computer) | ✅ |
| FR-MORRIS-01 | US-36 | TC-UNIT-MM-01, TC-UNIT-MM-02, TC-API-221 | ✅ |
| FR-MORRIS-02 | US-36 | TC-UNIT-MM-02, TC-UNIT-MM-04, TC-API-222 | ✅ |
| FR-MORRIS-03 | US-36 | TC-UNIT-MM-03, TC-API-223, TC-E2E-186 | ✅ |
| FR-MORRIS-04 | US-36 | TC-UNIT-MM-04 | ✅ |
| FR-MORRIS-05 | US-36 | TC-UNIT-MM-05, TC-API-224 | ✅ |
| FR-MORRIS-06 | US-36 | TC-UNIT-MM-06, TC-API-222; Hard vs Medium measured 2026-10-04 (4–0) | ✅ |
| FR-MORRIS-07 | US-36 | TC-API-221, TC-API-223, TC-API-225 | ✅ |
| FR-MORRIS-08 | US-36 | TC-API-224, TC-API-225, TC-E2E-098, TC-E2E-099 | ✅ |
| FR-MORRIS-09 | US-36 | TC-E2E-186, TC-E2E-187; manual (browser 2026-10-04: a mill against the computer) | ✅ |
| FR-WOT-SNAP-01 | — | TC-API-195; the clean-up tried on test data (A A A B B A C → A B A C, a second run removes nothing) | ✅ |
| FR-GAMES-CH-01 | US-34 | TC-API-204, TC-API-208, TC-E2E-180 | ✅ |
| FR-GAMES-CH-02 | US-34 | TC-API-205, TC-E2E-180 | ✅ |
| FR-GAMES-CH-03 | US-34 | TC-API-204 (against the Discord stub; the real channel and a DM: review on the live server) | ✅ / 🔍 |
| FR-GAMES-CH-04 | US-34 | TC-API-206, TC-E2E-180 | ✅ |
| FR-GAMES-CH-05 | US-34 | TC-API-205, TC-API-207 | ✅ |
| FR-GAMES-CH-06 | US-34, REG-GDPR-15, REG-GDPR-17 | TC-API-209, TC-API-210; the bot's command itself: review on the live server | ✅ / 🔍 |
| FR-GAMES-CH-07 | US-34 | TC-E2E-180 | ✅ |
| FR-NONOGRAM-01 | US-37 | TC-UNIT-NG-01, TC-UNIT-NG-03, TC-API-226 | ✅ |
| FR-NONOGRAM-02 | US-37 | TC-UNIT-NG-02, TC-UNIT-NG-03 | ✅ |
| FR-NONOGRAM-03 | US-37 | TC-UNIT-NG-04, TC-API-226 | ✅ |
| FR-NONOGRAM-04 | US-37 | TC-UNIT-NG-04, TC-API-227 | ✅ |
| FR-NONOGRAM-05 | US-37 | TC-UNIT-NG-05, TC-API-228, TC-API-229 | ✅ |
| FR-NONOGRAM-06 | US-37 | TC-API-229, TC-API-230, TC-E2E-098, TC-E2E-079 | ✅ |
| FR-NONOGRAM-07 | US-37 | TC-E2E-189; manual (browser 2026-10-05: the puzzle of the day solved logged in, the list and the streak) | ✅ |
| FR-NONOGRAM-08 | US-37 | TC-E2E-189, TC-E2E-190, TC-E2E-196 | ✅ |
| FR-PUZZLE-01 | US-38 | TC-API-233, TC-API-236, TC-E2E-200, TC-E2E-204 | ✅ |
| FR-PUZZLE-02 | US-38 | TC-E2E-199, TC-E2E-200, TC-E2E-201, TC-E2E-203, TC-E2E-191, TC-E2E-192 | ✅ |
| FR-PUZZLE-03 | US-38 | TC-API-320, TC-API-322 | ✅ |
| FR-SUDOKU-01 | US-38 | TC-UNIT-SD-01, TC-UNIT-SD-02 | ✅ |
| FR-SUDOKU-02 | US-38 | TC-UNIT-SD-02 | ✅ |
| FR-SUDOKU-03 | US-38 | TC-UNIT-SD-03, TC-API-231 | ✅ |
| FR-SUDOKU-04 | US-38 | TC-UNIT-SD-03, TC-API-232 | ✅ |
| FR-SUDOKU-05 | US-38 | TC-API-233, TC-E2E-200 | ✅ |
| FR-SUDOKU-06 | US-38 | TC-API-234, TC-E2E-165, TC-E2E-079 | ✅ |
| FR-SUDOKU-07 | US-38 | TC-E2E-199 | ✅ |
| FR-SUDOKU-08 | US-38 | TC-E2E-200 | ✅ |
| FR-OWLDOKU-01 | US-38 | TC-UNIT-OD-01 | ✅ |
| FR-OWLDOKU-02 | US-38 | TC-UNIT-OD-01, TC-UNIT-OD-02 | ✅ |
| FR-OWLDOKU-03 | US-38 | TC-UNIT-OD-03, TC-API-235 | ✅ |
| FR-OWLDOKU-04 | US-38 | TC-UNIT-OD-03, TC-API-235, TC-E2E-201 | ✅ |
| FR-OWLDOKU-05 | US-38 | TC-API-236, TC-API-237, TC-E2E-204 | ✅ |
| FR-OWLDOKU-06 | US-38 | TC-API-238, TC-E2E-165, TC-E2E-079 | ✅ |
| FR-BLOCKDOKU-01 | US-52 | TC-UNIT-BD-01, TC-API-295 | ✅ |
| FR-BLOCKDOKU-02 | US-52 | TC-UNIT-BD-02, TC-UNIT-BD-03, TC-API-295 | ✅ |
| FR-BLOCKDOKU-03 | US-52 | TC-UNIT-BD-04, TC-UNIT-BD-05, TC-API-295 | ✅ |
| FR-BLOCKDOKU-04 | US-52 | TC-UNIT-BD-05, TC-API-296, TC-API-321 | ✅ |
| FR-BLOCKDOKU-05 | US-52 | TC-API-295, TC-E2E-237 | ✅ |
| FR-BLOCKDOKU-06 | US-52 | TC-API-297, TC-E2E-237 | ✅ |
| FR-BLOCKDOKU-07 | US-52 | TC-API-298 | ✅ |
| FR-BLOCKDOKU-08 | US-52 | TC-API-299, TC-API-300 | ✅ |
| FR-BLOCKDOKU-09 | US-52 | TC-E2E-237, TC-E2E-238, TC-E2E-239; the Swedish pages: manual (screenshots, 2026-10-14) | ✅ |
| FR-BLOCKDOKU-10 | US-52 | TC-API-321 | ✅ |
| FR-BERSERK-01 | US-53 | TC-E2E-245 | ✅ |
| FR-BERSERK-02 | US-53 | TC-E2E-246, TC-E2E-247 | ✅ |
| FR-BERSERK-03 | US-53 | the member's own viking and the enemies' looks: manual (screenshots, 2026-10-17) | ✅ |
| FR-BERSERK-04 | US-53 | TC-E2E-246, TC-E2E-249 | ✅ |
| FR-BERSERK-05 | US-53 | TC-E2E-246 | ✅ |
| FR-BERSERK-06 | US-53 | TC-E2E-246 (bread); the roast and the axe: TC-E2E-247 (the pilot breaks every barrel) | ✅ |
| FR-BERSERK-07 | US-53 | TC-E2E-248 (keyboard, a game controller, touch, pause) | ✅ |
| FR-BERSERK-08 | US-53 | TC-API-304, TC-API-305 | ✅ |
| FR-BERSERK-09 | US-53 | TC-API-304, TC-API-305 | ✅ |
| FR-BERSERK-10 | US-53 | TC-API-306, TC-E2E-249 | ✅ |
| FR-BERSERK-11 | US-53 | TC-E2E-246, TC-E2E-247 (the rules run without graphics), TC-E2E-249 (WebGL or the message) | ✅ |
| FR-BERSERK-12 | US-53 | — (step 2, proposed) | ⏳ |
| FR-BERSERK-13 | US-53 | — (step 3, not started) | ⏳ |
| FR-BERSERK-14 | US-53 | TC-API-307 | ✅ |
| FR-BERSERK-15 | US-53 | TC-E2E-251 (change, move, Esc, refused keys, an empty action, Save, reload, the hub badge, Default keys), TC-E2E-252 (a guest's keys move to the account; another device gets the same), TC-API-308 | ✅ |
| FR-OWLDOKU-07 | US-38 | TC-E2E-201, TC-E2E-202 | ✅ |
| FR-OWLDOKU-08 | US-38 | TC-E2E-203 | ✅ |
| FR-REPORT-01 | US-39 | TC-API-251, TC-E2E-210 | ✅ |
| FR-REPORT-02 | US-39 | TC-API-250, TC-E2E-210 (against the Discord stub); manual on the live server 2026-10-07: a report from hakoo.eu reached #reports-from-hakoo-eu (the owner) | ✅ |
| FR-REPORT-03 | US-39 | TC-API-252 | ✅ |
| FR-REPORT-04 | US-39 | TC-E2E-211 | ✅ |
| FR-RUNBORG-01 | US-41 | TC-UNIT-RB-01, TC-UNIT-RB-03, TC-E2E-211 | ✅ |
| FR-RUNBORG-02 | US-41 | TC-UNIT-RB-02, TC-UNIT-RB-04 | ✅ |
| FR-RUNBORG-03 | US-41 | TC-UNIT-RB-03 (the order in Runborg::winner); both at once: review | ✅ / 🔍 |
| FR-RUNBORG-04 | US-41 | TC-UNIT-RB-05, TC-UNIT-RB-06; `qa/tools/runborg-balance.php` | ✅ |
| FR-RUNBORG-05 | US-41 | TC-UNIT-RB-07, TC-API-253, TC-API-254, TC-E2E-211 | ✅ |
| FR-RUNBORG-06 | US-41 | TC-API-255, TC-E2E-212 | ✅ |
| FR-RUNBORG-07 | US-41 | TC-API-255 | ✅ |
| FR-RUNBORG-08 | US-41 | TC-API-256, TC-E2E-212 | ✅ |
| FR-RUNBORG-09 | US-41, REG-GDPR-15, REG-GDPR-17 | TC-API-257, TC-API-264 (the second player's export) | ✅ |
| FR-RUNBORG-10 | US-43 | TC-API-262, TC-E2E-215 | ✅ |
| FR-RUNBORG-11 | US-43 | TC-API-262, TC-E2E-215 | ✅ |
| FR-RUNBORG-12 | US-43 | TC-API-263, TC-E2E-215 | ✅ |
| FR-RUNBORG-13 | US-43 | TC-API-264 (against the Discord stub; the real post: check on live) | ✅ / 🔍 |
| FR-RUNBORG-14 | US-43 | TC-API-265, TC-E2E-215 | ✅ |
| FR-RUNBORG-15 | US-41, US-43 | TC-E2E-215; the look of a win and a loss against the computer: manual (2026-10-11) | ✅ |
| FR-RUNBORG-16 | US-41 | TC-UNIT-RB-08; balance: qa/tools/runborg-balance.php (2026-10-11, 200 games a pair) | ✅ |
| FR-RUNBORG-17 | US-41 | TC-UNIT-RB-09; the page's pick: manual script in the browser (2026-10-11) | ✅ |
| FR-SAVED-01 | US-26 | TC-API-130 | ✅ |
| FR-SAVED-02 | US-26 | TC-API-131 | ✅ |
| FR-SAVED-03 | US-26 | TC-API-132, TC-E2E-120, TC-E2E-121, TC-E2E-123 | ✅ |
| FR-SAVED-04 | US-26 | TC-API-133, TC-E2E-120, TC-E2E-121, TC-E2E-232 | ✅ |
| FR-SAVED-05 | US-26 | TC-API-134, TC-API-287 | ✅ |
| FR-SAVED-06 | — | TC-API-135, TC-INT-003 | ✅ |
| FR-SAVED-07 | US-26 | TC-API-136 | ✅ |
| FR-SAVED-08 | US-26 | TC-API-130, TC-API-286 | ✅ |
| FR-REMIND-01 | — | TC-API-288, TC-E2E-233 | ✅ |
| FR-REMIND-02 | — | TC-API-289, TC-API-292, TC-E2E-233 | ✅ |
| FR-REMIND-03 | — | TC-API-290 | ✅ |
| FR-REMIND-04 | — | TC-API-291 | ✅ |
| FR-REMIND-05 | — | TC-API-292, TC-E2E-233 | ✅ |
| FR-REMIND-06 | — | TC-API-293 | ✅ |
| FR-REMIND-07 | — | TC-E2E-234 | ✅ |
| FR-REMIND-08 | — | TC-API-303, TC-E2E-233 | ✅ |
| FR-GOMOKU-01 | US-15 | TC-GOM-001..009, TC-API-069 | ✅ |
| FR-GOMOKU-02 | US-15 | TC-GOM-005..007, TC-API-063, TC-API-064, TC-E2E-062 | ✅ |
| FR-GOMOKU-03 | US-15 | TC-GOM-010..017, TC-API-060..062, TC-API-075, TC-E2E-061 | ✅ |
| FR-GOMOKU-04 | US-16 | TC-API-066..068, TC-E2E-064 | ✅ |
| FR-GOMOKU-05 | US-16 | TC-API-072, TC-E2E-064 | ✅ |
| FR-GOMOKU-06 | US-15 | TC-API-070, TC-API-071, TC-E2E-064 | ✅ |
| FR-GOMOKU-07 | US-15 | TC-API-073 | ✅ |
| FR-GOMOKU-08 | US-03, US-15 | TC-API-065, TC-API-074, TC-E2E-060, TC-E2E-066 | ✅ |
| FR-GOMOKU-09 | — | review of `gomoku.php` (purge on new games) | 🔍 |
| FR-GOMOKU-10 | — | TC-E2E-065 | ✅ |
| FR-GOMOKU-11 | — | TC-E2E-063, TC-E2E-067 | ✅ |
| FR-GOMOKU-12 | US-17 | TC-API-080..082, TC-API-086 | ✅ |
| FR-GOMOKU-13 | US-17, US-18 | TC-API-083, TC-E2E-070, TC-E2E-071 | ✅ |
| FR-GOMOKU-14 | US-18 | TC-API-084, TC-E2E-072, TC-E2E-073 | ✅ |
| FR-GOMOKU-15 | US-18 | TC-API-085, TC-E2E-074, TC-E2E-075 | ✅ |
| FR-GOMOKU-16 | — | TC-INT-003..005, TC-API-087 | ✅ |
| FR-AUTH-01..04 | US-04 | TC-API-051 (session check), TC-MAN-001 | ✅ / — (Discord itself: live only) |
| FR-AUTH-05 | US-13 | TC-API-052, TC-API-053, TC-E2E-050 | ✅ (cycle 2) |
| FR-USER-01 | US-04 | TC-INT-001, TC-API-026 | ✅ |
| FR-USER-02 | US-04 | TC-INT-002 | ✅ |
| FR-USER-03 | US-07 | TC-INT-007 | ✅ |
| FR-USER-04 | US-07 | TC-INT-008 | ✅ |
| FR-USER-06 | US-30 | TC-E2E-231 | ✅ |
| FR-USER-05 | US-30 | TC-E2E-165, TC-E2E-166; the profile tests TC-E2E-072, 073, 096, 097 open one game's page each | ✅ |
| FR-GDPR-01 | US-06 | TC-INT-003, TC-API-003 | ✅ |
| FR-GDPR-02 | US-07 | TC-INT-004, TC-INT-011, TC-API-005 | ✅ |
| FR-GDPR-03 | US-07 | TC-INT-005 | ✅ |
| FR-GDPR-04 | US-07 | TC-INT-009 | ✅ |
| FR-GDPR-05 | US-07 | TC-INT-006 | ✅ |
| FR-GDPR-06 | US-12 | TC-INT-010, TC-MAN-007 | ✅ |
| FR-GDPR-07 | US-07 | TC-API-004, TC-API-006 | ✅ |
| FR-GDPR-08 | US-07 | TC-API-005 | ✅ |
| FR-ROSTER-01 | US-05 | TC-API-010 | ✅ |
| FR-ROSTER-02 | US-05 | TC-API-011..014 | ✅ |
| FR-ROSTER-03 | US-05 | TC-API-015 | ✅ |
| FR-ROSTER-04 | US-05 | TC-API-016..021 | ✅ |
| FR-ROSTER-05 | US-05 | TC-API-022..025 | ✅ |
| FR-ROSTER-06 | US-05 | — | — backlog |
| FR-BUILDS-01..04 | US-08 | designed ([EP/BVA § 5](test-design/equivalence-partitioning-bva.md)) | — backlog |
| FR-LOOT-01..04 | US-10 | designed ([EP/BVA § 6](test-design/equivalence-partitioning-bva.md), [decision table 4](test-design/decision-tables.md)) | — backlog |
| FR-I18N-01 | US-09 | TC-API-031 | ✅ |
| FR-I18N-02 | US-09 | TC-API-032 | ✅ |
| FR-I18N-03 | US-09 | TC-API-033 | ✅ |
| FR-I18N-04 | US-11 | TC-API-030 | ✅ |
| FR-I18N-05 | US-09 | TC-API-035 | ✅ |
| FR-I18N-06 | US-09 | TC-E2E-023, TC-E2E-024, TC-E2E-143 | ✅ |
| FR-I18N-07 | US-09 | TC-E2E-143 | ✅ |
| FR-I18N-08 | US-09 | TC-E2E-144 | ✅ |
| FR-I18N-09 | US-09 | TC-E2E-146, TC-E2E-148 | ✅ |
| FR-I18N-10 | US-09 | TC-E2E-149 | ✅ |
| FR-I18N-11 | US-09 | TC-API-161, TC-E2E-150..153 | ✅ |
| FR-I18N-12 | US-09 | TC-E2E-150, TC-E2E-151, TC-E2E-153, TC-E2E-154 | ✅ |
| FR-I18N-13 | US-09 | TC-API-284, TC-E2E-227 (all 29 pages in Swedish, no English left; Blockdoku and Path of Berserk added 2026-10-17), TC-E2E-228; every game started and played a little in Swedish: manual script (2026-10-10) | ✅ |
| FR-I18N-14 | — | TC-E2E-243 | ✅ |
| FR-SITE-01 | US-31, US-32 | TC-E2E-168, TC-E2E-172 (screenshots of the themes, checked by eye 2026-10-02), TC-E2E-174 (the banner follows), TC-E2E-175 (HAKOO theme) | ✅ |
| FR-SITE-02 | US-31 | TC-E2E-169, TC-E2E-174, TC-E2E-180, TC-E2E-181, TC-API-172, TC-API-173, TC-API-176, TC-API-197 | ✅ |
| FR-SITE-03 | US-31 | TC-E2E-168, TC-E2E-170, TC-E2E-171, TC-E2E-173 (DEF-014), TC-API-170, TC-API-171, TC-API-174 | ✅ |
| FR-SITE-04 | US-31 | TC-E2E-170 | ✅ |
| FR-SITE-05 | US-32 | TC-E2E-174, TC-API-175, TC-API-176 | ✅ |
| FR-SITE-06 | US-31 | TC-E2E-176, TC-API-189 | ✅ |
| FR-SITE-07 | US-31 | TC-E2E-177 (screenshots of the three effects checked by eye 2026-10-03), TC-E2E-178 (reduce motion: moves only with the mouse), TC-E2E-179 (glass boxes), TC-API-189 | ✅ |
| FR-TEXTS-01 | US-11 | TC-API-146, TC-API-147, TC-E2E-138 | ✅ |
| FR-TEXTS-02 | US-11 | TC-API-147, TC-E2E-139, TC-E2E-140 | ✅ |
| FR-TEXTS-03 | US-11 | TC-API-148, TC-API-149, TC-E2E-142 | ✅ |
| FR-TEXTS-04 | US-11 | TC-API-150, TC-API-151, TC-E2E-142 | ✅ |
| FR-TEXTS-05 | US-11 | TC-API-151, TC-API-153, TC-API-154, TC-E2E-142 | ✅ |
| FR-TEXTS-06 | US-11 | TC-API-152, TC-E2E-142 | ✅ |
| FR-TEXTS-07 | US-11 | TC-API-155..158, TC-E2E-141 | ✅ |
| FR-TEXTS-08 | US-11 | TC-API-159 | ✅ |
| FR-TEXTS-09 | US-11 | TC-API-160, TC-INT-003, TC-INT-004 | ✅ |
| FR-TEXTS-10 | US-11 | TC-E2E-145, TC-E2E-147 | ✅ |
| FR-PROFILE-01 | US-42 | TC-API-258, TC-E2E-213 | ✅ |
| FR-PROFILE-02 | US-42 | TC-API-258, TC-E2E-214 | ✅ |
| FR-PROFILE-03 | US-42 | TC-API-259, TC-E2E-214; the name kept at the real Discord login: manual on live | ✅ / 🔍 |
| FR-PROFILE-04 | US-42 | TC-API-259, TC-E2E-214 | ✅ |
| FR-PROFILE-05 | US-42 | TC-API-260, TC-E2E-214 | ✅ |
| FR-PROFILE-06 | US-42, REG-GDPR-15, REG-GDPR-17 | TC-API-261 | ✅ |
| FR-AVATAR-01 | US-44 | TC-API-266 | ✅ |
| FR-AVATAR-02 | US-44 | TC-API-268, TC-E2E-216 | ✅ |
| FR-AVATAR-03 | US-44 | TC-API-267, TC-API-279, TC-E2E-216 | ✅ |
| FR-AVATAR-04 | US-44 | TC-API-268 | ✅ |
| FR-AVATAR-05 | US-44 | TC-API-269, TC-E2E-216, TC-E2E-217; the look of each item: manual (browser 2026-10-09, all items drawn) | ✅ |
| FR-AVATAR-06 | US-44, REG-GDPR-15, REG-GDPR-17 | TC-API-270 | ✅ |
| FR-AVATAR-07 | US-45 | TC-API-267, TC-API-271, TC-E2E-220; the look of each item in 3D and flat: manual (browser 2026-10-09) | ✅ |
| FR-AVATAR-08 | US-45 | TC-API-272, TC-E2E-219, TC-E2E-220; turning by drag, the sliders' look: manual (browser 2026-10-09) | ✅ |
| FR-PLAYERS-01 | US-45 | TC-API-274, TC-E2E-218, TC-E2E-219 | ✅ |
| FR-PLAYERS-02 | US-45 | TC-API-275, TC-E2E-219 | ✅ |
| FR-PLAYERS-03 | US-45 | TC-API-275, TC-API-276, TC-E2E-219 | ✅ |
| FR-PLAYERS-04 | US-45, REG-GDPR-15 | TC-API-277, TC-E2E-220 | ✅ |
| FR-PLAYERS-05 | US-45 | manual (screenshots at 1920, 1200 and 390 pixels, 2026-10-09) | ✅ |
| FR-PLAYERS-06 | US-46, US-47, US-50 | TC-API-278, TC-E2E-219, TC-E2E-226 | ✅ |
| FR-PLAYERS-07 | US-47 | TC-API-278, TC-E2E-220 | ✅ |
| FR-PLAYERS-08 | US-48, US-50 | TC-API-280, TC-E2E-224; the layout at 1920 pixels: manual (2026-10-10) | ✅ |
| FR-PLAYERS-09 | US-48 | TC-E2E-223, TC-E2E-224, TC-E2E-229; the look at 1920 pixels: manual (2026-10-09) | ✅ |
| FR-PLAYERS-10 | — | TC-E2E-236; the look at 1366, 1536 (large text), 1897 (xl), 800 and 390 pixels: manual (screenshots, 2026-10-13) | ✅ |
| FR-AVATAR-09 | US-47 | TC-API-279, TC-E2E-220; the look of each garment: manual (browser 2026-10-09) | ✅ |
| FR-AVATAR-10 | US-47 | TC-API-279, TC-E2E-220; the skies: manual (browser 2026-10-09) | ✅ |
| FR-AVATAR-11 | US-47 | TC-E2E-216, TC-E2E-220; the layout: manual (screenshot at 1500 pixels, 2026-10-09) | ✅ |
| FR-AVATAR-12 | US-49 | TC-API-267, TC-API-281, TC-E2E-225, TC-E2E-230 (the squares and the line about them); the look of the squares and the palette: manual (screenshot, 2026-10-11) | ✅ |
| FR-AVATAR-13 | — | TC-API-267, TC-API-271, TC-API-294, TC-E2E-235; the shield clear of the body from every side and the shapes and patterns in 3D: manual (screenshots, 2026-10-13) | ✅ |
| FR-NAV-01 | US-46 | TC-E2E-221 | ✅ |
| FR-NAV-02 | US-46 | TC-E2E-222 | ✅ |
| FR-NAV-03 | US-46, US-47 | TC-E2E-221 (mobile); the look: manual (screenshot at 390 pixels, 2026-10-09) | ✅ |
| FR-RANK-01 | US-50, REG-GDPR-15 | TC-API-278, TC-API-282, TC-E2E-219, TC-E2E-226 | ✅ |
| FR-RANK-02 | US-50 | TC-API-282, TC-E2E-226 | ✅ |
| FR-RANK-03 | US-50 | TC-API-282 (migration_users_ranks.sql run by the test database) | ✅ |
| FR-RANK-04 | US-51 | TC-API-282, TC-API-283, TC-E2E-219 | ✅ |
| FR-ADMIN-01 | US-40 | TC-API-239, TC-E2E-206 | ✅ |
| FR-ADMIN-02 | US-40 | TC-API-240, TC-API-241, TC-E2E-206 | ✅ |
| FR-ADMIN-03 | US-40 | TC-API-241 (several roles), TC-E2E-206 | ✅ |
| FR-ADMIN-04 | US-40 | TC-API-242, TC-E2E-207 | ✅ |
| FR-ADMIN-05 | US-40 | TC-API-243, TC-E2E-207; a login counted: manual on live (Discord login cannot run in the test environment) | ✅ / 🔍 |
| FR-ADMIN-06 | US-40, REG-GDPR-15, REG-GDPR-17 | TC-API-244 | ✅ |
| FR-ADMIN-07 | US-40 | TC-API-245, TC-E2E-206 (against the Discord stub; the real role: check on the live server) | ✅ / 🔍 |
| FR-ADMIN-08 | US-40 | TC-API-246, TC-E2E-206 | ✅ |
| FR-ADMIN-09 | US-50 | TC-API-240, TC-API-282, TC-API-285, TC-E2E-206, TC-E2E-226 | ✅ |
| FR-ADMIN-10 | US-40 | TC-API-285 (the page, giving and taking Admin, refusals, the right-click menu's viewer); the buttons on admin.html: manual | ✅ / 🔍 |
| FR-ADMIN-09 | US-40 | TC-E2E-206, TC-E2E-207 | ✅ |
| FR-LINK-01 | US-13 | TC-API-042, TC-E2E-052 | ✅ (cycle 2) |
| FR-LINK-02 | US-14 | TC-API-040, TC-API-041, TC-API-042 | ✅ (cycle 2) |
| FR-LINK-03 | US-13 | TC-API-042, TC-E2E-052, TC-E2E-053 | ✅ (cycle 2) |
| FR-LINK-04 | US-13 | TC-E2E-050, TC-E2E-051, TC-E2E-055, TC-E2E-056 | ✅ (cycle 2) |
| FR-LINK-05 | US-13 | TC-API-043, TC-E2E-051, TC-E2E-052, TC-E2E-055 | ✅ (cycle 2) |
| FR-LINK-06 | US-14 | TC-API-045 | ✅ (cycle 2) |
| FR-LINK-07 | US-14 | TC-E2E-054 | ✅ (cycle 2) |

## Non-functional

| Requirement | Tests | Status |
|---|---|---|
| NFR-PERF-01 | — (load test planned) | — |
| NFR-PERF-02 | TC-API-036 | ✅ |
| NFR-PERF-03 | — (Lighthouse planned) | — |
| NFR-COMP-01 | all E2E × Chromium/Firefox/mobile | ✅ |
| NFR-COMP-02 | full suite on PHP 8.5 (87 PHPUnit + 168 E2E) | ✅ |
| NFR-USAB-01 | TC-E2E-010, TC-E2E-011, TC-MAN-005 | ✅ |
| NFR-USAB-02 | TC-MAN-003 | 🔍 (375 px checked) |
| NFR-USAB-03 | TC-E2E-011 | ✅ |
| NFR-REL-01 | TC-E2E-040 | ✅ |
| NFR-REL-02, -03 | TC-MAN-008 | — |
| NFR-SEC-01 | TC-API-001, TC-API-010 | ✅ |
| NFR-SEC-02 | TC-API-002, TC-API-050, TC-API-051 | ✅ |
| NFR-SEC-03 | TC-API-027 | ✅ |
| NFR-SEC-04 | TC-API-034, TC-API-035 | ✅ |
| NFR-SEC-05 | TC-E2E-030 | ✅ |
| NFR-SEC-06 | TC-MAN-001 | — |
| NFR-SEC-07 | review of `.gitignore` | 🔍 |
| NFR-SEC-08 | TC-E2E-031 | ✅ |
| NFR-SEC-09 | TC-API-040, TC-API-041 | ✅ (cycle 2) |
| NFR-SEC-10 | TC-API-044, TC-API-053 | ✅ (cycle 2) |
| NFR-MAINT-01 | computed-style comparison of all 17 pages, TC-E2E-040 | 🔍 ✅ (2026-09-26) |
| NFR-MAINT-02, -03 | review | 🔍 |
| NFR-PORT-01 | first `docker compose up` | ✅ |
| NFR-PORT-02 | TC-E2E-253 | ✅ |

## Regulatory

| Requirement | Tests | Status |
|---|---|---|
| REG-GDPR-7 | TC-E2E-007 | ✅ |
| REG-GDPR-13 | TC-MAN-006 | ❌ expected (DEF-005) |
| REG-GDPR-15, -20 | TC-API-003, TC-INT-003 | ✅ |
| REG-GDPR-17 | TC-INT-004, TC-API-005 | ✅ |
| REG-GDPR-25 | TC-E2E-020..024 | ✅ |
| REG-GDPR-32 | NFR-SEC-* tests | ✅ |
| REG-EPR-01 | TC-E2E-020..024 | ✅ |
| REG-GDPR-5, -6, -30, -33, -44, REG-EPR-02, REG-DSA-* | reviews | ❌ / ⚠️ see [regulatory requirements](requirements/regulatory-requirements.md) |
