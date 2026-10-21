# Test plan: hakoo.eu

| | |
|---|---|
| Document | Test plan (structure based on ISO/IEC/IEEE 29119-3) |
| Version | 0.1 (draft), 2026-09-26 |
| Owner | Test manager: Woody_the_Viking (project owner) |
| Status | Draft, for review |

## 1. Context

hakoo.eu is a non-commercial community website for the [HAKOO] gaming clan (PHP 8.5, MySQL/MariaDB,
static HTML/CSS/JS, hosted at <HOSTING_PROVIDER> in Sweden). The current release in `src/` adds:

1. A start page where visitors choose **HAKOO Community** or **HAKOO Games**, with an opt-in "Remember my choice".
2. **HAKOO Games**: an anonymous section with no cookies, storage, scripts or third-party requests.
3. **hakoo_users**: one row per member, with cascading deletes and a complete GDPR export/delete.
4. Page texts from the database (i18n building blocks; not yet used by pages).
5. The split of every page into HTML/CSS/JS (shared `tokens.css`/`base.css`).

## 2. Scope

**In scope (test items):** everything in `src/`, the database migrations in `database/migrations/`,
the seed in `database/seed/`.

**Features to be tested:** see the functional requirements (FR-START, FR-GAMES, FR-AUTH, FR-USER, FR-GDPR,
FR-ROSTER, FR-BUILDS, FR-LOOT, FR-I18N), the non-functional requirements (NFR-*) and the regulatory
requirements (REG-*).

**Out of scope:** the external services themselves (Discord, Wargaming, Steam, Warframe APIs),
the presence bot (`presence-bot/`, Node.js), the old version (`oldversion/`), the hosting platform.

## 3. Test objectives (CTFL 1.1.1)

- Verify that the requirements are met, and validate the user stories with the owner and clan members.
- Find defects before release, especially in **personal-data handling** (highest risk).
- Provide evidence that regulatory requirements (GDPR/ePrivacy) are met.
- Build a regression suite that runs in Docker on every change.

## 4. Test approach

**Risk-based testing** (CTFL 5.2): the risk register ([risk-register.md](risk-register.md)) decides the
order and depth of testing. High risks get automated tests at more than one level.

| Test type (CTFL 2.2.2) | Approach |
|---|---|
| Functional | Black-box: equivalence partitioning, boundary value analysis, decision tables, state transitions ([test-design/](test-design/)) |
| Non-functional | Security (headers, auth, injection), compatibility (Chromium/Firefox/mobile), usability/accessibility (WCAG checks, keyboard), performance (planned: ETag, response times) |
| White-box | Planned: statement/branch coverage with PCOV in the PHP container |
| Change-related | **Confirmation testing** of every fixed defect; **regression testing** by running the full automated suite |
| Static testing | Review of these requirements and of code changes; static analysis `php -l` / `node --check` |
| Experience-based | Exploratory sessions with charters (see [manual test cases](test-cases/manual-test-cases.md)) and error guessing |

**Test levels:** see [README](README.md#test-levels-and-where-they-run).

**Test pyramid:** many fast integration/API tests, fewer browser tests, a few manual end-to-end checks on the live site.

## 5. Test environment

| Item | Test environment (Docker) | Live |
|---|---|---|
| Web | `php:8.5-apache` | <WEB_SERVER>, PHP 8.5 |
| Database | MariaDB 10.6, real migrations + fictional data | MySQL/MariaDB at <HOSTING_PROVIDER> |
| Config | `docker/web/config.test.php` (fake secrets) | `<CONFIG_FILE>` (never in Git) |
| Browsers | Playwright: Chromium, Firefox, Pixel 7 emulation | real devices |

Known differences (project risk R-08): the `wot_snapshots` base schema is reconstructed, and the live
`get_pdo()` settings are unknown. External APIs are not reachable with the fake keys.

## 6. Entry and exit criteria (CTFL 5.1.3)

**Entry criteria (Definition of Ready for testing):**
- The requirements for the change have IDs, and the user stories have acceptance criteria.
- The Docker environment starts (`docker compose up`), and all migrations run without error.
- The smoke test (TC-E2E-040) passes.

**Exit criteria (Definition of Done):**
- 100 % of the automated tests for high risks (level ≥ 12) pass.
- ≥ 95 % of all automated tests pass, and every failing test has a defect report.
- No open defects of severity **critical** or **major** without an owner decision.
- Each requirement in the traceability matrix has at least one executed test, or a documented reason why not.
- For releases touching personal data: TC-MAN-002 (migration on a copy of the live DB) is done.

## 7. Defect management (CTFL 5.5)

Defects are reported with [defects/defect-report-template.md](defects/defect-report-template.md) and listed in
[defects/defect-log.md](defects/defect-log.md).
Workflow: **New → Open → Fixed → Retest → Closed** (or Rejected / Deferred).
Severity: critical · major · minor · trivial. Priority: high · medium · low.

## 8. Roles

| Role | Who |
|---|---|
| Test manager / tester | Woody_the_Viking |
| Developer | Woody_the_Viking + Claude (AI pair) |
| Acceptance testers | HAKOO clan members (UAT of user stories) |
| Reviewers | Friends in the project (requirements and code review) |

## 9. Deliverables

Test plan, requirements (test basis), test design, automated test scripts, test data, test results
(`qa/test-results/`), defect reports, traceability matrix, test completion report per release.

## 10. Metrics (CTFL 5.3.1)

Tests planned / run / passed / failed / blocked · requirements coverage (from the traceability matrix) ·
defects found / fixed / open, by severity · defect detection per test level.

## 11. Schedule

| Step | When |
|---|---|
| Install Docker Desktop, first run of the environment | next session |
| Run automated suite, report defects | same session |
| Manual tests TC-MAN-001..007 | before deploy |
| Test completion report, go/no-go | before deploy |
