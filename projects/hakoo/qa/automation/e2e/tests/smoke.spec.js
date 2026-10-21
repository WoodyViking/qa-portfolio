// Smoke test: every page opens and throws no JavaScript errors.
// Run first after each deploy to the test environment (confirmation + regression).
// Traceability: NFR-REL-01, NFR-MAINT-01.
const { test, expect } = require('@playwright/test');

const PAGES = [
  '/index.html?choose=1', '/community.html', '/hakoo-games.html', '/roster.html', '/profile.html',
  '/this-week.html', '/hakoo-loot.html', '/help.html', '/privacy.html', '/terms.html', '/install.html',
  '/wot-stats.html', '/WoWs-tracker.html', '/texts.html',
  '/games/warframe.html', '/games/warframe-builder.html', '/games/warframe-counter.html',
  '/games/eight-claw.html', '/games/tennocon-2026.html', '/games/gomoku.html',
  '/games/chess.html', '/games/othello.html', '/games/hnefatafl.html', '/games/stratego.html',
  '/games/backgammon.html', '/games/checkers.html', '/games/morris.html', '/games/nonogram.html', '/games/sudoku.html', '/games/owldoku.html',
  '/games/blockdoku.html', '/games/blockdoku-rules.html', '/games/path-of-berserk.html', '/games/path-of-berserk-rules.html', '/games/yatzy.html',
  ...['gomoku', 'chess', 'othello', 'hnefatafl', 'stratego', 'backgammon', 'checkers', 'morris', 'ludo', 'yatzy', 'nonogram', 'sudoku', 'owldoku'].map(g => `/games/${g}-rules.html`),
  '/wot-tracker.html',   // redirects to the profile when no account is linked (US-13); must not throw on the way
];

for (const path of PAGES) {
  test(`TC-E2E-040: ${path} loads without JavaScript errors`, async ({ page }) => {
    const errors = [];
    page.on('pageerror', e => errors.push(e.message));
    const res = await page.goto(path);
    expect(res.status()).toBe(200);
    await page.waitForLoadState('load');
    expect(errors).toEqual([]);
  });
}
