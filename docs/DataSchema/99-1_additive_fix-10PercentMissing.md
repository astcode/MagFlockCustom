# 99-1 Additive Fix — The Missing 10%

**Version:** 1.0  
**Status:** Additive Enhancements to Existing Schema  
**Purpose:** Address the gaps not covered in documents 01, 02, 03, and 99

> This document identifies the **10% of critical functionality missing** from the current MagFlock architecture. These are **additive fixes**—no rewrites required. Each section proposes targeted schema additions, service-layer enhancements, and operational patterns that align with your existing two-plane, extension-first model.

---

## 1. Missing: Data Plane Health & Self-Healing

### 1.1 Problem
- No automated health checks for provisioned project databases
- No self-healing workflows when a data plane DB becomes unhealthy
- No circuit breaker pattern for failing connections

### 1.2 Solution: Add Health Monitoring Tables

```sql
-- Control Plane: Data Plane Health Registry
CREATE TABLE cp_obs.data_plane_health (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  project_id UUID NOT NULL REFERENCES cp_proj.projects(id) ON DELETE CASCADE,
  checked_at TIMESTAMPTZ NOT NULL DEFAULT now(),
  status VARCHAR(24) NOT NULL CHECK (status IN ('healthy','degraded','down','recovering')),
  latency_ms INTEGER,
  connection_pool_usage JSONB, -- {active: 5, idle: 10, max: 20}
  error_details TEXT,
  UNIQUE(project_id, checked_at)
);

CREATE INDEX idx_dp_health_project_time ON cp_obs.data_plane_health(project_id, checked_at DESC);

-- Self-Healing Actions Log
CREATE TABLE cp_obs.self_healing_actions (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  project_id UUID NOT NULL REFERENCES cp_proj.projects(id) ON DELETE CASCADE,
  triggered_at TIMESTAMPTZ NOT NULL DEFAULT now(),
  action_type VARCHAR(64) NOT NULL, -- 'restart_connection_pool', 'failover', 'scale_up'
  outcome VARCHAR(24) NOT NULL CHECK (outcome IN ('success','failed','in_progress')),
  details JSONB
);
```

### 1.3 Implementation Notes
- **Background Worker**: Runs every 60s, queries `mg_ctl.health_check()` on each project DB
- **Circuit Breaker**: After 3 consecutive failures, mark project as `degraded` and alert
- **Auto-Remediation**: Restart connection pool, attempt reconnect, escalate to manual intervention

### 1.4 When to Implement
- **Phase 2 (Connectivity)** — immediately after Control Plane DR is in place

---

## 2. Missing: Extension Dependency Resolution

### 2.1 Problem
- No formal dependency graph for extensions
- Can't enforce "Extension B requires Extension A to be installed first"
- No conflict detection (e.g., two extensions trying to modify the same table)

### 2.2 Solution: Add Dependency & Conflict Tables

```sql
-- Extension Dependencies
CREATE TABLE cp_ext.dependencies (
  extension_id UUID NOT NULL REFERENCES cp_ext.registry(id) ON DELETE CASCADE,
  depends_on_extension_id UUID NOT NULL REFERENCES cp_ext.registry(id) ON DELETE CASCADE,
  min_version VARCHAR(24) NOT NULL,
  max_version VARCHAR(24),
  PRIMARY KEY (extension_id, depends_on_extension_id)
);

-- Extension Conflicts
CREATE TABLE cp_ext.conflicts (
  extension_id UUID NOT NULL REFERENCES cp_ext.registry(id) ON DELETE CASCADE,
  conflicts_with_extension_id UUID NOT NULL REFERENCES cp_ext.registry(id) ON DELETE CASCADE,
  reason TEXT NOT NULL,
  PRIMARY KEY (extension_id, conflicts_with_extension_id)
);

-- Installation Validation Log
CREATE TABLE cp_ext.installation_validations (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  installation_id UUID NOT NULL REFERENCES cp_ext.installations(id) ON DELETE CASCADE,
  validated_at TIMESTAMPTZ NOT NULL DEFAULT now(),
  validation_type VARCHAR(64) NOT NULL, -- 'dependency_check', 'conflict_check', 'capability_check'
  passed BOOLEAN NOT NULL,
  details JSONB
);
```

