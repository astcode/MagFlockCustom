# MagFlock Control Plane Extensions — Enterprise Modules

**Version:** 1.0  
**Status:** Authoritative  
**Audience:** Platform Engineering, SRE, Security, Finance Ops

> This document specifies enterprise-grade Control Plane modules in depth: RBAC, API & Secrets, Extensions/Marketplace, Realtime, Usage/Billing, Networking/Zero Trust, Backups/Recovery, and Observability/SLOs. It complements the Single Source of Truth by providing deeper operational models, background jobs, and validation rules for each module.

---

## 0. Conventions & Guarantees
- **UUID** primary keys; **TIMESTAMPTZ** for time; **snake_case** columns.
- All tables have explicit `ON DELETE` behavior; JSONB columns are GIN-indexed when queried.
- Multi-tenant isolation enforced at service layer with org/project scoping and capability checks.
- Background workers use idempotent operations; jobs are retried with exponential backoff.

---

## 1. RBAC (`cp_rbac`) — Design & Operations

### 1.1 Domain Model
- **Role** → **Capabilities** (many-to-many) → **Assignment** (identity, resource, type)
- **Delegation** supports temporary elevation with approval status & expiry.
- **Breakglass** provides emergency access with mandatory justification and immutable audit events.

### 1.2 DDL (Superset)
```sql
-- Schemas and core tables are defined in the primary spec. The following are complementary structures.

CREATE TABLE IF NOT EXISTS cp_rbac.approval_steps (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  delegation_id UUID NOT NULL REFERENCES cp_rbac.delegations(id) ON DELETE CASCADE,
  approver_identity_id UUID NOT NULL REFERENCES cp_auth.identities(id) ON DELETE CASCADE,
  step_index INT NOT NULL,
  decision VARCHAR(16) CHECK (decision IN ('pending','approved','rejected')) DEFAULT 'pending',
  decided_at TIMESTAMPTZ,
  UNIQUE(delegation_id, step_index)
);

CREATE VIEW cp_rbac.effective_capabilities AS
SELECT a.identity_id,
       a.resource_id,
       a.resource_type,
       c.name AS capability
FROM cp_rbac.assignments a
JOIN cp_rbac.roles r ON r.id = a.role_id
JOIN cp_rbac.role_capabilities rc ON rc.role_id = r.id
JOIN cp_rbac.capabilities c ON c.id = rc.capability_id;
```

### 1.3 Background Jobs & Hooks
- **Capability Materializer**: periodically snapshots `effective_capabilities` into a denormalized cache for fast authz.
- **Delegation Expirer**: revokes expired delegations and writes an audit event.
- **Breakglass Watcher**: pushes high-severity alerts to SecOps and links to incident tooling.

### 1.4 Seed Roles & Capabilities (reference)
- Roles: `org_owner`, `org_admin`, `project_admin`, `project_developer`, `billing_admin`, `security_admin`.
- Capabilities (partial): `project:create`, `project:delete`, `apikey:manage`, `exposure:publish`, `backup:restore`, `quota:assign`, `invoice:view`, `network:policy.manage`, `rbac:delegate`, `extension:install`, `realtime:channel.manage`.

### 1.5 SLOs & KPIs
- Authz decision latency p95 < 5ms (from in-memory cache), error rate < 0.01%.

---

## 2. API, Secrets & Contracts (`cp_api`, `cp_secrets`)

### 2.1 Domain Model
- **Secrets Registry**: metadata for keys/certs; **API Keys**: per-project scoped, hashed token.
- **Exposures**: REST/GraphQL/Realtime contracts with versions and approval status.
- **Rate Plans**: quotas and limits per exposure version.
- **Usage**: time-series metering (see also `cp_usage`).

