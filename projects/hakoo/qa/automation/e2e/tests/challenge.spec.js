// Challenges between members on the board game pages (js/shared/board-game.js, board-game-api.inc.php):
// a member picks another member of the HAKOO Discord server, the bot posts it (to the Discord stub in the
// test environment), and the link in that post lets only the challenged member take the seat.
// Traceability: FR-GAMES-CH-01..04, FR-GAMES-CH-07.
const { test, expect } = require('@playwright/test');
const { loginAs } = require('./helpers');

const ALICE = '100000000000000001', BOB = '100000000000000002';
const MEMBERS = { members: [
  { user: { id: ALICE, username: 'alice', global_name: 'Alice' }, nick: null },
  { user: { id: BOB, username: 'bob', global_name: 'Bob' }, nick: null },
  { user: { id: '100000000000000003', username: 'carol', global_name: 'Carol' }, nick: 'Carol' },
] };

test('TC-E2E-180: Alice challenges Bob on the chess page; the link in Discord lets only Bob in, and the game starts for both', async ({ browser, baseURL }) => {
  const a = await browser.newContext();
  await loginAs(a, baseURL, ALICE, 'Alice');
  const pa = await a.newPage();
  await pa.route('**/discord_presence.json', r => r.fulfill({ json: MEMBERS }));   // the members as the bot sees them
  await pa.goto('/games/chess.html');
  await pa.locator('#go-challenge').click();
  await expect(pa.locator('#setup-title')).toHaveText('Challenge a HAKOO Member');
  await expect(pa.locator('#name')).toBeHidden();                                  // the Discord name is used
  expect(await pa.locator('#hakoo-members option').evaluateAll(o => o.map(x => x.value))).toEqual(['Bob', 'Carol']);   // not herself
  await pa.locator('#opponent').fill('Nobody');
  await pa.locator('#start').click();
  await expect(pa.locator('#error')).toContainText('Pick a member from the list');
  await pa.locator('#opponent').fill('Bob');
  await pa.locator('input[name=color][value=white]').check();                    // Alice white, Bob black
  await pa.locator('#start').click();
  await expect(pa.locator('#v-wait h2')).toHaveText('Waiting for Member 0002 to accept…');   // the name on the server
  await expect(pa.locator('#v-wait')).toContainText('The bot has posted your challenge in Discord');
  await expect(pa.locator('#invite-link')).toBeHidden();                           // nothing to share: only Bob can join
  await expect(pa.locator('#invite-timer')).toContainText('open for another 23:59');
  const id = new URLSearchParams((await pa.evaluate(() => location.hash)).slice(1)).get('g');

  const stranger = await browser.newContext();                                     // not logged in: asked to log in
  const ps = await stranger.newPage();
  await ps.goto(`/games/chess.html?challenge=${id}`);
  await expect(ps.locator('#error')).toContainText('Log in with Discord');
  expect(await ps.locator('#error a').getAttribute('href')).toContain(encodeURIComponent(`games/chess.html?challenge=${id}`));
  const carol = await browser.newContext();                                        // someone else: not for them
  await loginAs(carol, baseURL, '100000000000000003', 'Carol');
  const pc = await carol.newPage();
  await pc.goto(`/games/chess.html?challenge=${id}`);
  await expect(pc.locator('#error')).toContainText('This challenge is for another member');

  const b = await browser.newContext();
  await loginAs(b, baseURL, BOB, 'Bob');
  const pb = await b.newPage();
  await pb.goto(`/games/chess.html?challenge=${id}`);
  await expect(pb.locator('#v-game')).toBeVisible();
  expect(await pb.evaluate(() => location.search)).toBe('');                         // the game lives in the hash again
  await expect(pb.locator('#p-black .pname')).toHaveText('Bob (you)');
  await expect(pa.locator('#v-game')).toBeVisible();                               // Alice's page follows by itself
  await expect(pa.locator('#p-black .pname')).toHaveText('Bob');
  for (const c of [a, b, stranger, carol]) await c.close();
});
