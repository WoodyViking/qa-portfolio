# Defect log

Real defects found in hakoo.eu so far. Found in reviews, the browser comparisons during the
HTML/CSS/JS split, and exploratory testing on 2026-09-26.

| ID | Title | Severity | Priority | Status | Regression test |
|---|---|---|---|---|---|
| DEF-001 | "Forget my choice" shown although nothing is saved | minor | medium | **Closed** | TC-E2E-009 |
| DEF-002 | Legal pages write `wot_lang` to localStorage on page load | major | high | **Closed** | TC-E2E-023 |
| DEF-003 | GDPR export/delete misses three tables | critical | high | **Fixed**, retest on live after deploy | TC-INT-004, TC-API-003, TC-API-005 |
| DEF-004 | Broken internal links | minor | low | **Open**: `login.html` links removed (US-13); two links left | TC-E2E-040 (partly) |
| DEF-005 | Privacy notice describes only the bot | major | high | **Open** | TC-MAN-006 |
| DEF-006 | Pages linked from HAKOO Games load flag images from flagcdn.com | major | medium | **Open** | TC-E2E-020 (extend to linked pages) |
| DEF-007 | Hard-coded Swedish UI text in the WoT/WoWs trackers and install page | minor | low | **Closed** | review |
| DEF-008 | Test web server returned 500 for every page (live `.htaccess` loaded) | major | high | **Closed** (testware) | every E2E test |
| DEF-009 | PHPUnit could not load its configuration (`--` inside an XML comment) | minor | high | **Closed** (testware) | tests-php run |
| DEF-010 | WoT account link is not verified: any member can take over another player's WoT account link | **critical** | high | **Fixed** in `src/`; live until deploy | TC-API-040, TC-API-041 |
| DEF-011 | The session-signing secret is public in the trackers' JavaScript: anyone can forge a login | **critical** | high | **Fixed** in `src/`; live until deploy + secret rotation | TC-API-050 |
| DEF-012 | E2E tests depended on each other through shared test data (failed in parallel runs) | minor | high | **Closed** (testware) | 2 consecutive full runs green |
| DEF-013 | *Open security finding: details withheld until it is fixed (responsible disclosure)* | major | medium | **Open** | — |
| DEF-014 | Closing the Appearance dialog without Save can leave the tried theme on the page | minor | medium | **Fixed** in `src/`; live until deploy | TC-E2E-173 |
| DEF-015 | GDPR export ("Download my data") leaves out every game and settings table: rewards, looks, games, saved games, move speed, dice settings, appearance, translations (collected by `hakoo_collect_user()` but not written by `mydata.php`) | critical | high | **Fixed** in `src/` 2026-10-02; live until deploy | TC-API-188 |
| DEF-016 | Backgammon on a phone: the dice cup "does nothing" right after the computer's turn although the page says "Your turn: roll the dice" | major | high | **Fixed** in `src/` 2026-10-07; live until deploy | TC-E2E-197 |
| DEF-017 | Nonogram on a phone: two fingers neither zoom nor move the board, so a 20 × 20 puzzle cannot be seen whole or moved | major | high | **Fixed** in `src/` 2026-10-07; live until deploy | TC-E2E-196 |
| DEF-018 | The rules in short on the Ludo and Stratego start pages say less than the game does: "a 6 brings a piece out" (the default is a 6 or a 1), "the Spy beats the Marshal" (only when the Spy attacks) | minor | medium | **Fixed** in `src/` 2026-10-07; live until deploy | TC-E2E-198 |
| DEF-019 | A fresh Docker test database fails to start: the rewards backfill migration runs before the board-game tables it reads | major | high | **Closed** (testware) 2026-10-14 | db container start (`10-migrations.sh`) |

---

### DEF-001: "Forget my choice" shown although nothing is saved

| Field | Value |
|---|---|
| Date / reporter | 2026-09-26 / Claude (during development testing) |
| Test object | `src/index.html`, `src/css/index.css` |
| Environment | local static server, Chromium (in-app browser) |
| Found by | exploratory check after building the page (screenshot) |
| Test level | system |
| Severity / priority | minor / medium |
| Status | Closed |
| Requirement | FR-START-07 |