### 2.2 Additional DDL & Indexing
```sql
CREATE INDEX IF NOT EXISTS idx_exposures_project_name ON cp_api.exposures(project_id, name);
CREATE INDEX IF NOT EXISTS idx_api_usage_project_metric ON cp_api.usage_hyper(project_id, metric_type, time DESC);

CREATE TABLE IF NOT EXISTS cp_secrets.jwks_history (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  project_id UUID NOT NULL REFERENCES cp_proj.projects(id) ON DELETE CASCADE,
  kid VARCHAR(64) NOT NULL,
  jwk JSONB NOT NULL,
  valid_from TIMESTAMPTZ NOT NULL,
  valid_to TIMESTAMPTZ
);
```

### 2.3 Rotation & Revocation Workflow
1. Generate new secret → write to HSM/KMS; store **hash** & metadata in `secrets_registry`.
2. Create `rotation_jobs` with `scheduled` → worker executes → updates dependent configs.
3. Atomic swap: mark old key as `revoked`; update allowlists; broadcast cache invalidation.

### 2.4 Approval Gates for Exposures
- Draft → Approved (two-person rule for prod) → Published.
- Changes recorded in `cp_api.change_history` with diff payload.

### 2.5 Sample Queries
```sql
-- Find active API keys nearing expiry in 7 days
SELECT id, key_prefix, expires_at
FROM cp_secrets.api_keys
WHERE status='active' AND expires_at <= now() + interval '7 days';

-- Latest published exposure config
SELECT e.*, rh.diff
FROM cp_api.exposures e
LEFT JOIN LATERAL (
  SELECT diff FROM cp_api.change_history ch
  WHERE ch.exposure_id = e.id
  ORDER BY ch.timestamp DESC LIMIT 1
) rh ON TRUE
WHERE e.status='published';
```

---

## 3. Extensions & Marketplace (`cp_ext`)

### 3.1 Domain Model
- **Registry**: catalog of extensions and manifests.
- **Installations**: per-project instances with overrides.
- **Grants & Quotas**: explicit capabilities and resource limits.
- **Telemetry**: sandbox resource usage and health.
- **Rollouts**: ring-based staged deployments with pause/resume.

### 3.2 Operational DDL
```sql
CREATE INDEX IF NOT EXISTS idx_ext_installations_project ON cp_ext.installations(project_id);

CREATE TABLE IF NOT EXISTS cp_ext.sandbox_events (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  installation_id UUID NOT NULL REFERENCES cp_ext.installations(id) ON DELETE CASCADE,
  time TIMESTAMPTZ NOT NULL,
  event_type VARCHAR(64) NOT NULL,
  details JSONB
);
```

### 3.3 Lifecycle Hooks
- **Pre-Install**: static analysis of manifest; CVE check; capability request review.
- **Post-Install**: grant capabilities; create quotas; schedule health checks.
- **Upgrade**: create rollout entry (R0→R3); monitor error budget; auto-rollback on threshold.

### 3.4 KPIs
- Time to approve extension ≤ 24h; mean time to rollback < 10m once threshold tripped.

---

## 4. Realtime (`cp_rt`)

### 4.1 Domain Model
- **Channels**: pubsub/presence/stream with unique name per project.
- **Presence Policies**: visibility scope & custom logic.
- **Auth Bindings**: identities and permissions; can also bind API key scopes.
- **Replay Configs**: retention window and storage class.

### 4.2 Operational DDL
```sql
CREATE INDEX IF NOT EXISTS idx_rt_channels_project ON cp_rt.channels(project_id);

CREATE TABLE IF NOT EXISTS cp_rt.webhook_deliveries (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  channel_id UUID NOT NULL REFERENCES cp_rt.channels(id) ON DELETE CASCADE,
  status VARCHAR(24) NOT NULL CHECK (status IN ('pending','delivered','failed')),
  attempt_count INT NOT NULL DEFAULT 0,
  last_attempt_at TIMESTAMPTZ,
  payload JSONB
);
```

### 4.3 Policies & SLAs
- Channel auth decision ≤ 5ms p95; replay availability aligned to storage class.
- Backpressure: throttle publishers when lag > threshold; notify consumers via out-of-band webhook.

---

## 5. Usage, Quotas & Billing (`cp_usage`)

### 5.1 Domain Model
- **Quota Definitions**: reusable templates; **Entitlements**: assign templates to orgs.
- **Usage Aggregates**: precomputed by period; **Invoices** and **Overage Events**.

