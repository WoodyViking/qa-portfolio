// Nine men's morris (games/morris.html, js/games/morris.js on js/shared/board-game.js).
// playwright.config.js asks for less motion: the other player's turn shows at once.
// Traceability: FR-MORRIS-09, FR-GAMES-02, US-36.
const { test, expect } = require('@playwright/test');

const SHOTS = '../../test-results/morris';
const pt = (page, p) => page.locator(`#board .mpt[data-p="${p}"]`);

test('TC-E2E-186: placing against a friend, closing a mill and taking a stone; Undo the step; nothing is stored', async ({ page, context, request }, info) => {
  const api = (action, body, token) => request.post(`/morris.php?action=${action}`, { data: body, headers: token ? { 'X-Game-Token': token } : {} }).then(r => r.json());
  const a = await api('new', { mode: 'friend', color: 'white', name: 'Ann' });
  const b = await api('join', { game: a.game, invite: a.invite, name: 'Ben' });
  await page.goto(`/games/morris.html#g=${a.game}&p=${a.token}`);
  await expect(page.locator('#turn')).toHaveText('Place a stone (9 left). Your turn (White)');
  await expect(page.locator('#hand-white .hs')).toHaveCount(9);
  await expect(page.locator('#board .mpt.to')).toHaveCount(24);
  let n = 0;
  for (const [mine, theirs] of [[0, 9], [1, 10]]) {
    await pt(page, mine).click();
    await expect(page.locator('#move-count')).toHaveText(String(++n));
    await api('move', { game: a.game, to: theirs }, b.token);
    await expect(page.locator('#move-count')).toHaveText(String(++n), { timeout: 8000 });
  }
  // a7, d7 and now g7: a mill. Ben's stones are marked; Undo puts the stone back.
  await pt(page, 2).click();
  await expect(page.locator('#turn')).toHaveText('Mill! Take one of Ben\'s stones.');
  await expect(page.locator('#board .mpt.take')).toHaveCount(2);
  await page.click('#cancel-take');
  await expect(page.locator('#board .mpt.take')).toHaveCount(0);
  await expect(pt(page, 2)).not.toHaveClass(/\bw\b/);
  await pt(page, 2).click();
  await pt(page, 21).click();                                              // an empty point: not a stone to take
  await expect(page.locator('#error')).toContainText('marked stones');
  await pt(page, 9).click();
  await expect(page.locator('#move-count')).toHaveText('5');
  await expect(page.locator('#count-black')).toHaveText('8');
  await expect(page.locator('#board .mpt.mill')).toHaveCount(3);
  await expect(page.locator('#movelist button.mv').last()).toHaveText('g7 ×a4');
  await page.screenshot({ path: `${SHOTS}-mill-${info.project.name}.png`, fullPage: true });
  expect(await page.evaluate(() => [localStorage.length, sessionStorage.length])).toEqual([0, 0]);
  expect(await context.cookies()).toEqual([]);
});

test('TC-E2E-187: against the computer, the board fits the screen (also on a phone) and the computer answers', async ({ page }) => {
  await page.goto('/games/morris.html');
  await page.click('#go-computer');
  await page.check('input[name=color][value=white]');
  await page.check('input[name=difficulty][value=easy]');
  await page.uncheck('input[name=flying]');
  await page.click('#start');
  await expect(page.locator('#variant-tag')).toHaveText('No flying');
  await pt(page, 4).click();
  await expect(page.locator('#move-count')).toHaveText('2');
  await expect(page.locator('#turn')).toHaveText('Place a stone (8 left). Your turn (White)');
  const m = await page.evaluate(() => ({
    scroll: document.documentElement.scrollWidth - document.documentElement.clientWidth,
    board: document.getElementById('board').getBoundingClientRect(),
    point: document.querySelector('#board .mpt').getBoundingClientRect().width,
  }));
  expect(m.scroll).toBeLessThanOrEqual(0);
  expect(m.board.width).toBeGreaterThan(300);
  expect(Math.abs(m.board.height - m.board.width)).toBeLessThan(2);
  expect(m.point).toBeGreaterThanOrEqual(36);                              // big enough to hit with a finger
});
