// Owldoku (games/owldoku.html, js/games/owldoku.js, js/shared/puzzle-page.js, ../owldoku.php).
// The practice puzzle Easy #3 is fixed: regions abbbcd/abcccc/acccee/aaaeee/ffaaee/ffffee, the owls in
// columns 5,1,3,0,4,2 (made by owldoku-engine.inc.php).
// Traceability: FR-OWLDOKU-04, FR-OWLDOKU-05, FR-OWLDOKU-07, FR-OWLDOKU-08, FR-PUZZLE-01, FR-PUZZLE-02, FR-GAMES-02, US-38.
const { test, expect } = require('@playwright/test');
const { loginAs, freshUserId } = require('./helpers');

const SHOTS = '../../test-results/owldoku';
const OWLS3 = [5, 1, 3, 0, 4, 2];
const cell = (page, r, c) => page.locator(`#board .ocell[data-r="${r}"][data-c="${c}"]`);
/** The owls of a puzzle {size, regions}, column per row, by plain backtracking (the members' puzzle is never asked about). */
function solveOwls({ size: n, regions }) {
  const cols = [];
  const go = (r, usedC, usedG) => {
    if (r === n) return true;
    for (let c = 0; c < n; c++) {
      const g = regions[r].charCodeAt(c) - 97;
      if (usedC & (1 << c) || usedG & (1 << g) || (r && Math.abs(cols[r - 1] - c) < 2)) continue;
      cols[r] = c;
      if (go(r + 1, usedC | (1 << c), usedG | (1 << g))) return true;
    }
    return false;
  };
  go(0, 0, 0);
  return cols;
}
async function owl(page, r, c) {
  await cell(page, r, c).dblclick();
  await expect(cell(page, r, c)).not.toHaveClass(/pending/);
}

