# MagFlock RBAC Comprehensive Summary

## Overview
MagFlock implements a comprehensive Role-Based Access Control (RBAC) system in its enterprise-grade database architecture. The RBAC system spans both the Control Plane and integrates with all major components of the platform.

## Core RBAC Schema (cp_rbac)

### Tables

1. **roles**
   - `id` (UUID, PK): Unique identifier for the role
   - `name` (VARCHAR(120), UNIQUE): Name of the role (e.g., 'org_owner', 'project_admin')
   - `scope` (VARCHAR(24)): Either 'organization' or 'project'
   - `description` (TEXT): Description of the role

2. **capabilities**
   - `id` (UUID, PK): Unique identifier for the capability
   - `name` (VARCHAR(160), UNIQUE): Name of the capability (e.g., 'project:create', 'apikey:manage')
   - `description` (TEXT): Description of the capability

3. **role_capabilities**
   - `role_id` (UUID, PK, FK to roles.id): Reference to role
   - `capability_id` (UUID, PK, FK to capabilities.id): Reference to capability

4. **assignments**
   - `id` (UUID, PK): Unique identifier for the assignment
   - `identity_id` (UUID, FK to cp_auth.identities.id): Reference to identity
   - `role_id` (UUID, FK to roles.id): Reference to role
   - `resource_id` (UUID): Either organization or project ID
   - `resource_type` (VARCHAR(24)): Either 'organization' or 'project'
   - `created_at` (TIMESTAMPTZ): When the assignment was created

5. **delegations**
   - `id` (UUID, PK): Unique identifier for the delegation
   - `assigner_id` (UUID, FK to cp_auth.identities.id): Identity that assigned the role
   - `delegatee_id` (UUID, FK to cp_auth.identities.id): Identity that received the role
   - `role_id` (UUID, FK to roles.id): Reference to role
   - `resource_id` (UUID): Either organization or project ID
   - `resource_type` (VARCHAR(24)): Either 'organization' or 'project'
   - `approval_status` (VARCHAR(24)): 'pending', 'approved', or 'rejected'
   - `expires_at` (TIMESTAMPTZ): When the delegation expires
   - `created_at` (TIMESTAMPTZ): When the delegation was created

6. **approval_steps**
   - `id` (UUID, PK): Unique identifier for the approval step
   - `delegation_id` (UUID, FK to delegations.id): Reference to delegation
   - `approver_identity_id` (UUID, FK to cp_auth.identities.id): Identity of the approver
   - `step_index` (INT): Index of the approval step
   - `decision` (VARCHAR(16)): 'pending', 'approved', or 'rejected'
   - `decided_at` (TIMESTAMPTZ): When the decision was made

7. **breakglass_events**
   - `id` (UUID, PK): Unique identifier for the breakglass event
   - `identity_id` (UUID, FK to cp_auth.identities.id): Identity using breakglass access
   - `role_id` (UUID, FK to roles.id): Reference to role (SET NULL on delete)
   - `resource_id` (UUID): Either organization or project ID
   - `resource_type` (VARCHAR(24)): Either 'organization' or 'project'
   - `justification` (TEXT): Required justification for breakglass access
   - `activated_at` (TIMESTAMPTZ): When breakglass was activated

### Views

1. **effective_capabilities**
   - Shows the effective capabilities for each identity on each resource
   - Joins assignments, roles, role_capabilities, and capabilities tables

## Seeded Roles and Capabilities

### Roles
- `org_owner`: Owner of an organization
- `org_admin`: Administrator of an organization
- `project_admin`: Administrator of a project
- `project_developer`: Developer within a project
- `billing_admin`: Administrator for billing functions
- `security_admin`: Administrator for security functions

### Capabilities (Partial List)
- `project:create`: Ability to create projects
- `project:delete`: Ability to delete projects
- `apikey:manage`: Ability to manage API keys
- `exposure:publish`: Ability to publish exposures
- `backup:restore`: Ability to restore backups
- `quota:assign`: Ability to assign quotas
- `invoice:view`: Ability to view invoices
- `network:policy.manage`: Ability to manage network policies
- `rbac:delegate`: Ability to delegate roles
- `extension:install`: Ability to install extensions
- `realtime:channel.manage`: Ability to manage realtime channels

## RBAC Operations and Background Jobs

### Background Jobs
1. **Capability Materializer**: Periodically snapshots effective_capabilities into a denormalized cache for fast authorization decisions
2. **Delegation Expirer**: Revokes expired delegations and writes audit events
3. **Breakglass Watcher**: Pushes high-severity alerts to SecOps and links to incident tooling

### SLOs & KPIs
- Authorization decision latency p95 < 5ms (from in-memory cache)
- Error rate < 0.01%

## RBAC Integration with Other Components

### Audit Integration
- All RBAC actions are logged in the audit system
- Breakglass events create immutable audit events
- Delegation actions are tracked in the audit log

### API Integration
- API keys can have scopes that limit their capabilities
- Exposures are protected by RBAC capabilities
- Rate limiting can be applied based on roles

### Extension Integration
- Extensions have capability requirements that must be granted
- Extension installations are tracked with their granted capabilities
- Extension sandbox resource usage can be limited by RBAC

### Realtime Integration
- Realtime channels can be managed based on RBAC capabilities
- Channel auth bindings integrate with RBAC system

### Security Integration
- Cross-region access is monitored and can be enforced by RBAC policies
- Data residency policies can be enforced through RBAC

## Implementation Notes

### Domain Model
- Role → Capabilities (many-to-many) → Assignment (identity, resource, type)
- Delegation supports temporary elevation with approval status and expiry
- Breakglass provides emergency access with mandatory justification and immutable audit events

### Migration Guidance
- Deliver additive DDL first; backfill data with online jobs
- Dual-write during transitions (e.g., legacy roles → cp_rbac.assignments)
- Decommission legacy tables only after read-path flip and cool-down

## Missing Components (Identified in Documentation)

### Canonical Registries
- `cp_audit.action_catalog`: Master list of all valid audit actions
- `cp_audit.v_unknown_actions`: View to detect drift in audit actions
- `cp_rbac.v_unknown_capabilities`: View to detect drift in capabilities

### Implementation Layer
- Capability cache/materialization table (referenced but not defined)
- RBAC decision log - Track authorization decisions for debugging/audit
- Role hierarchy/inheritance - No parent-child role relationships
- Conditional capabilities - Time-based, IP-based, or context-based permissions
- Capability groups/bundles - Easier management of related capabilities

These missing components are critical for a production-ready RBAC implementation.