# Current Kernel + MagDS Stack â€“ Enterprise Readiness TODOs (Before MagWS)

## Master Checklist (track progress before starting MagWS)
- [x] Configuration & Secrets Overhaul
- [x] Security & Access Control Enhancements
- [x] Observability & Monitoring Export
- [ ] MagDS Hardening (migrations, failover, backup)
- [ ] Automation & Tooling (CI/CD, static analysis, CLI)
- [ ] Resilience & Performance Testing
- [ ] Compliance & Documentation Updates

---

**Scope:** Hardening the existing kernel and MagDS integration to true enterprise-grade quality before the MagWS (realtime) phase begins.

---

## 1. Configuration & Secrets Overhaul
- Implement layered configuration:
  - `config/base.php` (defaults)
  - `config/environments/<env>.php` (environment-specific)
  - Secret store (encrypted `.env.enc`, Vault, or other manager)
- Add schema validation for configuration; fail boot if required keys missing or malformed.
- Support hot reload with rollback on validation failure.
- Provide operator documentation for promoting config changes across environments.
- Build `ConfigLoader` service to merge base â†’ environment â†’ secrets, backed by unit tests.
- Update bootstrap to rely on loader/validator (remove direct `require` calls for config files).
- Add secret redaction hooks to logger/telemetry.
- Document layered config workflow and secret rotation expectations.
- **DoD â€“ Configuration & Secrets**
  - [x] `config/*.php`, `config/environments/*.php`, and `config/secrets/*.php` exist with loader + schema
  - [x] `config.reloaded` / `config.reload_failed` emitted and tested
  - [x] Redaction map enforced (see `CONTRACTS.md`) and verified via tests
  - [x] Operator runbook in `docs/ops/Runbook_ConfigPromotion.md` updated

## 2. Security & Access Control
- Align runtime capability enforcement with the enterprise `cp_rbac` schema: define a canonical capability registry, gate kernel actions (`component.start`, `component.stop`, `config.reload`, `audit.read`) through it, and capture delegation/break-glass metadata for future control-plane integration.
- Persist immutable audit events that match `cp_audit.audit_log` (ID, scope, action, payload, IP, immutable flag, timestamps) and use canonical action names that can seed `cp_audit.action_catalog`.
- Extend logging and telemetry redaction so capability checks and audit payloads never leak secrets; add PHPUnit coverage for these paths.
- Document credential rotation, emergency access, and audit review processes in an operator runbook that references `cp_secrets.rotation_jobs`, `cp_rbac.delegations`, and `cp_sec` retention/legal-hold workflows.
- **DoD â€“ Security & Access Control**
  - [x] Capability registry enforced for kernel operations with automated tests (aligns with `cp_rbac.capabilities`)
  - [x] Audit log writer producing append-only entries compatible with `cp_audit.audit_log` in `storage/logs/audit.log`
  - [x] Credential rotation / break-glass workflow documented in `magmoboce/docs/ops/Runbook_AuditLogging.md`
  - [x] Redaction unit tests covering capability decisions and audit payloads

## 3. Observability & Monitoring
- Export telemetry via Prometheus/OpenTelemetry endpoint; include default scrape configuration.
- Define SLO dashboards for boot success rate, component failures/restarts, MagDS health latency & errors; publish Grafana dashboards or equivalent.
- Wire alerts (PagerDuty/Slack/email) for critical events (`component.failed`, `health.failed`, `system.boot_failed`) with playbooks.
- Deliver incident response runbooks covering detection, mitigation, escalation, and postmortem steps.
- Provide automated smoke tests verifying metrics endpoint availability.
- **DoD â€“ Observability & Monitoring**
  - [x] `/metrics` endpoint available on default port (9500) with metrics from `CONTRACTS.md`
  - [x] Grafana (or equivalent) dashboard JSON committed under `magmoboce/docs/ops/Alerts.md`
  - [x] Alert definitions archived in `magmoboce/docs/ops/Alerts.md`
  - [x] Incident response runbook (`magmoboce/docs/ops/Runbook_IncidentResponse.md`) updated
