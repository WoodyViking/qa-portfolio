// Stratego (games/stratego.html): system tests in the browser.
// Seats: black = Red (moves first, bottom), white = Blue (sees the board turned round).
// Fixture: Alice has won on every level (gold Red army, purple Blue army, 3D, the Night board).
// Traceability: FR-STRATEGO-04, 06, 07, 09..11, FR-GAMES-08, FR-GAMES-09, US-27.
const { test, expect } = require('@playwright/test');
const { USERS, loginAs } = require('./helpers');

const SHOTS = '../../test-results/stratego';

async function startComputerGame(page, { name = 'Tester', color = 'black', level = 'easy', ready = true } = {}) {
  await page.goto('/games/stratego.html');
  await page.click('#go-computer');
  await page.fill('#name', name);
  await page.check(`input[name=color][value=${color}]`);
  await page.check(`input[name=difficulty][value=${level}]`);
  await page.click('#start');
  await expect(page.locator('#setup-card')).toBeVisible();
  if (ready) await setUp(page);
}

async function setUp(page) {
  await page.click('#setup-random');
  await page.click('#setup-ready');
  await expect(page.locator('#setup-card')).toBeHidden();
}

const sq = (page, r, c) => page.locator(`#board .ssq[data-r="${r}"][data-c="${c}"]`);

/** A front-row piece of Red that can step forward (no Bomb or Flag, not behind a lake). Returns [row, col]. */
const movable = page => page.evaluate(() => {
  const b = [...document.querySelectorAll('#board .ssq.pb')].find(x => x.dataset.r === '6' && !['B', 'F'].includes(x.dataset.code)
    && !['2', '3', '6', '7'].includes(x.dataset.c));
  return [+b.dataset.r, +b.dataset.c];
});

/** Red steps one front-row piece forward. Returns where it went. */
async function stepForward(page, tap = false) {
  const [r, c] = await movable(page);
  if (tap) { await sq(page, r, c).tap(); await sq(page, r - 1, c).tap(); }
  else { await sq(page, r, c).click(); await sq(page, r - 1, c).click(); }
  return [r - 1, c];
}

test('TC-E2E-130: Stratego stores nothing in the browser and calls no third party', async ({ page, context, baseURL }) => {
  const requests = [];
  page.on('request', r => requests.push(r.url()));
  await startComputerGame(page);
  await expect(page.locator('#turn')).toHaveText('Your turn (Red)');
  expect(await context.cookies()).toEqual([]);
  expect(await page.evaluate(() => [localStorage.length, sessionStorage.length])).toEqual([0, 0]);
  const origin = new URL(baseURL).origin;
  expect(requests.filter(u => !u.startsWith(origin))).toEqual([]);
});

test('TC-E2E-131: the set-up: a tray of 40 pieces, Ready only when all are placed; then the enemy shows only backs', async ({ page }, info) => {
  await startComputerGame(page, { level: 'medium', ready: false });
  await expect(page.locator('#turn')).toHaveText('Set up your army, then press Ready');
  await expect(page.locator('#tray button')).toHaveCount(12);
  await expect(page.locator('#board .ssq.home')).toHaveCount(40);
  await expect(page.locator('#setup-ready')).toBeDisabled();
  await page.click('#tray button[data-code="F"]');
  await sq(page, 9, 4).click();
  await expect(sq(page, 9, 4)).toHaveAttribute('data-code', 'F');
  await expect(page.locator('#tray button[data-code="F"]')).toBeDisabled();
  await page.click('#setup-random');
  await expect(sq(page, 9, 4)).toHaveAttribute('data-code', 'F', { timeout: 1000 });   // what was placed stays
  await expect(page.locator('#setup-ready')).toBeEnabled();
  await page.screenshot({ path: `${SHOTS}-setup-${info.project.name}.png`, fullPage: true, animations: 'disabled' });
  await page.click('#setup-ready');
  await expect(page.locator('#board .ssq.pb')).toHaveCount(40);
  await expect(page.locator('#board .ssq.pw.unknown')).toHaveCount(40);
  await expect(page.locator('#turn')).toHaveText('Your turn (Red)');
});

test('TC-E2E-132: picking a piece shows where it can go; a move is made and the computer answers', async ({ page }, info) => {
  await startComputerGame(page, { name: 'Woody', level: 'medium' });
  await expect(page.locator('#p-black .pname')).toHaveText('Woody (you)');
  await expect(page.locator('#p-white .pname')).toHaveText('Computer (Medium)');
  const from = await movable(page);
  await sq(page, ...from).click();
  await expect(sq(page, ...from)).toHaveClass(/\bsel\b/);
  expect(await page.locator('#board .ssq.to').count()).toBeGreaterThan(0);
  await sq(page, from[0] - 1, from[1]).click();
  await expect(page.locator('#move-count')).toHaveText('2');
  await expect(page.locator('#movelist li').first()).toContainText(`${'abcdefghij'[from[1]]}4`);
  await expect(page.locator('#turn')).toContainText('Your turn (Red)');
  await page.screenshot({ path: `${SHOTS}-computer-${info.project.name}.png`, fullPage: true });
});

