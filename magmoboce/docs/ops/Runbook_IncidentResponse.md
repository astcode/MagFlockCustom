# Incident Response Runbook — MagMoBoCE

Use this playbook to triage, mitigate, and close incidents surfaced by MagMoBoCE observability. All timestamps are UTC. Every action must emit an audit (`kernel.incident.*`) via the CLI or manually through the audit writer.

---

## 1. Detection & Verification

- **Alert sources:** Grafana dashboards (kernel_overview.json), Prometheus alerts (magmoboce-critical.yaml), on-call Slack channel `#mag-sentinel`.
- **Primary signals:** `component_state_changes_total{state="failed"}`, `component_restarts_total`, `magdb_health_failures_total`, `config_reload_attempts_total{result="failure"}`.
- **First validation:** Run `php bin/metrics-dump.php` on the kernel host and confirm the anomalous metric is increasing. The script streams `storage/telemetry/metrics.prom`, the payload magws-prometheus will scrape in Phase 3.
- **Cross-check logs:** Tail `storage/logs/mobo.log` for component traces and `storage/logs/audit.log` for capability-gated operations. Latest alert copies land in the on-call mailbox configured in Alertmanager (default `oncall@magmoboce.local` via Laragon SMTP). Use `php bin/alert-email-test.php` to smoke-test email delivery when validating fixes.

---

## 2. Triage Checklist

| Signal | Immediate Questions | Next Command |
| --- | --- | --- |
| `component_state_changes_total{state="failed"}` | Which component? How many restart attempts? | `php bin/mag status --component=<name>` |
| `component_restarts_total` | Restart loop? Within restart budget? | `grep <component> storage/logs/mobo.log` |
| `magdb_health_failures_total` | Single connection or cluster? Latency spikes? | `php bin/mag magdb:health --json` |
| `config_reload_attempts_total{result="failure"}` | Schema validation or capability deny? | `php bin/mag config:validate --env=<env>` |

Document every answer in the incident ticket (ServiceNow or Linear) with timestamp + actor.

---

## 3. Mitigation Playbooks

1. **Component crash / restart storm**
   - Run `php bin/mag component:restart <name>` to trigger controlled restart.
   - If restart count ≥ `recovery.max_restarts`, disable component in config and reload: update `config/environments/<env>.php`, then `php bin/mag config:reload`.
   - After stabilization, watch `component_restarts_total` for five minutes to confirm plateau.

2. **MagDB connectivity or latency**
   - Verify database host from `config/base/database.php` vs actual connection target.
   - For single connection failures: `php bin/mag magdb:reconnect --connection=<name>`.
   - For systemic latency: enable read-only mode via maintenance toggle, run `scripts/db/failover.ps1` (documented separately), then resume services.

3. **Configuration regression**
   - Inspect diff via `php bin/mag config:diff --env=<env>`.
   - If validation failing, execute `php bin/mag config:rollback` to last known-good snapshot.
   - Ensure `config_reload_attempts_total{result="failure"}` stops increasing post-fix.

4. **Security capability denials triggered by automation**
   - Review `security.capability_denied` events from event bus history.
   - Confirm capability registry in `config/base/security.php` still aligns with control-plane policy.
   - If automation is misconfigured, revoke token and rotate secrets per Runbook_AuditLogging.md.

---

## 4. Communication & Escalation

- Within 15 minutes: update the incident ticket with root signal, impact summary, ETA.
- Escalate to `@MagSentinel` SRE lead if:
  - `component_restarts_total` continues rising after two restart attempts.
  - `magdb_health_failures_total` > 5 in 10 minutes.
  - Any P1 customer impact detected.
- Add affected stakeholders to Slack bridge; keep voice/video optional but have transcript in channel.

---

## 5. Post-Incident Wrap-Up

1. Capture timeline with metric snapshots (attach `/metrics` scrape outputs to ticket).
2. File follow-up issues for missing alerts, dashboards, runbooks.
3. Update Grafana dashboard annotations with `incident_id` and resolution summary.
4. If schema or config changes contributed, ensure `docs/CurrentKernelMagDSStack_todoBeforeMagWS.md` reflects new guardrails.
5. Close with runbook verification: confirm `Runbook_IncidentResponse.md` still accurate; file PR if modifications required.

---

**Response role mapping**

- **Incident Commander:** kernel on-call engineer (rotates weekly).
- **Deputy:** MagDB owner.
- **Communications:** Product operations liaison.
- **Scribe:** First available contributor (updates timelines + artifacts).
