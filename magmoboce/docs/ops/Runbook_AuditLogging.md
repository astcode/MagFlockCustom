# Audit Logging & Security Operations Runbook

**Audience:** Platform operators, security engineering, compliance  
**Scope:** Kernel-level capability enforcement, audit trail management, credential rotation, and break-glass response aligned with the MagFlock control-plane schema (`cp_rbac`, `cp_audit`, `cp_secrets`, `cp_sec`).

---

## 1. Canonical Registries & Naming

- **Capabilities:** The kernel exposes `security.capabilities` that mirrors the canonical entries in `cp_rbac.capabilities`. Current kernel-level capabilities:
  - `kernel.component.start`
  - `kernel.component.stop`
  - `kernel.config.reload`
  - `kernel.audit.read`
- **Audit actions:** All audit entries must use names registered in `cp_audit.action_catalog`. Kernel seeds:
  - `kernel.component.start`
  - `kernel.component.stop`
  - `kernel.component.fail`
  - `kernel.config.reload.success`
  - `kernel.config.reload.failure`
  - `kernel.capability.denied`
- Any new capability or audit action requires:
  1. PR updating the control-plane registries.
  2. Matching update in `config/security.php` / kernel constants.
  3. Documentation of intended usage and monitoring hooks.

---

## 2. Capability Enforcement Workflow

1. **Identity resolution**
   - Default actor is `system`. When MagAuth integration launches, the control plane will inject the authenticated identity (UUID from `cp_auth.identities`).
   - Break-glass operations must set `mode => 'breakglass'` and include `justification` to allow creation of `cp_rbac.breakglass_events`.
2. **Gate evaluation**
   - `CapabilityGate::assertAllowed($capability, $actor, $context)`:
     - Fetches the capability definition from `security.capabilities`.
     - (Future) Queries `cp_rbac.effective_capabilities` cache for the actor/resource pair.
     - Raises `CapabilityDeniedException` and emits `kernel.capability.denied` audit event on failure.
3. **Monitoring**
   - Telemetry counters (`security.capability.allowed|denied`) feed MagMonitor dashboards.
   - Alerts should fire if `denied` spikes or if break-glass mode is triggered.

---

## 3. Audit Log Pipeline

- **Storage:** `storage/logs/audit.log` (JSON lines, append-only).  
  Format aligns with `cp_audit.audit_log`:
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
- **Immutability:** Files are append-only. Use log shipping/SIEM forwarders; never rewrite entries.
- **Redaction:** `ConfigRedactor` masks secrets/usernames (hash) before write. Verification tests live in `tests/Kernel/Security`.
- **Legal holds & purge:**
  - When a legal hold is issued (`cp_sec.legal_holds`), mark affected entries by setting `legal_hold_id`; do not rotate/delete until the hold is resolved.
  - Purge jobs (`cp_sec.purge_logs`) must run only after retention and hold requirements are met.
- **Rotation:** Archive daily/hourly depending on volume. Maintain retention schedule aligned with compliance (default 365 days; extend for regulated tenants).

---

## 4. Credential Rotation & Secrets Handling

1. **Inventory**
   - Kernel secrets follow `cp_secrets.secrets_registry`. Track key prefixes and rotation policies.
2. **Rotation trigger**
   - Use `cp_secrets.rotation_jobs` semantics: mark job `running`, execute rotation, set status `completed` or `failed`, and record `next_rotation_at`.
3. **Execution**
   - Generate new credentials (database passwords, API keys) via approved mechanism (Vault, KMS).
   - Update secret storage (`config/secrets/<env>.php` for now; transition to external store).
   - Reload kernel configuration (`Kernel::reloadConfig()`) ensuring new credentials load atomically.
4. **Verification**
   - Confirm component restart and audit entries (`kernel.config.reload.success`).
   - Update control-plane metadata (e.g., `cp_secrets.api_keys.last_used_at`, `status`).
5. **Documentation**
   - Record rotation in runbook appendix (timestamp, actor, affected components).

---

## 5. Break-Glass / Emergency Access

1. **Initiate**
   - Operator invokes critical action with `mode => 'breakglass'` and justification.
   - Capability gate logs `kernel.capability.denied` (if normal access blocked) and break-glass audit record.
2. **Audit**
   - Ensure audit entry includes justification, actor, target component, and timestamp.
   - Notify SecOps immediately; create incident ticket referencing `cp_rbac.breakglass_events`.
3. **Follow-up**
   - Post-incident review using `cp_sec.incident_root_causes`.
   - Rotate credentials if emergency access altered secrets.

---

## 6. Monitoring & Alerting

- **Metrics:** `security.capability.*`, `security.audit.*`, `config.reload.*`.
- **Alerts:**
  - Capability denials burst beyond baseline.
  - Break-glass usage.
  - Audit writer failures or log lag (no entries for >5 minutes in production).
  - Rotation job failures.
- **Dashboards:** Bridge into MagMonitor using Prometheus exporter once Section 3 is complete.

---

## 7. Runbook Checklists

### Capability Change
1. Update `config/security.php` and control-plane catalog.
2. Add tests covering allow/deny scenarios.
3. Review audit payload shape.
4. Roll forward via CI/CD; monitor for denials.

### Credential Rotation
1. Identify secret & rotation policy.
2. Generate new credential; store securely.
3. Update kernel secret layer; reload config.
4. Validate service health; audit action recorded.
5. Update rotation metadata (`cp_secrets.rotation_jobs`).

### Break-Glass Response
1. Validate justification; notify SecOps.
2. Monitor audit log and capability metrics.
3. After remediation, revoke temporary access and rotate credentials if necessary.
4. File incident report & root-cause entry (`cp_sec.incident_root_causes`).

---

## 8. References
- `docs/DataSchema/01_mag_flock_enterprise_database_schema_specification_single_source_of_truth.md`
- `docs/DataSchema/02_mag_flock_control_plane_extensions_enterprise_modules.md`
- `docs/DataSchema/03_mag_flock_governance_compliance_data_lifecycle_schema.md`
- `docs/kernel/CONTRACTS.md`
- `docs/CurrentKernelMagDSStack_todoBeforeMagWS.md` (Section 2 objectives)