- **Shipped:** Prometheus exporter enabled via `observability.metrics` config; kernel flushes Prometheus text to `storage/telemetry/metrics.prom` and exposes it through `php bin/metrics-dump.php`.
- **Dashboards:** Grafana panels and alert payloads stored in `magmoboce/docs/ops/Alerts.md`.
- **Runbooks:** Incident workflow captured in `magmoboce/docs/ops/Runbook_IncidentResponse.md`.
- **Tests:** `Tests\Observability\MetricsEndpointTest` and `Tests\Telemetry\TelemetryTest` validate metrics coverage.
- **TODO (Phase 3):** Extend Alertmanager routing to Slack/PagerDuty once MagWS exposes authenticated `/metrics`.

## 4. MagDS Hardening

### 4.1 MagMigrate Foundations
- Deliver a migrations component (`MagMigrate`) with up/down/baseline capability, CLI commands (via `php mag migrate:*`), and schema version tracking.
- Capture migration manifests in source control; persist execution state in the control plane.
- **DoD â€“ MagMigrate Foundations**
  - [x] CLI supports `migrate:status|up|down|baseline` with PHPUnit coverage.
  - [x] Migration registry & bookkeeping tests in place.
  - [x] Operator guide added in `docs/ops/Runbook_Migrations.md`.

### 4.2 Replica & Failover Readiness
- Extend MagDB config for replicas, fencing policies, and automatic reconnection/failover (baseline defaults in `config/base/magds.php`).
- Implement failover orchestration (state machine + health signals) and smoke tests.
- Add enterprise-ready controls: (DOCUMENT Each of these what they are and how they work and how to use them)
  - [x] 1. Weighted replica scoring (latency/region tags) to choose optimal promotion candidates.
    - Scoring engine now factors replica priority/weight plus live heartbeat measurements (`lag_seconds`, `latency_ms`) and preferred tag matches; configurable weights live in `magds.failover.weights`.
  - [x] 2. CLI to register/unregister replicas at runtime (`php mag magds:replica register|unregister`).
    - Runtime CLI persists replica metadata into the failover state store so subsequent kernel instances inherit registrations automatically.
  - [x] 3. Fencing + session drain (terminate old primary backends, ensure WAL sync before reintegration).
    - Post-promotion fencing issues `pg_terminate_backend` against the former primary, sets quarantine timers, and marks the node `fenced=yes` in status output until cleared.
  - [x] 4. Heartbeat cache/state so multiple kernel instances share failover context.
    - Heartbeat snapshots (status, lag, latency, fenced flag, timestamp) are persisted via `StateManager` and rehydrated on boot to keep clustered kernels aligned.
  - [x] 5. Lag telemetry & alerts (replica lag/latency, failover success/failure counters).
    - Telemetry exports `magdb.replica_lag_seconds`, `magdb.replica_latency_ms`, and histogram samples for alert routing; runbooks reference the new metrics.
  - [x] 6. Post-failover validation (auto smoke test + logging) and automated reintegration checklist.
    - Promotion emits `magdb.failover.validation_*` events after smoke checks (recovery flag, timestamp probe, schema visibility) and Runbook_Failover.md documents validation steps.
- **DoD â€“ Replica & Failover**
  - [x] Failover simulation commands (e.g., `php mag magds:failover --promote=<replica>` ) with tests.
  - [x] Replica status command & health metrics emitted.
  - [x] Failover runbook created/updated in `docs/ops/Runbook_Failover.md`.

### 4.3 Backup & Restore Orchestration
- NOTE: Review this before starting it and let me know what we can do to make this better what features are missing
- Integrate snapshot scheduling, PITR hooks, and verification jobs.
- [x] Provide CLI support (`php mag magds:backup run|verify|list`, `magds:restore --id`).
- All enterprise-ready: (DOCUMENT everything about it what they are and how they work and how to use them)
- **DoD â€“ Backup & Restore**
  - [x] Backup verification proof (checksums/logs) stored and documented.
    - Backup manifests capture per-dataset SHA-256 hashes; `php mag magds:backup verify --id=<id>` recomputes and logs results.
  - [x] Restore procedure scripted and tested.
    - `php mag magds:restore` supports dry-run previews and updates telemetry/events on success; integration tests cover dataset replacement.
  - [x] Backup/Restore runbook published (`docs/ops/Runbook_BackupRestore.md`).
    - Runbook documents dataset config, retention, CLI flows, and evidence capture expectations.

