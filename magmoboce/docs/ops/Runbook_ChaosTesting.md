# Chaos & Resilience Runbook

Use this runbook to execute, validate, and document MagDS chaos scenarios. Automation runs locally against the dockerised MagDS/Postgres stack, and the framework can be pointed at staging/production once those environments are provisioned.

---

## 1. Preparation
- Ensure the kernel is booted (`php mag magds:replica-status`).
- Verify telemetry is available (`php bin/metrics-dump.php` contains `chaos_scenarios_total` once scenarios run).
- Confirm `storage/backups/` has recent snapshots before destructive tests.
- For staging/production, coordinate change window and ensure IAM has permission to restart database hosts.

---

## 2. Scenario Catalogue
| Scenario ID | Purpose | Notes |
|-------------|---------|-------|
| `db.down` | Simulates primary failure and watches auto failover. | Requires at least one replica/autopromote candidate. |
| `db.latency.500ms` | Injects 500 ms query delay and ensures latency stays within SLI. | Uses `pg_sleep`; skips if connection missing. |
| `component.crash` | Stops/starts MagDB via lifecycle manager to confirm crash recovery. | Logs and telemetry capture restart. |
| `config.invalid` | Applies invalid config override to confirm loader rejects change. | Files restored automatically post-test. |

Scenarios are extensible; add new classes under `mobo/Chaos/Scenarios/`.

---

## 3. Running Chaos Scenarios
### List available scenarios
```bash
php mag magds:chaos list
```

### Execute all scenarios with auto report
```bash
php mag magds:chaos run --report=auto
```
- Generates JSON report under `docs/ops/ChaosReports/<timestamp>.json`.
- CLI output includes status, duration, and message per scenario.

### Run specific scenarios
```bash
php mag magds:chaos run --scenario=db.down,component.crash
```

CI smoke suites should target `component.crash`, `config.invalid`, and a stubbed `db.down` heartbeat. Full suites (including `db.latency.500ms`) should run nightly or before release.
Composer shortcut for CI smoke:
```bash
composer chaos-smoke
```

---

## 4. Pass Criteria & SLIs
- **Failover recovery**: `db.down` must promote a replica in ≤30 s (see `magdb.failovers_total` and logs).
- **Latency**: `db.latency.500ms` should complete within 500 ms ±300 ms tolerance and keep `magds` healthy.
- **Crash Recovery**: `component.crash` must return the component to `running` state.
- **Config guardrail**: `config.invalid` must reject the change and keep previous config.
- Telemetry:
  - `chaos.scenarios_total{scenario,result}`
  - `chaos.scenario_duration_ms_bucket`

If any scenario fails, raise an incident, capture logs, and re-run after remediation.

---

## 5. Evidence & Reporting
- Reports saved under `docs/ops/ChaosReports/` (tracked in git).
- Add summary (date, environment, scenario results, SLI observations) to release checklist.
- For regulated tenants, export report JSON + log snippets to compliance archive.

---

## 6. Extending to Staging/Production
- Swap the scenario runner’s provider to point at managed MagDS instances.
- Ensure automation supports:
  - Network shaping (for real latency injection).
  - Controlled shutdown of primary nodes.
  - Restoration of configuration from Git-backed store or secrets manager.
- Update this runbook with environment-specific steps (access credentials, change management approvals, rollback commands) before enabling external runs.
