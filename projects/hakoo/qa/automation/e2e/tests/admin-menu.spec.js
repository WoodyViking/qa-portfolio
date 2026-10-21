// The admins' right-click menu on a member (js/shared/admin-menu.js), in the players list and on the members' page:
// roles (Admin only for the owner) and the rank (tier). Fixture: Alice is the owner; Bob and Carol are members.
// Traceability: FR-ADMIN-09, FR-RANK-01.
const { test, expect } = require('@playwright/test');
const { loginAs, freshUserId } = require('./helpers');

test.beforeEach(async ({}, info) => {
  test.skip(info.project.name !== 'chromium', 'one browser: Bob\'s rank is shared by the browsers running at the same time');
});

test('TC-E2E-226: the owner right-clicks Bob in the players list and makes him a Jarl, then a free member again; a member gets no menu', async ({ page, context, baseURL, browser }) => {
  await loginAs(context, baseURL, '100000000000000001', 'Alice');
  await page.request.post('/admin.php?action=tier', { data: { discord_id: '100000000000000002', tier: 'free' } });   // as at the start
  await page.goto('/hakoo-games.html');
  const bob = page.locator('#pl-items .pl-item', { hasText: 'Bob' });
  await bob.click({ button: 'right' });
  const menu = page.locator('.adm-menu');
  await expect(menu).toBeVisible();
  await expect(menu.locator('h4')).toHaveText('Bob');
  await expect(menu.locator('[data-tier="free"]')).toHaveAttribute('aria-checked', 'true');
  await expect(menu.locator('[data-role="admin"]')).toBeEnabled();                    // the owner gives Admin too
  await menu.locator('[data-tier="vip"]').click();
  await expect(menu.locator('.adm-msg')).toHaveText('Rank saved ✓');
  await expect(page.locator('#pl-items .pl-group', { hasText: /^Jarls — \d+$/ })).toHaveCount(1);
  await expect(bob.locator('.pl-tag.rank')).toHaveText('Jarl');
  await page.keyboard.press('Escape');
  await expect(menu).toHaveCount(0);

  await page.goto('/members.html');                                                  // the same menu on the members' page
  await page.locator('#people .person', { hasText: 'Bob' }).click({ button: 'right' });
  await page.locator('.adm-menu [data-tier="free"]').click();
  await expect(page.locator('.adm-menu .adm-msg')).toHaveText('Rank saved ✓');
  await expect(page.locator('#people .person', { hasText: 'Bob' }).locator('.rank-tag')).toHaveCount(0);   // free: no rank by his name

  const other = await browser.newContext();
  await loginAs(other, baseURL, freshUserId(), 'Torunn');
  const po = await other.newPage();
  await po.goto(`${baseURL}/hakoo-games.html`);
  await po.locator('#pl-items .pl-item', { hasText: 'Bob' }).click({ button: 'right' });
  await expect(po.locator('.adm-menu')).toHaveCount(0);
  await other.close();
});
