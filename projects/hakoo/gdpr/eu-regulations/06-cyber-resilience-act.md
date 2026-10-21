# 06: Cyber Resilience Act (CRA)

**Regulation (EU) 2024/2847.** It entered into force on 10 December 2024.
**Vulnerability and incident reporting** obligations apply from
**11 September 2026**. Most other obligations apply from **11 December 2027**.

## What it is

The CRA sets security requirements for **products with digital elements**
(hardware and software) that are *made available on the EU market in the
course of a commercial activity*. Examples: secure-by-default design,
vulnerability handling, security updates, an SBOM, CE marking, and reporting of
actively exploited vulnerabilities to ENISA/CSIRTs.

## Does it apply to hakoo.eu?

**No.**

- A website or SaaS is generally **not** a "product". Remote data processing is
  only covered when it is part of a product.
- Free, non-monetised open-source software, such as the presence bot code in this
  repo, is outside the scope when it is not supplied in the course of a
  commercial activity.

## When it would start to matter

- We start **selling** or monetising software, for example a paid Discord bot or a
  desktop/mobile app.
- We distribute a downloadable app or tool to the public as part of a
  commercial activity.

## Good practice we can borrow now

- Keep a list of third-party components (tesseract.js, discord.js / Node
  packages) with versions. That is effectively a lightweight SBOM.
- Update dependencies regularly (`npm audit` in `presence-bot/`).
- Have a way for people to report security problems (a `SECURITY.md` file in
  the repo with a contact address).

## Sources

- CRA text: https://eur-lex.europa.eu/eli/reg/2024/2847/oj
- Commission CRA page: https://digital-strategy.ec.europa.eu/en/policies/cyber-resilience-act
