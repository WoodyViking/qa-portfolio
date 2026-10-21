# ADR-001: A web app on hakoo.eu, the server decides, the screens poll

| | |
|---|---|
| Status | Accepted (owner, 2026-10-14) |
| Date | 2026-10-14 |
| Requirements | NFR-01, NFR-02, NFR-03, NFR-05, NFR-07, NFR-09, NFR-10, K-01..K-05, K-11..K-13, K-28, K-33..K-36 |

## Context

Mysteries needs several **shared boards** (a TV in each household, a second monitor) and a **private view per player**
(phone, tablet, a computer window), all in one game session (US-01, K-01..K-03). Players are from 13 years old and are
family and friends in several countries. The test phase runs on hakoo.eu behind the tester/admin roles (K-41), on a
shared web host (<HOSTING_PANEL>, <WEB_SERVER>, PHP 8.5, MariaDB, Node 20 through Passenger for the presence bot).

What matters most for the choice:
- **Private information must stay private** (K-13): no clue may reach another device, not even in the network traffic.
- **Every device type**, TV browsers included (NFR-01), with no install to join (K-11: QR code or link).
- **Accessibility through the devices' own aids** (K-33..K-35): screen readers, zoom, text size, read-aloud.
- **5 s** from an action to every board (NFR-02), and a game that can be paused for a year (NFR-03).
- The content is mostly text, pictures and choices: clues, characters, rooms, codes.

## Decision

1. **A web app**, not a game engine. HTML, CSS and JavaScript in the browser (three.js for 3D if a game needs it, as on
   hakoo); the same pages later packaged as phone and tablet apps with Capacitor.
2. **The server decides everything (authoritative server)** in **PHP 8.5 + MariaDB**, inside hakoo.eu at `/mysteries/`.
   It keeps the whole game state and sends each device **only what that device may see**: a board gets the public state,
   a player their own private state as well. The browsers only show and ask; the server checks every action.
3. **The screens poll** for changes every 1.5 s (`api.php?action=state&since=<version>`; an unchanged game answers with
   one small line). hakoo's Ludo already plays this way. The client code hides the transport, so it can move to
   WebSockets later without changing the games.
4. **A shared core and one module per game type.** The core: sessions, devices and their approval, players, roles
   (host, board, player, game master), private state, pause; later the disconnect wait, votes and the AI stand-in
   (K-17..K-20). A module (murder mystery, escape room, RPG) adds the rules and the views of its own.
5. **Games are data.** A mystery or a room is a description (JSON): characters, clues, places, which parts are drawn at
   random, the solution. The module reads it. The same format is used by the creator tool (K-37), for replays with new
   random parts (K-36) and for the review before publishing (K-38).
6. **Devices are identified by a random token per browser tab** (`sessionStorage`), stored only as a SHA-256 hash.
   A board needs no login: it joins with the session code and waits for the host to approve it (K-12). A player must be
   logged in with Discord (hakoo's login) and is approved too; the player's token only works together with their own login.
   For testing, the host can add up to five test players (K-121): devices on the host's own login with a seat number, each
   a player of its own with its own private state, keyed `<discord id>-<seat>` in the games. A member without the tester
   role joins one game with its invitation key, a random 128-bit key per session that only the host sees (K-122, K-123).
7. **Languages**: one JSON file per language (`lang/en.json`, `sv`, `da`, `nl`); English is the default and fills in any
   missing text (K-28). The board shows the language the host picked, each player their own (K-29); a board address with two letters (`&lang=SE`, `DK`, `NL`, `EN`) picks that screen's language instead (K-71). Game content uses
   keys, so a card or a clue is translated the same way.
8. **Tests** as in hakoo (ISTQB): PHPUnit against the API in Docker, Playwright in chromium, firefox, phone, tablet and tv.

## Alternatives considered

| Alternative | Why not (now) |
|---|---|
| **Unity** (C#) | Its web builds are large and run badly or not at all in TV browsers; it draws its own interface, so screen readers, zoom and the phone's text size do not work (K-33..K-35); a new language, toolchain and licence; a separate game server would still be needed. Its strength, real-time 3D action, is not what these games need. |
| **Node.js + WebSockets** | Fewer requests and instant updates. But it is not known whether the shared host keeps long-lived connections open through <WEB_SERVER>/Passenger, and it would split the code over two languages from day one. Kept open behind the transport layer (decision 3). |
| **A hosted real-time service** (Firebase, Supabase, Ably…) | Personal data with a third party (GDPR, NFR-07), a running cost, and the rules would still have to be checked on a server we control. |
| **Peer-to-peer between the devices** | One device would hold everyone's secrets (against K-13), and it fails behind many home routers. |

## Consequences

- One stack for the whole of hakoo.eu: the same login, roles, Docker environment, tests and deploy.
- **Load**: with 10 devices a session makes about 7 small requests a second. That is fine for a few family games at once
  on the shared host; measure it (risk R-03) and slow the polling down when the game is paused or the tab is hidden.
- **Apple TV** has no browser and no web view in apps: until a native tvOS app exists, the board reaches an Apple TV with
  AirPlay from an iPhone, iPad or Mac (open question, K-04).
- **Personal data**: the sessions, devices and private state are tied to `hakoo_users` with `ON DELETE CASCADE`, so
  "Delete all my data" on hakoo removes them. hakoo's "Download my data" must include them before real players use
  Mysteries (a change in the hakoo repo; open item).
- A board on a TV is not logged in. It only ever sees the public state, and only after the host approved it.

## Open

- WebSockets on the host: try once the core works (a small test with the presence bot's Passenger app).
- QR code for joining (K-11): a small library kept on hakoo.eu, or drawn by our own code.
- Native apps (Capacitor) and the App Store rules for logins and payments: before launch.
