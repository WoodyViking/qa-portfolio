// Othello's discs turning over, and the move speed of all board games (js/games/othello.js,
// css/othello-board.css, js/shared/board-game.js speedBar()).
// playwright.config.js asks for less motion, so a test that wants to see the flips picks a speed.
// Fixture: Alice has won on every level (3D discs, teal and gold).
// Traceability: FR-GAMES-11, FR-GAMES-12, FR-OTHELLO-12, FR-OTHELLO-13, US-29.
const { test, expect } = require('@playwright/test');
const { USERS, loginAs, freshUserId, openNav, closeNav, viewBar } = require('./helpers');

const SHOTS = '../../test-results/othello-flip';

async function startOthello(page) {
  await page.goto('/games/othello.html');
  await page.click('#go-computer');
  await page.check('input[name=color][value=black]');
  await page.check('input[name=difficulty][value=easy]');
  await page.click('#start');
  await expect(page.locator('#board .osq.hint')).toHaveCount(4);
}

const discs = page => page.locator('#board .osq.b, #board .osq.w');

/** Open ⚙, set the move speed (a 'change' saves it for a member) and close the dialog again. */
async function setSpeed(page, ms) {
  await openNav(page);
  await page.click('#settings-open');
  await page.locator('#move-speed').fill(String(ms));
  await page.locator('#move-speed').dispatchEvent('change');
  await page.click('#settings-close');
}

/** The squares where the player may play in the position the server has (the page hides its hints while discs turn). */
const legalNow = page => page.evaluate(async () => {
  const h = new URLSearchParams(location.hash.slice(1));
  const j = await (await fetch(`../othello.php?action=state&game=${h.get('g')}`, { headers: { 'X-Game-Token': h.get('p') } })).json();
  return j.state.legal;
});

test('TC-E2E-160: a move turns the discs over in a wave, then the computer\'s disc lands and turns its own; nothing is stored', async ({ page, context }, info) => {
  await startOthello(page);
  await openNav(page);                                                             // (on a phone ⚙ is in ☰)
  await page.click('#settings-open');                                              // ⚙ in the menu bar
  await expect(page.locator('#move-speed-msg')).toContainText('less motion');      // no animation until a speed is picked
  await expect(page.locator('#settings-more')).toContainText('nothing is stored');
  await page.locator('#move-speed').fill('1000');
  await expect(page.locator('#move-speed-out')).toHaveText('1.0 s');
  await page.locator('#move-speed').dispatchEvent('change');
  await expect(page.locator('#move-speed-msg')).toHaveText('Log in to keep it for next time.');
  await page.click('#settings-close');

  await page.locator('#board .osq.hint').first().click();
  // My disc and its flip first: the computer's disc is not on the board yet
  await expect(page.locator('#board .osq.flip')).toHaveCount(1);
  await expect(page.locator('#move-count')).toHaveText('2');
  expect(await discs(page).count()).toBe(5);
  await page.screenshot({ path: `${SHOTS}-wave-${info.project.name}.png` });
  // Then the computer's disc, and in the end the hints for my next move
  await expect(discs(page)).toHaveCount(6, { timeout: 8000 });
  await expect(page.locator('#board .osq.hint').first()).toBeVisible({ timeout: 8000 });
  await expect(page.locator('#board .osq.flip')).toHaveCount(0);
  expect(await page.evaluate(() => [localStorage.length, sessionStorage.length])).toEqual([0, 0]);
  expect(await context.cookies()).toEqual([]);
});

test('TC-E2E-161: a click while discs are turning finishes them at once and plays the move', async ({ page }) => {
  await startOthello(page);
  await setSpeed(page, 2000);
  await page.locator('#board .osq.hint').first().click();
  await expect(page.locator('#board .osq.flip')).toHaveCount(1);
  const [r, c] = (await legalNow(page))[0];
  await page.locator(`#board .osq[data-r="${r}"][data-c="${c}"]`).click();
  await expect(page.locator('#move-count')).toHaveText('4');
  await expect(page.locator(`#board .osq[data-r="${r}"][data-c="${c}"]`)).toHaveClass(/\bb\b/);
});

