// Start page chooser (src/index.html + src/js/index.js).
// Test design: DECISION TABLE (remember x choice) and STATE TRANSITION
// (no choice saved <-> choice saved). See qa/test-design/decision-tables.md
// and qa/test-design/state-transition.md. Traceability: US-01, US-02, FR-START-*.
const { test, expect } = require('@playwright/test');

const saved = page => page.evaluate(() => localStorage.getItem('hakoo_start'));

// Stop the click from navigating, so the test can inspect the chooser page afterwards.
async function clickWithoutLeaving(page, choice) {
  await page.evaluate(c => document.querySelector(`[data-choice=${c}]`)
    .addEventListener('click', e => e.preventDefault()), choice);
  await page.click(`[data-choice=${choice}]`);
}

test.beforeEach(async ({ page }) => {
  await page.goto('/index.html?choose=1');
  await page.evaluate(() => localStorage.clear());
  await page.goto('/index.html');
});

test.describe('Decision table: Remember my choice x picked page', () => {
  const rules = [
    { id: 'R1 (TC-E2E-001)', remember: false, choice: 'community', expected: null },
    { id: 'R2 (TC-E2E-002)', remember: false, choice: 'games',     expected: null },
    { id: 'R3 (TC-E2E-003)', remember: true,  choice: 'community', expected: 'community' },
    { id: 'R4 (TC-E2E-004)', remember: true,  choice: 'games',     expected: 'games' },
  ];
  for (const r of rules) {
    test(`${r.id}: remember=${r.remember}, choice=${r.choice}`, async ({ page }) => {
      if (r.remember) await page.click('#remember');
      await clickWithoutLeaving(page, r.choice);
      const value = await saved(page);
      if (r.expected === null) expect(value).toBeNull();
      else expect(JSON.parse(value).page).toBe(r.expected);
    });
  }
});

test.describe('State transitions', () => {
  test('TC-E2E-005: a saved choice redirects straight to that page', async ({ page }) => {
    await page.click('#remember');
    await page.click('[data-choice=games]');
    await expect(page).toHaveURL(/hakoo-games\.html$/);
    await page.goto('/index.html');
    await expect(page).toHaveURL(/hakoo-games\.html$/);
  });

  test('TC-E2E-006: ?choose=1 always shows the chooser and the saved choice', async ({ page }) => {
    await page.click('#remember');
    await page.click('[data-choice=community]');
    await page.goto('/index.html?choose=1');
    await expect(page.locator('.choices')).toBeVisible();
    await expect(page.locator('#saved-text')).toHaveText(/HAKOO Community/);
  });

  test('TC-E2E-007: "Forget my choice" deletes the saved choice', async ({ page }) => {
    await page.click('#remember');
    await clickWithoutLeaving(page, 'games');
    await page.goto('/index.html?choose=1');
    await page.click('#forget');
    expect(await saved(page)).toBeNull();
    await expect(page.locator('#remember')).toHaveAttribute('aria-pressed', 'false');
    await expect(page.locator('#saved-note')).toBeHidden();
  });

  test('TC-E2E-008: turning Remember off again before choosing stores nothing', async ({ page }) => {
    await page.click('#remember');
    await page.click('#remember');
    await clickWithoutLeaving(page, 'community');
    expect(await saved(page)).toBeNull();
  });
});

test('TC-E2E-009: "Forget my choice" is hidden when nothing is saved (DEF-001 regression)', async ({ page }) => {
  await expect(page.locator('#saved-note')).toBeHidden();
});

test('TC-E2E-010: the Remember button reports its state to assistive technology', async ({ page }) => {
  const btn = page.locator('#remember');
  await expect(btn).toHaveAttribute('aria-pressed', 'false');
  await btn.click();
  await expect(btn).toHaveAttribute('aria-pressed', 'true');
});

test('TC-E2E-011: all three choices can be reached with the keyboard', async ({ page, browserName }) => {
  test.skip(browserName === 'webkit', 'Safari does not tab to links by default');
  const reached = [];
  for (let i = 0; i < 6; i++) {
    await page.keyboard.press('Tab');
    reached.push(await page.evaluate(() => document.activeElement.dataset.choice || document.activeElement.id));
  }
  expect(reached).toEqual(expect.arrayContaining(['community', 'games', 'remember']));
});
