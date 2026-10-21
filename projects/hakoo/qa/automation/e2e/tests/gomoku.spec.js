// Gomoku (games/gomoku.html): system tests in the browser.
// Traceability: FR-GOMOKU-01..09, FR-GAMES-01..03; school spec UC-02, UC-03, UC-05, UC-09..11, UC-20, NFR-01.6.
const { test, expect } = require('@playwright/test');

const SHOTS = '../../test-results/gomoku';

async function startComputerGame(page, { name = 'Tester', color = 'black', level = 'medium' } = {}) {
  await page.goto('/games/gomoku.html');
  await page.click('#go-computer');
  await page.fill('#name', name);
  await page.check(`input[name=color][value=${color}]`);
  await page.check(`input[name=difficulty][value=${level}]`);
  await page.click('#start');
  await expect(page.locator('#v-game')).toBeVisible();
}

const point = (page, r, c) => page.locator(`#board .pt[data-r="${r}"][data-c="${c}"]`);

test('TC-E2E-060: Gomoku stores nothing in the browser and calls no third party', async ({ page, context, baseURL }) => {
  const requests = [];
  page.on('request', r => requests.push(r.url()));
  await startComputerGame(page);
  await point(page, 7, 7).click();
  await expect(page.locator('#move-count')).toHaveText('2');
  expect(await context.cookies()).toEqual([]);
  expect(await page.evaluate(() => [localStorage.length, sessionStorage.length])).toEqual([0, 0]);
  const origin = new URL(baseURL).origin;
  expect(requests.filter(u => !u.startsWith(origin))).toEqual([]);
});

test('TC-E2E-061: play a move against the computer and get an answer', async ({ page }, info) => {
  await startComputerGame(page, { name: 'Woody', level: 'hard' });
  await expect(page.locator('#p-black .pname')).toHaveText('Woody (you)');
  await expect(page.locator('#p-white .pname')).toHaveText('Computer (Hard)');
  await expect(page.locator('#turn')).toHaveText('Your turn (Black)');
  await point(page, 7, 7).click();
  await expect(page.locator('#board .pt.w')).toHaveCount(1);
  await expect(page.locator('#board .pt.b')).toHaveCount(1);
  await expect(page.locator('#board .pt.last.w')).toHaveCount(1);          // FR-08.5
  await expect(page.locator('#turn')).toHaveText('Your turn (Black)');
  await page.screenshot({ path: `${SHOTS}-computer-${info.project.name}.png`, fullPage: true });
});

test('TC-E2E-062: an occupied point is refused with a message', async ({ page }) => {
  await startComputerGame(page);
  await point(page, 7, 7).click();
  await expect(page.locator('#move-count')).toHaveText('2');
  await point(page, 7, 7).click();
  await expect(page.locator('#error')).toContainText('taken');
  await expect(page.locator('#move-count')).toHaveText('2');
});

test('TC-E2E-063: the board can be played with the keyboard (AC-02-09)', async ({ page, browserName }, info) => {
  test.skip(info.project.name === 'mobile', 'no keyboard on the phone profile');
  await startComputerGame(page);
  await page.locator('#board .pt[tabindex="0"]').focus();
  await page.keyboard.press('ArrowRight');
  await page.keyboard.press('Enter');
  await expect(point(page, 7, 8)).toHaveClass(/\bb\b/);
  await expect(page.locator('#move-count')).toHaveText('2');
});