test('TC-E2E-133: wrong clicks explain what to do', async ({ page }) => {
  await startComputerGame(page, { ready: false });
  await sq(page, 2, 0).click();
  await expect(page.locator('#error')).toContainText('your own four rows');
  await sq(page, 8, 0).click();
  await expect(page.locator('#error')).toContainText('Pick a piece in the tray');
  await setUp(page);
  const bomb = page.locator('#board .ssq.pb[data-code="B"]').first();
  await bomb.click();
  await expect(page.locator('#error')).toContainText('never move');
  await page.locator('#board .ssq.pw').first().click();
  await expect(page.locator('#error')).toContainText('not your piece');
  await expect(page.locator('#move-count')).toHaveText('0');
});

test('TC-E2E-134: two friends set up at the same time and play; Blue sees the board turned round', async ({ browser }, info) => {
  const alice = await (await browser.newContext()).newPage();
  const bob = await (await browser.newContext()).newPage();
  await alice.goto('/games/stratego.html');
  await alice.click('#go-friend');
  await alice.fill('#name', 'Alice');
  await alice.check('input[name=color][value=black]');
  await alice.click('#start');
  await expect(alice.locator('#invite-link')).toHaveValue(/#join=[0-9a-f]{16}\.[0-9a-f]{32}$/);
  await bob.goto(await alice.locator('#invite-link').inputValue());
  await bob.fill('#join-name', 'Bob');
  await bob.click('#v-join button[type=submit]');
  await expect(bob.locator('#p-white .pname')).toHaveText('Bob (you)');
  await expect(bob.locator('#board .ssq').first()).toHaveAttribute('data-r', '9');   // Blue's own rows at the bottom
  await expect(alice.locator('#setup-card')).toBeVisible({ timeout: 5000 });

  await setUp(alice);
  await expect(alice.locator('#turn')).toHaveText('Waiting for Bob to set up…');
  await expect(alice.locator('#board .ssq.pw')).toHaveCount(0);
  await setUp(bob);
  await expect(alice.locator('#turn')).toHaveText('Your turn (Red)', { timeout: 5000 });
  await expect(alice.locator('#board .ssq.pw.unknown')).toHaveCount(40);

  const to = await stepForward(alice);
  await expect(bob.locator('#turn')).toContainText('Your turn (Blue)', { timeout: 5000 });
  await expect(sq(bob, ...to)).toHaveClass(/\bunknown\b/);
  await expect(sq(bob, ...to)).toHaveClass(/\bmoved\b/);

  await alice.click('#resign');
  await alice.click('#confirm-ok');
  await expect(alice.locator('#result-title')).toHaveText('Bob won');
  await expect(bob.locator('#result-title')).toHaveText('You won! 🎉', { timeout: 5000 });
  await bob.click('#result-close');
  await expect(bob.locator('#board .ssq.pb.unknown')).toHaveCount(0);                 // after the game everything is shown
  await bob.screenshot({ path: `${SHOTS}-friend-${info.project.name}.png`, fullPage: true });
});

test('TC-E2E-135: the rules explain the pieces, battles and hidden information; a reload keeps the game', async ({ page }) => {
  await page.goto('/games/stratego.html');
  await page.click('#go-rules');
  const rules = page.locator('main');
  for (const word of ['flag', 'Scout', 'Spy', 'Miner', 'Bomb', 'lakes', 'two squares', 'without seeing']) await expect(rules).toContainText(word);
  await page.click('.rhead a.play');
  await startComputerGame(page);
  await stepForward(page);
  await expect(page.locator('#move-count')).toHaveText('2');
  await page.reload();
  await expect(page.locator('#v-game')).toBeVisible();
  await expect(page.locator('#move-count')).toHaveText('2');
  await expect(page.locator('#board .ssq.pb')).not.toHaveCount(0);
});

test('TC-E2E-136: the board fits a 360 px phone and can be played by touch', async ({ browser }, info) => {
  test.skip(info.project.name !== 'chromium', 'one viewport check is enough');
  const page = await (await browser.newContext({ viewport: { width: 360, height: 740 }, hasTouch: true })).newPage();
  await startComputerGame(page);
  await stepForward(page, true);
  await expect(page.locator('#move-count')).toHaveText('2');
  expect(await page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(360);
  await page.screenshot({ path: `${SHOTS}-360px.png`, fullPage: true });
});

test('TC-E2E-137: a member who won everything plays with their own look and sees it on the profile', async ({ page, context, baseURL }, info) => {
  await loginAs(context, baseURL, USERS.alice, 'Alice');
  await page.goto('/games/stratego.html');
  await expect(page.locator('#rewards li.got')).toHaveCount(3);
  const vars = await page.evaluate(() => ['--piece-black', '--sb-bg'].map(n => getComputedStyle(document.documentElement).getPropertyValue(n).trim()));
  expect(vars).toEqual(['#d9a520', '#27364a']);
  expect(await page.evaluate(() => document.documentElement.classList.contains('s3d'))).toBe(true);
  await page.goto('/profile.html#stratego');
  await expect(page.locator('#stratego input[name=pieceBlack][value=gold]')).toBeChecked();
  await expect(page.locator('#stratego input[name=board][value=night]')).toBeChecked();
  await expect(page.locator('#stratego .sboard .ssq')).toHaveCount(100);
  if (info.project.name === 'chromium') await page.locator('#stratego').screenshot({ path: `${SHOTS}-profile-alice.png` });
});
