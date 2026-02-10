# Refinement Pass 01: Explicit Non-Goals

**Purpose**: Signal intentional restraint, prevent accidental regressions into deferred work, and provide forward-looking roadmap.

**Status**: Completed February 2026. No new features or major rewrites in this refinement.

---

## What We Deliberately Did NOT Do (And Why)

### 1. Event Sourcing / CQRS Architecture

**Not Implemented**: No event bus, no separate read/write models, no audit table as primary source of truth.

**Why deferred**:
- Current optimistic versioning + audit trail is sufficient for single-region consistency
- Event sourcing adds complexity (snapshots, event replay, temporal queries) that pays off only at massive scale
- Our audit table already captures deltas; replay logic would be a layer on top
- Regulatory requirements don't mandate pure event sources yet

**When to revisit**:
- If compliance demands true temporal snapshots (e.g., "state at exact time T")
- If you need distributed eventually-consistent replicas across regions
- If audit trail becomes primary business logic (not just forensics)

**Cost of waiting**: Minimal—current ledger design is append-only and immutable, making future migration straightforward.

---

### 2. Async Pricing Ingestion & Background Jobs

**Not Implemented**: No message queue (SQS/Redis queues), no `PriceUpdateJob`, no batched price fetches.

**Current State**: Stock prices are fetched synchronously on-demand from external APIs, with circuit breaker fallback to cached values.

**Why deferred**:
- Synchronous fetch w/ cache is simpler and lower-latency for individual users
- Gamified trading is not a high-frequency trading system; 5-minute stale prices are acceptable
- Background jobs add operational overhead (job retry policy, dead letter queues, queue depth monitoring)
- For true async, you'd need idempotency keys (to prevent double-updates) and event streaming complexity

**When to revisit**:
- If sub-second pricing becomes a requirement (conflicts with gamified design)
- If you need <5 minute price freshness across all users simultaneously
- If external API quota becomes bottleneck (batch fetches would help)

**Cost of waiting**: Low—current circuit breaker is a graceful fallback. API quota tracking is already in place.

---

### 3. Universal Table Partitioning

**Not Implemented**: No partitioning by date, no partition pruning, no archival jobs yet.

**Current State**: `portfolio_audit` table has `created_at` index; cleanup is manual (`audit:clean` command).

**Why deferred**:
- Partitioning adds DDL complexity and schema migration overhead
- Useful only when table size exceeds ~100M rows or monthly growth outpaces retention
- Better to confirm production scale and load patterns before designing partition strategy
- Current index + cleanup command is sufficient until audit table >1GB

**When to revisit**:
- Audit table >500M rows or >5GB in size
- `audit:clean` delete operations start causing lock contention
- Archival to S3 becomes regulatory requirement

**Current mitigation**: Cleanly separated deletion logic (can swap to partition rotation without rewriting service code).

**Roadmap**: See `PRODUCTION_SCALE_FIXES_GUIDE.md` "Next prioritized technical tasks" item 1.

---

### 4. Advanced Concurrency Testing (Chaos Engineering)

**Not Implemented**: No jepsen-style testing, no network partition simulation, no concurrent writer race harnesses at >10 threads.

**Current State**: `ConcurrentTradeTest` has explicit invariant assertions and simulates serialized concurrent ops. Enough to validate core logic. But not "torture test" scale.

**Why deferred**:
- Single-server deployment doesn't have network faults (our primary failure mode)
- Orchestration + multi-DB instance testing requires staging environment setup
- Thread pools with high contention expose issues faster via real load testing than simulation
- Current tests catch correctness bugs; load tests catch performance degradation

**When to revisit**:
- Multi-region replication or cross-data-center failover is introduced
- Production load testing reveals lock contention or retry storms
- You need formal proof-of-correctness for regulatory reasons

**Current mitigation**: Existing tests have invariant guards; new features should add concurrency tests before merging.

---

### 5. Real-Time Leaderboard (WebSocket Push)

**Not Implemented**: No WebSocket connections, no leaderboard push notifications when rankings change.

**Current State**: Leaderboard is HTTP paginated, cached for 5 minutes, client polls or refreshes on demand.

**Why deferred**:
- Gamified design is not competitive real-time (e.g., not gaming). Eventual consistency is fine.
- WebSocket + Redis Pub/Sub adds operational complexity (connection limit, memory, scaling)
- 5-minute stale leaderboard is acceptable UX for an educational app
- Polling with browser tab focus detection is simpler and preserves client battery

**When to revisit**:
- Product requirement changes to "real-time competitive rankings"
- User engagement metrics show high page refesh rate (indicates demand for real-time)

**Cost of waiting**: Low—cache invalidation logic is already in place; can be adapted to pub/sub later.

---

### 6. Database-Level Encryption at Rest

**Not Implemented**: No column-level encryption, no transparent data encryption (TDE).

**Current State**: AWS RDS with default encryption; secrets in `.env` are managed by ops.

**Why deferred**:
- Encrypted columns add computational overhead to every query (decryption cost)
- PII is minimal in this system (mostly usernames and XP, not SSNs or credit cards)
- AWS RDS encryption already protects data at rest (covers compliance for most regulations)
- Column encryption is needed only for HIPAA/PCI-DSS regulated data

