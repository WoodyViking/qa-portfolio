// Gomoku rewards, looks and the crown (games/gomoku.html, profile.html, js/shared/crowns.js).
// Fixture: Alice has won on every level (purple/gold 3D stones, HAKOO board, crown shown).
// Traceability: FR-GOMOKU-12..16, US-17, US-18.
const { test, expect } = require('@playwright/test');
const { USERS, loginAs, freshUserId } = require('./helpers');

const SHOTS = '../../test-results/gomoku-rewards';

test('TC-E2E-070: logged out, Gomoku explains the rewards and still works without login', async ({ page }) => {
  await page.goto('/games/gomoku.html');
  const box = page.locator('#rewards');
  await expect(box).toContainText('Rewards need a HAKOO login');
  await expect(box.locator('a[href*="discord-auth.php?action=start"]')).toHaveCount(1);
  await page.click('#go-computer');
  await expect(page.locator('#reward-note')).toContainText('does not count');
});

test('TC-E2E-071: a member who won everything sees the rewards and plays with their own look', async ({ page, context, baseURL }, info) => {
  await loginAs(context, baseURL, USERS.alice, 'Alice');
  await page.goto('/games/gomoku.html');
  await expect(page.locator('#rewards li.got')).toHaveCount(3);
  const look = await page.evaluate(() => {
    const s = getComputedStyle(document.documentElement);
    return [s.getPropertyValue('--stone-black').trim(), s.getPropertyValue('--board-bg').trim(), document.documentElement.classList.contains('s3d')];
  });
  expect(look).toEqual(['#7b2fb0', '#34124f', true]);
  await page.click('#go-computer');
  await expect(page.locator('#reward-note')).toContainText('counts for rewards');
  await page.check('input[name=color][value=black]');
  await page.click('#start');
  await page.locator('#board .pt[data-r="7"][data-c="7"]').click();
  await expect(page.locator('#move-count')).toHaveText('2');
  await page.screenshot({ path: `${SHOTS}-alice-game-${info.project.name}.png` });
});

test('TC-E2E-072: on the profile, a new member can change the board but not the locked rewards', async ({ page, context, baseURL }, info) => {
  await loginAs(context, baseURL, freshUserId(), 'Newbie');
  await page.goto('/profile.html#gomoku');
  const card = page.locator('#gomoku');
  await expect(card.locator('.gk-rewards li')).toHaveCount(3);
  await expect(card.locator('.gk-rewards li.got')).toHaveCount(0);
  await expect(card.locator('input[name=stoneBlack]').first()).toBeDisabled();
  await expect(card.locator('input[name=stones3d]')).toBeDisabled();
  await card.locator('input[name=board][value=felt]').check({ force: true });
  await card.locator('button[type=submit]').click();
  await expect(card.locator('#gk-msg')).toHaveText('Saved ✓');
  await page.reload();
  await expect(page.locator('#gomoku input[name=board][value=felt]')).toBeChecked();
  if (info.project.name === 'chromium') await card.screenshot({ path: `${SHOTS}-profile-new.png` });
});

test('TC-E2E-073: the profile preview shows the unlocked look of a member who won everything', async ({ page, context, baseURL }, info) => {
  await loginAs(context, baseURL, USERS.alice, 'Alice');
  await page.goto('/profile.html#gomoku');
  const card = page.locator('#gomoku');
  await expect(card.locator('.gk-rewards li.got')).toHaveCount(3);
  await expect(card.locator('input[name=stoneBlack][value=purple]')).toBeChecked();
  await expect(card.locator('#gk-preview')).toHaveClass(/s3d/);
  if (info.project.name === 'chromium') await card.screenshot({ path: `${SHOTS}-profile-alice.png` });
});

test('TC-E2E-074: a member who won on Hard gets a crown on their avatar', async ({ page, context, baseURL }, info) => {
  await loginAs(context, baseURL, USERS.alice, 'Alice');
  await page.goto('/community.html');
  const av = page.locator('#auth-area img.auth-av');
  await expect(av).toHaveCount(1);
  await expect(page.locator('#auth-area .crown-wrap.crowned img.auth-av')).toHaveCount(1);
  if (info.project.name === 'chromium') await page.locator('nav').screenshot({ path: `${SHOTS}-crown.png` });
});