**Steps:** clear localStorage → open `index.html`.
**Expected:** no "Forget my choice" link. **Actual:** the link was visible.
**Root cause:** `.start-note{display:flex}` overrode the `hidden` attribute.
**Fix:** `.start-note[hidden]{display:none}`. Confirmed in the browser; regression test TC-E2E-009.

### DEF-002: Legal pages write `wot_lang` to localStorage on page load

| Field | Value |
|---|---|
| Date / reporter | 2026-09-26 / Claude (code review) |
| Test object | `src/js/shared/lang-flags.js` (used by privacy, terms, install) |
| Test level | system |
| Severity / priority | major / high: breaks the "nothing stored" promise of HAKOO Games, which links to these pages (REG-EPR-01) |
| Status | Closed |
| Requirement | FR-I18N-06, REG-EPR-01 |

**Steps:** clear localStorage → open `privacy.html`.
**Expected:** nothing stored. **Actual:** `wot_lang = "en"` was stored.
**Root cause:** the script called `setLang()` (which saves) on load instead of only marking the active flag.
**Fix:** split into `markLang()` (on load) and `setLang()` (on click). Confirmed in the browser; regression test TC-E2E-023.

### DEF-003: GDPR export/delete misses three tables

| Field | Value |
|---|---|
| Date / reporter | 2026-09-25 / Claude (regulatory review) |
| Test object | `mydata.php` (live version) |
| Test level | system / acceptance (regulatory) |
| Severity / priority | critical / high: personal data is kept after a deletion request (GDPR Art. 15, 17) |
| Status | Fixed in `src/` (commit c251edd); retest on live after deploy |
| Requirement | FR-GDPR-01, FR-GDPR-02, REG-GDPR-15, REG-GDPR-17 |

**Steps:** as a member with roster entries, builds and a code report → "Delete all my data".
**Expected:** everything deleted. **Actual:** `hakoo_ign`, `hakoo_builds` and `hakoo_code_reports` rows remain;
the export does not include them either.
**Root cause:** the tables were added after `mydata.php` was written, and nothing linked them to it.
**Fix:** `hakoo_users` with foreign keys (ON DELETE CASCADE) and a single register in `hakoo-users.inc.php`.

### DEF-004: Broken internal links

| Field | Value |
|---|---|
| Date / reporter | 2026-09-26 / Claude (static link check of `src/`) |
| Severity / priority | minor / low |
| Status | New: the files may exist on the live server but not in the repo; verify there first |

| Page | Link | Problem |
|---|---|---|
| `community.html` (2×) | `warframe-tracker.html` | file not in the repo |
| `games/warframe-counter.html` | `../html/roster.html` | old folder structure; should be `../roster.html` |
| `WoWs-tracker.html` | `login.html` | file only in `oldversion/old/` |

### DEF-005: Privacy notice describes only the bot
Severity major, priority high, status Open. `privacy.html` covers the Discord bot but not the website's
processing (logins, linked accounts, stats, builds, presence, CDNs, browser storage). Requirement REG-GDPR-13.
Test: TC-MAN-006. Details: `dockuments/eu-regulations/hakoo-compliance-checklist.md`.

### DEF-006: Pages linked from HAKOO Games load flag images from flagcdn.com
Severity major, priority medium, status Open. `privacy.html`, `terms.html` and `install.html` (all linked from
HAKOO Games) load flag images from `flagcdn.com`, which sends the visitor's IP address to a third party
(REG-GDPR-25, REG-GDPR-44). Fix: self-host the five flag images. Confirmation test: extend TC-E2E-020 to these pages.

### DEF-007: Hard-coded Swedish UI text
Severity minor, status Closed (2026-09-26). Default texts in `wot-tracker.html`/`WoWs-tracker.html`, toasts in
their JS, and all of `install.html` were Swedish regardless of the chosen language. Fixed by using the English
texts from each page's own translation table.

### DEF-008: Test web server returned 500 for every page (testware)

| Field | Value |
|---|---|
| Date / reporter | 2026-09-26 / Claude (first start of the Docker environment, test cycle 1) |
| Test object | `docker/web/start.sh` (test environment) |
| Environment | Docker, `php:7.4-apache` |
| Severity / priority | major / high: blocked all system tests |
| Status | Closed |