**When to revisit**:
- If you add payment or health information
- If regulatory audit requires column-level encryption

**Cost of waiting**: Moderate—adding column encryption later requires migration and index rebuilds.

---

### 7. Advanced Observability (Distributed Tracing, Spans)

**Not Implemented**: No OpenTelemetry instrumentation, no Jaeger/Datadog APM integration.

**Current State**: Laravel logging to files, basic Sentry error tracking (if configured).

**Why deferred**:
- Single-server deployment doesn't benefit much from trace causality (most requests hit one service)
- Spans useful mainly for multi-service systems (microservices) where latency is distributed
- Context propagation overhead is non-zero and complexity is real
- Current log files + alerts (if metrics added per GUIDE) are sufficient for debugging

**When to revisit**:
- Microservice architecture is introduced
- Request latency becomes a top user complaint
- SLA requires sub-100ms p99 latency (you'll need detailed timing breakdowns)

**Cost of waiting**: Very low—logging hooks are already in place; instrumentation can be layered.

---

### 8. Database Read Replicas with Load Balancing

**Not Implemented**: No read/write splitting, no replica lag handling, no failover orchestration.

**Current State**: Single primary database, all queries go to primary (reads + writes).

**Why deferred**:
- Single primary is simpler and has no replica consistency issues
- Read load is currently light (paginated APIs + caching handle most requests)
- Replica lag introduces phantom read scenarios (read old balance, write new value)
- Multi-replica orchestration requires careful connection pooling and failover logic

**When to revisit**:
- DB query latency becomes P99 bottleneck (sustained >50ms per request)
- Read throughput exceeds capacity of single primary
- You need HA (high availability) with automatic failover

**Current mitigation**: Pagination and query projections already reduce read load. Caching is in place.

---

### 9. User-Defined Gamification Rules (Stored in DB)

**Not Implemented**: No admin UI for adjusting XP rewards or level thresholds at runtime.

**Current State**: XP/level rules are in `config/gamification.php`, pulled via `Config::get()` at runtime.

**Why deferred**:
- Config-driven rules are sufficient for current use case (rare tweaks via ENV vars)
- Database-driven rules add query overhead and cache invalidation complexity
- Admin UI requires validation, audit logging, and rollback mechanisms
- Rule versioning (which version applied to which trade) becomes complex
- Current approach allows A/B testing via feature flags without DB schema changes

**When to revisit**:
- Product team needs to adjust rules frequently (daily/weekly)
- You need audit trail of who changed rules and when
- Rules become complex (e.g., "XP multiplier for first trade of day")

**Cost of waiting**: Low—migration from config to DB is straightforward (add table + migration, update PortfolioService).

---

### 10. GraphQL API (in Addition to REST)

**Not Implemented**: No GraphQL schema, no apollo server, no field-selection optimization.

**Current State**: REST endpoints with fixed response shapes (Portfolio index, leaderboard, etc.).

**Why deferred**:
- REST + pagination is sufficient for current client needs
- GraphQL adds complexity (schema authoring, nested resolver execution, query cost analysis)
- Most benefits of GraphQL (client-side field selection) are mitigated by good REST paginated API design
- One data shape per endpoint is easier to reason about and cache

**When to revisit**:
- Client-side team frequently needs different field combinations (over-fetching is a real pain)
- Mobile clients want fine-grained control over payload size

**Cost of waiting**: Medium—adding GraphQL layer later requires schema design and query costing rules. Best done by team.

---

## Anti-Pattern Risks (What Could Go Wrong?)

### If You Ignore These Non-Goals:

1. **Premature event sourcing**: You build event replay logic that never gets used, adding 50% overhead to every write.
2. **Async jobs without idempotency**: Background price updates cause duplicate charges if code is redeployed mid-batch.
3. **Partition explosion**: Partition monthly, but queries start hitting multiple partitions, killing performance.
4. **Concurrency tests that lie**: Tests pass but miss invariant violations under real load; causes production bugs.
5. **WebSockets without plan**: Add real-time leaderboard, then connection count exceeds server limits during school hours.

**Mitigation**: Follow the "When to revisit" criteria above. Don't implement based on "what's cool" or "competitors have it."

---

## Appendix: How to Decide When to Revisit

When someone proposes a non-goal, ask:

1. **Is there a user complaint or impact?** (e.g., "leaderboard is too stale") vs. "this is best practice"
2. **Can we solve it simpler first?** (e.g., can we optimize the query instead of adding async?)
3. **What breaks if we don't do it?** (real impact) vs. "we might hit scale issues someday"
4. **What's the migration cost?** (refactoring + data migration + testing?) vs. benefit
5. **Do we have production data to guide the decision?** (e.g., partition size before partitioning)

If you can't answer 1, 3, and 4 clearly, it's probably too early.

---

**Last updated**: February 10, 2026  
**Owner**: Senior Engineering Lead  
**Status**: Living document. Update when items move from non-goal → roadmap.
