// Sudoku (games/sudoku.html, js/games/sudoku.js, js/shared/puzzle-page.js, ../sudoku.php).
// The practice puzzle Easy #7 is fixed: its digits and solution are below (made by sudoku-engine.inc.php).
// Traceability: FR-SUDOKU-07, FR-SUDOKU-08, FR-PUZZLE-01, FR-PUZZLE-02, FR-GAMES-02, FR-GAMES-18, US-38.
const { test, expect } = require('@playwright/test');
const { loginAs, freshUserId } = require('./helpers');

const SHOTS = '../../test-results/sudoku';
const EASY7 = '910000802005020160800130754071000300500000001003000940149076008057080400308000097';
const SOLVED7 = '914657832735428169862139754471295386596843271283761945149376528657982413328514697';
const cell = (page, r, c) => page.locator(`#board .scell[data-r="${r}"][data-c="${c}"]`);

/** A plain backtracking solver, for the puzzle of the day (whose solution the page never gets). */
function solve(g) {
  const a = [...g].map(Number);
  const ok = (i, d) => {
    const r = Math.floor(i / 9), c = i % 9;
    for (let k = 0; k < 9; k++) {
      const b = (Math.floor(r / 3) * 3 + Math.floor(k / 3)) * 9 + Math.floor(c / 3) * 3 + k % 3;
      if (a[r * 9 + k] === d || a[k * 9 + c] === d || a[b] === d) return false;
    }
    return true;
  };
  const go = () => {
    const i = a.indexOf(0);
    if (i < 0) return true;
    for (let d = 1; d <= 9; d++) if (ok(i, d)) { a[i] = d; if (go()) return true; a[i] = 0; }
    return false;
  };
  go();
  return a.join('');
}

