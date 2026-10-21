// Reminders for the puzzles of the day: profile → Reminders (js/profile.js loadReminders(), reminders.php).
// Traceability: FR-REMIND-01, FR-REMIND-02, FR-REMIND-07, FR-REMIND-08.
const { test, expect } = require('@playwright/test');
const { loginAs, freshUserId } = require('./helpers');

const SHOTS = '../../test-results/reminders';

test('TC-E2E-233: Reminders sits between My games and Your viking; a direct message needs the consent box; the choices are saved', async ({ page, context, baseURL }, info) => {
  test.skip(info.project.name === 'firefox', 'saves a member: two browsers are enough');
  await loginAs(context, baseURL, freshUserId().replace(/403$/, '402'), 'Remy');   // (the stub refuses DMs to ids ending in 403)
  await page.goto('/profile.html#reminders');
  const order = await page.locator('#pmenu a.pm-item').evaluateAll(as => as.map(a => a.dataset.sec));
  expect(order.slice(order.indexOf('my-games'), order.indexOf('my-games') + 3)).toEqual(['my-games', 'reminders', 'viking']);
  const card = page.locator('#reminders');
  await expect(card.locator('input[name="rm-mode"][value="off"]')).toBeChecked();
  await expect(card.locator('#rm-consent-row')).toBeHidden();                   // the ping: no consent needed

  await card.locator('input[name="rm-mode"][value="each"]').check();
  await card.locator('[data-game="sudoku"]').check();
  await card.locator('[data-time="sudoku"]').fill('07:30');
  await card.locator('input[name="rm-via"][value="dm"]').check();
  await expect(card.locator('#rm-consent-row')).toBeVisible();
  await expect(card.locator('#rm-consent-row')).toContainText('I agree that the HAKOO bot sends me direct messages');
  await card.locator('#rm-save').click();
  await expect(card.locator('#rm-msg')).toHaveText('Tick that you agree to direct messages, or choose the ping.');
  await card.locator('#rm-consent').check();
  await expect(card.locator('input[name="rm-always"][value="no"]')).toBeChecked();   // only with a puzzle left, by default
  await card.locator('input[name="rm-always"][value="yes"]').check();
  if (info.project.name === 'chromium') await card.screenshot({ path: `${SHOTS}-dm.png` });
  await card.locator('#rm-save').click();
  await expect(card.locator('#rm-msg')).toHaveText('✅ Saved.');

  await page.reload();
  await expect(card.locator('input[name="rm-mode"][value="each"]')).toBeChecked();
  await expect(card.locator('[data-game="sudoku"]')).toBeChecked();
  await expect(card.locator('[data-time="sudoku"]')).toHaveValue('07:30');
  await expect(card.locator('[data-game="nonogram"]')).not.toBeChecked();
  await expect(card.locator('#rm-consent')).toBeChecked();
  await expect(card.locator('input[name="rm-always"][value="yes"]')).toBeChecked();
  await card.locator('#rm-test').click();
  await expect(card.locator('#rm-msg')).toHaveText('✅ Sent: look in your direct messages on Discord.');

  await card.locator('input[name="rm-mode"][value="off"]').check();
  await card.locator('#rm-save').click();
  await expect(card.locator('#rm-msg')).toHaveText('✅ Saved: no reminders.');
});

test('TC-E2E-234: the Reminders section fits the window at every text size', async ({ page, context, baseURL }, info) => {
  test.skip(info.project.name !== 'chromium', 'sets its own window sizes');
  await loginAs(context, baseURL, freshUserId(), 'Remy');
  for (const size of ['normal', 'xl']) {
    await page.addInitScript(s => localStorage.setItem('wf_textsize', s), size);
    for (const [width, height] of [[390, 844], [1366, 768]]) {
      await page.setViewportSize({ width, height });
      await page.goto('/profile.html#reminders');
      const card = page.locator('#reminders');
      await card.locator('input[name="rm-mode"][value="each"]').check();
      await card.locator('input[name="rm-via"][value="dm"]').check();
      expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), `${size} ${width}: no sideways scroll`).toBe(true);
      await card.screenshot({ path: `${SHOTS}-${size}-${width}.png` });
    }
  }
});
