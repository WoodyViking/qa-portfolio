// Test mode in real browsers: the host plays a test player in a tab of its own, and a member without the tester role
// comes in with the invitation link. Traceability: US-26, K-121, K-122, K-123, K-12, K-13, AK-94, AK-95.
const { test, expect } = require('@playwright/test');
const { USERS, device } = require('./helpers');

test('TC-E2E-029: a test player opens in a new tab with cards of its own; the invitation link lets Carol ask to join; without a login it leads to Discord', async ({ browser, baseURL }) => {
  const host = await device(browser, baseURL, USERS.bob);
  await host.page.goto('/mysteries/');
  await host.page.getByRole('button', { name: 'Start a new game' }).click();
  await expect(host.page).toHaveURL(/play\.html\?code=[A-Z2-9]{8}$/);
  const code = new URL(host.page.url()).searchParams.get('code');

  await expect(host.page.getByRole('heading', { name: 'Test mode' })).toBeVisible();
  const link = await host.page.getByLabel('Invitation link').inputValue();
  expect(link).toMatch(new RegExp(`/mysteries/play\\.html\\?code=${code}&invite=[0-9a-f]{32}$`));
  await host.page.getByRole('button', { name: 'Copy' }).click();
  await expect(host.page.locator('#msg')).toHaveText(/The link is copied\.|The link is selected/);

  const [tab] = await Promise.all([host.ctx.waitForEvent('page'),                                 // K-121
    host.page.getByRole('button', { name: 'Add a test player' }).click()]);
  await expect(tab.locator('p.state')).toContainText('Test player 2', { timeout: 10_000 });
  expect(tab.url()).not.toContain('#t=');                                                        // the token is gone from the address
  await expect(tab.locator('p.state')).not.toContainText('Host');
  await expect(tab.getByRole('button', { name: 'Start the game' })).toHaveCount(0);
  await expect(tab.getByRole('heading', { name: 'Test mode' })).toHaveCount(0);
  await expect(host.page.locator('ul.names')).toContainText('Bob (2)', { timeout: 10_000 });

  const carol = await device(browser, baseURL, USERS.carol, { width: 390, height: 844 });       // no tester role
  await carol.page.goto(`/mysteries/play.html?code=${code}`);
  await expect(carol.page.getByText('Only testers can play during the test phase.')).toBeVisible();
  const carol2 = await device(browser, baseURL, USERS.carol, { width: 390, height: 844 });
  await carol2.page.goto(link);                                                                  // AK-95
  await expect(carol2.page.getByText('Waiting for the host to let you in')).toBeVisible({ timeout: 10_000 });
  await host.page.locator('ul.waiting li', { hasText: 'Carol' }).getByRole('button', { name: 'Let in' }).click({ timeout: 10_000 });
  await expect(carol2.page.locator('ul.names')).toContainText('Bob (2)', { timeout: 10_000 });

  const guest = await device(browser, baseURL, null);                                           // not logged in
  await guest.page.goto(link);
  const login = guest.page.getByRole('link', { name: 'Log in with Discord' });
  await expect(login).toBeVisible();
  const back = new URL(await login.getAttribute('href'), baseURL).searchParams.get('return');
  expect(back).toBe(`mysteries/play.html?code=${code}&invite=${new URL(link).searchParams.get('invite')}`);

  await host.page.getByRole('button', { name: 'Start the game' }).click();                      // AK-94
  await expect(tab.locator('.card')).toHaveCount(2, { timeout: 10_000 });
  await expect(host.page.locator('.card')).toHaveCount(2);
  await expect(host.page.getByRole('heading', { name: 'Test mode' })).toHaveCount(0);
  for (const c of await tab.locator('.card span').allInnerTexts()) await expect(host.page.locator('ul.cards')).not.toContainText(c);
  const first = (await tab.locator('.card span').allInnerTexts())[0];
  await tab.locator('.card', { hasText: first }).getByRole('button', { name: 'Show to all' }).click();
  await expect(host.page.locator('ul.shown')).toContainText('Bob (2) showed', { timeout: 5_000 });
  await Promise.all([host.ctx.close(), carol.ctx.close(), carol2.ctx.close(), guest.ctx.close()]);
});
