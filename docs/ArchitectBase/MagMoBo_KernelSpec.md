# MagMoBo Kernel Subsystem Specification

**Purpose:** capture the production-grade contracts, responsibilities, and test expectations for the MagMoBo kernel before implementation work resumes. The kernel is the “operating system” of MagFlock: it boots the platform, coordinates components, enforces lifecycle and health policies, and exposes the buses extensions rely on. This document turns the high-level guidance in the MagFlock Bible, Technical Bible, and architecture addenda into actionable engineering requirements.

---

## 1. Core Responsibilities

1. **Boot & Shutdown Orchestration**
   - Execute deterministic boot stages (Pre-Boot → Kernel Init → POST → Component Load → Service Start → Ready).
   - Halt or roll back stages when critical preconditions fail; emit structured failure events.
   - Perform graceful shutdown with dependency-aware teardown.

2. **Component Registry & Dependency Graph**
   - Track component metadata (name, class, capabilities, dependencies, lifecycle state, health).
   - Resolve dependency ordering at boot; prevent circular graphs; allow hot registration.
   - Expose query API for other subsystems (e.g., Lifecycle Manager, Health Monitor, Extension Kernel).

3. **Lifecycle Management**
   - Define standard lifecycle hooks (`configure`, `boot`, `start`, `stop`, `restart`, `destroy`).
   - Support targeted restarts and hot swaps without destabilizing other components.
   - Integrate with deployment ring logic (staged rollout, canary) once Extension Kernel lands.

4. **System Buses**
   - Event Bus: synchronous/async publish�subscribe with scoped channels (`system.*`, `component.*`, `health.*`, `extension.*`) and contract validation via the `EventSchemaRegistry`.
   - Command Bus (future): request/response semantics for MagGate, MagCLI, automation agents.
   - Provide instrumentation (counters, latency, error rate) for observability stack.

5. **Health & State Management**
   - Aggregate heartbeats, metrics, and anomaly reports from components.
   - Escalate to Lifecycle Manager for remediation on threshold breaches (retry, quarantine, failover).
   - Persist global state (boot time, instance identity, cluster role) to the State Manager store.

6. **Configuration & Secrets**
   - Layer configs: default → environment → control-plane overrides → runtime hot patches.
   - Validate configurations against schema before boot continues.
   - Expose immutable views to components; redact secrets in logs/metrics.

7. **Logging & Telemetry**
   - Provide structured logger with correlation IDs, component context, and severity-level routing. Kernel instance IDs and environment metadata are stamped on every log entry.
   - Emit boot timeline, lifecycle transitions, health escalations, and audit-friendly events.
   - Feed metrics to MagMonitor via shared telemetry contracts. Kernel exposes a `Telemetry` collector used by EventBus, LifecycleManager, and BootManager to increment counters, capture handler runtimes, track emit latency, and surface timeout/error rates.

---

## 2. Subsystem Contracts

| Subsystem          | Key Interfaces & Inputs                                                                              | Outputs & Guarantees                                                                                                                         |
| ------------------ | ---------------------------------------------------------------------------------------------------- | -------------------------------------------------------------------------------------------------------------------------------------------- |
| **BootManager**    | `boot(): bool`, `shutdown(): void`; access to Config, Registry, Lifecycle, State, Logger, EventBus    | Runs six boot stages; emits `system.boot`, `system.boot_stage`, `system.ready`; aborts on critical failure; idempotent retries allowed.      |
| **Registry**       | `register(ComponentInterface)`, `resolveDependencies()`, `get(string)`, `list()`, `setState()`        | Maintains dependency graph; detects circular deps; guarantees component metadata accuracy; thread-safe for concurrent reads.                |
| **LifecycleManager** | `startAll()`, `start(string)`, `stop(string, reason)`, `restart(string)`                              | Enforces lifecycle transitions; integrates with health escalations; ensures stop/start ordering respects dependencies.                       |
| **EventBus**       | `subscribe(event, listener)`, `emit(event, payload, async=false)`, `queue(event, delay)`              | Guarantees in-order delivery per topic; supports synchronous and async handlers; surfaces delivery stats to Logger/Monitor.                 |
| **HealthMonitor**  | `registerProbe(name, callable)`, `report(component, status, metrics)`, `tick()`                      | Aggregates health snapshots; emits `health.degraded`, `health.recovered`; triggers lifecycle actions per policy; supports configurable SLAs. |
| **ConfigManager**  | `get(path, default)`, `set(path, value)`, `validate()`, `reload()`                                    | Validates against schema; blocks boot on failure; supports hot reload with change-event broadcast.                                          |
| **StateManager**   | `set(key, value)`, `get(key)`, `persist()`, `setSystemState(status)`                                 | Persists cluster/global state with ACID semantics (backed by file store or control-plane DB); exposes eventual consistency guarantees.      |
| **CacheManager**   | `remember(key, ttl, loader)`, `put`, `forget`, `tags`                                                | Provides multi-layer caching (in-memory, Redis); exposes instrumentation for cache hit/miss; isolates tenant-aware caches when needed.      |
| **Logger**         | `debug/info/warning/error/critical(message, channel, context)`                                       | Structured JSON output; correlation IDs; integrates with audit pipeline; enforces PII scrubbing policies.                                   |

