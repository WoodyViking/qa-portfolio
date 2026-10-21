// Shared helpers for the browser tests. Test users are fictional (docker/db/init/90-test-data.sql).
const crypto = require('crypto');

const USERS = { alice: ['100000000000000001', 'Alice'], bob: ['100000000000000002', 'Bob'], carol: ['100000000000000003', 'Carol'],
  erik: ['100000000000000005', 'Erik'] };

// Same as qa/automation/integration/bootstrap.php: a hakoo_auth cookie signed with the TEST secret.
const <TEST_SECRET> = '<TEST_SECRET_VALUE>';
function sessionCookie([id, name], baseURL) {
  const b = Buffer.from(JSON.stringify({ id, name, avatar: '', ts: Math.floor(Date.now() / 1000) }))
    .toString('base64').replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');
  const sig = crypto.createHmac('sha256', <TEST_SECRET>).update(b).digest('hex');
  return { name: 'hakoo_auth', value: `${b}.${sig}`, url: baseURL };
}

/** A new browser (its own cookies and tabs) at a screen size, logged in as a test user or not. */
async function device(browser, baseURL, user, viewport = { width: 1280, height: 800 }) {
  const ctx = await browser.newContext({ baseURL, viewport, locale: 'en-GB' });
  if (user) await ctx.addCookies([sessionCookie(user, baseURL)]);
  return { ctx, page: await ctx.newPage() };
}

module.exports = { USERS, sessionCookie, device };
