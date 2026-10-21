# hakoo.eu compliance checklist

Status from a review of the code in `oldversion/` on **2026-09-25**.
Legend: ✅ done · ⚠️ partly / needs update · ❌ missing · ➖ not required (good practice)

## Already in place

| | Item | Where |
|---|---|---|
| ✅ | HTTPS, `Secure` + `HttpOnly` cookies, HMAC-signed session cookie | `discord-auth.php` |
| ✅ | Content-Security-Policy and security headers | `public_html/.htaccess` |
| ✅ | No analytics, ads or tracking cookies, so no cookie banner is needed | whole site |
| ✅ | Fonts are self-hosted, not loaded from Google | `public_html/font/` |
| ✅ | Guests see member presence blurred, and only members see who is online or playing what | `index.html`, `profile.html` |
| ✅ | GDPR self-service export + delete endpoint | `mydata.php` |
| ✅ | Secrets kept out of Git (`.gitignore`: `<CONFIG_FILE>`, `<DEPLOY_DIR>`, `<FTP_CREDENTIALS_FILE>`) | repo root |
| ✅ | EU hosting (<HOSTING_PROVIDER>, Sweden) | — |

## Gaps to fix

| Priority | Status | Item | Regulation | Suggested fix |
|---|---|---|---|---|
| 1 | ⚠️ | `mydata.php` export/delete ignored **`hakoo_ign`, `hakoo_builds`, `hakoo_code_reports`** | GDPR Art. 15, 17, 20 | **Fixed in `src/`** (2026-09-26) via `hakoo_users` + `hakoo-users.inc.php`; needs `migration_users.sql` + deploy |
| 1 | ⚠️ | `privacy.html` only describes **the bot**, not the website (logins, linked accounts, stats, builds, presence, CDNs) | GDPR Art. 13 | Rewrite as a site-wide privacy notice: controller, data, purposes, legal basis, retention, recipients, transfers, rights, a link to `mydata.php`, and IMY complaint info |
| 1 | ❌ | No documented **retention periods** (for example, `wot_snapshots` grows forever) | GDPR Art. 5(1)(e) | Decide the periods, and add a cleanup job or cron |
| 2 | ⚠️ | Visitors' IP addresses are sent to **cdn.discordapp.com**, Steam CDN and **jsDelivr** (the flag images are self-hosted since 2026-09-30, `iconer/flags/`) | GDPR Art. 6, 44 | Consider proxying or caching avatars and Warframe images; remove `https://flagcdn.com` from the CSP in `.htaccess` |
| 2 | ⚠️ | `terms.html` only covers the bot; there are no content rules for user posts | DSA Art. 14 (good practice) | Site-wide Terms with content rules, reporting, and a licence for user content |
| 2 | ❌ | No visible contact / report point on every page | DSA Art. 11–12, 16; e-Commerce Art. 5 | Shared footer with a contact email and a "report content" link |
| 2 | ❌ | No written **data-breach routine** | GDPR Art. 33–34 | A half-page routine: assess, contain, rotate secrets, and notify IMY within 72 h if there is risk |
| 3 | ⚠️ | No **record of processing** | GDPR Art. 30 | Started: `dockuments/personal-data-register.md` |
| 3 | ❌ | Presence/activity opt-out | GDPR Art. 21 | A profile toggle or Discord role that hides a member from the presence column |
| 3 | ➖ | 2FA on GitHub, Discord (bot owner), <HOSTING_PANEL>, email | NIS2 good practice | Enable it |
| 3 | ➖ | Accessibility basics (focus styles, contrast, alt text, labels) | WCAG 2.1 AA | Add them in the shared `src/css/base.css` |
| 3 | ➖ | `SECURITY.md` + `/.well-known/security.txt` | CRA good practice | Add a contact for vulnerability reports |
| 3 | ➖ | "Not affiliated with..." trademark disclaimer | Publisher fan-content policies | Add it to the shared footer |
| 3 | ⚠️ | Check the licence of the *Sweden Sans* font files before the repo is ever made public | Copyright | Check the licence |

## When to re-check

- A new database table or a new `localStorage` key or cookie → GDPR + ePrivacy
- Embedding third-party players or widgets → ePrivacy (consent needed)
- Payments, donations with rewards, or ads → consumer law, DSA, e-Commerce
- An AI chatbot or AI-generated content → AI Act Art. 50 + GDPR
- Selling or distributing an app or bot commercially → CRA
