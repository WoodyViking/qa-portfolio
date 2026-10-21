// Nonogram (games/nonogram.html, js/games/nonogram.js, ../nonogram.php).
// The practice puzzle Easy #7 is fixed: its picture is below (made by nonogram-engine.inc.php).
// Traceability: FR-NONOGRAM-07, FR-NONOGRAM-08, FR-GAMES-02, US-37.
const { test, expect } = require('@playwright/test');

const SHOTS = '../../test-results/nonogram';
const EASY7 = '0001001000001111110001011110100110000110011100111001111111100111111110111111111101000000100000000000';
const cell = (page, r, c) => page.locator(`#board .ncell[data-r="${r}"][data-c="${c}"]`);

test('TC-E2E-189: a practice puzzle: fill, drag, cross, undo; the address keeps it over a reload; solved, the picture shows; nothing is stored', async ({ page, context }, info) => {
  await page.goto('/games/nonogram.html#p=easy.7');
  await expect(page.locator('#ng-title')).toHaveText('🧩 Practice · Easy 10 × 10');
  await expect(page.locator('#board .ncell')).toHaveCount(100);
  await expect(page.locator('#board .nrow').first()).toHaveText('11');      // the clue "1 1"

  // Row 2 is 6 squares from column 1: one drag fills them all.
  const a = await cell(page, 1, 2).boundingBox(), b = await cell(page, 1, 7).boundingBox();
  await page.mouse.move(a.x + a.width / 2, a.y + a.height / 2);
  await page.mouse.down();
  await page.mouse.move(b.x + b.width / 2, b.y + b.height / 2, { steps: 6 });
  await page.mouse.up();
  await expect(page.locator('#board .ncell.on')).toHaveCount(6);
  await expect(page.locator('#board .nrow').nth(1)).toHaveClass(/done/);
  // A right click crosses; the cross button makes a click cross; Undo takes the last stroke back.
  await cell(page, 9, 0).click({ button: 'right' });
  await expect(cell(page, 9, 0)).toHaveClass(/\bx\b/);
  await page.click('#tool-cross');
  await cell(page, 9, 1).click();
  await expect(page.locator('#board .ncell.x')).toHaveCount(2);
  await page.click('#undo');
  await expect(page.locator('#board .ncell.x')).toHaveCount(1);
  await page.click('#tool-fill');
  await expect(page).toHaveURL(/#p=easy\.7\.[A-Za-z0-9_-]+$/);
  await page.reload();
  await expect(page.locator('#board .ncell.on')).toHaveCount(6);
  await expect(page.locator('#board .ncell.x')).toHaveCount(1);

  // The rest of the picture, square by square.
  for (let i = 0; i < 100; i++) {
    if (EASY7[i] === '1' && !(i >= 12 && i <= 17)) await cell(page, Math.floor(i / 10), i % 10).click();
  }
  await expect(page.locator('#result')).toBeVisible();
  await expect(page.locator('#result-text')).toContainText('Easy 10 × 10 in');
  await expect(page.locator('#board')).toHaveClass(/solved/);
  await page.click('#result-close');
  await page.screenshot({ path: `${SHOTS}-solved-${info.project.name}.png`, fullPage: true });
  expect(await page.evaluate(() => [localStorage.length, sessionStorage.length])).toEqual([0, 0]);
  expect(await context.cookies()).toEqual([]);
});

test('TC-E2E-190: the start page offers the puzzle of the day; not logged in, the list asks to log in; the board fits a phone', async ({ page }) => {
  await page.goto('/games/nonogram.html');
  await expect(page.locator('[data-daily]')).toHaveCount(3);
  await page.click('[data-daily=hard]');
  await expect(page.locator('#ng-title')).toHaveText('📅 Puzzle of the day · Hard 20 × 20');
  await expect(page).toHaveURL(/#d=hard\.\d+$/);                // the puzzle's number: members and guests have different ones
  await expect(page.locator('#side-body')).toContainText('Log in with Discord');
  const m = await page.evaluate(() => ({
    scroll: document.documentElement.scrollWidth - document.documentElement.clientWidth,
    cell: document.querySelector('#board .ncell').getBoundingClientRect().width,
  }));
  expect(m.scroll).toBeLessThanOrEqual(0);                   // a wide board scrolls inside its box, not the page
  expect(m.cell).toBeGreaterThanOrEqual(21);                 // big enough to hit
  await page.click('#new-puzzle');
  await expect(page.locator('#v-home')).toBeVisible();
});

test('TC-E2E-196: on a phone two fingers zoom and move the board, and a fill that turns into a pinch is taken back; ⤢ Fit shows the whole board', async ({ page }, info) => {
  test.skip(info.project.name !== 'mobile', 'touch: the phone project (Chromium) only');
  await page.goto('/games/nonogram.html');
  await page.click('[data-daily=hard]');
  await expect(page.locator('#ng-title')).toContainText('Hard 20 × 20');
  const scale = () => page.evaluate(() => { const m = document.getElementById('board').style.transform.match(/scale\(([\d.]+)\)/); return m ? +m[1] : 1; });
  // Normal size: wider than the phone, so ⤢ Fit is offered.
  await expect(page.locator('#zoom-fit')).toBeVisible();
  expect(await scale()).toBe(1);
  const b = await page.locator('#board').boundingBox();
  const x = b.x + 220, y = b.y + 200;
  const cdp = await page.context().newCDPSession(page);
  const touch = (type, pts) => cdp.send('Input.dispatchTouchEvent', { type, touchPoints: pts.map(([px, py], i) => ({ x: px, y: py, id: i + 1 })) });
  await touch('touchStart', [[x - 30, y]]);                          // one finger: fills a square ...
  await expect(page.locator('#board .ncell.on')).toHaveCount(1);
  await touch('touchStart', [[x - 30, y], [x + 30, y]]);             // ... a second one makes it a pinch
  for (let i = 1; i <= 8; i++) await touch('touchMove', [[x - 30 - i * 8, y + i * 4], [x + 30 + i * 8, y + i * 4]]);
  await touch('touchEnd', []);
  expect(await scale()).toBeGreaterThan(2);
  await expect(page.locator('#board .ncell.on')).toHaveCount(0);     // the pinch filled nothing
  await expect(page.locator('#undo')).toBeDisabled();
  await page.screenshot({ path: `${SHOTS}-zoomed.png` });
  // Zoomed in, one finger still fills the square under it.
  const sq = await cell(page, 5, 5).boundingBox();
  await page.touchscreen.tap(sq.x + sq.width / 2, sq.y + sq.height / 2);
  await expect(cell(page, 5, 5)).toHaveClass(/\bon\b/);
  await page.click('#zoom-fit');
  expect(await scale()).toBeLessThan(1);
  const m = await page.evaluate(() => ({ board: document.getElementById('board').getBoundingClientRect().right, frame: document.querySelector('.ng-scroll').getBoundingClientRect().right }));
  expect(m.board).toBeLessThanOrEqual(m.frame + 1);                 // all of it shows
  await expect(page.locator('#zoom-fit')).toBeHidden();
});
