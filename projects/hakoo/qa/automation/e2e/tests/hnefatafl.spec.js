// Hnefatafl (games/hnefatafl.html): system tests in the browser.
// Seats: black = the attackers (move first), white = the defenders with the king.
// Fixture: Alice has won on every level (red attackers, bone defenders, 3D, the Fjord board).
// Traceability: FR-HNEFATAFL-05, 06, 08, 10, 11, FR-GAMES-08, FR-GAMES-09, US-25.
const { test, expect } = require('@playwright/test');
const { USERS, loginAs } = require('./helpers');

const SHOTS = '../../test-results/hnefatafl';

async function startComputerGame(page, { name = 'Tester', color = 'black', level = 'easy' } = {}) {
  await page.goto('/games/hnefatafl.html');
  await page.click('#go-computer');
  await page.fill('#name', name);
  await page.check(`input[name=color][value=${color}]`);
  await page.check(`input[name=difficulty][value=${level}]`);
  await page.click('#start');
  await expect(page.locator('#v-game')).toBeVisible();
}

const sq = (page, r, c) => page.locator(`#board .tsq[data-r="${r}"][data-c="${c}"]`);

test('TC-E2E-104: Hnefatafl stores nothing in the browser and calls no third party', async ({ page, context, baseURL }) => {
  const requests = [];
  page.on('request', r => requests.push(r.url()));
  await startComputerGame(page);
  await sq(page, 3, 0).click();
  await sq(page, 2, 0).click();
  await expect(page.locator('#move-count')).toHaveText('2');
  expect(await context.cookies()).toEqual([]);
  expect(await page.evaluate(() => [localStorage.length, sessionStorage.length])).toEqual([0, 0]);
  const origin = new URL(baseURL).origin;
  expect(requests.filter(u => !u.startsWith(origin))).toEqual([]);
});

test('TC-E2E-105: picking a piece shows where it can go; a move is made and the computer answers', async ({ page }, info) => {
  await startComputerGame(page, { name: 'Woody', level: 'medium' });
  await expect(page.locator('#p-black .pname')).toHaveText('Woody (you)');
  await expect(page.locator('#p-white .pname')).toHaveText('Computer (Medium)');
  await expect(page.locator('#turn')).toHaveText('Your turn (Attackers)');
  await expect(page.locator('#count-black')).toHaveText('24');
  await expect(page.locator('#count-white')).toHaveText('13');
  await expect(page.locator('#board .tsq.throne.k')).toHaveCount(1);
  await sq(page, 3, 0).click();
  await expect(sq(page, 3, 0)).toHaveClass(/\bsel\b/);
  await expect(page.locator('#board .tsq.to')).toHaveCount(6);                   // a8: up to a9, a10 (not the corner), right to b8..e8
  await sq(page, 2, 0).click();
  await expect(page.locator('#move-count')).toHaveText('2');
  await expect(sq(page, 2, 0)).toHaveClass(/\ba\b/);
  await expect(page.locator('#board .tsq.last')).toHaveCount(1);
  await expect(page.locator('#movelist li').first()).toContainText('a8–a9');
  await page.screenshot({ path: `${SHOTS}-computer-${info.project.name}.png`, fullPage: true });
});

test('TC-E2E-106: wrong clicks explain what to do', async ({ page }) => {
  await startComputerGame(page);
  await sq(page, 8, 8).click();
  await expect(page.locator('#error')).toContainText('Pick one of your pieces');
  await sq(page, 3, 5).click();
  await expect(page.locator('#error')).toContainText('not your piece');
  await sq(page, 0, 3).click();
  await sq(page, 0, 0).click();
  await expect(page.locator('#error')).toContainText('Only the king');
  await expect(page.locator('#move-count')).toHaveText('0');
});