test('TC-E2E-201: a practice Owldoku: a click crosses, a double click places an owl, a wrong owl costs a heart, a clash costs none; the address keeps it; solved', async ({ page, context }, info) => {
  await page.goto('/games/owldoku.html#p=easy.3');
  await expect(page.locator('#pz-title')).toHaveText('🦉 Practice · Easy 6 × 6');
  await expect(page.locator('#board .ocell')).toHaveCount(36);
  await expect(page.locator('#hearts')).toHaveText('❤️❤️❤️');

  await cell(page, 0, 0).click();
  await expect(cell(page, 0, 0)).toHaveClass(/\bx\b/);
  await page.waitForTimeout(450);                                   // not a double click
  await cell(page, 0, 0).click();
  await expect(cell(page, 0, 0)).not.toHaveClass(/\bx\b/);
  await page.waitForTimeout(450);
  // An owl: the squares it blocks get a dot.
  await owl(page, 0, 5);
  await expect(cell(page, 0, 5)).toHaveClass(/\bowl\b/);
  await expect(cell(page, 0, 5).locator('.owl-pic')).toHaveCount(1);
  await expect(cell(page, 1, 4)).toHaveClass(/\bauto\b/);
  await expect(cell(page, 0, 0)).toHaveClass(/\bauto\b/);
  // An owl in the same row is refused before it is sent: no heart lost.
  await owl(page, 0, 1);
  await expect(cell(page, 0, 1)).not.toHaveClass(/\bowl\b|\bwrong\b/);
  await expect(page.locator('#pz-count')).toContainText('row already has its owl');
  await expect(page.locator('#hearts')).toHaveText('❤️❤️❤️');
  // A wrong guess costs a heart and leaves a red cross.
  await owl(page, 2, 2);
  await expect(cell(page, 2, 2)).toHaveClass(/\bwrong\b/);
  await expect(page.locator('#hearts')).toHaveText('❤️❤️🤍');
  // A drag crosses a stretch; Undo takes it back.
  await cell(page, 5, 1).scrollIntoViewIfNeeded();                // the bottom row may be under the fold
  const a = await cell(page, 5, 0).boundingBox(), b = await cell(page, 5, 1).boundingBox();
  await page.mouse.move(a.x + a.width / 2, a.y + a.height / 2);
  await page.mouse.down();
  await page.mouse.move(b.x + b.width / 2, b.y + b.height / 2, { steps: 4 });
  await page.mouse.up();
  await expect(page.locator('#board .ocell.x')).toHaveCount(2);
  await page.click('#undo');
  await expect(page.locator('#board .ocell.x')).toHaveCount(0);
  await expect(page).toHaveURL(/#p=easy\.3\.[A-Za-z0-9_-]+$/);
  await page.reload();
  await expect(cell(page, 0, 5)).toHaveClass(/\bowl\b/);
  await expect(cell(page, 2, 2)).toHaveClass(/\bwrong\b/);
  await expect(page.locator('#hearts')).toHaveText('❤️❤️🤍');

  for (let r = 1; r < 6; r++) {
    await page.waitForTimeout(400);
    await owl(page, r, OWLS3[r]);
  }
  await expect(page.locator('#result')).toBeVisible();
  await expect(page.locator('#result-text')).toContainText('Easy 6 × 6 in');
  await expect(page.locator('#board')).toHaveClass(/solved/);
  await page.click('#result-close');
  await page.screenshot({ path: `${SHOTS}-solved-${info.project.name}.png`, fullPage: true });
  expect(await page.evaluate(() => [localStorage.length, sessionStorage.length])).toEqual([0, 0]);
  expect(await context.cookies()).toEqual([]);
});

test('TC-E2E-202: three wrong owls: out of hearts; start over with three new hearts, or keep going for fun', async ({ page }) => {
  await page.goto('/games/owldoku.html#p=easy.3');
  for (const [r, c] of [[2, 2], [4, 0], [5, 4]]) {
    await owl(page, r, c);
    await page.waitForTimeout(400);
  }
  await expect(page.locator('#lost')).toBeVisible();
  await expect(page.locator('#hearts')).toHaveText('🤍🤍🤍');
  await page.click('#lost-on');
  await owl(page, 0, 5);
  await expect(cell(page, 0, 5)).toHaveClass(/\bowl\b/);           // for fun, owls still go in
  await page.click('#restart');
  await page.click('#confirm-ok');
  await expect(page.locator('#board .ocell.owl, #board .ocell.wrong')).toHaveCount(0);
  await expect(page.locator('#hearts')).toHaveText('❤️❤️❤️');
});

test('TC-E2E-203: on a phone one finger crosses, two quick taps place an owl, and the 10 × 10 board fits with squares big enough to hit', async ({ page }, info) => {
  test.skip(info.project.name !== 'mobile', 'touch: the phone project only');
  await page.goto('/games/owldoku.html#p=easy.3');
  const centre = async (r, c) => { const b = await cell(page, r, c).boundingBox(); return [b.x + b.width / 2, b.y + b.height / 2]; };
  let [x, y] = await centre(3, 3);
  await page.touchscreen.tap(x, y);
  await expect(cell(page, 3, 3)).toHaveClass(/\bx\b/);
  [x, y] = await centre(0, 5);
  await page.touchscreen.tap(x, y);
  await page.touchscreen.tap(x, y);
  await expect(cell(page, 0, 5)).toHaveClass(/\bowl\b/);
  await expect(cell(page, 0, 5)).not.toHaveClass(/\bx\b/);
  await expect(page.locator('#hearts')).toHaveText('❤️❤️❤️');

  await page.goto('/games/owldoku.html#p=hard.42');
  await page.reload();                                             // only the '#' changed: load the page again
  await expect(page.locator('#pz-title')).toContainText('Hard 10 × 10');
  const m = await page.evaluate(() => ({
    scroll: document.documentElement.scrollWidth - innerWidth,
    cell: document.querySelector('#board .ocell').getBoundingClientRect().width,
    touch: getComputedStyle(document.getElementById('board')).touchAction,
  }));
  expect(m.scroll).toBeLessThanOrEqual(0);
  expect(m.cell).toBeGreaterThanOrEqual(30);
  expect(m.touch).toBe('none');                                    // a drag on the board crosses, it does not scroll the page
  await expect(page.locator('nav .nav-burger')).toBeVisible();     // the phone menu bar (FR-GAMES-18)
  await page.screenshot({ path: `${SHOTS}-phone.png`, fullPage: true });
});

test('TC-E2E-204: the puzzle of the day for a member: guests cannot ask about it; the server keeps the hearts lost over a fresh start; solved, the time is on today\'s list', async ({ page, context, baseURL, request }) => {
  await loginAs(context, baseURL, freshUserId(), 'Owl tester');
  const daily = await (await page.request.post('/owldoku.php?action=daily', { data: { level: 'easy' } })).json();   // as the member
  const { seed, day, size } = daily.puzzle;
  // Logged out, the members' puzzle answers nothing (the cheat: find the owls as a guest, then solve it as a member).
  const guess = await request.post('/owldoku.php?action=place', { data: { level: 'easy', seed, r: 0, c: 0, day } });
  expect([guess.status(), (await guess.json()).error]).toEqual([401, 'login_needed']);
  const owls = solveOwls(daily.puzzle);
  expect(owls).toHaveLength(size);
  const wrong = [size - 1, (owls[size - 1] + 3) % size];
  await page.goto('/games/owldoku.html');
  await page.click('[data-daily=easy]');
  await expect(page.locator('#pz-title')).toHaveText('📅 Puzzle of the day · Easy 6 × 6');
  await owl(page, ...wrong);
  await expect(page.locator('#hearts')).toHaveText('❤️❤️🤍');
  await page.goto('/games/owldoku.html');                          // a fresh start without the address: the server still knows
  await page.click('[data-daily=easy]');
  await expect(page.locator('#hearts')).toHaveText('❤️❤️🤍');
  for (let r = 0; r < size; r++) {
    await owl(page, r, owls[r]);
    await page.waitForTimeout(400);
  }
  await expect(page.locator('#result')).toBeVisible();
  await expect(page.locator('#result-text')).toContainText('Today\'s Easy puzzle in');
  await expect(page.locator('#side-body .side-list li.you')).toContainText('Owl tester (you)');
});
