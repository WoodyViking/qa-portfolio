// The players beside a game (js/shared/players-panel.js on each game's page): on a wide screen, the list and the
// chosen player's viking at the sides; on a puzzle's page, today's puzzlers instead of the viking. Not shown, nor asked for, on a
// smaller screen, except today's puzzlers, which then take the short rules' place. Traceability: FR-PLAYERS-08..10.
const { test, expect } = require('@playwright/test');
const { loginAs, freshUserId } = require('./helpers');

test.beforeEach(async ({}, info) => {
  test.skip(info.project.name === 'mobile', 'a wide screen');
});

test('TC-E2E-223: on a game page, a wide screen shows the players and the member\'s own viking; a smaller one shows nothing and asks for nothing', async ({ page, context, baseURL }, info) => {
  const me = freshUserId(), name = `Torvald${me.slice(-4)}`;
  await loginAs(context, baseURL, me, name);
  const asked = [];
  page.on('request', r => { if (r.url().includes('members.php')) asked.push(r.url()); });
  await page.setViewportSize({ width: 1280, height: 900 });
  await page.goto('/games/gomoku.html');
  await expect(page.locator('#people')).toBeHidden();
  await page.waitForTimeout(500);
  expect(asked).toEqual([]);

  await page.setViewportSize({ width: 1920, height: 1000 });                         // made wider: now it shows
  await expect(page.locator('#people .pl-list')).toBeVisible();
  await expect(page.locator('#pl-items .pl-item', { hasText: name })).toHaveAttribute('aria-pressed', 'true');
  await expect(page.locator('#pl-items .pl-item', { hasText: 'Bob' })).toHaveCount(1);
  await expect(page.locator('#pl-who')).toContainText(name);
  await expect(page.locator('#pl-stage canvas.viking-3d, #pl-stage svg.viking-av')).toHaveCount(1);
  await expect(page.locator('#pl-info a', { hasText: 'Change your viking' })).toHaveAttribute('href', /\/profile\.html#viking$/);
  await page.screenshot({ path: `../../test-results/game-players-${info.project.name}.png` });
});

test('TC-E2E-224: on a puzzle\'s page, the players at the left and only today\'s puzzlers at the right (no viking); a click opens a profile; logged out, a blurred sample and a login that comes back here', async ({ page, context, baseURL, browser }) => {
  await page.setViewportSize({ width: 1920, height: 1000 });
  const anon = await browser.newPage({ viewport: { width: 1920, height: 1000 } });
  await anon.goto('/games/sudoku.html');
  await expect(anon.locator('#pl-title')).toHaveText('🧔 Players');                  // the players at the left, as on every page
  await expect(anon.locator('#pl-day')).toBeHidden();                                // no puzzlers for a visitor
  await expect(anon.locator('#pl-card, #pl-stage')).toHaveCount(0);                 // and no viking card on a puzzle's page
  await expect(anon.locator('#pl-items')).toHaveClass(/blur/);
  await expect(anon.locator('#pl-call a')).toHaveAttribute('href', /discord-auth\.php\?action=start&return=games%2Fsudoku\.html$/);
  await anon.close();

  const me = freshUserId(), name = `Sunniva${me.slice(-4)}`;
  await loginAs(context, baseURL, me, name);
  await page.goto('/games/sudoku.html');
  await expect(page.locator('#pl-day-title')).toHaveText("🧩 Today's puzzlers");      // alone at the right
  await expect(page.locator('#pl-day-note')).toBeVisible();
  await expect(page.locator('#pl-card, #pl-stage, #pl-who')).toHaveCount(0);           // no viking
  await expect(page.locator('#pl-day-items .pl-item', { hasText: name })).toHaveCount(0);   // not opened yet
  await expect(page.locator('#pl-items .pl-item', { hasText: name })).toHaveCount(1);       // but in the players at the left
  expect((await page.request.post('/sudoku.php?action=daily', { data: { level: 'easy' } })).status()).toBe(200);
  await page.reload();
  const mine = page.locator('#pl-day-items .pl-item', { hasText: name });
  await expect(mine.locator('.pl-times').first()).toContainText('Eplaying');
  await expect(mine.locator('.pl-stats')).toHaveText('🔥 0 in a row · ✓ 0 solved');
  await mine.click();                                                                  // a click opens her profile
  await expect(page).toHaveURL(new RegExp(`/members\\.html#m=${me}$`));
});

test('TC-E2E-229: with a larger text size the players beside a game stay inside the window, or are left out when they no longer fit; nothing is wider than the window', async ({ page }) => {
  await page.addInitScript(() => localStorage.setItem('wf_textsize', 'xl'));          // the page zoomed by 1.22
  const inside = () => page.evaluate(() => [...document.querySelectorAll('.pl-list, .pl-card')]
    .filter(el => el.getClientRects().length).every(el => { const b = el.getBoundingClientRect(); return b.left >= 0 && b.right <= innerWidth; }));
  await page.setViewportSize({ width: 2560, height: 1300 });                          // room for them: at the sides, in view
  await page.goto('/games/chess.html');
  await expect(page.locator('#people .pl-list')).toBeVisible();
  expect(await inside()).toBe(true);
  await page.setViewportSize({ width: 1897, height: 998 });                           // 1897 / 1.22 < 1680: no room, not shown
  await expect(page.locator('#people .pl-list')).toBeHidden();
  expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true);
  await page.evaluate(() => { localStorage.setItem('wf_textsize', 'normal'); document.documentElement.removeAttribute('data-textsize'); });
  await expect(page.locator('#people .pl-list')).toBeVisible();                       // normal text: room again, back at the sides
  expect(await inside()).toBe(true);
});

