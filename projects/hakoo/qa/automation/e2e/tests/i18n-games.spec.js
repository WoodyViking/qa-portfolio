// The HAKOO Games pages and their rules pages speak the picked language (js/shared/i18n.js, "By their English";
// the texts: database/texts/games/*.json -> database/seed/hakoo_texts_games.sql). Traceability: FR-I18N-12.
// What a page still shows in English is written to test-results/i18n-missing/<page>.json.
const fs = require('fs');
const path = require('path');
const { test, expect } = require('@playwright/test');

const GAMES = ['backgammon', 'blockdoku', 'checkers', 'chess', 'gomoku', 'hnefatafl', 'ludo', 'morris', 'nonogram', 'othello', 'owldoku', 'path-of-berserk', 'runborg', 'stratego', 'sudoku', 'yatzy'];
const PAGES = [...GAMES, ...GAMES.filter(g => g !== 'runborg').map(g => g + '-rules')];
const OUT = path.join(__dirname, '..', '..', '..', 'test-results', 'i18n-missing');

/** Opens a page in a language and waits until its texts are in. */
async function open(page, url, lang) {
  await page.addInitScript(l => { try { localStorage.setItem('wot_lang', l); } catch (e) {} }, lang);
  const loaded = page.waitForResponse(r => r.url().includes('texts.php') && r.url().includes('shared=games'));
  await page.goto(url);
  await loaded;
  await page.waitForTimeout(300);
}

test.beforeEach(async ({}, info) => {
  test.skip(info.project.name !== 'chromium', 'the same texts in every browser');
});

for (const p of PAGES) {
  test(`TC-E2E-227: games/${p}.html in Swedish has no English left`, async ({ page }) => {
    await open(page, `/games/${p}.html`, 'sv');
    const missing = await page.evaluate(() => HakooI18n.missing());
    fs.mkdirSync(OUT, { recursive: true });
    fs.writeFileSync(path.join(OUT, p + '.json'), JSON.stringify(missing, null, 1));
    expect(missing).toEqual([]);
  });
}

test('TC-E2E-228: a game page changes language in place, also what its script writes later, and back to English', async ({ page }) => {
  await open(page, '/games/nonogram.html', 'sv');
  await expect(page.locator('#v-home h2').first()).toContainText('Dagens pussel');
  await expect(page.locator('#rules-title')).toHaveText('Så spelar du Nonogram');
  await page.evaluate(() => setLang('de'));
  await expect(page.locator('#v-home h2').first()).toContainText('Rätsel des Tages');
  await page.locator('#practice-levels [data-practice="easy"]').click();          // the puzzle: written by the script
  await expect(page.locator('#restart')).toHaveText('Neu anfangen');
  await page.evaluate(() => setLang('en'));
  await expect(page.locator('#restart')).toHaveText('Start over');
  await expect(page).toHaveTitle('Nonogram — HAKOO Games');
});
