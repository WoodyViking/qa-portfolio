// Turning and tilting the board after the 3D unlock (js/shared/board-game.js turnable(),
// css/games/board-game.css .bview, css/stratego-board.css .behind), in all five board games.
// Fixture: Alice has won on every level of every game (so she has the Medium unlock everywhere).
// Test design: qa/test-design/decision-tables.md §"Turning the board".
// On a touch screen the board is never turned: fingers zoom and move it instead (js/shared/board-zoom.js, TC-E2E-205).
// Traceability: FR-GAMES-10, FR-GAMES-19, FR-STRATEGO-12, US-28.
const { test, expect } = require('@playwright/test');
const { USERS, loginAs, freshUserId, closeNav, viewBar } = require('./helpers');

const SHOTS = '../../test-results/board-view';
const GAMES = [   // [page, a piece selector inside the board]; Othello's discs lie flat (FR-OTHELLO-12)
  ['gomoku', '.pt.b .st'], ['chess', '.pc.pw'], ['othello', '.osq.b .disc'], ['hnefatafl', '.tsq.a .piece'], ['stratego', '.ssq.pb .piece'],
];

/** Start a game against the computer as the first player (Stratego: set up at random). */
async function startGame(page, game) {
  await page.goto(`/games/${game}.html`);
  await page.click('#go-computer');
  await page.check('input[name=color][value=black]');
  if (await page.locator('input[name=difficulty][value=easy]').count()) await page.check('input[name=difficulty][value=easy]');
  await page.click('#start');
  await expect(page.locator('#v-game')).toBeVisible();
  if (game === 'stratego') {
    await page.click('#setup-random');
    await page.click('#setup-ready');
    await expect(page.locator('#setup-card')).toBeHidden();
  }
  if (game === 'gomoku') {                                   // Gomoku starts empty: place a stone
    await page.locator('#board .pt[data-r="7"][data-c="7"]').click();
    await expect(page.locator('#move-count')).not.toHaveText('0');
  }
}

const viewOf = page => page.evaluate(() => getComputedStyle(document.querySelector('.bview')).getPropertyValue('--view').trim());

test('TC-E2E-156: without the Medium unlock there is nothing to turn (logged out, or a member who has not won on Medium)', async ({ page, context, baseURL }) => {
  await startGame(page, 'othello');
  await expect(page.locator('.view-bar')).toHaveCount(0);
  await expect(page.locator('.bview')).toHaveCount(0);

  await loginAs(context, baseURL, freshUserId(), 'Newbie');
  for (const game of ['othello', 'stratego']) {
    await startGame(page, game);
    await expect(page.locator('#rewards li.got')).toHaveCount(0);
    await expect(page.locator('.view-bar')).toHaveCount(0);
    await expect(page.locator('.bview')).toHaveCount(0);
  }
});

test('TC-E2E-157: after the Medium unlock every board turns and tilts with the buttons, the pieces stand up, and Reset makes it flat again', async ({ page, context, baseURL }, info) => {
  test.skip(info.project.name === 'mobile', 'a touch screen never turns the board (TC-E2E-205)');
  await loginAs(context, baseURL, USERS.alice, 'Alice');
  for (const [game, piece] of GAMES) {
    await startGame(page, game);
    const bar = await viewBar(page);                                // under the board, or in ☰ on a phone
    await expect(bar).toBeVisible();
    await expect(page.locator('.bview')).not.toHaveClass(/turned/);   // flat from your own side until you turn it
    for (let i = 0; i < 3; i++) await bar.locator('[data-view=up]').click();
    await bar.locator('[data-view=right]').focus();
    await page.keyboard.press('Enter');                             // the buttons work from the keyboard
    await expect(page.locator('.bview')).toHaveClass(/turned/);
    expect(await viewOf(page)).toMatch(/^rotateX\(45deg\) rotateZ\(45deg\) scale3d/);
    // A piece is rotated back towards the viewer and lifted off the board
    const pc = await page.locator(`#board ${piece}`).first().evaluate(el => [getComputedStyle(el).rotate, getComputedStyle(el).translate]);
    if (game === 'othello') expect(pc[0]).toBe('none');            // a disc lies flat, like a real one
    else expect(pc[0]).not.toBe('none');
    expect(pc[1]).not.toBe('none');
    await page.locator('.bview').scrollIntoViewIfNeeded();
    await page.screenshot({ path: `${SHOTS}-${game}-turned-${info.project.name}.png` });
    await bar.locator('[data-view=reset]').click();
    await expect(page.locator('.bview')).not.toHaveClass(/turned/);
    await closeNav(page);
  }
  // FR-GAMES-02: turning stores nothing; the page still fits a phone without scrolling sideways
  expect(await page.evaluate(() => [localStorage.length, sessionStorage.length])).toEqual([0, 0]);
  expect((await context.cookies()).map(c => c.name)).toEqual(['hakoo_auth']);
  expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true);
});