### 5.2 Pipeline
1. Ingest raw metrics into `cp_api.usage_hyper` (or streaming store).
2. Nightly aggregation job → `cp_usage.usage_aggregates`.
3. Billing cycle close → generate `cp_usage.invoices`; post to AR system; update status.

### 5.3 Additional DDL
```sql
CREATE INDEX IF NOT EXISTS idx_usage_aggregates_org_metric ON cp_usage.usage_aggregates(org_id, metric_type, period_start DESC);

CREATE TABLE IF NOT EXISTS cp_usage.rate_cards (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  name VARCHAR(120) NOT NULL,
  currency VARCHAR(8) NOT NULL DEFAULT 'USD',
  lines JSONB NOT NULL -- [{metric_type, unit_price_cents, tier_start, tier_end}]
);

CREATE TABLE IF NOT EXISTS cp_usage.invoice_lines (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  invoice_id UUID NOT NULL REFERENCES cp_usage.invoices(id) ON DELETE CASCADE,
  metric_type VARCHAR(40) NOT NULL,
  quantity BIGINT NOT NULL,
  unit_price_cents BIGINT NOT NULL,
  amount_cents BIGINT NOT NULL
);
```

### 5.4 Controls
- Guardrails on `overage_events` trigger throttling/suspension; notify owners & billing admins.
- Revenue assurance job validates invoice amounts against aggregates and rate cards.

---

## 6. Networking & Zero Trust (`cp_net`)

### 6.1 Domain Model
- **Allowlists** at org/project scope; **mTLS Policies** per project; **Device Attestation** (IoT/edge); **MQTT Credentials**.

### 6.2 Operational DDL
```sql
CREATE INDEX IF NOT EXISTS idx_net_allowlists_scope ON cp_net.allowlists(scope_type, scope_id);
CREATE INDEX IF NOT EXISTS idx_net_device_attestations_project ON cp_net.device_attestations(project_id);

CREATE TABLE IF NOT EXISTS cp_net.edge_credentials (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  project_id UUID NOT NULL REFERENCES cp_proj.projects(id) ON DELETE CASCADE,
  subject VARCHAR(255) NOT NULL, -- SPIFFE ID or similar
  cert_pem TEXT NOT NULL,
  not_before TIMESTAMPTZ NOT NULL,
  not_after TIMESTAMPTZ NOT NULL
);
```

### 6.3 Policies
- Default-deny egress; only `approved_egress` in secrets registry.
- Device creds rotate every 90 days; attestations expire if `last_seen` > 30 days.

---

## 7. Backups, PITR & Recovery (`cp_bkp`)

### 7.1 Backup Strategy
- **Full** weekly, **incremental/logical** daily; PITR windows tracked per project.
- Retention aligned with `cp_bkp.retention_policies`.

### 7.2 Additional DDL
```sql
CREATE INDEX IF NOT EXISTS idx_bkp_jobs_plan_status ON cp_bkp.backup_jobs(plan_id, status, started_at DESC);

CREATE TABLE IF NOT EXISTS cp_bkp.restore_requests (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  project_id UUID NOT NULL REFERENCES cp_proj.projects(id) ON DELETE CASCADE,
  requested_by UUID NOT NULL REFERENCES cp_auth.identities(id) ON DELETE RESTRICT,
  target_time TIMESTAMPTZ NOT NULL,
  reason TEXT,
  status VARCHAR(24) NOT NULL CHECK (status IN ('pending','approved','executing','completed','failed')) DEFAULT 'pending',
  created_at TIMESTAMPTZ NOT NULL DEFAULT now()
);
```

### 7.3 Procedures
- Two-person approval for restores into production.
- After restore, write `backup_markers` in `mg_sys` and record audit event with correlation IDs.

---

## 8. Observability & SLOs (`cp_obs`)

### 8.1 Telemetry Builder
- Declarative configs create materialized views or hypertables for metrics.

