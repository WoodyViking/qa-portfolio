# EU regulatory requirements for hakoo.eu

This folder collects the EU rules that apply, or could apply, to the HAKOO
community website (hakoo.eu), its PHP backend, and the HAKOObot Discord bot.
It is written in English so every contributor in Europe can use it.

> **Not legal advice.** These notes are a practical summary for a small,
> non-commercial community project. When something is marked **verify**,
> check the current official text or national authority before relying on it.
> The notes were last reviewed on **2026-09-25**.

## Who we are, for regulatory purposes

| Fact | Why it matters |
|---|---|
| Operator is a private individual in **Sweden**, who runs the site for the [HAKOO] gaming clan | Swedish authorities supervise us, and Swedish national law applies on top of EU law |
| Hosting at <HOSTING_PROVIDER> (Sweden, EU) | The server data stays in the EU |
| Non-commercial: no ads, no sales, no analytics, no payments | Several laws (DSA, EAA, CRA, NIS2) apply only to commercial services or larger organisations |
| Login with Discord OAuth; accounts can be linked to Steam and Wargaming | We process personal data, so **GDPR applies** |
| Users can publish content (Warframe builds, loot-code reports, IGN notes) | This may make us a "hosting service" under the DSA, see file 04 |
| Pages load images from third-party CDNs (Discord, Steam, flagcdn, jsDelivr) | Visitors' IP addresses go to third parties (GDPR, ePrivacy) |

## Overview

| # | Regulation | Applies to hakoo.eu? | File |
|---|---|---|---|
| 1 | **GDPR**: General Data Protection Regulation (EU) 2016/679 | **Yes** | [01-gdpr.md](01-gdpr.md) |
| 2 | **ePrivacy** / cookie rules: Directive 2002/58/EC, Art. 5(3) | **Yes** (for the strictly-necessary exemption) | [02-eprivacy-cookies.md](02-eprivacy-cookies.md) |
| 3 | **NIS2**: Directive (EU) 2022/2555 | **No** (below the size threshold) | [03-nis2.md](03-nis2.md) |
| 4 | **DSA**: Digital Services Act, Regulation (EU) 2022/2065 | **Unclear/partly**: follow the light hosting rules as good practice | [04-digital-services-act.md](04-digital-services-act.md) |
| 5 | **Accessibility**: European Accessibility Act (Directive (EU) 2019/882) and WCAG | **No** (legally), but **recommended** | [05-accessibility.md](05-accessibility.md) |
| 6 | **CRA**: Cyber Resilience Act, Regulation (EU) 2024/2847 | **No** (as long as nothing is sold) | [06-cyber-resilience-act.md](06-cyber-resilience-act.md) |
| 7 | **AI Act**: Regulation (EU) 2024/1689 | **Not today**; applies if we add AI features | [07-ai-act.md](07-ai-act.md) |
| 8 | **e-Commerce Directive** 2000/31/EC and consumer law | **Mostly no**; contact info is good practice | [08-ecommerce-and-consumer-law.md](08-ecommerce-and-consumer-law.md) |
| 9 | Other obligations: platform/API terms, children, copyright | **Yes** (contractual) | [09-other-obligations.md](09-other-obligations.md) |

**Action list:** [hakoo-compliance-checklist.md](hakoo-compliance-checklist.md) lists
what hakoo.eu already does, the gaps found in the current code, and who fixes what.

## Authorities (Sweden)

| Area | Authority |
|---|---|
| GDPR, data breaches | IMY, Integritetsskyddsmyndigheten (imy.se) |
| Cookies / electronic communications | PTS, Post- och telestyrelsen (pts.se) |
| Digital Services Act coordinator | PTS |
| Consumer law | Konsumentverket |

Contributors in other EU countries: the EU rules are the same, but national
details (for example, the age of digital consent under GDPR Art. 8) vary.

## How to maintain this folder

- One file per regulation. Keep the same headings: *What it is*, *Does it apply*,
  *Requirements*, *What it means for hakoo.eu*, *Sources*.
- When a feature is added (payments, ads, AI, a mobile app, a new data table),
  re-check the "Applies?" column above and update the checklist.
- Write everything in English.
