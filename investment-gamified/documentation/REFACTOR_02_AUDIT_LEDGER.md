# Refactor #2: Portfolio Audit / Ledger Hardening

## Executive Summary

This refactor builds on Refactor #1 (pessimistic locking) and introduces an immutable audit ledger for every buy and sell operation. The goals are:

- Provide an append-only, tamper-resistant record of all portfolio mutations.
- Anchor portfolio rows with checkpoint IDs and checksums for quick integrity verification.
- Ensure ledger insertion is atomic with portfolio updates and balance changes.
- Preserve existing XP and level-up mechanics.

This document describes the problem, technical design, DB layout, testing strategy, deployment steps, and troubleshooting guidance.

---

## Problem Definition

Financial systems must guarantee that money and asset balances are never lost, double-counted, or silently corrupted. While Refactor #1 solved race conditions by serializing per-user modifications with pessimistic locks, it left two important gaps:

1. There was no single authoritative, immutable ledger tying each portfolio change to a recorded event.
2. Portfolio rows could be deleted (zero quantity), removing easy anchors for reconstructing history.

Without an immutable ledger and checkpoints, incident investigation and automated reconciliation become difficult. This refactor addresses those gaps.

---

## Technical Solution (Summary)

Core changes:

- New table `portfolio_audit` (immutable) storing: id, user_id, stock_id, type, quantity, price, total_amount, portfolio_snapshot (JSON), created_at.
- New optional fields on `portfolios`: `ledger_checkpoint_id`, `checksum` (sha256 of snapshot).
- `PortfolioService::buyStock()` and `sellStock()` now create an audit row inside the same DB transaction used for balance and portfolio updates so the operations are atomic.
- Database-level triggers prevent UPDATE/DELETE on `portfolio_audit` (supports MySQL, SQLite, PostgreSQL).
- Portfolio rows are retained even when quantity reaches zero (to preserve checkpoint linkage).

Transaction flow (simplified):

1. Start DB transaction.
2. lockForUpdate() user row.
3. Validate balance/quantity.
4. Update balance and portfolio rows (locked).
5. Insert legacy `transactions` row (backwards compatibility).
6. Insert `portfolio_audit` row with immutable JSON snapshot.
7. Compute checksum and update portfolio `ledger_checkpoint_id` and `checksum`.
8. Commit transaction.

This ensures the audit row is either fully recorded together with portfolio changes or not recorded at all.

---

## Database Structure

Table: `portfolio_audit`

- `id` BIGINT PK
- `user_id` BIGINT FK -> `users.id`
- `stock_id` BIGINT FK -> `stocks.id` (nullable)
- `type` VARCHAR(10) ("buy" or "sell")
- `quantity` INT
- `price` DECIMAL(14,4)
- `total_amount` DECIMAL(18,4)
- `portfolio_snapshot` JSON
- `created_at` TIMESTAMP

Indexes: `user_id`, `stock_id`

DB-level triggers prevent UPDATE/DELETE to enforce immutability.

Table: `portfolios` (changes)

- `ledger_checkpoint_id` (nullable FK -> `portfolio_audit.id`)
- `checksum` (nullable string) — sha256 of the `portfolio_snapshot`

These allow quick verification of the portfolio's current state against the ledger.

---

## SQL Examples

Insert audit row (performed by Laravel in a transaction):

```sql
INSERT INTO portfolio_audit (user_id, stock_id, type, quantity, price, total_amount, portfolio_snapshot, created_at)
VALUES (123, 42, 'buy', 5, 100.00, 500.00, '{"portfolio":{"quantity":5,"average_price":100},"user_balance":4500}', NOW());
```

Rebuild portfolio from ledger:

```sql
SELECT COALESCE(SUM(CASE WHEN type='buy' THEN quantity ELSE -quantity END),0) as qty
FROM portfolio_audit
WHERE user_id = 123 AND stock_id = 42;
```

---

## Testing Strategy

Automated tests cover:

- Audit insertion on buy/sell
- Ledger immutability (DB triggers)
- Checksum correctness
- Rebuild portfolio from ledger
- Edge cases: zero quantities, insufficient funds (no audit created)
- Concurrency scenarios (leveraging pessimistic locks from Refactor #1)

See `tests/Feature/ConcurrentLedgerTest.php` for implementation.

---

## Deployment Guide

1. Review code changes and run CI.
2. Run migrations: `php artisan migrate` (adds `portfolio_audit` and new columns).
3. Deploy application code.
4. Run smoke tests (examples in this repository).
5. Monitor logs for trigger errors or deadlocks.

Rollback: use `php artisan migrate:rollback` then revert code.

Minimal downtime: migrations are additive and safe to run online. The triggers are created during migration and only affect the audit table.

---

## Troubleshooting

- If you see `portfolio_audit is immutable` errors during tests, it likely means a test or script attempted to update an audit row — do not attempt to update audit rows.
- If deadlocks appear, review stack traces and reduce transaction scope to avoid external API calls inside transactions.

---

## Code Review Checklist

- Is `portfolio_audit` insert performed inside the same transaction as portfolio/balance updates? YES
- Are there any external API calls inside transactions? NO
- Are triggers created for supported DB drivers? YES
- Is the checksum computed deterministically (sha256 of JSON snapshot)? YES
- Are portfolios retained (zero quantity) to preserve checkpoints? YES

---

## Appendix: Diagrams & Flow

See REFACTOR_02_QUICK_GUIDE.md and visual overviews in the repo for diagrams and before/after flows.