### 2.3 Implementation Notes
- **Pre-Install Hook**: Check `dependencies` and `conflicts` before allowing installation
- **Dependency Resolver**: Topological sort to determine installation order
- **Conflict Detection**: Block installation if conflicting extension is already installed

### 2.4 When to Implement
- **Phase 4 (Pro Edition)** — when marketplace extensions become available

---

## 3. Missing: API Rate Limit Burst & Token Bucket

### 3.1 Problem
- `cp_api.rate_plans` defines limits but no burst allowance
- No token bucket algorithm for smooth rate limiting
- No per-endpoint rate limit overrides

### 3.2 Solution: Enhance Rate Plans & Add Token Bucket State

```sql
-- Add burst columns to existing rate_plans table
ALTER TABLE cp_api.rate_plans
  ADD COLUMN burst_allowance INTEGER DEFAULT 0,
  ADD COLUMN refill_rate_per_second NUMERIC(10,2) DEFAULT 1.0;

-- Token Bucket State (in-memory cache, but persisted for recovery)
CREATE TABLE cp_api.rate_limit_state (
  api_key_id UUID NOT NULL REFERENCES cp_secrets.api_keys(id) ON DELETE CASCADE,
  endpoint_pattern VARCHAR(255) NOT NULL, -- e.g., '/api/v1/users'
  tokens_remaining NUMERIC(10,2) NOT NULL,
  last_refill_at TIMESTAMPTZ NOT NULL DEFAULT now(),
  PRIMARY KEY (api_key_id, endpoint_pattern)
);

CREATE INDEX idx_rate_limit_state_key ON cp_api.rate_limit_state(api_key_id);
```

### 3.3 Implementation Notes
- **Token Bucket Algorithm**: Refill tokens at `refill_rate_per_second`, allow bursts up to `burst_allowance`
- **Per-Endpoint Overrides**: Store in `rate_limit_state` for fine-grained control
- **Cache Layer**: Use Redis for fast lookups, persist to Postgres every 60s

### 3.4 When to Implement
- **Phase 2 (Connectivity)** — when MagGate REST API goes live

---

## 4. Missing: Data Plane Migration Rollback

### 4.1 Problem
- `mg_sys.migrations_log` tracks applied migrations but no rollback mechanism
- No way to undo a failed migration without manual SQL
- No pre-migration snapshot for quick recovery

### 4.2 Solution: Add Rollback Scripts & Pre-Migration Snapshots

```sql
-- Add rollback_script to migrations_log
ALTER TABLE mg_sys.migrations_log
  ADD COLUMN rollback_script TEXT,
  ADD COLUMN pre_migration_snapshot_id UUID REFERENCES cp_bkp.snapshots(id);

-- Migration Rollback Log
CREATE TABLE mg_sys.migration_rollbacks (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  migration_log_id UUID NOT NULL REFERENCES mg_sys.migrations_log(id) ON DELETE CASCADE,
  rolled_back_at TIMESTAMPTZ NOT NULL DEFAULT now(),
  rolled_back_by UUID NOT NULL REFERENCES cp_auth.identities(id),
  reason TEXT,
  outcome VARCHAR(24) NOT NULL CHECK (outcome IN ('success','failed'))
);
```

### 4.3 Implementation Notes
- **Pre-Migration Hook**: Take lightweight snapshot (schema-only) before applying migration
- **Rollback Command**: `magcli db:rollback <project_id> --migration=<name>`
- **Audit Trail**: Log every rollback to `migration_rollbacks` and `cp_audit.audit_log`

### 4.4 When to Implement
- **Phase 1 (Core CE)** — as soon as MagCLI migration commands are built

---

## 5. Missing: Cross-Region Data Residency Enforcement

### 5.1 Problem
- `cp_org.organizations.region` exists but no enforcement mechanism
- No validation that project data stays within org's declared region
- No audit trail for cross-region data access

### 5.2 Solution: Add Region Enforcement & Audit