test('TC-E2E-064: two friends play through an invite link', async ({ browser, baseURL }, info) => {
  const alice = await (await browser.newContext()).newPage();
  const bob = await (await browser.newContext()).newPage();
  await alice.goto('/games/gomoku.html');
  await alice.click('#go-friend');
  await expect(alice.locator('#difficulty')).toBeHidden();                  // FR-05.4
  await alice.fill('#name', 'Alice');
  await alice.check('input[name=color][value=black]');
  await alice.click('#start');
  await expect(alice.locator('#v-wait')).toBeVisible();
  await expect(alice.locator('#invite-timer')).toContainText('expires in');
  const link = await alice.locator('#invite-link').inputValue();
  expect(link).toMatch(/#join=[0-9a-f]{16}\.[0-9a-f]{32}$/);

  await bob.goto(link);
  await expect(bob.locator('#v-join')).toBeVisible();
  await bob.fill('#join-name', 'Bob');
  await bob.click('#v-join button[type=submit]');
  await expect(bob.locator('#p-black .pname')).toHaveText('Alice');
  await expect(bob.locator('#p-white .pname')).toHaveText('Bob (you)');
  await expect(bob).not.toHaveURL(/join=/);                                  // the invite is used up

  await expect(alice.locator('#v-game')).toBeVisible({ timeout: 5000 });   // FR-07.5, by polling
  await expect(alice.locator('#p-white .pname')).toHaveText('Bob');
  await point(alice, 7, 7).click();
  await expect(point(bob, 7, 7)).toHaveClass(/\bb\b/, { timeout: 5000 });  // NFR-02.6 (about 1 s here)
  await expect(bob.locator('#turn')).toHaveText('Your turn (White)');

  await bob.click('#resign');
  await bob.click('#confirm-ok');
  await expect(bob.locator('#result-title')).toHaveText('Alice won');
  await expect(alice.locator('#result-title')).toHaveText('You won! 🎉', { timeout: 5000 });
  await alice.screenshot({ path: `${SHOTS}-friend-${info.project.name}.png`, fullPage: true });
});

test('TC-E2E-065: the rules explain the exact-five rule and can start a game', async ({ page }) => {
  await page.goto('/games/gomoku.html');
  await page.click('#go-rules');
  await expect(page).toHaveURL(/gomoku-rules.html$/);
  const rules = page.locator('main');
  await expect(rules).toContainText('exactly five');
  await expect(rules).toContainText('Six or more in a row does not win');
  await expect(rules).toContainText('draw');
  await page.click('.rhead a.play');
  await expect(page.locator('#v-home')).toBeVisible();
});

test('TC-E2E-066: a reload keeps the game (the token lives in the URL #)', async ({ page }) => {
  await startComputerGame(page);
  await point(page, 7, 7).click();
  await expect(page.locator('#move-count')).toHaveText('2');
  await page.reload();
  await expect(page.locator('#v-game')).toBeVisible();
  await expect(page.locator('#move-count')).toHaveText('2');
});

test('TC-E2E-067: the 19x19 board fits a 360 px wide phone without scrolling sideways (NFR-01.6)', async ({ browser }, info) => {
  test.skip(info.project.name !== 'chromium', 'one viewport check is enough');
  const page = await (await browser.newContext({ viewport: { width: 360, height: 740 }, hasTouch: true })).newPage();
  await page.goto('/games/gomoku.html');
  await page.click('#go-computer');
  await page.check('input[name=size][value="19"]');
  await page.check('input[name=color][value=black]');
  await page.click('#start');
  await expect(page.locator('#board .pt')).toHaveCount(361);
  expect(await page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(360);
  await point(page, 9, 9).tap();
  await expect(page.locator('#move-count')).toHaveText('2');
  await page.screenshot({ path: `${SHOTS}-360px.png`, fullPage: true });
});

test('TC-E2E-102: coordinates on the board, the move list, and looking back through the game', async ({ page }) => {
  await startComputerGame(page);
  await expect(page.locator('#board .lbl.col').first()).toHaveText('A');
  await expect(page.locator('#board .lbl.row').first()).toHaveText('15');
  await point(page, 7, 7).click();
  await expect(page.locator('#movelist .mv').first()).toHaveText('H8');
  await expect(page.locator('#movelist .mv')).toHaveCount(2);                 // the computer's answer too
  await page.click('#movelist [data-ply="1"]');
  await expect(page.locator('#turn')).toHaveText('Looking back: after move 1 of 2');
  await expect(page.locator('#board .pt.b, #board .pt.w')).toHaveCount(1);
  await point(page, 0, 0).click();
  await expect(page.locator('#error')).toContainText('earlier move');           // no moves while looking back
  await page.click('#mv-first');
  await expect(page.locator('#board .pt.b, #board .pt.w')).toHaveCount(0);
  await page.click('#mv-last');
  await expect(page.locator('#board .pt.b, #board .pt.w')).toHaveCount(2);
  await expect(page.locator('#turn')).toHaveText('Your turn (Black)');
});
