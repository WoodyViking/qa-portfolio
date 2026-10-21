// The menu bar of the HAKOO Games pages on a phone (js/shared/game-nav.js): ☰ on the left for the
// page (All games, Game Rules, ⚙ and, with the 3D reward, turning the board), 👤▾ on the right for
// the language and the login. On a computer it stays one bar. The 'mobile' project is the phone.
// Fixture: Alice has won on every level of every game (the Medium unlock: the board turns).
// Traceability: FR-GAMES-18, FR-GAMES-10, US-28.
const { test, expect } = require('@playwright/test');
const { USERS, loginAs } = require('./helpers');

const SHOTS = '../../test-results/game-nav';

test('TC-E2E-194: on a phone the bar is one row with ☰ (the page) and 👤▾ (language and login); on a computer nothing is folded', async ({ page }, info) => {
  await page.goto('/games/hnefatafl.html');
  await expect(page.locator('#auth-area .auth-login')).toHaveCount(1);
  const burger = page.locator('nav .nav-burger'), more = page.locator('nav .nav-more');
  if (info.project.name !== 'mobile') {
    await expect(burger).toBeHidden();
    await expect(more).toBeHidden();
    for (const sel of ['#nav-rules', '#settings-open', '#auth-area .auth-login', '.lang-current']) await expect(page.locator(sel)).toBeVisible();
    return;
  }
  // Folded: one row, nothing of either menu shows.
  expect((await page.locator('nav').boundingBox()).height).toBeLessThan(70);
  for (const sel of ['#nav-rules', '#settings-open', '#auth-area .auth-login', '.lang-current']) await expect(page.locator(sel)).toBeHidden();
  // ☰: the page's own links and ⚙; the corner stays closed.
  await burger.click();
  await expect(burger).toHaveAttribute('aria-expanded', 'true');
  for (const sel of ['nav .nav-links a[href="../hakoo-games.html"]', '#nav-rules', '#settings-open']) await expect(page.locator(sel)).toBeVisible();
  await expect(page.locator('#auth-area .auth-login')).toBeHidden();
  await page.screenshot({ path: `${SHOTS}-page-menu.png` });
  // 👤▾: the language and the login; ☰ closes.
  await more.click();
  await expect(more).toHaveAttribute('aria-expanded', 'true');
  await expect(burger).toHaveAttribute('aria-expanded', 'false');
  await expect(page.locator('#auth-area .auth-login')).toBeVisible();
  await expect(page.locator('.lang-current')).toBeVisible();
  await expect(page.locator('#nav-rules')).toBeHidden();
  await page.screenshot({ path: `${SHOTS}-corner-menu.png` });
  // Escape or a tap outside closes it; ⚙ opens the settings and closes the menu.
  await page.keyboard.press('Escape');
  await expect(page.locator('#auth-area .auth-login')).toBeHidden();
  await more.click();
  await page.mouse.click(4, 400);                                   // beside the page, under the menu

  await expect(page.locator('#auth-area .auth-login')).toBeHidden();
  await burger.click();
  await page.click('#settings-open');
  await expect(page.locator('#settings')).toBeVisible();
  await expect(burger).toHaveAttribute('aria-expanded', 'false');
});

test('TC-E2E-195: with the 3D reward, the buttons that turn and tilt the board are under it on a computer; a phone has none (the board is zoomed and moved instead, TC-E2E-205)', async ({ page, context, baseURL }, info) => {
  await loginAs(context, baseURL, USERS.alice, 'Alice');
  await page.goto('/games/hnefatafl.html');
  await page.click('#go-computer');
  await page.check('input[name=color][value=black]');
  await page.check('input[name=difficulty][value=easy]');
  await page.click('#start');
  await expect(page.locator('.bview')).toHaveCount(info.project.name === 'mobile' ? 0 : 1);
  // Logged in, the corner button shows the member's picture when they have one (the test users have none: 👤).
  await expect(page.locator('#auth-area .auth-out')).toHaveCount(1);
  const pic = await page.locator('#auth-area img.auth-av').getAttribute('src');
  await expect(page.locator('nav .nav-more img')).toHaveCount(pic ? 1 : 0);
  if (info.project.name !== 'mobile') {
    await expect(page.locator('.view-bar')).toBeVisible();
    await expect(page.locator('nav .nav-view')).toBeHidden();
    return;
  }
  await expect(page.locator('.view-bar')).toHaveCount(0);
  await page.click('nav .nav-burger');
  await expect(page.locator('nav .nav-view')).toHaveCount(0);
});
