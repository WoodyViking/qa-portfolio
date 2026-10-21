# Functional requirements

What the system must do. Each requirement is **testable** (it has a clear expected result),
has a **priority** (MoSCoW: Must / Should / Could) and names its **source**.
Reverse-engineered from `src/` on 2026-09-26; review them (static testing) before relying on them.

## FR-START: start page (`index.html`)

| ID | Requirement | Priority | Source |
|---|---|---|---|
| FR-START-01 | The start page shows three large options: HAKOO Community, HAKOO Games and "Remember my choice". | Must | US-01 |
| FR-START-02 | Nothing is stored in the browser unless "Remember my choice" is on **and** the visitor picks a page, or the visitor picks a language or a text size (FR-I18N-12), or saves an appearance (FR-SITE-03). | Must | US-02, REG-EPR-01 |
| FR-START-03 | When a choice is saved, opening the start page sends the visitor straight to the saved page. | Must | US-02 |
| FR-START-04 | `index.html?choose=1` always shows the chooser, including the saved choice if there is one. | Must | US-02 |
| FR-START-05 | "Forget my choice" deletes the saved choice and turns "Remember my choice" off. | Must | US-02, REG-GDPR-7 |
| FR-START-06 | "Remember my choice" shows its state (on/off) visually and via `aria-pressed`. | Should | NFR-USAB-01 |
| FR-START-07 | "Forget my choice" is only visible when a choice is saved. | Should | DEF-001 |

## FR-GAMES: HAKOO Games (`hakoo-games.html`)

