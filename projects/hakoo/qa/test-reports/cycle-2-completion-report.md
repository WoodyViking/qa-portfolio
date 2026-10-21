# Test completion report: cycle 2

| | |
|---|---|
| Release / scope | US-13 (link the account a page needs via the profile), fixes for DEF-010 and DEF-011 |
| Test period | 2026-09-27 |
| Environment | Docker (PHP 7.4.33, MariaDB 10.6, Playwright 1.47.2) + new Wargaming stub `wgmock` |
| Author | Claude (AI pair), for review by Woody_the_Viking |
| Recommendation | **Deploy the security fixes as soon as possible**, following § 6 exactly |

## 1. Summary

While analysing US-13, two **critical** security defects were found in code that is live today: WoT account links
were not verified (DEF-010, reproduced in Docker), and the secret that signs login cookies is readable in the trackers'
public JavaScript (DEF-011, proven from the code). Both are fixed in `src/`, and US-13 is built on top of the fixes. A
Wargaming stub (test double) made the whole linking flow testable end to end. After one testware defect (DEF-012, tests
sharing data) was fixed, **all 198 automated test executions passed in two consecutive runs**.

## 2. Scope and deviations

- New: `LinkApiTest` (6), `SessionSecurityTest` (4), `account-linking.spec.js` (7 × 3 browsers), WoT tracker added to the smoke test.
- Regression: the complete cycle 1 suites were re-run.
- Not tested: real Discord and Wargaming (TC-MAN-001 on live after deploy); token expiry during use (transition L7,
  needs a real expired token; covered by code review).

## 3. Results

| Suite | Run | Passed | Failed |
|---|---|---|---|
| PHPUnit (integration + API) | 54 | 54 | 0 |
| Playwright × Chromium/Firefox/mobile | 144 | 144 | 0 |
| Playwright, second consecutive run (independence check) | 144 | 144 | 0 |
| `php -l` on all PHP files in `src/` (16, incl. the new `hakoo-session.inc.php`) | 16 | 16 | 0 |

Before DEF-012 was fixed, one run had 2 failures + 1 flaky and the next had 1 failure; both are explained in the defect log.

## 4. Defects

| ID | Severity | Where | Status |
|---|---|---|---|
| DEF-010 | critical | product (live) | Fixed in `src/`, confirmation tests pass |
| DEF-011 | critical | product (live) | Fixed in `src/`, confirmation tests pass; **needs config change on live** |
| DEF-012 | minor | testware | Closed |
| DEF-013 | major | product (live) | Open (details withheld until fixed) |
| DEF-004 | minor | product | Partly fixed: the `login.html` links are gone |

## 5. Exit criteria

| Criterion | Met? |
|---|---|
| All high-risk tests pass (R-14, R-15 new) | ✅ |
| 100 % of automated tests pass, no flaky tests | ✅ (two consecutive runs) |
| No open critical defects | ✅ in `src/`; ❌ on live until § 6 is done |
| New requirements traced to tests | ✅ FR-LINK-01..07, FR-AUTH-05, NFR-SEC-09/10 |

## 6. Deploy steps (order matters)

1. In the live `<CONFIG_FILE>` (on the server, never in Git), add two new random values, 64 hex characters each:
   `define('<SESSION_SECRET>', '...');` and `define('<DELETION_LOG_SECRET>', '...');`.
   They must differ from `<API_SECRET>`. Without `<SESSION_SECRET>`, login and every member page answer
   "server_misconfigured" (fail closed).
2. Recommended: rotate `<API_SECRET>` in the same upload.
3. Run TC-MAN-002 (migration on a copy of the live DB), then `database/migrations/migration_users.sql` on live.
4. Upload `src/` (the new PHP files, `js/shared/require-account.js`, the changed JS, `profile.html`/CSS, and the trackers).
5. Retest on live: TC-MAN-001 (Discord login → profile → Link WoT with a real account → back to the tracker), and
   try the DEF-010 URL with a test account: it must end on `profile.html?link_error=wot`.
6. Everyone is logged out once (the new session secret); they log in again as usual.

## 7. Lessons learned

- Reviewing trust boundaries (what comes from the URL? what reaches the browser?) found two critical defects that no
  functional test had covered. Add "error guessing: trust boundaries" to every review.
- Test data management matters when tests run in parallel: shared fixtures are read-only; tests that write create their own data.
- A stub for an external API turns "manual only" into automated tests and makes the full flow demonstrable.
