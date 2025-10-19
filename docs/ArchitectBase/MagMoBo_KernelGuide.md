# MagMoBo Kernel – Enterprise Usage Guide

This guide complements the technical specification by describing how the kernel is composed, how to integrate new components, and which contracts must be honoured. Treat it as the operator/developer reference for the core platform.

---

## 1. Kernel Overview

| Aspect | Description |
| --- | --- |
| Purpose | Motherboard for MagFlock: boots the platform, coordinates components, enforces lifecycle/health policies, mediates events |
| Key modules | `Kernel`, `BootManager`, `Registry`, `LifecycleManager`, `HealthMonitor`, `EventBus`, `Telemetry`, `StateManager`, `ConfigManager`, `Logger` |
| Input config | `config/mobo.php` + layered overrides (future work: environment + secrets) |
| Outputs | Structured logs, metrics counters/timers, event emissions, persisted state |

**Boot flow (summary)**  
1. Pre-boot: config validation + writable storage check  
2. Kernel init: subsystems come online and telemetry is primed  
3. POST: connectivity checks, dependency resolution  
4. Component load: instantiate + `configure()` + `boot()`  
5. Service start: lifecycle `start()` in dependency order  
6. Ready: state snapshot, `system.ready` event, boot summary  

Shutdown reverses dependency order and emits `system.shutdown`.

---

## 2. Component Lifecycle Contract

Every component registered with the kernel **must** implement `MoBo\Contracts\ComponentInterface`. The lifecycle is enforced by `LifecycleManager` and backed by telemetry and event emissions.

| Hook | Purpose | Kernel guarantees |
| --- | --- | --- |
| `configure(array $config)` | Inject configuration before boot | Called exactly once prior to `boot()` |
| `boot()` | Allocate resources, warm caches | Executed during component loading stage |
| `start()` | Begin serving traffic | Ordered by dependency graph; on success emits `component.started` |
| `stop()` | Gracefully stop serving traffic | Invoked during shutdown/hot restart; emits `component.stopped` |
| `recover()` | Attempt self-heal after failure | Triggered by `LifecycleManager::recover()` within restart budget |
| `shutdown(int $timeout)` | Final teardown | Called during system shutdown |

**Telemetry:** `LifecycleManager` increments counters (`components.started.*`, `components.failed.*`) and records durations for start/stop operations.

---

## 3. Event Bus Contract

The Event Bus publishes structured payloads validated by the **Event Schema Registry** (`config/events.php`). Consumers can rely on required fields being present; missing fields trigger warnings.

| Channel | Required payload fields |
| --- | --- |
| `system.boot` | _none_ |
| `system.boot_failed` | `error` |
| `system.ready` | _none_ |
| `system.shutdown` | `timeout` |
| `component.registered` | `name` |
| `component.state_changed` | `name`, `old_state`, `new_state` |
| `component.started` / `component.stopped` | `name` |
| `component.failed` | `name`, `error` |
| `component.recovery_failed` | `name`, `restart_count` |
| `health.status_changed` | `name`, `old_status`, `new_status` |
| `health.failed` | `name`, `error` |
| `health.check_complete` | `system`, `components` |

**Developer guidance**
- Use `EventBus::on($event, $listener, $priority)` for sync handlers and `once()` for one-shot listeners.  
- Payloads include a `timestamp` (unix microtime float).  
- Telemetry counters: `events.emitted.*`, `events.handler.timeout`, `events.handler.error`.  
- Add new events by updating `config/events.php` and, if applicable, documenting them here and in the spec.

---

## 4. Telemetry & Logging

| Collector | Counters/Timers |
| --- | --- |
| EventBus | `events.emitted.total`, per-event counts, handler runtimes, timeout/error counters |
| LifecycleManager | `components.started.total`, `components.failed.total`, per-component counters |
| BootManager | Stage success counters (`boot.stage.*`), boot duration, readiness tally |

Logs are JSON-friendly strings written to `storage/logs/mobo.log`. Context always includes:
- `instance_id` (generated each initialization)
- `environment` (from config)
- Optional additional context via `Logger::withContext()`

---

## 5. Health Monitoring

`HealthMonitor` polls component health with configurable retries (`health.retries`, `health.retry_delay`) and thresholds (`health.failure_threshold`, `health.recovery_threshold`).

| Status | Meaning |
| --- | --- |
| `healthy` | Meets recovery threshold for consecutive passes |
| `degraded` | Recent failures but below failure threshold |
| `failed` | Consecutive failures ≥ failure threshold |

Events emitted:
- `health.status_changed` (with `name`, `old_status`, `new_status`)
- `health.failed` (with `name`, `error`)
- `health.check_complete` (system snapshot + per-component results)

Use `HealthMonitor::checkAll()` for diagnostics and `HealthMonitor::getHistory($component)` for recent status window.

---

## 6. State & Configuration Persistence

**ConfigManager & Layered Loader**
- `LayeredConfigLoader` merges `config/base`, `config/environments/<env>.php`, and `config/secrets` in order, capturing the contributing source files.
- `ConfigManager::replace()` snapshots the active configuration while `rollback()` restores the previous snapshot when validation fails.
- `ConfigSchemaValidator` enforces `config/schema.php`; violations abort boot/reload and surface via `config.reload_failed`.
- `ConfigRedactor` consumes `config/redaction.php` so logger/telemetry payloads hash usernames and mask secrets automatically.

**Hot Reload Path**
- `Kernel::reloadConfig()` reloads layers, diffs changed keys via `LayeredConfigLoader::diffKeys()`, and emits `config.reloaded { version, changed_keys }` on success.
- On failure the kernel rolls back to the last known-good config and emits `config.reload_failed { error }`.
- Logger log level is refreshed after reload to honour `logging.level` updates.

