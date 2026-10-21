# Regulatory requirements

Requirements that come from law, derived from [`dockuments/eu-regulations/`](../../dockuments/eu-regulations/).
Verification methods: **T** = test, **I** = inspection (look at the code/config), **R** = review of a document.
Status is the state on 2026-09-26 for the code in `src/`.

> Not legal advice. When a requirement is uncertain, it says **verify**.

## GDPR (Regulation (EU) 2016/679)

| ID | Article | Requirement | Verify | Tests | Status |
|---|---|---|---|---|---|
| REG-GDPR-5 | Art. 5 | Data minimisation and storage limitation: collect only what a feature needs; defined retention periods. | R, I | TC-INT-006 | ⚠️ retention periods not defined |
| REG-GDPR-6 | Art. 6 | Each processing purpose has a documented lawful basis. | R | — | ❌ to be documented in the privacy notice |
| REG-GDPR-7 | Art. 7(1), 7(3) | Consent can be demonstrated and withdrawn as easily as it was given. | T, I | TC-E2E-007 | ⚠️ start page ✅; `hakoo_users.consent_*` not yet filled at login |
| REG-GDPR-13 | Art. 12–13 | A clear privacy notice covers all processing. | R | TC-MAN-006 | ❌ DEF-005 |
| REG-GDPR-15 | Art. 15 | Right of access: members can get all their data. | T | TC-API-003, TC-INT-003 | ✅ in `src/` |
| REG-GDPR-17 | Art. 17 | Right to erasure: members can delete all their data. | T | TC-INT-004, TC-API-005 | ✅ in `src/` (DEF-003 fixed) |
| REG-GDPR-20 | Art. 20 | Data portability in a machine-readable format. | T | TC-API-003 (JSON) | ✅ |
| REG-GDPR-25 | Art. 25 | Data protection by design and by default. | T, I | TC-E2E-020..024 | ✅ start page, HAKOO Games |
| REG-GDPR-30 | Art. 30 | Record of processing activities. | R | — | ⚠️ `personal-data-register.md` started |
| REG-GDPR-32 | Art. 32 | Appropriate security (auth, headers, secrets). | T, I | NFR-SEC-01..08 | ✅ mostly |
| REG-GDPR-33 | Art. 33–34 | Data-breach routine (notify IMY within 72 h). | R | — | ❌ not written |
| REG-GDPR-44 | Art. 44–49 | Transfers outside the EU have a legal basis (Discord, Steam, CDNs). | R | — | ⚠️ verify providers; DEF-006 |

## ePrivacy (Directive 2002/58/EC Art. 5(3); Sweden: LEK 2022:482 ch. 9 § 28)

| ID | Requirement | Verify | Tests | Status |
|---|---|---|---|---|
| REG-EPR-01 | Nothing is stored in or read from the visitor's device without consent, unless strictly necessary for a service the visitor asked for. | T | TC-E2E-020..024 | ✅ (DEF-002 fixed) |
| REG-EPR-02 | Visitors are told what is stored and why. | R | TC-MAN-006 | ⚠️ start page ✅; privacy notice ❌ |

## Digital Services Act (Regulation (EU) 2022/2065): good practice

| ID | Requirement | Verify | Tests | Status |
|---|---|---|---|---|
| REG-DSA-01 | A visible contact point on every page. | I | — | ❌ |
| REG-DSA-02 | A way to report illegal content (notice and action). | T | — | ❌ |
| REG-DSA-03 | Terms of use with content rules. | R | — | ❌ `terms.html` covers only the bot |

## Accessibility (WCAG 2.1 AA): good practice, no legal obligation

| ID | Requirement | Verify | Tests | Status |
|---|---|---|---|---|
| REG-WCAG-01 | New pages follow WCAG 2.1 AA (see NFR-USAB-01). | T | TC-E2E-010/011, TC-MAN-005 | ⚠️ partly tested |

## Bookkeeping (Swedish *bokföringslagen* ch. 7): only if payments are added

| ID | Requirement | Verify | Tests | Status |
|---|---|---|---|---|
| REG-BFL-01 | Accounting records are kept for 7 years, even when the member is deleted (**verify** with an accountant). | R, T | future | ➖ no payments today |
