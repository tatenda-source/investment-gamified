**Production Scale Fixes — Senior Engineer Guide**

Purpose: a concise, actionable summary of the critical fixes applied to make the investment-gamified backend production-safe at 10–100× scale, the root causes that led to these issues, and practical oversight and remediation guidance for making the codebase maintainable and resilient going forward.

**Scope**: This document covers the high-impact changes made (pagination, query projections, concurrency, retention, API resilience, validation, and config-driven gamification) and prescribes governance and technical follow-ups.

**Summary of Critical Fixes**
- **Portfolio Index (SQL projection + pagination + zero-filtering):** replaced PHP-side mapping with a single DB projection and added pagination (default 50, max 100). See: [app/Http/Controllers/Api/PortfolioController.php](app/Http/Controllers/Api/PortfolioController.php)
  - Why: eliminated N+1/PHP-iteration overhead, moved floating-point money math into DECIMAL columns and SQL expressions, prevented division-by-zero with CASE.
  - Impact: one DB query per request, bounded responses, lower memory and CPU in PHP threads.

- **Achievement listing (LEFT JOIN):** replaced in_array loop with a LEFT JOIN producing an `unlocked` boolean at SQL time. See: [app/Http/Controllers/Api/AchievementController.php](app/Http/Controllers/Api/AchievementController.php)
  - Why: removed O(a×u) PHP complexity; single query with consistent results.

- **Leaderboard (pagination + caching + deterministic ordering):** added `per_page`/`page`, cache TTL (default 5 minutes) with cache tags and deterministic tie-breaker ordering. See: [app/Http/Controllers/Api/AchievementController.php](app/Http/Controllers/Api/AchievementController.php) and [config/cache_ttl.php](config/cache_ttl.php)
  - Why: prevents repeated full-table sorts and enables cache invalidation on XP/level changes.

- **PortfolioService (optimistic versioned updates + retries):** collapsed multiple writes into a single atomic user update using a `balance_version`, added retry/backoff, single write to user row per transaction, and reduced audit snapshot size to deltas. See: [app/Services/PortfolioService.php](app/Services/PortfolioService.php) and migration [database/migrations/2026_02_10_000003_add_balance_version_to_users.php](database/migrations/2026_02_10_000003_add_balance_version_to_users.php)
  - Why: reduces lock contention under concurrent trades and ensures deterministic retries instead of long serialization.

- **Audit table immutability + retention:** added application-level immutability guards on `PortfolioAudit` and a nightly `audit:clean` command with an indexed `created_at` to enforce a default retention policy (2 years). See: [app/Models/PortfolioAudit.php](app/Models/PortfolioAudit.php) and [app/Console/Commands/CleanOldAudits.php](app/Console/Commands/CleanOldAudits.php)
  - Why: prevents unbounded storage growth and accidental mutations while giving an operational cleanup path.

- **External API resilience:** implemented a lightweight `CircuitBreaker` and `ApiQuotaTracker`; services fall back to stale cache when external providers fail or quota is exhausted. See: [app/Services/CircuitBreaker.php](app/Services/CircuitBreaker.php), [app/Services/ApiQuotaTracker.php](app/Services/ApiQuotaTracker.php), [app/Services/FinancialModelingPrepService.php](app/Services/FinancialModelingPrepService.php), [app/Services/StockApiService.php](app/Services/StockApiService.php)
  - Why: prevents external outages from cascading into core user flows and enables quota-aware behavior.

- **Public stock listing (pagination + search cap):** added paginated listing, category filtering and bounded `per_page`. See: [app/Http/Controllers/Api/StockController.php](app/Http/Controllers/Api/StockController.php)
  - Why: prevents huge payloads, reduces bandwidth and client-side strain.

- **Gamification rules moved to config:** XP rewards and level thresholds are now configurable, centralized, and used by `PortfolioService`. See: config/gamification.php and [app/Services/PortfolioService.php](app/Services/PortfolioService.php)
  - Why: avoid magic numbers in code; allow quick adjustments without deployments.

- **Validation and immutability:** added symbol validation and existence checks before external calls, and enforced `PortfolioAudit` immutability in code to keep DB triggers optional. See: [app/Http/Controllers/Api/ExternalStockController.php](app/Http/Controllers/Api/ExternalStockController.php) and [app/Models/PortfolioAudit.php](app/Models/PortfolioAudit.php)

**Root Causes — why the code accumulated problems**
- Lack of performance-first code reviews: business logic and display calculations were implemented in controllers instead of being evaluated for vectorized DB execution.
- Missing non-functional requirements: no documented expectations for pagination, retention, cache behavior, or external API quotas.
- Over-reliance on pessimistic locking as a quick correctness fix, without considering lock granularity or optimistic alternatives.
- Hardcoded business rules (XP/level thresholds) and duplicated logic across buy/sell flows, making future changes error-prone.
- Insufficient test coverage for concurrency, large datasets, and external failures — problems surfaced only under simulated load.
- Lack of operational guidance (cache driver, backup/archival, partitioning strategy) causing overlooked growth and performance issues.

