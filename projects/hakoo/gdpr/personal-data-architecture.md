# Personal data architecture: "one person, one delete"

**Status:** built in `src/` (2026-09-26), not yet deployed or run against the live database.
See `database/migrations/migration_users.sql`, `src/hakoo-users.inc.php` and
[personal-data-register.md](personal-data-register.md).

**Decision while building:** the key is the existing **Discord id** instead of a new
`user_id`. Discord is the only login today, every table already uses `discord_id`,
and this avoids rewriting every table. If another login method is added later, a
surrogate `user_id` can be introduced then.

## Goal (from the project owner)

- Visitors can use **HAKOO Games** anonymously, with no cookies and no login.
- Members who **accept cookies and log in** get the full HAKOO Community.
- Everything we store about one person should be **easy to delete in one go** when they
  ask (GDPR Art. 17). The exception is **financial transactions**, which other laws require
  us to keep.

The first idea was **a separate database per member**. This document explains why we
recommend a different way to reach the same goal.

## Why not one database per member

| Problem | Details |
|---|---|
| Hosting limits | <HOSTING_PROVIDER> limits the number of databases, and every new database needs a DB user with rights. The website would need permission to *create* databases, which is a big security risk if the site is ever hacked. |
| Features need everyone's data | The roster, leaderboards, builds, loot-code reports and the member list all show data from **many** members at once. With one DB per member, every page would have to open dozens of databases. |
| Changes multiply | A new column means running the migration in every member's database, and backups and monitoring multiply the same way. |
| Deletion is not really simpler | Personal data also ends up in shared places: report counts, cached JSON files (`discord_presence.json`), server logs and backups. Dropping one database does not clean those. |

## Recommendation: one user row with everything hanging from it

Same result (one delete removes the person), but in one database:

```
hakoo_users                           ← one row per person who has logged in
  user_id (PK), discord_id, created_at,
  consent_version, consent_at         ← proof of consent (GDPR Art. 7(1))
      │
      ├── hakoo_links          (FK user_id ON DELETE CASCADE)
      ├── hakoo_ign            (FK user_id ON DELETE CASCADE)
      ├── hakoo_builds         (FK user_id ON DELETE CASCADE)
      ├── hakoo_code_reports   (FK user_id ON DELETE CASCADE)
      ├── wot_access / wot_snapshots (FK user_id ON DELETE CASCADE)
      └── hakoo_transactions   (FK user_id ON DELETE SET NULL)  ← financial records are kept, see below
```

- **Deleting a person = `DELETE FROM hakoo_users WHERE user_id = ?`.** The database removes
  every row in every linked table automatically (foreign keys with `ON DELETE CASCADE`), in one
  transaction, so it cannot be half done.
- **Exporting a person** (GDPR Art. 15/20) = follow the same foreign keys. `mydata.php`
  can be generated from the same list instead of being hand-written. Today, handwritten code
  misses three tables; see `eu-regulations/hakoo-compliance-checklist.md`.
- **A register** (`dockuments/personal-data-register.md`, to be written) lists every table and
  file that holds personal data, and how each is deleted. This doubles as the GDPR Art. 30 record.

## Financial transactions (the exception)

If HAKOO ever takes payments (donations with rewards, merchandise, and so on):

- Swedish bookkeeping law (*bokföringslagen*, ch. 7) requires accounting records to be kept for
  **7 years** after the end of the calendar year. GDPR Art. 17(3)(b) allows keeping data that a
  legal obligation requires. **Verify** the details with the accountant or payment provider.
- Keep transactions in their own table, `hakoo_transactions`, with `ON DELETE SET NULL`.
  When a member is deleted, the link to them is removed, and only what the law requires stays
  (date, amount, receipt number, and the payment provider's reference).
- Prefer a payment provider (Stripe, PayPal, Swish Handel) that stores the card and personal
  details, so HAKOO stores as little as possible.

## Anonymous HAKOO Games

- No `hakoo_users` row, no cookie and no localStorage (this is already how `hakoo-games.html` works).
- To be decided in the requirements prompt: if games keep high scores, store them
  **without identity** (a nickname typed per game, no account), or let a logged-in member
  *choose* to claim a score, which then links it to their user row.

## Remaining things to handle

| Item | Plan |
|---|---|
| Backups | Deleted data survives in backups until they rotate. Document the backup retention period (for example 30 days), and keep a list of deleted `user_id`s so that a restore does not bring them back. |
| Cached files | `discord_presence.json` and the other caches are rebuilt from Discord every few minutes. A deleted member disappears once they leave the server or opt out. |
| Server logs | IP addresses in web server logs are kept by <HOSTING_PROVIDER> for a limited time. Mention this in the privacy notice. |
| Consent record | Store which consent version was accepted, and when, on the user row. When the text changes, ask again. |

## Migration path (when we decide to build it)

1. Create `hakoo_users`, keyed by the existing Discord id so that current data can be linked.
2. Fill it from the Discord ids already in `hakoo_links`, `hakoo_ign`, `hakoo_builds` and the other tables.
3. Add `user_id` + foreign keys with `ON DELETE CASCADE` to each table.
4. Rewrite `mydata.php` export/delete around `hakoo_users`, and test that deleting a test user
   leaves zero rows behind.