test('TC-E2E-107: two friends play through an invite link', async ({ browser }, info) => {
  const alice = await (await browser.newContext()).newPage();
  const bob = await (await browser.newContext()).newPage();
  await alice.goto('/games/hnefatafl.html');
  await alice.click('#go-friend');
  await alice.fill('#name', 'Alice');
  await alice.check('input[name=color][value=black]');
  await alice.click('#start');
  await expect(alice.locator('#invite-link')).toHaveValue(/#join=[0-9a-f]{16}\.[0-9a-f]{32}$/);
  const link = await alice.locator('#invite-link').inputValue();

  await bob.goto(link);
  await bob.fill('#join-name', 'Bob');
  await bob.click('#v-join button[type=submit]');
  await expect(bob.locator('#p-white .pname')).toHaveText('Bob (you)');
  await expect(alice.locator('#v-game')).toBeVisible({ timeout: 5000 });

  await sq(alice, 3, 0).click();
  await sq(alice, 2, 0).click();
  await expect(sq(bob, 2, 0)).toHaveClass(/\ba\b/, { timeout: 5000 });
  await expect(bob.locator('#turn')).toHaveText('Your turn (Defenders)');
  await sq(bob, 5, 5).click();                                                   // the king is boxed in by his defenders
  await expect(bob.locator('#error')).toContainText('cannot move');

  await alice.click('#resign');
  await alice.click('#confirm-ok');
  await expect(alice.locator('#result-title')).toHaveText('Bob won');
  await expect(bob.locator('#result-title')).toHaveText('You won! 🎉', { timeout: 5000 });
  await bob.screenshot({ path: `${SHOTS}-friend-${info.project.name}.png`, fullPage: true });
});

test('TC-E2E-108: the rules explain the special squares, captures and how to win; a reload keeps the game', async ({ page }) => {
  await page.goto('/games/hnefatafl.html');
  await page.click('#go-rules');
  const rules = page.locator('main');
  for (const word of [/throne/, /corner/, /shieldwall/i, /edge fort/, /surround/, /third time/]) await expect(rules).toContainText(word);
  await page.click('.rhead a.play');
  await startComputerGame(page);
  await sq(page, 3, 0).click();
  await sq(page, 2, 0).click();
  await expect(page.locator('#move-count')).toHaveText('2');
  await page.reload();
  await expect(page.locator('#v-game')).toBeVisible();
  await expect(page.locator('#move-count')).toHaveText('2');
});

test('TC-E2E-109: the board fits a 360 px phone and can be played by touch', async ({ browser }, info) => {
  test.skip(info.project.name !== 'chromium', 'one viewport check is enough');
  const page = await (await browser.newContext({ viewport: { width: 360, height: 740 }, hasTouch: true })).newPage();
  await startComputerGame(page);
  await sq(page, 3, 0).tap();
  await sq(page, 2, 0).tap();
  await expect(page.locator('#move-count')).toHaveText('2');
  expect(await page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(360);
  await page.screenshot({ path: `${SHOTS}-360px.png`, fullPage: true });
});

test('TC-E2E-110: a member who won everything plays with their own look and sees it on the profile', async ({ page, context, baseURL }, info) => {
  await loginAs(context, baseURL, USERS.alice, 'Alice');
  await page.goto('/games/hnefatafl.html');
  await expect(page.locator('#rewards li.got')).toHaveCount(3);
  const vars = await page.evaluate(() => ['--piece-black', '--tb-bg'].map(n => getComputedStyle(document.documentElement).getPropertyValue(n).trim()));
  expect(vars).toEqual(['#b3261e', '#2f6d8c']);
  expect(await page.evaluate(() => document.documentElement.classList.contains('t3d'))).toBe(true);
  await page.click('#go-computer');
  await expect(page.locator('#reward-note')).toContainText('counts for rewards');
  await page.goto('/profile.html#hnefatafl');
  await expect(page.locator('#hnefatafl input[name=pieceBlack][value=red]')).toBeChecked();
  await expect(page.locator('#hnefatafl input[name=board][value=fjord]')).toBeChecked();
  await expect(page.locator('#hnefatafl .tboard .tsq')).toHaveCount(121);
  if (info.project.name === 'chromium') await page.locator('#hnefatafl').screenshot({ path: `${SHOTS}-profile-alice.png` });
});
