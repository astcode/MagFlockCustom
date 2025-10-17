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

... [UNCHANGED CONTENT FROM DOC 2, sections 2–11] ...

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

---

## 15. Cross‑Document Adherence
This document adheres to the **Canonical Registries** defined in the Governance & Compliance schema (Doc 3 §12):
- All capabilities referenced in RBAC, API, and extension modules must exist in `cp_rbac.capabilities`.
- All audit actions emitted by modules (e.g., `exposure.publish`, `backup.job.completed`) must be drawn from the canonical `cp_audit.action_catalog`.
- Background workers and validation queries should use the views `cp_audit.v_unknown_actions` and `cp_rbac.v_unknown_capabilities` to detect drift.

*End of document.*

