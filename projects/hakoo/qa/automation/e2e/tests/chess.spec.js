// Chess (games/chess.html): system tests in the browser.
// Traceability: FR-CHESS-01..07, FR-CHESS-11..17, FR-GAMES-01..03, US-19, US-23.
const { test, expect } = require('@playwright/test');

const SHOTS = '../../test-results/chess';

async function startComputerGame(page, { name = 'Tester', color = 'white', level = 'easy' } = {}) {
  await page.goto('/games/chess.html');
  await page.click('#go-computer');
  await page.fill('#name', name);
  await page.check(`input[name=color][value=${color}]`);
  await page.check(`input[name=difficulty][value=${level}]`);
  await page.click('#start');
  await expect(page.locator('#v-game')).toBeVisible();
}

// A square by its name, e.g. sq(page, 'e2'); row 0 is rank 8.
const sq = (page, name) => page.locator(`#board .sq[data-r="${8 - +name[1]}"][data-c="${'abcdefgh'.indexOf(name[0])}"]`);

async function move(page, from, to) {
  await sq(page, from).click();
  await sq(page, to).click();
}

test('TC-E2E-080: Chess stores nothing in the browser and calls no third party', async ({ page, context, baseURL }) => {
  const requests = [];
  page.on('request', r => requests.push(r.url()));
  await startComputerGame(page);
  await move(page, 'e2', 'e4');
  await expect(page.locator('#movelist li')).toHaveCount(1);
  expect(await context.cookies()).toEqual([]);
  expect(await page.evaluate(() => [localStorage.length, sessionStorage.length])).toEqual([0, 0]);
  const origin = new URL(baseURL).origin;
  expect(requests.filter(u => !u.startsWith(origin))).toEqual([]);
});

test('TC-E2E-081: pick a piece, see where it can go, move, and get the computer\'s answer', async ({ page }, info) => {
  await startComputerGame(page, { name: 'Woody', level: 'medium' });
  await expect(page.locator('#p-white .pname')).toHaveText('Woody (you)');
  await expect(page.locator('#p-black .pname')).toHaveText('Computer (Medium)');
  await expect(page.locator('#turn')).toHaveText('Your turn (White)');
  await sq(page, 'g1').click();
  await expect(page.locator('#board .sq.target')).toHaveCount(2);            // Nf3, Nh3
  await sq(page, 'f3').click();
  await expect(page.locator('#movelist li').first()).toContainText('Nf3');
  await expect(page.locator('#movelist li .mv').nth(1)).not.toBeEmpty();     // the computer answered
  await expect(page.locator('#turn')).toHaveText('Your turn (White)');
  await expect(page.locator('#move-count')).toHaveText('2');
  await expect(page.locator('#board .sq.to')).toHaveCount(1);                 // the last move is marked
  await page.screenshot({ path: `${SHOTS}-computer-${info.project.name}.png`, fullPage: true });
});

test('TC-E2E-082: wrong moves are refused with a message, and nothing moves', async ({ page }) => {
  await startComputerGame(page);
  await sq(page, 'e7').click();
  await expect(page.locator('#error')).toContainText('Pick one of your own pieces');
  await move(page, 'e2', 'e5');
  await expect(page.locator('#error')).toContainText('not allowed');
  await expect(page.locator('#movelist li')).toHaveCount(0);
});

test('TC-E2E-083: playing black, the board is turned so black is at the bottom', async ({ page }) => {
  await startComputerGame(page, { color: 'black' });
  await expect(page.locator('#board .sq').first()).toHaveAttribute('aria-label', /^h1,/);
  await expect(page.locator('#movelist li .mv').first()).not.toBeEmpty();   // the computer (white) opened
  await expect(page.locator('#turn')).toHaveText('Your turn (Black)');
});

test('TC-E2E-084: the board can be played with the keyboard', async ({ page }, info) => {
  test.skip(info.project.name === 'mobile', 'no keyboard on the phone profile');
  await startComputerGame(page);
  await sq(page, 'e2').focus();
  await page.keyboard.press('Enter');                                         // pick the pawn
  await page.keyboard.press('ArrowUp');
  await page.keyboard.press('ArrowUp');
  await page.keyboard.press('Enter');                                         // e4
  await expect(page.locator('#movelist li').first()).toContainText('e4');
});

