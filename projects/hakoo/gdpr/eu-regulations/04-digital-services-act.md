# 04: Digital Services Act (DSA)

**Regulation (EU) 2022/2065.** It has been fully applicable since 17 February 2024.
Sweden's Digital Services Coordinator is PTS.

## What it is

The DSA sets rules for **intermediary services**: mere conduit, caching and
**hosting**. Its main subjects are illegal content, transparency and user
rights. The obligations are layered: more duties apply to online platforms, and
the most apply to very large platforms.

## Does it apply to hakoo.eu?

**Unclear, and partly at most.**

- The DSA only covers "information society services", which are services
  *normally provided for remuneration*. A free, non-commercial hobby site with
  no ads is probably **outside** that definition. However, the Court of Justice
  reads "remuneration" broadly, for example to include ad-funded services.
- If it did apply: users can publish content (Warframe builds, loot-code
  reports, IGN notes) that other members can see, so we would be a **hosting
  service**, possibly an **online platform**.
- **Micro and small enterprises** (fewer than 50 people and at most EUR 10 million turnover) are
  **exempt from most online-platform obligations** (Art. 19) and from
  transparency reporting. The basic hosting duties still apply.

**Recommendation:** follow the basic hosting duties below as good practice.
They are cheap, and they are also useful for moderating the community.

## Requirements (the parts relevant to a tiny hosting service)

| Article | Requirement | hakoo.eu action |
|---|---|---|
| Art. 11–12 | A single point of contact for authorities and users | Publish a contact email on the site (for example, in the footer or on a contact page) |
| Art. 14 | Terms and conditions must state content rules and moderation | Add content rules to the Terms (no illegal content, no harassment, no cheats or exploits for sale) |
| Art. 16 | **Notice-and-action**: an easy way to report illegal content | A "Report" link on builds, or the contact email |
| Art. 17 | **Statement of reasons** when content is removed or restricted | Send a short message to the author (Discord DM is fine) with the reason |
| Art. 18 | Report suspected crimes that threaten life or safety to the police | Rare, but know the rule |

## What it means for hakoo.eu

- [ ] Rewrite `terms.html` so that it covers the **website** (it currently covers only the bot),
      including content rules.
- [ ] Add a visible contact point for reports.
- [ ] Keep a simple log of removed content and the reason.

## Sources

- DSA text: https://eur-lex.europa.eu/eli/reg/2022/2065/oj
- Commission DSA page: https://digital-strategy.ec.europa.eu/en/policies/digital-services-act-package