```sql
-- Data Residency Policy
CREATE TABLE cp_sec.data_residency_policies (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  org_id UUID NOT NULL REFERENCES cp_org.organizations(id) ON DELETE CASCADE,
  allowed_regions TEXT[] NOT NULL, -- e.g., ['us-east-1', 'us-west-2']
  enforcement_mode VARCHAR(24) NOT NULL CHECK (enforcement_mode IN ('audit_only','block')) DEFAULT 'audit_only',
  created_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

-- Cross-Region Access Log
CREATE TABLE cp_sec.cross_region_access_log (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  project_id UUID NOT NULL REFERENCES cp_proj.projects(id) ON DELETE CASCADE,
  accessed_from_region VARCHAR(64) NOT NULL,
  accessed_at TIMESTAMPTZ NOT NULL DEFAULT now(),
  identity_id UUID NOT NULL REFERENCES cp_auth.identities(id),
  action VARCHAR(64) NOT NULL,
  blocked BOOLEAN NOT NULL DEFAULT false
);

CREATE INDEX idx_cross_region_project ON cp_sec.cross_region_access_log(project_id, accessed_at DESC);
```

### 5.3 Implementation Notes
- **MagGate Middleware**: Check request origin region against `data_residency_policies`
- **Enforcement Modes**: `audit_only` logs violations, `block` rejects requests
- **Compliance Reports**: Generate monthly reports from `cross_region_access_log`

### 5.4 When to Implement
- **Phase 4 (Pro Edition)** — when multi-region deployments are supported

---

## 6. Missing: Real-Time Schema Change Notifications

### 6.1 Problem
- Schema changes (DDL) are logged in `mg_sys.ddl_log` but no real-time notifications
- Developers don't know when schema changes break their API contracts
- No webhook/event stream for schema change events

### 6.2 Solution: Add Schema Change Event Stream

```sql
-- Schema Change Subscriptions
CREATE TABLE cp_rt.schema_change_subscriptions (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  project_id UUID NOT NULL REFERENCES cp_proj.projects(id) ON DELETE CASCADE,
  subscriber_type VARCHAR(24) NOT NULL CHECK (subscriber_type IN ('webhook','channel','email')),
  subscriber_config JSONB NOT NULL, -- {url: '...', headers: {...}} or {channel_id: '...'}
  event_filters JSONB, -- {tables: ['users'], operations: ['ALTER','DROP']}
  created_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

-- Schema Change Events (published to Soketi)
CREATE TABLE cp_rt.schema_change_events (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  project_id UUID NOT NULL REFERENCES cp_proj.projects(id) ON DELETE CASCADE,
  ddl_log_id UUID NOT NULL REFERENCES mg_sys.ddl_log(id) ON DELETE CASCADE,
  published_at TIMESTAMPTZ NOT NULL DEFAULT now(),
  delivery_status JSONB -- {webhook_1: 'delivered', channel_2: 'failed'}
);
```

### 6.3 Implementation Notes
- **DDL Trigger**: After DDL is logged, publish event to Soketi channel `project.{id}.schema`
- **Webhook Delivery**: Async worker sends POST to subscribed webhooks
- **Retry Logic**: Exponential backoff for failed deliveries

### 6.4 When to Implement
- **Phase 3 (Intelligence Demos)** — when real-time features are showcased

---

## 7. Missing: API Exposure Versioning & Deprecation

### 7.1 Problem
- `cp_api.exposures` has `version` but no deprecation workflow
- No way to sunset old API versions gracefully
- No client notification when using deprecated endpoints

### 7.2 Solution: Add Deprecation & Sunset Tables

```sql
-- API Version Lifecycle
CREATE TABLE cp_api.version_lifecycle (
  exposure_id UUID NOT NULL REFERENCES cp_api.exposures(id) ON DELETE CASCADE,
  version VARCHAR(24) NOT NULL,
  status VARCHAR(24) NOT NULL CHECK (status IN ('active','deprecated','sunset')) DEFAULT 'active',
  deprecated_at TIMESTAMPTZ,
  sunset_at TIMESTAMPTZ,
  replacement_version VARCHAR(24),
  PRIMARY KEY (exposure_id, version)
);

-- Deprecated Endpoint Usage (for client migration tracking)
CREATE TABLE cp_api.deprecated_usage_log (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  exposure_id UUID NOT NULL REFERENCES cp_api.exposures(id) ON DELETE CASCADE,
  version VARCHAR(24) NOT NULL,
  api_key_id UUID NOT NULL REFERENCES cp_secrets.api_keys(id) ON DELETE CASCADE,
  last_used_at TIMESTAMPTZ NOT NULL DEFAULT now(),
  usage_count INTEGER NOT NULL DEFAULT 1,
  UNIQUE(exposure_id, version, api_key_id)
);
```

