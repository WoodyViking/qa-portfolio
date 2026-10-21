// Kunglig Vikingludo on the Ludo page (games/ludo.html, js/games/ludo.js, js/games/ludo-viking.js, ludo.php):
// the board choice when a game is made, the six-armed board, the numbered pieces with a button per move, and
// the crown and feather on the die. The rules themselves are tested on the server (LudoEngineTest, LudoApiTest).
// The classic game is played on the royal board without crowns and feathers (TC-E2E-258).
// Traceability: FR-LUDO-02, FR-LUDO-19, FR-LUDO-20, FR-LUDO-21, FR-LUDO-22, FR-I18N-14.
const { test, expect } = require('@playwright/test');

const checked = (page, name) => page.locator(`input[name=${name}]:checked`);
const option = (page, name, v) => page.locator(`input[name=${name}][value="${v}"]`);

test('TC-E2E-240: the board choice: Vikingludo adds 5–6 players and Purple and Orange, and hides the rule choices it does not use', async ({ page }) => {
  await page.goto('/games/ludo.html');
  await page.click('#go-computer');
  await expect(checked(page, 'board')).toHaveValue('classic');
  for (const v of ['5', '6']) await expect(option(page, 'players', v)).toBeHidden();
  for (const v of ['4', '5']) await expect(option(page, 'colour', v)).toBeHidden();
  await expect(page.locator('#set-exit')).toBeVisible();

  await option(page, 'board', 'viking').check();
  await expect(page.locator('#viking-note')).toBeVisible();
  for (const id of ['#set-exit', '#set-finish', '#set-more']) await expect(page.locator(id)).toBeHidden();
  await expect(page.locator('#opt-hutt')).toBeVisible();                              // Fia med hutt can still be added
  await expect(option(page, 'colour', '4')).toBeHidden();                             // Purple: only on the six-armed board
  await option(page, 'players', '6').check();
  await expect(page.locator('#players-hint')).toHaveText('You and 5 computer players.');
  await option(page, 'colour', '5').check();
  await expect(option(page, 'colour', '5')).toBeChecked();

  // Back to 4 players: Orange has no seat, so the colour goes back to Green; back to the classic board: 4 players.
  await option(page, 'players', '4').check();
  await expect(checked(page, 'colour')).toHaveValue('3');
  await option(page, 'players', '5').check();
  await option(page, 'board', 'classic').check();
  await expect(checked(page, 'players')).toHaveValue('4');
  await expect(option(page, 'players', '5')).toBeHidden();
  await expect(page.locator('#set-exit')).toBeVisible();

  // The rules page shows both boards.
  await page.goto('/games/ludo-rules.html');
  await expect(page.locator('#viking h2')).toHaveText('👑 Kunglig Vikingludo');
  await expect(page.locator('#viking .viking-fig svg')).toHaveCount(2);
});

test('TC-E2E-241: six players on the six-armed board: numbered pieces, your nest at the bottom left, crown and feather on the die', async ({ page }) => {
  await page.goto('/games/ludo.html');
  await page.click('#go-computer');
  await option(page, 'board', 'viking').check();
  await option(page, 'players', '6').check();
  await option(page, 'colour', '4').check();                                         // Purple
  await page.click('#start');
  await expect(page.locator('#v-game')).toBeVisible();
  await expect(page.locator('#rules-line')).toHaveText('Kunglig Vikingludo · 👑 crowns · 🪶 feathers');
  const board = page.locator('#board');
  await expect(board).toHaveClass(/viking/);
  await expect(board.locator('svg.viking-board')).toHaveCount(1);
  await expect(board.locator('.piece')).toHaveCount(24);
  await expect(page.locator('#pc-4-2 .num')).toHaveText('3');
  await expect(page.locator('#players .lplayer')).toHaveCount(6);

  // The board is turned so the purple nest is at the bottom left (purple has not rolled yet: all four are in it).
  const b = await board.boundingBox();
  for (let i = 0; i < 4; i++) {
    const r = await page.locator(`#pc-4-${i}`).boundingBox();
    expect(r.x + r.width / 2).toBeLessThan(b.x + b.width * .4);
    expect(r.y + r.height / 2).toBeGreaterThan(b.y + b.height * .6);
  }

  // The die: a crown instead of three pips, a feather instead of five.
  await expect(page.locator('#tray')).toHaveClass(/viking/);
  for (const v of ['3', '5']) {
    const face = page.locator(`#tray .die-face[data-value="${v}"]`).first();
    expect(await face.evaluate(el => getComputedStyle(el).backgroundImage)).toContain('data:image/svg+xml');
    expect(await face.locator('.pip').first().evaluate(el => getComputedStyle(el).display)).toBe('none');
  }
  expect(await page.locator('#tray .die-face[data-value="4"] .pip').first().evaluate(el => getComputedStyle(el).display)).not.toBe('none');
});

