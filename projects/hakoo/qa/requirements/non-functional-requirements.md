# Non-functional requirements

How well the system must work, grouped by the quality characteristics of **ISO/IEC 25010**
(the 2011 names that the ISTQB syllabus uses; the 2023 revision renames usability to
*interaction capability* and portability to *flexibility*, and adds *safety*).
Every requirement has a measurable acceptance value.

## Performance efficiency (NFR-PERF)

| ID | Requirement | Measure | Test |
|---|---|---|---|
| NFR-PERF-01 | `texts.php` answers quickly. | p95 < 300 ms with 20 concurrent users in the test environment | planned (k6 or JMeter) |
| NFR-PERF-02 | Unchanged texts are not sent again. | Repeat request with `If-None-Match` → **304** | TC-API-036 |
| NFR-PERF-03 | The start page loads fast on a phone. | Lighthouse (mobile): LCP < 2.5 s, total weight < 100 KB | planned |

## Compatibility (NFR-COMP)

| ID | Requirement | Measure | Test |
|---|---|---|---|
| NFR-COMP-01 | Works in current Chrome/Edge, Firefox and Safari, desktop and mobile. | All E2E tests pass in the Chromium, Firefox and mobile projects | Playwright projects |
| NFR-COMP-02 | The server code runs on PHP 8.5 (live; upgraded from 7.4 in September 2026). | Test suite passes on PHP 8.5 | Docker (`php:8.5-apache`) |

## Usability / accessibility (NFR-USAB)

| ID | Requirement | Measure | Test |
|---|---|---|---|
| NFR-USAB-01 | New pages (start page, HAKOO Games) meet WCAG 2.1 AA. | 0 axe-core violations of level A/AA; text contrast ≥ 4.5:1 | TC-E2E-010, TC-E2E-011, TC-MAN-005; axe planned |
| NFR-USAB-02 | Pages work from 320 px to 1920 px wide. | No horizontal scrolling at 320 px and 375 px | TC-MAN-003 |
| NFR-USAB-03 | Every choice on the start page can be reached and used with the keyboard. | Tab reaches all three options, and Enter/Space activates them | TC-E2E-011 |

## Reliability (NFR-REL)

| ID | Requirement | Measure | Test |
|---|---|---|---|
| NFR-REL-01 | No page throws JavaScript errors. | 0 uncaught errors on every page | TC-E2E-040 |
| NFR-REL-02 | If the database is unavailable, pages still show (English) text and Discord login still works. | Manual fault injection: stop the `db` container | TC-MAN-008 |
| NFR-REL-03 | A data deletion is all-or-nothing. | Deletion runs in one transaction and rolls back on error | code review + TC-MAN-008 |

## Security (NFR-SEC)

| ID | Requirement | Measure | Test |
|---|---|---|---|
| NFR-SEC-01 | Endpoints with personal data require a valid session. | No session → 401 | TC-API-001, TC-API-010 |
| NFR-SEC-02 | Sessions cannot be forged. Sessions are signed with `<SESSION_SECRET>`, which never leaves the server and differs from the public `<API_SECRET>`. | Cookie signed with another key, including `<API_SECRET>` → 401 | TC-API-002, TC-API-050 |
| NFR-SEC-03 | Discord ids are never exposed by the roster list. | No `discord_id` field in the response | TC-API-027 |
| NFR-SEC-04 | No SQL injection. | All values go through prepared statements or whitelists; injection attempt returns normal data and changes nothing | TC-API-034 |
| NFR-SEC-05 | Security headers on every page. | CSP (`default-src 'self'`, `object-src 'none'`), `nosniff`, `SAMEORIGIN`, strict referrer policy | TC-E2E-030 |
| NFR-SEC-06 | Session cookies are HttpOnly and Secure. | Inspect Set-Cookie | code review + TC-MAN-001 |
| NFR-SEC-07 | No secrets in Git. | `<CONFIG_FILE>`, `<DEPLOY_DIR>`, `<CREDENTIALS_FILE>` are ignored; secret scan finds nothing | `.gitignore` + review |
| NFR-SEC-08 | Library files cannot be executed directly. | `hakoo-users.inc.php` → 404 | TC-E2E-031 |
| NFR-SEC-09 | Account ownership is proven, never assumed from URL parameters. | Forged or foreign-token callback → refused, link unchanged | TC-API-040, TC-API-041 |
| NFR-SEC-10 | No open redirects: return addresses are limited to own pages. | Foreign URL as `return` is ignored | TC-API-044, TC-API-053 |

## Maintainability (NFR-MAINT)

| ID | Requirement | Measure | Test |
|---|---|---|---|
| NFR-MAINT-01 | Shared look lives in one place (`tokens.css` → `base.css` → page CSS). | Pages render identically after the split (computed styles) | done 2026-09-26 (manual comparison) + TC-E2E-040 |
| NFR-MAINT-02 | The personal-data register in code and in the docs match. | Every table in `HAKOO_PERSONAL_TABLES` is in `dockuments/personal-data-register.md`, and every table with `discord_id` has a foreign key | review + TC-INT-003 |
| NFR-MAINT-03 | Every automated test names its TC id and requirement ids. | Checked when updating the traceability matrix | review |

## Portability (NFR-PORT)

| ID | Requirement | Measure | Test |
|---|---|---|---|
| NFR-PORT-01 | Anyone in the project can start an identical test environment. | `docker compose up` works on Windows, macOS and Linux with no other setup | first run |
| NFR-PORT-02 | hakoo.eu can be installed as an app from the home screen (PWA, owner 2026-10-18) and then opens without the browser's bars. | Every page links `manifest.webmanifest` (display `standalone`, scope `/`) and has the Apple web-app tags; the 192 px, 512 px and maskable 512 px icons are served as PNG | TC-E2E-253 |
