// The core in real browsers (ADR-001): a host, a TV board and a player, each in a browser of its own.
// Traceability: US-01, US-02, US-03, US-04, US-06, K-01, K-12, K-13, K-15, K-29, K-71, AK-01, AK-03, AK-06, AK-08, AK-58, NFR-02.
const { test, expect } = require('@playwright/test');
const { USERS, device } = require('./helpers');

const TV = { width: 1920, height: 1080 }, PHONE = { width: 390, height: 844 };

/** Bob starts a game; a board asks to join; Erik asks to join. */
async function table(browser, baseURL, boardLang) {
  const host = await device(browser, baseURL, USERS.bob);
  await host.page.goto('/mysteries/');
  if (boardLang) await host.page.getByLabel('Language on the board').selectOption(boardLang);
  await host.page.getByRole('button', { name: 'Start a new game' }).click();
  await expect(host.page).toHaveURL(/play\.html\?code=[A-Z2-9]{8}$/);
  const code = new URL(host.page.url()).searchParams.get('code');

  const tv = await device(browser, baseURL, null, TV);
  await tv.page.goto(`/mysteries/board.html?code=${code}`);
  await tv.page.getByLabel('Name of this screen').fill("TV at Mum's");
  await tv.page.getByRole('button', { name: 'Show the board here' }).click();

  const erik = await device(browser, baseURL, USERS.erik, PHONE);
  await erik.page.goto(`/mysteries/play.html?code=${code}`);
  return { host: host.page, tv: tv.page, erik: erik.page, code, close: () => Promise.all([host.ctx.close(), tv.ctx.close(), erik.ctx.close()]) };
}

async function letIn(host, name) {
  await host.locator('ul.waiting li', { hasText: name }).getByRole('button', { name: 'Let in' }).click();
}

test('TC-E2E-003: the host lets a TV and a player in; each player sees only their own cards; a card shown to all is on the TV within 5 s; pause shows there too', async ({ browser, baseURL }) => {
  const g = await table(browser, baseURL);
  await expect(g.tv.getByText('Waiting for the host to let this screen in')).toBeVisible();      // AK-03: nothing before the yes
  await expect(g.erik.getByText('Waiting for the host to let you in')).toBeVisible();
  await expect(g.tv.locator('.board-grid')).toHaveCount(0);

  await expect(g.host.locator('ul.waiting li')).toHaveCount(2, { timeout: 10_000 });
  await letIn(g.host, "TV at Mum's");
  await letIn(g.host, 'Erik');
  await expect(g.tv.locator('ul.names')).toContainText('Erik', { timeout: 10_000 });
  await expect(g.tv.getByText(g.code)).toBeVisible();                                            // the code to join with

  await g.host.getByRole('button', { name: 'Start the game' }).click();
  await expect(g.erik.locator('.card')).toHaveCount(2, { timeout: 10_000 });
  await expect(g.host.locator('.card')).toHaveCount(2);
  const [erikFirst, erikSecond] = await g.erik.locator('.card span').allInnerTexts();
  for (const c of [erikFirst, erikSecond]) {                                                      // AK-06: not on the TV, not with Bob
    await expect(g.tv.locator('main')).not.toContainText(c);
    await expect(g.host.locator('ul.cards')).not.toContainText(c);
  }

  await g.erik.locator('.card', { hasText: erikFirst }).getByRole('button', { name: 'Show to all' }).click();
  await expect(g.tv.locator('ul.shown')).toContainText(erikFirst, { timeout: 5_000 });           // AK-01, NFR-02
  await expect(g.tv.locator('ul.shown')).toContainText('Erik showed');
  await expect(g.tv.locator('main')).not.toContainText(erikSecond);
  await expect(g.erik.locator('.card')).toHaveCount(1);

  await g.host.getByRole('button', { name: 'Pause' }).click();                                    // K-15
  await expect(g.tv.locator('p.state')).toHaveText('Paused', { timeout: 5_000 });
  await expect(g.erik.getByRole('button', { name: 'Show to all' })).toBeDisabled({ timeout: 5_000 });
  await g.host.getByRole('button', { name: 'Resume' }).click();
  await expect(g.tv.locator('p.state')).toHaveText('The game is on', { timeout: 5_000 });
  await g.close();
});

