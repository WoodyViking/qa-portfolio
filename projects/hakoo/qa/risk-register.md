# Risk register

Risk-based testing (CTFL 5.2). **Risk level = likelihood × impact**, each on a scale of 1–5.
Level ≥ 12 = **high** (test at more than one level, automate), 6–11 = **medium**, ≤ 5 = **low**.

## Product risks (quality risks in the software)

| ID | Risk | L | I | Level | Mitigation (test response) | Tests |
|---|---|---|---|---|---|---|
| R-01 | Deleting a member leaves personal data behind (GDPR Art. 17 breach). | 3 | 5 | **15** | Integration tests per table + API test; register in code; FK cascade as safety net | TC-INT-003..011, TC-API-005 |
| R-02 | Deleting one member deletes someone else's data. | 2 | 5 | **10** | Negative tests with other members' data present | TC-INT-005 |
| R-03 | `migration_users.sql` fails on the live database (orphans, type mismatch, missing rights). | 3 | 4 | **12** | Run all migrations in Docker on every start; dry run on a copy of the live DB | Docker init, TC-MAN-002 |
| R-04 | After the FK change, a member without a user row cannot save anything. | 3 | 4 | **12** | `hakoo_ensure_user()` before every write; regression test | TC-API-026 |
| R-05 | Session forgery gives access to someone else's data. | 2 | 5 | **10** | HMAC check tests | TC-API-002 |
| R-06 | SQL injection through `texts.php` (language used as a column name). | 2 | 5 | **10** | Whitelist + injection test | TC-API-034 |
| R-07 | HAKOO Games or the start page stores data or leaks IP addresses without consent (ePrivacy). | 3 | 4 | **12** | Browser tests on storage, cookies and third-party requests | TC-E2E-020..024 |
| R-08 | The HTML/CSS/JS split changed how pages look. | 2 | 3 | 6 | Computed-style comparison (done), smoke test | TC-E2E-040 |
| R-09 | A page throws JavaScript errors after the refactoring. | 2 | 3 | 6 | Smoke test on every page | TC-E2E-040 |
| R-10 | Input validation lets bad data in (roster, builds, codes). | 3 | 2 | 6 | EP/BVA tests | TC-API-011..025 |
| R-11 | Security headers are lost when `.htaccess` is edited. | 2 | 3 | 6 | Header test | TC-E2E-030 |
| R-12 | A backup restore brings deleted members back. | 2 | 4 | 8 | Re-apply test | TC-INT-010 |
| R-13 | New pages cannot be used with the keyboard or a screen reader. | 3 | 2 | 6 | Keyboard/ARIA tests, manual screen-reader check | TC-E2E-010/011, TC-MAN-005 |
| R-14 | A member links someone else's game account and reads or deletes their data. **Occurred: DEF-010.** | 4 | 5 | **20** | Ownership proven with the Wargaming API; stub-based regression tests | TC-API-040..045 |
| R-15 | A secret that signs sessions leaks to the browser. **Occurred: DEF-011.** | 3 | 5 | **15** | Separate server-only `<SESSION_SECRET>`, fail closed; regression test | TC-API-050 |
| R-16 | Open redirect through the new `return` parameter. | 3 | 3 | 9 | Whitelist of own pages, server and client | TC-API-044, TC-API-053 |
| R-17 | Path of Berserk is played in the browser, so a run could report a score it did not earn and take a place in the month's or year's list (and, in step 2, a reward). | 4 | 2 | 8 | The server starts and times every run, counts the score itself from capped counts (no more enemies than the level has, no win under 60 s, one finish per run, token per run); rewards are only looks. Residual risk: a forged run can still reach the best believable score. Later: replay the recorded inputs on the server (the engine is deterministic with a seed). | TC-API-304..306 |

## Project risks (risks to the testing itself)

| ID | Risk | L | I | Level | Response |
|---|---|---|---|---|---|
| P-01 | The test environment differs from live (reconstructed `wot_snapshots`, unknown `get_pdo()` settings, Apache instead of <WEB_SERVER>). | 4 | 3 | **12** | Compare the schema with phpMyAdmin (structure only); TC-MAN-002 on a copy of the live DB |
| P-02 | ~~Docker is not installed yet, so nothing has run.~~ **Closed 2026-09-26**: Docker Desktop installed, cycle 1 run. | – | – | – | – |
| P-03 | External services (Discord, Wargaming, Steam) cannot be tested automatically. | 4 | 2 | 8 | Wargaming: stub `wgmock` since cycle 2. Discord/Steam: manual tests on the live site (TC-MAN-001) |
| P-04 | One person is tester and developer (independence, CTFL 1.5.3). | 4 | 2 | 8 | Reviews by friends; the AI pair wrote the code, the owner reviews and tests |
