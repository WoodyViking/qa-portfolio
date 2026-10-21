// Saved games: "My games" on the profile, and watching a saved game on its game page.
// Fixture: Alice kept her first Gomoku win on Hard and a starred chess game against Bob.
// Traceability: FR-SAVED-03, FR-SAVED-04, US-26.
const { test, expect } = require('@playwright/test');
const { USERS, loginAs } = require('./helpers');

const SHOTS = '../../test-results/saved-games';

test('TC-E2E-120: My games lists the saved games, and Watch opens one to look back through', async ({ page, context, baseURL }, info) => {
  await loginAs(context, baseURL, USERS.alice, 'Alice');
  await page.goto('/profile.html#my-games');
  const card = page.locator('#my-games');
  await expect(card.locator('.sg')).toHaveCount(2);
  await expect(card.locator('.sg').first()).toContainText('vs Bob');
  await expect(card.locator('.sg').nth(1)).toContainText('vs Computer (Hard)');
  await expect(card.locator('.sg').first().locator('.sg-star')).toHaveCount(1);          // games against members can be starred
  await expect(card.locator('.sg').nth(1).locator('.sg-star')).toHaveCount(0);           // a first win is always kept
  // Three columns: against members, rewards (first wins), against the computer
  await expect(card.locator('.sg-col h3')).toHaveText(['🤝 Against members 1', '🏆 Rewards 1', '🤖 Against the computer 0']);
  await expect(card.locator('.sg-col[data-reason="friend"] .sg')).toContainText('vs Bob');
  await expect(card.locator('.sg-col[data-reason="first_win"] .sg')).toContainText('vs Computer (Hard)');
  await expect(card.locator('.sg-col[data-reason="computer"] .empty')).toContainText('None yet');
  if (info.project.name === 'chromium') await card.screenshot({ path: `${SHOTS}-profile.png` });

  await card.locator('.sg').first().locator('a', { hasText: 'Watch' }).click();
  await expect(page).toHaveURL(/games\/chess\.html#saved=2$/);
  await expect(page.locator('#turn')).toHaveText('📼 Saved game: You won! 🎉');
  await expect(page.locator('#result')).not.toBeVisible();                     // no pop-up for an old game
  await expect(page.locator('#movelist .mv')).toHaveCount(7);
  await page.click('#mv-first');
  await expect(page.locator('#turn')).toHaveText('Looking back: after move 0 of 7');
  await expect(page.locator('#board .sq[data-r="6"][data-c="4"]')).toHaveAttribute('aria-label', 'e2, white pawn');
  await page.click('#movelist [data-ply="7"]');
  await expect(page.locator('#turn')).toHaveText('📼 Saved game: You won! 🎉');
  if (info.project.name === 'chromium') await page.screenshot({ path: `${SHOTS}-watch.png`, fullPage: true });
});

test('TC-E2E-121: a saved game can be starred and unstarred; others cannot watch it', async ({ page, context, baseURL, browser }, info) => {
  test.skip(info.project.name !== 'chromium', "changes Alice's data: one browser only (DEF-012)");
  await loginAs(context, baseURL, USERS.alice, 'Alice');
  await page.goto('/profile.html#my-games');
  const star = page.locator('#my-games .sg').first().locator('.sg-star');
  await star.click();
  await expect(star).not.toHaveClass(/\bon\b/);
  await page.reload();
  await expect(page.locator('#my-games .sg').first().locator('.sg-star')).not.toHaveClass(/\bon\b/);
  await page.locator('#my-games .sg').first().locator('.sg-star').click();                  // back as it was
  await expect(page.locator('#my-games .sg').first().locator('.sg-star')).toHaveClass(/\bon\b/);

  const guest = await (await browser.newContext()).newPage();
  await guest.goto('/games/chess.html#saved=2');
  await expect(guest.locator('#error')).toContainText('Log in with Discord');
});

test('TC-E2E-232: My games shows its three columns side by side when they fit, one under the other when not, at every text size; nothing is wider than the window', async ({ page, context, baseURL }, info) => {
  test.skip(info.project.name !== 'chromium', 'sets its own window sizes');
  await loginAs(context, baseURL, USERS.alice, 'Alice');
  for (const size of ['normal', 'large', 'xl']) {
    await page.addInitScript(s => localStorage.setItem('wf_textsize', s), size);
    for (const [width, height] of [[390, 844], [1366, 768], [1897, 998], [2560, 1271]]) {
      await page.setViewportSize({ width, height });
      await page.goto('/profile.html#my-games');
      const cols = page.locator('#my-games .sg-col');
      await expect(cols).toHaveCount(3);
      const tops = await cols.evaluateAll(els => els.map(el => Math.round(el.getBoundingClientRect().top)));
      const lefts = await cols.evaluateAll(els => els.map(el => Math.round(el.getBoundingClientRect().left)));
      if (width === 390) expect(new Set(lefts).size, `${size} ${width}: one under the other`).toBe(1);
      if (width >= 1897) expect(new Set(tops).size, `${size} ${width}: side by side`).toBe(1);
      expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), `${size} ${width}: no sideways scroll`).toBe(true);
      if (size !== 'large') await page.locator('#my-games').screenshot({ path: `${SHOTS}-columns-${size}-${width}.png` });
    }
  }
});
