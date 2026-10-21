# 01: GDPR (General Data Protection Regulation)

**Regulation (EU) 2016/679.** It has applied since 25 May 2018. In Sweden it is
supplemented by the Data Protection Act (*dataskyddslagen*, SFS 2018:218), and
IMY supervises compliance.

## What it is

GDPR governs any processing of personal data, meaning any information about an
identifiable person. Examples: Discord user IDs, usernames, avatars, in-game
names, IP addresses, online status, and which game someone is playing.

## Does it apply to hakoo.eu?

**Yes.** The "household exemption" (Art. 2(2)(c)) does **not** cover a website
that is publicly reachable. In *Lindqvist* (C-101/01), the Court of Justice
ruled that publishing personal data on the open internet is not a purely
personal activity. Being non-commercial does not change this.

The operator of hakoo.eu is the **controller**. <HOSTING_PROVIDER> (hosting) and Discord
(when it processes data on our behalf) act as **processors** or independent
controllers.

## Personal data we process today

| Data | Where | Source |
|---|---|---|
| Discord ID, name, avatar | `hakoo_auth` cookie (signed), DB tables | Discord OAuth login |
| Linked WoT / Steam accounts | `hakoo_links` | User links them |
| Clan access requests, officer flag | `wot_access` | User / officers |
| WoT stat snapshots, clan role | `wot_snapshots` | Wargaming API |
| In-game names, notes, Mastery Rank | `hakoo_ign` | User (roster.php) |
| Published Warframe builds + author | `hakoo_builds` | User (builds.php) |
| Loot-code reports | `hakoo_code_reports` | User (codes.php) |
| Online status + current game/activity | `discord_presence.json` | Presence bot (Discord gateway) |
| Member list, roles | bot cache files | Discord API |
| IP address, user agent | Web server logs, third-party CDNs | Every visit |

## Key requirements

| Article | Requirement | In practice |
|---|---|---|
| Art. 5 | Principles: lawfulness, transparency, purpose limitation, **data minimisation**, **storage limitation**, accuracy, security, accountability | Only collect what a feature needs; delete old data |
| Art. 6 | A **lawful basis** for every purpose | Most features: *legitimate interest* (running the clan community) or *contract* (the user asked for the feature). Document the choice. |
| Art. 7 | Consent must be freely given, specific, informed and withdrawable | Only needed if we rely on consent (for example, optional features) |
| Art. 8 | Children: consent age for online services is **13 in Sweden** (13–16 elsewhere) | Relevant if we rely on consent; Discord itself requires 13+ |
| Art. 12–14 | **Privacy notice**: who we are, what, why, legal basis, retention, recipients, transfers, rights | See the gap list: the current privacy.html only covers the bot |
| Art. 15–22 | Data-subject rights: access, rectification, erasure, restriction, portability, objection | `mydata.php` export/delete exists, but it is incomplete |
| Art. 25 | Data protection by design and by default | Guests already see presence data blurred, which is good |
| Art. 28 | Processor agreements (DPA) | <HOSTING_PROVIDER> hosting: accept their DPA (usually part of the terms, **verify**) |
| Art. 30 | Record of processing activities | Small organisations are only exempt for occasional processing. Keep a simple record; the table above is a start. |
| Art. 32 | Appropriate security | HTTPS, signed cookies, secrets out of Git, updated software |
| Art. 33–34 | **Data breach**: notify IMY within **72 hours**; tell users if high risk | Write down a short breach routine (see the checklist) |
| Art. 35 | DPIA for high-risk processing | Not required today (no large-scale sensitive data or profiling) |
| Art. 44–49 | Transfers outside the EU/EEA need a legal mechanism | Discord, Valve/Steam and CDNs are US companies. Check whether each is certified under the **EU-US Data Privacy Framework** or uses SCCs (**verify** per provider). |

## What it means for hakoo.eu

1. **Rewrite the privacy notice** so that it covers the whole website, not just
   the bot: every table above, the presence feature, the linking features,
   retention periods, third-party CDNs, and users' rights with a link to
   `mydata.php`.
2. **Make `mydata.php` complete.** Export and delete currently cover
   `hakoo_links`, `wot_access` and `wot_snapshots` only. **`hakoo_ign`,
   `hakoo_builds` and `hakoo_code_reports` are missing.**
3. **Set retention periods.** For example: stat snapshots older than N months
   are aggregated or deleted, and a presence cache is overwritten (already the case).
4. **Limit third-party requests.** Self-host flag images (flagcdn.com is
   loaded on nearly every page) and consider proxying or caching avatars. A German
   court (LG München I, 3 O 17493/20, 2022) found that loading Google Fonts
   from Google's servers without consent unlawfully disclosed the visitor's IP address.
   Our fonts are already self-hosted, which is good.
5. **Presence and activity data** is personal data about behaviour. Keep it
   member-only (as now) and let members opt out (for example, via a Discord role or
   a profile toggle).
6. **Keep secrets out of Git** (`<CONFIG_FILE>`, `<DEPLOY_DIR>`), which is already done via `.gitignore`.

## Sources

- GDPR text: https://eur-lex.europa.eu/eli/reg/2016/679/oj
- IMY guidance (English): https://www.imy.se/en/
- EDPB guidelines: https://www.edpb.europa.eu/
