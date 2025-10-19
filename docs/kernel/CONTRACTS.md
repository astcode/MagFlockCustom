# Kernel & MagDS Contracts (v1)

Use this file as the machine-actionable reference for schemas, events, metrics, CLI output, and redaction rules. Keep entries short and copy-pasteable.

---

## Configuration Schema (excerpt)

```php
// docs/kernel/CONTRACTS.md – config schema v1
return [
    'kernel' => [
        'name'        => ['type' => 'string', 'required' => true],
        'version'     => ['type' => 'string', 'required' => true],
        'environment' => ['type' => 'enum', 'values' => ['development', 'staging', 'production'], 'required' => true],
    ],
    'logging' => [
        'level'       => ['type' => 'enum', 'values' => ['debug', 'info', 'warn', 'error'], 'default' => 'info'],
        'path'        => ['type' => 'string', 'default' => 'storage/logs/mobo.log'],
        'redact_keys' => ['type' => 'array<string>', 'default' => ['password', 'secret', 'token', 'apikey']],
    ],
    'database' => ['type' => 'object', 'required' => true], // Delegates to config/database.php schema
    'health'   => ['type' => 'object', 'required' => true],
    'recovery' => ['type' => 'object', 'required' => true],
    'observability' => [
        'type' => 'object',
        'required' => false,
        'children' => [
            'metrics' => [
                'type' => 'object',
                'required' => false,
                'children' => [
                    'enabled' => ['type' => 'boolean', 'default' => true],
                    'host' => ['type' => 'string', 'default' => '127.0.0.1'],
                    'port' => ['type' => 'integer', 'default' => 9500],
                    'path' => ['type' => 'string', 'default' => '/metrics'],
                    'file' => ['type' => 'string', 'default' => 'storage/telemetry/metrics.prom'],
                    'export_interval' => ['type' => 'number', 'default' => 1.0],
                ],
            ],
        ],
    ],
    'magds' => [
        'type' => 'object',
        'required' => false,
        'children' => [
            'primary' => [
                'type' => 'object',
                'required' => true,
                'children' => [
                    'connection' => ['type' => 'string', 'required' => true],
                ],
            ],
            'replicas' => [
                'type' => 'array',
                'required' => false,
                'items' => [
                    'type' => 'object',
                    'children' => [
                        'connection' => ['type' => 'string', 'required' => true],
                        'priority' => ['type' => 'integer', 'required' => false],
                        'read_only' => ['type' => 'boolean', 'required' => false],
                        'auto_promote' => ['type' => 'boolean', 'required' => false],
                    ],
                ],
            ],
            'failover' => [
                'type' => 'object',
                'required' => false,
                'children' => [
                    'enabled' => ['type' => 'boolean', 'required' => false],
                    'failure_threshold' => ['type' => 'integer', 'required' => false],
                    'heartbeat_interval_seconds' => ['type' => 'integer', 'required' => false],
                    'retry_interval_seconds' => ['type' => 'integer', 'required' => false],
                    'max_retries' => ['type' => 'integer', 'required' => false],
                    'quarantine_seconds' => ['type' => 'integer', 'required' => false],
                    'cooldown_seconds' => ['type' => 'integer', 'required' => false],
                ],
            ],
            'fencing' => [
                'type' => 'object',
                'required' => false,
                'children' => [
                    'grace_period_seconds' => ['type' => 'integer', 'required' => false],
                    'session_timeout_seconds' => ['type' => 'integer', 'required' => false],
                ],
            ],
            'health' => [
                'type' => 'object',
                'required' => false,
                'children' => [
                    'read_timeout_seconds' => ['type' => 'integer', 'required' => false],
                    'write_timeout_seconds' => ['type' => 'integer', 'required' => false],
                ],
            ],
        ],
    ],
];
```

### Redaction Map
```
kernel.logging.redact_keys
database.connections.*.password
database.connections.*.username (log as hash only)
services.*.secret
services.*.key
```

Hot reload emits:
- `config.reloaded { version:string, changed_keys:string[] }`
- `config.reload_failed { error:string }`

Rollback rule: on validation failure or downstream exception, revert to last known-good config and emit `config.reload_failed`.

---

## Events (name → required fields)

```
system.boot                { instance_id:string, environment:string }
system.boot_failed         { stage:string, error:string, trace_id:string }
system.ready               { instance_id:string, environment:string }
system.shutdown            { timeout:int }

component.registered       { name:string }
component.state_changed    { name:string, old_state:string, new_state:string }
component.started          { name:string }
component.stopped          { name:string }
component.failed           { name:string, error:string }
component.recovery_failed  { name:string, restart_count:int }

health.status_changed      { name:string, old_status:string, new_status:string }
health.failed              { name:string, error:string }
health.check_complete      { system:object, components:object }

config.reloaded            { version:string, changed_keys:string[] }
config.reload_failed       { error:string }
security.capability_denied { capability:string, actor:string, context:object }
```

