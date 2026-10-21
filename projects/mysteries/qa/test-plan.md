# Test plan: Mysteries

| | |
|---|---|
| Document | Test plan (structure based on ISO/IEC/IEEE 29119-3) |
| Version | 0.1 (skeleton), 2026-10-11 |
| Owner | Test manager: Woody_the_Viking (project owner) |
| Status | Skeleton: to be filled in once the architecture is chosen |

## 1. Context

Mysteries is a game engine for party games on several screens: shared boards (TV, second monitor, several
households at once) and a private player device (phone, tablet, computer window). Categories: murder mystery,
escape room, role-playing game, plus a creator tool. Test phase on hakoo.eu behind a hidden address for the
roles tester and admin. Test basis: [requirements/kravsammanstallning.md](requirements/kravsammanstallning.md).

## 2. Scope

**In scope:** everything in `src/` once it exists; US-01..US-14.
**Test order (A-01):** murder mystery → escape room → RPG → creator tool.
Logins: Discord → Steam → others (K-22). TV: computer → Android TV → Apple TV → smart TV (K-04).
**Out of scope:** see section 6 of the requirements; the external services themselves (Discord, Steam, app stores).

## 3. Test objectives

- Verify the requirements and validate the user stories with the testers (family and friends).
- Find defects early, especially in **private-information leaks** and **personal-data handling** (see [risk register](risk-register.md)).
- Build a regression suite that runs automatically on every change.

## 4. Test approach

The architecture is [ADR-001](../docs/adr-001-architecture.md): a web app, the server decides, the screens poll.

| Level | What | How |
|---|---|---|
| Component | The game modules' rules (deal, act), mystery content and its checker | PHPUnit without database (`LudoEngineTest.php`, `MysteryContentTest.php`, TC-INT-001..) |
| System (API) | `src/mysteries/api.php`: sessions, approval, what each device sees, pause | PHPUnit over HTTP (`SessionApiTest.php`, TC-API-006..) |
| System (E2E) | A host, a TV board and a player, each in a browser of its own | Playwright in chromium, firefox, phone, tablet and tv (`core.spec.js`, `ludo.spec.js`, TC-E2E-003..) |
| Acceptance | The user stories with the testers, on real TVs (Chromecast, Android TV, smart TV) and phones | Manual (TC-MAN), per release |

Techniques: boundary values for the settings (length 30..180, wait 1..10), decision table for who may do what
(host, player, board × waiting, approved, refused), state transitions for the session (lobby → playing ⇄ paused).
Ludo (US-15): see [test-design/ludo.md](test-design/ludo.md).
Mystery content (US-16): see [test-design/mystery-content.md](test-design/mystery-content.md).

## 5. Entry and exit criteria

To be decided.

## 6. Test environment

Docker ([docker/README.md](../docker/README.md)): hakoo's site with Mysteries at http://localhost:8090/mysteries/,
MariaDB with hakoo's and Mysteries' migrations, fictional users (Alice owner, Bob and Erik testers, Carol no role).
PHPUnit for API/integration, Playwright for chromium, firefox, phone, tablet and tv.
Native apps and real TV devices (Chromecast, Apple TV, Android TV): to be decided.