test('TC-E2E-085: two friends play through an invite link, and a check is shown', async ({ browser }, info) => {
  const alice = await (await browser.newContext()).newPage();
  const bob = await (await browser.newContext()).newPage();
  await alice.goto('/games/chess.html');
  await alice.click('#go-friend');
  await expect(alice.locator('#difficulty')).toBeHidden();
  await alice.fill('#name', 'Alice');
  await alice.check('input[name=color][value=white]');
  await alice.click('#start');
  await expect(alice.locator('#invite-link')).toHaveValue(/#join=[0-9a-f]{16}\.[0-9a-f]{32}$/);
  const link = await alice.locator('#invite-link').inputValue();

  await bob.goto(link);
  await bob.fill('#join-name', 'Bob');
  await bob.click('#v-join button[type=submit]');
  await expect(bob.locator('#p-black .pname')).toHaveText('Bob (you)');
  await expect(alice.locator('#v-game')).toBeVisible({ timeout: 5000 });

  await move(alice, 'e2', 'e4');
  await expect(sq(bob, 'e4')).toHaveAttribute('aria-label', 'e4, white pawn', { timeout: 5000 });
  await move(bob, 'f7', 'f6');
  await expect(sq(alice, 'f6')).toHaveAttribute('aria-label', 'f6, black pawn', { timeout: 5000 });
  await move(alice, 'd1', 'h5');                                               // check
  await expect(bob.locator('#turn')).toHaveText('Check! Your turn (Black)', { timeout: 5000 });
  await expect(sq(bob, 'e8')).toHaveClass(/\bcheck\b/);

  await bob.click('#resign');
  await bob.click('#confirm-ok');
  await expect(bob.locator('#result-title')).toHaveText('Alice won');
  await expect(alice.locator('#result-title')).toHaveText('You won! 🎉', { timeout: 5000 });
  await alice.screenshot({ path: `${SHOTS}-friend-${info.project.name}.png`, fullPage: true });
});

test('TC-E2E-086: the rules (their own page) explain checkmate, the special moves and draws, and lead back to the game', async ({ page }) => {
  await page.goto('/games/chess.html');
  await page.click('#go-rules');
  await expect(page).toHaveURL(/chess-rules.html$/);
  const rules = page.locator('main');
  for (const word of [/checkmate/i, /castling/i, /en passant/i, /promotion/i, /stalemate/i]) await expect(rules).toContainText(word);
  await page.click('.rhead a.play');
  await expect(page.locator('#v-home')).toBeVisible();
});

test('TC-E2E-087: a reload keeps the game, and the board fits a 360 px phone', async ({ browser }, info) => {
  test.skip(info.project.name !== 'chromium', 'one viewport check is enough');
  const page = await (await browser.newContext({ viewport: { width: 360, height: 740 }, hasTouch: true })).newPage();
  await startComputerGame(page);
  await sq(page, 'd2').tap();
  await sq(page, 'd4').tap();
  await expect(page.locator('#movelist li .mv').nth(1)).not.toBeEmpty();
  expect(await page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(360);
  await page.reload();
  await expect(page.locator('#v-game')).toBeVisible();
  await expect(page.locator('#movelist li .mv').nth(1)).not.toBeEmpty();
  await page.screenshot({ path: `${SHOTS}-360px.png`, fullPage: true });
});

test('TC-E2E-100: Fog of War against the computer: the fog, the shadows, the hidden answer, and no rewards', async ({ page }, info) => {
  await page.goto('/games/chess.html');
  await page.click('#go-computer');
  await page.check('input[name=variant][value=fog]');
  await expect(page.locator('#reward-note')).toContainText('does not count for rewards');
  await page.check('input[name=color][value=white]');
  await page.click('#start');
  await expect(page.locator('#variant-tag')).toBeVisible();
  await expect(page.locator('#board .sq.fog')).toHaveCount(32);                // ranks 5-8 at the start
  await expect(page.locator('#board .sq.ghost')).toHaveCount(16);              // the opponent's army, remembered where it started
  await expect(sq(page, 'e8')).toHaveAttribute('aria-label', 'e8, fog, last seen: black king');
  await expect(sq(page, 'e6')).toHaveAttribute('aria-label', 'e6, fog');       // nothing was ever seen there
  await move(page, 'e2', 'e4');
  await expect(page.locator('#movelist li').first()).toHaveText(/e4\s*\?/);    // the computer's move is hidden
  await expect(page.locator('#turn')).toHaveText('Your turn (White)');
  await page.screenshot({ path: `${SHOTS}-fog-${info.project.name}.png`, fullPage: true });
});

test('TC-E2E-155: Fog of War: a piece out of sight stays as a shadow with a ?, and the loser sees what took a piece', async ({ browser }, info) => {
  const alice = await (await browser.newContext()).newPage();
  const bob = await (await browser.newContext()).newPage();
  await alice.goto('/games/chess.html');
  await alice.click('#go-friend');
  await alice.fill('#name', 'Alice');
  await alice.check('input[name=color][value=white]');
  await alice.check('input[name=variant][value=fog]');
  await alice.click('#start');
  await expect(alice.locator('#invite-link')).toHaveValue(/#join=[0-9a-f]{16}\.[0-9a-f]{32}$/);
  const link = await alice.locator('#invite-link').inputValue();
  await bob.goto(link);
  await bob.fill('#join-name', 'Bob');
  await bob.click('#v-join button[type=submit]');
  await expect(alice.locator('#v-game')).toBeVisible({ timeout: 5000 });

  // Bob (black) remembers white's army where it started: shadows, each with a ?
  await expect(bob.locator('#board .sq.ghost')).toHaveCount(16);
  await expect(sq(bob, 'g1')).toHaveAttribute('aria-label', 'g1, fog, last seen: white knight');
  await expect(sq(bob, 'g1').locator('.pc')).toHaveText(/♞/);
  expect(await sq(bob, 'g1').evaluate(el => getComputedStyle(el, '::after').content)).toBe('"?"');

  await move(alice, 'g1', 'f3');
  await expect(bob.locator('#turn')).toHaveText('Your turn (Black)', { timeout: 5000 });
  await move(bob, 'e7', 'e5');
  await expect(alice.locator('#turn')).toHaveText('Your turn (White)', { timeout: 5000 });
  await expect(sq(alice, 'e5')).toHaveAttribute('aria-label', 'e5, black pawn');   // seen by the knight on f3
  await move(alice, 'f3', 'e5');                                               // takes the pawn

  // Bob lost the pawn: he is told where, by what, and the knight is a shadow on that square.
  await expect(bob.locator('#turn')).toHaveText('Your pawn was taken on e5 by the white knight. Your turn (Black)', { timeout: 5000 });
  await expect(sq(bob, 'e5')).toHaveClass(/\btook\b/);
  await expect(sq(bob, 'e5')).toHaveClass(/\bghost\b/);
  await expect(sq(bob, 'e5')).toHaveAttribute('aria-label', 'e5, fog, last seen: white knight');
  await expect(sq(bob, 'g1')).toHaveAttribute('aria-label', 'g1, fog');          // the knight is not remembered on g1 any more
  await expect(sq(bob, 'b1')).toHaveAttribute('aria-label', 'b1, fog, last seen: white knight');
  await bob.screenshot({ path: `${SHOTS}-fog-ghosts-${info.project.name}.png`, fullPage: true });

  // White took it and knows: no mark on e5 for Alice.
  await expect(sq(alice, 'e5')).not.toHaveClass(/\btook\b/);

  // Bob moves on: the note goes, the shadow stays until he sees the square.
  await move(bob, 'g8', 'f6');
  await expect(alice.locator('#turn')).toHaveText('Your turn (White)', { timeout: 5000 });
  await expect(sq(bob, 'e5')).not.toHaveClass(/\btook\b/);
  await expect(sq(bob, 'e5')).toHaveClass(/\bghost\b/);
});

test('TC-E2E-101: the rules explain Fog of War', async ({ page }) => {
  await page.goto('/games/chess-rules.html');
  await expect(page.locator('#fog')).toContainText('Fog of War');
  await expect(page.locator('#fog')).toContainText('takes the other king wins');
});

test('TC-E2E-122: on a wide screen the move list stands to the right of the board; on a phone below it', async ({ browser }, info) => {
  test.skip(info.project.name !== 'chromium', 'two viewports are checked here');
  for (const [width, beside] of [[1280, true], [390, false]]) {
    const page = await (await browser.newContext({ viewport: { width, height: 900 } })).newPage();
    await startComputerGame(page);
    await move(page, 'e2', 'e4');
    await expect(page.locator('#movelist .mv').nth(1)).not.toBeEmpty();
    const board = await page.locator('#board').boundingBox(), list = await page.locator('.movelist-card').boundingBox();
    if (beside) {
      expect(list.x).toBeGreaterThanOrEqual(board.x + board.width);
      expect(list.y).toBeLessThan(board.y + board.height);
      await page.screenshot({ path: `${SHOTS}-wide.png` });
    } else {
      expect(list.y).toBeGreaterThanOrEqual(board.y + board.height);
    }
  }
});
