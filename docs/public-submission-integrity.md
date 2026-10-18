# Public submission integrity and concurrency

## Public POST route inventory

All public form routes are grouped in `routes/web.php` under `throttle:5,1`:

| Route | Duplicate/capacity rule | Database backstop | Abuse and consent controls |
| --- | --- | --- | --- |
| `public.leads.store` | Reject an existing non-deleted lead email | Unique virtual `customer_leads.active_email` for rows where `deleted_at IS NULL` | `website` prohibited; privacy consent required; IP rate limit |
| `public.competitions.entries.store` | Reject duplicate email within a competition and enforce `maximum_entries` | Unique `(competition_id, email)` index | `website` prohibited; privacy consent required; IP rate limit |
| `public.newsletter.store` | Existing active subscription is idempotent | Unique virtual `newsletter_subscribers.active_email` for rows where `deleted_at IS NULL` | `website` prohibited; privacy consent required; IP rate limit |
| `public.reviews.store` | One active review per customer/product; soft-deleted reviews may be replaced | Unique generated active-customer key, documented in `product-review-uniqueness.md` | `website` prohibited; privacy consent required; customer eligibility check; IP rate limit |

Email comparison follows the database's configured collation, as it did before this change. This issue does not silently normalize or rewrite existing email identities. The current throttle and honeypot behavior remains in place; neither is treated as a business-integrity control or a complete bot defense.

## Competition capacity locking

`CompetitionEntrySubmission` starts a database transaction, locks the parent competition row with `SELECT ... FOR UPDATE`, checks the current active entry count and duplicate email, then inserts before releasing the lock. On transactional engines such as MySQL/InnoDB, requests for the same competition serialize on that row. Unlimited competitions use `maximum_entries = 0` (or another falsey value) and skip the capacity ceiling. Soft-deleted entries do not consume capacity, but their email remains reserved for that competition to match the existing duplicate rule.

Every application path that creates a competition entry must use this service, including the Filament create page. Direct SQL or new code that inserts `competition_entries` without locking the parent competition can bypass the capacity invariant. The database unique index independently prevents duplicate `(competition_id, email)` pairs but cannot enforce a count-based capacity ceiling by itself.

## Migration safety

The public-submission uniqueness migration checks for existing duplicate groups before adding indexes. If duplicates exist, it aborts with a reconciliation message rather than deleting or silently changing historical records. Review and reconcile those rows before retrying deployment. Generated virtual columns allow multiple soft-deleted lead/subscriber rows while permitting at most one active row per email.

## Production-engine verification plan

SQLite feature tests verify migration compatibility and application behavior, but SQLite does not reproduce MySQL/InnoDB row-lock semantics. Before release, run this plan against the deployed MySQL major version on a disposable database with the production schema:

1. Apply migrations and verify no duplicate preflight error; inspect `SHOW CREATE TABLE competition_entries`, `customer_leads`, and `newsletter_subscribers` to confirm the unique indexes and generated columns.
2. Create a currently open competition with `maximum_entries = 1` and zero entries.
3. Send two simultaneous valid POST requests to `public.competitions.entries.store`, with different valid email addresses, using separate HTTP clients/connections and a barrier so both requests begin together.
4. Assert exactly one request succeeds, the other receives the controlled `competition` validation error, and exactly one non-deleted entry exists.
5. Repeat with `maximum_entries = 2` and three concurrent distinct emails; assert exactly two active entries. Repeat with `maximum_entries = 0`; all valid distinct submissions should be accepted.
6. Submit the same email concurrently twice for one competition; assert one row exists and the loser receives a controlled email validation error, never a raw SQL exception.
7. Repeat duplicate lead and newsletter requests concurrently; verify one active row per email and user-safe responses.
8. Record MySQL version, migration output, request results, and resulting row counts in the release evidence.

The local Sandbox does not have a production MySQL connection, so this production-engine concurrency verification remains a deployment prerequisite. Do not interpret SQLite passing tests as proof of InnoDB lock behavior.
