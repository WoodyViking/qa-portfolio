// Blockdoku (games/blockdoku.html, js/games/blockdoku.js, blockdoku.php): placing blocks by dragging and by tapping,
// the game in the address, today's high scores, and the page at every size.
// Traceability: FR-BLOCKDOKU-01, FR-BLOCKDOKU-05, FR-BLOCKDOKU-06, FR-BLOCKDOKU-09.
const { test, expect } = require('@playwright/test');

const SHOTS = '../../test-results/blockdoku';
const filled = page => page.locator('#board .bd-sq.on').count();

test('TC-E2E-237: a guest plays Blockdoku: a block dragged onto the board or tapped and placed; a reload comes back to the game', async ({ page }, info) => {
  await page.goto('/games/blockdoku.html');
  await expect(page.locator('#top')).toContainText(/Nobody has scored today yet|Anonymous|A member/);
  await expect(page.locator('#top-login')).toBeVisible();                              // a guest: how to be in the list by name
  await page.click('#play');
  await expect(page.locator('#v-game')).toBeVisible();
  await expect(page).toHaveURL(/#g=[0-9a-f]{16}\.[0-9a-f]+$/);
  await expect(page.locator('#tray .bd-slot[data-k]')).toHaveCount(3);
  expect(await filled(page)).toBe(0);

  // Drag the first block to the top left corner (with the mouse: the block's middle under the pointer)
  const slot = page.locator('.bd-slot[data-k="0"]'), board = page.locator('#board');
  const s = await slot.boundingBox(), b = await board.boundingBox(), cell = b.width / 9;
  await page.mouse.move(s.x + s.width / 2, s.y + s.height / 2);
  await page.mouse.down();
  await page.mouse.move(b.x + cell * 2, b.y + cell * 2, { steps: 10 });
  await expect(page.locator('#board .bd-sq.ghost')).toHaveCount(4);                     // where it would go
  await page.mouse.up();
  await expect(page.locator('#moves')).toHaveText('1 block placed');
  expect(await filled(page)).toBe(4);
  await expect(page.locator('.bd-slot[data-k="0"]')).toHaveCount(0);                   // used

  // Tap a block, then a square in the bottom right part of the board
  await page.locator('.bd-slot[data-k="1"]').click();
  await expect(page.locator('.bd-slot[data-k="1"]')).toHaveAttribute('aria-pressed', 'true');
  await page.locator('#board .bd-sq[data-r="5"][data-c="5"]').click();
  await expect(page.locator('#moves')).toHaveText('2 blocks placed');
  expect(await filled(page)).toBe(8);
  if (info.project.name === 'chromium') await page.screenshot({ path: `${SHOTS}-game.png`, fullPage: true });

  await page.reload();                                                                  // the game is in the address
  await expect(page.locator('#v-game')).toBeVisible();
  await expect(page.locator('#moves')).toHaveText('2 blocks placed');
  expect(await filled(page)).toBe(8);
  await page.click('#to-top');
  await expect(page.locator('#v-home')).toBeVisible();
  await expect(page).not.toHaveURL(/#g=/);
});

test('TC-E2E-238: the keyboard: a number picks a block, the arrows move, Enter places it', async ({ page }, info) => {
  test.skip(info.project.name === 'mobile', 'a keyboard');
  await page.goto('/games/blockdoku.html');
  await page.click('#play');
  await expect(page.locator('#tray .bd-slot[data-k]')).toHaveCount(3);
  await page.keyboard.press('3');
  await expect(page.locator('.bd-slot[data-k="2"]')).toHaveAttribute('aria-pressed', 'true');
  await expect(page.locator('#board')).toBeFocused();
  await page.keyboard.press('ArrowUp');
  await page.keyboard.press('ArrowUp');
  await page.keyboard.press('Enter');
  await expect(page.locator('#moves')).toHaveText('1 block placed');
});

test('TC-E2E-239: the Blockdoku pages fit the window at every text size; the game is on HAKOO Games', async ({ page }, info) => {
  test.skip(info.project.name !== 'chromium', 'sets its own window sizes');
  for (const size of ['normal', 'xl']) {
    await page.addInitScript(s => localStorage.setItem('wf_textsize', s), size);
    for (const [w, h] of [[390, 844], [1366, 900]]) {
      await page.setViewportSize({ width: w, height: h });
      for (const url of ['/games/blockdoku.html', '/games/blockdoku-rules.html']) {
        await page.goto(url);
        await page.waitForTimeout(300);
        expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), `${url} ${size} ${w}`).toBe(true);
      }
      await page.goto('/games/blockdoku.html');
      await page.click('#play');
      await expect(page.locator('#tray .bd-slot[data-k]')).toHaveCount(3);
      const [board, tray] = [await page.locator('#board').boundingBox(), await page.locator('#tray').boundingBox()];
      expect(board.x >= 0 && board.x + board.width <= w, `${size} ${w}: the board inside`).toBe(true);
      expect(tray.y, `${size} ${w}: the blocks under the board`).toBeGreaterThan(board.y + board.height - 1);
      expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true);
      if (size === 'xl' && w === 390) await page.screenshot({ path: `${SHOTS}-phone-xl.png`, fullPage: true });
    }
  }
  await page.goto('/hakoo-games.html');
  await expect(page.locator('a.game-card[href="games/blockdoku.html"]')).toContainText('Blockdoku');
});
