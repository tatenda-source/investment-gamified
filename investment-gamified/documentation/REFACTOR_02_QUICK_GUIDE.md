# Refactor #2 Quick Guide

Purpose: Fast reference for developers and QA to validate and deploy the audit ledger.

Key commands

 - Run migrations: `php artisan migrate`
 - Run tests: `composer test tests/Feature/ConcurrentLedgerTest.php`

What changed (high level)

 - New immutable table `portfolio_audit` stores every buy/sell with a JSON snapshot.
 - `portfolios` gains `ledger_checkpoint_id` and `checksum` to anchor state to the audit.
 - `PortfolioService` now writes audit rows atomically with portfolio updates.

How to verify locally

1. `php artisan migrate --seed` (if you use seeders)
2. Run the feature tests: `composer test tests/Feature/ConcurrentLedgerTest.php`
3. Make a test buy then inspect the `portfolio_audit` table.

Troubleshooting

- If updating `portfolio_audit` fails: that is by design — the table is immutable.
- If tests fail with deadlocks: re-run tests; review whether external calls exist in transactions.
