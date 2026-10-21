// The HAKOO Games hub's shelves (hakoo-games.html, js/hakoo-games.js, games-popular.php):
// a menu of game types, and the 6 most played games first.
// Traceability: FR-GAMES-14, FR-GAMES-15, FR-GAMES-02, FR-BERSERK-01.
const { test, expect } = require('@playwright/test');

const shown = page => page.locator('#game-grid .game-card:visible');

test('TC-E2E-188: the hub opens on the 6 most played games; the menu picks a shelf; the choice is only in the address', async ({ page, context, request }) => {
  // Make Nine Men's Morris the most played: games the computer opens count as played.
  for (let i = 0; i < 40; i++) {
    await request.post('/morris.php?action=new', { data: { mode: 'computer', color: 'black', difficulty: 'easy' } });
  }
  const counts = await (await request.get('/games-popular.php')).json();
  expect(counts.days).toBe(30);
  expect(counts.counts.morris).toBeGreaterThanOrEqual(40);

  await page.goto('/hakoo-games.html');
  await expect(page.locator('#gmenu .gm-item[aria-current=page]')).toHaveAttribute('data-cat', 'popular');
  await expect(page.locator('#shelf-title')).toHaveText('🔥 Most played');
  await expect(shown(page)).toHaveCount(6);
  await expect(page.locator('#game-grid a[href="games/morris.html"]')).toHaveCSS('order', '0');   // most played first
  await expect(page.locator('#game-grid a[href="games/morris.html"] .plays')).toContainText('played in 30 days');

  await page.click('#gmenu [data-cat=dice]');
  await expect(page).toHaveURL(/#dice$/);
  await expect(page.locator('#shelf-title')).toHaveText('🎲 Dice games');
  await expect(shown(page)).toHaveCount(3);                     // Backgammon, Ludo and Yatzy
  await expect(page.locator('#gmenu [data-cat=dice] small')).toHaveText('3 games');
  await expect(page.locator('#gmenu [data-cat=board] small')).toHaveText('9 games');
  await page.click('#gmenu [data-cat=cards]');
  await expect(shown(page)).toHaveCount(1);                     // Runborg, the first version
  await expect(shown(page).first()).toHaveAttribute('href', 'games/runborg.html');
  await expect(page.locator('#gmenu [data-cat=cards] small')).toHaveText('1 game');
  await page.click('#gmenu [data-cat=all]');
  await page.click('#gmenu [data-cat=puzzles]');
  await expect(shown(page)).toHaveCount(4);                     // Nonogram, Sudoku, Owldoku and Blockdoku
  await expect(page.locator('#gmenu [data-cat=puzzles] small')).toHaveText('4 games');
  await page.click('#gmenu [data-cat=arcade]');
  await expect(shown(page)).toHaveCount(1);                     // Path of Berserk, the first arcade game
  await expect(page.locator('#gmenu [data-cat=arcade] small')).toHaveText('1 game');
  await page.click('#gmenu [data-cat=all]');
  await expect(shown(page)).toHaveCount(17);                    // 16 games and "more are on the way"

  await page.reload();                                         // the address keeps the shelf
  await expect(page.locator('#gmenu .gm-item[aria-current=page]')).toHaveAttribute('data-cat', 'all');
  expect(await page.evaluate(() => [localStorage.length, sessionStorage.length])).toEqual([0, 0]);
  expect(await context.cookies()).toEqual([]);
});