// The server's answer is made up here so the die shows a crown and two moves are possible: Green (seat 3) has a
// piece 2 and a piece 10 squares on; a crown takes them to the next crown (9 and 14 on), and the one ahead of
// piece 2 is Red's start square, where a red piece stands safe, so it goes back to its own nest.
const seat = kind => ({ kind, name: kind === 'human' ? 'Ylva' : '', ready: false, left: false });
const made = (pos, more = {}) => ({
  game: '0123456789abcdef', mode: 'computer', difficulty: 'medium', status: 'active', host: 3, you: 3,
  seats: [seat('computer'), seat('computer'), seat('computer'), seat('human')],
  rules: { exit: '6', finish: 'exact', tries: true, safeStart: false, block: false, hutt: false, board: 'viking' },
  pos, turn: 3, roll: null, tries: 0, drink: [], winner: null, reason: null, version: 7, eventCount: 0, from: 0, events: [], idle: 0,
  legal: {}, ...more,
});
test('TC-E2E-242: a button per piece says what the move does, and choosing one moves that piece', async ({ page }) => {
  const state = made([[0, -1, -1, -1], [-1, -1, -1, -1], [-1, -1, -1, -1], [2, 10, -1, -1]], { roll: 3, legal: { 0: 9, 1: 14 } });
  let moved = null;
  await page.route('**/ludo.php?action=new*', r => r.fulfill({ json: { status: 'ok', game: state.game, token: 'a'.repeat(32), state } }));
  await page.route('**/ludo.php?action=move*', r => {
    moved = r.request().postDataJSON().piece;
    r.fulfill({ json: { status: 'ok', state: { ...state, pos: [[0, -1, -1, -1], [-1, -1, -1, -1], [-1, -1, -1, -1], [9, 10, -1, -1]], turn: 0, roll: null, legal: {} } } });
  });
  await page.goto('/games/ludo.html');
  await page.click('#go-computer');
  await option(page, 'board', 'viking').check();
  await page.click('#start');

  await expect(page.locator('#status')).toHaveText('You rolled a crown: choose a piece to move.');
  const picks = page.locator('#pick .pick');
  await expect(picks).toHaveText(['1 to the next crown', '2 to the next crown, safe on a crown: back to your nest']);
  await expect(page.locator('#pc-3-0')).toHaveClass(/can/);
  await picks.first().hover();
  await expect(page.locator('#pc-3-0')).toHaveClass(/hl/);                            // which piece the button means
  await picks.first().click();
  await expect.poll(() => moved).toBe(0);
  await expect(page.locator('#pick')).toBeHidden();
});

// Green's pieces 2 and 4 stand on one ring square and 1 and 3 on one home square: two stacks, "24" and "13".
test('TC-E2E-244: pieces of one colour on one square are one bigger piece with their numbers', async ({ page }, info) => {
  const state = made([[0, -1, -1, -1], [-1, -1, -1, -1], [-1, -1, -1, -1], [56, 7, 56, 7]]);
  await page.route('**/ludo.php?action=new*', r => r.fulfill({ json: { status: 'ok', game: state.game, token: 'a'.repeat(32), state } }));
  await page.goto('/games/ludo.html');
  await page.click('#go-computer');
  await option(page, 'board', 'viking').check();
  await page.click('#start');
  const pc = i => page.locator(`#pc-3-${i}`);
  await expect(pc(1)).toHaveClass(/\bstack\b/);
  await expect(pc(1).locator('.num')).toHaveText('24');
  await expect(pc(1)).toHaveAttribute('aria-label', 'Green pieces 2, 4');
  await expect(pc(3)).toBeHidden();
  await expect(pc(0).locator('.num')).toHaveText('13');
  await expect(pc(2)).toBeHidden();
  const [big, red] = [await pc(1).boundingBox(), await page.locator('#pc-0-0').boundingBox()];
  expect(big.width).toBeGreaterThan(red.width * 1.1);                                // a little bigger than one piece
  if (info.project.name === 'chromium') await page.locator('#board').screenshot({ path: '../../test-results/ludo-viking-stacks.png' });
});

// A language in the address: the page is in it, nothing is stored, and a flag still chooses.
test('TC-E2E-243: ?SE, ?GE or ?lang=DK in the address shows the page in that language and stores nothing', async ({ page }) => {
  await page.goto('/games/ludo.html?SE');
  await expect(page.locator('#go-friends b')).toHaveText('Spela med vänner');
  await expect(page.locator('html')).toHaveAttribute('lang', 'sv');
  expect(await page.evaluate(() => localStorage.getItem('wot_lang'))).toBeNull();
  await page.goto('/games/ludo.html?GE');
  await expect(page.locator('#go-friends b')).toHaveText('Mit Freunden spielen');
  await page.goto('/games/ludo.html?lang=DK');
  await expect(page.locator('#go-friends b')).toHaveText('Spil med venner');
  await page.goto('/games/ludo.html');
  await expect(page.locator('#go-friends b')).toHaveText('Play with Friends');
});

test('TC-E2E-258: classic Ludo plays on the royal board of 15 × 15 squares without crowns and feathers, with numbered pieces', async ({ page }) => {
  await page.goto('/games/ludo.html');
  await page.click('#go-computer');
  await option(page, 'board', 'classic').check();
  await option(page, 'players', '4').check();
  await page.click('#start');
  await expect(page.locator('#v-game')).toBeVisible();
  const board = page.locator('#board');
  await expect(board).not.toHaveClass(/viking/);                                        // not the Vikingludo rules
  await expect(board.locator('svg.viking-board')).toHaveCount(1);                      // but its board
  expect(await board.locator('svg.viking-board').getAttribute('viewBox')).toBe('0 0 520 520');
  // the frame (2), the 4 yards (2 each and 4 nest squares), 56 ring squares and 4 × 5 home squares: no crown (it has a rect)
  expect(await board.locator('svg.viking-board rect').count()).toBe(2 + 4 * 6 + 56 + 20);
  await expect(board.locator('svg.viking-board path[fill="#7a3fc0"]')).toHaveCount(0);                  // and no feather
  await expect(board.locator('.piece')).toHaveCount(16);
  await expect(page.locator('#pc-0-3 .num')).toHaveText('4');
  await expect(page.locator('#tray')).not.toHaveClass(/viking/);                       // a plain die
  if (test.info().project.name === 'chromium') await board.screenshot({ path: '../../test-results/ludo-classic-board.png' });
});
