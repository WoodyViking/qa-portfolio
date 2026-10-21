// The language menu on every page, and the texts of the start page, HAKOO Games and the
// board-game pages from the database (pages "index", "hakoo-games" and "common").
// Traceability: US-09, FR-I18N-07, FR-I18N-10..12, FR-START-02, FR-GAMES-02, REG-EPR-01.
// The text inside the games (board messages, setup forms, rules) is not translated yet, so
// these tests only look at the parts that are: menu bar, start buttons, notes and badges.
const { test, expect } = require('@playwright/test');
const { USERS, loginAs, openNav, closeNav } = require('./helpers');

const PAGES = [
  '/index.html?choose=1', '/community.html', '/hakoo-games.html', '/roster.html', '/profile.html',
  '/this-week.html', '/hakoo-loot.html', '/help.html', '/privacy.html', '/terms.html', '/install.html',
  '/wot-stats.html', '/WoWs-tracker.html', '/texts.html',
  '/games/warframe.html', '/games/warframe-builder.html', '/games/warframe-counter.html',
  '/games/eight-claw.html', '/games/tennocon-2026.html', '/games/gomoku.html',
  '/games/chess.html', '/games/othello.html', '/games/hnefatafl.html', '/games/stratego.html', '/games/checkers.html', '/games/morris.html', '/games/nonogram.html', '/games/sudoku.html', '/games/owldoku.html',
  '/games/blockdoku.html', '/games/blockdoku-rules.html', '/games/path-of-berserk.html', '/games/path-of-berserk-rules.html', '/games/yatzy.html',
  ...['gomoku', 'chess', 'othello', 'hnefatafl', 'stratego', 'backgammon', 'checkers', 'morris', 'ludo', 'yatzy', 'nonogram', 'sudoku', 'owldoku'].map(g => `/games/${g}-rules.html`),
  '/wot-tracker.html',   // redirects to the profile when no account is linked; the profile has the menu too
];
const GAMES = ['gomoku', 'chess', 'othello', 'hnefatafl', 'stratego', 'checkers', 'morris'];

const storageKeys = page => page.evaluate(() => Object.keys(localStorage));
async function pickLanguage(page, lang) {
  await openNav(page, 'corner');                                  // on a phone the language is in 👤▾
  await page.locator('.lang-current').click();
  await page.locator(`.lang-pop .lang-flag[data-lang=${lang}]`).click();
  await closeNav(page);
}

test.beforeEach(async ({ page, context }) => {
  await context.clearCookies();
  await page.goto('/index.html?choose=1');
  await page.evaluate(() => localStorage.clear());
});

for (const path of PAGES) {
  test(`TC-E2E-149: ${path} has the language menu, showing one flag`, async ({ page }) => {
    await page.goto(path);
    const menu = page.locator('.lang-row.lang-menu');
    await expect(menu).toHaveCount(1);
    if (/games/.test(path)) await openNav(page, 'corner');         // HAKOO Games on a phone: in 👤▾
    await expect(menu.locator('.lang-current')).toBeVisible();
    await expect(menu.locator('.lang-current img')).toHaveAttribute('src', /iconer\/flags\/gb\.png$/);
    await expect(menu.locator('.lang-flag:visible')).toHaveCount(0);
  });
}

test('TC-E2E-150: the start page stores nothing until a language is picked, then speaks it', async ({ page }) => {
  await page.goto('/index.html');
  await expect(page.locator('.start-head p')).toHaveText('Where do you want to go?');
  await page.waitForLoadState('networkidle');
  expect(await storageKeys(page)).toEqual([]);

  await pickLanguage(page, 'sv');
  await expect(page.locator('.start-head p')).toHaveText('Vart vill du gå?');
  await expect(page.locator('#remember-text')).toContainText('Av. Vi sparar ingenting');
  await expect(page.locator('.lang-current img')).toHaveAttribute('src', /iconer\/flags\/se\.png$/);
  await expect(page.locator('html')).toHaveAttribute('lang', 'sv');
  expect(await page.evaluate(() => localStorage.getItem('wot_lang'))).toBe('sv');
  expect(await page.evaluate(() => localStorage.getItem('hakoo_start'))).toBeNull();   // the language is not a start page

  // The state texts of "Remember my choice" follow the language too, and the choice follows the visitor.
  await page.click('#remember');
  await expect(page.locator('#remember-text')).toContainText('På. Välj HAKOO Community');
  await page.reload();
  await expect(page.locator('.start-head p')).toHaveText('Vart vill du gå?');
  await pickLanguage(page, 'en');
  await expect(page.locator('.start-head p')).toHaveText('Where do you want to go?');
});