| ID | Requirement | Priority | Source |
|---|---|---|---|
| FR-GAMES-01 | HAKOO Games can be used without login and without accepting cookies. | Must | US-03 |
| FR-GAMES-02 | HAKOO Games sets no cookies and writes no localStorage/sessionStorage, except the language or text size the visitor picks in the language menu (FR-I18N-12), an appearance they save (FR-SITE-03) and the Path of Berserk keys they save (FR-BERSERK-15, 2026-10-17). Playing stores nothing. | Must | US-03, REG-EPR-01 |
| FR-GAMES-03 | For a visitor who is not logged in, HAKOO Games loads nothing from other domains (logged in: the Discord avatar in the login corner). | Must | US-03, REG-GDPR-25 |
| FR-GAMES-06 | The HAKOO Games badges show the visitor's real state: anonymous or "logged in as …", what is stored (Discord login, saved start page, language, text size), and the rewards over all board games (n/9, per game in the tooltip). | Should | US-03 |
| FR-GAMES-04 | HAKOO Games links to the start page (`?choose=1`) and to HAKOO Community. | Should | US-01 |
| FR-GAMES-05 | HAKOO Games has the same login corner as the rest of the site (login button, or avatar + profile link), returning to the same page; HAKOO Community pages link to HAKOO Games. | Should | US-17 |
| FR-GAMES-07 | A member gets one 👑 on their avatar for a Hard win in any board game (Gomoku, Chess, Othello, Hnefatafl, Stratego) whose crown they show; the crown's tooltip names those games. | Could | US-18, US-22 |
| FR-GAMES-08 | HAKOO Games offers Gomoku, Chess, Othello, Hnefatafl and Stratego, each against the computer (three levels) or a friend. | Must | US-15, US-16, US-19, US-20, US-25, US-27 |
| FR-GAMES-09 | Every board game shows coordinates on the board and a numbered move list in the game's usual notation (Gomoku `H8`, letters without I; Othello `d3`, rows counted from the top, passes shown; Chess SAN; Hnefatafl `a8–a9`, a1 bottom-left; Stratego `a4–a5`, an attack `a4×a5 7>5`). A player can look back at the board after any move (⏮ ◀ ▶ ⏭ or a click on a move) and cannot move meanwhile; in Fog of War looking back still shows only what the player saw.  On a wide screen (960 px and more) the move list stands to the right of the board; on a narrow screen under it. | Should | US-24 |
| FR-GAMES-10 | A member who has unlocked the Medium reward (3D) of a board game can turn and tilt that game's board: by dragging it (mouse, pen or one finger; a drag is never a move) or with buttons that also work from the keyboard (turn 45° left or right, tilt 15° more or less, 0–60°), and "Reset view" shows it flat from their own side again. The pieces stand up and face the viewer; a click still hits the square under the pointer. The view is not stored (a reload starts flat), and a page without the unlock (logged out, or no Medium win) has nothing to turn. | Could | US-28 |
| FR-GAMES-11 | Every board-game page has a move-speed setting for everyone, behind ⚙ in the menu bar on every view of the page (and on the profile under Board games for members): 0.1–2 s in steps of 0.1 s, 0.6 s by default. It is how long a move takes to show (FR-OTHELLO-13, FR-GAMES-12). A logged-in member's choice is saved on their profile (`hakoo_board_settings`) and holds in every board game; for a visitor who is not logged in it lasts until the page is left and nothing is stored. A device that asks for less motion (`prefers-reduced-motion`) sees no move animation until the visitor picks a speed or has one saved. The server accepts only whole numbers 100–2000 (ms), only by POST and only when logged in. | Should | US-29, REG-GDPR-15, REG-GDPR-17 |
| FR-GAMES-12 | In Gomoku, Chess, Hnefatafl and Stratego the other player's move is shown at the move speed: the piece slides over from its square (an attack in Stratego only when the attacker wins), a Gomoku stone drops in. The player's own move is shown at once. | Could | US-29 |
| FR-GAMES-13 | The dice games (Ludo, Backgammon, later Yatzy; prototype `games/dice-lab.html`) roll 3D dice in one of three ways the player chooses: a button, a cup held with the mouse or a finger (shake it, let go), or shaking the phone and tipping it to pour (phones, secure page, permission on iPhone). For shaking the phone, how much shaking and tipping is needed: a little / normal (default) / a lot. The server always decides the values. A logged-in member's choices are saved on their profile (`hakoo_board_settings`, under Board games) and hold in every dice game; for a visitor nothing is stored. The server accepts only `diceRoll` button/cup/motion and `diceShake` easy/normal/hard, only by POST and only when logged in. | Should | REG-GDPR-15, REG-GDPR-17 |
| FR-GAMES-14 | The HAKOO Games hub shows the games on shelves picked in a menu: Most played, Board games, Dice games, Card games, Brain puzzles, All games (a column on the left; a row that scrolls on a phone). Each type says how many games it has, or "coming soon". Card games: Runborg, the first version in the page (`games/runborg-lab.html`, owner 2026-10-08). The shelf is in the address (`#board`, ...); nothing is stored in the browser. | Should | Owner 2026-10-05 |
| FR-GAMES-15 | "Most played", the first shelf, shows the 6 games with the most games started in the last 30 days that got at least one move (`games-popular.php`: counts only, cached 10 minutes), with the number on each card; a tie, or no data, keeps the page's own order. | Should | Owner 2026-10-05 |
| FR-GAMES-16 | Every game's start page shows the rules in short beside the choices (to the right on a wide screen, below them on a phone): a few lines, a picture drawn with the game's own board, and a link to the rules page. The lines say what the game does with the choices it starts with (the engine's defaults), and the heading and the link follow the language on the pages that have one (page texts "common": `rules_short`, `rules_more`). | Should | Owner 2026-10-05 |
| FR-GAMES-17 | Every game has a rules page (`games/<game>-rules.html`) with the complete rules and pictures drawn with the game's own board, with arrows and marks (Chess: castling, en passant, promotion, stalemate). "Game Rules" on the start page and in the menu bar opens it; during a game the Rules button opens the rules in a dialog that links to it, so the game stays open. | Should | Owner 2026-10-05 |
| FR-GAMES-18 | On a phone (700 px wide or less) the menu bar of every HAKOO Games page is one row with two menus: ☰ on the left for the page (All games, Game Rules, ⚙ the game's settings and, with the 3D reward, the buttons that turn and tilt the board, which are then not under the board) and 👤▾ on the right for what is in that corner on a computer (the language with its own settings, and logging in or out; logged in, the button shows the member's picture). A link or ⚙ closes the menu, the turn buttons keep it open, Escape or a tap outside closes it. On a wider screen the bar is unchanged. | Should | Owner 2026-10-07 |
| FR-GAMES-19 | On a touch screen (phone or tablet) the board of a board game is never turned or tilted, not even with the 3D reward (the 3D pieces stay; no turn buttons). Instead fingers zoom and move it (`js/shared/board-zoom.js`): two fingers pinch to zoom (in up to 4×) and move the board sideways and up and down; one finger drags a zoomed board; a tap is still a move and a drag or pinch never is; when the board cannot move further the rest of the movement scrolls the page; ⤢ shows the whole board. Nonogram uses the same two-finger zoom. On a computer nothing changes. | Should | Owner 2026-10-07 |
| FR-GAMES-20 | Every choice a player makes about how a game plays or looks (keys, controls, speeds, dice, chosen rules, looks, sounds, the last game's choices) is kept with a logged-in member on the server and comes back on every device they log in on; a guest's is kept in their browser only after they chose to keep it, and moves to their account the first time they log in. Kept today: appearance (FR-SITE-03), board-game settings and Ludo choices (FR-LUDO-18), Path of Berserk keys (FR-BERSERK-15). Not yet: the language and the start page (browser only); the text size stays per device. | Must | Owner 2026-10-18 ("spara ändringar som spelaren gör ... oavsett vart man loggar in") |

*2026-09-27, owner decision:* the course rule "HAKOO Games runs no JavaScript" (old TC-E2E-021) no longer applies; the hub now uses the shared login corner and shows the visitor's real state (FR-GAMES-05, 06).

## FR-GOMOKU: Gomoku (`games/gomoku.html`, `gomoku.php`, `gomoku-engine.inc.php`)

Built from the school requirements in [SebTns/Gomoku](https://github.com/SebTns/Gomoku) (IDs in
the Source column, prefixed `G:`), adapted to HAKOO Games: anonymous, PHP + MariaDB, no WebSockets.

| ID | Requirement | Priority | Source |
|---|---|---|---|
| FR-GOMOKU-01 | The board is 15×15 (default) or 19×19; black moves first; **exactly** five in a row wins, six or more does not; a full board without five is a draw, and a five on the last point is a win. | Must | US-15, G:FR-03.2, G:FR-04.3, G:FR-08.8–16, G:FR-20, G:SR-01.3 |
| FR-GOMOKU-02 | The server checks every move: it must be the player's turn, the point must be empty and on the board, and the game must be active. A refused move changes nothing. | Must | US-15, G:FR-08.2, G:FR-08.3, G:FR-08.13 |
| FR-GOMOKU-03 | A player can play the computer on Easy, Medium (default) or Hard; the computer answers within 3 seconds, and harder levels beat easier ones. | Must | US-15, G:FR-05, G:FR-06, G:NFR-02.3 |
| FR-GOMOKU-04 | A player can invite a friend with a link that works once, for 15 minutes, and can be cancelled; an invalid, used, cancelled or expired link is refused with the reason. | Must | US-16, G:FR-07 |
| FR-GOMOKU-05 | The friend's moves appear without reloading the page, within about 1 second. | Should | US-16, G:NFR-02.6 (deviation: 500 ms and WebSockets in the school spec; polling here) |
| FR-GOMOKU-06 | A player can resign (the opponent wins) or end the game (no winner); both ask for confirmation first. | Must | US-15, G:FR-09, G:FR-10 |
| FR-GOMOKU-07 | Player names are optional, 2–20 letters, digits, `-` or `_`; the defaults are "Player 1" and "Player 2". | Should | G:FR-02.2–02.4 |
| FR-GOMOKU-08 | Gomoku keeps FR-GAMES-01..03: no login, no cookies, no browser storage, no third-party requests. A player is identified by a random token in the URL after `#`; only its SHA-256 hash is stored; a reload continues the game. | Must | US-03, US-15, REG-GDPR-25 |
| FR-GOMOKU-09 | Games are deleted 30 days after the last activity. | Must | REG-GDPR-5, G:FR-11.5 |
| FR-GOMOKU-10 | The rules (goal, moves, exact five, draw) can be opened from the start view and during a game without affecting it. | Should | G:FR-22 |
| FR-GOMOKU-11 | The board works with mouse, touch and keyboard (arrow keys + Enter), and a 19×19 board fits a 360 px screen without sideways scrolling. | Should | G:NFR-01.3, G:NFR-01.6, G:AC-02-09 |
| FR-GOMOKU-12 | A member who is logged in with Discord when starting a computer game unlocks a reward with the first win on each level: Easy → stone colours, Medium → 3D stones, Hard → a crown. Anonymous games and friend games unlock nothing. | Should | US-17 |
| FR-GOMOKU-13 | The Gomoku page shows a logged-in member's unlocks and plays with their chosen look; logged out, it explains that rewards need a login and works as before. | Should | US-17, US-18 |
| FR-GOMOKU-14 | On the profile, a member can choose the board (always), the stone colours (after Easy; the two colours must differ), 3D stones (after Medium) and whether the crown is shown (after Hard). The server refuses locked choices. | Should | US-18 |
| FR-GOMOKU-15 | A member with a Hard win and a visible crown gets 👑 on their avatar across HAKOO Community (login corner, member lists, trackers). | Could | US-18 |
| FR-GOMOKU-16 | Rewards, look and the member's computer games are in the personal-data register: exported by "Download my data" and deleted by "Delete all my data". | Must | REG-GDPR-15, REG-GDPR-17 |

**Not in this version** (from the school spec): accounts and passwords (G:FR-13–16, 18, 19, 25; HAKOO uses
Discord), game history and leaderboard (G:FR-11), undo (G:FR-12), local hot-seat (G:FR-21), save/load
files (G:FR-17, 26), reset (G:FR-23), problem reports and moderation (G:FR-28, 29), remembering the name and
difficulty in the browser (G:FR-02.6, G:FR-05.6: conflicts with FR-GAMES-02). Next step: challenge a clan
member from the Discord member list; the bot sends the same invite link as a DM (Discord does not give
websites access to a user's friends list).

## FR-CHESS: Chess (`games/chess.html`, `chess.php`, `chess-engine.inc.php`)

Chess works like Gomoku: the actions, seats, invites, polling and rewards are shared by all HAKOO Games
board games (`board-game-api.inc.php`, `js/shared/board-game.js`), so FR-GOMOKU-04..09 hold for Chess too.

| ID | Requirement | Priority | Source |
|---|---|---|---|
| FR-CHESS-01 | The FIDE rules: white moves first; every piece moves its own way, including castling, en passant and promotion (the player picks queen, rook, bishop or knight); no move may leave the mover's own king in check. | Must | US-19 |
| FR-CHESS-02 | The server checks every move: the player's turn, their own piece, a legal move, an active game. A refused move changes nothing and says why (`illegal_move`, `not_your_piece`, `not_your_turn`, `game_over`). | Must | US-19 |
| FR-CHESS-03 | Checkmate wins. Stalemate, the same position three times, fifty moves each without a capture or pawn move, and too little material to checkmate are automatic draws. | Must | US-19 |
| FR-CHESS-04 | A player can play the computer on Easy, Medium (default) or Hard; it answers in the same request within 3 seconds and opens the game when it plays white. Every level takes a mate in one; harder levels beat easier ones. | Must | US-19 |
| FR-CHESS-05 | The board shows where a picked piece can go, the last move, a king in check, the captured pieces with the material difference, and the moves in algebraic notation; playing black, the board is turned with black at the bottom. | Should | US-19 |
| FR-CHESS-06 | Like FR-GOMOKU-06..09: resign (with confirmation) or end without a winner; optional names; no login, cookies or browser storage; the seat token lives after `#` and only its hash is stored; games are deleted 30 days after the last activity. | Must | US-03, US-19 |
| FR-CHESS-07 | Like FR-GOMOKU-04/05: a friend is invited with a one-time link valid for 15 minutes, and their moves appear within about 1 second. | Must | US-19 |
| FR-CHESS-08 | A member logged in with Discord when starting a computer game unlocks a reward with the first **checkmate** on each level: Easy → piece colours, Medium → 3D pieces, Hard → a crown. Anonymous games and friend games unlock nothing. | Should | US-21 |
| FR-CHESS-09 | The Chess page plays with the member's look. On the profile, a member can choose the board colours (always), the piece colours (after Easy; the two must differ), 3D pieces (after Medium) and whether the Chess crown is shown (after Hard). The server refuses locked choices. | Should | US-21, US-22 |
| FR-CHESS-10 | Chess rewards, look and the member's computer games are in the personal-data register (export and delete). | Must | REG-GDPR-15, REG-GDPR-17 |
| FR-CHESS-11 | The board works with mouse, touch and keyboard (arrow keys + Enter to pick and to move) and fits a 360 px screen without sideways scrolling. | Should | US-19 |
| FR-CHESS-12 | A game can be started as **Fog of War**: each player sees only their own pieces, the squares those pieces can move to (and the pawn that can be taken en passant), and the square in front of each pawn (two from the start row); everything else is fog. | Should | US-23 |
| FR-CHESS-13 | In Fog of War there is no check: any move of the pieces is allowed (also castling through attacked squares, and moving the king into attack), taking the king wins, and moves are written without `+`/`#`. Stalemate (no move at all), repetition and fifty moves still draw; insufficient material does not. | Should | US-23 |
| FR-CHESS-14 | Against the computer in Fog of War, Easy and Medium play fair: they only know what they see and where they last saw each piece. Hard peeks through the fog, and the settings say so. Medium beats Easy. | Should | US-23 |
| FR-CHESS-15 | The server never sends a player what they cannot see while the game runs: fogged squares are `?`, the opponent's moves are hidden, and the legal moves are only sent on the player's own turn. What it sends about memory (FR-CHESS-16, FR-CHESS-17) depends only on what the player has seen. When the game is over, the whole game is shown. A Fog of War game unlocks no reward. | Must | US-23 |
| FR-CHESS-16 | In Fog of War a piece of the opponent that the player has lost sight of stays on the board as a **shadow** (a faint piece with a "?") on the square where it was last seen, until that square is in sight again. At the start each side remembers the other's army where it begins. When a piece is seen on a new square, the nearest shadow of that kind is dropped (the number of pieces left is counted from the start and the pieces taken, never from the real board, so an unseen promotion shows nothing). The memory is worked out on the server from the moves; nothing is stored in the browser. | Should | US-23 |
| FR-CHESS-17 | In Fog of War the player who loses a piece is shown the square where it was taken and what took it: the taker is remembered on that square as a shadow, the square is marked until the player's next move, and the turn line says "Your pawn was taken on e5 by the white knight.". The player who took it is not told anything new. | Should | US-23 |

## FR-OTHELLO: Othello (`games/othello.html`, `othello.php`, `othello-engine.inc.php`)

Othello (also called Reversi) works like Gomoku too, on the same shared API and page script.

| ID | Requirement | Priority | Source |
|---|---|---|---|
| FR-OTHELLO-01 | The World Othello Federation rules: 8×8, two discs of each colour in the middle, black first; a disc must outflank at least one line of the opponent's discs and every outflanked line flips; a player without a legal move passes (automatically); when neither can move the game ends and most discs wins, equal is a draw. | Must | US-20 |
| FR-OTHELLO-02 | The server checks every move: the player's turn, an empty square on the board, at least one disc flipped, an active game. A refused move changes nothing and says why (`no_flips`, `occupied`, `not_your_turn`, `game_over`). | Must | US-20 |
| FR-OTHELLO-03 | A player can play the computer on Easy, Medium (default) or Hard; it answers within 3 seconds; harder levels beat easier ones; Medium and Hard take a free corner; Hard plays the last 12 empty squares perfectly. | Must | US-20 |
| FR-OTHELLO-04 | The computer answers in the same request, opens the game when it plays black, and moves again as long as the player has to pass, so the player is never left waiting. | Must | US-20 |
| FR-OTHELLO-05 | Like FR-GOMOKU-04/05: a friend is invited with a one-time link valid for 15 minutes, and their moves appear within about 1 second. | Must | US-20 |
| FR-OTHELLO-06 | Like FR-GOMOKU-06..09: resign or end, optional names, no login/cookies/browser storage, token after `#` (hash stored), deleted 30 days after the last activity. | Must | US-03, US-20 |
| FR-OTHELLO-07 | A member logged in with Discord when starting a computer game unlocks a reward with the first win **on the board** (most discs) on each level: Easy → disc colours, Medium → 3D discs, Hard → a crown. | Should | US-21 |
| FR-OTHELLO-08 | The Othello page plays with the member's look. On the profile: board (always), disc colours (after Easy; must differ), 3D discs (after Medium), Othello crown shown (after Hard). The server refuses locked choices. | Should | US-21, US-22 |
| FR-OTHELLO-09 | Othello rewards, look and the member's computer games are in the personal-data register (export and delete). | Must | REG-GDPR-15, REG-GDPR-17 |
| FR-OTHELLO-10 | On the player's turn, dots show the legal squares; both disc counts, the last disc and flipping discs are shown, and a pass is announced ("… had no move and passed"). | Should | US-20 |
| FR-OTHELLO-11 | The board works with mouse, touch and keyboard (arrow keys + Enter) and fits a 360 px screen. | Should | US-20 |
| FR-OTHELLO-12 | The 3D discs (Medium reward, FR-OTHELLO-07) look like real Othello discs: thick, one face and half the edge in the black colour, the other face and half the edge in the white colour. A white disc is the same disc turned over. On a turned board (FR-GAMES-10) the discs lie flat on it. | Should | US-29 |
| FR-OTHELLO-13 | For every player (flat or 3D discs): after a move the played disc lands and the discs it flips turn over one after the other in a wave outwards from it, nearest first; each turn takes the move speed (FR-GAMES-11). When the computer answers in the same request, the player's move is shown to the end first, then the computer's disc lands and its flips turn. A click during the flips shows the end position at once and plays the move. | Should | US-29 |

## FR-HNEFATAFL: Hnefatafl (`games/hnefatafl.html`, `hnefatafl.php`, `hnefatafl-engine.inc.php`)

Hnefatafl (Viking chess) works like Othello, on the same shared API and page script. The seats are called
black (the attackers) and white (the defenders with the king) in the API; the page says Attackers and Defenders.

| ID | Requirement | Priority | Source |
|---|---|---|---|
| FR-HNEFATAFL-01 | The Copenhagen Hnefatafl rules on 11×11: 24 attackers on the edges, the king on the throne with 12 defenders, the attackers move first; every piece moves like a rook; only the king may stop on the throne and the corners, and any piece may pass the empty throne. | Must | US-25 |
| FR-HNEFATAFL-02 | Captures: an enemy between the moved piece and another of the mover's pieces or a hostile square (corners; the throne, hostile to defenders only when empty) is taken; moving in between is safe; the king takes part in captures; a shieldwall on the edge (2+ pieces, both ends closed, a corner counts, an enemy in front of each) is taken except the king. | Must | US-25 |
| FR-HNEFATAFL-03 | The game ends when the king reaches a corner or builds an edge fort that cannot be broken (defenders win), when the king is surrounded on four sides or three and the throne, never on the edge, or all defenders are surrounded (attackers win), when a player cannot move (they lose), or on the third repetition of a position (the defenders lose). | Must | US-25 |
| FR-HNEFATAFL-04 | A player can play the computer on Easy, Medium (default) or Hard, as either side; it answers within 3 seconds; every level takes a king's open way to a corner; Medium and Hard block a single open line. | Must | US-25 |
| FR-HNEFATAFL-05 | The server checks every move: the player's turn, their own piece, a straight line over empty squares, no stop on a restricted square. A refused move changes nothing and says why (`not_your_turn`, `no_piece`, `not_your_piece`, `not_straight`, `blocked`, `restricted`, `outside`). The computer answers in the same request and opens the game when it attacks. | Must | US-25 |
| FR-HNEFATAFL-06 | Like FR-OTHELLO-05/06 and FR-GAMES-09: invite link (15 minutes), resign or end, optional names, no login/cookies/browser storage, token after `#` (hash stored), deleted 30 days after the last activity, move list and looking back. | Must | US-03, US-25 |
| FR-HNEFATAFL-07 | A member logged in with Discord when starting a computer game unlocks a reward with the first win **on the board** on each level: Easy → piece colours, Medium → 3D pieces, Hard → a crown. | Should | US-25 |
| FR-HNEFATAFL-08 | The Hnefatafl page plays with the member's look. On the profile: board (always), attacker and defender colours (after Easy; must differ), 3D pieces (after Medium), Hnefatafl crown shown (after Hard). The server refuses locked choices. | Should | US-22, US-25 |
| FR-HNEFATAFL-09 | Hnefatafl rewards, look and the member's computer games are in the personal-data register (export and delete). | Must | REG-GDPR-15, REG-GDPR-17 |
| FR-HNEFATAFL-10 | The player picks a piece and sees dots where it can go; the throne and corners are marked, the pieces left on each side, the last move and captured pieces are shown, and a capture is announced; wrong clicks say why. | Should | US-25 |
| FR-HNEFATAFL-11 | The board works with mouse, touch and keyboard (arrow keys + Enter, Escape puts a piece down) and fits a 360 px screen. | Should | US-25 |

## FR-STRATEGO: Stratego (`games/stratego.html`, `stratego.php`, `stratego-engine.inc.php`)

Stratego works like Othello, on the same shared API and page script, plus a set-up phase and hidden
information. The seats are called black (Red, moves first) and white (Blue) in the API; the page says Red and Blue.

| ID | Requirement | Priority | Source |
|---|---|---|---|
| FR-STRATEGO-01 | Classic Stratego (ISF, 10 = highest): 10×10 with two lakes; 40 pieces each (Marshal 10, General 9, 2 Colonels, 3 Majors, 4 Captains, 4 Lieutenants, 4 Sergeants, 5 Miners, 8 Scouts, Spy, 6 Bombs, Flag); each player sets up on their own four rows before the first move; Red moves first. | Must | US-27 |
| FR-STRATEGO-02 | Moves: one square across or down, never into a lake or onto an own piece; a Scout any number of empty squares in a line and may attack at the end; Bombs and the Flag never move. Attacks: the higher rank wins, equal ranks both fall, the Spy takes the Marshal when the Spy attacks, a Bomb takes every attacker but a Miner. | Must | US-27 |
| FR-STRATEGO-03 | Taking the Flag wins; a player who cannot move loses (both stuck: a draw); a piece may not move between the same two squares more than three times in a row; 200 moves in a row without an attack are a draw. | Must | US-27 |
| FR-STRATEGO-04 | Hidden information: the server never sends a player the other set-up or the rank of an enemy piece they have not seen (in a battle, or a Scout's long run); during the set-up not even where the enemy pieces stand. Looking back during a game is just as secret; after the game everything is shown. | Must | US-27 |
| FR-STRATEGO-05 | A player can play the computer on Easy, Medium (default) or Hard, as either army. The computer plays only from what its seat may know (it never peeks), sets up with its Flag on the back row behind Bombs (Medium, Hard), takes seen pieces it beats, and answers within 3 seconds; Hard beats Medium more often than not. | Must | US-27 |
| FR-STRATEGO-06 | The server checks every set-up (40 pieces in the right numbers, once) and every move (`not_set_up`, `not_your_turn`, `no_piece`, `not_your_piece`, `cannot_move`, `lake`, `own_piece`, `not_straight`, `one_step`, `blocked`, `two_squares`, `outside`). The computer sets up at once and answers in the same request. | Must | US-27 |
| FR-STRATEGO-07 | Like FR-OTHELLO-05/06 and FR-GAMES-09: invite link (15 minutes), both friends set up at the same time, resign or end, optional names, no login/cookies/browser storage, token after `#` (hash stored), deleted 30 days after the last activity, move list with battles, looking back. | Must | US-03, US-27 |
| FR-STRATEGO-08 | A member logged in with Discord when starting a computer game unlocks a reward with the first win **on the board** (flag or no moves) on each level: Easy → piece colours, Medium → 3D pieces, Hard → a crown. | Should | US-27 |
| FR-STRATEGO-09 | The Stratego page plays with the member's look. On the profile: board (always), Red and Blue army colours (after Easy; must differ), 3D pieces (after Medium), Stratego crown shown (after Hard). The server refuses locked choices. Rewards, look and computer games are in the personal-data register (export and delete). | Should | US-22, US-27, REG-GDPR-15, REG-GDPR-17 |
| FR-STRATEGO-10 | The set-up has a tray of the 40 pieces, placing, swapping and taking back, "Fill at random" (Flag on the back row, Bombs beside it) and Ready (only with all 40 placed). In play: pick a piece, marks show where it can go; enemy pieces show their backs, a mark for enemy pieces that have moved, 👁 on own pieces the enemy has seen; every battle is announced and fallen pieces are listed; wrong clicks say why. | Should | US-27 |
| FR-STRATEGO-11 | Blue sees the board turned round (own army at the bottom). The board works with mouse, touch and keyboard (arrow keys + Enter, Escape puts a piece down) and fits a 360 px screen. | Should | US-27 |
| FR-STRATEGO-12 | When the board is turned so that the player looks from the enemy's side (more than 90° from their own side, FR-GAMES-10), their own pieces show their backs without rank, as in the real game. Turning never shows an enemy rank the player has not seen: those ranks are not in the browser at all (FR-STRATEGO-04). | Should | US-28 |

*2026-10-03, owner decisions:* the boxes must not change colour with the text; own box colours like the background; moving backgrounds of dots or stars that react to the mouse (FR-SITE-06, 07).

*2026-10-02, owner decisions (later the same day):* the profile gets a left menu with one section at a time, which replaces the list of Discord members there (FR-USER-05); the move speed moves behind ⚙ on the game pages, for everyone (FR-GAMES-11).

*2026-10-02, owner decisions:* the move speed is saved on the profile (not in the browser); the 3D disc stays the Medium reward while the flips and the speed are for everyone; the discs turn in a wave outwards (FR-GAMES-11, FR-OTHELLO-12, 13). How 3D models are made in the other games later (CSS or WebGL) is not decided yet.

*2026-10-02, owner decision (confirmed):* FR-STRATEGO-12 is what the customer meant by "får inte visa vilken pjäs det är": pieces show their backs when the board is turned past the side. Enemy pieces that have been seen keep showing their rank from every side, as they did before turning existed.

## FR-LUDO: Ludo / Fia med knuff (`games/ludo.html`, `ludo.php`, `ludo-engine.inc.php`, `js/shared/dice.js`)

Ludo has up to four players, so it has its own API (`ludo.php`) instead of the two-seat board-game API; it
borrows that API's helpers and the members' settings (FR-GAMES-11, FR-GAMES-13).

| ID | Requirement | Priority | Source |
|---|---|---|---|
| FR-LUDO-01 | The server rolls the die (`hakoo_roll_dice`, `random_int`), only for the player to move and only when no roll is waiting; the page only shows it on the 3D die (FR-GAMES-13). | Must | Owner 2026-10-02 |
| FR-LUDO-02 | Board: the royal board of Kunglig Vikingludo with 4 arms (FR-LUDO-20) without crowns and feathers: 56 shared squares (14 an arm, the corner square by the middle included), 5 home squares per colour, the goal; seats Red, Blue, Yellow, Green clockwise. A piece leaves the nest on a 6, or on a 6 or a 1 (chosen when the game is made), onto its start square. A game begun on the earlier board (40 squares, 4 home squares) goes on with every piece on the same stretch of the new board. | Must | Owner 2026-10-02; board changed by the owner 2026-10-20 ("den vanliga på hakoo ska vara som den du ritade upp för hakoo men utan kronor och fjädrar") |
| FR-LUDO-03 | Landing on another colour's piece on the ring sends it back to its nest; own pieces may share a square; the home column is safe. | Must | Owner 2026-10-02 |
| FR-LUDO-04 | Into the goal (chosen when the game is made): with the exact roll, bouncing back what is left over, or any roll that reaches it. | Must | Owner 2026-10-02 |
| FR-LUDO-05 | A 6 gives another roll. With "three tries" (default on), a player with no piece on the ring may roll up to three times. No possible move: the turn passes; exactly one possible move is made at once. | Must | Owner 2026-10-02 |
| FR-LUDO-06 | The first player with all four pieces in the goal wins; the game ends. | Must | Owner 2026-10-02 |
| FR-LUDO-07 | Computer players on Easy (any move), Medium and Hard (Hard wins about 55 % against Medium); they play in the same request as the person before them. | Must | Owner 2026-10-02 |
| FR-LUDO-08 | Against the computer: the player chooses colour, 2–4 players on the board, the level and the rules, and the game starts at once. Two players sit opposite each other. | Must | Owner 2026-10-02 |
| FR-LUDO-09 | With friends: the starter gets one invite link (30 minutes) that up to three friends use; each friend has a seat and presses Ready; everyone sees the lobby update. | Must | Owner 2026-10-02 |
| FR-LUDO-10 | Only the starter can start, and only when everyone who joined is ready; seats nobody took are played by the computer. A started game takes no more joiners. | Must | Owner 2026-10-02 |
| FR-LUDO-11 | The page shows the others' turns one by one (their roll on the die, the piece hopping square by square, a knocked-out piece flying home), turns the board so the player's nest is at the bottom left, and lets the player choose among the glowing pieces (mouse, touch, keyboard). | Should | Owner 2026-10-02 |
| FR-LUDO-12 | Leaving: in the lobby a friend frees the seat and the starter calls the game off; during the game the computer plays the seat on, and with no people left the game ends. When the player to move has done nothing for 90 seconds, the others may let the computer play for them. | Should | Owner 2026-10-02 |
| FR-LUDO-13 | Anonymous like the other games: optional names, no login or cookies, the token after `#` (only its hash stored, inside the seats), no Discord ids, deleted 30 days after the last activity. | Must | REG-GDPR |
| FR-LUDO-14 | Blocks (chosen when the game is made): two or more pieces of one colour on a ring square make a block; other colours may pass it, but a piece that lands on it (also coming out of the nest) goes back to its own nest and the block stays. Any number of a colour's pieces may share a square. Medium and Hard never land on a block. | Should | Owner 2026-10-02 (corrected the same day: passing allowed, no limit of two) |
| FR-LUDO-15 | Safe start square (chosen when the game is made): a piece on its own start square cannot be knocked out; a piece of another colour that lands there goes back to its own nest. Medium and Hard never walk onto one. | Should | Owner 2026-10-02 |
| FR-LUDO-16 | Fia med hutt (chosen when the game is made, marked as a drinking game for adults, any drink works): whoever is knocked out sees a SKÅL! splash and must press "Skål! I took my sup"; nobody can roll until then (`drink_first`). Computer players take theirs at once; the others see who they wait for, and after 90 s may let the computer play for that player. | Could | Owner 2026-10-02 |
| FR-LUDO-17 | The dice roll visibly: a roll lasts about 1.5 s and the die turns about two times or more; the cup waits beside the dice table, not on it (not shown when rolling with the button). | Should | Owner 2026-10-02 |
| FR-LUDO-18 | The new-game form starts with the player's last choices (colour, players, computer level, name, every rule): for a logged-in member they are kept on the profile when a game starts (`hakoo_board_settings.ludo_setup`, in the export and deleted with the account); a visitor's are kept in their own browser only when they tick "Remember my choices on this device", and unticking removes them. | Should | Owner 2026-10-03 |
| FR-LUDO-19 | Kunglig Vikingludo is a second board, chosen when the game is made (Fia med knuff stays the default). Its rules are fixed: out of the nest only on a 6, with three tries when no piece is out; a 6 rolls again; the goal needs the exact roll. The die has 1, 2, crown, 4, feather, 6. **Crown** squares are safe: a piece landing on another colour there goes back to its own nest (except one coming out onto its own start square: that one knocks); the crown face moves a piece on the ring to the nearest crown ahead. **Feather** squares: a piece landing on one jumps on to the next feather; the feather face moves a piece on the ring to the next feather. Neither face takes a piece out of the nest. Fia med hutt can be added; the other rule choices do not apply. | Must | Owner 2026-10-15 |
| FR-LUDO-20 | Vikingludo boards: 2–4 players on the royal board (15×15, a ring of 56 squares, 5 home squares), 5–6 players on the six-armed board (a ring of 84). Every arm has 14 squares: a crown on its start square and 9 squares on, and a feather on the corner square by the middle (5 squares on, between two arms) and 12 squares on. Seats Red, Blue, Yellow, Green, Purple, Orange; two players sit opposite; the board turns so the player's nest is at the bottom left. A game begun on the boards of 13 squares an arm (before 2026-10-16) goes on with every piece on the same square of the new board. | Must | Owner 2026-10-15, 2026-10-16 |
| FR-LUDO-21 | In Vikingludo the pieces show their numbers 1–4, and besides the glowing pieces the player can choose with a button per piece that says what the move does. Pieces of one colour on one square are one somewhat bigger piece with their numbers ("12", "24"). | Should | Owner 2026-10-15, 2026-10-16 |
| FR-LUDO-22 | In Vikingludo the 3D die shows a crown for 3 and a feather for 5, and the page says "rolled a crown" or "rolled a feather". | Should | Owner 2026-10-15 |
| FR-LUDO-23 | The computer players play Vikingludo: Medium and Hard never land on another colour that is safe on a crown, and count a crown as a safe square for their own pieces. | Must | Owner 2026-10-15 |

*2026-10-15, owner decisions:* Kunglig Vikingludo from Mysteries (US-15 there) comes to hakoo as a second board, with both the royal board and the six-armed board for 5–6 players ("Även 5–6 spelare"). The crown replaces the globe and the feather the star of Danish globe ludo; the defaults "the crown face does not take a piece out" and "exact into the goal" are kept.

*2026-10-02, owner decisions:* up to four people (a lobby with Ready, the starter starts, computers fill the rest); the exit rule and the goal rule are chosen when a game is made; a 6 gives another roll. "Three tries" was added as the usual Swedish rule and can be turned off. Rewards for Ludo are not decided yet. *Later the same day:* blocks, a safe start square and Fia med hutt as options; longer dice rolls; the cup beside the table.

## FR-YATZY: Yatzy (`games/yatzy.html`, `yatzy.php`, `yatzy-engine.inc.php`, `js/shared/dice.js`)

Scandinavian Yatzy as described on sv.wikipedia.org/wiki/Yatzy. Up to six players, so it is built like Ludo: its own API
(`yatzy.php`) on the board-game API's helpers and the members' settings (FR-GAMES-11, FR-GAMES-13).

| ID | Requirement | Priority | Source |
|---|---|---|---|
| FR-YATZY-01 | A turn is up to three rolls of five dice. The first rolls all five; before the second and third the player keeps any dice (not all five) and the server rolls the others (`hakoo_roll_dice`). The player may stop after any roll; a fourth roll is refused (`no_rolls_left`), and so is writing before the first roll (`roll_first`). | Must | Owner 2026-10-18 |
| FR-YATZY-02 | Ones to sixes score the sum of the dice showing that number. 63 or more in these six rows gives a bonus of 50 (62 gives none). | Must | Owner 2026-10-18 (Wikipedia: the Scandinavian bonus) |
| FR-YATZY-03 | One pair, two pairs, three and four of a kind score the sum of those dice (the highest pair; two pairs of different numbers). Five of a kind counts as a pair, three and four of a kind, but not as two pairs. | Must | Owner 2026-10-18 |
| FR-YATZY-04 | Small straight 1-2-3-4-5: 15; large straight 2-3-4-5-6: 20; full house (three of one number and two of another, not five of a kind): the sum; chance: the sum; Yatzy (five of a kind): 50. The best sheet is 374 points. | Must | Owner 2026-10-18 |
| FR-YATZY-05 | After the turn the player writes the dice in one empty row; dice that do not fit score 0 there (the row is struck). A row is written only once. | Must | Owner 2026-10-18 |
| FR-YATZY-06 | Order of the rows, chosen when the game is made: free (any empty row, the default) or forced (tvång: from ones down to Yatzy). | Should | Owner 2026-10-18 (Wikipedia: tvång) |
| FR-YATZY-07 | The game ends when every player has filled all 15 rows; the highest total wins, and a tie is a shared win. | Must | Owner 2026-10-18 |
| FR-YATZY-08 | Computer players on Easy (keeps the dice it has most of, writes the highest score), Medium (one roll ahead, plain scores) and Hard (every roll that is left, scores against an ordinary game's, the bonus). On their own they average about 171, 216 and 240 points (2026-10-18, 300 games each); a test keeps Hard above Medium above Easy. They play in the same request as the person before them. | Must | Owner 2026-10-18 |
| FR-YATZY-09 | Against the computer: 1–6 players (alone, or with 1–5 computer players), the level and the order; the game starts at once, and who starts is drawn. | Must | Owner 2026-10-18 |
| FR-YATZY-10 | With friends: 2–6 players; the starter gets one invite link (30 minutes) for up to five friends, each presses Ready, everyone sees the lobby update. | Must | Owner 2026-10-18 |
| FR-YATZY-11 | Only the starter can start, and only when everyone who joined is ready; seats nobody took are played by the computer. A started game takes no more joiners. | Must | Owner 2026-10-18 |
| FR-YATZY-12 | The page rolls the 3D dice (button, cup or phone, FR-GAMES-13); the five dice of the turn are shown under the table and the player clicks them to keep them (mouse, touch, keyboard); the score sheet has a column per player and, on the player's turn, shows what each empty row would give; striking a row (0) asks first. The others' rolls are shown on the dice and their scores light up on the sheet. | Should | Owner 2026-10-18 |
| FR-YATZY-13 | Leaving: in the lobby a friend frees the seat and the starter calls the game off; during the game the computer plays the seat on, and with no people left the game ends. When the player to move has done nothing for 90 seconds, the others may let the computer play for them. | Should | Owner 2026-10-18 (as Ludo) |
| FR-YATZY-14 | The new-game form starts with the player's last choices (players, computer level, name, order): for a logged-in member they are kept with the account when a game starts (`hakoo_board_settings.yatzy_setup`, in the export and deleted with the account) and come back on every device (FR-GAMES-20); a visitor's are kept in their own browser only after ticking "Remember my choices on this device". | Should | Owner 2026-10-18 (rule 9) |
| FR-YATZY-15 | Anonymous like the other games: optional names, no login or cookies needed, the token after `#` (only its hash stored, inside the seats), no Discord ids, deleted 30 days after the last activity. Yatzy is on the 🎲 Dice games shelf of HAKOO Games and speaks every language of the site. | Must | REG-GDPR, Owner 2026-10-18 |

*2026-10-18, owner request:* "kan vi göra ett yatzy ... och lägga in det under tärningsspel". Chosen as defaults (not asked;
the owner may change them): the Scandinavian rules of the Wikipedia article, 1–6 players like Ludo, computer players on
three levels, free order with forced order (tvång) as a choice. Not built: Maxi Yatzy (six dice), half-forced order
(halvtvång), rewards for Yatzy.

## FR-BACKGAMMON: Backgammon (`games/backgammon.html`, `backgammon.php`, `backgammon-engine.inc.php`, `js/shared/dice.js`)

Backgammon has two seats, so it runs on the shared board-game API (invites, rewards, saved games, crowns, the move
list); a turn is two moves to it, `{roll: true}` and `{play: [[from, to], ...]}`.

| ID | Requirement | Priority | Source |
|---|---|---|---|
| FR-BACKGAMMON-01 | Standard board: 15 checkers each on the 24, 13, 8 and 6 points; each player sees the board from their own side (home board bottom right) and counts the points in their own numbering. The opening roll is one die each, never a double; the higher one moves first and plays both. | Must | Owner 2026-10-03 |
| FR-BACKGAMMON-02 | A checker may not land on a point held by two or more of the other player's checkers; landing on a single one (a blot) hits it to the bar, and a checker on the bar must come in on the other player's home board before any other checker moves. | Must | Owner 2026-10-03 |
| FR-BACKGAMMON-03 | Both dice must be played if possible, a double four times; if only one die can be played, the higher one if possible. With no legal move the turn passes at once. | Must | Owner 2026-10-03 |
| FR-BACKGAMMON-04 | Bearing off starts when all fifteen checkers are in the home board: with the exact die, or a higher die from the highest point that holds a checker. | Must | Owner 2026-10-03 |
| FR-BACKGAMMON-05 | The first to bear off all fifteen wins a single game (1 point), a gammon (2: the loser has borne off none) or a backgammon (3: and still has a checker on the bar or in the winner's home board). No doubling cube in version 1. | Must | Owner 2026-10-03 |
| FR-BACKGAMMON-06 | The computer on Easy (any legal play), Medium (the best position by a measure of points, primes, blots in reach and the race) and Hard (also trapped back checkers, and looks at all 21 answers; wins about 60 % against Medium). It plays in the same request as the player before it. | Must | Owner 2026-10-03 |
| FR-BACKGAMMON-07 | The server rolls every die (`hakoo_roll_dice`), only for the player to move and only when no roll is waiting (`rolled_already`); it checks every play (`roll_first`, `use_all_dice`, `too_many_steps`, `illegal_play`). | Must | Owner 2026-10-03 |
| FR-BACKGAMMON-08 | Like the other two-player games: against a friend with an invite link, resign and end, looking back through the turns, rewards for a logged-in member (Easy: checker colours, Medium: 3D checkers, Hard: a crown), saved games; anonymous, no cookies, deleted 30 days after the last activity. | Must | Owner 2026-10-03 |
| FR-BACKGAMMON-09 | The page rolls the 3D dice from the cup, with the button or by shaking the phone (the dice setting under ⚙, shared with Ludo and the profile), shows the other player's roll (in the hint, and as big dice beside the board; their player box is lit meanwhile) and each checker moving, and lets the player move checker by checker (only checkers that can lead to a legal play glow; click twice for the higher die; Undo; Done). Without animation (less motion) the other player's last turn is written out beside the board. The dice table with the cup is the player's own: it shows only on their turn, once the other player's turn has been shown; while the other player rolls and moves it is hidden and the turn line says "… is playing" (owner 2026-10-07: the cup never shows without working). | Should | Owner 2026-10-03 |

*2026-10-03, owner:* "känns som att vi skulle kunna bygga backgammon nu" — single games first; the doubling cube and
matches to N points can come later (dockuments/games-wishlist.md).

## FR-CHECKERS: Checkers / draughts (`games/checkers.html`, `checkers.php`, `checkers-engine.inc.php`)

Checkers has two seats and no chance, so it runs on the shared board-game API; a sequence of jumps is one move to it,
`{path: [[row, col], ...]}`.

| ID | Requirement | Priority | Source |
|---|---|---|---|
| FR-CHECKERS-01 | 8×8 board, 12 men each on the dark squares of the three nearest rows (a1 dark). The rules are chosen when a game is made: **English checkers** (black moves first) or **flying kings**, the international rules on 8×8 (white moves first). The player sees their own side at the bottom. | Must | Owner 2026-10-04 |
| FR-CHECKERS-02 | A man moves one square diagonally forwards. Taking is compulsory: jump an opponent's piece next to it onto the empty square behind; a sequence of jumps is one move and must be completed (`must_take`, `must_continue`); jumped pieces stay until the move ends and cannot be jumped twice. | Must | Owner 2026-10-04 |
| FR-CHECKERS-03 | English: men take forwards only and any sequence may be chosen. Flying kings: men also take backwards and the sequence that takes the most pieces must be chosen (`must_take_most`). | Must | Owner 2026-10-04 |
| FR-CHECKERS-04 | A man that ends its move on the far row becomes a king. English: a king moves and takes one square in any direction, and a man crowned during a jump stops there. Flying kings: a king moves any distance and takes a piece at any distance, landing on any empty square behind it; a man that passes the far row during a jump goes on as a man. | Must | Owner 2026-10-04 |
| FR-CHECKERS-05 | A player who cannot move (no pieces, or all blocked) loses (`no_pieces`, `no_moves`: wins). A draw: the same position for the third time (`repetition`), or 40 moves each with no piece taken and no man moved (`draw`). | Must | Owner 2026-10-04 |
| FR-CHECKERS-06 | The computer on Easy (often any move, otherwise its own move and the forced takes after it), Medium (six moves ahead) and Hard (deeper until 2 seconds are used; beats Medium); it answers in the same request, every level within 3 seconds. | Must | Owner 2026-10-04 |
| FR-CHECKERS-07 | Like the other two-player games: against a friend with an invite link, resign and end, looking back through the moves (from–to, jumps written a×b×c), saved games; anonymous, no cookies, deleted 30 days after the last activity. | Must | Owner 2026-10-04 |
| FR-CHECKERS-08 | Rewards for a logged-in member (Easy: piece colours, Medium: 3D pieces and turning the board, Hard: a crown); the board for every member; both rule sets count. | Should | Owner 2026-10-04 |
| FR-CHECKERS-09 | The page: click a piece, then the dots, one jump at a time or just the last square; when taking is compulsory the pieces that can take are marked and the turn line says so; the other player's move is shown jump by jump with the taken pieces fading at the move speed. | Should | Owner 2026-10-04 |

*2026-10-04, owner decisions:* both rule sets in version 1, chosen per game (like Fog of War in Chess): English checkers
and flying kings (the international rules on 8×8). 10×10 can come later as a third choice.

## FR-MORRIS: Nine men's morris / kvarnspel (`games/morris.html`, `morris.php`, `morris-engine.inc.php`)

Morris has two seats and no chance, so it runs on the shared board-game API; a turn is one move to it: `{to}` places a
stone, `{from, to}` moves one, and `{take}` is part of the same turn when it closes a mill.

| ID | Requirement | Priority | Source |
|---|---|---|---|
| FR-MORRIS-01 | The board of 24 points on three squares joined in the middle of each side (16 mills, points named a1–g7); nine stones each; white moves first. Flying is chosen when a game is made (on by default). | Must | Owner 2026-10-04 |
| FR-MORRIS-02 | First the players place their stones one by one on empty points (`occupied`, `place_first`); when all eighteen are placed, a stone moves along a line to an empty neighbour (`not_adjacent`, `no_stone`, `not_your_stone`). | Must | Owner 2026-10-04 |
| FR-MORRIS-03 | A step that closes a mill (three in a row on one line, also by moving out and back in) must take one of the other player's stones (`must_take`), one that is not in a mill unless all are (`in_mill`, `not_their_stone`); no take without a mill (`no_mill`). | Must | Owner 2026-10-04 |
| FR-MORRIS-04 | With flying, a player down to three stones may move a stone to any empty point. | Must | Owner 2026-10-04 |
| FR-MORRIS-05 | A player with fewer than three stones left (on the board and in hand) or with no move loses (`stones`, `blocked`: wins). A draw: the same position for the third time (`repetition`), or 50 turns in a row after all stones are placed with no stone taken (`draw`). | Must | Owner 2026-10-04 |
| FR-MORRIS-06 | The computer on Easy (often any turn), Medium (four turns ahead) and Hard (deeper until 2 seconds are used; beats Medium); it closes a mill when it can and answers in the same request, every level within 3 seconds. | Must | Owner 2026-10-04 |
| FR-MORRIS-07 | Like the other two-player games: against a friend with an invite link, resign and end, looking back through the turns (d6, d6–d5, ×a1 for a stone taken), saved games; anonymous, no cookies, deleted 30 days after the last activity. | Must | Owner 2026-10-04 |
| FR-MORRIS-08 | Rewards for a logged-in member (Easy: stone colours, Medium: 3D stones and turning the board, Hard: a crown); the board for every member; with and without flying count. | Should | Owner 2026-10-04 |
| FR-MORRIS-09 | The page: click a point to place, or a stone and then a point to move; after a mill the stones that may be taken are marked and the step can be undone; the stones still to place are shown under the names; stones in a mill get a ring. | Should | Owner 2026-10-04 |

*2026-10-04, owner decisions:* flying is a choice per game, on by default.

## FR-GAMES-CH: challenges between members (`board-game-api.inc.php`, `hakoo-discord.inc.php`, `hakoo_challenges`, the bot's `/challenge`)

*2026-10-04, owner:* "utmana någon på hakoo servern" — from Discord and from the game page; the bot posts it in a channel,
locked to the challenged member; open for 24 hours.

| ID | Requirement | Priority | Source |
|---|---|---|---|
| FR-GAMES-CH-01 | On every board game page that uses the shared page (Gomoku, Chess, Othello, Hnefatafl, Stratego, Backgammon and later ones) a logged-in member can challenge another member of the HAKOO Discord server ("⚔️ Challenge a Member": pick a name, a side and the game's options). Not oneself, not a bot, not someone who is not on the server (`challenge_self`, `challenge_bot`, `not_a_member`); not logged in: `not_logged_in`. | Should | US-34 |
| FR-GAMES-CH-02 | Only the challenged member, logged in with Discord, can take the other seat (`not_for_you` for anyone else); there is no invite link anyone could use. Their Discord name is used on the board (made into 2–20 letters, digits, `-`, `_`). | Must | US-34 |
| FR-GAMES-CH-03 | The bot posts the challenge in Discord, pinging only the challenged member, with a button to the game page (`?challenge=<game>`): in the channel `DISCORD_CHALLENGE_CHANNEL_ID`, else the first text channel named like "challenge"/"utmaning", "board"/"brädspel", "games"/"spel", else as a direct message. A challenge is open for 24 hours; if the post fails, the challenger gets the link to send themselves. | Should | US-34 |
| FR-GAMES-CH-04 | The same link brings either member back to their seat on any device once they log in (a new seat link; the older one stops working). Not logged in, the page asks them to log in and comes back to the challenge. | Should | US-34 |
| FR-GAMES-CH-05 | The post follows the game: when it is accepted (and the challenger is pinged in a reply), the result, a withdrawn challenge, one that expired. | Should | US-34 |
| FR-GAMES-CH-06 | `/challenge member game [colour]` in Discord does the same for the member who uses it: the bot asks the website with a shared secret (`<BOT_SECRET>`, refused otherwise: `bad_bot`) and answers only the challenger with the link to their seat. Challenges are in "Download my data" (sent ones without the other member's id, received ones under `challenges_received`) and are deleted with either member and with the game (30 days). | Should | US-34, REG-GDPR-15, REG-GDPR-17 |
| FR-GAMES-CH-07 | The challenger's waiting page names who it is for, says the bot has posted it, counts down the 24 hours, and turns into the game by itself when it is accepted; "Withdraw the challenge" withdraws it. | Should | US-34 |

## FR-NONOGRAM: Nonogram, the first brain puzzle (`games/nonogram.html`, `nonogram.php`, `nonogram-engine.inc.php`)

One player, no seats: its own small API, with the look and rewards of the board games (board-game-api.inc.php helpers).

| ID | Requirement | Priority | Source |
|---|---|---|---|
| FR-NONOGRAM-01 | A square grid with a clue per row and column: the runs of filled squares in order. Easy 10×10, Medium 15×15, Hard 20×20. | Must | Owner 2026-10-05 |
| FR-NONOGRAM-02 | Every puzzle is made by our own generator from a number (a random picture of blobs, often mirrored; 30–70 % filled) and kept only when it can be solved line by line without guessing, so it has exactly one solution. No puzzle is copied from another site. | Must | Owner 2026-10-05 |
| FR-NONOGRAM-03 | The page gets the clues, never the solution; the same number gives the same puzzle again (practice: a new number each time). | Must | Owner 2026-10-05 |
| FR-NONOGRAM-04 | The server checks a grid against the clues (`solved` true or false). | Must | Owner 2026-10-05 |
| FR-NONOGRAM-05 | The puzzle of the day: one per level, the same for every logged-in member, changing at midnight Swedish time; guests get another puzzle of the day (also the same for all of them, without times), and the members' puzzle is checked only for a logged-in member (`login_needed`), so nobody can solve it logged out first (owner 2026-10-07). For a logged-in member the clock starts when they first open it (opening again does not restart it) and stops at the first right answer, measured by the server; a puzzle of another day is refused (`day_over`). Not logged in, nothing is stored. Records are kept 400 days. | Must | Owner 2026-10-05 |
| FR-NONOGRAM-06 | Logged-in members see the day's list per level (Discord name and time, fastest first, the member's own line marked) and their streak (days in a row with a puzzle of the day solved). The first puzzle solved on a level unlocks the reward, as in the board games: Easy square colours, Medium 3D squares, Hard a crown on the avatar; the look is chosen on the profile. | Should | Owner 2026-10-05 |
| FR-NONOGRAM-07 | The page: click to fill, right click or the ✕ tool to cross, drag along a row or column to do a stretch, undo, start over; a clue fades when its line is right; the time runs; when all clues are right the grid is sent and the picture shows. Keyboard: arrows, Space, X. | Must | Owner 2026-10-05 |
| FR-NONOGRAM-08 | Nothing is stored in the browser: the puzzle and the squares so far are in the address after '#', so a reload keeps them. A big board on a phone starts with squares big enough to hit and is wider than its box: two fingers zoom it (out until all of it shows, in up to 4×) and move it inside the box, sideways and up and down (FR-GAMES-19), and a fill that turns into a pinch is taken back; ⤢ Fit shows the whole board. With a mouse the box scrolls sideways. | Must | FR-GAMES-02 |

*2026-10-11, owner decisions:* your own colour on the viking is a square, so it differs from the round colours everyone has, with a line saying how to use it (FR-AVATAR-12); every profile section explains itself in a folded "How it works" (FR-USER-06); a founder does everything an admin does and gives and takes Admin (FR-ADMIN-10).

*2026-10-05, owner decisions:* Nonogram first of the brain puzzles; the puzzle of the day with a list of times and a
streak; rewards like the board games.

## FR-PUZZLE: what the brain puzzles share (`puzzle-daily.inc.php`, `js/shared/puzzle-page.js`, `css/games/puzzle.css`)

Sudoku and Owldoku are built like Nonogram; the shared parts live in one place (Nonogram keeps its own copy).

| ID | Requirement | Priority | Source |
|---|---|---|---|
| FR-PUZZLE-01 | The puzzle of the day as in FR-NONOGRAM-05 and -06: one per level, the same for every logged-in member, changing at midnight Swedish time; guests get puzzles of their own without times, and the members' puzzles answer only a logged-in member (401 `login_needed`), so nobody can try one out logged out first; the address keeps the puzzle's number, so the squares of a guest puzzle never land on the members' one; a member's time is measured by the server from the first opening to the first right answer and counts once; the day's list (up to 20 names, fastest first), the streak, the first puzzle solved per level unlocks the reward. Records are kept 400 days. A slow puzzle of the day is made once and kept as a file in the server's temp folder for three days. | Must | Owner 2026-10-07 |
| FR-PUZZLE-02 | The page: a start page with the three puzzles of the day (✓ and the time when solved, how many solved today, 💔 when the hearts ran out) and practice on three levels; the clock; today's list beside the puzzle (below it on a phone); the result dialog; the rules page and the rules in short. Nothing is stored in the browser: the puzzle and the squares so far are in the address after '#'. On a phone the menu bar is ☰ and 👤▾ (FR-GAMES-18) and the board uses the width of the screen with squares big enough for a finger. | Must | Owner 2026-10-07 |
| FR-PUZZLE-03 | Sudoku, Owldoku and Nonogram: a logged-in member's squares are kept on the server (today's puzzle of the day and one practice puzzle per game and level), so they can leave and go on later, also on another device; the server's copy wins over the address. The clock goes on while they are away: the puzzle of the day from when it was opened, a practice puzzle from when it was started. Practice without a puzzle number opens their unsolved one (the start page shows it in progress with its time); "New puzzle" always a new one; Start over keeps an empty grid (and starts a practice clock again); a solved practice puzzle is not kept. Guests keep the address only. | Must | Owner 2026-10-17 |

## FR-SUDOKU: Sudoku (`games/sudoku.html`, `sudoku.php`, `sudoku-engine.inc.php`)

| ID | Requirement | Priority | Source |
|---|---|---|---|
| FR-SUDOKU-01 | A 9 × 9 grid: every row, column and 3 × 3 box has the digits 1 to 9 once. The levels are the tricks a puzzle needs: Easy only singles (and at least 36 digits given), Medium also locked digits and pairs, Hard also triples, X-wing and Y-wing. | Must | Owner 2026-10-07 |
| FR-SUDOKU-02 | Every puzzle is made by our own generator from a number: a random full grid, then digits taken away in pairs mirrored through the centre as long as a solver that never guesses still solves it with the level's tricks; a Medium or Hard puzzle that the easier tricks solve is thrown away. So every puzzle has one solution. | Must | Owner 2026-10-07 |
| FR-SUDOKU-03 | The page gets the digits given, never the solution; the same number gives the same puzzle again. | Must | Owner 2026-10-07 |
| FR-SUDOKU-04 | The server checks a full grid against the rules and the digits given (`solved` true or false). | Must | Owner 2026-10-07 |
| FR-SUDOKU-05 | The puzzle of the day, the list and the streak as in FR-PUZZLE-01. | Must | Owner 2026-10-07 |
| FR-SUDOKU-06 | Rewards: Easy the colours of your digits and of the highlight, Medium 3D squares, Hard a crown on the avatar; the look (and the board) is chosen on the profile. | Should | Owner 2026-10-07 |
| FR-SUDOKU-07 | The page: pick a square, then a digit (buttons, or the keyboard); ✎ Notes writes small digits, and a digit written takes its note out of its row, column and box; ⌫ Erase; undo; start over. The chosen square's row, column and box and the same digit light up. Two equal digits in a row, column or box turn red; nothing else says whether a digit is right (owner 2026-10-07: only clashes). A full grid without a clash is sent. Keyboard: arrows, 1–9, Shift + digit for a note, Delete, N. | Must | Owner 2026-10-07 |
| FR-SUDOKU-08 | The digit buttons show how many of each digit are left; beside the grid on a wide screen, in one row under it on a phone (at least 44 px high), with Notes and Erase side by side under them. | Should | Owner 2026-10-07 |

## FR-OWLDOKU: Owldoku, the HAKOO owl puzzle (`games/owldoku.html`, `owldoku.php`, `owldoku-engine.inc.php`)

Our own take on Star Battle with one star (like Meowdoku or Queens), with the HAKOO logo as the owl.

| ID | Requirement | Priority | Source |
|---|---|---|---|
| FR-OWLDOKU-01 | A square grid split into as many colour regions as rows: one owl in every row, column and region, and no two owls touching, not even at a corner. Easy 6 × 6, Medium 8 × 8, Hard 10 × 10. | Must | Owner 2026-10-07 |
| FR-OWLDOKU-02 | Every puzzle is made by our own generator from a number: the owls first, then regions grown from them at random and mended until the owls are the only solution; kept only when a solver that never guesses finds them, and only when the hardest trick it needed is the level's (Easy: a line or region with one square left; Medium: a region inside one line, a square that would block a whole region; Hard: k regions inside k lines). Every region is in one piece. | Must | Owner 2026-10-07 |
| FR-OWLDOKU-03 | The page gets the regions, never the owls; the same number gives the same puzzle again. | Must | Owner 2026-10-07 |
| FR-OWLDOKU-04 | Every owl placed is checked by the server at once (`place`: owl or not). A wrong owl costs one of three hearts and stays as a red cross. An owl that breaks a rule the page can already see (its row, column or region has an owl, or it touches one) is refused by the page and costs nothing. When all owls stand, they are sent (`solve`). | Must | Owner 2026-10-07 (three hearts) |
| FR-OWLDOKU-05 | For a member's puzzle of the day the server counts the hearts (`mistakes`, at most 3; not after it is solved), so a fresh start does not give them back; with no hearts left the server answers no more owls on that puzzle until the next day (409 `no_hearts`: a second account cannot ask its way to the owls either), and the day's time and the reward do not count (`lost`). Otherwise the puzzle of the day as in FR-PUZZLE-01. | Must | Owner 2026-10-07 |
| FR-OWLDOKU-06 | Rewards: Easy the colours of the owl and the crosses, Medium 3D regions, Hard a crown on the avatar; the region colours (board) are free. Chosen on the profile. | Should | Owner 2026-10-07 |
| FR-OWLDOKU-07 | The page: a tap crosses a square (a second tap later clears it), two quick taps (or a double click, right click, the Owl tool, O or Enter) place an owl; a drag crosses every square it passes (or clears them, when it starts on a cross); squares an owl blocks get a dot; undo takes back crosses only. Out of hearts: start over with three new hearts, or keep going for fun (no reward); on a member's puzzle of the day it is over for the day, and the page offers a practice puzzle. | Must | Owner 2026-10-07 (rules from the owner) |
| FR-OWLDOKU-08 | On a phone the board fits the screen (10 × 10: squares of at least 30 px), a finger on the board never scrolls the page (`touch-action: none`), and the Cross and Owl tools are big buttons. | Must | Owner 2026-10-07 (think of the phone) |

*2026-10-07, owner decisions:* members and guests get different puzzles of the day, so the members' puzzle cannot be
worked out logged out ("en daglig för inloggade och en för anonyma så går det inte att fuska"). Sudoku and Owldoku as daily and practice puzzles; Owldoku uses the HAKOO logo and the
rules of Meowdoku with three hearts; Sudoku shows only clashes; rewards and profile looks like Nonogram.

## FR-RUNBORG: Runborg, the Viking card duel (`games/runborg.html`, `runborg.php`, `runborg-engine.inc.php`)

Rules and cards: [dockuments/runborg-design.md](../../dockuments/runborg-design.md). Owner 2026-10-08.

| ID | Requirement | Priority | Source |
|---|---|---|---|
| FR-RUNBORG-01 | Every board is a saga with its own start (Fjord the classic; Winter hall 15, palisade 10, timber 10; Northern Lights rune stone 3, runes 8, mead hall 1; Autumn Forest forest 3, timber 10, palisade 2; Winter Dusk hall 25, palisade 10, 8 of each resource) and its own goal (Fjord hall 50 or 120 of one resource; Winter hall 40 and palisade 20 at once; Northern Lights hall 50 or 100 runes; Autumn Forest hall 50 or 150 timber; Winter Dusk hall 60). The enemy's hall at 0 wins on every board. The page shows the goal, how far each side is and the start ("How to win"); another board or opponent is a new game. | Must | US-41 |
| FR-RUNBORG-02 | A turn: the generators give, then one card is paid for and played, or thrown away; the hand is filled back to six; a card marked "again" gives one more. Damage takes the palisade first; cards "to the hall" go past it. 36 cards, two of each in the deck. | Must | US-41 |
| FR-RUNBORG-03 | Both players reaching a goal at once: the one whose turn it is wins. | Must | US-41 |
| FR-RUNBORG-04 | Five opponents, each harder to beat: the Farmer (random cards), the Viking, the Shieldmaiden, the Jarl and the King (the best card now, with less chance the higher they are, and a bigger estate from the start: more warriors, a higher palisade, more land, a bigger hall). The computer never looks at the player's hand or the deck. Self-play checks that each beats the one below (`qa/tools/runborg-balance.php`). | Must | US-41 |
| FR-RUNBORG-05 | The game runs on the server: the page gets its own hand and only the number of the opponent's cards and of the deck; every card played is checked; the opponent's turn comes back as steps that the page shows on the field. Playing needs no login; the game is in the address after '#' (nothing in the browser) and is deleted 30 days after the last move. The old address `games/runborg-lab.html` leads to `games/runborg.html`. | Must | US-41, FR-GAMES-02 |
| FR-RUNBORG-06 | A logged-in member's wins are kept per board and opponent (first win and how many) and shown in the game ("Your wins on Fjord") and on the profile (a table of boards and opponents). | Should | US-41 |
| FR-RUNBORG-07 | Every opponent a member has beaten (on any board) is a title: Farmer, Viking, Shieldmaiden, Jarl, King. A first win against an opponent says so. | Should | US-41 |
| FR-RUNBORG-08 | On the profile (🃏 Runborg) the member picks one title they have won, or none; the server refuses one not won. It shows after their name in the login corner on every page, on the puzzle-of-the-day lists and at the Runborg table. | Should | US-41 |
| FR-RUNBORG-09 | A member's Runborg games, wins and title are personal data: in "Download my data" (the games without their moves; the games they joined as the second player too) and removed with "Delete all my data". | Must | REG-GDPR-15, REG-GDPR-17 |
| FR-RUNBORG-10 | Two players: the host picks "A friend" and gets an invite link (it holds 7 days, one seat); the friend opens it and the saga starts for both. No login is needed. | Must | US-43 |
| FR-RUNBORG-11 | Between two players both start the same (the board's start, no estate); who starts is drawn by lot, and the one who goes second gets 1 more of each resource. | Must | US-43 |
| FR-RUNBORG-12 | The turns go in order (`not_your_turn` otherwise); each page sees itself as "you" with its own hand only. The waiting page asks the server now and then and shows the other player's turn on the field, step by step. | Must | US-43 |
| FR-RUNBORG-13 | A member can challenge another member of the HAKOO Discord server (no bots, not themselves): the bot posts it with a link; only the challenged member, logged in, takes the seat (within 24 hours), and either member gets their seat back on another device with the link. The post follows the game (on, who won). | Should | US-43 |
| FR-RUNBORG-14 | Either player can give up: the other wins. A win between players gives no title (titles are for the computer's opponents). | Should | US-43 |
| FR-RUNBORG-15 | When a saga ends, a box over the field says it: Victory (gold) or Defeat (red), why, and a title won the first time (or that a login keeps wins); "New game" starts one, "Look at the table" (or Esc) closes it. It also shows when a finished saga is opened again. | Should | US-41, US-43 |
| FR-RUNBORG-16 | What a raid takes from the enemy is the raider's (Timber Raid 4 timber; Loki's Trick 4 timber and 4 warriors; Plunder 4 timber, 4 runes and 4 warriors): as much as the enemy has, up to the number, so nothing is lost. Players' idea (2026-10-11); self-play still has every opponent beat the one below. | Should | US-41 |
| FR-RUNBORG-17 | Seeress's Vision: the player picks the card to throw away (the page asks; a right-click or Esc takes it back), then draws one and plays again. The server checks the pick; the computer throws away its card furthest from being paid for. Players' idea (2026-10-11). | Should | US-41 |

## FR-BLOCKDOKU: Blockdoku, a high-score game (`games/blockdoku.html`, `blockdoku.php`, `blockdoku-engine.inc.php`, `blockdoku-day.inc.php`)

Owner request 2026-10-14 ("blockdoku / block crush som ett dagligt spel"): no levels; a high score; play until none of
the random blocks fits; the blocks are the Tetris pieces; 9 points per row, column or box, a bonus for several at once
and for clearing on moves in a row; a list of today's best (Swedish day 00:00-23:59) with names only for logged-in
members, guests as Anonymous; three colours for the day's top three.

| ID | Requirement | Priority | Source |
|---|---|---|---|
| FR-BLOCKDOKU-01 | A 9 × 9 board; three blocks at a time, at random from the seven Tetris pieces in every turn (19 shapes, four squares each); placed in any order where they fit, never turned; three new ones when all three are placed. | Must | US-52 |
| FR-BLOCKDOKU-02 | A block fits only on empty squares inside the board. A full row, column or 3 × 3 box is cleared; one block can fill several, and each counts. | Must | US-52 |
| FR-BLOCKDOKU-03 | Points for a block = 9 × n × (n + s − 1): n rows, columns and boxes it cleared, s the moves in a row that cleared, this one too. A block that clears nothing ends the run; placing alone gives no points. | Must | US-52 |
| FR-BLOCKDOKU-04 | The game is over when none of the blocks left in hand fits anywhere, or at midnight Swedish time (owner 2026-10-17, "ny dag är ny dag": its score stays on that day's list); an over game takes no more moves (a game of a day that is over: `day_over`). | Must | US-52 |
| FR-BLOCKDOKU-05 | The server deals the blocks and makes every move (the page never knows the next blocks; a score is always real). A game is played with its token (in the page's address after '#'; only its hash is stored); without it, or with another, it is refused. A reload comes back to the game. | Must | US-52, FR-GAMES-01 |
| FR-BLOCKDOKU-06 | Today's high scores (00:00-23:59 Swedish time): each member's best game once and every guest game with points, highest first, on equal points the one that got there first; the top 20 and the member's own place. Names (and the Runborg title) only for a logged-in viewer; guests are Anonymous for everyone. Anyone can play as often as they like. | Must | US-52 |
| FR-BLOCKDOKU-07 | When a day is over its first three are kept (hakoo_blockdoku_podium); a member there wins a colour for their viking: 1st magenta, 2nd mint, 3rd indigo (FR-AVATAR-12); a guest in the three takes the place but wins nothing. Yesterday's three are shown on the start page. | Should | US-52 |
| FR-BLOCKDOKU-08 | Blockdoku can be chosen in the reminders (FR-REMIND); it counts as done when the member has made a move in a game of today. A member's games (without the token) and places are in "Download my data" and go with "Delete all my data"; games are deleted 30 days after the last move. | Must | US-52, REG-GDPR-15, REG-GDPR-17 |
| FR-BLOCKDOKU-09 | Playing: drag a block (with a finger it floats above the finger; where it would go and what it would clear light up), or tap a block and then a square, or the keyboard (1-3, arrows, Enter, Esc). The page and the rules page fit the window at every size and text size, and speak all our languages. | Should | US-52, FR-I18N |
| FR-BLOCKDOKU-10 | A logged-in member's game also opens with their login (not only the token in the address), and the start page offers "Continue" for their latest game of today that is not over, so they can leave and go on later on another device; a game lasts until no block fits or midnight (FR-BLOCKDOKU-04). Each square keeps its block's colour. Another member or a guest without the token cannot open it. | Must | Owner 2026-10-17 |

## FR-BERSERK: Path of Berserk, an arcade fight (`games/path-of-berserk.html`, `js/games/berserk/`, `berserk.php`, `berserk.inc.php`)

Owner request 2026-10-17 ("en vikinga variant på streets of Rage ... path of berserk"): a side-scrolling beat 'em up in
the spirit of Streets of Rage (the genre only: no names, figures, levels or music of Sega's), with the member's own
viking. Decisions 2026-10-17: a new **Arcade** category on HAKOO Games; keyboard and game controller (and touch);
Mysteries later with the phone as the controller; start with the viking we have, and build it so that harder, more
detailed graphics can replace it later; high-score lists per month and for the year; rewards for the viking
(a sword, a battle axe, colours that vary, and fly agarics around the viking for the year's best). Built in steps:
step 1 the game, the lists and the category (01-11); step 2 the rewards (12); step 3 Mysteries (13).

| ID | Requirement | Priority | Source |
|---|---|---|---|
| FR-BERSERK-01 | HAKOO Games has a shelf **🕹️ Arcade** (`#arcade`) in its menu, after Card games; Path of Berserk is on it and in All games. | Must | Owner 2026-10-17 |
| FR-BERSERK-02 | The game: the hero walks to the right through a level of four parts (beach, village, forest, the Jarl's hall). In each part enemies come in waves; the way on stays closed until the part's last wave is beaten, then "GO →" shows. The last part is the boss, Jarl Ulfr, who calls two more men when he is down to half his health. Beating him wins the level. | Must | Owner 2026-10-17 |
| FR-BERSERK-03 | The hero is the player's own viking (its saved look, profile → Your viking); a guest, or a member without a saved viking, plays the default viking. The enemies are vikings in their own looks (thug, axeman, shieldman, the Jarl). | Should | Owner 2026-10-17 |
| FR-BERSERK-04 | Moves: walk along the level and in depth (8 directions), a combo of three hits (the third knocks down), a jump and a jump attack (knocks down). Health 100 and 3 lives; a lost life comes back with full health and a moment of safety; losing the last ends the game. A shieldman blocks hits from the front, except the third of a combo and a jump attack. | Must | Owner 2026-10-17 |
| FR-BERSERK-05 | **Berserk:** a meter fills by 8 for each hit that lands (to 100). When full, Berserk can be started: for 6 seconds the hero does double damage, takes none and walks faster; the meter then empties. | Must | Owner 2026-10-17 |
| FR-BERSERK-06 | Barrels and crates break when hit and may hold bread (+30 health), a roast (full health) or an axe (stronger hits: the next 12 hits do 1.5 × damage). | Should | Owner 2026-10-17 |
| FR-BERSERK-07 | Controls: **keyboard** (arrows or WASD to walk; J or Z hit; K or X jump; L or C Berserk; P or Esc pause), **game controller** (any controller the browser knows: stick or d-pad, A jump, X hit, B or Y Berserk, Start pause) and **touch** on phones and tablets (a stick on the left, three buttons on the right). The controls are shown on the start page and in the rules. Hiding the tab pauses the game. | Must | Owner 2026-10-17 |
| FR-BERSERK-08 | **Score**, counted by the server from what the run reports: thug 100, axeman 200, shieldman 300, the Jarl 3000, a barrel or crate 50; for a won level also 1000, + 10 per second under 5 minutes (the server's own clock from the start, pauses included), + 10 per point of health left, + 1000 per life left. | Must | Owner 2026-10-17 |
| FR-BERSERK-09 | A run is started by the server (an id and a token; only the token's hash is kept) and finished once. The server refuses a finish it cannot believe: more enemies, barrels or bosses than the level has, a won level without the Jarl, health above 100 or lives above 3, a won level faster than 60 seconds, a second finish, a wrong token; it keeps no score for those. | Must | NFR-SEC, risk R-17 |
| FR-BERSERK-10 | **High scores** for this month and this year (Swedish time): the best run of each member and every guest run, highest first (on equal scores the earlier); names only for a logged-in viewer, guests as Anonymous (as Blockdoku). When a month or a year is over, its first three members are kept (`hakoo_berserk_podium`) for the rewards, and the page shows last month's and last year's three. | Must | Owner 2026-10-17 |
| FR-BERSERK-11 | The rules of the game do not depend on how it is drawn: `js/games/berserk/engine.js` runs the whole game without graphics (the tests run it so), and the look is a replaceable *skin* (`render-three.js` with the soft vikings today) that can be swapped for more detailed models without changing the rules, the scores or the server. Without WebGL the page says so instead of failing. | Should | Owner 2026-10-17 ("lite hårdare i framtiden och med högre grafik") |
| FR-BERSERK-12 | Step 2, **proposed, not built**: rewards for the viking: a sword for beating the Jarl; a battle axe for beating him without losing a life; each of the month's first three members a colour, drawn for that month from a set of berserk colours; the year's first three members fly agarics around their viking. | Should | Owner 2026-10-17 (proposal to confirm) |
| FR-BERSERK-13 | Step 3, **not built**: in Mysteries the TV shows the game and the players' phones are the controllers. | Could | Owner 2026-10-17 |
| FR-BERSERK-14 | A member's runs (their id and Discord name at the start, the score and times) are in "Download my data" and go with "Delete all my data"; runs are deleted 400 days after they began (the year's list needs them); a guest run carries no name or id. | Must | REG-GDPR-15, REG-GDPR-17 |
| FR-BERSERK-15 | The player can change the keyboard keys (⌨️ Change keys on the start page): two keys for each action (walk left, right, up, down; hit; jump; Berserk; pause), a key pressed for an action is taken from the action that had it (the page says so), Backspace or Delete empties a slot, every action needs a key, and Default keys puts them back. A logged-in member's keys are kept with their account (`hakoo_berserk_profile.controls`) so the game plays the same on every device they log in on, and keys they had saved as a guest in that browser move to the account the first time (owner decision 2026-10-18); a guest's keys are kept in the browser (`hakoo_berserk_keys`) only when they press Save. Default keys removes them either way. The page and the rules show the keys in use. Ctrl, Alt, the Windows/Cmd key and Tab cannot be chosen. | Should | Owner 2026-10-17 ("ändra på vilka tangenter man vill använda till vad") |

## FR-SAVED: saved games (`hakoo_saved_games`, `saved-games.php`, profile → My games)

Owner decision 2026-09-29: keep the first win on each level, and games between two members; latest 100 plus
starred; a deleted member becomes "Deleted player" in the other player's copy.

| ID | Requirement | Priority | Source |
|---|---|---|---|
| FR-SAVED-01 | When a logged-in member beats the computer on a level for the first time (the win that unlocks a reward), that game is saved for them as a first win, in every board game. A win that comes with the computer's move (Othello: the computer puts the last disc) counts too. | Should | US-26 |
| FR-SAVED-02 | A game between two logged-in members is saved for both players when it ends (won, drawn, resigned or ended, with at least one move). If one player is a guest, nothing is kept. | Should | US-26 |
| FR-SAVED-03 | A member can watch their own saved games on the game page (`#saved=<id>`) with the move list and looking back; nobody else can open them, and nothing can be played there.  Pages, scripts and styles are sent with `Cache-Control: no-cache`, so after an upload no browser runs an old page (an old page opened `#saved=` links as a new game). | Should | US-26 |
| FR-SAVED-04 | "My games" on the profile lists a member's saved games in three columns — against members, rewards (first wins), against the computer — each newest first (game, opponent, result, moves, date), with Watch, a star (not on first wins, which are always kept), and delete (only one's own copy). | Should | US-26 |
| FR-SAVED-05 | The latest 100 games against members and, apart from them, the latest 100 games against the computer are kept per member; starred games and first wins are never removed to make room. | Should | US-26 |
| FR-SAVED-06 | Saved games are in "Download my data" (without the other player's id) and "Delete all my data"; in the other player's copy a deleted member's name becomes "Deleted player" and their id is removed. A saved game holds no seat tokens. | Must | REG-GDPR-15, REG-GDPR-17, US-26 |
| FR-SAVED-07 | A saved Fog of War game is shown whole: the fog has lifted once the game is over. | Should | US-26 |
| FR-SAVED-08 | A logged-in member's game against the computer is saved for them when it ends with at least one move (won, lost, drawn, resigned or ended); the game that first wins a level is saved once, as a first win. Until `migration_users_saved_games_computer.sql` has run, nothing breaks: these games are just not saved. | Should | US-26 |

## FR-REMIND: reminders for the puzzles of the day (`reminders.php`, profile → Reminders, the bot's minute tick)

Owner request 2026-10-12: the bot reminds a member of the puzzles of the day, as a direct message or a ping in
#reminders-spams; for all puzzles or one by one, each at its own time; the member must agree to direct messages.

| ID | Requirement | Priority | Source |
|---|---|---|---|
| FR-REMIND-01 | A logged-in member chooses on the profile (⏰ Reminders, between My games and Your viking): Off, one reminder for all the puzzles of the day (Nonogram, Sudoku, Owldoku) at one time, or a reminder for each chosen puzzle at its own time. Times are in the member's time zone (from the browser). | Should | Owner 2026-10-12 |
| FR-REMIND-02 | The member chooses how: a ping in the channel #reminders-spams (config `DISCORD_REMINDER_CHANNEL_ID`, else the channel named like "remind") or a direct message from the bot. A direct message needs a ticked consent ("I agree that the HAKOO bot sends me direct messages"); the server refuses DM without it, keeps when it was given, and choosing the ping or Off takes it back. | Must | Owner 2026-10-12, REG-GDPR |
| FR-REMIND-03 | Once a minute the bot asks hakoo.eu to send the due reminders (`reminders.php?action=tick`, `<BOT_AUTH_HEADER>`). A reminder is sent once a day, from its time, and only names the puzzles the member has not solved today (any level, as for the streak); with all solved, nothing is sent. | Should | Owner 2026-10-12 |
| FR-REMIND-04 | A reminder the bot could not send on time is sent up to 3 hours late, not later. A new or moved reminder whose time has already passed today starts tomorrow. | Should | — |
| FR-REMIND-05 | "Send a test reminder" sends one at once (at most one a minute). When Discord refuses a reminder (the member takes no direct messages, the channel is missing), the profile says so and how to fix it. | Should | — |
| FR-REMIND-06 | Reminders and the consent are in "Download my data" and go with "Delete all my data"; the privacy page describes them. | Must | REG-GDPR-15, REG-GDPR-17 |
| FR-REMIND-07 | The Reminders section fits the window at every text size and screen width. | Should | — |
| FR-REMIND-08 | The member chooses when: only on a day they have a puzzle left to do (the default), or every day. Reminded every day, a reminder on a day with puzzles done names them as done today, and on a day with all done it still comes, with every puzzle to play again. The choice is offered once `migration_users_reminders_always.sql` has run, and is in "Download my data". | Should | Owner 2026-10-16 |

## FR-AUTH: login and session

| ID | Requirement | Priority | Source |
|---|---|---|---|
| FR-AUTH-01 | Members log in with Discord OAuth2 (`identify` scope). A random `state` value in a 10-minute cookie protects the callback. | Must | US-04 |
| FR-AUTH-02 | The session is a `hakoo_auth` cookie (HttpOnly, Secure, 30 days) whose content is signed with HMAC-SHA256. | Must | US-04, NFR-SEC-02 |
| FR-AUTH-03 | Logout deletes the session cookie. | Must | US-04 |
| FR-AUTH-04 | After login and logout, the member is sent to `community.html`, unless FR-AUTH-05 applies. | Should | US-04 |
| FR-AUTH-05 | Login can carry a return page (`?return=`); after login the member is sent there. Only own pages are accepted. | Must | US-13, NFR-SEC-10 |

## FR-USER: members (`hakoo_users`)

| ID | Requirement | Priority | Source |
|---|---|---|---|
| FR-USER-01 | A member row is created the first time the member logs in or saves any data. | Must | architecture decision |
| FR-USER-02 | Each login updates `last_login_at`. | Should | architecture decision |
| FR-USER-03 | Deleting a member row deletes that member's rows in every table linked to it (ON DELETE CASCADE). | Must | US-07 |
| FR-USER-04 | Member data cannot be stored for a Discord id without a member row (foreign key). | Must | US-07 |
| FR-USER-06 | Every section of the profile starts with a folded **❔ How it works** that says what can be set there and how (Accounts, Discord, Appearance, Board games and each game's look, Runborg, My games, Your viking, Who sees your profile, Your data). Owner 2026-10-11. In English, like the rest of the profile (FR-I18N: the profile is not translated yet). | Should | US-30 |
| FR-USER-05 | The profile page (logged in) shows one section at a time, chosen in a menu in the left column: Overview (who you are, and tiles for the other sections), Accounts (WoT, Steam and the Steam games, Warframe), Discord (game roles, loot pings), Board games (the move speed, the rewards per game, and under it one page per game with its look), My games, Your data. The address names the section (`#accounts`, `#games`, `#othello`, …; the game pages' links `#<game>` and `#my-games` keep working; an unknown one shows the overview). On a screen under 900 px the menu is a row at the top that scrolls sideways; the page itself never does. The list of Discord members online is on Community, not on the profile. | Should | US-30 |

## FR-GDPR: data self-service (`mydata.php`)

| ID | Requirement | Priority | Source |
|---|---|---|---|
| FR-GDPR-01 | A logged-in member can download all stored data about them as one JSON file: account, linked accounts, in-game names, builds, loot-code reports, clan access, WoT snapshots and file records. | Must | US-06, REG-GDPR-15, REG-GDPR-20 |
| FR-GDPR-02 | A logged-in member can delete all stored data about them. The database part is all-or-nothing (one transaction). | Must | US-07, REG-GDPR-17 |
| FR-GDPR-03 | Deleting one member never changes other members' data or WoT-only players' data. | Must | US-07 |
| FR-GDPR-04 | Deletion also removes the member's entries in `wot_boxes.json` and `discord_overrides.json`. | Must | US-07 |
| FR-GDPR-05 | The deletion log stores only an HMAC of the Discord id, never the id itself. | Must | REG-GDPR-5 |
| FR-GDPR-06 | After a backup restore, `hakoo_reapply_deletions()` deletes again every member in the deletion log. | Should | US-12 |
| FR-GDPR-07 | Deletion requires POST and the confirmation text `DELETE`; otherwise nothing is deleted. | Must | US-07 |
| FR-GDPR-08 | After deletion, the member is logged out. | Must | US-07 |

## FR-ROSTER: clan roster (`roster.php`)

| ID | Requirement | Priority | Source |
|---|---|---|---|
| FR-ROSTER-01 | Only logged-in members can view or edit the roster (otherwise 401). | Must | US-05 |
| FR-ROSTER-02 | The in-game name is required, trimmed, and 1–64 characters long. | Must | US-05 |
| FR-ROSTER-03 | The note is optional; anything longer than 120 characters is cut to 120. | Should | US-05 |
| FR-ROSTER-04 | Mastery Rank is optional; values below 0 become 0 and above 40 become 40. | Should | US-05 |
| FR-ROSTER-05 | The game must be `warframe`, `wot` or `wows` (not case-sensitive). | Must | US-05 |
| FR-ROSTER-06 | A member can remove their own entry for a game. | Must | US-05 |

## FR-BUILDS: Warframe builds (`builds.php`)

| ID | Requirement | Priority | Source |
|---|---|---|---|
| FR-BUILDS-01 | The latest 60 builds are public. | Should | US-08 |
| FR-BUILDS-02 | Saving requires login; title 1–80 characters, frame id `[a-z0-9-]{1,32}`, build code base64 of at most 512 characters. | Must | US-08 |
| FR-BUILDS-03 | A member has at most 20 builds; saving a 21st replaces their oldest. | Should | US-08 |
| FR-BUILDS-04 | Members delete their own builds; admins can delete any build. | Must | US-08 |

## FR-LOOT: loot-code reports (`codes.php`)

| ID | Requirement | Priority | Source |
|---|---|---|---|
| FR-LOOT-01 | A member can report a code as dead once per code. | Must | US-10 |
| FR-LOOT-02 | When 5 different members have reported a code, it is blocked for everyone. | Must | US-10 |
| FR-LOOT-03 | Admins (the owner and the admins, FR-ADMIN-04) can block a code directly, and unblock it again. | Should | US-10, US-40 |
| FR-LOOT-04 | Game must be `warframe`, `wot` or `wows`; code must match `[A-Z0-9_-]{3,40}` (after upper-casing). | Must | US-10 |

## FR-I18N: languages

| ID | Requirement | Priority | Source |
|---|---|---|---|
| FR-I18N-01 | `texts.php?lang=xx&page=yy` returns the page's texts in the requested language. | Must | US-09 |
| FR-I18N-02 | A text that is not translated is returned in English. | Must | US-09 |
| FR-I18N-03 | An unknown language code returns English. | Must | US-09 |
| FR-I18N-04 | `texts.php?action=languages` lists the enabled languages in their sort order. | Should | US-11 |
| FR-I18N-05 | An invalid page name is rejected with 400. | Must | NFR-SEC-04 |
| FR-I18N-06 | The language choice is saved only when the visitor clicks a flag. | Must | REG-EPR-01, DEF-002 |
| FR-I18N-07 | The flags are a menu (`js/shared/lang-menu.js`): only the flag of the chosen language shows; a click opens the list of languages. Opening the menu stores nothing. | Should | US-09, FR-I18N-06 |
| FR-I18N-08 | The flag images are served by hakoo.eu (`iconer/flags/`), not by another domain. | Must | REG-GDPR-25 |
| FR-I18N-09 | Below the languages, a gear opens the settings: the text size (normal, large, extra large) and, for logged-in members, a link to the translation page. The text size is saved only when the visitor picks one, and follows them to the other pages that have the menu. The Warframe Builder keeps its own text-size rules. | Should | US-09, REG-EPR-01 |
| FR-I18N-10 | Every page has the language menu, including the start page, HAKOO Games and the five board-game pages. | Must | US-09 |
| FR-I18N-11 | The start page, HAKOO Games and the board-game pages get their texts from the database (pages `index`, `hakoo-games` and `common`); the English stays in the page. The text inside the games is translated by FR-I18N-13. | Should | US-09 |
| FR-I18N-12 | Those pages store nothing in the browser until the visitor picks a language or text size; only then is the language saved (`wot_lang`) and the loaded texts cached (`hakoo_texts2:*`). The language is English until one is picked. | Must | REG-EPR-01, FR-I18N-06 |
| FR-I18N-13 | Every HAKOO Games page (the 13 games) and every rules page is in the picked language: the page itself, its dialogs, and everything the game writes while it is played (whose turn, messages, results, the cards of Runborg, the screen-reader labels of the squares, what `confirm()` asks). The texts are looked up by their English (`js/shared/i18n.js`, "By their English"; `<body data-i18n-shared="games">`) from pages `games` and `games/<page>` in the database, made from `database/texts/games/*.json` by `database/tools/build-game-texts.js`. A text that has no translation stays English. Changing the language changes the page at once, and back to English too. | Should | US-09 |
| FR-I18N-14 | A link can name the language: `?SE`, `?DK`, `?NL`, `?GE` or `?EN` in the address (also `?sv`, `?da`, `?de` and `?lang=SE`) shows the page in it, over the visitor's own choice, and stores nothing (FR-I18N-12). Picking a flag still chooses and saves. The board games keep the code in the invite links they make, so a friend opens the game in the same language. | Should | Owner 2026-10-16 |

## FR-SITE: appearance (`js/shared/appearance.js`, `appearance.php`, `hakoo_appearance`)

| ID | Requirement | Priority | Source |
|---|---|---|---|
| FR-SITE-01 | Every page can be shown in a theme (Dark, the default; Light; High contrast; HAKOO: the Community banner's deep purple, a brighter purple further down, and its yellow `#FFDF56` as the accent), with the visitor's own background (the theme's, one colour, or several colours: FR-SITE-05), text and accent colours (each optional), and a background pattern (none, dots, grid, lines). A page shows the saved look before it is drawn (no flash of the default colours). The Community banner follows the look (with no look, and with the HAKOO theme, it is the HAKOO banner as before); the choices on the start page stay dark in every theme; the board-game boards keep their own looks (rewards). | Should | US-31, US-32 |
| FR-SITE-02 | A look whose text is below 4.5:1 or whose accent is below 3:1 against its background — against every colour of a gradient, the theme's own included — (WCAG contrast) gives a warning that says why, but it is shown on the page and Save still works. Save on such a look asks, in a box that is always high contrast (black, white, yellow), "Keep these colours?" with a 5-second countdown ("Going back" has the focus): "Keep" saves it; "Go back", Escape or no answer in time returns to the saved look and offers a readable version of the picked colours (text and accent moved towards white or black, same hue) with "Use the suggestion". The server refuses such a look (`hard_to_read`) unless the request has `confirmed: true`. Only the four themes, the four patterns and colours `#rrggbb` (or none) are accepted (`bad_look`). | Must | US-31, NFR-ACC |
| FR-SITE-03 | The look is changed under ⚙ → 🎨 Appearance in the language menu on every page, and for members on the profile (section Appearance). Changes show at once; only Save keeps them: in the browser (`localStorage.hakoo_look`), and for a member also on the account (`hakoo_appearance`). Leaving without Save goes back to the saved look; "Reset to default" + Save removes the stored look. A first visit stores nothing. | Must | US-31, REG-EPR-01, REG-GDPR-15, REG-GDPR-17 |
| FR-SITE-04 | A member's look on the account follows them: on a device where they are logged in, every page shows it (and keeps it in that browser). | Should | US-31 |
| FR-SITE-05 | A background of several colours: 2–4 colours blended as a straight line (direction 0–360°, in steps of 5) or as a circle from a place (the middle, a side or a corner); each colour has a slider for where on the way it lies (0–100 %, never before the colour before it). A colour can be added (up to 4) or removed (down to 2); a strip shows the result. The first colour is also the base for the automatic boxes (FR-SITE-06). The server accepts only this form (`bad_look`) and stores it in `hakoo_appearance.gradient`. | Should | US-32 |
| FR-SITE-06 | Boxes and cards follow the background, never the text colour: automatically they are the background colour (a gradient's first colour) a little lighter, or a little darker on a light background. The visitor can give them a colour of their own ("Boxes"; "Automatic" goes back); the bars (top bar, side columns, buttons: `--bg2`) are then made from it too, and no page has box colours of its own (the banner's figures, the Loot and Warframe buttons follow), and every page's banner is made from the look: the Loot banner like the Community banner (`--hero`), the others as the box colour shading into a hint of the accent, green or blue. Text below 4.5:1 or an accent below 3:1 on own boxes is refused as in FR-SITE-02, in the page and on the server (`hard_to_read`); stored in `hakoo_appearance.box`. | Should | US-31 |
| FR-SITE-07 | A moving background: none (the default), stars, a network or fireflies, drawn behind the page in the text and accent colours. It follows the mouse (the stars shift with it and light up near it, the network's dots move away and link to it, the fireflies drift towards it), never takes a click. The boxes and bars become glass (78 % of their colour) that shows the moving background twice as large, like a magnifying glass; dialogs and drop-down menus stay solid. With "reduce motion" in the system settings it moves only while the mouse moves (and a moment after), never by itself; a background tab draws nothing. Only these four are accepted (`bad_look`); stored in `hakoo_appearance.effect`. | Should | US-31 |

*2026-10-03, owner decision:* colours that are hard to read are no longer blocked: a warning, a high-contrast question after Save with a 5-second countdown, and a readable suggestion after "Go back" (FR-SITE-02).

*2026-10-02, owner decisions (later the same day):* a background of up to 4 colours with direction and where each colour lies; the HAKOO theme takes the Community banner's purple and yellow; the banner follows the visitor's look (FR-SITE-01, 05).

*2026-10-02, owner decisions:* themes and own colours (with a contrast check), a background of colour or pattern (no uploaded pictures), changed under ⚙ on every page and on the profile, for the whole site.

## FR-TEXTS: the translation page (`texts.html`, `texts-admin.php`)

| ID | Requirement | Priority | Source |
|---|---|---|---|
| FR-TEXTS-01 | The translation page and its data are only for members who are logged in with Discord (401 otherwise). | Must | US-11 |
| FR-TEXTS-02 | The page shows every text in every enabled language, with the flags in the table header; a missing translation is marked. The table can be filtered by page, by search text and to missing translations only. | Must | US-11 |
| FR-TEXTS-03 | A member can ask for the language role. Only the admin (`DISCORD_ADMIN_IDS`) gives the role, to a member who asked, and can remove it again. | Must | US-11 |
| FR-TEXTS-04 | Only a member with the language role can suggest a translation; one waiting suggestion per member, text and language (a new one replaces the old). | Must | US-11 |
| FR-TEXTS-05 | A suggestion changes nothing on the site until the admin approves it; a rejected suggestion changes nothing. Only the admin decides. | Must | US-11 |
| FR-TEXTS-06 | English is changed by the admin only; it cannot be suggested. | Must | US-11 |
| FR-TEXTS-07 | The admin changes any text in any language directly. English cannot be empty; an emptied translation falls back to English (FR-I18N-02). | Must | US-11 |
| FR-TEXTS-08 | A member can take back their own waiting suggestion, not someone else's. | Should | US-11 |
| FR-TEXTS-09 | The language role and the suggestions are personal data: exported with "Download my data" and removed with "Delete all my data". Translations that were approved stay. | Must | REG-GDPR-17, REG-GDPR-20 |
| FR-TEXTS-10 | Pages show a link to the translation page, in the footer and in the language menu's settings, only to members who are logged in. | Should | US-11 |

## FR-ADMIN: the admin page (`admin.html`, `admin.php`, `hakoo-admin.inc.php`, `hakoo_roles`, `hakoo_member_names`, `hakoo_play_stats`)

The owner is `DISCORD_ADMIN_IDS` in `<CONFIG_FILE>` (as before). "The admin" in FR-TEXTS and FR-LOOT means the owner or an admin from FR-ADMIN-02.

| ID | Requirement | Priority | Source |
|---|---|---|---|
| FR-ADMIN-01 | The admin page and its data are only for the owner and the admins (401 logged out, 403 for other members). The profile shows the way to it (🛠 Admin) only to them. | Must | US-40 |
| FR-ADMIN-02 | Only the owner gives and takes roles. The member is picked by name from the HAKOO Discord server's members (as for challenges; no bots); the server checks with Discord that they are a member and stores the name Discord gives. A Discord id can be typed when the list cannot be loaded. | Must | US-40 |
| FR-ADMIN-03 | The roles: **admin** (FR-ADMIN-04), and the badges **Tester** (FR-ADMIN-07) and **OG HAKOO**, which does nothing on the site yet (owner 2026-10-07: later, for example free games when games cost). A member can have several roles: one row per member with a button per role that the owner switches on and off. | Must | US-40 |
| FR-ADMIN-04 | An admin sees the statistics, looks after the loot codes (blocks a code, unblocks one that was blocked by mistake: its reports go too, so it is not blocked again at once; sees the reported, listed, waiting and blocked codes) and approves translations like the owner (FR-TEXTS). An admin gives no roles. | Must | US-40 |
| FR-ADMIN-05 | The statistics are numbers only: members (how many, logged in in the last 7 and 30 days, new in the last 30 days), logins per day for the last 30 days, and games started per game (7 days, 30 days, in all, and how: computer, friend, challenge, practice, of the day). A board game counts when it is started, Ludo when it leaves the lobby, a practice puzzle when a new one is asked for (not a reload), the puzzle of the day the first time a member opens it (a guest: every time). The counts (`hakoo_play_stats`: day, game, how, number) hold no member id and stay when the games are deleted. Who played what is not stored for now; the owner may want it later (owner 2026-10-07). | Must | US-40 |
| FR-ADMIN-07 | The Tester badge also gives the member the role **Hakoo testers** on the HAKOO Discord server (config `DISCORD_TESTER_ROLE_ID`, or the role found by that name), and taking the badge takes the role. The page says whether Discord did it, and why not (no such role; the bot needs Manage Roles above it). The other roles do not touch Discord. | Should | US-40 |
| FR-ADMIN-08 | The owner can give a member a name of their own (at most 40 characters, one line of plain text), because some members change their Discord name: it is shown first, with the member's Discord name today (from the bot's member list) and the name they had when they got a role, if it changed. An empty name removes it. | Should | US-40 |
| FR-ADMIN-10 | A **founder** (the role Founder, given only by the owner) does everything an admin does and also gives and takes the Admin role; an admin who is not a founder is refused (`founder_only`), and a founder cannot make founders (`owner_only`). The admin page, the right-click menu and admin.html say so. Owner 2026-10-11. | Must | US-40 |
| FR-ADMIN-09 | The owner gives every role; a founder every role but Founder (FR-ADMIN-10); an admin every role but Admin and Founder (owner_only, founder_only), on admin.html or with the right-click menu (FR-RANK-02). | Must | US-50 |
| FR-ADMIN-09 | The admin page has a menu (Statistics, Members and roles, Loot codes, Translations) and shows one section at a time; the section is in the address (`#members`, ...). On a phone the menu is a row at the top. | Should | US-40 |
| FR-ADMIN-06 | Roles and the owner's names for members are personal data: in "Download my data" (role, name, since; not who gave it) and removed with "Delete all my data". When the owner deletes their data, the roles they gave stay without their id. | Must | REG-GDPR-15, REG-GDPR-17 |

## FR-PROFILE: members' profiles (`members.html`, `members.php`, profile → Who sees your profile)

| ID | Requirement | Priority | Source |
|---|---|---|---|
| FR-PROFILE-01 | The members' profiles are for logged-in members only (401). A visitor who is not logged in sees a blurred, made-up sample profile and a way to log in; no member's data is sent to the page then. | Must | US-42 |
| FR-PROFILE-02 | A member sees the list of the profiles shown to the members, and their own. The owner and the admins see every member who has logged in, the hidden ones marked, with the last login. | Must | US-42, US-40 |
| FR-PROFILE-03 | A profile: the Discord name and picture (as at the last login, or the last visit while logged in), the title, member since, the games won against the computer (Easy, Medium, Hard per game), the Runborg wins per board and opponent, and the puzzles of the day (solved, streak, best time per level). | Must | US-42 |
| FR-PROFILE-04 | A hidden profile is refused to other members (403 `private`); its owner and the admins see it. The linked game accounts (World of Tanks, Steam, in-game names) show only if the member turned that on, for everyone. | Must | US-42 |
| FR-PROFILE-05 | On their profile (👁 Who sees your profile) a member shows their profile to the members or not (hidden by default), and their linked accounts with it or not (off by default). | Must | US-42 |
| FR-PROFILE-06 | The name, picture and the two choices are personal data: in "Download my data" and removed with "Delete all my data". | Must | REG-GDPR-15, REG-GDPR-17 |

## FR-AVATAR: the member's viking (profile → Your viking, `avatar.php`, `hakoo-avatar.inc.php`, `js/shared/viking-avatar.js`, `js/shared/viking-3d.js`)

| ID | Requirement | Priority | Source |
|---|---|---|---|
| FR-AVATAR-01 | A logged-in member makes a viking on their profile; visitors who are not logged in have none (401). | Must | US-44 |
| FR-AVATAR-02 | Free for everyone: man or woman, skin (3), hair (4), clothes (blue, red, green, brown), and on the head no helmet, a helmet, or a helmet with horns. The picture follows each choice at once; Save keeps it. | Must | US-44 |
| FR-AVATAR-03 | Unlocked by what the member has done: Runborg titles (Farmer: straw hat and pitchfork, Viking: axe, Shieldmaiden: round shield, Jarl: fur cloak, King: crown), games won (Easy in 5 different games: the colour purple; Easy in each game: a colour of its own, FR-AVATAR-12; Medium in 3: a rune pattern on the tunic; Hard in any: gold trim), days in a row with a puzzle of the day solved (7: lantern, 30: rune pendant, 100: a raven on the shoulder), roles (Tester: builder's hammer, OG HAKOO: owl brooch). Winning a board game on Hard unlocks a thing of its own (FR-AVATAR-07). Locked items show with 🔒 and how to win them. | Must | US-44 |
| FR-AVATAR-04 | The server decides what is unlocked (from those tables) and sets anything not free or not unlocked back when the viking is saved, so a locked item cannot be worn. | Must | US-44 |
| FR-AVATAR-05 | The viking shows on the member's profile, on the members' profiles page (the profile and the list), beside their name at the Runborg table and, if they choose, instead of their Discord picture in the login corner on every page. | Should | US-44 |
| FR-AVATAR-06 | The viking is personal data: in "Download my data" and removed with "Delete all my data". | Must | REG-GDPR-15, REG-GDPR-17 |
| FR-AVATAR-07 | Hard in each board game: Gomoku a necklace of black and white stones, Chess a knight pendant, Othello a two-tone cape (black outside, white inside), Hnefatafl a king-piece amulet, Stratego a flag on the back, Backgammon a dice belt, Checkers a checkered scarf, Nine Men's Morris a mill-board buckle (on a new slot, at the waist). Ludo has no levels and no item. | Must | US-45 |
| FR-AVATAR-08 | The viking is shown in 3D on the profile (beside the editor) and on HAKOO Games (FR-PLAYERS-02): a whole figure on a round stand that turns when dragged (mouse, pen or finger), drawn with three.js kept on hakoo.eu (`js/vendor/three`, MIT). Two sliders set how tall and how strongly built it is (0-100, saved with the viking; anything else is set to the middle). The small round portrait stays for the lists and the login corner. Without WebGL the portrait shows instead. | Must | US-45 |
| FR-AVATAR-09 | Free for everyone too, shown in 3D: trousers, a skirt (under the tunic, to the knees), a long tunic (to the knees, over trousers) or a dress (to the ankles, in the colour of the clothes); the colour of the trousers or the skirt (grey, brown, blue, green, red, black); boots or bare feet. | Must | US-47 |
| FR-AVATAR-10 | The background behind the 3D viking: by default the member's own hakoo.eu background (its colour, gradient and pattern, not the moving effects); beating the Shieldmaiden, the Jarl or the King on a Runborg board unlocks that board as a picture behind the viking (Fjord, Winter, Northern Lights, Autumn Forest, Winter Dusk): its sky with the sun or the moon, mountains with snow, a line of pines and leafy trees, the ground, and the board's own touches (the fjord's water and clouds, the northern lights and stars, falling leaves, snow). | Must | US-47 |
| FR-AVATAR-11 | The editor has three parts: the look everyone has from the start (viking, skin, hair, clothes, legs and their colour, feet, size; colours as swatches), the things won (one drop-down list per place on the body, the locked ones greyed out with 🔒, and how many of all are won) and the colours and backgrounds won; "Still to unlock" folds open under them. On a phone the 3D viking scrolls away with the page. | Should | US-47 |
| FR-AVATAR-12 | Colours won in the games: winning a game on Easy gives a colour (Gomoku gold, Chess ivory, Othello teal, Hnefatafl crimson, Stratego orange, Backgammon navy, Checkers black, Nine Men's Morris olive, Nonogram pink, Sudoku sky blue, Owldoku lilac; purple for Easy in 5 games). They show in the right part of the editor (locked ones greyed out, with how to win them). After the colours everyone has for the clothes, the legs and the pattern there is an empty **square** (the colours everyone has are round, so the member's own colour looks different; owner 2026-10-11), and a line in Your look says how to use it: the member chooses the square, then a colour won, and the square and that part of the viking take it. The pattern on the clothes is one of a list (no pattern, the rune pattern; ready for more), in white or a colour won. The server sets back a colour or a pattern that is not won; an old save with the rune pattern on keeps it. | Must | US-49 |
| FR-AVATAR-13 | The shield (owner 2026-10-13): held out at the side, upright and turned towards the front, so it never goes into the body, a long tunic or a dress. While the viking holds it, a box under the three parts of the editor (🛡 Your shield) chooses its shape, colour, pattern, the pattern's colour and the rim and boss, each choice with a small picture of the shield; the 3D viking and the flat portrait draw the same face. Free: the round shield; the clothes' colour or blue, red, green, brown; cross, plain, halves, quarters, sun rays; white or black; wood or iron; plus every colour won. Won: small round shield (3 puzzles of the day in a row), kite shield (the Jarl), shield-wall shield (Hnefatafl on Medium), rune ring (Medium in 3 games), raven (14 in a row), sun wheel (30 in a row), dragon (the King), valknut (every board game on Medium), Yggdrasil (gold medal in all three puzzles), silver rim (Medium in any game), gold rim (Hard in any game). The server sets back what is not won; a viking saved before keeps its shield (round, the clothes' colour, a cross, wood). | Should | Owner 2026-10-13 |

## FR-PLAYERS: the players on HAKOO Games (`hakoo-games.html`, `js/hakoo-games.js`, `members.php` ?action=players/card)

| ID | Requirement | Priority | Source |
|---|---|---|---|
| FR-PLAYERS-01 | A logged-in member sees a list of the members who have logged in to play (name, title, viking or Discord picture; by name, in groups: FR-PLAYERS-06), themselves always included and always first. A member who left the list (FR-PLAYERS-04) is not in it, for anyone. Not logged in: a blurred, made-up sample and a way to log in; no member's data is sent (401). | Must | US-45 |
| FR-PLAYERS-02 | Clicking a player shows their viking in 3D (FR-AVATAR-08) with their puzzle medals beside it, and under it their profile (games won, Runborg titles, puzzle streaks, a link to the whole profile) only if they show it (FR-PROFILE-05), it is their own, or for an admin; otherwise a line that they keep it to themselves. The member's own card is shown first. | Must | US-45 |
| FR-PLAYERS-03 | The puzzle medals (not items): for each of Nonogram, Sudoku and Owldoku, bronze, silver or gold for the best level won (Easy, Medium, Hard), and one for the longest run of puzzles of the day: bronze 7, silver 30, gold 100 days. They also come with the profile (members.php). | Must | US-45 |
| FR-PLAYERS-04 | On their profile (👁 Who sees your profile) a member leaves the players list or comes back (in it by default); their card is then refused to others (403 `private`). The choice is personal data, in "Download my data" and removed with "Delete all my data". | Must | US-45, REG-GDPR-15 |
| FR-PLAYERS-06 | The list is grouped: first the member themselves ("You"), then the King (the owner), a Queen (FR-RANK-04) and Admin (the roles on hakoo.eu; a Discord role does not make anyone an admin here), then by rank (FR-RANK-01): Jarls, Vikings and shieldmaidens, Members (free); each group has a heading with its count, names by the alphabet. A name is drawn in the colour of the member's highest coloured Discord role, and a player with no name on the site gets their Discord name (Discord is asked at most every 10 minutes; the answer is kept in a server file that cannot be opened from the web). Beside a name: "you", the rank, and a tag for each role on the site (King for the owner, Admin, Tester, OG HAKOO, Founder; a role added later shows by its own name). The Runborg title chosen stays before the name. | Must | US-46, US-47, US-50 |
| FR-PLAYERS-07 | Only members who have played on hakoo.eu are in the list (a game against the computer or a friend, Runborg, a puzzle of the day, or a win); only logging in, or being on the Discord server, does not count. The member always sees themselves. The card of someone who has not played is refused (403), except to an admin. | Must | US-47 |
| FR-PLAYERS-08 | On the page of a puzzle with a puzzle of the day (Nonogram, Sudoku, Owldoku) the players list stays at the left, and the card at the right is only today's puzzlers (no viking, so it fits the screen): the members who have opened today's puzzle (not those who left the players list; the member always sees themselves), each with today's and yesterday's time per level (or playing, or out of hearts in Owldoku), their days in a row and how many puzzles of the day they have solved in all; most solved today first, then the least time; a click on a player in either list opens their profile (members.html). Logged in only (401); logged out, no puzzlers, and the players list is a blurred sample with a login that comes back to the page. | Must | US-48, US-50 |
| FR-PLAYERS-09 | Every game's page (the board games, Ludo, Runborg and the puzzles) has the list and the card in the empty sides of a screen 1680 pixels or wider, as on HAKOO Games (js/shared/players-panel.js). On a smaller screen they are not shown, and nothing is asked for (except today's puzzlers on a puzzle's page: FR-PLAYERS-10). A larger text size (the ⚙ menu zooms the page) counts: the page must have 1680 pixels after the zoom, so the columns are always inside the window; nothing is ever wider than the window. | Should | US-48 |
| FR-PLAYERS-10 | On a puzzle's page without room at the sides (a laptop, or a larger text size), today's puzzlers are not left out: their card takes the short rules' place at the right of the start page, and the short rules move down under the rest of the start page (owner 2026-10-13: the day's list matters more than the rules). On a phone the card comes before the rules. With room at the sides, everything is as in FR-PLAYERS-08 again. | Should | Owner 2026-10-13 |
| FR-PLAYERS-05 | On a screen of 1680 pixels or wider the list stands in the empty space to the left of the page and the card to the right, both staying in place while the games scroll; on smaller screens they come under the games (one under the other on a phone), and a click brings the card into view. | Should | US-45 |

## FR-NAV: the way back, in the menu bar (`js/shared/breadcrumbs.js`)

| ID | Requirement | Priority | Source |
|---|---|---|---|
| FR-NAV-01 | Every page but the start page shows in the menu bar, after the logo, the pages below the one the logo stands for down to this one, e.g. [HAKOO Games] › Chess › Rules; the page itself is last and not a link, every step before it is. A step that only says again what the logo says (HAKOO Help) is left out. | Must | US-46, US-47 |
| FR-NAV-02 | A back arrow to the left of the logo goes one step up (the same page every time, not back in the browser's history); the start page has none. Pages that already have their own arrow (the WoT pages) keep it. | Must | US-46, US-47 |
| FR-NAV-03 | On a phone only the arrow and the logo show, to save room. | Should | US-46, US-47 |

## FR-RANK: ranks, and roles that admins give (`admin.php` ?action=tier/role, `js/shared/admin-menu.js`, `members.php`)

| ID | Requirement | Priority | Source |
|---|---|---|---|
| FR-RANK-01 | Every member has a rank from what they pay (the tier, set by the owner or an admin): free — no rank by the name, unless the member picks Farmer or Soldier; paying — Viking or Shieldmaiden; VIP — Jarl. Everyone starts free, so nobody is called a farmer unless they choose it (the Runborg title they chose stays before the name). The member picks on their profile (👁 Who sees your profile); the pick stays when the tier changes. The rank shows by the name and groups the players list (FR-PLAYERS-06). Tier and pick are personal data: in "Download my data" and removed with "Delete all my data". | Must | US-50, REG-GDPR-15 |
| FR-RANK-02 | The owner and the admins right-click a member (or press the menu key, or long-press) in the players list or on the members' page and get a menu: the rank (Free member · Viking or shieldmaiden · Jarl) and the roles (Tester, OG HAKOO, Admin) with the ones they have ticked. Members who are not admins get no menu (the server refuses them anyway, 403). Escape or a click elsewhere closes it. | Must | US-50 |
| FR-RANK-03 | The roles column takes any role name, so a role (a server group) can be added later without a change to the database. | Should | US-50 |
| FR-RANK-04 | An OG HAKOO may pick any of the ranks (none, Farmer, Soldier, Viking, Shieldmaiden, Jarl) whatever they pay. The role Founder (given by the owner only, for the founders of HAKOO) adds Queen; a Queen has a group of her own after the King. The server keeps only a pick the member may have; one they may no longer have (a role taken away) falls back to the first of their choices. The owner shows as King (tag and group) instead of Owner. | Must | US-51 |

## FR-WOT: the WoT tracker's stat snapshots (`wot-tracker.html`, `api.php`, `wot_snapshots`)

| ID | Requirement | Priority | Source |
|---|---|---|---|
| FR-WOT-SNAP-01 | A snapshot with exactly the same values as the latest one of the account is not stored again (no new row); a changed one is (within 5 minutes of the latest it replaces it, as before). Repeats stored before 2026-10-03 are removed once by `migration_wot_snapshots_dedupe.sql`, keeping the first of each unchanged stretch. The wallet's arrows compare with the latest stored snapshot: nothing changed, no arrow. | Should | Owner 2026-10-03, REG-GDPR (data minimisation) |

## FR-REPORT: problem reports from members (`report.php`, `js/shared/report.js`)

| ID | Requirement | Priority | Source |
|---|---|---|---|
| FR-REPORT-01 | A logged-in member sees "🐞 Report a problem" by the login on every page with the login corner (on a phone in 👤▾); not logged in, there is no button and `report.php` answers 401. The form: the kind (does not work, looks wrong, an idea, other), what happened (at least 5 characters), what they expected and what they did just before (optional); the page address without '#' (game tokens live there), browser, system, screen size and the page's last five errors are added unless the member unticks it, and the form shows exactly what is added. | Should | Owner 2026-10-07 |
| FR-REPORT-02 | The HAKOO bot posts the report in the Discord channel #reports-from-hakoo-eu (config `DISCORD_REPORT_CHANNEL_ID`, else the channel with that name, else the first with "report" in its name) as an embed with the member's Discord name and a mention that pings nobody. Nothing is stored in the database. Without the bot or the channel: 503 `not_configured`; Discord refusing: 502 `not_sent`; the member gets a message either way. | Should | Owner 2026-10-07 |
| FR-REPORT-03 | At most five reports an hour per member (429 `too_many`), counted in the server's temp folder per hashed Discord id and forgotten after a day. | Should | Owner 2026-10-07 |
| FR-REPORT-04 | The button and the form are in the language the visitor picked (all our languages): the texts are page "report" in the database (`database/tools/build-page-texts.js`), loaded by `report.js` with the English as the fallback; the form follows a language picked later the next time it opens. What the bot posts in Discord stays in English, for the team. | Should | Owner 2026-10-10 (every new page in all our languages) |

## FR-LINK: linking game accounts (`profile.html`, `link.php`, `js/shared/require-account.js`)

| ID | Requirement | Priority | Source |
|---|---|---|---|
| FR-LINK-01 | A logged-in member can link a Wargaming (WoT/WoWs) account from the profile page. | Must | US-13 |
| FR-LINK-02 | A Wargaming link is stored only after the Wargaming API confirms that the returned access token belongs to the account; the nickname is taken from the API, not from the URL. | Must | US-14, DEF-010 |
| FR-LINK-03 | After a successful link, the Wargaming token is handed to the browser in the URL fragment and stored in localStorage for the trackers; the fragment is removed from the address bar. | Must | US-13 |
| FR-LINK-04 | Pages that cannot work without a Wargaming account (WoT tracker, WoWs tracker) send the visitor to `profile.html?need=wot&return=<page>` when: not logged in, no account linked, or (WoT tracker) no valid token for a linked account in this browser. | Must | US-13 |
| FR-LINK-05 | The profile explains what the page needs (not logged in / not linked / token expired) and, after linking, sends the visitor back to the return page. | Must | US-13 |
| FR-LINK-06 | Without a Discord session, the link callback links nothing. | Must | US-14 |
| FR-LINK-07 | A refused link shows an error on the profile and changes nothing. | Should | US-14 |
