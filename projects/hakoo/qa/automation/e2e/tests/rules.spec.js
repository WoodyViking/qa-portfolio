// The game rules: the short rules beside every game's start page and the rules pages with pictures
// (games/<game>-rules.html; the pictures by js/shared/rules-diagrams.js with each game's own board).
// Traceability: FR-GAMES-16, FR-GAMES-17.
const { test, expect } = require('@playwright/test');

const GAMES = ['gomoku', 'chess', 'othello', 'hnefatafl', 'stratego', 'backgammon', 'checkers', 'morris', 'ludo', 'yatzy', 'nonogram', 'sudoku', 'owldoku'];
const SHOTS = '../../test-results/rules';

test('TC-E2E-191: every game\'s start page has the rules in short with a picture, beside the choices on a wide screen and below them on a phone', async ({ page }, info) => {
  for (const g of GAMES) {
    await page.goto(`/games/${g}.html`);
    const aside = page.locator('#v-home .rules-aside');
    await expect(aside).toBeVisible();
    await expect(aside.locator('li')).not.toHaveCount(0);
    await expect(aside.locator('figure[data-drawn]')).toHaveCount(1);
    await expect(aside.locator('a.more')).toHaveAttribute('href', `${g}-rules.html`);
    const a = await aside.boundingBox(), c = await page.locator('#v-home .choices').first().boundingBox();
    if (info.project.name === 'mobile') expect(a.y).toBeGreaterThan(c.y);                       // under the choices
    else expect(a.x).toBeGreaterThan(c.x + c.width - 1);                                         // to the right of them
  }
  await page.goto('/games/chess.html');
  await page.screenshot({ path: `${SHOTS}-chess-start-${info.project.name}.png`, fullPage: true });
});

test('TC-E2E-192: every rules page draws all its pictures with their marks and arrows, and leads back to the game', async ({ page }, info) => {
  const errors = [];
  page.on('pageerror', e => errors.push(e.message));
  for (const g of GAMES) {
    await page.goto(`/games/${g}-rules.html`);
    await expect(page.locator('.rhead a.play')).toHaveAttribute('href', `${g}.html`);
    const figs = await page.locator('figure.rdiag').count();
    expect(figs, g).toBeGreaterThan(2);
    await expect(page.locator('figure.rdiag[data-drawn]')).toHaveCount(figs);
    const missing = await page.evaluate(() => [...document.querySelectorAll('figure.rdiag')].filter(f => {
      const want = ['ring', 'dot', 'x'].reduce((n, k) => n + (f.dataset[k] || '').split(/\s+/).filter(Boolean).length, 0);
      const arrows = (f.dataset.arrows || '').split(/\s+/).filter(Boolean).length;
      return f.querySelectorAll('.rd-m').length !== want || f.querySelectorAll('polyline').length !== arrows;
    }).map(f => f.querySelector('figcaption').textContent));
    expect(missing, g).toEqual([]);
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), `${g}: no sideways scroll`).toBe(true);
  }
  expect(errors).toEqual([]);
  await page.goto('/games/chess-rules.html#castling');
  await page.screenshot({ path: `${SHOTS}-chess-castling-${info.project.name}.png` });
});

test('TC-E2E-198: the rules in short say what the game does by default (DEF-018)', async ({ page }) => {
  // Ludo: the box names the way out of the nest that is ticked in the setup (the engine's default, LudoRules).
  await page.goto('/games/ludo.html');
  await page.click('#go-computer');
  const exit = (await page.locator('input[name=exit]:checked').locator('..').innerText()).trim().toLowerCase();
  await page.goto('/games/ludo.html');
  await expect(page.locator('#v-home .rules-aside')).toContainText(exit);
  // Stratego: the Spy beats the Marshal only when the Spy attacks.
  await page.goto('/games/stratego.html');
  await expect(page.locator('#v-home .rules-aside')).toContainText('The Spy beats the Marshal when the Spy attacks');
});

test('TC-E2E-193: during a game the Rules button opens the rules in a dialog that links to the full page', async ({ page }) => {
  await page.goto('/games/othello.html');
  await page.click('#go-computer');
  await page.click('#start');
  await expect(page.locator('#v-game')).toBeVisible();
  await page.click('#game-rules');
  await expect(page.locator('#rules')).toBeVisible();
  await expect(page.locator('#rules a[href="othello-rules.html"]')).toBeVisible();
  await expect(page.locator('#rules-play')).toBeHidden();
});
