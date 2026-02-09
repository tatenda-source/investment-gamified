# Refactor #2 Deliverables Index

This folder contains everything needed to review, test, and deploy the Portfolio Audit / Ledger Hardening refactor.

- `REFACTOR_02_AUDIT_LEDGER.md` — Full MR-level documentation (technical details, testing, deployment)
- `REFACTOR_02_QUICK_GUIDE.md` — Fast reference for developers and QA
- `REFACTOR_02_SUMMARY.md` — High-level summary for stakeholders
- Code changes:
  - `app/Services/PortfolioService.php` (modified)
  - `app/Models/PortfolioAudit.php` (new)
  - `database/migrations/*` (new)
  - `tests/Feature/ConcurrentLedgerTest.php` (new)
