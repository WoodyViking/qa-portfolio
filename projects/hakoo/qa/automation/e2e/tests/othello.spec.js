// Othello (games/othello.html): system tests in the browser.
// Traceability: FR-OTHELLO-01..06, FR-OTHELLO-10, FR-OTHELLO-11, FR-GAMES-01..03, US-20.
const { test, expect } = require('@playwright/test');

const SHOTS = '../../test-results/othello';

async function startComputerGame(page, { name = 'Tester', color = 'black', level = 'easy' } = {}) {
  await page.goto('/games/othello.html');
  await page.click('#go-computer');
  await page.fill('#name', name);
  await page.check(`input[name=color][value=${color}]`);
  await page.check(`input[name=difficulty][value=${level}]`);
  await page.click('#start');
  await expect(page.locator('#v-game')).toBeVisible();
}

const sq = (page, r, c) => page.locator(`#board .osq[data-r="${r}"][data-c="${c}"]`);

test('TC-E2E-088: Othello stores nothing in the browser and calls no third party', async ({ page, context, baseURL }) => {
  const requests = [];
  page.on('request', r => requests.push(r.url()));
  await startComputerGame(page);
  await sq(page, 2, 3).click();
  await expect(page.locator('#move-count')).toHaveText('2');
  expect(await context.cookies()).toEqual([]);
  expect(await page.evaluate(() => [localStorage.length, sessionStorage.length])).toEqual([0, 0]);
  const origin = new URL(baseURL).origin;
  expect(requests.filter(u => !u.startsWith(origin))).toEqual([]);
});

test('TC-E2E-089: the dots show the legal moves; a move flips discs and the computer answers', async ({ page }, info) => {
  await startComputerGame(page, { name: 'Woody', level: 'medium' });
  await expect(page.locator('#p-black .pname')).toHaveText('Woody (you)');
  await expect(page.locator('#p-white .pname')).toHaveText('Computer (Medium)');
  await expect(page.locator('#board .osq.hint')).toHaveCount(4);
  await sq(page, 2, 3).click();
  await expect(page.locator('#move-count')).toHaveText('2');
  await expect(page.locator('#turn')).toHaveText('Your turn (Black)');
  const counts = [await page.locator('#count-black').textContent(), await page.locator('#count-white').textContent()].map(Number);
  expect(counts[0] + counts[1]).toBe(6);                                      // 4 at the start + 2 placed
  await expect(page.locator('#board .osq.last')).toHaveCount(1);
  await page.screenshot({ path: `${SHOTS}-computer-${info.project.name}.png`, fullPage: true });
});

test('TC-E2E-090: a square that flips nothing is refused with a message', async ({ page }) => {
  await startComputerGame(page);
  await sq(page, 0, 0).click();
  await expect(page.locator('#error')).toContainText('must flip');
  await expect(page.locator('#move-count')).toHaveText('0');
});

test('TC-E2E-091: two friends play through an invite link', async ({ browser }, info) => {
  const alice = await (await browser.newContext()).newPage();
  const bob = await (await browser.newContext()).newPage();
  await alice.goto('/games/othello.html');
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

  await sq(alice, 2, 3).click();
  await expect(sq(bob, 2, 3)).toHaveClass(/\bb\b/, { timeout: 5000 });
  await expect(bob.locator('#count-black')).toHaveText('4');
  await expect(bob.locator('#turn')).toHaveText('Your turn (White)');
  await expect(bob.locator('#board .osq.hint')).toHaveCount(3);

  await alice.click('#resign');
  await alice.click('#confirm-ok');
  await expect(alice.locator('#result-title')).toHaveText('Bob won');
  await expect(bob.locator('#result-title')).toHaveText('You won! 🎉', { timeout: 5000 });
  await bob.screenshot({ path: `${SHOTS}-friend-${info.project.name}.png`, fullPage: true });
});

test('TC-E2E-092: the rules explain flipping, passing and winning; a reload keeps the game', async ({ page }) => {
  await page.goto('/games/othello.html');
  await page.click('#go-rules');
  const rules = page.locator('main');
  for (const word of ['flip', 'pass', 'most discs', 'draw']) await expect(rules).toContainText(word);
  await page.click('.rhead a.play');
  await startComputerGame(page);
  await sq(page, 2, 3).click();
  await expect(page.locator('#move-count')).toHaveText('2');
  await page.reload();
  await expect(page.locator('#v-game')).toBeVisible();
  await expect(page.locator('#move-count')).toHaveText('2');
});

test('TC-E2E-093: the board fits a 360 px phone and can be played by touch', async ({ browser }, info) => {
  test.skip(info.project.name !== 'chromium', 'one viewport check is enough');
  const page = await (await browser.newContext({ viewport: { width: 360, height: 740 }, hasTouch: true })).newPage();
  await startComputerGame(page);
  await sq(page, 2, 3).tap();
  await expect(page.locator('#move-count')).toHaveText('2');
  expect(await page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(360);
  await page.screenshot({ path: `${SHOTS}-360px.png`, fullPage: true });
});

test('TC-E2E-103: Othello coordinates and the move list', async ({ page }) => {
  await startComputerGame(page);
  await expect(sq(page, 0, 0).locator('.co')).toHaveText(['1', 'a']);
  await sq(page, 2, 3).click();
  await expect(page.locator('#movelist .mv').first()).toHaveText('d3');
  await expect(page.locator('#movelist .mv')).toHaveCount(2);
  await page.click('#mv-prev');
  await expect(page.locator('#turn')).toHaveText('Looking back: after move 1 of 2');
  await expect(page.locator('#count-black')).toHaveText('4');                   // the counts follow the board shown
});
