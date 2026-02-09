# Refactor #2 Summary

Overview

This refactor adds an immutable audit ledger to the trading flow and anchors portfolio rows to ledger checkpoints and checksums. It complements Refactor #1 (pessimistic locking) to provide high-integrity, auditable operations suitable for regulated or production financial systems.

Business impact

- Reduces time-to-investigation for disputes
- Enables deterministic rebuilds of portfolio state from ledger
- Keeps per-user serialization for correctness while adding minimal latency

Technical impact

- Adds one small table and two optional columns — low schema churn
- Adds DB triggers for immutability (compatible with MySQL, SQLite, PostgreSQL)

Testing & rollout

- Automated tests included (`ConcurrentLedgerTest.php`)
- Rollout is migration-first and backward-compatible