**StateManager**
- Persists to `storage/state/system.json` via atomic write + rename.
- Auto-recovers to defaults when file is missing or corrupted.
- Exposes helpers:
  - `setSystemState()`, `getSystemState()`
  - `setComponentState($name, $state)`, `getComponentState($name)`
  - `all()` for snapshots

**CacheManager**
- In-memory cache with TTL tracking (`set`, `get`, `has`, `remember`, `flush`).
- TTL expiry handled on read.
- Instrumented with debug logs for set/forget operations.

---

## 7. Reset & Testing Hooks

| API | Description |
| --- | --- |
| `Kernel::resetInstance(bool $shutdown = true)` | Clears the singleton, optionally invoking shutdown first. Use in tests/CLI when bootstrapping multiple kernels in one process. |
| `Kernel::reset()` | Instance convenience wrapper. |
| `KernelHarness` | Test helper that calls `Kernel::resetInstance()` before boot and after shutdown. |

**Testing commands**
- `composer kernel-smoke` – boots kernel with fake components (smoke test).
- `vendor/bin/phpunit` – runs full suite (30 tests covering lifecycle, event bus, health, config/state/cache, reset behaviour).
- `composer kernel-smoke-magds` – boots kernel with real MagDS integration (requires Docker Postgres on `5433`); reports MagDS health and shuts down.

Run tests after modifying kernel subsystems or events to ensure contract compliance.

---

## 7a. Security Controls & Audit Trail

**Capability Gate**
- Capabilities live under `security.capabilities` and must mirror canonical names defined in `cp_rbac.capabilities` (e.g., `kernel.component.start`, `kernel.component.stop`, `kernel.config.reload`, `kernel.audit.read`).
- `CapabilityGate::assertAllowed($capability, $actor, array $context = [])` is called by `LifecycleManager` and `Kernel::reloadConfig()` before executing sensitive operations. The gate records the acting identity (currently `system`) and contextual metadata (`component`, `request_id`, `mode`) so future control-plane wiring can populate `cp_rbac.assignments/delegations`.
- Break-glass invocation should pass `mode => 'breakglass'` so downstream emitters can create `cp_rbac.breakglass_events` and high-severity alerts.

**Audit Writer**
- `AuditWriter` appends JSON lines to `storage/logs/audit.log` that match the `cp_audit.audit_log` contract:
  ```json
  {
    "id": "uuid",
    "action": "kernel.component.start",
    "org_id": null,
    "project_id": null,
    "user_id": null,
    "payload": {"component":"MagDB","result":"success"},
    "ip_address": null,
    "immutable": true,
    "legal_hold_id": null,
    "created_at": "2025-10-18T01:23:45Z"
  }
  ```
- Actions must come from the canonical `action_catalog`; any new kernel action requires coordination with the control plane.
- Logs are append-only and subject to retention/legal-hold rules described in `cp_sec` (legal holds, purge logs, retention enforcement jobs).

**Redaction & Telemetry**
- Audit payloads flow through `ConfigRedactor`, ensuring secrets, usernames, and tokens are masked or hashed before persistence.
- Telemetry counters (`security.capability.allowed|denied`, `security.audit.write.failed`) and logger hooks allow MagMonitor to observe enforcement health.
- PHPUnit coverage under `tests/Kernel/Security` exercises capability allow/deny flows, audit redaction, and immutable-write guarantees.

---

## 8. Extending the Kernel

1. **Register new components**
   - Add entry to `config/components.php` (future work: environment layers)
   - Implement `ComponentInterface` and respect lifecycle hooks
   - Ensure health checks and telemetry counters behave appropriately

2. **Publish new events**
   - Define schema in `config/events.php`
   - Emit via `EventBus::emit($event, $payload)`
   - Document the event in the spec and, if needed, in this guide

3. **Telemetry**
   - Use `Kernel::getTelemetry()` to register custom counters/timers from new subsystems

4. **Logging**
   - Use `Logger::withContext()` to add scoped context while preserving global fields

---

## 9. MagDS Component (PostgreSQL)

| Capability | Details |
| --- | --- |
| Class | `Components\MagDB\MagDB` |
| Config source | `config/database.php` (`default`, `connections`, `pool`) |
| Lifecycle | Establishes PDO connections on `start()`, clears on `stop()` / `shutdown()` |
| Health | Executes `SELECT 1` per connection; statuses emitted through EventBus (`component.failed`, `health.*`) |
| Telemetry | Increments `magdb.connections.*`, records health latency timings, updates failure counters |

**Usage pattern**
```php
$magdb = new \Components\MagDB\MagDB();
$magdb->configure(require base_path('config/database.php'));
$magdb->boot();
$magdb->start();

$pdo = $magdb->connection(); // default connection
$pdo->query('SELECT now()');
```

- The component honours `database.default` for the primary connection and will instantiate every entry under `database.connections`.
- Persistent PDO connections are enabled by default; configure `persistent => false` to disable.
- On failure, `recover()` attempts to re-establish broken connections within the lifecycle restart budget.

---

## 10. Future Enhancements (Tracking)

| Area | Planned work |
| --- | --- |
| Config orchestration | Add CLI entrypoints for reload/validate and integrate MagVault secret backend |
| Command Bus | Formalize request/response routing for CLI + MagGate |
| Observability | Export telemetry via HTTP/gRPC for MagMonitor ingestion |
| Extension Plane | Register component adapters + capability enforcement |

Keep this guide up to date as new features land to ensure enterprise operators and contributors have an authoritative reference.

