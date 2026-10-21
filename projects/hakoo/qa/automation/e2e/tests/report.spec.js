// "🐞 Report a problem" (js/shared/report.js, ../report.php): the button by the login for members, the
// form, and the bot's post in #reports-from-hakoo-eu (the Discord stub in wgmock records it).
// Traceability: FR-REPORT-01..03, US-39.
const { test, expect } = require('@playwright/test');
const { loginAs, freshUserId, openNav } = require('./helpers');

const SHOTS = '../../test-results/report';
const MOCK = 'http://wgmock:8000/discord';

test('TC-E2E-210: a member reports a problem from a game page: the form says what is added, the bot posts it; logged out there is no button', async ({ page, context, baseURL, request }, info) => {
  await page.goto('/games/chess.html');
  await expect(page.locator('#auth-area .auth-login')).toHaveCount(1);
  await expect(page.locator('.report-btn')).toHaveCount(0);          // not logged in: no button

  const id = freshUserId();
  await loginAs(context, baseURL, id, 'Report Tester');
  await page.goto('/games/chess.html?from=report#g=secret-token');      // a new address: the page loads again, logged in
  await openNav(page, 'corner');                                     // on a phone it is in 👤▾, with the login
  await page.click('.report-btn');
  const d = page.locator('#report-dlg');
  await expect(d).toBeVisible();
  await expect(d.locator('.rp-about')).toContainText('/games/chess.html?from=report');
  await expect(d.locator('.rp-about')).not.toContainText('secret-token');   // the address after '#' (game tokens) is never sent
  await d.locator('.rp-send').click();
  await expect(d.locator('.rp-msg')).toContainText('a few words');   // nothing written yet
  await d.locator('input[name=kind][value=looks]').check();
  await d.locator('textarea[name=what]').fill('The board is too small on my phone');
  await d.locator('textarea[name=steps]').fill('Opened chess, played e4');
  await page.screenshot({ path: `${SHOTS}-form-${info.project.name}.png` });
  await d.locator('.rp-send').click();
  await expect(d.locator('.rp-msg')).toContainText('Thank you');
  await expect(d).toBeHidden({ timeout: 5000 });

  const log = await (await request.get(`${MOCK}/_log`)).json();
  const post = log.filter(c => c.method === 'POST' && /\/channels\/900000000000000003\/messages$/.test(c.path) && c.body.content === `Report from <@${id}>`);
  expect(post).toHaveLength(1);
  const e = post[0].body.embeds[0];
  expect([e.title, e.description, e.footer.text]).toEqual(['🎨 Something looks wrong', 'The board is too small on my phone', 'Report Tester · from hakoo.eu']);
  expect(e.fields.map(f => f.name)).toEqual(['What they did just before', 'Page', 'Browser and screen']);
  expect(e.fields[1].value).toMatch(/\/games\/chess\.html\?from=report$/);
  expect(await page.evaluate(() => [localStorage.length, sessionStorage.length])).toEqual([0, 0]);
});

test('TC-E2E-211: the button and the form speak the language the member picked (German, then Swedish), with the English as the fallback', async ({ page, context, baseURL }) => {
  await loginAs(context, baseURL, freshUserId(), 'Sprach Tester');
  await page.addInitScript(() => { if (!sessionStorage.getItem('lang-set')) { localStorage.setItem('wot_lang', 'de'); sessionStorage.setItem('lang-set', '1'); } });
  await page.goto('/games/chess.html?from=report-de');
  await openNav(page, 'corner');
  await expect(page.locator('.report-btn')).toHaveText('🐞 Ein Problem melden');
  await page.click('.report-btn');
  const d = page.locator('#report-dlg');
  await expect(d.locator('h2')).toHaveText('🐞 Ein Problem melden');
  await expect(d.locator('.rp-send')).toHaveText('Meldung senden');
  await expect(d.locator('.rp-about')).toContainText('Browser und Bildschirm:');
  await d.locator('.rp-send').click();
  await expect(d.locator('.rp-msg')).toHaveText('Schreib ein paar Worte dazu, was passiert ist.');
  await d.locator('.rp-cancel').click();
  // Swedish picked later: the next time the form opens it is in Swedish.
  await page.evaluate(() => localStorage.setItem('wot_lang', 'sv'));
  await openNav(page, 'corner');                                     // a phone closed 👤▾ when the form opened
  await page.click('.report-btn');
  await expect(d.locator('.rp-send')).toHaveText('Skicka rapport');
  await expect(d.locator('textarea[name=what]')).toHaveAttribute('placeholder', 'T.ex. tärningarna rullade inte efter datorns tur');
});
