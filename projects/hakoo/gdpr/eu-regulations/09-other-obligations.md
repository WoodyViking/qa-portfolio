# 09: Other obligations (platform terms, children, copyright)

These are not EU regulations, but they are binding contracts or closely
related rules that the site depends on.

## Third-party platform and API terms

| Platform | What we use | Key points (**verify** the current terms) |
|---|---|---|
| **Discord** | OAuth login, bot with *Server Members* and *Presence* privileged intents | Discord Developer Terms and Developer Policy: use data only for the stated purpose, have a privacy policy, delete data when asked or when the bot is removed, and don't sell data. Privileged intents require a justification if the bot grows beyond 100 servers. |
| **Wargaming** (WoT/WoWs API) | Player stats, clan data | Wargaming Developer API terms: an application ID is required, attribution may be required, and rate limits apply. Game assets (tank icons) are subject to Wargaming's IP and fan-content rules. |
| **Valve / Steam** | Steam Web API, avatars | Steam Web API Terms of Use: API key conditions, and show that data comes from Steam. Don't imply endorsement by Valve. |
| **Digital Extremes** (Warframe) | Item data via WFCD / warframestat.us, images via jsDelivr | Warframe fan-content policy / community guidelines. WFCD data is open source (check its licence). |
| **Arrowhead** (Helldivers) | Links / community API | Community API usage rules |

## Children

- Discord requires users to be at least 13 (higher in some countries), so our Discord login effectively inherits that.
- GDPR Art. 8: if we ever rely on **consent** for a user under 13 in Sweden
  (13–16 in other member states), parental consent is required.
- Keep the privacy notice readable for teenagers. Plain language is required by
  GDPR Art. 12 anyway.

## Copyright and trademarks

- Game names, logos, icons and screenshots belong to the publishers. Use them
  under their fan-content policies, and add a footer disclaimer such as: "Not affiliated with
  or endorsed by Wargaming, Valve, Digital Extremes or Arrowhead. All
  trademarks belong to their owners."
- Fonts: check the licence of the self-hosted *Sweden Sans* files in
  `public_html/font/` before redistributing them in a public repo.
- User-published builds: the Terms should state that users grant the site a
  licence to display what they post.

## Security disclosure

- Add `SECURITY.md` to the repo and a `/.well-known/security.txt` (RFC 9116) on
  the site with a contact address for vulnerability reports.