test('TC-E2E-075: a member without a Hard win gets no crown', async ({ page, context, baseURL }) => {
  await loginAs(context, baseURL, USERS.bob, 'Bob');
  await page.goto('/community.html');
  await expect(page.locator('#auth-area img.auth-av')).toHaveCount(1);
  await page.waitForLoadState('networkidle');
  await expect(page.locator('#auth-area .crowned')).toHaveCount(0);
});

test('TC-E2E-076: Gomoku has a login corner that comes back to the game page', async ({ page }) => {
  await page.goto('/games/gomoku.html');
  const login = page.locator('#auth-area a.auth-login');
  await expect(login).toHaveAttribute('href', /discord-auth\.php\?action=start&return=games%2Fgomoku\.html$/);
});

test('TC-E2E-077: logged in, the Gomoku login corner shows the avatar with the crown', async ({ page, context, baseURL }) => {
  await loginAs(context, baseURL, USERS.alice, 'Alice');
  await page.goto('/games/gomoku.html');
  await expect(page.locator('#auth-area .auth-name')).toHaveText('Alice');
  await expect(page.locator('#auth-area .crown-wrap.crowned img.auth-av')).toHaveCount(1);
  await expect(page.locator('#auth-area a.auth-out')).toHaveAttribute('href', /return=games%2Fgomoku\.html$/);
});

test('TC-E2E-078: HAKOO Games and HAKOO Community link to each other, and Games offers a login', async ({ page }) => {
  await page.goto('/hakoo-games.html');
  await expect(page.locator('#auth-area a.auth-login')).toHaveAttribute('href', /discord-auth\.php\?action=start&return=hakoo-games\.html$/);
  for (const p of ['/community.html', '/profile.html', '/roster.html']) {
    await page.goto(p);
    await expect(page.locator('nav a[href="hakoo-games.html"]')).toHaveCount(1);
  }
});

test('TC-E2E-079: logged in, HAKOO Games shows who you are, what is stored and your rewards', async ({ page, context, baseURL }) => {
  await loginAs(context, baseURL, USERS.alice, 'Alice');
  await page.goto('/index.html?choose=1');
  await page.evaluate(() => localStorage.setItem('hakoo_start', JSON.stringify({ page: 'games', saved: new Date().toISOString(), v: 1 })));
  await page.goto('/hakoo-games.html');
  await expect(page.locator('#b-who')).toHaveText('👤 Logged in as Alice');
  await expect(page.locator('#b-stored')).toHaveText('🍪 Saved: Discord login, start page');
  await expect(page.locator('#b-rewards')).toHaveText('🏆 Rewards: 33/33');        // 3 per game: Gomoku, Chess, Othello, Hnefatafl, Stratego, Backgammon, Checkers, Morris, Nonogram, Sudoku, Owldoku
  await expect(page.locator('#b-rewards')).toHaveAttribute('title', /Gomoku 3\/3 · Chess 3\/3 · Othello 3\/3 · Hnefatafl 3\/3 · Stratego 3\/3 · Backgammon 3\/3 · Checkers 3\/3 · Nine Men's Morris 3\/3 · Nonogram 3\/3 · Sudoku 3\/3 · Owldoku 3\/3/);
  await expect(page.locator('#hero-text')).toContainText('Welcome back, Alice');
  await expect(page.locator('#auth-area .crown-wrap.crowned img.auth-av')).toHaveCount(1);
});

test('TC-E2E-079b: screenshot of the logged-in HAKOO Games hub', async ({ page, context, baseURL }, info) => {
  test.skip(info.project.name !== 'chromium', 'one screenshot is enough');
  await loginAs(context, baseURL, USERS.alice, 'Alice');
  await page.goto('/hakoo-games.html');
  await expect(page.locator('#b-rewards')).toHaveText('🏆 Rewards: 33/33');
  await page.screenshot({ path: `${SHOTS}-hub-alice.png` });
});
