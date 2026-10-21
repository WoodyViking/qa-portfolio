// The profile's left menu (profile.html, js/profile.js showSection()) and the move speed there.
// Fixture: Alice has won on every level of every game; a fresh member has nothing.
// Traceability: FR-USER-05, FR-GAMES-11, US-30.
const { test, expect } = require('@playwright/test');
const { USERS, loginAs, freshUserId, openNav } = require('./helpers');

const SHOTS = '../../test-results/profile-menu';
const SECTIONS = ['overview', 'accounts', 'discord', 'games', 'my-games', 'data'];

test('TC-E2E-165: the profile shows one section at a time, chosen in the left menu; old links to a game or My games still work', async ({ page, context, baseURL }, info) => {
  await loginAs(context, baseURL, USERS.alice, 'Alice');
  await page.goto('/profile.html');
  const menu = page.locator('#pmenu');
  await expect(menu).toBeVisible();
  for (const id of SECTIONS) await expect(menu.locator(`a.pm-item[data-sec="${id}"]`)).toHaveCount(1);
  await expect(page.locator('#members')).toHaveCount(0);                          // the Discord member list is on Community
  // Start: the overview, with the tiles filled in
  await expect(page.locator('#sec-overview')).toBeVisible();
  await expect(page.locator('#sec-accounts')).toBeHidden();
  await expect(page.locator('#ov-rewards')).toHaveText('33/33 rewards');
  await expect(menu.locator('a[data-sec="overview"]')).toHaveAttribute('aria-current', 'page');
  // A click in the menu shows that section, and the address says which
  await menu.locator('a[data-sec="accounts"]').click();
  await expect(page).toHaveURL(/#accounts$/);
  await expect(page.locator('#sec-accounts')).toBeVisible();
  await expect(page.locator('#sec-overview')).toBeHidden();
  await expect(page.locator('#wf-card')).toBeVisible();
  // Board games: the games appear under it in the menu; a game has its own page
  await menu.locator('a[data-sec="games"]').click();
  await expect(page.locator('#sec-games')).toBeVisible();
  await expect(page.locator('#rw-othello')).toContainText('3/3 rewards');
  await expect(menu.locator('.pm-sub.open a.pm-item')).toHaveCount(11);
  await menu.locator('a[data-sec="othello"]').click();
  await expect(page.locator('#othello .gk-rewards li.got')).toHaveCount(3);
  await expect(page.locator('#sec-games')).toBeHidden();
  // The puzzles have look cards too, with a preview of their own board
  for (const [g, cls] of [['sudoku', 'sboard'], ['owldoku', 'oboard-owl']]) {
    await menu.locator(`a[data-sec="${g}"]`).click();
    await expect(page.locator(`#${g} .gk-rewards li.got`)).toHaveCount(3);
    await expect(page.locator(`#${g} .gk-preview.${cls} button`)).not.toHaveCount(0);
  }
  // Links from the game pages
  await page.goto('/profile.html#chess');
  await expect(page.locator('#chess')).toBeVisible();
  await expect(menu.locator('a[data-sec="chess"]')).toHaveAttribute('aria-current', 'page');
  await page.goto('/profile.html#my-games');
  await expect(page.locator('#my-games')).toBeVisible();
  await page.goto('/profile.html#no-such-section');                                // unknown: the overview
  await expect(page.locator('#sec-overview')).toBeVisible();
  if (info.project.name !== 'firefox') {
    await page.goto('/profile.html#games');
    await expect(page.locator('#rw-othello')).toContainText('3/3');
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true);   // no sideways scrolling
    await page.screenshot({ path: `${SHOTS}-games-${info.project.name}.png` });
  }
});

test('TC-E2E-166: logged out, the profile asks for a login and shows no menu', async ({ page }) => {
  await page.goto('/profile.html#games');
  await expect(page.locator('#profile-login')).toBeVisible();
  await expect(page.locator('#pmenu')).toBeHidden();
});

test('TC-E2E-167: the move speed changed on the profile is the one ⚙ shows on a game page', async ({ page, context, baseURL }) => {
  await loginAs(context, baseURL, freshUserId(), 'Profiler');
  await page.goto('/profile.html#games');
  const speed = page.locator('#pf-speed');
  await expect(speed).toBeEnabled();
  await expect(speed).toHaveValue('600');
  await expect(page.locator('#pf-speed-msg')).toContainText('default');
  await speed.fill('1800');
  await speed.dispatchEvent('change');
  await expect(page.locator('#pf-speed-out')).toHaveText('1.8 s');
  await expect(page.locator('#pf-speed-msg')).toHaveText('Saved ✓');
  await page.goto('/games/hnefatafl.html');
  await openNav(page);
  await page.click('#settings-open');
  await expect(page.locator('#move-speed')).toHaveValue('1800');
  await expect(page.locator('#settings-more a[href="../profile.html#games"]')).toHaveCount(1);
  await page.reload();
  await page.goto('/profile.html#games');
  await expect(page.locator('#pf-speed')).toHaveValue('1800');
});

test('TC-E2E-231: every section of the profile explains what can be set there and how, folded away until opened', async ({ page, context, baseURL }) => {
  await loginAs(context, baseURL, USERS.alice, 'Alice');
  for (const id of ['accounts', 'discord', 'appearance', 'games', 'gomoku', 'runborg', 'my-games', 'viking', 'public', 'data']) {
    await page.goto(`/profile.html#${id}`);
    const how = page.locator(`#sec-${id} details.howto`).first();
    await expect(how, id).toBeVisible();
    await expect(how.locator('summary')).toHaveText(/How it works/);
    await expect(how.locator('.howto-body')).toBeHidden();                          // folded until opened
    await how.locator('summary').click();
    await expect(how.locator('.howto-body')).toBeVisible();
    expect((await how.locator('.howto-body').innerText()).length, id).toBeGreaterThan(80);
  }
});
