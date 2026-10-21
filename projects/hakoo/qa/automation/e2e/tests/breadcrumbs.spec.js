// The way back (js/shared/breadcrumbs.js), in the menu bar: a back arrow to the left of the logo and, after the logo,
// the pages below the one it stands for down to this one. On a phone only the arrow and the logo.
// Traceability: FR-NAV-01..03.
const { test, expect } = require('@playwright/test');

// page, the steps after the logo, where the arrow goes
const TRAILS = [
  ['/hakoo-games.html', [], /\/index\.html\?choose=1$/],
  ['/games/gomoku.html', ['Gomoku'], /\/hakoo-games\.html$/],
  ['/games/chess-rules.html', ['Chess', 'Rules'], /\/games\/chess\.html$/],
  ['/games/warframe-builder.html', ['Warframe', 'Builder'], /\/games\/warframe\.html$/],
  ['/help.html', [], /\/community\.html$/],                      // the logo says "HAKOO Help" already
  ['/profile.html', [], /\/index\.html\?choose=1$/],
  ['/wot-stats.html', ['WoT clan stats'], null],                 // its own arrow (back in the history) stays
];

test('TC-E2E-221: in the menu bar, the arrow left of the logo goes one step up, and the steps after the logo show where the page is; the start page has neither', async ({ page }, info) => {
  const phone = info.project.name === 'mobile';
  for (const [url, steps, up] of TRAILS) {
    await page.goto(url);
    const nav = page.locator('nav');
    if (up) await expect(nav.locator('.crumbs-back'), url).toHaveAttribute('href', up);
    else await expect(nav.locator('.crumbs-back'), url).toHaveCount(0);
    const crumbs = nav.locator('ol.crumbs');
    await expect(crumbs, url).toHaveCount(steps.length ? 1 : 0);
    if (!steps.length) continue;
    expect(await crumbs.locator('li').evaluateAll(li => li.map(x => x.textContent.trim())), url).toEqual(steps);
    await expect(crumbs.locator('[aria-current="page"]'), url).toHaveText(steps[steps.length - 1]);
    await expect(crumbs.locator('a'), url).toHaveCount(steps.length - 1);
    if (phone) await expect(crumbs, url).toBeHidden();                                // a phone: the arrow and the logo only
    else await expect(crumbs, url).toBeVisible();
  }
  await page.goto('/index.html');
  await expect(page.locator('.crumbs-back, ol.crumbs')).toHaveCount(0);
});

test('TC-E2E-222: the back arrow goes one step up each time, and a step in the trail goes there', async ({ page }, info) => {
  await page.goto('/games/chess-rules.html');
  await expect(page.locator('.crumbs-back')).toHaveAttribute('aria-label', 'Back to Chess');
  await page.locator('.crumbs-back').click();
  await expect(page).toHaveURL(/\/games\/chess\.html$/);
  await page.locator('.crumbs-back').click();
  await expect(page).toHaveURL(/\/hakoo-games\.html$/);
  if (info.project.name === 'mobile') return;                                       // no trail on a phone
  await page.goto('/games/chess-rules.html');
  await page.locator('ol.crumbs a', { hasText: 'Chess' }).click();
  await expect(page).toHaveURL(/\/games\/chess\.html$/);
});
