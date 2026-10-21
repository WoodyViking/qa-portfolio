# Test completion report: cycle 1

| | |
|---|---|
| Release / commit | `src/` at commits 034e24c + c251edd (start page, HAKOO Games, hakoo_users, GDPR self-service), plus testware fixes in this cycle |
| Test period | 2026-09-26 |
| Environment | Docker Desktop 29.8 on Windows 11: `php:7.4-apache` (PHP 7.4.33), MariaDB 10.6, Playwright 1.47.2 (Chromium, Firefox, Pixel 7 emulation) |
| Author | Claude (AI pair), for review by Woody_the_Viking |
| Recommendation | **Go with conditions** for deploying `src/` (see § 6) |

## 1. Summary

This was the first run of the Docker test environment and the automated suites. The database was built from
**all nine real migrations**, including `migration_users.sql` with its four `ON DELETE CASCADE` foreign keys,
without errors. After two testware defects were fixed (DEF-008, DEF-009), **all 164 automated test executions
passed**: 44 PHPUnit (integration + API) and 120 Playwright (40 tests × 3 browsers). No product defects were
found in this cycle. The deletion logic, the GDPR endpoint, input validation, SQL-injection protection, the
start-page consent logic and the "nothing stored" promise of HAKOO Games all behave as specified.

## 2. Scope and deviations

- **Executed:** all automated tests in `qa/automation/`, a PHP 7.4 syntax check of every PHP file in `src/`, and
  HTTP status and header checks.
- **Not executed:** manual tests TC-MAN-001..008. They need the live site, a copy of the live database, a screen
  reader or fault injection, and are planned before deploy.
- **Not covered yet:** FR-ROSTER-06, FR-BUILDS, FR-LOOT (designed, not automated), NFR-PERF-01/03 (load test,
  Lighthouse), white-box coverage.

## 3. Results

| Suite | Planned | Run | Passed | Failed | Blocked |
|---|---|---|---|---|---|
| Static: `php -l` (PHP 7.4) on `src/*.php` | 15 files | 15 | 15 | 0 | 0 |
| Database build: migrations + seed + test data | 12 scripts | 12 | 12 | 0 | 0 |
| Integration + API (PHPUnit 9.6) | 44 | 44 | 44 | 0 | 0 |
| E2E (Playwright) × Chromium/Firefox/mobile | 120 | 120 | 120 | 0 | 0 |
| Manual (TC-MAN) | 8 | 0 | – | – | – |

No test needed a retry (no flaky tests). Durations: PHPUnit 3 s, Playwright 39 s.
JUnit XML and the Playwright HTML report are in `qa/test-results/` (not committed).

**Requirements coverage** (see the [traceability matrix](../traceability-matrix.md)): every functional requirement
of the new features (FR-START, FR-GAMES, FR-USER, FR-GDPR, FR-I18N) and the existing roster validation has at
least one passed test. Open: FR-AUTH (live only), FR-ROSTER-06, FR-BUILDS, FR-LOOT.

## 4. Defects

| Severity | Found this cycle | Fixed | Open |
|---|---|---|---|
| critical | 0 | – | – |
| major | 1 (testware: DEF-008) | 1 | 0 |
| minor | 1 (testware: DEF-009) | 1 | 0 |

Both defects were in the **testware** (test environment and test configuration), not in the product.
They show why the first run of a new environment is itself a test.
Open product defects from earlier reviews: DEF-004 (new), DEF-005, DEF-006 (see the [defect log](../defects/defect-log.md)).

## 5. Exit criteria (test plan § 6)

| Criterion | Met? |
|---|---|
| All high-risk tests pass (R-01, R-03, R-04, R-07) | ✅ automated parts; R-03 still needs TC-MAN-002 on a copy of the live DB |
| ≥ 95 % of automated tests pass, failures have defect reports | ✅ 100 % |
| No open critical/major defects without an owner decision | ⚠️ DEF-005 (privacy notice) and DEF-006 (flag images) need a decision |
| Every requirement has an executed test or a documented reason | ⚠️ FR-BUILDS, FR-LOOT, FR-ROSTER-06 in the backlog |
| TC-MAN-002 done (personal-data changes) | ❌ not yet |

## 6. Residual risks and conditions for go

1. **Run TC-MAN-002** (migration on a copy of the live database) before running `migration_users.sql` live (R-03, P-01).
   The test environment has a reconstructed `wot_snapshots` schema and an assumed `get_pdo()`.
2. **Decide on DEF-005 / DEF-006:** the privacy notice does not describe the website yet, and pages linked from
   HAKOO Games load flag images from flagcdn.com.
3. After deploy, run **TC-MAN-001** (real Discord login) and the confirmation test for DEF-003 on live.

## 7. Lessons learned

- Assumptions about third-party images (`php:apache` enables `.htaccess`) must be tested. The first run found it
  within seconds.
- Test configuration is code too. The XML comment defect would have been caught by a lint step, so add
  `xmllint`/`php -l` for testware to the pipeline.
- Next cycle: automate `BuildsApiTest` and `CodesApiTest` (designed already), add axe-core accessibility checks,
  and run the suite on PHP 8.x (NFR-COMP-02).
