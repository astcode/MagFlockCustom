# Security & Access Control – DataSchema Notes

## RBAC (`cp_rbac`)
- Roles are scoped to `organization` or `project` and map to capabilities via `cp_rbac.role_capabilities`.
- `cp_rbac.capabilities` stores canonical capability strings; assignments bind an identity to a role against a specific resource (`resource_id`, `resource_type`).
- Delegations support temporary elevation with approval workflow (`approval_status`, optional expiry); Doc 02 adds `cp_rbac.approval_steps` for multi-step approvals.
- Break-glass access is captured in `cp_rbac.breakglass_events` with mandatory justification and timestamp.
- `cp_rbac.effective_capabilities` view materialises the active capability set per identity/resource; background jobs are expected to cache this view, expire delegations, and alert on break-glass usage.
- Seed roles/capabilities include `org_owner`, `security_admin`, `project_admin`, etc., with capabilities such as `project:create`, `apikey:manage`, `rbac:delegate`, `realtime:channel.manage`.

## Audit Ledger (`cp_audit`)
- `cp_audit.audit_log` is the immutable audit table (fields: `id`, `org_id`, `project_id`, `user_id`, `action`, `payload` JSONB, `ip_address`, `immutable`, `legal_hold_id`, `created_at`), with indexes on org/project/user/action/timestamp/payload.
- Legal holds link via `legal_hold_id`; records default to `immutable=true` to block updates.
- Docs reference a canonical `cp_audit.action_catalog` and drift-detection view `cp_audit.v_unknown_actions`—these registries are not yet implemented but are required to validate emitted audit actions.

## Secrets & API Keys (`cp_secrets`, `cp_api`)
- `cp_secrets.secrets_registry` tracks named secrets (types: `api_key`, `tls_cert`, `jwk`, `oauth_client`), TTLs, rotation policy metadata, and approved egress.
- API keys live in `cp_secrets.api_keys` with hashed tokens, JSONB scopes, status, expiry, and usage timestamps; `rotation_jobs` schedules automated rotation.
- `cp_api.exposures`, `rate_plans`, `usage_hyper`, and `change_history` provide contract/versioning context tied to secret enforcement.
- Guidance stresses hashing tokens, storing only prefixes, and rotating via control-plane jobs.

## Security Governance (`cp_sec`)
- Tables capture threat models (`cp_sec.threat_models`), pen-test outputs, vulnerability remediation workflow, legal holds, purge logs, retention/anonymisation schedules, downtime credits, SLA attestations, and incident root-cause records.
- Supports compliance controls (data lifecycle, legal blocks, anonymisation, SLA compensation) that security tooling must integrate with.

## Noted Gaps / Follow-ups
- Canonical registries for audit actions and capabilities (`action_catalog`, `v_unknown_actions`, `v_unknown_capabilities`) remain unimplemented per `00000_Critical_Missing_Components.md`.
- RBAC implementation still needs the decision cache/log, capability bundles, inheritance, and contextual rules outlined as missing in the same doc.
- Background job infrastructure (queues, leases, retry policies) is assumed by RBAC delegation expiry, rotation jobs, and anonymisation enforcement but no schema exists yet.
- Secrets management should include HSM/KMS integration and access logging; flagged as missing components in the audit review doc.

These points should guide the Security & Access Control work: leverage the defined schemas, ensure registries/caches/background jobs are built, and align new kernel enforcement (capability checks, audit writes, redaction) with the authoritative data model.