test('TC-E2E-158: on a turned board a click still picks the right square, and a drag turns the board without making a move', async ({ page, context, baseURL }, info) => {
  test.skip(info.project.name === 'mobile', 'a touch screen never turns the board (TC-E2E-205)');
  await loginAs(context, baseURL, USERS.alice, 'Alice');
  await startGame(page, 'stratego');
  const bar = await viewBar(page);
  for (let i = 0; i < 3; i++) await bar.locator('[data-view=up]').click();
  await bar.locator('[data-view=right]').click();
  await closeNav(page);
  // A front-row piece that can step forward
  const [r, c] = await page.evaluate(() => {
    const b = [...document.querySelectorAll('#board .ssq.pb')].find(x => x.dataset.r === '6' && !['B', 'F'].includes(x.dataset.code)
      && !['2', '3', '6', '7'].includes(x.dataset.c));
    return [+b.dataset.r, +b.dataset.c];
  });
  await page.locator(`#board .ssq[data-r="${r}"][data-c="${c}"]`).click();
  await expect(page.locator('#board .ssq.sel')).toHaveAttribute('data-c', String(c));
  await expect(page.locator(`#board .ssq[data-r="${r - 1}"][data-c="${c}"]`)).toHaveClass(/\bto\b/);

  // A drag across the board: the view changes, the chosen piece stays chosen, no move is sent
  const before = await viewOf(page);
  const box = await page.locator('.bview').boundingBox();
  await page.mouse.move(box.x + box.width * 0.4, box.y + box.height * 0.7);
  await page.mouse.down();
  await page.mouse.move(box.x + box.width * 0.6, box.y + box.height * 0.6, { steps: 8 });
  await page.mouse.up();
  expect(await viewOf(page)).not.toBe(before);
  await expect(page.locator('#move-count')).toHaveText('0');
  await expect(page.locator('#board .ssq.sel')).toHaveAttribute('data-c', String(c));

  // ...and the move itself still works on the turned board
  await page.locator(`#board .ssq[data-r="${r - 1}"][data-c="${c}"]`).click();
  await expect(page.locator('#move-count')).not.toHaveText('0');
  await page.screenshot({ path: `${SHOTS}-stratego-moved-${info.project.name}.png` });
});

test('TC-E2E-159: Stratego seen from the enemy side shows the backs of your own pieces; the enemy ranks are never in the page', async ({ page, context, baseURL }, info) => {
  test.skip(info.project.name === 'mobile', 'a touch screen never turns the board (TC-E2E-205)');
  await loginAs(context, baseURL, USERS.alice, 'Alice');
  await startGame(page, 'stratego');
  const bar = await viewBar(page);
  const ownRank = page.locator('#board .ssq.pb .piece b').first();
  await expect(ownRank).toBeVisible();
  await bar.locator('[data-view=up]').click();
  await bar.locator('[data-view=right]').click();
  await bar.locator('[data-view=right]').click();                 // 90°: still from the side, ranks shown
  await expect(page.locator('#board')).not.toHaveClass(/behind/);
  await expect(ownRank).toBeVisible();
  await bar.locator('[data-view=right]').click();                 // 135°: past the side
  await expect(page.locator('#board')).toHaveClass(/behind/);
  await expect(ownRank).toBeHidden();
  await page.locator('.bview').scrollIntoViewIfNeeded();
  await page.screenshot({ path: `${SHOTS}-stratego-behind-${info.project.name}.png` });
  // The enemy army is still only backs: no rank of Blue is in the page, whichever way the board is turned
  expect(await page.evaluate(() => [...document.querySelectorAll('#board .ssq.pw')].filter(b => b.dataset.code !== '?').length)).toBe(0);
  await bar.locator('[data-view=reset]').click();
  await expect(page.locator('#board')).not.toHaveClass(/behind/);
  await expect(ownRank).toBeVisible();
});