### 7.3 Implementation Notes
- **Deprecation Headers**: Return `Sunset: <date>` and `Deprecation: true` headers
- **Client Notifications**: Email API key owners when they use deprecated endpoints
- **Auto-Sunset**: After `sunset_at`, return 410 Gone with migration guide

### 7.4 When to Implement
- **Phase 2 (Connectivity)** — when API versioning is introduced

---

## 8. Missing: Extension Sandbox Resource Limits

### 8.1 Problem
- `cp_ext.resource_quotas` defines limits but no runtime enforcement
- No way to kill runaway extension processes
- No CPU/memory/disk usage tracking per extension

### 8.2 Solution: Add Runtime Resource Tracking

```sql
-- Extension Resource Usage (time-series)
CREATE TABLE cp_ext.resource_usage_log (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  installation_id UUID NOT NULL REFERENCES cp_ext.installations(id) ON DELETE CASCADE,
  measured_at TIMESTAMPTZ NOT NULL DEFAULT now(),
  cpu_percent NUMERIC(5,2),
  memory_mb INTEGER,
  disk_mb INTEGER,
  network_kb INTEGER,
  query_count INTEGER
);

-- Convert to TimescaleDB hypertable if available
SELECT create_hypertable('cp_ext.resource_usage_log', 'measured_at', if_not_exists => TRUE);

-- Resource Limit Violations
CREATE TABLE cp_ext.resource_violations (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  installation_id UUID NOT NULL REFERENCES cp_ext.installations(id) ON DELETE CASCADE,
  violated_at TIMESTAMPTZ NOT NULL DEFAULT now(),
  resource_type VARCHAR(24) NOT NULL, -- 'cpu', 'memory', 'disk', 'queries'
  limit_value NUMERIC NOT NULL,
  actual_value NUMERIC NOT NULL,
  action_taken VARCHAR(64) NOT NULL -- 'throttle', 'suspend', 'alert'
);
```

### 8.3 Implementation Notes
- **Monitoring Agent**: Collects metrics every 10s, writes to `resource_usage_log`
- **Enforcement**: When quota exceeded, throttle queries or suspend extension
- **Alerts**: Notify org admins when extension violates limits

### 8.4 When to Implement
- **Phase 4 (Pro Edition)** — when marketplace extensions are live

---

## 9. Missing: Backup Verification & Restore Testing

### 9.1 Problem
- `cp_bkp.snapshots` stores backups but no automated restore testing
- No way to verify backup integrity without manual restore
- No RTO/RPO validation

### 9.2 Solution: Add Backup Verification Jobs

```sql
-- Backup Verification Schedule
CREATE TABLE cp_bkp.verification_schedules (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  project_id UUID NOT NULL REFERENCES cp_proj.projects(id) ON DELETE CASCADE,
  frequency_cron VARCHAR(100) NOT NULL, -- e.g., '0 2 * * 0' (weekly at 2am)
  last_verified_at TIMESTAMPTZ,
  next_verification_at TIMESTAMPTZ
);

-- Verification Results
CREATE TABLE cp_bkp.verification_results (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  snapshot_id UUID NOT NULL REFERENCES cp_bkp.snapshots(id) ON DELETE CASCADE,
  verified_at TIMESTAMPTZ NOT NULL DEFAULT now(),
  outcome VARCHAR(24) NOT NULL CHECK (outcome IN ('success','failed','partial')),
  restore_time_seconds INTEGER, -- actual RTO
  data_integrity_checks JSONB, -- {row_count_match: true, checksum_match: true}
  error_details TEXT
);
```

### 9.3 Implementation Notes
- **Verification Worker**: Restores backup to temporary DB, runs integrity checks, measures RTO
- **Integrity Checks**: Compare row counts, checksums, foreign key constraints
- **Alerting**: Notify if verification fails or RTO exceeds target

### 9.4 When to Implement
- **Phase 2 (Connectivity)** — immediately after backup system is in place

---

## 10. Missing: Multi-Tenant Query Performance Isolation

### 10.1 Problem
- No query timeout enforcement per project
- No way to prevent one project's slow query from affecting others
- No query cost estimation before execution