**Defensive decisions made (and why they are safe/backward-compatible)**
- Prefer database projections and pagination — these return the same data structure but in pages, consistent with the API contract (added `meta` only).
- Optimistic update uses a new numeric `balance_version` column with a safe default (1). Code will retry up to 3 times — this avoids breaking behaviour for normal, low-concurrency usage while performing better under contention.
- Audit snapshots store deltas to cut per-row storage; raw historical payloads are still available in transaction/audit records where necessary. Nightly cleanup is opt-in via scheduled command and default retention is conservative (2 years).
- Circuit breaker returns stale cached data when available to avoid returning null for users — this prioritizes availability over freshest prices and is logged for operators.

**Operational & Oversight Recommendations (Senior Engineer actions)**
- Code review checklist (must be used on PRs):
  - **Query efficiency:** ensure no unbounded `get()` on user-visible endpoints; prefer pagination and DB projection for aggregates.
  - **Concurrency:** look for `lockForUpdate()` usage; require explanation and consider optimistic alternatives.
  - **Data growth:** any table that logs events (audit, history, metrics) must have a retention/partition plan and be reviewed for JSON payload size.
  - **External integrations:** require quota/tier documentation, circuit breaker, and fallback path in code.
  - **Config:** no hardcoded business numbers; route to `config/*` and prefer DB-driven rules if they change frequently.

- CI / Tests enhancement:
  - Add unit tests for logic and integration tests for endpoints (done for a subset). Tests must include: pagination, division-by-zero, immutability guards, symbol validation.
  - Add a concurrency integration test harness (parallel worker simulation) to validate optimistic updates under contention.
  - Add a daily load-test job in staging to simulate 50–100 concurrent trades per popular user.

- Observability and runbook:
  - Metrics: DB query latency, cache hit ratio, cache eviction rate, queue length, lock wait times, API quota usage per provider.
  - Alerts: sustained cache miss rate > X, DB lock waits above baseline, circuit open for > 1 minute, audit table size growth beyond expected rate.
  - Runbook for circuit open: degrade to cached pricing and notify via PagerDuty; investigate external provider status + quota logs.

- Deployment/migration rules:
  - Migrations that add `balance_version` must backfill values (safe default 1) in a separate step if table is large; avoid long locks on `users` table.
  - For large audit tables consider partitioning by month and support partition rotation/archival to S3 instead of DELETE when retention will be multi-year.

**Next prioritized technical tasks (deliverable list)**
1. Add partitioning and archival plan for `portfolio_audit` (monthly partitions + archive job). (High)
2. Add robust concurrency test harness and run in CI/staging with realistic trade volumes. (High)
3. Convert critical cache usage to Redis with tags enabled (for leaderboard invalidation). (Medium)
4. Add DB-level constraints and/or computed columns for money handling where appropriate (store cents as integer where needed). (Medium)
5. Roll out targeted load tests for popular-user scenarios and measure lock wait times / retries. (High)

**Checklist for PR reviewers**
- Has the author considered pagination for list endpoints? (Yes/No)
- Are heavy computations pushed into SQL where appropriate? (Yes/No)
- Any use of lockForUpdate — is there justification and estimated contention? (Yes/No)
- Are business constants moved to config or DB? (Yes/No)
- Are external calls wrapped with circuit breaker and fallback? (Yes/No)
- Has the PR added or updated tests demonstrating the fix? (Yes/No)

**Appendix: Files touched in the remediation**
- Portfolio index and pagination: [app/Http/Controllers/Api/PortfolioController.php](app/Http/Controllers/Api/PortfolioController.php)
- Portfolio optimistic updates and XP refactor: [app/Services/PortfolioService.php](app/Services/PortfolioService.php)
- Portfolio model zero-quantity scope: [app/Models/Portfolio.php](app/Models/Portfolio.php)
- Achievements and leaderboard: [app/Http/Controllers/Api/AchievementController.php](app/Http/Controllers/Api/AchievementController.php)
- Stock listing pagination: [app/Http/Controllers/Api/StockController.php](app/Http/Controllers/Api/StockController.php)
- External API resilience: [app/Services/CircuitBreaker.php](app/Services/CircuitBreaker.php), [app/Services/ApiQuotaTracker.php](app/Services/ApiQuotaTracker.php), [app/Services/FinancialModelingPrepService.php](app/Services/FinancialModelingPrepService.php), [app/Services/StockApiService.php](app/Services/StockApiService.php)
- Audit immutability and retention command: [app/Models/PortfolioAudit.php](app/Models/PortfolioAudit.php), [app/Console/Commands/CleanOldAudits.php](app/Console/Commands/CleanOldAudits.php)
- Migrations: [database/migrations/2026_02_10_000003_add_balance_version_to_users.php](database/migrations/2026_02_10_000003_add_balance_version_to_users.php), [database/migrations/2026_02_10_000004_add_index_to_portfolio_audit_created_at.php](database/migrations/2026_02_10_000004_add_index_to_portfolio_audit_created_at.php)

-- End of guide --
