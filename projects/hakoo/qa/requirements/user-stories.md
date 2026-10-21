# User stories

Format: *As a \<role>, I want \<goal>, so that \<value>.* Each story has **acceptance criteria**
in Given/When/Then form (the "Confirmation" of the 3 C's: Card, Conversation, Confirmation).
Stories should follow **INVEST**: Independent, Negotiable, Valuable, Estimable, Small, Testable.

**Roles:** *visitor* (anyone), *anonymous player* (uses HAKOO Games), *member* (logged in with Discord),
*admin* (clan admin), *translator* (a friend who translates texts).

---

### US-01: Choose where to go
*As a visitor, I want to choose between HAKOO Community and HAKOO Games on the start page, so that I get
to the part of HAKOO I came for.*
Status: ✅ built · Requirements: FR-START-01, FR-GAMES-04

```gherkin
Scenario: The chooser shows both sections
  Given I have never saved a start page
  When I open the start page
  Then I see "HAKOO Community", "HAKOO Games" and "Remember my choice"

Scenario: Going to HAKOO Games
  Given I am on the start page
  When I select "HAKOO Games"
  Then the HAKOO Games page opens
```

### US-02: Remember my choice
*As a returning visitor, I want the site to remember which section I chose, so that I don't have to choose every time.*
Status: ✅ built · Requirements: FR-START-02..07, REG-EPR-01, REG-GDPR-7

```gherkin
Scenario: Nothing is stored without my consent
  Given "Remember my choice" is off
  When I select "HAKOO Community"
  Then nothing is stored in my browser

Scenario: My choice is remembered after I opt in
  Given "Remember my choice" is on
  When I select "HAKOO Games"
  And I open the start page again later
  Then I am sent straight to HAKOO Games

Scenario: I can withdraw my consent
  Given I have saved "HAKOO Games" as my start page
  When I open the start page with ?choose=1 and select "Forget my choice"
  Then nothing is stored in my browser any more
  And "Remember my choice" is off
```

### US-03: Play anonymously
*As an anonymous player, I want to use HAKOO Games without logging in or accepting cookies, so that I can play without giving away any data.*
Status: ✅ page built; games: Gomoku (US-15, US-16), Chess (US-19), Othello (US-20), Hnefatafl (US-25), Stratego (US-27), Backgammon (US-33), Checkers (US-35), Nine men's morris (US-36) · Requirements: FR-GAMES-01..03, REG-GDPR-25

```gherkin
Scenario: No tracking in HAKOO Games
  Given I have no cookies and no stored data for hakoo.eu
  When I open HAKOO Games
  Then no cookie is set
  And nothing is written to localStorage or sessionStorage
  And no request is sent to any other domain
```

### US-04: Log in with Discord
*As a member, I want to log in with my Discord account, so that the site knows who I am without a new password.*
Status: ✅ existing · Requirements: FR-AUTH-01..04, FR-USER-01..02

```gherkin
Scenario: First login creates my member record
  Given I have never logged in
  When I log in with Discord and authorise HAKOO
  Then I am sent to HAKOO Community, logged in
  And a member record exists for my Discord id
```

### US-05: Share my in-game names
*As a member, I want to add my in-game names to the clan roster, so that clanmates can find and add me in the games.*
Status: ✅ existing · Requirements: FR-ROSTER-01..06

```gherkin
Scenario Outline: In-game name length
  Given I am logged in
  When I save the in-game name "<name>" for World of Tanks
  Then the result is "<result>"
  Examples:
    | name              | result   |
    | (empty)           | rejected |
    | A                 | saved    |
    | 64 characters     | saved    |
    | 65 characters     | rejected |
```

### US-06: Download my data
*As a member, I want to download everything HAKOO stores about me, so that I can see it and take it with me.*
Status: ✅ built · Requirements: FR-GDPR-01, REG-GDPR-15, REG-GDPR-20

```gherkin
Scenario: The export is complete
  Given I am logged in and have linked accounts, in-game names, builds and a code report
  When I select "Download my data"
  Then I receive one JSON file
  And it contains every one of those items
```

### US-07: Delete all my data
*As a member, I want to delete everything HAKOO stores about me with one action, so that I can leave without a trace.*
Status: ✅ built · Requirements: FR-GDPR-02..08, FR-USER-03..04, REG-GDPR-17

```gherkin
Scenario: Everything is deleted
  Given I am logged in and have data in every part of HAKOO
  When I select "Delete all my data" and type DELETE
  Then all my data is deleted
  And I am logged out
  And other members' data is unchanged

Scenario: Nothing happens without confirmation
  Given I am logged in
  When I select "Delete all my data" but do not type DELETE
  Then nothing is deleted
```

### US-08: Share a Warframe build
*As a member, I want to save and share my Warframe builds, so that clanmates can use them.*
Status: ✅ existing · Requirements: FR-BUILDS-01..04

### US-09: Read the site in my language
*As a visitor, I want to read HAKOO in my own language, so that I understand everything.*
Status: ⚠️ every page has the language menu; the start page, HAKOO Games and the start of each board game are translated from the database, the texts inside the games are not · Requirements: FR-I18N-01..12

```gherkin
Scenario: A missing translation falls back to English
  Given a text has no German translation
  When I view the page in German
  Then that text is shown in English
```

### US-10: Report a dead loot code
*As a member, I want to report a loot code that no longer works, so that others don't waste time on it.*
Status: ✅ existing · Requirements: FR-LOOT-01..04

### US-11: Translate texts
*As a translator, I want to edit texts for my language in the browser, so that I can translate without touching code.*
Status: ✅ built (`texts.html`) · Requirements: FR-TEXTS-01..10, FR-I18N-07..08

```gherkin
Scenario: A suggestion waits for the admin
  Given the admin has given me the language role
  When I suggest a Dutch translation for a text
  Then the site still shows the old text
  And the admin sees my suggestion

Scenario: The admin approves a suggestion
  Given a member has suggested a Dutch translation
  When the admin approves it
  Then the site shows that translation

Scenario: English is the admin's
  Given I have the language role
  When I try to change an English text
  Then I am not allowed to
```

### US-12: Restore a backup safely
*As an admin, I want deleted members to stay deleted after I restore a backup, so that we keep our promise to them.*
Status: ✅ function built (no admin button yet) · Requirements: FR-GDPR-06

```gherkin
Scenario: A deleted member is not brought back
  Given Alice deleted her data after the last backup
  When the admin restores that backup and runs the re-apply step
  Then Alice's data is deleted again
  And other members' data is restored
```

### US-13: Link the account a page needs, then continue
*As a member, I want pages that need a game account to take me to my profile to link it, and then back, so that
I don't have to find out on my own where and how to connect Wargaming, Steam or Warframe.*
Status: ✅ built for Wargaming (WoT and WoWs trackers), 2026-09-27 · Requirements: FR-LINK-01..07, FR-AUTH-05
Decisions (owner, 2026-09-27): the trackers require Discord login; the Wargaming token stays in the browser;
only pages that cannot work without the account redirect (others will show a link card).

```gherkin
Scenario: Not logged in
  Given I am not logged in
  When I open the WoT Tracker
  Then I am on my profile page, which asks me to log in with Discord
  And after logging in I am back on the profile with the same request

Scenario: No account linked yet
  Given I am logged in and have no Wargaming account linked
  When I open the WoWs Tracker
  Then my profile explains that the WoWs Tracker needs my Wargaming account
  When I link it through Wargaming
  Then I am sent straight back to the WoWs Tracker

Scenario: Linked, but no access in this browser
  Given my Wargaming account is linked but this browser has no valid Wargaming token
  When I open the WoT Tracker
  Then my profile asks me to connect Wargaming again
```

### US-14: Only link accounts I own
*As a member, I want nobody else to be able to link my game accounts to themselves, so that my data cannot be read
or deleted by someone else.*
Status: ✅ fixed 2026-09-27 (DEF-010) · Requirements: FR-LINK-02, NFR-SEC-09

```gherkin
Scenario: Someone tries to link my account
  Given my WoT account is linked to me
  When another member opens the link callback with my account id but without my Wargaming token
  Then nothing is linked and my account stays mine
```

### US-15: Play Gomoku against the computer
*As an anonymous player, I want to play Gomoku against the computer on a level that suits me, so that I can play
or practise on my own.*
Status: ✅ built 2026-09-27 · Requirements: FR-GOMOKU-01..03, 06..11

```gherkin
Scenario: The computer answers my move
  Given I start a game against the computer as black
  When I place a stone on an empty point
  Then the computer places a white stone within 3 seconds
  And it is my turn again

Scenario: Six in a row is not a win
  Given I have two stones and three stones in a row with one empty point between them
  When I fill the gap so that six stones are in a row
  Then the game goes on
```

### US-16: Play Gomoku with a friend
*As an anonymous player, I want to send a friend a link to a game, so that we can play each other without accounts.*
Status: ✅ built 2026-09-27 · Requirements: FR-GOMOKU-04, 05, 08

```gherkin
Scenario: My friend joins with the link
  Given I created an invite link less than 15 minutes ago
  When my friend opens it and joins
  Then we both see each other's names and the game starts
  And a third person with the same link cannot join

Scenario: The link has expired
  Given my invite link is older than 15 minutes
  When my friend opens it
  Then they are told that the invite has expired
```

### US-17: Earn rewards in Gomoku
*As a member, I want my wins against the computer to unlock something, so that beating the harder levels is worth it.*
Status: ✅ built 2026-09-27 · Requirements: FR-GOMOKU-12, 13, 16

```gherkin
Scenario: First win on Easy while logged in
  Given I am logged in with Discord and have never beaten the computer
  When I win a game against the computer on Easy
  Then the result tells me that I unlocked stone colours

Scenario: Playing without logging in
  Given I am not logged in
  When I win a game against the computer
  Then nothing is unlocked and nothing is stored about me
```

### US-18: Show off my Gomoku look and crown
*As a member, I want to choose how my board and stones look and show a crown after beating Hard, so that others can see it.*
Status: ✅ built 2026-09-27 · Requirements: FR-GOMOKU-14, 15

```gherkin
Scenario: A locked reward cannot be chosen
  Given I have not beaten the computer on Medium
  When I open the Gomoku section of my profile
  Then the 3D stones option is locked

Scenario: The crown
  Given I have beaten the computer on Hard and my crown is shown
  When anyone logged in opens HAKOO Community
  Then my avatar has a crown
```

### US-19: Play chess against the computer or a friend
*As a player, I want to play chess against the computer on a level that suits me, or against a friend with a
link, so that I can play without an account.*
Status: ✅ built 2026-09-28 · Requirements: FR-CHESS-01..07, 11

```gherkin
Scenario: The computer answers my move
  Given I start a game against the computer as white
  When I pick a pawn and one of the marked squares
  Then the computer answers within 3 seconds
  And both moves appear in the move list

Scenario: A move that leaves my king in check is refused
  Given my knight is pinned to my king
  When I try to move the knight
  Then the move is refused and nothing changes

Scenario: Checkmate
  Given it is my move and I can checkmate
  When I checkmate the other king
  Then the game ends and I am the winner
```

### US-20: Play Othello against the computer or a friend
*As a player, I want to play Othello against the computer or a friend, so that I can play without an account.*
Status: ✅ built 2026-09-28 · Requirements: FR-OTHELLO-01..06, 10, 11

```gherkin
Scenario: I see where I can play
  Given it is my turn
  Then dots mark the squares where a disc would flip at least one of my opponent's discs

Scenario: I have no move
  Given it is my turn and no square flips anything
  Then I pass automatically, my opponent moves again, and the page tells me I passed
```

### US-21: Earn rewards in Chess and Othello
*As a member, I want my wins against the computer in Chess and Othello to unlock something too, so that every
game is worth beating on the harder levels.*
Status: ✅ built 2026-09-28 · Requirements: FR-CHESS-08..10, FR-OTHELLO-07..09, FR-GAMES-06

```gherkin
Scenario: First checkmate on Medium while logged in
  Given I am logged in with Discord and started a chess game against the computer on Medium
  When I checkmate the computer
  Then the result tells me that I unlocked 3D pieces

Scenario: All my rewards on the HAKOO Games hub
  Given I am logged in
  When I open HAKOO Games
  Then a badge shows how many of the 12 rewards I have (3 per board game)
```

### US-23: Play chess in the fog
*As a player, I want a chess game where I only see what my pieces can reach, so that chess becomes a game of
scouting, guessing and surprise.*
Status: ✅ built 2026-09-28; memory and "what took my piece" added 2026-10-02 · Requirements: FR-CHESS-12..17

```gherkin
Scenario: The fog
  Given I start a Fog of War game as white
  Then I see my own pieces and the two rows in front of them, and the rest of the board is fog

Scenario: Taking the king
  Given my opponent's king stepped onto a square my queen attacks
  When I take the king
  Then I win the game and the fog lifts

Scenario: Looking at the network traffic does not help
  Given it is a Fog of War game
  When I read what the server sends my browser
  Then it contains nothing I have not seen on the board

Scenario: A piece I lost sight of is remembered
  Given I saw my opponent's pawn arrive on e5
  When my knight moves away and e5 goes back into the fog
  Then a faint pawn with a "?" stays on e5
  And it goes when I see e5 again, or see the pawn on another square

Scenario: I am told what took my piece
  Given my pawn stands on e5
  When my opponent's knight takes it
  Then e5 is marked, I am told which piece took mine,
  And a faint knight with a "?" stands on e5 until I see that square
```

### US-24: See and replay the moves
*As a player, I want to see every move of the game written down and step back through them, so that I can
follow what happened and learn from it.*
Status: ✅ built 2026-09-28 · Requirements: FR-GAMES-09

```gherkin
Scenario: The move list
  Given I play Gomoku and place my first stone in the middle
  Then the move list starts with "1. H8" and the board shows the letters and numbers

Scenario: Looking back
  Given a game with several moves
  When I click the first move in the list
  Then the board shows the game after that move
  And I cannot place a stone until I press ⏭
```

### US-25: Play Hnefatafl (Viking chess)
*As a player, I want to play Hnefatafl as the attackers or the defenders, against the computer or a friend,
so that I can play the old Viking board game without an account.*
Status: ✅ built 2026-09-28 · Requirements: FR-HNEFATAFL-01..11, FR-GAMES-08

```gherkin
Scenario: Where can this piece go?
  Given it is my turn
  When I click one of my pieces
  Then dots mark every square it can move to

Scenario: The king escapes
  Given I play the defenders and the king has an open line to a corner
  When I move the king to the corner
  Then I win the game

Scenario: The computer watches the corners
  Given I play the defenders against Medium
  When the king gets one open line to a corner
  Then the computer blocks it
```

### US-22: One crown, my look in every game
*As a member, I want to choose my Chess and Othello looks like my Gomoku look, and wear one crown for all
the games I beat on Hard.*
Status: ✅ built 2026-09-28 · Requirements: FR-CHESS-09, FR-OTHELLO-08, FR-GAMES-07

```gherkin
Scenario: The crown names my games
  Given I beat the computer on Hard in Gomoku and Chess
  When someone points at my avatar on HAKOO Community
  Then the crown says "Beat the HAKOO computer on Hard: Gomoku, Chess"

Scenario: Hiding one crown
  Given I beat Hard in Chess and Othello
  When I hide my Chess crown on my profile
  Then my crown only names Othello
```

### US-26: Keep my best games
*As a member, I want the game in which I first beat the computer on a level, and my games against other
members, to be kept, so that I can watch them again later.*
Status: ✅ built 2026-09-29 · Requirements: FR-SAVED-01..07

```gherkin
Scenario: My first win on Hard is kept
  Given I am logged in and have never beaten the computer on Hard
  When I win a game against it on Hard
  Then the game is listed under "My games" on my profile and I can watch it move by move

Scenario: A game between two members
  Given two logged-in members play each other with an invite link
  When the game ends
  Then both find it under "My games"

Scenario: The other player deletes their data
  Given I have a saved game against another member
  When they delete all their data
  Then I keep the game, and their name shows as "Deleted player"
```

### US-27: Play Stratego
*As a player, I want to play Stratego against the computer or a friend, with my set-up kept secret, so that
bluffing and guessing work online like at the table.*
Status: ✅ built 2026-09-29 · Requirements: FR-STRATEGO-01..11, FR-GAMES-08

```gherkin
Scenario: Setting up
  Given a new game of Stratego
  When I fill my four rows at random and press Ready
  Then my army stands on the board and the other army shows only the backs of its pieces

Scenario: A battle
  Given my Captain (6) attacks an enemy piece I have not seen
  Then both players see both ranks, and the lower one leaves the board

Scenario: The computer does not peek
  Given I play against the computer
  Then it moves as if it could see only what a human in its seat could see
```

### US-28: Turn the board and look at it from the side
*As a member who has beaten the computer on Medium, I want to tilt and turn the board and see the pieces
standing up, so that the 3D reward feels like a real board on a table.*
Status: ✅ built 2026-10-02 · Requirements: FR-GAMES-10, FR-STRATEGO-12 · Source: the owner's customer
interview ("vrida och vinkla brädet efter 3D-upplåsningen", for all games; Stratego "får inte visa vilken pjäs det är")

```gherkin
Scenario: Turning after the unlock
  Given I have beaten the computer on Medium in Othello
  When I drag the Othello board, or press Tilt + and ↻
  Then the board tilts and turns, the discs stand up, and a click still places a disc on the square I clicked
  And "Reset view" shows the board flat from my side again

Scenario: No unlock, nothing to turn
  Given I am not logged in, or have not beaten Medium in that game
  Then the board has no turning buttons and does not turn when I drag it

Scenario: Stratego from the other side
  Given I play Stratego with the Medium unlock
  When I turn the board more than 90° from my side
  Then my own pieces show their backs, and the enemy pieces I have not seen still show only their backs
```

### US-29: See the discs turn over, at a speed I choose
*As a player, I want the Othello discs to turn over one after the other like on a real board, and to choose
how fast moves are shown, so that I can follow what a move did; as a member who beat Medium, I want 3D discs
that look like the real ones.*
Status: ✅ built 2026-10-02 · Requirements: FR-OTHELLO-12, FR-OTHELLO-13, FR-GAMES-11, FR-GAMES-12 · Source: the owner
("varje flipp kan ta 0,5–0,7 sek", "välja mellan 0,1 till 2 sekunder", a photo of real Othello discs)

```gherkin
Scenario: The flips come one after the other
  Given I play Othello against the computer with the move speed at 0.6 s
  When my disc flips three discs in a line
  Then the nearest disc turns over first and the others follow, and only then does the computer's disc land and turn its own

Scenario: My speed follows me
  Given I am logged in and set the move speed to 1.5 s in Othello
  When I open Chess on another day
  Then the move speed is 1.5 s there too

Scenario: Not logged in
  Given I am not logged in
  When I change the move speed
  Then it works on this page, nothing is stored, and I am told that logging in keeps it

Scenario: The 3D disc
  Given I have beaten the computer on Medium in Othello
  Then my discs are thick, half in one colour and half in the other, and lie flat when I tilt the board
```

### US-30: Find my settings
*As a member, I want my profile split into sections in a menu (accounts, Discord, board games), so that I find a
setting without scrolling through everything; as a player, I want to reach the game settings from the game page.*
Status: ✅ built 2026-10-02 · Requirements: FR-USER-05, FR-GAMES-11 · Source: the owner ("jag hittar inte den
inställningen", "en meny på vänstersida … community/konton, Discord/servergroups, game/colors, 3D och draw speed")

```gherkin
Scenario: One section at a time
  Given I am logged in on my profile
  When I pick "Board games" in the left menu
  Then I see the move speed and my rewards per game, and the six games appear under "Board games" in the menu

Scenario: A link from a game
  Given I am on the Chess page
  When I follow "Change your board and pieces on your profile"
  Then the profile opens on the Chess page of the menu

Scenario: The setting on the game page
  Given I am on a game page, playing or not
  When I press ⚙ in the menu bar
  Then I can change the move speed there
```

### US-31: Make the site look the way I like
*As a visitor, I want to pick a theme, my own colours and a background pattern for hakoo.eu, so that it is
comfortable to read for me; as a member, I want it to follow me to my other devices.*
Status: ✅ built 2026-10-02 · Requirements: FR-SITE-01..04 · Source: the owner ("ändra bakgrunden och ändra färgerna på text")

```gherkin
Scenario: A theme for every page
  Given I open ⚙ → 🎨 Appearance in the language menu
  When I pick "Light" and press Save
  Then every page of hakoo.eu is light, also after a reload

Scenario: Unreadable colours
  Given I pick a dark grey text colour on the dark background
  Then I am warned that the text is hard to read, but I see it on the page and can still press Save
  And after Save a high-contrast box asks "Keep these colours?" and goes back by itself after 5 seconds
  And after going back I am offered a readable colour like the one I picked

Scenario: My look on another device
  Given I saved "HAKOO purple" on my profile
  When I log in on my phone
  Then the pages are purple there too

Scenario: Just looking
  Given I try a theme but close the dialog without Save
  Then the page goes back to how it was, and nothing is stored
```

### US-32: A background of several colours, and a HAKOO theme in the HAKOO colours
*As a visitor, I want a background that blends several colours the way I choose, and a HAKOO theme that looks
like the purple and yellow Community banner, so that the site feels like our clan.*
Status: ✅ built 2026-10-02 · Requirements: FR-SITE-01, FR-SITE-05 · Source: the owner ("flera färger i bakgrunden som
man kan välja hur de ska blandas på vägen", "Hakoo temat bör vara mer det lila och gula")

```gherkin
Scenario: Three colours from the top
  Given I pick "Several colours" under Background
  When I choose dark blue, purple and dark green, move the purple to 40 %, and pick "Circle" from the top
  Then the page and the Community banner show that blend, and Save keeps it

Scenario: A colour that makes the text unreadable
  Given my text is light
  When I add a light beige colour to the background
  Then I am told the text is hard to read on one of the background colours, and Save is off

Scenario: The HAKOO theme
  When I pick the HAKOO theme
  Then the pages are deep purple with headings and links in the banner's yellow
```

### US-33: Play Backgammon
*As a player, I want to play Backgammon against the computer or a friend and roll real-looking dice from a cup,
so that it feels like the board game at home.*
Status: ✅ built 2026-10-03 · Requirements: FR-BACKGAMMON-01..09, FR-GAMES-08 · Source: the owner ("känns som att vi
skulle kunna bygga backgammon nu")

```gherkin
Scenario: My turn
  Given it is my turn in a game of Backgammon
  When I shake the cup and let go
  Then the server's dice land on the table and the checkers I can move glow
  And when I have moved a checker for each die I press Done and the computer answers

Scenario: Hit to the bar
  Given my opponent hits my single checker
  Then it goes to the bar, and on my next turn only the bar glows until it is back in

Scenario: A gammon
  Given I bear off all fifteen checkers before my opponent has borne off any
  Then I win a gammon, worth two points
```

### US-34: Challenge a clanmate
*As a member, I want to challenge someone on the HAKOO Discord server to a board game, from Discord or from the game
page, so that we can play without sending links around.*
Status: ✅ built 2026-10-04 · Requirements: FR-GAMES-CH-01..07 · Source: the owner ("är det möjligt att kunna göra ett
tillägg till discordbotten som göra att man skulle kunna utmana någon på hakoo servern?")

```gherkin
Scenario: A challenge in Discord
  Given I type /challenge @Bob Chess in Discord
  Then the bot posts "@Bob, Alice challenges you to a game of Chess!" with a button
  And only I see my own link to the game

Scenario: Only Bob can accept
  Given Carol presses the button
  Then the page tells her the challenge is for another member
  When Bob presses it and is logged in with Discord
  Then the game starts for both of us, and the bot's post says the game is on

Scenario: Nobody answers
  Given Bob does not answer within 24 hours
  Then the challenge expires and the bot's post says so

### US-35: Play Checkers
*As a player, I want to play checkers against the computer or a friend, with the English rules or with flying kings,
so that I can play the game the way I learned it.*
Status: ✅ built 2026-10-04 · Requirements: FR-CHECKERS-01..09 · Source: the owner ("kan vi ta och göra checkers och
kvarnespelet")

```gherkin
Scenario: A double jump
  Given it is my turn and my man can jump two of my opponent's pieces in a row
  When I click the man and then the last square
  Then both pieces are taken in one move and the computer answers

Scenario: Taking is compulsory
  Given one of my pieces can take
  When I try to move another piece
  Then the page tells me I must take and marks the pieces that can

Scenario: Flying kings
  Given I chose flying kings
  When my king has an open diagonal with an opponent's piece on it
  Then it may fly over it from a distance, and I must choose the jump that takes the most
```

### US-36: Play Nine Men's Morris
*As a player, I want to play nine men's morris (kvarnspel) against the computer or a friend, so that I can play the
old board game without a board at home.*
Status: ✅ built 2026-10-04 · Requirements: FR-MORRIS-01..09 · Source: the owner ("kan vi ta och göra checkers och
kvarnespelet")

```gherkin
Scenario: A mill
  Given I have two stones on a line and the third point is free
  When I place or move a stone there
  Then the opponent's stones I may take are marked and I click one to take it

Scenario: Flying
  Given the game allows flying and I am down to three stones
  Then I may move a stone to any empty point

Scenario: The end
  Given my opponent has three stones left
  When I close a mill and take one
  Then I win
```

### US-37: A brain puzzle a day
*As a member, I want a Nonogram puzzle every day that everyone gets, and see who solved it fastest, so that we have
something small to do together and talk about.*
Status: ✅ built 2026-10-05 · Requirements: FR-NONOGRAM-01..08, FR-GAMES-14 · Source: the owner ("jag funderar på att
skapa lite aktiviteter för att köra hjärngymnastik")

```gherkin
Scenario: Today's puzzle
  Given I am logged in with Discord
  When I open today's Medium puzzle and fill the squares until every clue is right
  Then the picture shows, my time is on today's list and my streak grows

Scenario: Practice without an account
  Given I am not logged in
  When I pick a practice puzzle and reload the page halfway
  Then the squares I filled are still there, and nothing is stored in my browser
```

### US-38: Sudoku and Owldoku every day
*As a member, I want a Sudoku and an Owldoku (the HAKOO owl puzzle) every day, on my phone as well as on a computer,
so that we have more small puzzles to compare times on.*
Status: ✅ built 2026-10-07 · Requirements: FR-PUZZLE-01..02, FR-SUDOKU-01..08, FR-OWLDOKU-01..08 · Source: the owner
("jag skulle vilja skapa sodoku och Owldoku. Tänker mig dem som dagliga och vanliga spel")

```gherkin
Scenario: Today's Owldoku on a phone
  Given I am logged in with Discord on my phone
  When I open today's Easy Owldoku, tap once to cross a square and twice to place an owl
  And one owl I place is wrong
  Then I lose one of three hearts, and opening the puzzle again does not give it back
  And when every owl stands, my time is on today's list

Scenario: Sudoku practice without an account
  Given I am not logged in
  When I pick a practice Sudoku, write digits and notes and reload the page halfway
  Then my digits and notes are still there, two equal digits in a row turn red, and nothing is stored in my browser
```

### US-39: Tell the team when something is wrong
*As a member, I want to report a problem from the page where it happens, so that the team gets what they need to fix
it without me having to explain my phone and the page in Discord.*
Status: ✅ built 2026-10-07 · Requirements: FR-REPORT-01..03 · Source: the owner ("en knapp där medlemmar kan rapportera
fel ... jag fixar en kanal på DC servern, den heter reports-from-hakoo-eu")

```gherkin
Scenario: A report from a game page
  Given I am logged in with Discord and playing chess on my phone
  When I press "🐞 Report a problem", write what happened and send it
  Then the HAKOO bot posts it in #reports-from-hakoo-eu with my name, the page, my browser and screen
```

### US-40: Admins who help me run the site
*As the owner, I want to make other members admins, so that they can help me keep the loot codes clean and approve
translations, and so that we can see how many play each game.*
Status: ✅ built 2026-10-07 · Requirements: FR-ADMIN-01..09, FR-LOOT-03 · Source: the owner ("en admin sida ... kunna
addera en spelare för att kunna vara admin och hjälpa mig med att hålla koll på loot koder och kunna se statestik om
hur många som spelar"; badges Tester and OG HAKOO from the games wish list; "en meny ... flera roller ... ge dom ett namn
då en del tenderar att ändra sitt namn ... rollen Hakoo testers på DC servern")

```gherkin
Scenario: The owner makes a helper an admin
  Given I am the owner and logged in with Discord
  When I open the admin page, type the start of a member's name, pick them and choose Admin
  Then they see the admin page (statistics, loot codes, the translations) but cannot give roles

Scenario: A loot code that does not work
  Given I am an admin and a code is reported as not working
  When I press Block on the admin page
  Then the code is gone from the loot page for everyone, and Unblock brings it back
```

### US-41: Beat them all, from the Farmer to the King
*As a member, I want opponents that get harder step by step, and to carry the title of the strongest one I have
beaten, so that I have something to aim for in Runborg.*
Status: ✅ built 2026-10-08 · Requirements: FR-RUNBORG-01..09 · Source: the owner ("vunnit på de olika platserna ...
1. bonde 2. viking 3. sköldmö 4. jarl 5. kung ... när man vinner mot dom får man det som en titel ... ta bort lab")

```gherkin
Scenario: A new title
  Given I am logged in with Discord and have beaten the Viking
  When I beat the Shieldmaiden on the Winter board
  Then the page says I have a new title, and Winter shows the Shieldmaiden as beaten
  And on my profile I can pick "Shieldmaiden", and it shows after my name in the login corner
```

### US-42: See how the others are doing
*As a member, I want to see the profiles of the members who show theirs, and choose whether to show mine; as an admin,
I want to see every member's profile and how they do in the games.*
Status: ✅ built 2026-10-09 · Requirements: FR-PROFILE-01..06 · Source: the owner ("admins kan se profilen för de som
loggat in ... de som loggar in kan välja att göra deras profil synlig ... inte inloggad ... en blurrad variant";
"kopplade konton om spelaren själv väljer")

```gherkin
Scenario: Showing my profile
  Given I am logged in with Discord and my profile is hidden
  When I tick "Show my profile to the other logged-in members" on my profile
  Then the other members find me in the members list and see my games, my Runborg wins and my puzzle streaks
  But not my linked game accounts, unless I tick that too

Scenario: A visitor
  Given I am not logged in
  When I open the members page
  Then I see a blurred sample profile and a button to log in with Discord, and no member's data
```

### US-43: Runborg against a friend
*As a member, I want to play Runborg against a friend or challenge another member, on the same terms, so that the best
player wins and not the one with the bigger estate.*
Status: ✅ built 2026-10-09 · Requirements: FR-RUNBORG-10..14 · Source: the owner ("spela runborg mot andra spelare och
då ska man naturligtvis ha samma förutsättningar"; "Länk + utmana")

```gherkin
Scenario: A friend with a link
  Given I pick "A friend" as the opponent
  When my friend opens the link I sent
  Then the saga starts for both of us with the same start, and the card I play shows on my friend's page

Scenario: A challenge
  Given I am logged in and challenge a member of the HAKOO Discord server
  Then the bot tells them in Discord, and only they can take the seat with the link
```

### US-44: My own viking
*As a member, I want a viking of my own on my profile, with things I unlock by playing, so that others can see what I
have done.*
Status: ✅ built 2026-10-09 · Requirements: FR-AVATAR-01..06 · Source: the owner ("en avatar som man har på sin
profilsida där man låser upp olika tillbehör och färger"; "Viking men man ska kunna välja man/kvinna och några få färger
till kläderna. med eller utan hjälm med horn eller utan")

```gherkin
Scenario: The crown
  Given I am logged in and have beaten the King in Runborg
  When I open my viking on my profile
  Then the crown is no longer locked, and I can put it on and save
  And with "Use my viking in the login corner" ticked, my viking wears it at the top of every page
```

### US-45: Meet the players
*As a member, I want to see who else plays on HAKOO Games and their vikings in 3D, with what they have won, so that the
games feel like a place with people in it.*
Status: ✅ built 2026-10-09 · Requirements: FR-AVATAR-07..08, FR-PLAYERS-01..05 · Source: the owner ("i den gula delan
kan vi ha spelare som har loggat in för att spela och om man klickar på en spelare så får man upp deras avatar i den rosa
rutan och om de delar med sig så får man upp informationen under avataren"; "en halsband med Gomoku stenar om man klarat
hard"; "de dagliga där får man medaljer"; "en 3D gubbe ... hur kraftigt byggd och lång med sliders")

```gherkin
Scenario: A player's viking
  Given I am logged in on HAKOO Games
  When I click a player in the list at the side
  Then their viking stands on the other side in 3D, and I can turn it
  And their puzzle medals hang beside it
  And under it I see what they have won, if they show their profile

Scenario: The Gomoku necklace
  Given I have won Gomoku on Hard
  When I open my viking on my profile
  Then the necklace of Gomoku stones is no longer locked, and I can wear it
```

### US-46: Finding my way
*As a member, I want the players grouped like on our Discord server with me on top, and a way back from every
page, so that I see who is who and never get lost.*
Status: ✅ built 2026-10-09 · Requirements: FR-PLAYERS-06, FR-NAV-01..03 · Source: the owner ("sorteringen av
spelarna kan väl vara spelaren själv överst sedan i ordning se bild 2"; "där det står you kan vi lägga till admin";
"breadcrumbs uppe till vänster så att man kan klicka tillbaka")

```gherkin
Scenario: The players as on Discord
  Given I am logged in on HAKOO Games
  Then I am first in the players list
  And the others come in the groups of the Discord member list, each with its count, names in their colours
  And an admin has an "Admin" tag by their name

Scenario: Back from the rules
  Given I read the rules of Chess
  When I click the back arrow at the top left
  Then I am on the Chess page, and the bar shows HAKOO › HAKOO Games › Chess
```

### US-47: Dress my viking, and only real players
*As a member, I want to dress my viking from top to toe and stand them in front of a sky I have won, and I want the
players list to show only people who play here, so that it means something to be in it.*
Status: ✅ built 2026-10-09 · Requirements: FR-AVATAR-09..11, FR-PLAYERS-06..07, FR-NAV-01..03 · Source: the owner ("jag
vill kunna välja byxor, kjol, lång tunika eller klänning samt kunna välja färger sedan skor/barfota"; "har man klarat av
sheildmaiden så kan man få bakgrunden till det board som man vann på"; "jag vill bara sedan de som spelat på sidan ska
synas"; "tillbaka pilen kan vara till vänster om loggan")

```gherkin
Scenario: A sky won in Runborg
  Given I have beaten the Shieldmaiden at Winter Dusk
  When I open my viking on my profile
  Then the Winter Dusk background is no longer locked, and my viking stands in front of it on HAKOO Games

Scenario: Only players
  Given a member of the Discord server who has only logged in
  Then they are not in the players list until they have played something
```

### US-48: Players beside every game
*As a member, I want to see the players and their vikings beside every game, and on a puzzle's page who is taking
on today's puzzle and how they are doing, so that I can measure myself against the others.*
Status: ✅ built 2026-10-09 · Requirements: FR-PLAYERS-08..09, FR-AVATAR-10 · Source: the owner ("kan vi lägga till
spelar och profilbilden på alla spelsidor förutom de dagliga spelen där dagens deltagare visas i en lista där man har
dagens och gårdagens tider samt hur många dagar i sträck man har klarat det och sedan hur många gånger man klarat det
totalt"; "till bakgrunderna till vikingen så kanske vi kan få med lite mer vad som händer på det boardet")

```gherkin
Scenario: Today's puzzlers
  Given I am logged in on the Sudoku page on a wide screen
  Then the list at the left shows who has opened today's puzzle, with today's and yesterday's times,
       their days in a row and how many they have solved
  And my own viking stands at the right
```

### US-49: My own colours
*As a member, I want to dress my viking in the colours I have won in the games, so that every game I win shows.*
Status: ✅ built 2026-10-09 · Requirements: FR-AVATAR-12 · Source: the owner ("vi har ju låst upp lite färger och de
skulle ju kunna hamna i högra kolumnen ... väljer man den så klickar man sedan bland sina upplåsta färger och så fyller man
den med den färg man valde. vi kanske kan förbereda för lite mönster på kläderna")

```gherkin
Scenario: Gold trousers
  Given I have won Gomoku on Easy
  When I choose the ring after the colours of the legs, and then gold among the colours I have won
  Then my viking's trousers are gold, and the ring shows gold
```

### US-50: Ranks, and admins who help with roles
*As the owner, I want my admins to give roles and ranks with a right-click, and the members to carry a rank from what
they pay, so that supporters are seen and I do not have to do everything myself.*
Status: ✅ built 2026-10-10 · Requirements: FR-RANK-01..03, FR-ADMIN-09, FR-PLAYERS-06, FR-PLAYERS-08 · Source: the owner
("admin kan högerklicka i members eller på en profil i rutan players och tilldela dom en roll så admin kan tilldela allt
utom admin"; "Jarl för de som betalar VIP ... viking eller sköldmö ... bonde eller soldat"; "topplistan skulle vara på
höger sida där profilen är och spelarkolumnen alltid på vänster")

```gherkin
Scenario: A new Jarl
  Given I am an admin on HAKOO Games
  When I right-click a member in the players list and choose Jarl
  Then they stand among the Jarls, with the tag Jarl by their name
```

### US-51: The King, the Queen and the OGs
*As the owner, I want to be shown as the King, a co-founder to be able to be Queen, and the OG HAKOO members to pick any
rank, so that the people who built HAKOO stand out.*
Status: ✅ built 2026-10-10 · Requirements: FR-RANK-04 · Source: the owner ("har man fått gruppen OG hakoo så får man
tillgång till alla bonde/soldat, viking/sköldmö och jarl och kan välja"; "Lauritsen83 ska kunna välja queen/drottning
hon är en av grundarna av hakoo och istället för owner så vill jag ha kung/king på mig")

### US-52: Blockdoku, a high score every day
*As a member, I want to play Blockdoku (Block Crush) on HAKOO Games and see who has the best score today, so that I
have a quick game to beat my friends at every day.*
Status: ✅ built 2026-10-14 · Requirements: FR-BLOCKDOKU-01..09 · Source: the owner ("kan vi lägga till blockdoku /
block crush som ett dagligt spel"; high score until no block fits, Tetris pieces, 9 points per row, column or box with
bonuses, names only for logged-in members and guests as anonymous, three colours for the top of the day, CET day)

### US-53: Path of Berserk, a viking beat 'em up
*As a player, I want to fight my way through a viking raid with my own viking, with the keyboard, a game controller or
my phone, and see who has the best score this month and this year, so that HAKOO has an arcade game to compete in.*
Status: 🚧 step 1 built 2026-10-17 (the game, the lists, the Arcade shelf); step 2 (rewards) proposed; step 3
(Mysteries, the phone as controller) not started · Requirements: FR-BERSERK-01..14 · Source: the owner ("en vikinga
variant på streets of Rage ... path of berserk"; an Arcade category, keyboard and controller, the phone as controller
in Mysteries, our viking first and harder graphics later, lists per month and for the year, a sword, a battle axe,
colours, fly agarics for the year's best)

```gherkin
Scenario: Fight through the raid
  Given I open Path of Berserk on HAKOO Games
  When I press Play
  Then my viking stands on the beach and the first raiders come
  And the way on opens with "GO →" when the last of them is down

Scenario: Berserk
  Given my Berserk meter is full
  When I press the Berserk button
  Then for 6 seconds I hit twice as hard and take no damage

Scenario: A place in the month's list
  Given I am logged in with Discord
  When I beat Jarl Ulfr
  Then the server counts my score and I see my place in this month's and this year's list

### US-54: Yatzy on the dice shelf
*As a player, I want to play Yatzy with the 3D dice, alone, against the computer or with friends, so that HAKOO Games has
the classic dice game next to Backgammon and Ludo.*
Status: ✅ built 2026-10-18 (in Docker; not yet live) · Requirements: FR-YATZY-01..15 · Source: the owner ("kan vi göra ett
yatzy https://sv.wikipedia.org/wiki/Yatzy och lägga in det under tärningsspel")

```gherkin
Scenario: Find it on the dice shelf
  Given I open HAKOO Games' "🎲 Dice games" shelf
  When I click Yatzy
  Then the Yatzy page opens

Scenario: Keep dice and roll again
  Given it is my turn and I have rolled once
  When I click two dice and roll again
  Then those two dice stay and the server rolls the other three
  And after the third roll I can only choose a row

Scenario: Write a row
  Given I have rolled
  When I click a number on my score sheet
  Then it is written in that row and the next player (or the computer) plays

Scenario: The same choices on another device
  Given I am logged in and start a game with 5 players on Hard in forced order
  When I open a new game on another device, logged in as the same member
  Then the form starts with 5 players, Hard and forced order
```
