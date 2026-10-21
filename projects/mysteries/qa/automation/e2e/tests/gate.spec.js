// The hidden test phase in real browsers (US-13, K-41, AK-26).
const { test, expect } = require('@playwright/test');
const { USERS, sessionCookie } = require('./helpers');

// TC-E2E-001 (K-41)
test('a tester sees the page', async ({ page, context, baseURL }) => {
  await context.addCookies([sessionCookie(USERS.bob, baseURL)]);
  await page.goto('/mysteries/');
  await expect(page.getByText('Welcome, Bob.')).toBeVisible();
});

// TC-E2E-002 (AK-26)
test('a member without the tester role is denied', async ({ page, context, baseURL }) => {
  await context.addCookies([sessionCookie(USERS.carol, baseURL)]);
  const response = await page.goto('/mysteries/');
  expect(response.status()).toBe(403);
  await expect(page.getByText('only for testers')).toBeVisible();
});