test('TC-E2E-151: HAKOO Games shows its texts and badges in the picked language, from hakoo.eu only', async ({ page, baseURL }) => {
  const requests = [];
  page.on('request', r => requests.push(r.url()));
  await page.goto('/hakoo-games.html');
  await expect(page.locator('#b-who')).toHaveText('🕶 Anonymous');
  await expect(page.locator('#b-stored')).toHaveText('🍪 Nothing stored');
  expect(await storageKeys(page)).toEqual([]);

  await pickLanguage(page, 'sv');
  await expect(page.locator('#b-who')).toHaveText('🕶 Anonym');
  await expect(page.locator('#b-stored')).toHaveText('🍪 Sparat: språk');            // it lists what the visitor chose
  await expect(page.locator('#b-rewards')).toHaveText('🔓 Ingen inloggning krävs');
  await expect(page.locator('#hero-text')).toContainText('Spela direkt i webbläsaren');
  await expect(page.locator('nav a[href^="index.html"]')).toHaveText('⇄ Startsida');
  await expect(page.locator('#game-grid .game-card').first().locator('p')).toContainText('Fem i rad');
  await expect(page.locator('footer a[href^="terms"]')).toHaveText('Villkor');
  const origin = new URL(baseURL).origin;
  expect(requests.filter(u => !u.startsWith(origin))).toEqual([]);
});

test('TC-E2E-152: logged in, the HAKOO Games badges and welcome follow the language', async ({ page, context, baseURL }) => {
  await loginAs(context, baseURL, USERS.alice, 'Alice');
  await page.goto('/hakoo-games.html');
  await expect(page.locator('#b-rewards')).toHaveText('🏆 Rewards: 33/33');
  await pickLanguage(page, 'de');
  await expect(page.locator('#b-who')).toHaveText('👤 Angemeldet als Alice');
  await expect(page.locator('#b-stored')).toHaveText('🍪 Gespeichert: Discord-Login, Sprache');
  await expect(page.locator('#b-rewards')).toHaveText('🏆 Belohnungen: 33/33');
  await expect(page.locator('#b-rewards')).toHaveAttribute('title', /Gomoku 3\/3 .* — Leicht: Farben · Mittel: 3D · Schwer: eine Krone$/);
  await expect(page.locator('#hero-text')).toContainText('Willkommen zurück, Alice!');
});

for (const game of GAMES) {
  test(`TC-E2E-153: the ${game} page speaks the picked language in its menu bar and start buttons`, async ({ page }) => {
    await page.goto(`/games/${game}.html`);
    await expect(page.locator('#go-computer b')).toHaveText('Play vs Computer');
    expect(await storageKeys(page)).toEqual([]);

    await pickLanguage(page, 'sv');
    await expect(page.locator('#go-computer b')).toHaveText('Spela mot datorn');
    await expect(page.locator('#go-computer small')).toHaveText('Lätt, Medel eller Svår');
    await expect(page.locator('#go-friend b')).toHaveText('Spela med en vän');
    await expect(page.locator('#go-rules b')).toHaveText('Spelregler');
    await expect(page.locator('#nav-rules')).toHaveText('📖 Spelregler');
    await expect(page.locator('nav a[data-i18n=nav_all_games]')).toHaveText('🎮 Alla spel');
    await expect(page.locator('#v-home .note')).toContainText('Inget konto behövs');
    await expect(page.locator('#rules-aside-title')).toHaveText('📖 Reglerna i korthet');      // the rules box (FR-GAMES-16)
    await expect(page.locator('.rules-aside a.more')).toHaveText('Alla regler, med bilder →');

    // Back to English, and the game itself still works.
    await pickLanguage(page, 'en');
    await expect(page.locator('#go-computer b')).toHaveText('Play vs Computer');
    await page.click('#go-computer');
    await expect(page.locator('#v-setup')).toBeVisible();
  });
}

test('TC-E2E-154: an unknown language in storage falls back to English texts', async ({ page }) => {
  await page.evaluate(() => localStorage.setItem('wot_lang', 'xx'));
  await page.goto('/hakoo-games.html');
  await expect(page.locator('#b-who')).toHaveText('🕶 Anonymous');
  await expect(page.locator('nav a[href^="index.html"]')).toHaveText('⇄ Start page');
});