### 10.2 Solution: Add Query Governance Tables

```sql
-- Project Query Policies
CREATE TABLE cp_proj.query_policies (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  project_id UUID NOT NULL REFERENCES cp_proj.projects(id) ON DELETE CASCADE,
  max_execution_time_ms INTEGER NOT NULL DEFAULT 30000, -- 30s default
  max_rows_returned INTEGER NOT NULL DEFAULT 10000,
  max_cost_estimate NUMERIC(10,2), -- Postgres query planner cost
  require_cost_estimate BOOLEAN NOT NULL DEFAULT false,
  created_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

-- Query Execution Log (for slow query analysis)
CREATE TABLE cp_obs.query_execution_log (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  project_id UUID NOT NULL REFERENCES cp_proj.projects(id) ON DELETE CASCADE,
  executed_at TIMESTAMPTZ NOT NULL DEFAULT now(),
  query_hash VARCHAR(64) NOT NULL, -- MD5 of normalized query
  execution_time_ms INTEGER NOT NULL,
  rows_returned INTEGER,
  cost_estimate NUMERIC(10,2),
  was_cancelled BOOLEAN NOT NULL DEFAULT false,
  cancel_reason VARCHAR(64) -- 'timeout', 'cost_exceeded', 'manual'
);

CREATE INDEX idx_query_log_project_time ON cp_obs.query_execution_log(project_id, executed_at DESC);
```

### 10.3 Implementation Notes
- **Query Interceptor**: Before executing query, check `query_policies`
- **Cost Estimation**: Use `EXPLAIN` to estimate cost, reject if exceeds limit
- **Timeout Enforcement**: Use Postgres `statement_timeout` per connection
- **Slow Query Alerts**: Notify project admins when queries exceed thresholds

### 10.4 When to Implement
- **Phase 2 (Connectivity)** — when MagGate starts serving production traffic

---

## 11. Missing: Audit Log Retention & Archival

### 11.1 Problem
- `cp_audit.audit_log` grows unbounded
- No automated archival to cold storage
- No retention policy enforcement

### 11.2 Solution: Add Audit Archival Tables

```sql
-- Audit Retention Policies
CREATE TABLE cp_audit.retention_policies (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  scope_type VARCHAR(24) NOT NULL CHECK (scope_type IN ('org','project','platform')),
  scope_id UUID,
  hot_retention_days INTEGER NOT NULL DEFAULT 90, -- keep in main table
  cold_retention_days INTEGER NOT NULL DEFAULT 2555, -- 7 years for compliance
  archive_storage_class VARCHAR(24) NOT NULL DEFAULT 's3_glacier',
  created_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

-- Archived Audit Logs (metadata only, actual data in S3/Glacier)
CREATE TABLE cp_audit.archived_logs (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  archive_date DATE NOT NULL,
  scope_type VARCHAR(24) NOT NULL,
  scope_id UUID,
  record_count BIGINT NOT NULL,
  archive_uri VARCHAR(2048) NOT NULL, -- s3://bucket/audit/2024/01/01.parquet.gz
  checksum VARCHAR(64) NOT NULL,
  archived_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE INDEX idx_archived_logs_date ON cp_audit.archived_logs(archive_date DESC);
```

### 11.3 Implementation Notes
- **Archival Worker**: Runs daily, moves logs older than `hot_retention_days` to S3/Glacier
- **Parquet Format**: Use columnar format for efficient querying in cold storage
- **Restore API**: `magcli audit:restore --date=2024-01-01` fetches from archive

### 11.4 When to Implement
- **Phase 3 (Intelligence Demos)** — when audit log volume becomes significant

---

## 12. Missing: Extension Marketplace Ratings & Reviews

### 12.1 Problem
- `cp_ext.registry` has extensions but no user feedback mechanism
- No way to discover high-quality extensions
- No trust signals for marketplace extensions

### 12.2 Solution: Add Ratings & Reviews Tables