test('TC-E2E-004: the board speaks the language the host picked; each player picks their own', async ({ browser, baseURL }) => {
  const g = await table(browser, baseURL, 'sv');                                                  // the TV's browser is English
  await expect(g.host.locator('ul.waiting li')).toHaveCount(2, { timeout: 10_000 });
  await letIn(g.host, "TV at Mum's");
  await letIn(g.host, 'Erik');
  await expect(g.tv.locator('p.state')).toHaveText('Väntar på att spelet ska börja', { timeout: 10_000 });
  await expect(g.erik.locator('p.state')).toHaveText('Waiting for the game to start', { timeout: 10_000 });
  await g.erik.getByLabel('Language').selectOption('nl');
  await expect(g.erik.locator('p.state')).toHaveText('Wachten tot het spel begint');
  await expect(g.erik.getByRole('button', { name: 'Spel verlaten' })).toBeVisible();
  await g.close();
});

test('TC-E2E-018: two letters in the board address pick that screen\'s language; unknown letters leave the host\'s', async ({ browser, baseURL }) => {
  const g = await table(browser, baseURL, 'sv');                                                  // the TVs' browsers are English
  const tvs = [];
  for (const [letters, name] of [['DK', 'Danish TV'], ['GE', 'German TV']]) {
    const tv = await device(browser, baseURL, null, TV);
    tvs.push(tv);
    await tv.page.goto(`/mysteries/board.html?code=${g.code}&lang=${letters}`);
    await tv.page.getByLabel(letters === 'DK' ? 'Navn på denne skærm' : 'Name of this screen').fill(name);  // DK: Danish at once
    await tv.page.locator('#join button').click();
  }
  const [dk, ge] = tvs.map(d => d.page);
  await expect(dk.locator('#join')).toHaveCount(0);
  await expect(dk.getByText('Venter på, at værten lukker denne skærm ind')).toBeVisible();      // Danish before the yes too
  await expect(dk).toHaveURL(new RegExp(`board\\.html\\?code=${g.code}&lang=DK$`));               // the letters stay in the address
  await expect(g.host.locator('ul.waiting li')).toHaveCount(4, { timeout: 10_000 });
  for (const name of ["TV at Mum's", 'Danish TV', 'German TV']) await letIn(g.host, name);
  await expect(dk.locator('p.state')).toHaveText('Venter på, at spillet starter', { timeout: 10_000 });
  await expect(g.tv.locator('p.state')).toHaveText('Väntar på att spelet ska börja', { timeout: 10_000 });
  await expect(ge.locator('p.state')).toHaveText('Väntar på att spelet ska börja', { timeout: 10_000 });  // no German yet: the host's
  await dk.reload();                                                                              // a reload keeps Danish
  await expect(dk.locator('p.state')).toHaveText('Venter på, at spillet starter', { timeout: 10_000 });
  await Promise.all(tvs.map(d => d.ctx.close()));
  await g.close();
});

test('TC-E2E-005: a refused board learns it; a player who leaves is gone from the board', async ({ browser, baseURL }) => {
  const g = await table(browser, baseURL);
  await expect(g.host.locator('ul.waiting li')).toHaveCount(2, { timeout: 10_000 });
  await g.host.locator('ul.waiting li', { hasText: "TV at Mum's" }).getByRole('button', { name: 'Refuse' }).click();
  await expect(g.tv.getByText('The host did not let this device in.')).toBeVisible({ timeout: 10_000 });
  await letIn(g.host, 'Erik');
  await expect(g.host.locator('ul.names')).toContainText('Erik', { timeout: 10_000 });
  g.erik.once('dialog', d => d.accept());
  await g.erik.getByRole('button', { name: 'Leave the game' }).click();
  await expect(g.erik.getByText('This device is no longer in the game.')).toBeVisible();
  await expect(g.host.locator('ul.names')).not.toContainText('Erik', { timeout: 10_000 });
  await g.close();
});
