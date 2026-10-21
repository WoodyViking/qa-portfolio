# Personal data register

Every place where hakoo.eu stores data about a person, and how it is exported and
deleted. This also serves as the GDPR Art. 30 record of processing.
**Update this file whenever a table, file, cookie or storage key is added.**

The code version of this register is `src/hakoo-users.inc.php`
(`HAKOO_PERSONAL_TABLES` and `HAKOO_PERSONAL_FILES`). The two must match.

## Database (MySQL `<DB_NAME>`)

| Table | Keyed by | What | Export | Delete |
|---|---|---|---|---|
| `hakoo_users` | Discord id | One row per member: first seen, last login, consent version/time | ✅ | ✅ The row itself; everything below cascades from it |
| `hakoo_links` | Discord id → FK | Linked WoT and Steam accounts | ✅ | ✅ cascade + explicit |
| `hakoo_ign` | Discord id → FK | In-game names, notes and Mastery Rank (roster) | ✅ | ✅ cascade + explicit |
| `hakoo_builds` | Discord id → FK | Published Warframe builds + author name | ✅ | ✅ cascade + explicit |
| `hakoo_code_reports` | Discord id → FK | Reports of dead loot codes | ✅ | ✅ cascade + explicit |
| `hakoo_gomoku_profile` | Discord id → FK | Gomoku rewards (first win per level) and chosen look (stone colours, 3D, board, crown on/off) | ✅ | ✅ cascade + explicit |
| `hakoo_gomoku_games` | Game id; Discord id → FK only for a logged-in member's computer games | Gomoku games: moves, player names typed in, token **hashes**, result. Anonymous games have no Discord id. | ✅ own computer games | ✅ own games cascade + explicit; all games deleted 30 days after the last activity |
| `hakoo_chess_profile` | Discord id → FK | Chess rewards (first win per level) and chosen look (piece colours, 3D, board, crown on/off) | ✅ | ✅ cascade + explicit |
| `hakoo_chess_games` | Game id; Discord id → FK only for a logged-in member's computer games | Chess games, stored like Gomoku games | ✅ own computer games | ✅ own games cascade + explicit; all games deleted 30 days after the last activity |
| `hakoo_othello_profile` | Discord id → FK | Othello rewards (first win per level) and chosen look (disc colours, 3D, board, crown on/off) | ✅ | ✅ cascade + explicit |
| `hakoo_othello_games` | Game id; Discord id → FK only for a logged-in member's computer games | Othello games, stored like Gomoku games | ✅ own computer games | ✅ own games cascade + explicit; all games deleted 30 days after the last activity |
| `hakoo_hnefatafl_profile` | Discord id → FK | Hnefatafl rewards (first win per level) and chosen look (piece colours, 3D, board, crown on/off) | ✅ | ✅ cascade + explicit |
| `hakoo_hnefatafl_games` | Game id; Discord id → FK only for a logged-in member's computer games | Hnefatafl games, stored like Gomoku games | ✅ own computer games | ✅ own games cascade + explicit; all games deleted 30 days after the last activity |
| `hakoo_stratego_profile` | Discord id → FK | Stratego rewards (first win per level) and chosen look (piece colours, 3D, board, crown on/off) | ✅ | ✅ cascade + explicit |
| `hakoo_stratego_games` | Game id; Discord id → FK only for a logged-in member's computer games | Stratego games, stored like Gomoku games, plus each player's secret set-up (never sent to the other player) | ✅ own computer games | ✅ own games cascade + explicit; all games deleted 30 days after the last activity |
| `hakoo_ludo_games` | Game id only (no Discord id) | Ludo games: up to six seats (Kunglig Vikingludo has 5–6) with the names the players typed and a hash of each player's token, the rules, the board, the dice and moves | — (anonymous) | all games deleted 30 days after the last activity |
| `hakoo_yatzy_games` | Game id only (no Discord id) | Yatzy games: one to six seats with the names the players typed and a hash of each player's token, the rules (free or forced order), the score sheets, the dice and every roll and score | — (anonymous) | all games deleted 30 days after the last activity |
| `hakoo_board_settings` | Discord id → FK | Board-game settings of a member, one row for all games: the move speed (100–2000 ms) the dice settings (how to roll: button / cup / motion; how much shaking: easy / normal / hard) and the choices of the member's last Ludo game (colour, players, computer level, the name typed, rules) and last Yatzy game (`yatzy_setup`: players, computer level, the name typed, order; FR-YATZY-14, 2026-10-18). Only when the member changes them or starts a Ludo or Yatzy game; visitors who are not logged in store nothing. | ✅ | ✅ cascade + explicit |
| `hakoo_appearance` | Discord id → FK | The member's site appearance: theme, own colours (background, text, accent, boxes) or none, a background of several colours (`gradient`) or none, background pattern, moving background (`effect`). Only when the member saves one. | ✅ | ✅ cascade + explicit |
| `hakoo_backgammon_profile` | Discord id → FK | Backgammon rewards (first win per level) and chosen look (checker colours, 3D, board, crown on/off) | ✅ | ✅ cascade + explicit |
| `hakoo_backgammon_games` | Game id; Discord id → FK only for a logged-in member's computer games | Backgammon games, stored like Gomoku games, plus the dice waiting to be played | ✅ own computer games | ✅ own games cascade + explicit; all games deleted 30 days after the last activity |
| `hakoo_checkers_profile` | Discord id → FK | Checkers rewards (first win per level) and chosen look (piece colours, 3D, board, crown on/off) | ✅ | ✅ cascade + explicit |
| `hakoo_checkers_games` | Game id; Discord id → FK only for a logged-in member's computer games | Checkers games, stored like Gomoku games, plus the rules chosen (English or flying kings) | ✅ own computer games | ✅ own games cascade + explicit; all games deleted 30 days after the last activity |
| `hakoo_morris_profile` | Discord id → FK | Nine men's morris rewards (first win per level) and chosen look (stone colours, 3D, board, crown on/off) | ✅ | ✅ cascade + explicit |
| `hakoo_nonogram_profile` | Discord id → FK | Nonogram rewards (first puzzle solved per level) and chosen look (square and cross colours, 3D, board, crown on/off) | ✅ | ✅ cascade + explicit |
| `hakoo_nonogram_daily` | Discord id → FK | The puzzle of the day: when the member opened and solved it, the time, and their Discord name then (shown to logged-in members in the day's list; the streak is counted from it). Practice puzzles store nothing. Kept 400 days. | ✅ | ✅ cascade + explicit |
| `hakoo_sudoku_profile` | Discord id → FK | Sudoku rewards (first puzzle solved per level) and chosen look (digit and highlight colours, 3D, board, crown on/off) | ✅ | ✅ cascade + explicit |
| `hakoo_sudoku_daily` | Discord id → FK | Like `hakoo_nonogram_daily`, for Sudoku's puzzle of the day. Kept 400 days. | ✅ | ✅ cascade + explicit |
| `hakoo_puzzle_saves` | Discord id → FK | The squares a logged-in member wrote in Sudoku, Owldoku and Nonogram, so they can go on later, also on another device: today's puzzle of the day and one practice puzzle per game and level (which puzzle, the squares, when the practice clock started). A solved practice puzzle's row is deleted; a new one replaces it. | ✅ | ✅ cascade + explicit |
| `hakoo_owldoku_profile` | Discord id → FK | Owldoku rewards (first puzzle solved per level) and chosen look (owl and cross colours, 3D regions, region colours, crown on/off) | ✅ | ✅ cascade + explicit |
| `hakoo_users` (profile columns) | Discord id | The Discord display name and picture as at the last login or visit (for the members list), and the member's choices: profile shown to the members (default no), linked accounts shown with it (default no), in the players list on HAKOO Games (default yes: name, title, viking and puzzle medals, to the logged-in members); the tier (free, paying, VIP — set by the owner or an admin) and the member's pick of rank in it (shown as the rank to the logged-in members; free members show none unless they pick one). The owner and the admins see every profile (members.html) | ✅ (`account`) | ✅ with the row |
| `hakoo_avatar` | Discord id → FK | The member's viking: the choices (body, skin, hair, clothes, head, items) and whether it replaces the Discord picture in the login corner. Shown to the members who see the profile, and at the Runborg table | ✅ | ✅ cascade + explicit |
| `hakoo_runborg_games` | Game id; Discord id → FK for a logged-in member's game; `guest_id` → FK for the second player | Runborg games: the board, the opponent (the computer or a second player: their name at the table, a challenge's member), the whole game (both hands, the deck), the winner; the invite's and the tokens' hashes. Deleted 30 days after the last move | ✅ own games (without the moves) and the ones joined (`runborg_as_second_player`) | ✅ cascade + explicit; as the second player through the foreign key |
| `hakoo_runborg_wins` | Discord id → FK | Runborg wins per board and opponent: the first win and how many (the titles) | ✅ | ✅ cascade + explicit |
| `hakoo_runborg_profile` | Discord id → FK | The Runborg title the member shows by their name (or none) | ✅ | ✅ cascade + explicit |
| `hakoo_owldoku_daily` | Discord id → FK | Like `hakoo_nonogram_daily`, for Owldoku's puzzle of the day, plus the number of wrong owls (hearts lost; with none left the day's time does not count). Kept 400 days. | ✅ | ✅ cascade + explicit |
| `hakoo_morris_games` | Game id; Discord id → FK only for a logged-in member's computer games | Nine men's morris games, stored like Gomoku games, plus the rule chosen (flying on/off) | ✅ own computer games | ✅ own games cascade + explicit; all games deleted 30 days after the last activity |
| `hakoo_challenges` | Discord id → FK (challenger); `opponent_id` (the challenged member, deleted explicitly) | Board-game challenges between members: which game, who challenged whom (ids and Discord names), the challenger's side, open/accepted/over, the bot's Discord post (channel and message id). Deleted with the game, 30 days after the last move. | ✅ sent ones (without the other member's id) and received ones (`challenges_received`) | ✅ sent ones cascade + explicit, received ones explicit |
| `hakoo_saved_games` | Discord id → FK (owner); `opponent_discord_id` → FK, SET NULL | Saved games: the first win on each level against the computer, and games between two logged-in members (a copy for each). Snapshot of the moves, the names typed in and the result. While a members' game is played, a row without snapshot records who sits where (removed after 30 days if the game never ends). Latest 100 games against members kept per member, plus starred. | ✅ own copies (without the opponent's id) | ✅ own copies cascade + explicit; in the other member's copy the name becomes "Deleted player" and the id is cleared |
| `hakoo_berserk_runs` | Run id; Discord id -> FK only for a logged-in member's run | Path of Berserk runs: a hash of the run's token, the member's Discord name at the start (shown to logged-in members in the month's and year's lists), when it began and ended, what it reported (won, parts reached, enemies beaten, barrels broken, health and lives left) and the score the server counted. A guest's run has no id or name. Kept 400 days (the year's list); runs never finished are deleted after a day. | ✅ own runs | ✅ own runs cascade + explicit; all runs deleted after 400 days |
| `hakoo_berserk_podium` | Discord id -> FK | Path of Berserk: the first three members of each month and year that is over, with their scores (the rewards for the viking) | ✅ | ✅ cascade + explicit |
| `hakoo_berserk_profile` | Discord id -> FK | Path of Berserk totals of a member: runs finished, wins, wins without losing a life, best score, enemies beaten (the rewards; they outlive the runs), and the keyboard keys they chose (`controls`, so they are the same on every device) | ✅ | ✅ cascade + explicit |
| `wot_access` | WoT account id | Clan-access request/approval, officer flag | ✅ via linked WoT accounts | ✅ via linked WoT accounts |
| `wot_snapshots` | WoT account id | WoT stat history | ✅ via linked WoT accounts | ✅ via linked WoT accounts |
| `hakoo_deletions` | HMAC of Discord id | Proof of deletion for backup restores (no readable id) | — | kept on purpose |
| `hakoo_text_roles` | Discord id → FK | The language role of the translation page: who asked, Discord display name at that time, asked/given, when | ✅ | ✅ cascade + explicit |
| `hakoo_text_suggestions` | Discord id → FK | Translations a member suggested: text, language, Discord display name at that time, waiting/approved/rejected | ✅ | ✅ cascade + explicit. An approved translation stays in `hakoo_texts`: it is site text |
| `hakoo_roles` | Discord id → FK | Roles the owner gave on the admin page (admin, the Tester and OG HAKOO badges): which role, the Discord display name at that time, since when, and `granted_by` (the owner's Discord id) | ✅ (without `granted_by`) | ✅ cascade + explicit; `granted_by` is cleared when that owner deletes their data |
| `hakoo_member_names` | Discord id → FK | The name the owner gave a member on the admin page (members change their Discord names), when, and `updated_by` (the owner's Discord id) | ✅ (without `updated_by`) | ✅ cascade + explicit; `updated_by` is cleared when that owner deletes their data |
| `hakoo_play_stats` | — | How much is played: logins and games started per day, game and how. Numbers only, no id: not personal data | — | nothing to delete |
| `hakoo_texts`, `hakoo_languages` | — | Site texts. `hakoo_texts.updated_by` holds the Discord id of the admin who last changed the text | — | `updated_by` (and `decided_by` in the two tables above) is cleared when that admin deletes their data |

**WoT-only users:** the WoT tracker can also be used with a Wargaming login and no
Discord account. Their `wot_access`/`wot_snapshots` rows are not linked to a
`hakoo_users` row. Today they are removed with the tracker's own "clear history" action.
A deletion request from such a user must be handled by the admin (delete by `account_id`).

## Files in the web root

| File | What | Personal? | Delete |
|---|---|---|---|
| `wot_boxes.json` | Clan supply boxes per WoT account id | Yes | ✅ entries for the member's linked WoT accounts |
| `discord_overrides.json` | Manual "WoT name → Discord name" matches | Yes | ✅ entries for the member's linked WoT nicknames |
| `discord_presence.json`, `discord_cache.json` | Discord names, avatars, status, current game | Yes | Rebuilt from Discord every few minutes; gone when the member leaves the server |
| `discord_groups.cache.php` | Discord names, the server's roles shown apart and each member's highest such role and name colour (the names and their colours in the players list on HAKOO Games) | Yes | Asked from Discord again after 10 minutes; gone when the member leaves the server. A PHP file that answers 404 when opened from the web |
| `steam_cache.json`, `wot_stats_cache.json` | Cached public Steam / WoT data | Yes | Short-lived cache, overwritten automatically |
| `hakoo_codes_blocked.json` | Blocked loot codes (+ which admin blocked them) | Admin id only | Manual |
| `hakoo_codes_submitted.json` | Loot codes members reported to the bot | No id or name, only a keyed hash of the Discord id (for the 10-per-day limit) | Automatic after 60 days |

## In the visitor's browser

| Name | Type | Set when | Purpose |
|---|---|---|---|
| `hakoo_auth` | Cookie (HttpOnly, 30 days) | Discord login | Session |
| `hakoo_oauth_state` | Cookie (10 min) | During Discord login | CSRF protection |
| `hakoo_login_return` | Cookie (10 min) | Login started from a page that needs an account | Page to return to after login |
| `wot_token`, `wot_account`, `wot_nickname`, `wot_expires`, `wot_app_id`, `wot_server` | localStorage | After linking a Wargaming account on the profile | Lets the WoT/WoWs trackers call the Wargaming API; the tracker's Logout removes them |
| `wot_lang` | localStorage | Picking a language in the language menu (any page, including the start page and HAKOO Games) | Language choice |
| `hakoo_start` | localStorage | "Remember my choice" is on and a start page is picked | Skip the start page |
| `hakoo_texts2:*` | localStorage | Pages using `js/shared/i18n.js` (start page, HAKOO Games, the game and rules pages), only after the visitor picked a language | Text cache (it replaces the older `hakoo_texts:*`, which is removed when the page loads its texts) |
| Builder keys (`wf_*`) | localStorage | Using the Warframe builder | Builder state |
| `wf_textsize` | localStorage | Picking a text size in the language menu's settings (or in the Warframe builder) | Text size |
| `hakoo_berserk_keys` | localStorage | A guest pressing Save under ⌨️ Change keys on Path of Berserk's start page (a logged-in member's keys are kept with the account instead, and these move there); Default keys removes it | The keyboard keys the player chose (no personal data) |
| `hakoo_yatzy_setup` | localStorage | A guest ticking "Remember my choices on this device" when starting a Yatzy game; unticking it removes it (a logged-in member's choices are kept with the account instead) | The choices of the last Yatzy game: players, computer level, the name typed, order |
| `hakoo_look` | localStorage | Pressing Save under ⚙ → 🎨 Appearance (any page) or on the profile; for a logged-in member also when their saved look is loaded on a new device | The site appearance (theme, colours, pattern); removed by "Reset to default" + Save |

**HAKOO Games (`hakoo-games.html`, `games/gomoku.html`, `games/chess.html`, `games/othello.html`, `games/hnefatafl.html`, `games/stratego.html`, `games/backgammon.html`, `games/checkers.html`, `games/morris.html`, `games/nonogram.html`, `games/sudoku.html`, `games/owldoku.html`) sets nothing.** The games keep their game token in the URL after `#`. They *read* an existing `hakoo_auth` cookie (logged in on HAKOO Community) to give rewards and the chosen look; they never set one.

**Problem reports (`report.php`, `js/shared/report.js`) store nothing in the database.** The report (the member's text,
the kind, and unless they untick it: the page address without '#', browser, system, screen size and the page's error
messages) is posted with the member's Discord name and a non-pinging mention by the HAKOO bot in the Discord channel
#reports-from-hakoo-eu. hakoo.eu keeps only the times of the member's reports of the last hour (a file per member,
named by a hash of the Discord id, in the server's temp folder, deleted after a day) for the limit of five an hour. The
post is in Discord: a deletion request for it is handled by the admins there.

## Outside our control

| Where | What | Note |
|---|---|---|
| <HOSTING_PROVIDER> server logs | IP addresses, user agents | Kept by the host for a limited time; mention in the privacy notice |
| Database backups | Copies of all tables | Deleted members come back on restore → run `hakoo_reapply_deletions()` after every restore |
| Discord, Steam, Wargaming | Their own accounts | Governed by their own privacy policies |