test('TC-E2E-205: on a phone the board never turns: two fingers zoom it, one finger moves it up, down and sideways, a tap is still a move, ⤢ shows it all', async ({ page, context, baseURL }, info) => {
  test.skip(info.project.name !== 'mobile', 'touch: the phone project (Chromium) only');
  await loginAs(context, baseURL, USERS.alice, 'Alice');                // Alice has the 3D reward: still nothing turns
  await startGame(page, 'chess');
  await expect(page.locator('.bview')).toHaveCount(0);
  await expect(page.locator('.view-bar')).toHaveCount(0);
  await expect(page.locator('nav .nav-view')).toHaveCount(0);
  await expect(page.locator('.bzoom .bz-fit')).toBeHidden();
  const moves = await page.locator('#move-count').textContent();       // the computer (white) may have moved already
  const zoom = () => page.evaluate(() => {
    const m = document.getElementById('board').style.transform.match(/translate\(([-\d.]+)px, ([-\d.]+)px\) scale\(([\d.]+)\)/);
    return m ? m.slice(1).map(Number) : [0, 0, 1];
  });
  const b = await page.locator('#board').boundingBox();
  const x = b.x + b.width / 2, y = b.y + b.height / 2;
  const cdp = await page.context().newCDPSession(page);
  const touch = (type, pts) => cdp.send('Input.dispatchTouchEvent', { type, touchPoints: pts.map(([px, py], i) => ({ x: px, y: py, id: i + 1 })) });
  // Two fingers apart: zoomed in.
  await touch('touchStart', [[x - 30, y]]);
  await touch('touchStart', [[x - 30, y], [x + 30, y]]);
  for (let i = 1; i <= 8; i++) await touch('touchMove', [[x - 30 - i * 10, y], [x + 30 + i * 10, y]]);
  await touch('touchEnd', []);
  const [x1, y1, k1] = await zoom();
  expect(k1).toBeGreaterThan(2);
  await expect(page.locator('.bzoom .bz-fit')).toBeVisible();
  await expect(page.locator('#move-count')).toHaveText(moves);          // the pinch made no move
  // One finger: the board moves right and down (towards the top left corner).
  await touch('touchStart', [[x, y]]);
  for (let i = 1; i <= 8; i++) await touch('touchMove', [[x + i * 12, y + i * 12]]);
  await touch('touchEnd', []);
  const [x2, y2, k2] = await zoom();
  expect(k2).toBeCloseTo(k1, 2);
  expect(x2).toBeGreaterThan(x1 + 30);
  expect(y2).toBeGreaterThan(y1 + 30);
  await expect(page.locator('#move-count')).toHaveText(moves);          // the drag made no move
  await page.screenshot({ path: `${SHOTS}-phone-zoomed.png` });
  // ⤢ shows the whole board; a tap still plays: one of your pawns, then a square it may go to.
  await page.click('.bzoom .bz-fit');
  expect((await zoom())[2]).toBe(1);
  await expect(page.locator('.bzoom .bz-fit')).toBeHidden();
  const tap = async el => { const r = await el.boundingBox(); await page.touchscreen.tap(r.x + r.width / 2, r.y + r.height / 2); };
  for (const r of [1, 6]) {
    for (let c = 0; c < 8 && !(await page.locator('#board .sq.target').count()); c++) await tap(page.locator(`#board .sq[data-r="${r}"][data-c="${c}"]`));
  }
  await expect(page.locator('#board .sq.sel')).toHaveCount(1);
  await tap(page.locator('#board .sq.target').first());
  await expect(page.locator('#move-count')).not.toHaveText(moves);
});