**Steps:** `docker compose up -d --build` → `curl http://localhost:8080/index.html`.
**Expected:** 200. **Actual:** 500; Apache log: *Invalid command 'PassengerAppRoot'*.
**Root cause:** wrong assumption that the image ignores `.htaccess` (`AllowOverride None`). The `php:apache`
image enables it, so Apache read the live `.htaccess` with its Passenger block in addition to the cleaned copy.
**Fix:** the generated config now sets `AllowOverride None` for `/var/www/html`. Confirmed: pages 200, headers present.

### DEF-009: PHPUnit could not load its configuration (testware)

| Field | Value |
|---|---|
| Date / reporter | 2026-09-26 / Claude (first run of tests-php, test cycle 1) |
| Test object | `qa/automation/integration/phpunit.xml` |
| Severity / priority | minor / high: blocked the integration suite |
| Status | Closed |

**Actual:** *Could not load phpunit.xml: Double hyphen within comment*. The comment contained `--profile`,
and XML does not allow `--` inside comments. **Fix:** reworded the comment. Confirmed: 44/44 tests ran.

### DEF-010: WoT account link is not verified (account takeover of links)

| Field | Value |
|---|---|
| Date / reporter | 2026-09-27 / Claude (code review while analysing a new user story, reproduced in Docker) |
| Test object | `src/link.php`, Wargaming callback (`?provider=wot&status=ok&account_id=...`); same code is **live** |
| Environment | Docker test environment, test users Alice and Bob |
| Found by | review + reproduction (experience-based: error guessing on trust boundaries) |
| Test level | system |
| Severity / priority | **critical** / high: confidentiality and integrity of other players' data (GDPR Art. 32) |
| Status | Fixed in `src/` 2026-09-27 (confirmation tests TC-API-040/041 pass); retest on live after deploy |
| Requirement | NFR-SEC-09, REG-GDPR-32, FR-LINK-02 |