```sql
-- Extension Ratings
CREATE TABLE cp_ext.ratings (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  extension_id UUID NOT NULL REFERENCES cp_ext.registry(id) ON DELETE CASCADE,
  org_id UUID NOT NULL REFERENCES cp_org.organizations(id) ON DELETE CASCADE,
  rating INTEGER NOT NULL CHECK (rating BETWEEN 1 AND 5),
  review_text TEXT,
  created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
  UNIQUE(extension_id, org_id)
);

-- Extension Statistics (denormalized for performance)
CREATE TABLE cp_ext.statistics (
  extension_id UUID PRIMARY KEY REFERENCES cp_ext.registry(id) ON DELETE CASCADE,
  total_installations INTEGER NOT NULL DEFAULT 0,
  active_installations INTEGER NOT NULL DEFAULT 0,
  average_rating NUMERIC(3,2),
  total_reviews INTEGER NOT NULL DEFAULT 0,
  last_updated_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

-- Materialized view for marketplace listing
CREATE MATERIALIZED VIEW cp_ext.marketplace_listing AS
SELECT 
  r.id,
  r.name,
  r.version,
  r.description,
  r.author,
  s.total_installations,
  s.active_installations,
  s.average_rating,
  s.total_reviews
FROM cp_ext.registry r
LEFT JOIN cp_ext.statistics s ON s.extension_id = r.id
WHERE r.status = 'published'
ORDER BY s.average_rating DESC NULLS LAST, s.total_installations DESC;

CREATE UNIQUE INDEX idx_marketplace_listing ON cp_ext.marketplace_listing(id);
```

### 12.3 Implementation Notes
- **Rating Submission**: Only orgs with active installations can rate
- **Statistics Refresh**: Background job updates `statistics` table every hour
- **Marketplace UI**: Filament page shows `marketplace_listing` view

### 12.4 When to Implement
- **Phase 4 (Pro Edition)** — when marketplace goes public

---

## 13. Missing: API Client SDK Generation

### 13.1 Problem
- MagGate exposes REST APIs but no client SDKs
- Developers must write HTTP clients manually
- No type-safe client libraries

### 13.2 Solution: Add SDK Generation Metadata

```sql
-- SDK Generation Configs
CREATE TABLE cp_api.sdk_configs (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  exposure_id UUID NOT NULL REFERENCES cp_api.exposures(id) ON DELETE CASCADE,
  language VARCHAR(24) NOT NULL, -- 'typescript', 'python', 'go', 'php'
  package_name VARCHAR(255) NOT NULL,
  generator_version VARCHAR(24) NOT NULL,
  custom_templates JSONB, -- override default templates
  created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
  UNIQUE(exposure_id, language)
);

-- Generated SDK Artifacts
CREATE TABLE cp_api.sdk_artifacts (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  sdk_config_id UUID NOT NULL REFERENCES cp_api.sdk_configs(id) ON DELETE CASCADE,
  generated_at TIMESTAMPTZ NOT NULL DEFAULT now(),
  artifact_uri VARCHAR(2048) NOT NULL, -- s3://bucket/sdks/project-123/typescript/v1.0.0.tar.gz
  checksum VARCHAR(64) NOT NULL,
  download_count INTEGER NOT NULL DEFAULT 0
);
```

### 13.3 Implementation Notes
- **SDK Generator**: Use OpenAPI spec + templates to generate client libraries
- **Supported Languages**: TypeScript, Python, Go, PHP (Laravel)
- **Distribution**: Host on S3, provide download links in MagUI

### 13.4 When to Implement
- **Phase 3 (Intelligence Demos)** — when showcasing developer experience

---

## 14. Missing: Disaster Recovery Runbooks

### 14.1 Problem
- No documented procedures for disaster scenarios
- No automated DR drills
- No RTO/RPO validation

### 14.2 Solution: Add DR Runbook Registry

```sql
-- DR Runbooks
CREATE TABLE cp_sec.dr_runbooks (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  scenario VARCHAR(255) NOT NULL, -- 'control_plane_failure', 'region_outage', 'data_corruption'
  runbook_uri VARCHAR(2048) NOT NULL, -- link to wiki/docs
  rto_target_minutes INTEGER NOT NULL,
  rpo_target_minutes INTEGER NOT NULL,
  last_tested_at TIMESTAMPTZ,
  test_outcome VARCHAR(24) CHECK (test_outcome IN ('success','failed','partial')),
  created_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

-- DR Drill Results
CREATE TABLE cp_sec.dr_drill_results (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  runbook_id UUID NOT NULL REFERENCES cp_sec.dr_runbooks(id) ON DELETE CASCADE,
  executed_at TIMESTAMPTZ NOT NULL DEFAULT now(),
  actual_rto_minutes INTEGER,
  actual_rpo_minutes INTEGER,
  outcome VARCHAR(24) NOT NULL CHECK (outcome IN ('success','failed','partial')),
  lessons_learned TEXT,
  corrective_actions JSONB
);
```