test('TC-E2E-162: a member\'s move speed is saved on the profile and used in every board game', async ({ page, context, baseURL }) => {
  await loginAs(context, baseURL, freshUserId(), 'Speedy');
  await page.goto('/games/othello.html');
  await expect(page.locator('#rewards')).toContainText('Rewards for Speedy');
  await expect(page.locator('#move-speed')).toHaveValue('600');                    // the default
  await setSpeed(page, 1500);                                                      // ⚙ works on the start view too
  await expect(page.locator('#move-speed-msg')).toHaveText('Saved ✓');
  for (const game of ['othello', 'chess', 'stratego']) {
    await page.goto(`/games/${game}.html`);
    await expect(page.locator('#rewards')).toContainText('Rewards for Speedy');     // the member is loaded
    await expect(page.locator('#move-speed')).toHaveValue('1500');
    await expect(page.locator('#move-speed-out')).toHaveText('1.5 s');
    await expect(page.locator('#move-speed-msg')).toHaveText('');                 // a chosen speed is used, less motion or not
  }
  expect(await page.evaluate(() => [localStorage.length, sessionStorage.length])).toEqual([0, 0]);
});

test('TC-E2E-163: the 3D disc (after Medium) is thick, half one colour and half the other, and lies flat on a turned board', async ({ page, context, baseURL }, info) => {
  await loginAs(context, baseURL, USERS.alice, 'Alice');
  await startOthello(page);
  const disc = page.locator('#board .osq.b .disc').first();
  await expect(disc.locator('.face')).toHaveCount(2);
  await expect(disc.locator('.edge:visible')).toHaveCount(8);
  if (info.project.name === 'mobile') return;                           // a touch screen never turns the board (TC-E2E-205)
  const bar = await viewBar(page);
  for (let i = 0; i < 3; i++) await bar.locator('[data-view=up]').click();
  await bar.locator('[data-view=right]').click();
  await closeNav(page);
  expect(await disc.evaluate(el => getComputedStyle(el).rotate)).toBe('none');
  // The black face is up on a black disc, the white face on a white one (it is the same disc turned over)
  const turned = await page.evaluate(() => [...document.querySelectorAll('#board .osq.w .disc')].map(d => getComputedStyle(d).transform));
  expect(turned.length).toBeGreaterThan(0);
  for (const t of turned) expect(t).not.toBe('none');
  await page.locator('.bview').scrollIntoViewIfNeeded();
  await page.screenshot({ path: `${SHOTS}-3d-${info.project.name}.png` });
});

test('TC-E2E-164: in the other games the answer moves at the move speed (a chess piece slides over, a Gomoku stone drops in), and not at all with less motion and no speed picked', async ({ page }) => {
  const sq = (r, c) => page.locator(`#board [data-r="${r}"][data-c="${c}"]`);
  // Chess, as white: after 1. e4 the computer's piece slides to its square
  await page.goto('/games/chess.html');
  await page.click('#go-computer');
  await page.check('input[name=color][value=white]');
  await page.click('#start');
  const played = page.locator('#movelist button.mv');                              // chess counts full moves, so count the list
  await expect(played).toHaveCount(0);
  await sq(6, 4).click();
  await sq(4, 4).click();
  await expect(played).toHaveCount(2);
  await expect(page.locator('#board .moving')).toHaveCount(0);                     // less motion, no speed picked

  await setSpeed(page, 1500);
  const [from, to] = await page.evaluate(async () => {                              // a legal move from the server
    const h = new URLSearchParams(location.hash.slice(1));
    const j = await (await fetch(`../chess.php?action=state&game=${h.get('g')}`, { headers: { 'X-Game-Token': h.get('p') } })).json();
    const m = j.state.legal.find(x => !x.startsWith('e4'));
    return [[8 - +m[1], 'abcdefgh'.indexOf(m[0])], [8 - +m[3], 'abcdefgh'.indexOf(m[2])]];
  });
  await sq(...from).click();
  await sq(...to).click();
  await expect(played).toHaveCount(4);
  const moving = page.locator('#board .moving');
  await expect(moving).toHaveCount(1);
  expect(await moving.locator('.pc').evaluate(el => el.getAnimations().length)).toBe(1);
  await expect(moving).toHaveCount(0, { timeout: 4000 });                          // it lands

  // Gomoku: the computer's stone drops in
  await page.goto('/games/gomoku.html');
  await page.click('#go-computer');
  await page.check('input[name=color][value=black]');                            // black opens: the centre is free
  await page.click('#start');
  await setSpeed(page, 1000);
  await sq(7, 7).click();
  await expect(page.locator('#move-count')).toHaveText('2');
  await expect(page.locator('#board .moving')).toHaveCount(1);
  await expect(page.locator('#board .moving')).toHaveCount(0, { timeout: 4000 });
});