test('TC-E2E-236: on a puzzle\'s page without room at the sides, today\'s puzzlers take the rules\' place at the right of the start page and the rules go down; with room they are at the side again', async ({ page, context, baseURL }, info) => {
  test.skip(info.project.name !== 'chromium', 'sets its own window sizes');
  await loginAs(context, baseURL, freshUserId(), 'Puzzler');
  const box = sel => page.locator(sel).boundingBox();
  for (const size of ['normal', 'xl']) {
    await page.addInitScript(s => localStorage.setItem('wf_textsize', s), size);
    await page.setViewportSize({ width: 1366, height: 900 });                          // a laptop
    await page.goto('/games/sudoku.html');
    await expect(page.locator('#v-home > #pl-day')).toBeVisible();                     // on the start page
    await expect(page.locator('#pl-items')).toBeHidden();                              // the players list waits for room
    const [day, levels, rules] = [await box('#pl-day'), await box('#daily-levels'), await box('#v-home .rules-aside')];
    expect(day.x, `${size}: at the right`).toBeGreaterThan(levels.x + levels.width);
    expect(day.y, `${size}: at the top`).toBeLessThan(levels.y);
    expect(rules.y, `${size}: the rules under the levels`).toBeGreaterThan(levels.y + levels.height);
    expect(Math.round(rules.x), `${size}: in the main column`).toBe(Math.round(levels.x));
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true);
    if (size === 'normal') await page.screenshot({ path: '../../test-results/puzzle-day-laptop.png' });
  }
  await page.setViewportSize({ width: 1920, height: 1000 });                           // normal text (the last was xl): room at the sides again
  await page.evaluate(() => { localStorage.setItem('wf_textsize', 'normal'); document.documentElement.removeAttribute('data-textsize'); });
  await expect(page.locator('#people > #pl-day')).toBeVisible();
  await expect(page.locator('#pl-items')).toBeVisible();
  const [day, rules] = [await box('#pl-day'), await box('#v-home .rules-aside')];
  expect(day.x).toBeGreaterThan(rules.x + rules.width);                               // the rules beside the start page, the puzzlers outside
  await page.setViewportSize({ width: 390, height: 844 });                             // a phone: the puzzlers before the rules
  await expect(page.locator('#v-home > #pl-day')).toBeVisible();
  expect((await box('#pl-day')).y).toBeLessThan((await box('#v-home .rules-aside')).y);
});