### 8.2 Additional DDL
```sql
CREATE INDEX IF NOT EXISTS idx_obs_telemetry_project ON cp_obs.telemetry_configs(project_id);

CREATE TABLE IF NOT EXISTS cp_obs.notification_channels (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  project_id UUID NOT NULL REFERENCES cp_proj.projects(id) ON DELETE CASCADE,
  kind VARCHAR(24) NOT NULL CHECK (kind IN ('email','slack','webhook','pagerduty')),
  config JSONB NOT NULL
);

CREATE TABLE IF NOT EXISTS cp_obs.alert_events (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  policy_id UUID NOT NULL REFERENCES cp_obs.alert_policies(id) ON DELETE CASCADE,
  status VARCHAR(16) NOT NULL CHECK (status IN ('open','ack','closed')),
  opened_at TIMESTAMPTZ NOT NULL DEFAULT now(),
  closed_at TIMESTAMPTZ,
  details JSONB
);
```

### 8.3 SLO Process
- Burn-rate alerts at 2h/6h windows; auto-link to incident management; generate `incident_links`.

---

## 9. Background Workers (Shared)
- **Job Runner**: executes rotations, aggregations, health checks, rollouts, and backups.
- **Scheduler**: cron-like orchestrator with jitter; stores state in dedicated jobs table (out-of-scope DDL).
- **Idempotency**: every job carries correlation IDs; retries are safe and write-once semantics enforced.

---

## 10. Security & Compliance
- **Key Handling**: tokens stored as salted hashes; short prefixes for UX only.
- **Immutability**: audit rows are append-only with legal hold linkage.
- **Data Residency**: enforced via `organizations.region` and project `region_binding`.
- **PII Controls**: masking policies in `mg_sys.security_policies` + audit of anonymization jobs.

---

## 11. Example End-to-End Flows

### 11.1 New Project Provisioning
1. Create `cp_proj.projects` row → `provisioning_state='creating'`.
2. Seed RBAC assignments for owners/admins.
3. Create default secrets (service account JWKS); write to registry.
4. Establish network baseline: default-deny + admin IP allowlist.
5. Initialize `mg_sys` tables in the new project database.
6. Flip `provisioning_state='ready'`.

### 11.2 Publish a Contract
1. Author `cp_api.exposures` v1 (draft) → review → approved.
2. Publish; create `rate_plans`; start metering into `usage_hyper`.
3. Map to DB object via `mg_sys.exposures_map`.

### 11.3 Restore & Validate
1. Open `cp_bkp.restore_requests` → approvals → execute.
2. Update `pitr_windows`; create `backup_markers`; run smoke tests.

---

## 12. Testing & Readiness Checklist
- [ ] RBAC decision cache hit rate ≥ 99%
- [ ] Secrets rotated within policy windows
- [ ] Extension upgrades rollback-tested per ring
- [ ] Realtime replay verified per storage class
- [ ] Usage aggregates reconcile to raw within ±0.1%
- [ ] mTLS enforced; invalid device fails fast
- [ ] PITR tested monthly; RTO/RPO documented
- [ ] SLO burn-rate alerts generate incidents automatically

---

## 13. Migration Guidance
- Deliver additive DDL first; backfill data with online jobs.
- Dual-write during transitions (e.g., legacy roles → `cp_rbac.assignments`).
- Decommission legacy tables only after read-path flip and cool-down.

---

## 14. Appendix — Example Seeds
```sql
-- Example: map developer role
INSERT INTO cp_rbac.roles (name, scope, description) VALUES ('project_developer','project','Can develop within project') ON CONFLICT DO NOTHING;
INSERT INTO cp_rbac.capabilities (name) VALUES
 ('exposure:publish'),('apikey:manage'),('realtime:channel.manage') ON CONFLICT DO NOTHING;
INSERT INTO cp_rbac.role_capabilities (role_id, capability_id)
SELECT r.id, c.id FROM cp_rbac.roles r CROSS JOIN cp_rbac.capabilities c
WHERE r.name='project_developer' AND c.name IN ('exposure:publish','realtime:channel.manage')
ON CONFLICT DO NOTHING;
```

*End of document.*

