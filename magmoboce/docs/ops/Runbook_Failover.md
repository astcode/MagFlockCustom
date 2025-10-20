# MagDS Failover Runbook

Use this runbook to diagnose degraded primaries, promote replicas, and restore service. Execute all commands from the application root (`magmoboce/`) with `php mag ...`.

---

## 1. Detection & Triage
- **Signals:** `magdb.failovers_total`, `magdb.replica_health`, `magdb.replica_lag_seconds`, `magdb.replica_latency_ms`, kernel logs (`storage/logs/mobo.log`), and Alertmanager notifications.
- **Immediate actions:**
  1. Inspect current topology  
     ```bash
     php mag magds:replica-status
     ```
  2. Confirm telemetry  
     ```bash
     php bin/metrics-dump.php | rg 'magdb_replica_(health|lag_seconds|latency_ms)'
     ```
  3. Review the event stream for `magdb.failover.validation_*` notifications to confirm the latest promotion passed smoke tests.
  4. Check Postgres health on each host (e.g., `psql` or monitoring dashboards).

---

## 2. Automatic Failover
- Heartbeat and auto-promotion are triggered via:
  ```bash
  php mag magds:failover
  ```
- If the primary is unreachable and a replica with `auto_promote: true` is healthy, the command promotes it and emits `magdb.failover.completed`.
- Review `storage/logs/mobo.log` for confirmation and update the incident ticket with the new primary.

---

## 3. Manual Promotion
Use this when you must promote a specific replica or bypass health checks:

```bash
php mag magds:failover --promote=magds_replica --force
```

Parameters:
- `--promote=<name>`: Connection name defined in configuration.
- `--force`: Promote even if the candidate reports unhealthy (use only under supervision).

After promotion, re-run `php mag magds:replica-status` and ensure the new primary is marked `active=yes, healthy=yes`.

---

## 4. Fencing & Session Cleanup
- Respect `magds.failover.fencing` settings (`grace_period_seconds`, `session_timeout_seconds`). During promotion the manager quarantines and fences the failed primary, draining active sessions (`pg_terminate_backend`) where possible.
- `php mag magds:replica-status` reports `quarantined=yes` / `fenced=yes` when a node is under lockout; do not reintroduce it until root cause is resolved and quarantine clears.
- When reintegrating the former primary, update its status in the Postgres cluster, validate replication (lag returns to zero and latency normalises), then rerun `php mag magds:replica-status`.

---

## 5. Post-incident Tasks
1. Record the failover in the incident ticket (include metrics, logs, and timestamps).
2. Run the MagMigrate status to ensure schema alignment:
   ```bash
   php mag migrate:status
   ```
3. Capture a fresh backup (`php mag magds:backup run`) before returning the old primary to service.
4. Capture telemetry snapshots (`magdb_replica_lag_seconds`, `magdb_replica_latency_ms`) for incident records and ensure a `magdb.failover.validation_passed` event was emitted for the new primary.
5. Conduct a postmortem, documenting root cause and remediation actions.