### 14.3 Implementation Notes
- **Quarterly Drills**: Schedule automated DR drills every 90 days
- **Runbook Automation**: Use Ansible/Terraform to execute DR procedures
- **Validation**: Compare actual RTO/RPO against targets

### 14.4 When to Implement
- **Phase 2 (Connectivity)** — as soon as production traffic starts

---

## 15. Summary: Implementation Priority Matrix

| # | Missing Component | Impact | Effort | Phase | Priority |
|---|------------------|--------|--------|-------|----------|
| 1 | Data Plane Health & Self-Healing | High | Low | 2 | **P0** |
| 2 | Extension Dependency Resolution | Medium | Low | 4 | P2 |
| 3 | API Rate Limit Burst & Token Bucket | High | Medium | 2 | **P0** |
| 4 | Data Plane Migration Rollback | High | Low | 1 | **P0** |
| 5 | Cross-Region Data Residency | Medium | Medium | 4 | P2 |
| 6 | Real-Time Schema Change Notifications | Medium | Low | 3 | P1 |
| 7 | API Exposure Versioning & Deprecation | High | Medium | 2 | **P0** |
| 8 | Extension Sandbox Resource Limits | Medium | Medium | 4 | P2 |
| 9 | Backup Verification & Restore Testing | High | Medium | 2 | **P0** |
| 10 | Multi-Tenant Query Performance Isolation | High | Medium | 2 | **P0** |
| 11 | Audit Log Retention & Archival | Medium | Low | 3 | P1 |
| 12 | Extension Marketplace Ratings & Reviews | Low | Low | 4 | P3 |
| 13 | API Client SDK Generation | Medium | High | 3 | P1 |
| 14 | Disaster Recovery Runbooks | High | Low | 2 | **P0** |

### Priority Legend
- **P0**: Critical for production readiness (Phase 2)
- **P1**: Important for user experience (Phase 3)
- **P2**: Nice-to-have for enterprise features (Phase 4)
- **P3**: Future enhancements

---

## 16. Additive DDL Summary

All proposed tables are **additive**—they do not modify existing schemas. Here's the complete list of new tables:

### Control Plane Additions
- `cp_obs.data_plane_health`
- `cp_obs.self_healing_actions`
- `cp_ext.dependencies`
- `cp_ext.conflicts`
- `cp_ext.installation_validations`
- `cp_api.rate_limit_state`
- `cp_sec.data_residency_policies`
- `cp_sec.cross_region_access_log`
- `cp_rt.schema_change_subscriptions`
- `cp_rt.schema_change_events`
- `cp_api.version_lifecycle`
- `cp_api.deprecated_usage_log`
- `cp_ext.resource_usage_log`
- `cp_ext.resource_violations`
- `cp_bkp.verification_schedules`
- `cp_bkp.verification_results`
- `cp_proj.query_policies`
- `cp_obs.query_execution_log`
- `cp_audit.retention_policies`
- `cp_audit.archived_logs`
- `cp_ext.ratings`
- `cp_ext.statistics`
- `cp_ext.marketplace_listing` (materialized view)
- `cp_api.sdk_configs`
- `cp_api.sdk_artifacts`
- `cp_sec.dr_runbooks`
- `cp_sec.dr_drill_results`

### Data Plane Additions
- `mg_sys.migration_rollbacks`

### Column Additions (Non-Breaking)
- `mg_sys.migrations_log`: `rollback_script`, `pre_migration_snapshot_id`
- `cp_api.rate_plans`: `burst_allowance`, `refill_rate_per_second`

---

## 17. Next Steps

1. **Review & Prioritize**: Identify which P0 items to implement first
2. **Generate DDL**: Create migration files for selected components
3. **Build Services**: Implement background workers and service classes
4. **Test**: Validate each component in isolation before integration
5. **Document**: Update technical docs with new capabilities

**This is the missing 10%. Once implemented, MagFlock will be production-hardened and enterprise-ready.** 🚀