---

## Metrics (name • type • labels • description)

```
kernel.boot.time_ms              • gauge      • { }                 • Time spent booting kernel
kernel.uptime.seconds            • counter    • { }                 • Total uptime in seconds
eventbus.events_total            • counter    • { event }           • Count of emitted events
eventbus.handler_duration_ms     • histogram  • { event }           • Handler execution time
component.restarts_total         • counter    • { component }       • Lifecycle restarts
component.state_changes_total    • counter    • { component,state } • State transitions
magdb.connections.opened_total   • counter    • { name }            • PDO connections opened
magdb.connections.closed_total   • counter    • { name }            • PDO connections closed
magdb.health_latency_ms          • histogram  • { name }            • Health check latency
magdb.health_failures_total      • counter    • { name }            • Health check failures
magdb.replica_health             • gauge      • { name }            • Replica health (1 healthy, 0 unhealthy)
magdb.failovers_total            • counter    • { reason }          • Failover promotions grouped by reason
config.reload_attempts_total     • counter    • { result }          • Reload attempts labelled success|failure
```

> Exporter normalises metric identifiers to Prometheus-safe names by replacing `.` with `_` (example: `kernel.boot.time_ms` -> `kernel_boot_time_ms`).

Access patterns:
- Kernel writes Prometheus text to `storage/telemetry/metrics.prom` (path configurable via `observability.metrics.file`).
- `php bin/metrics-dump.php` streams the payload for operators/tests. MagWS Phase 3 will mount the same file or route it publicly.

---

## CLI Output Contract (JSON)

```
Command: mag kernel:status
Input flags: --json (default) | --pretty
Exit codes: 0 success, 1 error
Output JSON:
{
  "command": "kernel:status",
  "instance_id": "abc123",
  "state": "running",
  "environment": "staging",
  "uptime_seconds": 842,
  "components": [
    { "name": "MagDB", "state": "running", "restarts": 0 }
  ]
}
```

Additional commands to align with Automation & Tooling checklist will follow the same pattern (`command`, metadata fields, structured payload).

Command family: `php mag migrate:*`

```
php mag migrate:status [--component=magds] [--connection=magdsdb]
php mag migrate:up [--component=magds] [--connection=magdsdb] [--target=<id>]
php mag migrate:down [--component=magds] [--connection=magdsdb] [--steps=1] [--target=<id>]
php mag migrate:baseline --target=<id> [--component=magds] [--connection=magdsdb]
```

- Migrations are loaded from `migrations/<component>` and tracked in `schema_migrations`.
- Commands exit `0` on success, `1` on validation or database failure.
- CLI honours `.env` via `bootstrap.php`; override component/connection with flags as needed.

MagDS failover commands:

```
php mag magds:replica-status
php mag magds:failover [--promote=<connection>] [--force]
```

- `magds:replica-status` reports configured primary, current primary, and replica health.
- `magds:failover` runs a heartbeat with auto promotion or forces promotion to a specific replica.
- Failover events emit `magdb.failover.*` and update telemetry (`magdb.failovers_total`, `magdb.replica_health`).

---

## Chaos Scenario IDs (for resilience testing)

```
db.down            → shut down primary DB; expected: kernel triggers failover/backoff, emits component.failed
db.latency.500ms   → introduce 500ms latency; expected: health status degraded, alert fires if threshold exceeded
component.crash    → force component->start() throw; expected: lifecycle recover within restart budget
config.invalid     → push invalid config; expected: config.reload_failed, rollback, no crash
```

Each scenario must emit an audit log entry and be recorded in test results.

---

## Audit Log Shape

Stored at `storage/logs/audit.log` (JSON lines). Example:
```json
{
  "id": "0f8fad5b-d9cb-469f-a165-70867728950e",
  "action": "kernel.component.start",
  "org_id": null,
  "project_id": null,
  "user_id": null,
  "payload": { "component": "MagDB", "result": "success" },
  "ip_address": null,
  "immutable": true,
  "legal_hold_id": null,
  "created_at": "2025-10-17T20:31:00.123Z"
}
```

Retention: 90 days (configurable). Clock source: `DateTimeImmutable` in UTC.

---

Keep this file updated whenever interfaces change. Agents should treat these contracts as source of truth.