All subsystems must implement dependency injection friendly constructors and avoid global state except via the Kernel singleton.

---

## 3. Event & State Protocols

1. **Boot Sequence Events**
   - `system.boot`: emitted at boot start.
   - `system.boot_stage`: payload `{ stage, started_at }`.
   - `system.boot_failed`: payload `{ stage, error, trace_id }`.
   - `system.ready`: indicates all components running; triggers control-plane registration heartbeat.

2. **Lifecycle Events**
   - `component.registered`, `component.state_changed`, `component.started`, `component.stopped`, `component.failed`, `component.recovery_failed`.
   - Payload schema enforced:
     - `component.registered` ? `name`
     - `component.state_changed` ? `name`, `old_state`, `new_state`
     - `component.started` / `component.stopped` ? `name`
     - `component.failed` ? `name`, `error`
     - `component.recovery_failed` ? `name`, `restart_count`

3. **Health Events**
   - `health.status_changed` ? `name`, `old_status`, `new_status`
   - `health.failed` ? `name`, `error`
   - `health.check_complete` ? `system`, `components`
   - `health.degraded` / `health.recovered` / `health.escalated` (future) must include root component metadata, metrics snapshot, and remediation action.

4. **State Snapshots**
   - Kernel stores `system.boot_time`, `system.instance_id`, `system.cluster_role`, and `system.version`.
   - Persisted in State Manager with WAL-style append for recovery.

---

## 4. Failure Semantics

1. **Boot Failure**
   - Any critical stage failure aborts boot; Boot Manager rolls back started components by invoking `LifecycleManager::stop`.
   - Emit `system.boot_failed`; State Manager marks system as `failed_boot`.
   - CLI/automation should retry after remediation with exponential back-off.

2. **Component Misbehavior**
   - Registry flags component as `error`; Health Monitor triggers lifecycle action (restart, quarantine).
   - Repeated failures escalate to Incident Commander once MagSentinel is integrated.

3. **Configuration Errors**
   - `ConfigManager::validate` must catch missing/invalid fields; Boot stage fails early.
   - Hot reload failures revert to previous known-good configuration; emit warning.

4. **Event Bus Back-pressure**
   - Async queue must apply bounded buffers; on overflow, shed load with clear telemetry events (`eventbus.backpressure`).

---

## 5. Testing Strategy

1. **Unit Tests**
   - Mock dependencies to validate each subsystem (Boot Manager stage ordering, Registry cycle detection, Lifecycle transitions, EventBus pub/sub semantics).
   - Use table-driven tests for edge cases (missing deps, config validation errors, health threshold transitions).

2. **Integration Tests**
   - Harness spins up kernel with fake components; verifies full boot→shutdown success path.
   - Inject failures: component throwing on `boot`, health probe returning degraded, event listener exceptions.
   - Ensure logging/metrics emit expected artifacts (parse logs to confirm structured format).

3. **Performance Smoke**
   - EventBus throughput baseline (e.g., 10k events/sec in-process).
   - Boot time target < 2s with default components; recorded for regression tracking.

4. **Resilience Scenarios**
   - Simulate crash mid-boot and confirm re-run resumes cleanly.
   - Force ConfigManager hot reload while components running; ensure consistent state.
   - Validate cache invalidation (tag-based) does not bleed across tenants.

---

## 6. Implementation Checklist

1. Subsystem contracts reviewed and approved.
2. Kernel test harness scaffolding created (mock components, CLI runner).
3. Boot Manager implemented with stage hooks and tests.
4. Registry and Lifecycle Manager fully unit-tested with dependency graphs.
5. Event Bus implemented with sync + async paths, metrics, error handling.
6. Health Monitor integrated with lifecycle policies and escalation events.
7. Config/State/Cache/Logger subsystems wired with validation and persistence.
8. Integration test suite covering boot success/failure, restarts, health degradation.
9. Documentation updated (README + developer guides) to reflect commands and subsystem usage.
10. Observability hooks verified (logs, metrics, state snapshots).
11. Kernel reset/unboot API exposed for tooling and tests (`Kernel::resetInstance`).

---

## 7. Open Questions

1. **Command Bus Scope:** do we ship V1 with read-only command handling (e.g., simple dispatch) or defer until MagGate needs it?
2. **State Store Backing:** initial implementation uses local filesystem; timeline for migrating to control-plane replicated store?
3. **Multi-instance Coordination:** what semantics are required before clustering (leader election, distributed locks)?
4. **Extension Kernel Coupling:** which kernel events must be formalized now to avoid breaking changes when the mediation plane lands?

---

**Next Action:** build the test harness and start implementing Boot Manager with the above contracts in mind.