**Steps to reproduce**
1. Log in as Alice (`qa/tools/login-cookie.php 100000000000000001 Alice`).
2. Open `http://localhost:8080/link.php?provider=wot&status=ok&account_id=500000012&nickname=Hijacked`
   (500000012 is Bob's WoT account). Wargaming is never contacted.
3. `mydata.php?action=export` as Alice.

**Expected:** the link is refused, because Alice has not proved that she owns account 500000012.
**Actual:** Bob's link is moved to Alice (`ON DUPLICATE KEY UPDATE discord_id = ...`). Alice's export contains Bob's
WoT stat snapshots, and "Delete all my data" as Alice would delete Bob's snapshots and clan access.
**Root cause:** the callback trusts `account_id` from the URL. The Wargaming redirect also carries an `access_token`,
but it is never checked with the Wargaming API.
**Fix (proposed):** verify server-side that the `access_token` belongs to `account_id` (Wargaming `account/info`
with the token returns the private block only for the token's owner), and refuse otherwise.
**Fix (2026-09-27):** `link.php` now calls `wot/account/info` with the token and stores the link only if the private
block comes back for that account (`wg_verified_nickname()`); the nickname is taken from the API as well.
Verified against the Wargaming stub `qa/automation/mocks/wg-api.php`: the forged callback and a valid token for another
account are both refused, a real link goes through.

### DEF-011: The session-signing secret is public in the trackers' JavaScript

| Field | Value |
|---|---|
| Date / reporter | 2026-09-27 / Claude (code review while building US-13) |
| Test object | The trackers' JavaScript, all endpoints that verify `hakoo_auth` |
| Severity / priority | **critical** / high: anyone can log in as any member or admin (read, change and delete their data) |
| Status | Fixed in `src/` 2026-09-27; **live is affected until deploy and secret rotation** |
| Requirement | NFR-SEC-02, REG-GDPR-32 |

**Analysis (static testing):** a shared secret, `<API_SECRET>`, was readable in the trackers' JavaScript. The same
`<API_SECRET>` signed the `hakoo_auth` session cookie (HMAC-SHA256). With the value from the JavaScript file, anyone can
create a valid session cookie for any Discord id.
**Not reproduced on live** (on purpose: no attacks on production), and no separate "before" run was made: the old
code signed sessions with `<API_SECRET>` by design, so the defect follows directly from the code. After the fix, a cookie
signed with `<API_SECRET>` is rejected with 401 (TC-API-050), and a cookie signed with `<SESSION_SECRET>` is accepted (TC-API-051).
**Fix:** a separate `<SESSION_SECRET>` (server-only, ≥ 32 characters, must differ from `<API_SECRET>`) signs sessions;
`hakoo-session.inc.php` refuses to run without it (fail closed). The deletion log uses `<DELETION_LOG_SECRET>`.
**Required on live:** add `<SESSION_SECRET>` (and `<DELETION_LOG_SECRET>`) to `<CONFIG_FILE>` **before** uploading the new PHP
files, then deploy. Existing sessions end (everyone logs in again). Rotating `<API_SECRET>` is recommended too.

### DEF-012: E2E tests depended on each other (testware)

| Field | Value |
|---|---|
| Date / reporter | 2026-09-27 / Claude (test cycle 2) |
| Severity / priority | minor / high: false failures (TC-E2E-051 failed in 2 of 3 browsers, then in all) |
| Status | Closed |

**Root cause:** the browsers run in parallel against one database, and the E2E suite does not reset it. TC-E2E-052
linked an account to Carol, so TC-E2E-051 ("Carol has nothing linked") failed depending on order, and kept failing
in later runs. **Fix:** tests that change data, or need a member without data, use `freshUserId()`; shared users are
read-only. Confirmed by two consecutive full runs: 144/144 both times.

### DEF-013: Open security finding (details withheld)

| Field | Value |
|---|---|
| Date / reporter | 2026-09-27 / Claude (follow-up of DEF-011) |
| Severity / priority | major / medium |
| Status | Open |

The details are left out of this public copy until the finding is fixed on the live site (responsible disclosure).
The full report, analysis and proposed fix are kept in the project's private repository.

### DEF-014: Closing the Appearance dialog without Save can leave the tried theme on the page

| Field | Value |
|---|---|
| Date / reporter | 2026-10-02 / Claude (checking the live site after the upload of e14babd) |
| Test object | `src/js/shared/appearance.js` (`openDialog()`) |
| Environment | hakoo.eu, the in-app browser while its window was in the background |
| Found by | verification after deployment (a scripted check of the dialog) |
| Test level | system |
| Severity / priority | minor / medium (nothing is stored; a reload shows the saved look again) |
| Status | Fixed in `src/`, live until the next upload |
| Requirement | FR-SITE-03 ("leaving without Save goes back to the saved look") |

**Steps:** open ⚙ → 🎨 Appearance → pick "Light" → press Close.
**Expected:** the page is dark again (the saved look). **Actual:** it stayed light until a reload.
**Root cause:** going back to the saved look ran only on the dialog's `close` event, which is queued and in a
window in the background did not come at all.
**Fix:** Close and Escape (`cancel`) go back at once; `close` is kept as a fallback. Regression test TC-E2E-173
checks the colours in the same moment as the click, so it does not depend on the event either.

### DEF-016: Backgammon on a phone: the dice cup "does nothing" after the computer's turn

| Field | Value |
|---|---|
| Date / reporter | 2026-10-07 / the owner (a friend playing on an Android phone: "the dice did not work and it locked up") |
| Test object | `src/js/games/backgammon.js` (`run()`, `controls()`), `src/js/shared/dice.js` |
| Environment | hakoo.eu on Android; reproduced in the test environment with Playwright (Pixel 7, touch) |
| Found by | the owner's friend; analysed with a scripted touch roll |
| Test level | system |
| Severity / priority | major / high (the game looks stuck) |
| Status | Fixed in `src/`, live until the next upload |
| Requirement | FR-BACKGAMMON-09 |

**Steps:** play against the computer on a phone (move animation on); after pressing Done, touch the cup as soon as
the turn line says "Your turn: roll the dice". **Expected:** the cup can be held and shaken. **Actual:** nothing
happens: the computer's turn is still being shown, so the cup is locked, while the turn line already says it is
the player's turn. On a slower phone this lasts several seconds and looks like a hang.
**Root cause:** the turn line comes from the game state (already the player's turn), the cup from the turn being
shown. The scripted touch roll itself works once the computer's turn has been shown (several turns in a row); a
real hang on the friend's phone was not reproduced: if it comes back, note the browser and the dice setting (⚙).
**Fix:** the dice table is the player's own (owner 2026-10-07): hidden while the other player rolls and moves, the
turn line says "… is playing", and it shows (working) when the player can roll. `dice.js` keeps its layout while
the table is hidden.

### DEF-017: Nonogram on a phone: two fingers do not zoom or move the board

| Field | Value |
|---|---|
| Date / reporter | 2026-10-07 / the owner (playing on a phone) |
| Test object | `src/js/games/nonogram.js`, `src/css/games/nonogram.css` |
| Environment | hakoo.eu on a phone |
| Found by | exploratory use by the owner |
| Test level | system |
| Severity / priority | major / high (Hard 20 × 20 is hard to play on a phone) |
| Status | Fixed in `src/`, live until the next upload |
| Requirement | FR-NONOGRAM-08 |

**Steps:** open the Hard puzzle on a phone, pinch or drag with two fingers on the board.
**Expected:** the board zooms and moves. **Actual:** nothing: the squares take every touch (`touch-action: none`,
so a finger fills instead of scrolling), so the browser's own zoom never starts, and the board can only be moved by
touching the clues.
**Fix:** the page zooms and moves the board itself with two fingers (out until all of it shows, in up to 4×); a
second finger takes back what the first had just filled; ⤢ Fit shows the whole board.

### DEF-018: the rules in short on Ludo and Stratego say less than the game does

| Field | Value |
|---|---|
| Date / reporter | 2026-10-07 / review of the rules texts (FR-GAMES-16, FR-GAMES-17) |
| Test object | `src/games/ludo.html`, `src/games/stratego.html` (the box "The rules in short") |
| Environment | `src/` |
| Found by | static testing: every short rule and rules page read against `src/<game>-engine.inc.php` |
| Test level | system |
| Severity / priority | minor / medium (a new player learns a rule the game does not play) |
| Status | Fixed in `src/`, live until the next upload |
| Requirement | FR-GAMES-16 |

**Expected:** the short rules agree with the game as it starts. **Actual:** Ludo said "a 6 brings a piece out",
but the setup starts with "A 6 or a 1" ticked (`LudoRules::$exit = '6or1'`); Stratego said "the Spy beats the
Marshal", but only a Spy that attacks does (`stratego-engine.inc.php`; its own rules page says so). The rules pages
and the other eight boxes agreed with their engines.
**Fix:** Ludo: "a 6 or a 1 brings a piece out (only a 6, if you choose), and a 6 rolls again"; Stratego: "The Spy
beats the Marshal when the Spy attacks". TC-E2E-198 reads the ticked Ludo choice, so the box follows the setup.

### DEF-019: a fresh Docker test database fails to start (migration order)

| Field | Value |
|---|---|
| Date / reporter | 2026-10-14 / Claude (a fresh `docker compose down -v && docker compose up -d --build`) |
| Test object | `database/migrations/migration_users_rewards_backfill.sql` (commit bb8d268), `docker/db/init/10-migrations.sh` |
| Environment | Docker test environment only; the live database is not affected (migrations are run by hand there) |
| Severity / priority | major / high: no test can run on a fresh environment |
| Status | Closed (testware) |

**Expected:** the db container runs every migration and starts. **Actual:** `ERROR 1146 Table
'<DB_NAME>.hakoo_stratego_profile' doesn't exist`. `10-migrations.sh` runs the migrations in file-name order,
and the backfill (which reads the board games' tables) sorted before `migration_users_stratego.sql`; its header said
the name sorted after them, which was not true.
**Fix:** renamed to `migration_zz_users_rewards_backfill.sql`, and a naming rule in `database/README.md`: a data
migration that reads other migrations' tables is named `migration_zz_<name>.sql`. Confirmed: a fresh start ran all 43
migrations and the seeds with the backfill last, the backfill ran a second time without error, PHPUnit 399 of 399.