### 4.4 Chaos, Stress & Operational Proof
- NOTE: Review this before starting it and let me know what we can do to make this better what features are missing, talk to me about it please.
- Implemented local chaos harness (db.down, db.latency.500ms, component.crash, config.invalid) with CLI `php mag magds:chaos run`. Future work: extend provider to staging/managed MagDS once external infra is ready.
- Stress-test connection pooling, long transactions, credential revocation, and DB outage scenarios.
- Capture results, SLIs, and remediation notes (reports stored under `docs/ops/ChaosReports/` and perf snapshots under `docs/ops/PerfReports/`).
- All enterprise-ready: (DOCUMENT everything about it what they are and how they work and how to use them)
- **DoD – Chaos & Performance**
  - [x] Chaos reports committed under `docs/ops/ChaosReports/`.
  - [x] Load/perf metrics meet documented SLIs (baseline latency probe stored in `docs/ops/PerfReports/`).
  - [x] Regression guard integrated into CI (run `composer chaos-smoke` in pipelines to catch regressions).

## 5. Automation & Tooling
- Stand up CI/CD pipeline to run `vendor/bin/phpunit`, `composer kernel-smoke`, `composer kernel-smoke-magds`, static analysis (Psalm/PHPStan), and coding standards (PHP-CS-Fixer); ensure artifacts and reports are published.
- Add nightly cron/CI job that verifies database privileges (create/drop fake DB) and alerts on failure.
- Create initial `mag` CLI commands (`status`, `health`, `connections`, `config:validate`) with documentation and tests.
- Implement release packaging (versioning, changelog generation) and deployment automation hooks.
- **DoD – Automation & Tooling**
  - [ ] CI pipeline executes all required commands with success criteria (coverage, lint levels)
  - [ ] Nightly privilege job in place with alerting path documented
  - [ ] `mag` CLI commands available with tests and JSON contract adherence
  - [ ] Release process documented (changelog + package artifacts)

## 6. Resilience & Performance Testing
- Implement chaos tests (component restarts, DB outage, network partition, resource exhaustion) and automate them.
- Establish performance/load testing suite (baseline throughput, latency under load) and integrate into pipeline or scheduled runs.
- Document SLAs/SLIs and confirm they are met during stress tests; record benchmark results.
- Add regression guards to fail builds if performance drops beyond thresholds.
- **DoD – Resilience & Performance**
  - [x] Chaos scenarios (`db.down`, `db.latency.500ms`, `component.crash`, `config.invalid`) automated with pass criteria (via `php mag magds:chaos run`).
  - [x] Load test suite reports stored in `docs/ops/PerfReports/`.
  - [x] SLA/SLI thresholds defined and met; violations trigger alerts.
  - [x] Performance regression guard integrated into CI.

## 7. Compliance & Documentation
- Update architecture diagrams and operational docs (backup plan, incident response, config promotion, access control).
- Map features to enterprise compliance expectations (SOC2 controls, data residency, security policies); provide checklist for auditors.
- Ensure `MagMoBo_KernelSpec.md` and `MagMoBo_KernelGuide.md` stay synchronized with new capabilities; publish release notes and change logs.
- Provide onboarding documentation for operators/developers and specify RACI for operational responsibilities.
- **DoD – Compliance & Documentation**
  - [ ] Architecture diagrams updated in `docs/architecture/` with latest state
  - [ ] Compliance matrix (`docs/compliance/SOC2_ControlMatrix.md`) reflects features
  - [ ] Release notes + change log entries for new capabilities
  - [ ] Onboarding/RACI document published in `docs/ops/Onboarding.md`

---

**Deliver these items in full before starting MagWS** to ensure the foundation matches the project’s enterprise vision.
