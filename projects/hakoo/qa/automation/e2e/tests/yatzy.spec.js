// Yatzy (games/yatzy.html, js/games/yatzy.js, yatzy.php): the card on the HAKOO Games hub, a turn in the
// browser (roll, keep dice, roll again, write a row), the member's last choices on another device, and the
// page on a phone in a long language. The rules themselves are tested on the server (YatzyEngineTest, YatzyApiTest).
// Traceability: FR-YATZY-09, FR-YATZY-12, FR-YATZY-14, FR-YATZY-15, FR-GAMES-20, FR-I18N-12.
const { test, expect } = require('@playwright/test');
const { loginAs, freshUserId, openNav, closeNav } = require('./helpers');

const option = (page, name, v) => page.locator(`input[name=${name}][value="${v}"]`);

/** Roll with the button (⚙ Settings), so a test does not have to shake the cup. */
async function rollWithButton(page) {
  await openNav(page);
  await page.click('#settings-open');
  await option(page, 'set-roll', 'button').check();
  await page.click('#settings-close');
  await closeNav(page);
}
const handValues = page => page.locator('#hand .yz-die').evaluateAll(els => els.map(b => +b.querySelector('.yz-face').dataset.v));

test('TC-E2E-254: Yatzy is on the Dice games shelf of HAKOO Games and opens its page', async ({ page }) => {
  await page.goto('/hakoo-games.html#dice');
  const card = page.locator('.game-card[data-key=yatzy]');
  await expect(card).toBeVisible();
  await expect(card).toHaveAttribute('data-cat', /\bdice\b/);
  await card.click();
  await expect(page).toHaveURL(/games\/yatzy\.html$/);
  await expect(page.locator('#v-home h1')).toContainText('Yatzy');
});

test('TC-E2E-255: a turn: roll, keep two dice, roll the others, write a row; then the computer plays', async ({ page }) => {
  await page.goto('/games/yatzy.html');
  await page.click('#go-computer');
  await option(page, 'players', '2').check();
  await expect(page.locator('#players-hint')).toHaveText('You and 1 computer player.');
  await page.click('#start');
  await expect(page.locator('#v-game')).toBeVisible();
  await rollWithButton(page);
  await expect(page.locator('#status')).toHaveText('Your turn: roll the dice.', { timeout: 30000 });   // the computer may start
  await expect(page.locator('#hand .yz-die:enabled')).toHaveCount(0);                                   // nothing to keep yet
  await page.click('#roll');
  await expect(page.locator('#status')).toHaveText(/^Roll 1 of 3/, { timeout: 15000 });
  const first = await handValues(page);
  expect(first.every(v => v >= 1 && v <= 6)).toBe(true);
  await page.locator('#hand .yz-die').nth(0).click();
  await page.locator('#hand .yz-die').nth(3).click();
  await expect(page.locator('#hand .yz-die.kept')).toHaveCount(2);
  await expect(page.locator('#roll')).toHaveText('🎲 Roll again (2 left)');
  await page.click('#roll');
  await expect(page.locator('#status')).toHaveText(/^Roll 2 of 3/, { timeout: 15000 });
  const second = await handValues(page);
  expect([second[0], second[3]]).toEqual([first[0], first[3]]);                                        // the kept dice stayed
  await expect(page.locator('#hand .yz-die.kept')).toHaveCount(2);
  // Write the dice in Chance (always their sum).
  const chance = page.locator('.yz-pick[data-c="13"]');
  await expect(chance).toHaveText(String(second.reduce((a, b) => a + b, 0)));
  await chance.click();
  await expect(page.locator('#sheet tbody tr').nth(15).locator('td').first()).toHaveText(String(second.reduce((a, b) => a + b, 0)));
  // The computer takes its turn and it comes back to us.
  await expect(page.locator('#status')).toHaveText('Your turn: roll the dice.', { timeout: 30000 });
  await expect(page.locator('#sheet .yz-pick')).toHaveCount(0);
  const theirs = await page.locator('#sheet tbody tr td:nth-child(3)').allInnerTexts();
  expect(theirs.filter(t => /^\d+$|^–$/.test(t.trim())).length).toBeGreaterThan(1);                   // a row (and its sums) written for the computer
});

test('TC-E2E-256: a member\'s last choices come back on another device; a visitor\'s are kept only after ticking Remember', async ({ browser, baseURL }) => {
  const id = freshUserId();
  const one = await browser.newContext();
  await loginAs(one, baseURL, id, 'Yatzyfan');
  const p1 = await one.newPage();
  await p1.goto('/games/yatzy.html');
  await p1.click('#go-computer');
  await expect(p1.locator('#remember-member')).toBeVisible();
  await p1.fill('#name', 'Yatzyfan');
  await option(p1, 'players', '5').check();
  await option(p1, 'difficulty', 'hard').check();
  await option(p1, 'order', 'forced').check();
  await p1.click('#start');
  await expect(p1.locator('#v-game')).toBeVisible();
  await one.close();

  const two = await browser.newContext();                                   // another device, the same member
  await loginAs(two, baseURL, id, 'Yatzyfan');
  const p2 = await two.newPage();
  const me = p2.waitForResponse(r => r.url().includes('yatzy.php?action=me'));
  await p2.goto('/games/yatzy.html');
  await me;
  await p2.click('#go-computer');
  await expect(p2.locator('#name')).toHaveValue('Yatzyfan');
  await expect(option(p2, 'players', '5')).toBeChecked();
  await expect(option(p2, 'difficulty', 'hard')).toBeChecked();
  await expect(option(p2, 'order', 'forced')).toBeChecked();
  expect(await p2.evaluate(() => localStorage.getItem('hakoo_yatzy_setup'))).toBeNull();   // on the server, not in the browser
  await two.close();

  const guest = await browser.newContext();
  const g = await guest.newPage();
  await g.goto('/games/yatzy.html');
  await g.click('#go-computer');
  await option(g, 'players', '3').check();
  await g.click('#start');
  await expect(g.locator('#v-game')).toBeVisible();
  expect(await g.evaluate(() => Object.keys(localStorage))).toEqual([]);  // playing stores nothing
  await g.goto('/games/yatzy.html');
  await g.click('#go-computer');
  await g.check('#remember');
  await option(g, 'players', '3').check();
  await g.click('#start');
  await expect(g.locator('#v-game')).toBeVisible();
  expect(JSON.parse(await g.evaluate(() => localStorage.getItem('hakoo_yatzy_setup'))).players).toBe(3);
  await guest.close();
});

test('TC-E2E-257: on a 375 px phone in German the game fits the screen, and the score sheet scrolls on its own', async ({ page }) => {
  await page.setViewportSize({ width: 375, height: 812 });
  await page.addInitScript(() => { try { localStorage.setItem('wot_lang', 'de'); } catch (e) {} });
  await page.goto('/games/yatzy.html');
  await page.click('#go-computer');
  await option(page, 'players', '6').check();
  await page.click('#start');
  await expect(page.locator('#v-game')).toBeVisible();
  await expect(page.locator('#sheet tbody th').first()).toHaveText(/Einser/, { timeout: 15000 });
  expect(await page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(375);
  const box = await page.locator('.yz-sheet-wrap').boundingBox();
  expect(box.x + box.width).toBeLessThanOrEqual(375);
});