test('TC-E2E-199: a practice Sudoku: digits, a clash in red, notes, undo; the address keeps it over a reload; solved, nothing is stored', async ({ page, context }, info) => {
  await page.goto('/games/sudoku.html#p=easy.7');
  await expect(page.locator('#pz-title')).toHaveText('🔢 Practice · Easy 9 × 9');
  await expect(page.locator('#board .scell')).toHaveCount(81);
  await expect(page.locator('#board .scell.given')).toHaveCount(EASY7.replace(/0/g, '').length);

  // A square, then a digit button.
  await cell(page, 0, 2).click();
  await expect(cell(page, 0, 2)).toHaveClass(/\bsel\b/);
  await expect(cell(page, 0, 0)).toHaveClass(/\barea\b/);           // its row lights up
  await page.click('.pad-d[data-d="4"]');
  await expect(cell(page, 0, 2)).toHaveText('4');
  await expect(cell(page, 0, 2)).toHaveClass(/\bmine\b/);
  // A 9 next to the given 9 clashes: both red; Delete clears it.
  await cell(page, 0, 3).click();
  await page.keyboard.press('9');
  await expect(cell(page, 0, 3)).toHaveClass(/\bbad\b/);
  await expect(cell(page, 0, 0)).toHaveClass(/\bbad\b/);
  await expect(page.locator('#pz-count')).toContainText('clash');
  await page.keyboard.press('Delete');
  await expect(page.locator('#board .scell.bad')).toHaveCount(0);
  // Notes: small digits; Undo takes the last one back.
  await page.click('#notes');
  await page.click('.pad-d[data-d="5"]');
  await page.click('.pad-d[data-d="6"]');
  await expect(cell(page, 0, 3).locator('.snotes')).toHaveText('56');
  await page.click('#undo');
  await expect(cell(page, 0, 3).locator('.snotes')).toHaveText('5');
  await page.click('#notes');
  await expect(page).toHaveURL(/#p=easy\.7\.[A-Za-z0-9_-]+$/);
  await page.reload();
  await expect(cell(page, 0, 2)).toHaveText('4');
  await expect(cell(page, 0, 3).locator('.snotes')).toHaveText('5');
  // A digit over the notes hides them; erasing it brings them back, erasing again clears them.
  await cell(page, 0, 3).click();
  await page.keyboard.press('7');
  await expect(cell(page, 0, 3)).toHaveText('7');
  await page.keyboard.press('Delete');
  await expect(cell(page, 0, 3).locator('.snotes')).toHaveText('5');
  await page.click('#erase');
  await expect(cell(page, 0, 3).locator('.snotes')).toHaveCount(0);
  await page.click('#notes');
  await page.click('.pad-d[data-d="5"]');
  await page.click('#notes');

  // The rest with the keyboard: writing the digit clears the note.
  for (let i = 0; i < 81; i++) {
    if (EASY7[i] !== '0' || i === 2) continue;
    await cell(page, Math.floor(i / 9), i % 9).click();
    await page.keyboard.press(SOLVED7[i]);
  }
  await expect(page.locator('#result')).toBeVisible();
  await expect(page.locator('#result-text')).toContainText('Easy 9 × 9 in');
  await expect(page.locator('#board')).toHaveClass(/solved/);
  await page.click('#result-close');
  await page.screenshot({ path: `${SHOTS}-solved-${info.project.name}.png`, fullPage: true });
  expect(await page.evaluate(() => [localStorage.length, sessionStorage.length])).toEqual([0, 0]);
  expect(await context.cookies()).toEqual([]);
});

test('TC-E2E-200: the puzzle of the day for a member: the digit buttons fit a phone under the grid; solved, the time is on today\'s list', async ({ page, context, baseURL, request }, info) => {
  await loginAs(context, baseURL, freshUserId(), 'Sudoku tester');
  const guest = await (await request.post('/sudoku.php?action=daily', { data: { level: 'easy' } })).json();   // not logged in
  const daily = await (await page.request.post('/sudoku.php?action=daily', { data: { level: 'easy' } })).json();   // as the member
  expect(daily.puzzle.seed).not.toBe(guest.puzzle.seed);            // members and guests get different puzzles
  const answer = solve(daily.puzzle.grid);
  await page.goto('/games/sudoku.html');
  await page.click('[data-daily=easy]');
  await expect(page.locator('#pz-title')).toHaveText('📅 Puzzle of the day · Easy 9 × 9');
  await expect(page.locator('#side-body')).not.toContainText('Log in with Discord');   // members see the list (other runs may be on it)

  const m = await page.evaluate(() => {
    const r = el => el.getBoundingClientRect();
    const board = r(document.getElementById('board')), pad = r(document.getElementById('pad')), one = r(document.querySelector('.pad-d'));
    return { scroll: document.documentElement.scrollWidth - innerWidth, board, pad, one };
  });
  expect(m.scroll).toBeLessThanOrEqual(0);
  if (info.project.name === 'mobile') {
    expect(m.pad.top).toBeGreaterThan(m.board.bottom - 1);           // under the grid
    expect(m.one.height).toBeGreaterThanOrEqual(44);                 // big enough for a thumb
    expect(m.board.width).toBeGreaterThan(330);                      // the grid uses the width of the phone
    await page.screenshot({ path: `${SHOTS}-phone.png`, fullPage: true });
  } else {
    expect(m.pad.left).toBeGreaterThan(m.board.right - 1);           // beside the grid
  }

  for (let i = 0; i < 81; i++) {
    if (daily.puzzle.grid[i] !== '0') continue;
    await cell(page, Math.floor(i / 9), i % 9).click();
    await page.click(`.pad-d[data-d="${answer[i]}"]`);
  }
  await expect(page.locator('#result')).toBeVisible();
  await expect(page.locator('#result-text')).toContainText('Today\'s Easy puzzle in');
  await expect(page.locator('#result-text')).toContainText('1 day in a row');
  await expect(page.locator('#side-body .side-list li.you')).toContainText('Sudoku tester (you)');
  await page.click('#result-new');
  await expect(page.locator('[data-daily=easy] .state')).toContainText('✓');
});
