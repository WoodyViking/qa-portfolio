# Manual test cases

For what cannot (yet) be automated: real external services, the live database, assistive
technology and exploratory testing. Each case has preconditions, steps and expected results so
that anyone in the project can run it and get the same result.

---

### TC-MAN-001: Discord login on the live site (system integration)
**Covers:** US-04, FR-AUTH-01..04, FR-USER-01, NFR-SEC-06 · **Priority:** high · **Environment:** live, after deploy

| # | Step | Expected result |
|---|---|---|
| 1 | Open `https://hakoo.eu/community.html` in a private window | Page loads, "Login with Discord" visible |
| 2 | Click "Login with Discord" and authorise | You return to `community.html`, your avatar and name are shown |
| 3 | Browser dev tools → Application → Cookies | `hakoo_auth` is **HttpOnly** and **Secure**, expires in 30 days |
| 4 | phpMyAdmin: `SELECT * FROM hakoo_users WHERE discord_id = <your id>` | One row, `last_login_at` ≈ now |
| 5 | Click "Logout" | You return to `community.html`, logged out, `hakoo_auth` is gone |

### TC-MAN-002: Migration dry run on a copy of the live database
**Covers:** R-03, P-01 · **Priority:** high · **Environment:** Docker + export of the live DB

| # | Step | Expected result |
|---|---|---|
| 1 | phpMyAdmin (live) → Export the database (SQL) → save **outside the repo** (it contains personal data) | File saved |
| 2 | Import it into the Docker DB (phpMyAdmin at http://localhost:8081) in a new database | Import succeeds |
| 3 | Run `database/migrations/migration_users.sql` against it | No errors; the last query shows **0 orphans** in every table |
| 4 | Delete the export file and drop the test copy | Personal data is not left behind |

### TC-MAN-003: Responsive layout of the new pages
**Covers:** NFR-USAB-02, NFR-COMP-01 · **Priority:** medium · **Environment:** Docker

| # | Step | Expected result |
|---|---|---|
| 1 | Open `http://localhost:8080/index.html?choose=1` at 320, 375, 768, 1280 and 1920 px | No horizontal scrolling, no overlapping text; the three choices stack under 820 px |
| 2 | Same for `hakoo-games.html` | Same |

### TC-MAN-004: Exploratory session: HAKOO Games privacy
**Charter:** *Explore HAKOO Games and the pages it links to, with the browser's network and storage panels
open, to discover any request to another domain or any stored data.*
**Covers:** US-03, REG-EPR-01 · **Time box:** 30 min · **Record:** notes, screenshots, defects found.

### TC-MAN-005: Screen reader on the start page
**Covers:** NFR-USAB-01, REG-WCAG-01 · **Priority:** medium · **Tool:** NVDA (Windows) or VoiceOver (macOS/iOS)

| # | Step | Expected result |
|---|---|---|
| 1 | Open the start page and move through it with Tab | Each choice is announced with its title and text |
| 2 | Reach "Remember my choice" and press Space | Announced as a toggle button, state "pressed"/"not pressed" |
| 3 | Check the colour contrast of all text (e.g. WebAIM Contrast Checker) | ≥ 4.5:1 for normal text |

### TC-MAN-006: Privacy notice review
**Covers:** REG-GDPR-13, REG-EPR-02 · **Priority:** high · **Type:** review (static testing)

| # | Check | Expected result |
|---|---|---|
| 1 | Does `privacy.html` name the controller and a contact? | Yes |
| 2 | Does it list every item in `dockuments/personal-data-register.md`? | Yes |
| 3 | Lawful basis and retention for each purpose? | Yes |
| 4 | Rights, how to use them (`mydata.php`), and the right to complain to IMY? | Yes |
| 5 | Browser storage (cookies, localStorage) explained? | Yes |

Currently expected to **fail** (DEF-005).

### TC-MAN-007: Backup restore drill
**Covers:** US-12, FR-GDPR-06 · **Priority:** medium · **Environment:** Docker

| # | Step | Expected result |
|---|---|---|
| 1 | Export the Docker DB (backup) | File saved |
| 2 | Log in as a test member (signed cookie, see `HakooTestCase::sessionCookie`) and delete the data via `mydata.php` | Data deleted |
| 3 | Import the backup | The deleted member is back |
| 4 | Run `hakoo_reapply_deletions(get_pdo())` (for example from a one-off PHP CLI command in the web container) | Returns 1; the member is gone again |

### TC-MAN-008: Database outage (fault injection)
**Covers:** NFR-REL-02, NFR-REL-03, decision table 3 rule R5 · **Priority:** medium · **Environment:** Docker

| # | Step | Expected result |
|---|---|---|
| 1 | `docker compose stop db` | DB stopped |
| 2 | Open `community.html`, `roster.html`, `hakoo-games.html` | Pages render; text is in English; no white page |
| 3 | Call `mydata.php?action=delete` with a valid session | Error response; **nothing** half-deleted when the DB comes back |
| 4 | `docker compose start db` | Everything works again |
