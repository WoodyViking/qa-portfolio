# 02: ePrivacy Directive (cookies and local storage)

**Directive 2002/58/EC, Art. 5(3)**, as amended by 2009/136/EC. Sweden implements it
in the Electronic Communications Act (*lag (2022:482) om elektronisk
kommunikation*, ch. 9 § 28). PTS supervises it. IMY is responsible for the
consent quality under GDPR.

## What it is

Storing information on, or reading information from, a user's device needs
**prior consent**. This covers cookies, `localStorage`, `sessionStorage`,
IndexedDB, pixels and fingerprinting. The only exception is storage that is
**strictly necessary** to provide a service the user explicitly asked for.

Consent must be an active choice. Pre-ticked boxes are not valid consent
(CJEU *Planet49*, C-673/17), and rejecting must be as easy as accepting.

## Does it apply to hakoo.eu?

**Yes**, but today everything we store appears to fall under the
strictly-necessary exemption:

| Item | Type | Purpose | Strictly necessary? |
|---|---|---|---|
| `hakoo_auth` | Cookie, HttpOnly, Secure, 30 days | Keeps the user logged in | **Yes** (the user logs in on purpose) |
| OAuth `state` cookie | Cookie, 10 min | CSRF protection during Discord login | **Yes** (security) |
| `wot_lang` | localStorage | Remembers the chosen language | **Yes** (a user-requested preference, generally accepted) |
| `wf_upsize` and similar builder keys | localStorage | Remembers builder UI state / builds | **Yes** (user-requested functionality) |
| `hakoo_start` | localStorage | Remembers the start-page choice (Community / Games) | Only saved after the visitor turns on **"Remember my choice"** on `index.html` (explicit opt-in). "Forget my choice" deletes it. |

**HAKOO Games** (`hakoo-games.html`) stores nothing at all and makes no third-party requests.
Keep it that way. Pages linked from it (privacy, terms) must not write storage on page load.

We use **no analytics, no ads, no tracking pixels and no social-media
embeds**, so **no cookie banner is needed** today.

## Requirements if this changes

A consent banner with "Accept" and "Reject" buttons of equal weight becomes
necessary if we add any of the following:
- analytics (Google Analytics, Matomo with cookies, etc.)
- embedded YouTube/Twitch players, or other third-party widgets that set cookies
- ads or affiliate tracking

Even without a banner, we must **inform** users about what we store. Add a
short "Cookies and local storage" section to the privacy notice that lists the
table above.

## What it means for hakoo.eu

- [ ] Add a "Cookies and local storage" section to the privacy notice.
- [ ] Before embedding any third-party player or widget, use a "click to load"
      placeholder so that nothing loads before the user acts.
- [ ] Re-check this file whenever a new `localStorage` key or cookie is added.

## Sources

- Directive 2002/58/EC: https://eur-lex.europa.eu/eli/dir/2002/58/oj
- EDPB Guidelines 05/2020 on consent
- PTS on cookies: https://www.pts.se/
