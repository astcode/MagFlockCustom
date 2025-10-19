# MagFlock Extension System — Technical Specification

**Version:** 1.0\
**Date:** 2025-10-05\
**Status:** Foundation Architecture\
**Purpose:** Complete technical specification for MagFlock's extension-ready architecture

---

## Table of Contents

1. [Executive Summary](#executive-summary)

2. [Architecture Overview](#architecture-overview)

3. [Extension Kernel](#extension-kernel)

4. [Component Adapter System](#component-adapter-system)

5. [Hook System Architecture](#hook-system-architecture)

6. [Capability System](#capability-system)

7. [Event Bus Contract](#event-bus-contract)

8. [Extension Lifecycle Management](#extension-lifecycle-management)

9. [Sandboxing & Security](#sandboxing--security)

10. [Extension Manifest Specification](#extension-manifest-specification)

11. [Component-Specific Integration](#component-specific-integration)

12. [Pro Edition Strategy](#pro-edition-strategy)

13. [Development Constitution](#development-constitution)

14. [Performance & Monitoring](#performance--monitoring)

15. [Migration & Compatibility](#migration--compatibility)

---

## Executive Summary

### Vision

MagFlock Community Edition (CE) is built as an **extension-ready platform from day one**. The Extension System is not an afterthought—it's a core architectural principle that enables:

* **Seamless CE → Pro upgrade** (Pro is just extension bundles)

* **Third-party marketplace** (developers can extend MagFlock)

* **Industry-specific solutions** (healthcare, IoT, finance modules)

* **AI-native extensibility** (extensions can be AI agents)

### Core Principle

**"Extension Kernel as Mediator, Not God Object"**

The Extension Kernel is a **thin mediation layer** that:

* ✅ Registers extensions and their capabilities

* ✅ Enforces security policies (least privilege, sandboxing)

* ✅ Routes events between components and extensions

* ✅ Manages lifecycle (install, enable, upgrade, disable, uninstall)

* ❌ Does NOT contain business logic

* ❌ Does NOT directly execute extension code

* ❌ Does NOT become a monolithic dependency

### Architecture Philosophy

```
Normal Request Flow:
Client → Control Plane → MagMoBo Components → Data Plane
(Extensions do NOT intercept normal requests)

Extension Flow (Out-of-Band):
Extension → Extension Kernel → Component Adapter → MagMoBo Component
(Mediated, sandboxed, audited)
```

---

## Architecture Overview

### Four-Plane Architecture

```
┌─────────────────────────────────────────────────────────────────────┐
│                         CLIENT PLANE                                │
│  Apps • SDKs • BI Tools • IoT Devices • AI Agents • Mobile Apps     │
└────────────────────────────┬────────────────────────────────────────┘
                             │
                             ▼
┌─────────────────────────────────────────────────────────────────────┐
│                       CONTROL PLANE                                 │
│  ┌──────────┐  ┌──────────┐  ┌──────────┐  ┌──────────┐            │
│  │  MagUI   │  │ MagGate  │  │  MagWS   │  │ MagCLI   │            │
│  │ (Admin)  │  │  (API)   │  │(Realtime)│  │  (CLI)   │            │
│  └────┬─────┘  └────┬─────┘  └────┬─────┘  └────┬─────┘            │
│       │             │             │             │                   │
│       └─────────────┼─────────────┼─────────────┘                   │
└─────────────────────┼─────────────┼─────────────────────────────────┘
                      │             │
                      ▼             ▼
│         ┌──────────────────────────────────────────┐               │
│         │       Protection Layer                    │               │
│         │  ┌──────────────────┐  ┌──────────────────┐          │               │
│         │  │  MagPuma         │  │   MagSentinal   │          │               │
│         │  │  (he intelligent gatekeeper      │  │  Full Mesh Gatekeeper      │          │               │
│         │  │   deployment patterns,
                  privacy/compliance
                  testing/validation
                  .......
                        │  │(Capability)      │          │               │
│         └──────────────────────────────────────────┘               │



┌─────────────────────────────────────────────────────────────────────┐
│                         DATA PLANE                                  │
│                        MagMoBo Core                                 │
│  ┌──────────┐  ┌──────────┐  ┌──────────┐  ┌──────────┐            │
│  │ Storage  │  │   CPU    │  │   RAM    │  │   GPU    │            │
│  │ (MagDS)  │  │ (Compute)│  │ (Cache)  │  │  (AI)    │            │
│  └──────────┘  └──────────┘  └──────────┘  └──────────┘            │
│  ┌──────────┐  ┌──────────┐  ┌──────────┐  ┌──────────┐            │
│  │   PSU    │  │   BIOS   │  │   PCIe   │  │  Periph  │            │
│  │ (Auth)   │  │ (Boot)   │  │  (Bus)   │  │ (Extend) │            │
│  └──────────┘  └──────────┘  └──────────┘  └──────────┘            │
└─────────────────────────────────────────────────────────────────────┘
                             ▲
                             │ (out-of-band mediation)
                             │
┌─────────────────────────────────────────────────────────────────────┐
│                  EXTENSION MEDIATION PLANE                          │
│                                                                     │
│  ┌────────────────────────────────────────────────────────────┐    │
│  │              Component Adapters (Thin Shims)               │    │
│  │  ┌──────┐  ┌──────┐  ┌──────┐  ┌──────┐  ┌──────┐         │    │
│  │  │ MagUI│  │MagGate│ │ MagWS│  │MagCLI│  │MagAuth│         │    │
│  │  │Adapter│ │Adapter│ │Adapter│ │Adapter│ │Adapter│         │    │
│  │  └───┬──┘  └───┬──┘  └───┬──┘  └───┬──┘  └───┬──┘         │    │
│  │      └──────────┼─────────┼─────────┼─────────┘            │    │
│  └─────────────────┼─────────┼─────────┼──────────────────────┘    │
│                    │         │         │                           │
│                    ▼         ▼         ▼                           │
│         ┌──────────────────────────────────────────┐               │
│         │       EXTENSION KERNEL (Core)            │               │
│         │  ┌────────────┐  ┌────────────┐          │               │
│         │  │  Registry  │  │   Policy   │          │               │
│         │  │  (Manifests│  │  Gatekeeper│          │               │
│         │  │   & Hooks) │  │(Capability)│          │               │
│         │  └────────────┘  └────────────┘          │               │
│         │  ┌────────────┐  ┌────────────┐          │               │
│         │  │ Event Bus  │  │ Lifecycle  │          │               │
│         │  │ (Pub/Sub)  │  │  Manager   │          │               │
│         │  └────────────┘  └────────────┘          │               │
│         │  ┌────────────┐  ┌────────────┐          │               │
│         │  │  Sandbox   │  │   Audit    │          │               │
│         │  │  Enforcer  │  │   Logger   │          │               │
│         │  └────────────┘  └────────────┘          │               │
│         └──────────────────────────────────────────┘               │
│                             │                                       │
│  ┌────────────────────────────────────────────────────────────┐    │
│  │              Component Adapters (Thin Shims)               │    │
│  │  ┌──────┐  ┌──────┐  ┌──────┐  ┌──────┐  ┌──────┐         │    │
│  │  │ MagDS│  │ MagRAG│ │MagMQTT│ │MagBIOS│ │MagPCIe│        │    │
│  │  │Adapter│ │Adapter│ │Adapter│ │Adapter│ │Adapter│         │    │
│  │  └──────┘  └──────┘  └──────┘  └──────┘  └──────┘         │    │
│  └────────────────────────────────────────────────────────────┘    │
│                             │                                       │
└─────────────────────────────┼───────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────────────┐
│                         EXTENSIONS                                  │
│  ┌──────────────────┐  ┌──────────────────┐  ┌─────────────────┐   │
│  │  First-Party CE  │  │    Pro Pack      │  │  Third-Party    │   │
│  │  • MagRAG        │  │  • MultiTenant   │  │  • Marketplace  │   │
│  │  • MagMQTT       │  │  • Billing       │  │  • Community    │   │
│  │  • MagAnalytics  │  │  • Enterprise    │  │  • Industry     │   │
│  │                  │  │    Auth (SSO)    │  │    Solutions    │   │
│  │                  │  │  • Collaboration │  │                 │   │
│  └──────────────────┘  └──────────────────┘  └─────────────────┘   │
└─────────────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────────────┐
│                       SECURITY PLANE                                │
│                      MagSentinel (AI Security Mesh)                 │
│  Patrol Agents • Threat Analyzer • Incident Commander              │
└─────────────────────────────────────────────────────────────────────┘
```

### Key Architectural Principles

1. **Separation of Concerns**

  * Normal requests flow directly: Client → Control → Data

  * Extension requests flow through mediation: Extension → Kernel → Adapter → Component

  * Extensions never intercept normal traffic (no performance penalty)

2. **Out-of-Band Mediation**

  * Extension Kernel operates separately from request path

  * Components expose hooks via adapters

  * Kernel routes extension calls to appropriate adapters

3. **Thin Adapters**

  * Adapters are translation layers only (no business logic)

  * Each component has one adapter

  * Adapters implement standard interface

4. **Slim Kernel**

  * Kernel is registry + policy + events + lifecycle

  * Kernel does NOT execute extension code directly

  * Kernel delegates to adapters for component-specific operations

5. **Security by Default**

  * All extension code runs sandboxed

  * Capabilities declared and enforced

  * All actions audited

  * Health checks continuous

---

## Extension Kernel

### Core Responsibilities

The Extension Kernel is the **central mediator** for all extension interactions. It has six core responsibilities:

#### 1\. Registry

**Purpose:** Catalog all installed extensions, their manifests, and available hooks.

**Data Structures:**

```
ExtensionRegistry:
├─ extensions: Map<ExtensionID, ExtensionMetadata>
├─ hooks: Map<HookName, List<ExtensionID>>
├─ capabilities: Map<ExtensionID, Set<Capability>>
├─ dependencies: Map<ExtensionID, List<ExtensionID>>
└─ versions: Map<ExtensionID, VersionInfo>

ExtensionMetadata:
├─ id: string (e.g., "magflock/magrag")
├─ name: string
├─ version: semver
├─ author: string
├─ license: string
├─ manifest_hash: string (integrity check)
├─ installed_at: timestamp
├─ enabled: boolean
├─ health_status: enum (healthy, degraded, unhealthy)
└─ last_health_check: timestamp
```

**Operations:**

* `Register(extension)` - Add extension to registry

* `Unregister(extension_id)` - Remove extension from registry

* `GetExtension(extension_id)` - Retrieve extension metadata

* `ListExtensions(filter)` - Query extensions by status, capability, etc.

* `GetHookSubscribers(hook_name)` - Find all extensions listening to a hook

#### 2\. Policy Gatekeeper

**Purpose:** Enforce capability-based access control with least privilege.

**Policy Engine:**

```
PolicyGatekeeper:
├─ capability_definitions: Map<Capability, CapabilitySpec>
├─ extension_grants: Map<ExtensionID, Set<Capability>>
├─ runtime_policies: List<Policy>
└─ violation_handlers: Map<ViolationType, Handler>

CapabilitySpec:
├─ name: string (e.g., "database.write")
├─ description: string
├─ risk_level: enum (low, medium, high, critical)
├─ requires_approval: boolean
├─ rate_limit: optional<RateLimit>
└─ audit_level: enum (none, basic, detailed)
```

**Enforcement Flow:**

```
1. Extension calls kernel.ExecuteHook("database.query", context)
2. Kernel checks: Does extension have "database.read" capability?
3. If NO → Deny + Audit + Alert
4. If YES → Check rate limit
5. If rate limit exceeded → Deny + Audit
6. If OK → Proceed to adapter
7. After execution → Audit log entry
```

**Policy Types:**

* **Capability Policies:** What extensions can do

* **Rate Limit Policies:** How often extensions can act

* **Resource Policies:** CPU/memory/network budgets

* **Time Policies:** When extensions can run (e.g., no background jobs during peak hours)

* **Data Policies:** What data extensions can access (row-level security)

#### 3\. Event Bus

**Purpose:** Publish/subscribe event system for component ↔ extension communication.

**Event Bus Architecture:**

```
EventBus:
├─ topics: Map<TopicName, Topic>
├─ subscriptions: Map<ExtensionID, List<Subscription>>
├─ event_queue: PriorityQueue<Event>
├─ event_history: RingBuffer<Event> (7 days retention)
└─ dead_letter_queue: Queue<FailedEvent>

Topic:
├─ name: string (e.g., "database.query.executed")
├─ schema: JSONSchema
├─ required_capability: Capability
├─ retention_policy: Duration
└─ delivery_guarantee: enum (at_least_once, at_most_once)

Subscription:
├─ extension_id: ExtensionID
├─ topic_pattern: string (supports wildcards, e.g., "database.*")
├─ filter: optional<EventFilter>
├─ handler: function
└─ priority: int (0-100)
```

**Event Flow:**

```
1. Component emits event via adapter
2. Adapter calls kernel.PublishEvent(event)
3. Kernel validates event schema
4. Kernel finds all subscriptions matching topic
5. For each subscription:
   a. Check extension has required capability
   b. Check extension is enabled and healthy
   c. Apply filter (if any)
   d. Add to event queue with priority
6. Event dispatcher processes queue
7. For each event:
   a. Call extension handler (sandboxed)
   b. If handler fails → retry (3 attempts)
   c. If still fails → dead letter queue
   d. Audit log entry
```

**Event Guarantees:**

* **At-least-once delivery:** Extensions may receive duplicates (must be idempotent)

* **Ordered within topic:** Events on same topic are ordered

* **No cross-topic ordering:** Events from different topics may arrive out of order

* **Retention:** 7 days (can be replayed for debugging)

* **Backpressure:** If extension is slow, events are queued (up to limit, then dropped)

#### 4\. Lifecycle Manager

**Purpose:** Manage extension installation, enablement, upgrades, and removal.

**Lifecycle States:**

```
Extension States:
├─ NOT_INSTALLED
├─ DOWNLOADING
├─ VERIFYING
├─ INSTALLING
├─ INSTALLED (but disabled)
├─ ENABLING
├─ ENABLED (running)
├─ DEGRADED (running but unhealthy)
├─ DISABLING
├─ DISABLED
├─ UPGRADING
├─ UNINSTALLING
└─ ERROR

State Transitions:
NOT_INSTALLED → DOWNLOADING → VERIFYING → INSTALLING → INSTALLED
INSTALLED → ENABLING → ENABLED
ENABLED → DISABLING → DISABLED
ENABLED → DEGRADED (automatic on health check failure)
DEGRADED → ENABLED (automatic on health recovery)
DEGRADED → DISABLED (automatic after 3 consecutive failures)
INSTALLED → UPGRADING → INSTALLED (new version)
DISABLED → UNINSTALLING → NOT_INSTALLED
```

**Lifecycle Operations:**

**Install:**

```
Install(extension_package):
1. Download package from registry
2. Verify signature (code signing with public key)
3. Extract package to temp directory
4. Parse manifest.json
5. Validate manifest schema
6. Check compatibility:
   - MagMoBo version >= min_version
   - All dependencies installed
   - No conflicting extensions
7. Display capabilities to user
8. User approves capabilities
9. Run extension.Install() method (sandboxed)
10. Run database migrations (if any)
11. Copy files to extensions directory
12. Register with kernel
13. Health check #1: Extension responds to ping
14. If healthy → Mark as INSTALLED
15. If unhealthy → Rollback (undo migrations, remove files)
16. Audit log entry
```

**Enable:**

```
Enable(extension_id):
1. Check extension is INSTALLED
2. Load configuration from config file
3. Validate configuration schema
4. Run extension.Init() method (sandboxed)
5. Register hooks with component adapters
6. Subscribe to events
7. Run extension.Start() method (sandboxed)
8. Health check #2: Extension handles test event
9. If healthy → Mark as ENABLED
10. If unhealthy → Disable + Alert admin
11. Audit log entry
```

**Upgrade:**

```
Upgrade(extension_id, new_version):
1. Check extension is INSTALLED or ENABLED
2. Download new version package
3. Verify signature
4. Parse new manifest
5. Check compatibility
6. If extension is ENABLED:
   a. Keep old version running (serve requests)
   b. Install new version in parallel (separate directory)
   c. Run database migrations (new version)
   d. Health check #3: New version responds
   e. If healthy → Switch traffic to new version (atomic swap)
   f. If unhealthy → Rollback to old version (zero downtime)
   g. After 24 hours → Remove old version files
7. If extension is DISABLED:
   a. Uninstall old version
   b. Install new version
8. Audit log entry
```

**Disable:**

```
Disable(extension_id):
1. Check extension is ENABLED or DEGRADED
2. Run extension.Stop() method (sandboxed, 30 second timeout)
3. Unregister hooks from component adapters
4. Unsubscribe from events
5. Mark as DISABLED
6. Extension files remain (can re-enable quickly)
7. Audit log entry
```

**Uninstall:**

```
Uninstall(extension_id):
1. Check extension is DISABLED
2. Run extension.Uninstall() method (cleanup, sandboxed)
3. Rollback database migrations (if safe)
   - If migrations are destructive → Warn user, require confirmation
4. Remove files from extensions directory
5. Unregister from kernel
6. Remove configuration
7. Audit log entry
```

#### 5\. Sandbox Enforcer

**Purpose:** Isolate extension code to prevent security breaches.

**Sandboxing Techniques:**

**Process Isolation:**

```
Each extension runs in separate process:
├─ Dedicated process per extension
├─ Limited CPU quota (e.g., 10% of one core)
├─ Limited memory quota (e.g., 256 MB)
├─ No access to parent process memory
└─ Killed if exceeds quota
```

**Filesystem Isolation:**

```
Extension filesystem access:
├─ Read-only access to own directory
├─ Read/write access to temp directory (auto-cleaned)
├─ No access to other extensions' directories
├─ No access to MagMoBo core directories
└─ Enforced via chroot or containerization
```

**Network Isolation:**

```
Extension network access:
├─ Outbound HTTP/HTTPS only if "network.outbound" capability
├─ Inbound webhooks only if "network.inbound" capability
├─ No direct database connections (must go through kernel)
├─ No access to internal MagMoBo services
└─ Firewall rules enforced per extension
```

**API Isolation:**

```
Extension API access:
├─ Extensions call kernel API only (no direct component access)
├─ Kernel validates every call against capabilities
├─ Kernel rate-limits calls per extension
├─ Kernel audits all calls
└─ Kernel can revoke access at runtime
```

**Language-Specific Sandboxing:**

```
PHP Extensions:
├─ Run in separate PHP-FPM pool
├─ disable_functions enforced (exec, shell_exec, system, etc.)
├─ open_basedir enforced (filesystem restriction)
└─ memory_limit enforced

JavaScript Extensions (Node.js):
├─ Run in VM2 sandbox
├─ No access to require() (except whitelisted modules)
├─ No access to process, fs, net (unless capability granted)
└─ Timeout enforced (30 seconds per call)

Python Extensions:
├─ Run in RestrictedPython sandbox
├─ No access to __import__ (except whitelisted modules)
├─ No access to open, exec, eval
└─ Resource limits enforced via cgroups
```

**Escape Detection:**

```
Sandbox escape attempts trigger:
├─ Immediate extension termination
├─ Automatic disable
├─ Alert to admin
├─ Audit log entry (critical severity)
├─ Report to MagFlock security team (if opted in)
└─ Extension blacklisted (cannot be re-enabled without review)
```

#### 6\. Audit Logger

**Purpose:** Record all extension activities for security, compliance, and debugging.

**Audit Log Schema:**

```json
{
  "log_id": "uuid",
  "timestamp": "2025-10-05T10:23:45.123Z",
  "severity": "info|warning|error|critical",
  "category": "lifecycle|capability|event|performance|security",
  "extension_id": "magflock/magrag",
  "extension_version": "1.2.3",
  "action": "hook.executed",
  "details": {
    "hook_name": "database.query",
    "capability_used": "database.read",
    "duration_ms": 12,
    "result": "success"
  },
  "context": {
    "project_id": "proj_abc123",
    "user_id": "user_xyz789",
    "trace_id": "trace_123",
    "ip_address": "192.168.1.100"
  },
  "metadata": {
    "cpu_usage_percent": 5.2,
    "memory_usage_mb": 128,
    "network_bytes_sent": 1024,
    "network_bytes_received": 2048
  }
}
```

**Audit Categories:**

**Lifecycle Events:**

```
- extension.installed
- extension.enabled
- extension.disabled
- extension.upgraded
- extension.uninstalled
- extension.health_check.passed
- extension.health_check.failed
```

**Capability Events:**

```
- capability.granted
- capability.revoked
- capability.denied (attempted use without grant)
- capability.rate_limit_exceeded
```

**Hook Events:**

```
- hook.registered
- hook.executed
- hook.failed
- hook.timeout
```

**Event Bus Events:**

```
- event.published
- event.delivered
- event.failed
- event.dead_letter
```

**Security Events:**

```
- sandbox.escape_attempt
- capability.violation
- resource.quota_exceeded
- network.unauthorized_access
- filesystem.unauthorized_access
```

**Audit Retention:**

```
Severity Levels:
├─ INFO: 30 days
├─ WARNING: 90 days
├─ ERROR: 180 days
└─ CRITICAL: 365 days (or indefinite for compliance)

Compliance Mode (GDPR, HIPAA, SOC2):
├─ All audit logs retained indefinitely
├─ Tamper-proof storage (append-only)
├─ Encrypted at rest
└─ Exportable for compliance audits
```

---

## Component Adapter System

### Adapter Interface

Every MagMoBo component that supports extensions must implement the **Adapter Interface**:

```
IComponentAdapter:
├─ GetComponentName() → string
├─ RegisterHooks(kernel) → void
├─ ExecuteHook(hook_name, context, extension_id) → Result
├─ ValidateCapability(extension_id, capability) → boolean
├─ EmitEvent(event) → void
└─ GetHealthStatus() → HealthStatus
```

### Adapter Responsibilities

**1. Hook Registration**

```
RegisterHooks(kernel):
  Purpose: Tell kernel what hooks this component provides
  
  Example (MagDS Adapter):
    kernel.RegisterHook("database.before_create", this)
    kernel.RegisterHook("database.after_create", this)
    kernel.RegisterHook("database.before_migration", this)
    kernel.RegisterHook("database.after_migration", this)
    kernel.RegisterHook("database.before_backup", this)
    kernel.RegisterHook("database.after_restore", this)
    kernel.RegisterHook("database.health_check", this)
```

**2. Hook Execution**

```
ExecuteHook(hook_name, context, extension_id):
  Purpose: Kernel calls this when extension triggers hook
  
  Flow:
    1. Adapter receives hook call from kernel
    2. Adapter validates context (schema check)
    3. Adapter translates context to component-native format
    4. Adapter calls component's internal method
    5. Component executes operation
    6. Adapter translates result back to standard format
    7. Adapter returns result to kernel
    8. Adapter emits event (if applicable)
  
  Example (MagDS Adapter executing "database.query" hook):
    1. Receive: hook_name="database.query", context={sql: "SELECT * FROM users"}
    2. Validate: Check SQL is valid, not malicious
    3. Translate: Convert to PostgreSQL prepared statement
    4. Execute: MagDS.ExecuteQuery(prepared_statement)
    5. Translate: Convert result rows to standard JSON format
    6. Return: {rows: [...], duration_ms: 12}
    7. Emit: kernel.PublishEvent("database.query.executed", {...})
```

**3. Capability Validation**

```
ValidateCapability(extension_id, capability):
  Purpose: Component-specific capability checks
  
  Example (MagDS Adapter):
    If capability == "database.write":
      - Check database is not read-only
      - Check database has available connections
      - Check database disk space > 10%
      - Return true if all checks pass
    
    If capability == "database.admin":
      - Check extension is signed by trusted authority
      - Check user has explicitly approved admin access
      - Return true if approved
```

**4. Event Emission**

```
EmitEvent(event):
  Purpose: Component publishes events to kernel
  
  Example (MagDS Adapter):
    When database query completes:
      adapter.EmitEvent({
        type: "database.query.executed",
        data: {query: "...", duration_ms: 12, rows: 5},
        context: {project_id: "...", user_id: "..."}
      })
    
    Kernel receives event and dispatches to subscribed extensions
```

**5. Health Status**

```
GetHealthStatus():
  Purpose: Report component health to kernel
  
  Returns:
    {
      status: "healthy|degraded|unhealthy",
      checks: [
        {name: "database_connection", status: "healthy"},
        {name: "disk_space", status: "healthy", value: "75% free"},
        {name: "query_latency", status: "degraded", value: "250ms avg"}
      ],
      last_check: timestamp
    }
```

### Adapter Implementation Pattern

**Thin Adapter (Correct):**

```
MagDSAdapter.ExecuteHook("database.query", context):
  1. Validate context schema
  2. Translate context to native format
  3. Call MagDS.ExecuteQuery(native_query)
  4. Translate result to standard format
  5. Return result
  
  (No business logic, just translation)
```

**Fat Adapter (Incorrect):**

```
MagDSAdapter.ExecuteHook("database.query", context):
  1. Validate context schema
  2. Check user permissions (❌ should be in kernel)
  3. Apply rate limiting (❌ should be in kernel)
  4. Log to audit (❌ should be in kernel)
  5. Translate context to native format
  6. Call MagDS.ExecuteQuery(native_query)
  7. Cache result (❌ business logic, should be in MagDS)
  8. Translate result to standard format
  9. Return result
  
  (Too much logic, violates single responsibility)
```

### Standard Adapter Methods

All adapters should provide these standard methods:

```
StandardAdapterMethods:
├─ Initialize(config) → void
├─ Shutdown() → void
├─ GetVersion() → string
├─ GetCapabilities() → List<Capability>
├─ GetHooks() → List<HookDefinition>
├─ GetEventTopics() → List<TopicDefinition>
└─ GetHealthStatus() → HealthStatus
```

---

## Hook System Architecture

### Hook Definition

A **hook** is a named extension point where extensions can inject custom behavior.

**Hook Schema:**

```
HookDefinition:
├─ name: string (e.g., "database.before_migration")
├─ component: string (e.g., "magds")
├─ description: string
├─ phase: enum (before, after, around, replace)
├─ required_capability: Capability
├─ context_schema: JSONSchema (input to hook)
├─ result_schema: JSONSchema (output from hook)
├─ execution_mode: enum (sequential, parallel, first_match)
├─ timeout_ms: int
└─ priority_support: boolean
```

### Hook Phases

**1. Before Hooks**

```
Purpose: Run before component action
Use case: Validation, preprocessing, policy enforcement

Example: database.before_migration
  - Extension can validate migration SQL
  - Extension can reject migration (return error)
  - Extension cannot modify migration (read-only)

Flow:
  1. Component calls adapter.ExecuteHook("database.before_migration", context)
  2. Adapter calls kernel.ExecuteHook(...)
  3. Kernel finds all extensions subscribed to this hook
  4. Kernel calls each extension's handler (sequential)
  5. If any extension returns error → Abort operation
  6. If all extensions return success → Proceed with operation
```

**2. After Hooks**

```
Purpose: Run after component action
Use case: Notifications, logging, side effects

Example: database.after_migration
  - Extension can send notification (Slack, email)
  - Extension can update external system
  - Extension cannot modify migration result

Flow:
  1. Component completes operation
  2. Component calls adapter.ExecuteHook("database.after_migration", context)
  3. Adapter calls kernel.ExecuteHook(...)
  4. Kernel finds all extensions subscribed to this hook
  5. Kernel calls each extension's handler (parallel, fire-and-forget)
  6. If extension fails → Log error, continue (don't block)
```

**3. Around Hooks**

```
Purpose: Wrap component action (before + after)
Use case: Timing, caching, transaction management

Example: database.around_query
  - Extension can start timer before query
  - Extension can cache result
  - Extension can wrap in transaction

Flow:
  1. Component calls adapter.ExecuteHook("database.around_query", context)
  2. Adapter calls kernel.ExecuteHook(...)
  3. Kernel calls extension's handler
  4. Extension's handler:
     a. Do pre-processing
     b. Call context.Proceed() (executes actual query)
     c. Do post-processing
     d. Return result
```

**4. Replace Hooks**

```
Purpose: Completely replace component action
Use case: Custom implementations, mocking, testing

Example: database.replace_backup
  - Extension can implement custom backup strategy
  - Extension completely replaces default backup

Flow:
  1. Component calls adapter.ExecuteHook("database.replace_backup", context)
  2. Adapter calls kernel.ExecuteHook(...)
  3. Kernel finds extension with highest priority for this hook
  4. Kernel calls extension's handler
  5. Extension's handler returns result
  6. Component uses extension's result (skips default implementation)

Warning: Replace hooks are powerful but dangerous
  - Only one extension can replace a hook
  - Extension must fully implement expected behavior
  - If extension fails, fallback to default (if available)
```

### Hook Execution Modes

**1. Sequential**

```
Purpose: Execute extensions one at a time, in priority order
Use case: Order matters (e.g., validation pipeline)

Flow:
  for each extension in priority_order:
    result = extension.HandleHook(context)
    if result.error:
      abort and return error
    context = result.modified_context (for next extension)
  return final context
```

**2. Parallel**

```
Purpose: Execute all extensions simultaneously
Use case: Order doesn't matter (e.g., notifications)

Flow:
  results = []
  for each extension in subscribers:
    results.append(async extension.HandleHook(context))
  wait for all results (with timeout)
  return aggregated results
```

**3. First Match**

```
Purpose: Execute extensions until one returns success
Use case: Fallback chain (e.g., authentication providers)

Flow:
  for each extension in priority_order:
    result = extension.HandleHook(context)
    if result.success:
      return result (stop processing)
  return error (no extension succeeded)
```

### Hook Context

Every hook receives a **context object** with standard fields:

```json
{
  "hook_name": "database.before_migration",
  "component": "magds",
  "timestamp": "2025-10-05T10:23:45.123Z",
  "trace_id": "trace_123",
  "project_id": "proj_abc123",
  "user_id": "user_xyz789",
  "data": {
    // Hook-specific data (validated against context_schema)
    "migration_file": "2025_10_05_create_users_table.sql",
    "migration_sql": "CREATE TABLE users (...)"
  },
  "metadata": {
    // Optional metadata
    "source": "magcli",
    "ip_address": "192.168.1.100"
  }
}
```

### Hook Result

Every hook returns a **result object**:

```json
{
  "success": true,
  "error": null,
  "data": {
    // Hook-specific result (validated against result_schema)
    "validation_passed": true,
    "warnings": []
  },
  "modified_context": {
    // Optional: Modified context for next extension in chain
    // (Only used in sequential execution mode)
  },
  "metadata": {
    "duration_ms": 5,
    "extension_version": "1.2.3"
  }
}
```

### Hook Priority

Extensions can specify priority for hooks (0-100, higher = earlier):

```json
{
  "hooks": {
    "database.before_migration": {
      "handler": "validateMigration",
      "priority": 90
    }
  }
}
```

**Priority Guidelines:**

```
90-100: Critical validation (security, compliance)
70-89:  Business logic validation
50-69:  Default priority (most extensions)
30-49:  Enrichment, logging
10-29:  Notifications, side effects
0-9:    Cleanup, finalization
```

---

## Capability System

### Capability Taxonomy

Capabilities are organized hierarchically:

```
Capability Hierarchy:
├─ database
│  ├─ database.read
│  ├─ database.write
│  ├─ database.schema
│  └─ database.admin
├─ api
│  ├─ api.register_endpoint
│  ├─ api.intercept_request
│  └─ api.emit_events
├─ ui
│  ├─ ui.register_widget
│  ├─ ui.register_panel
│  └─ ui.modify_schema
├─ realtime
│  ├─ realtime.register_channel
│  ├─ realtime.subscribe_events
│  └─ realtime.broadcast
├─ auth
│  ├─ auth.register_provider
│  ├─ auth.modify_roles
│  └─ auth.audit_access
├─ network
│  ├─ network.outbound
│  └─ network.inbound
├─ filesystem
│  ├─ filesystem.read
│  ├─ filesystem.write
│  └─ filesystem.temp_only
└─ system
   ├─ system.background_jobs
   ├─ system.cron_schedule
   └─ system.metrics
```

### Capability Specification

Each capability has a detailed specification:

```json
{
  "name": "database.write",
  "display_name": "Database Write Access",
  "description": "Allows extension to INSERT, UPDATE, DELETE rows in project databases",
  "risk_level": "high",
  "requires_user_approval": true,
  "requires_admin_approval": false,
  "rate_limit": {
    "max_calls_per_minute": 100,
    "max_calls_per_hour": 1000,
    "max_calls_per_day": 10000
  },
  "resource_limits": {
    "max_rows_per_query": 1000,
    "max_query_duration_ms": 5000
  },
  "audit_level": "detailed",
  "dependencies": ["database.read"],
  "conflicts": [],
  "examples": [
    "Insert new rows into tables",
    "Update existing rows",
    "Delete rows (with RLS enforcement)"
  ],
  "security_notes": [
    "Row-level security (RLS) is always enforced",
    "Extensions cannot bypass RLS policies",
    "All writes are audited"
  ]
}
```

### Capability Declaration (Manifest)

Extensions declare required capabilities in manifest:

```json
{
  "id": "magflock/magrag",
  "name": "MagRAG",
  "version": "1.2.3",
  "capabilities": {
    "database.read": {
      "reason": "Read documents from database for vectorization",
      "optional": false
    },
    "database.write": {
      "reason": "Store vector embeddings in database",
      "optional": false
    },
    "database.schema": {
      "reason": "Create vector extension and embedding tables",
      "optional": false
    },
    "network.outbound": {
      "reason": "Call OpenAI API for embeddings",
      "optional": true,
      "fallback": "Use local embedding model if denied"
    },
    "system.background_jobs": {
      "reason": "Process large document batches asynchronously",
      "optional": false
    }
  }
}
```

### Capability Approval Flow

**Installation Time:**

```
1. User runs: mag extension:install magflock/magrag
2. Kernel parses manifest
3. Kernel displays capability request to user:

   ┌─────────────────────────────────────────────────────────┐
   │ MagRAG Extension Capability Request                     │
   ├─────────────────────────────────────────────────────────┤
   │                                                         │
   │ This extension requests the following capabilities:     │
   │                                                         │
   │ ✓ Database Read Access (REQUIRED)                      │
   │   Reason: Read documents from database for             │
   │           vectorization                                │
   │   Risk: Medium                                         │
   │                                                         │
   │ ✓ Database Write Access (REQUIRED)                     │
   │   Reason: Store vector embeddings in database          │
   │   Risk: High                                           │
   │                                                         │
   │ ✓ Database Schema Modification (REQUIRED)              │
   │   Reason: Create vector extension and embedding tables │
   │   Risk: High                                           │
   │                                                         │
   │ ○ Outbound Network Access (OPTIONAL)                   │
   │   Reason: Call OpenAI API for embeddings               │
   │   Risk: Medium                                         │
   │   Fallback: Use local embedding model if denied        │
   │                                                         │
   │ ✓ Background Jobs (REQUIRED)                           │
   │   Reason: Process large document batches               │
   │   Risk: Low                                            │
   │                                                         │
   ├─────────────────────────────────────────────────────────┤
   │ Approve all required capabilities? [Y/n]               │
   │ Approve optional capabilities? [y/N]                   │
   └─────────────────────────────────────────────────────────┘

4. User approves
5. Kernel grants capabilities
6. Extension installs
```

**Runtime Enforcement:**

```
Extension calls: kernel.ExecuteQuery("INSERT INTO embeddings ...")

Kernel checks:
1. Does extension have "database.write" capability? ✓
2. Is capability currently enabled? ✓
3. Has rate limit been exceeded? ✓ (50/100 calls this minute)
4. Is extension healthy? ✓
5. Proceed with query

Kernel audits:
{
  "action": "capability.used",
  "extension": "magflock/magrag",
  "capability": "database.write",
  "result": "success",
  "rate_limit_remaining": 50
}
```

### Capability Revocation

Admin can revoke capabilities at runtime:

```bash
# Revoke specific capability
$ mag extension:revoke-capability magflock/magrag database.write

# Extension continues running but cannot write to database
# Attempts to write → Error + Audit log

# Re-grant capability
$ mag extension:grant-capability magflock/magrag database.write
```

### Capability Inheritance

Some capabilities imply others:

```
database.admin implies:
├─ database.schema
├─ database.write
└─ database.read

database.schema implies:
└─ database.read

auth.modify_roles implies:
└─ auth.audit_access
```

### Capability Risk Levels

```
Risk Levels:
├─ LOW: Read-only access, metrics, logging
├─ MEDIUM: Write access, network access, background jobs
├─ HIGH: Schema changes, role modifications, API interception
└─ CRITICAL: Admin access, system modifications, code execution

User Approval Required:
├─ LOW: Auto-approved
├─ MEDIUM: User approval required
├─ HIGH: User approval + warning
└─ CRITICAL: Admin approval + explicit confirmation
```

---

## Event Bus Contract

### Event Schema

All events follow a standard schema:

```json
{
  "event_id": "evt_abc123",
  "event_type": "database.query.executed",
  "timestamp": "2025-10-05T10:23:45.123Z",
  "version": "1.0",
  "source": {
    "component": "magds",
    "extension": null,
    "adapter": "magds_adapter"
  },
  "data": {
    // Event-specific data (validated against event schema)
    "query": "SELECT * FROM users WHERE id = $1",
    "parameters": ["user_123"],
    "duration_ms": 12,
    "rows_returned": 1,
    "cache_hit": false
  },
  "context": {
    "project_id": "proj_abc123",
    "user_id": "user_xyz789",
    "trace_id": "trace_123",
    "session_id": "sess_456"
  },
  "metadata": {
    "capabilities_required": ["database.read"],
    "priority": 50,
    "retention_days": 7
  }
}
```

### Event Categories

**Database Events:**

```
database.query.executed
├─ Emitted: After every database query
├─ Data: {query, parameters, duration_ms, rows_returned, cache_hit}
├─ Capability: database.read (to subscribe)
└─ Use case: Query monitoring, analytics, caching

database.migration.applied
├─ Emitted: After migration completes
├─ Data: {migration_file, migration_sql, duration_ms, success}
├─ Capability: database.schema (to subscribe)
└─ Use case: Schema change notifications, documentation

database.backup.completed
├─ Emitted: After backup finishes
├─ Data: {backup_file, size_bytes, duration_ms, type}
├─ Capability: database.admin (to subscribe)
└─ Use case: Backup monitoring, external storage sync

database.connection.failed
├─ Emitted: When database connection fails
├─ Data: {error, retry_count, next_retry_in_ms}
├─ Capability: database.read (to subscribe)
└─ Use case: Alerting, failover
```

**API Events:**

```
api.request.received
├─ Emitted: When API request arrives
├─ Data: {method, path, headers, body, ip_address}
├─ Capability: api.intercept_request (to subscribe)
└─ Use case: Request logging, rate limiting, security

api.response.sent
├─ Emitted: When API response sent
├─ Data: {status_code, headers, body, duration_ms}
├─ Capability: api.intercept_request (to subscribe)
└─ Use case: Response logging, analytics

api.error.occurred
├─ Emitted: When API error occurs
├─ Data: {error, stack_trace, request_context}
├─ Capability: api.emit_events (to subscribe)
└─ Use case: Error tracking, alerting

api.rate_limit.exceeded
├─ Emitted: When rate limit exceeded
├─ Data: {user_id, endpoint, limit, current_count}
├─ Capability: api.emit_events (to subscribe)
└─ Use case: Abuse detection, alerting
```

**Auth Events:**

```
auth.user.login
├─ Emitted: When user logs in
├─ Data: {user_id, method, ip_address, user_agent}
├─ Capability: auth.audit_access (to subscribe)
└─ Use case: Security monitoring, analytics

auth.user.logout
├─ Emitted: When user logs out
├─ Data: {user_id, session_duration_ms}
├─ Capability: auth.audit_access (to subscribe)
└─ Use case: Session analytics

auth.token.expired
├─ Emitted: When JWT token expires
├─ Data: {user_id, token_id, expired_at}
├─ Capability: auth.audit_access (to subscribe)
└─ Use case: Token refresh, security

auth.permission.denied
├─ Emitted: When permission check fails
├─ Data: {user_id, resource, action, reason}
├─ Capability: auth.audit_access (to subscribe)
└─ Use case: Security monitoring, alerting
```

**Real-Time Events:**

```
realtime.connection.opened
├─ Emitted: When WebSocket connection opens
├─ Data: {connection_id, user_id, ip_address}
├─ Capability: realtime.subscribe_events (to subscribe)
└─ Use case: Connection monitoring, presence

realtime.subscription.created
├─ Emitted: When client subscribes to channel
├─ Data: {connection_id, channel, filters}
├─ Capability: realtime.subscribe_events (to subscribe)
└─ Use case: Channel analytics, monitoring

realtime.message.sent
├─ Emitted: When message broadcast to channel
├─ Data: {channel, message, recipient_count}
├─ Capability: realtime.broadcast (to subscribe)
└─ Use case: Message analytics, debugging

realtime.connection.closed
├─ Emitted: When WebSocket connection closes
├─ Data: {connection_id, duration_ms, reason}
├─ Capability: realtime.subscribe_events (to subscribe)
└─ Use case: Connection analytics, debugging
```

**Extension Events:**

```
extension.installed
├─ Emitted: When extension installed
├─ Data: {extension_id, version, capabilities}
├─ Capability: system.metrics (to subscribe)
└─ Use case: Extension analytics, monitoring

extension.enabled
├─ Emitted: When extension enabled
├─ Data: {extension_id, version}
├─ Capability: system.metrics (to subscribe)
└─ Use case: Extension monitoring

extension.disabled
├─ Emitted: When extension disabled
├─ Data: {extension_id, reason}
├─ Capability: system.metrics (to subscribe)
└─ Use case: Extension monitoring, alerting

extension.error
├─ Emitted: When extension throws error
├─ Data: {extension_id, error, stack_trace, context}
├─ Capability: system.metrics (to subscribe)
└─ Use case: Error tracking, debugging

extension.health_check.failed
├─ Emitted: When extension health check fails
├─ Data: {extension_id, check_name, error}
├─ Capability: system.metrics (to subscribe)
└─ Use case: Health monitoring, alerting
```

### Event Subscription

Extensions subscribe to events in manifest:

```json
{
  "id": "magflock/query-monitor",
  "name": "Query Monitor",
  "version": "1.0.0",
  "capabilities": {
    "database.read": {
      "reason": "Subscribe to query events"
    }
  },
  "subscriptions": [
    {
      "topic": "database.query.executed",
      "handler": "onQueryExecuted",
      "filter": {
        "data.duration_ms": {"$gt": 1000}
      },
      "priority": 50
    },
    {
      "topic": "database.*.failed",
      "handler": "onDatabaseError",
      "priority": 90
    }
  ]
}
```

**Subscription Filters:**

```json
{
  "filter": {
    // Simple equality
    "data.cache_hit": false,
    
    // Comparison operators
    "data.duration_ms": {"$gt": 1000},
    "data.rows_returned": {"$lte": 100},
    
    // Array operators
    "context.project_id": {"$in": ["proj_1", "proj_2"]},
    
    // Logical operators
    "$or": [
      {"data.duration_ms": {"$gt": 1000}},
      {"data.rows_returned": {"$gt": 10000}}
    ],
    
    // Regex
    "data.query": {"$regex": "^SELECT.*FROM users"}
  }
}
```

### Event Delivery Guarantees

**At-Least-Once Delivery:**

```
- Events may be delivered multiple times
- Extensions must be idempotent
- Use event_id to deduplicate

Example:
  extension.onQueryExecuted(event):
    if redis.exists(event.event_id):
      return  // Already processed
    
    // Process event
    processQuery(event.data)
    
    // Mark as processed
    redis.set(event.event_id, "processed", ttl=7days)
```

**Ordering Guarantees:**

```
Within same topic:
  - Events are ordered by timestamp
  - Events from same source are strictly ordered

Across topics:
  - No ordering guarantee
  - Use trace_id to correlate related events

Example:
  Event 1: database.query.executed (trace_123, timestamp: 10:00:00.100)
  Event 2: database.query.executed (trace_123, timestamp: 10:00:00.200)
  Event 3: api.response.sent (trace_123, timestamp: 10:00:00.300)
  
  Extension receives:
    - Event 1 before Event 2 (same topic, ordered)
    - Event 3 may arrive before Event 2 (different topic, no guarantee)
```

**Retention & Replay:**

```
- Events retained for 7 days
- Extensions can replay events for debugging

Replay API:
  kernel.ReplayEvents({
    topic: "database.query.executed",
    start_time: "2025-10-05T00:00:00Z",
    end_time: "2025-10-05T23:59:59Z",
    filter: {data.duration_ms: {$gt: 1000}}
  })
```

### Event Backpressure

If extension is slow, events are queued:

```
Queue Limits:
├─ Max queue size per extension: 10,000 events
├─ Max queue age: 5 minutes
└─ If exceeded: Drop oldest events + Alert

Backpressure Handling:
1. Extension processes events slowly
2. Queue grows beyond 5,000 events
3. Kernel emits warning: extension.backpressure.warning
4. Queue grows beyond 10,000 events
5. Kernel drops oldest events
6. Kernel emits alert: extension.backpressure.critical
7. Admin can:
   - Increase queue size
   - Disable extension
   - Scale extension (if supported)
```

---

## Extension Lifecycle Management

### Lifecycle State Machine

```
┌─────────────────┐
│ NOT_INSTALLED   │
└────────┬────────┘
         │ install
         ▼
┌─────────────────┐
│  DOWNLOADING    │
└────────┬────────┘
         │ verify
         ▼
┌─────────────────┐
│   VERIFYING     │
└────────┬────────┘
         │ extract
         ▼
┌─────────────────┐
│  INSTALLING     │
└────────┬────────┘
         │ success
         ▼
┌─────────────────┐     enable      ┌─────────────────┐
│   INSTALLED     │────────────────▶│    ENABLING     │
│   (disabled)    │                 └────────┬────────┘
└────────┬────────┘                          │ success
         │                                   ▼
         │                          ┌─────────────────┐
         │                          │     ENABLED     │
         │                          │    (running)    │
         │                          └────────┬────────┘
         │                                   │
         │                          ┌────────┴────────┐
         │                          │                 │
         │                   health │                 │ disable
         │                   failed │                 │
         │                          ▼                 ▼
         │                  ┌─────────────┐   ┌─────────────┐
         │                  │  DEGRADED   │   │  DISABLING  │
         │                  │  (running)  │   └──────┬──────┘
         │                  └──────┬──────┘          │
         │                         │                 │
         │                  health │                 │ success
         │                recovered│                 │
         │                         │                 ▼
         │                         │         ┌─────────────────┐
         │                         └────────▶│   DISABLED      │
         │                                   └────────┬────────┘
         │                                            │
         │ uninstall                                  │ uninstall
         │◀───────────────────────────────────────────┘
         ▼
┌─────────────────┐
│  UNINSTALLING   │
└────────┬────────┘
         │ success
         ▼
┌─────────────────┐
│ NOT_INSTALLED   │
└─────────────────┘

         ┌─────────────────┐
         │     ERROR       │ (any state can transition to ERROR)
         └─────────────────┘
```

### Installation Process

**Step-by-Step:**

```
1. DOWNLOAD
   ├─ Fetch extension package from registry
   ├─ URL: https://registry.magflock.com/extensions/{extension_id}/{version}.tar.gz
   ├─ Verify TLS certificate
   ├─ Download to temp directory
   └─ Progress: Show download progress to user

2. VERIFY
   ├─ Check file integrity (SHA-256 hash)
   ├─ Verify code signature (GPG or similar)
   ├─ Public key from: https://registry.magflock.com/keys/{author}.pub
   ├─ If signature invalid → Abort + Alert
   └─ Extract to temp directory

3. PARSE MANIFEST
   ├─ Read manifest.json
   ├─ Validate against schema
   ├─ Check required fields: id, name, version, author, license
   └─ If invalid → Abort + Show error

4. COMPATIBILITY CHECK
   ├─ Check MagMoBo version >= manifest.min_magmobo_version
   ├─ Check all dependencies installed
   ├─ Check no conflicting extensions
   ├─ If incompatible → Abort + Show error
   └─ If compatible → Proceed

5. CAPABILITY APPROVAL
   ├─ Display capabilities to user (see Capability Approval Flow)
   ├─ User approves or denies
   ├─ If denied → Abort
   └─ If approved → Proceed

6. RUN INSTALL SCRIPT
   ├─ Execute extension.Install() method (sandboxed)
   ├─ Timeout: 5 minutes
   ├─ If timeout → Abort + Rollback
   ├─ If error → Abort + Rollback
   └─ If success → Proceed

7. DATABASE MIGRATIONS
   ├─ Check for migrations directory
   ├─ Run migrations in order (001_*.sql, 002_*.sql, ...)
   ├─ Wrap in transaction (rollback on error)
   ├─ If error → Abort + Rollback
   └─ If success → Proceed

8. COPY FILES
   ├─ Copy extension files to: /extensions/{extension_id}/
   ├─ Set permissions (read-only for code, read-write for data)
   └─ Create config file: /extensions/{extension_id}/config.json

9. REGISTER WITH KERNEL
   ├─ Add to extension registry
   ├─ Register hooks
   ├─ Register event subscriptions
   └─ Grant capabilities

10. HEALTH CHECK #1
    ├─ Call extension.HealthCheck()
    ├─ Timeout: 10 seconds
    ├─ If unhealthy → Rollback (undo migrations, remove files)
    └─ If healthy → Mark as INSTALLED

11. AUDIT LOG
    ├─ Log: extension.installed
    ├─ Data: {extension_id, version, capabilities, user_id}
    └─ Severity: INFO

12. SUCCESS
    ├─ Display success message to user
    ├─ Show next steps: "Run 'mag extension:enable {extension_id}' to activate"
    └─ Return INSTALLED state
```

**Rollback on Failure:**

```
If any step fails:
1. Undo database migrations (if applied)
2. Remove extension files
3. Unregister from kernel
4. Delete config file
5. Audit log: extension.install.failed
6. Display error to user
7. Return NOT_INSTALLED state
```

### Enablement Process

```
1. VALIDATE STATE
   ├─ Check extension is INSTALLED or DISABLED
   ├─ If not → Error: "Extension must be installed first"
   └─ If yes → Proceed

2. LOAD CONFIGURATION
   ├─ Read config file: /extensions/{extension_id}/config.json
   ├─ Validate against config schema (from manifest)
   ├─ If invalid → Error: "Invalid configuration"
   └─ If valid → Proceed

3. RUN INIT SCRIPT
   ├─ Execute extension.Init(config) method (sandboxed)
   ├─ Timeout: 1 minute
   ├─ If timeout → Error + Disable
   ├─ If error → Error + Disable
   └─ If success → Proceed

4. REGISTER HOOKS
   ├─ For each hook in manifest:
   │  ├─ Register with component adapter
   │  └─ Validate handler method exists
   └─ If any hook fails → Error + Disable

5. SUBSCRIBE TO EVENTS
   ├─ For each subscription in manifest:
   │  ├─ Subscribe to event topic
   │  └─ Validate handler method exists
   └─ If any subscription fails → Error + Disable
```
6. RUN START SCRIPT
   ├─ Execute extension.Start() method (sandboxed)
   ├─ Timeout: 1 minute
   ├─ If timeout → Error + Disable
   ├─ If error → Error + Disable
   └─ If success → Proceed

7. HEALTH CHECK #2
   ├─ Call extension.HealthCheck()
   ├─ Send test event to extension
   ├─ Verify extension responds correctly
   ├─ Timeout: 10 seconds
   ├─ If unhealthy → Disable + Alert admin
   └─ If healthy → Mark as ENABLED

8. START MONITORING
   ├─ Begin periodic health checks (every 60 seconds)
   ├─ Monitor CPU usage
   ├─ Monitor memory usage
   ├─ Monitor error rate
   └─ Monitor response time

9. AUDIT LOG
   ├─ Log: extension.enabled
   ├─ Data: {extension_id, version, user_id}
   └─ Severity: INFO

10. SUCCESS
    ├─ Display success message to user
    ├─ Extension is now active and processing events
    └─ Return ENABLED state
```

### Upgrade Process

**Zero-Downtime Upgrade Strategy:**

```
1. VALIDATE STATE
   ├─ Check extension is INSTALLED or ENABLED
   ├─ If not → Error
   └─ If yes → Proceed

2. DOWNLOAD NEW VERSION
   ├─ Fetch new version package from registry
   ├─ Verify signature
   ├─ Extract to temp directory
   └─ Parse new manifest

3. COMPATIBILITY CHECK
   ├─ Check MagMoBo version compatibility
   ├─ Check dependency compatibility
   ├─ Check breaking changes (manifest.breaking_changes)
   ├─ If incompatible → Abort + Show error
   └─ If compatible → Proceed

4. CAPABILITY DIFF
   ├─ Compare old capabilities vs new capabilities
   ├─ If new capabilities added:
   │  ├─ Display to user for approval
   │  ├─ User approves or denies
   │  └─ If denied → Abort
   └─ If only existing capabilities → Auto-approve

5. PARALLEL INSTALLATION (if extension is ENABLED)
   ├─ Keep old version running (continue serving requests)
   ├─ Install new version in separate directory:
   │  └─ /extensions/{extension_id}/versions/{new_version}/
   ├─ Run new version's Install() method
   ├─ Run database migrations (new version)
   │  ├─ Migrations are additive only (no destructive changes)
   │  └─ Old version continues to work with new schema
   └─ If error → Abort + Rollback migrations

6. HEALTH CHECK #3 (new version)
   ├─ Call new_version.HealthCheck()
   ├─ Send test events to new version
   ├─ Verify new version responds correctly
   ├─ Timeout: 30 seconds
   ├─ If unhealthy → Abort + Rollback
   └─ If healthy → Proceed

7. TRAFFIC SWITCH (atomic)
   ├─ Update kernel registry to point to new version
   ├─ New requests go to new version
   ├─ Old requests complete on old version
   ├─ Wait for old version to drain (max 30 seconds)
   └─ Atomic switch (no requests lost)

8. MONITOR NEW VERSION
   ├─ Monitor for 5 minutes
   ├─ Check error rate
   ├─ Check response time
   ├─ Check health checks
   ├─ If degraded → Automatic rollback
   └─ If healthy → Proceed

9. CLEANUP OLD VERSION (delayed)
   ├─ Wait 24 hours (configurable)
   ├─ Verify new version is stable
   ├─ Run old_version.Uninstall() method
   ├─ Remove old version files
   └─ Keep old version migrations (for rollback capability)

10. AUDIT LOG
    ├─ Log: extension.upgraded
    ├─ Data: {extension_id, old_version, new_version, user_id}
    └─ Severity: INFO

11. SUCCESS
    ├─ Display success message to user
    ├─ Extension is now running new version
    └─ Return ENABLED state (new version)
```

**Rollback on Upgrade Failure:**

```
If upgrade fails at any step:
1. Stop new version (if started)
2. Rollback database migrations (if applied)
3. Remove new version files
4. Keep old version running (no downtime)
5. Audit log: extension.upgrade.failed
6. Alert admin
7. Display error to user
8. Return to previous state (ENABLED with old version)
```

**Manual Rollback:**

```
Admin can manually rollback to previous version:

$ mag extension:rollback magflock/magrag

Process:
1. Check if previous version files exist
2. If not → Error: "No previous version available"
3. If yes → Reverse upgrade process:
   a. Stop current version
   b. Rollback migrations (if safe)
   c. Switch to previous version
   d. Start previous version
   e. Health check
   f. If healthy → Success
   g. If unhealthy → Error (manual intervention required)
```

### Disable Process

```
1. VALIDATE STATE
   ├─ Check extension is ENABLED or DEGRADED
   ├─ If not → Error: "Extension is not running"
   └─ If yes → Proceed

2. RUN STOP SCRIPT
   ├─ Execute extension.Stop() method (sandboxed)
   ├─ Timeout: 30 seconds (graceful shutdown)
   ├─ If timeout → Force kill process
   └─ Extension should:
      ├─ Complete in-flight requests
      ├─ Close database connections
      ├─ Flush caches
      └─ Clean up resources

3. UNREGISTER HOOKS
   ├─ For each hook in manifest:
   │  └─ Unregister from component adapter
   └─ New hook calls will not reach this extension

4. UNSUBSCRIBE FROM EVENTS
   ├─ For each subscription in manifest:
   │  └─ Unsubscribe from event topic
   └─ New events will not be delivered to this extension

5. DRAIN EVENT QUEUE
   ├─ Process remaining events in queue (up to 1000)
   ├─ Timeout: 60 seconds
   ├─ If timeout → Drop remaining events + Log warning
   └─ If completed → Proceed

6. STOP MONITORING
   ├─ Stop periodic health checks
   ├─ Stop resource monitoring
   └─ Extension is no longer monitored

7. MARK AS DISABLED
   ├─ Update state in registry
   ├─ Extension files remain (can re-enable quickly)
   └─ Configuration remains

8. AUDIT LOG
   ├─ Log: extension.disabled
   ├─ Data: {extension_id, reason, user_id}
   └─ Severity: INFO

9. SUCCESS
   ├─ Display success message to user
   ├─ Extension is now inactive
   └─ Return DISABLED state
```

### Uninstall Process

```
1. VALIDATE STATE
   ├─ Check extension is DISABLED
   ├─ If ENABLED → Error: "Must disable extension first"
   ├─ If INSTALLED → Proceed
   └─ If DISABLED → Proceed

2. DEPENDENCY CHECK
   ├─ Check if other extensions depend on this one
   ├─ If yes → Error: "Cannot uninstall, other extensions depend on it"
   │  └─ Display list of dependent extensions
   └─ If no → Proceed

3. DATA CLEANUP WARNING
   ├─ Display warning to user:
   │  ┌─────────────────────────────────────────────────┐
   │  │ WARNING: Uninstalling Extension                 │
   │  ├─────────────────────────────────────────────────┤
   │  │                                                 │
   │  │ This will remove:                               │
   │  │ • Extension files                               │
   │  │ • Extension configuration                       │
   │  │ • Extension database tables (optional)          │
   │  │                                                 │
   │  │ This action cannot be undone.                   │
   │  │                                                 │
   │  │ Keep extension data? [Y/n]                      │
   │  └─────────────────────────────────────────────────┘
   └─ User chooses to keep or delete data

4. RUN UNINSTALL SCRIPT
   ├─ Execute extension.Uninstall() method (sandboxed)
   ├─ Timeout: 5 minutes
   ├─ Extension should:
   │  ├─ Clean up temporary files
   │  ├─ Close external connections
   │  └─ Perform final cleanup
   ├─ If timeout → Force kill + Log warning
   └─ If error → Log error + Continue (best effort)

5. DATABASE MIGRATIONS ROLLBACK
   ├─ If user chose to delete data:
   │  ├─ Check if migrations are reversible
   │  ├─ If reversible → Run down migrations
   │  ├─ If not reversible → Display warning:
   │  │  "Cannot automatically remove database tables.
   │  │   Manual cleanup required."
   │  └─ If error → Log error + Continue
   └─ If user chose to keep data:
      └─ Skip migration rollback (data remains)

6. REMOVE FILES
   ├─ Delete extension directory: /extensions/{extension_id}/
   ├─ Delete configuration file
   ├─ Delete logs (optional, based on user choice)
   └─ If error → Log error + Continue (best effort)

7. UNREGISTER FROM KERNEL
   ├─ Remove from extension registry
   ├─ Remove capability grants
   ├─ Remove hook registrations
   ├─ Remove event subscriptions
   └─ Remove from dependency graph

8. AUDIT LOG
   ├─ Log: extension.uninstalled
   ├─ Data: {extension_id, version, data_deleted, user_id}
   └─ Severity: INFO

9. SUCCESS
   ├─ Display success message to user
   ├─ Extension is now completely removed
   └─ Return NOT_INSTALLED state
```

### Health Check System

**Periodic Health Checks:**

```
Every 60 seconds (configurable):
1. Call extension.HealthCheck()
2. Timeout: 10 seconds
3. Expected response:
   {
     "status": "healthy|degraded|unhealthy",
     "checks": [
       {
         "name": "database_connection",
         "status": "healthy",
         "message": "Connected to database",
         "duration_ms": 5
       },
       {
         "name": "external_api",
         "status": "degraded",
         "message": "API response time > 1s",
         "duration_ms": 1200
       }
     ],
     "metadata": {
       "uptime_seconds": 3600,
       "requests_processed": 1000,
       "errors_last_hour": 2
     }
   }
4. Kernel evaluates overall health
5. Update extension status in registry
6. If status changed → Emit event: extension.health_status.changed
```

**Health Status Definitions:**

```
HEALTHY:
├─ All health checks pass
├─ Error rate < 1%
├─ Response time < 1s (p95)
├─ CPU usage < 50%
├─ Memory usage < 80%
└─ No recent crashes

DEGRADED:
├─ Some health checks fail (non-critical)
├─ Error rate 1-5%
├─ Response time 1-5s (p95)
├─ CPU usage 50-80%
├─ Memory usage 80-95%
└─ Extension still functional but impaired

UNHEALTHY:
├─ Critical health checks fail
├─ Error rate > 5%
├─ Response time > 5s (p95)
├─ CPU usage > 80%
├─ Memory usage > 95%
└─ Extension not functional
```

**Automatic Actions on Health Status:**

```
HEALTHY → DEGRADED:
├─ Emit event: extension.health_status.changed
├─ Log warning
├─ Alert admin (if configured)
└─ Continue running (no action)

DEGRADED → HEALTHY:
├─ Emit event: extension.health_status.changed
├─ Log info
└─ Continue running

DEGRADED → UNHEALTHY:
├─ Emit event: extension.health_status.changed
├─ Log error
├─ Alert admin (critical)
└─ Continue running (1 more chance)

UNHEALTHY (3 consecutive checks):
├─ Automatic disable
├─ Emit event: extension.disabled
├─ Log critical error
├─ Alert admin (critical)
└─ Require manual intervention to re-enable
```

**Custom Health Checks:**

Extensions can define custom health checks:

```php
// Extension health check implementation
class MagRAGExtension {
  public function HealthCheck(): HealthCheckResult {
    $checks = [];
    
    // Check 1: Database connection
    try {
      $this->db->query("SELECT 1");
      $checks[] = new HealthCheck(
        name: "database_connection",
        status: "healthy",
        message: "Connected to database"
      );
    } catch (Exception $e) {
      $checks[] = new HealthCheck(
        name: "database_connection",
        status: "unhealthy",
        message: "Cannot connect to database: " . $e->getMessage()
      );
    }
    
    // Check 2: Vector extension
    try {
      $result = $this->db->query("SELECT * FROM pg_extension WHERE extname = 'vector'");
      if ($result->rowCount() > 0) {
        $checks[] = new HealthCheck(
          name: "vector_extension",
          status: "healthy",
          message: "Vector extension installed"
        );
      } else {
        $checks[] = new HealthCheck(
          name: "vector_extension",
          status: "unhealthy",
          message: "Vector extension not installed"
        );
      }
    } catch (Exception $e) {
      $checks[] = new HealthCheck(
        name: "vector_extension",
        status: "unhealthy",
        message: "Cannot check vector extension: " . $e->getMessage()
      );
    }
    
    // Check 3: OpenAI API (if configured)
    if ($this->config->get('openai_api_key')) {
      try {
        $start = microtime(true);
        $response = $this->openai->ping();
        $duration = (microtime(true) - $start) * 1000;
        
        if ($duration < 1000) {
          $checks[] = new HealthCheck(
            name: "openai_api",
            status: "healthy",
            message: "OpenAI API responding",
            duration_ms: $duration
          );
        } else {
          $checks[] = new HealthCheck(
            name: "openai_api",
            status: "degraded",
            message: "OpenAI API slow",
            duration_ms: $duration
          );
        }
      } catch (Exception $e) {
        $checks[] = new HealthCheck(
          name: "openai_api",
          status: "degraded",
          message: "OpenAI API unavailable (using local model)",
          duration_ms: 0
        );
      }
    }
    
    // Determine overall status
    $hasUnhealthy = array_filter($checks, fn($c) => $c->status === "unhealthy");
    $hasDegraded = array_filter($checks, fn($c) => $c->status === "degraded");
    
    if (count($hasUnhealthy) > 0) {
      $status = "unhealthy";
    } elseif (count($hasDegraded) > 0) {
      $status = "degraded";
    } else {
      $status = "healthy";
    }
    
    return new HealthCheckResult(
      status: $status,
      checks: $checks,
      metadata: [
        "uptime_seconds" => $this->getUptime(),
        "requests_processed" => $this->getRequestCount(),
        "errors_last_hour" => $this->getErrorCount()
      ]
    );
  }
}
```

---

## Sandboxing & Security

### Multi-Layer Sandboxing

MagFlock uses **defense in depth** with multiple sandboxing layers:

```
Layer 1: Process Isolation
├─ Each extension runs in separate process
├─ Cannot access other extension processes
├─ Cannot access MagMoBo core process
└─ Killed if exceeds resource quota

Layer 2: Filesystem Isolation
├─ chroot or containerization
├─ Read-only access to own directory
├─ Read/write access to temp directory only
└─ No access to system directories

Layer 3: Network Isolation
├─ Firewall rules per extension
├─ Outbound HTTP/HTTPS only if capability granted
├─ No access to internal MagMoBo services
└─ All network calls logged

Layer 4: API Isolation
├─ Extensions call kernel API only
├─ Kernel validates every call
├─ Kernel enforces capabilities
└─ Kernel rate-limits calls

Layer 5: Language Runtime Isolation
├─ PHP: disable_functions, open_basedir
├─ Node.js: VM2 sandbox
├─ Python: RestrictedPython
└─ Language-specific restrictions enforced
```

### Process Isolation Details

**Linux (cgroups + namespaces):**

```
Extension process runs with:
├─ PID namespace (isolated process tree)
├─ Network namespace (isolated network stack)
├─ Mount namespace (isolated filesystem view)
├─ IPC namespace (isolated inter-process communication)
├─ UTS namespace (isolated hostname)
└─ User namespace (isolated user IDs)

Resource limits (cgroups):
├─ CPU: 10% of one core (configurable)
├─ Memory: 256 MB (configurable)
├─ Disk I/O: 10 MB/s (configurable)
├─ Network: 1 MB/s (configurable)
└─ Processes: 10 max (configurable)

Example cgroup configuration:
/sys/fs/cgroup/magflock/extensions/magrag/
├─ cpu.max: 10000 100000 (10% of one core)
├─ memory.max: 268435456 (256 MB)
├─ io.max: 8:0 rbps=10485760 wbps=10485760 (10 MB/s)
└─ pids.max: 10
```

**Docker (containerization):**

```
Extension runs in Docker container:
├─ Base image: magflock/extension-runtime:latest
├─ No privileged mode
├─ No host network
├─ No host filesystem mounts
├─ Read-only root filesystem
└─ Writable /tmp only

Example Dockerfile:
FROM magflock/extension-runtime:latest
COPY extension/ /extension/
RUN chmod -R 555 /extension
WORKDIR /extension
USER magflock
CMD ["php", "extension.php"]

Resource limits:
docker run \
  --cpus=0.1 \
  --memory=256m \
  --memory-swap=256m \
  --pids-limit=10 \
  --network=magflock-extensions \
  --read-only \
  --tmpfs /tmp:size=100m \
  magflock/magrag:1.2.3
```

### Filesystem Isolation Details

**Directory Structure:**

```
/extensions/
├─ magrag/
│  ├─ code/ (read-only)
│  │  ├─ extension.php
│  │  ├─ manifest.json
│  │  └─ lib/
│  ├─ config/ (read-only after install)
│  │  └─ config.json
│  ├─ data/ (read-write, extension-specific)
│  │  └─ cache/
│  └─ logs/ (read-write, extension-specific)
│     └─ extension.log
└─ magmqtt/
   └─ ...

/tmp/extensions/ (auto-cleaned every 24 hours)
├─ magrag/
│  └─ temp_files/
└─ magmqtt/
   └─ temp_files/
```

**Filesystem Permissions:**

```
Extension process runs as user: magflock-ext-{extension_id}
UID: 10000 + extension_id_hash
GID: 10000

Permissions:
/extensions/{extension_id}/code/     → 555 (r-xr-xr-x)
/extensions/{extension_id}/config/   → 444 (r--r--r--)
/extensions/{extension_id}/data/     → 700 (rwx------)
/extensions/{extension_id}/logs/     → 700 (rwx------)
/tmp/extensions/{extension_id}/      → 700 (rwx------)

All other directories: No access (enforced by chroot or mount namespace)
```

**Filesystem Capability Enforcement:**

```
filesystem.read capability:
├─ Can read files in /extensions/{extension_id}/code/
├─ Can read files in /extensions/{extension_id}/config/
├─ Can read files in /extensions/{extension_id}/data/
└─ Cannot read files outside extension directory

filesystem.write capability:
├─ Can write files in /extensions/{extension_id}/data/
├─ Can write files in /extensions/{extension_id}/logs/
├─ Can write files in /tmp/extensions/{extension_id}/
└─ Cannot write files outside these directories

filesystem.temp_only capability:
├─ Can write files in /tmp/extensions/{extension_id}/ only
├─ Files auto-deleted after 24 hours
└─ Max size: 100 MB per extension
```

### Network Isolation Details

**Firewall Rules (iptables):**

```
Default policy: DROP (deny all)

If extension has network.outbound capability:
├─ Allow outbound HTTP (port 80)
├─ Allow outbound HTTPS (port 443)
├─ Allow DNS (port 53)
└─ Deny all other outbound traffic

If extension has network.inbound capability:
├─ Allow inbound webhooks on assigned port
├─ Port range: 20000-29999 (one port per extension)
└─ Deny all other inbound traffic

Example iptables rules for MagRAG extension:
# Allow outbound HTTPS (for OpenAI API)
iptables -A OUTPUT -m owner --uid-owner 10001 -p tcp --dport 443 -j ACCEPT

# Allow DNS
iptables -A OUTPUT -m owner --uid-owner 10001 -p udp --dport 53 -j ACCEPT

# Deny all other outbound
iptables -A OUTPUT -m owner --uid-owner 10001 -j DROP

# Deny all inbound (no network.inbound capability)
iptables -A INPUT -m owner --uid-owner 10001 -j DROP
```

**Network Monitoring:**

```
All network calls are logged:
{
  "timestamp": "2025-10-05T10:23:45.123Z",
  "extension_id": "magflock/magrag",
  "direction": "outbound",
  "protocol": "https",
  "destination": "api.openai.com:443",
  "bytes_sent": 1024,
  "bytes_received": 2048,
  "duration_ms": 250,
  "status": "success"
}

Anomaly detection:
├─ If extension makes > 1000 requests/minute → Alert
├─ If extension connects to unexpected domain → Alert
├─ If extension sends > 100 MB/hour → Alert
└─ If extension receives > 1 GB/hour → Alert
```

**DNS Restrictions:**

```
Extensions can only resolve public DNS:
├─ Allowed: api.openai.com, api.stripe.com, etc.
├─ Blocked: localhost, 127.0.0.1, 192.168.*, 10.*, 172.16.*
└─ Blocked: Internal MagMoBo service names

DNS resolver configuration:
nameserver 8.8.8.8
nameserver 1.1.1.1
options timeout:2 attempts:2

DNS firewall rules:
├─ Block queries for internal domains
├─ Block queries for private IP ranges
└─ Log all DNS queries
```

### API Isolation Details

**Kernel API Surface:**

Extensions can only call kernel API methods:

```
Kernel API Methods:
├─ ExecuteQuery(sql, params) → Result
├─ ExecuteHook(hook_name, context) → Result
├─ PublishEvent(event) → void
├─ SubscribeEvent(topic, handler) → void
├─ GetConfig(key) → value
├─ SetConfig(key, value) → void
├─ Log(level, message, context) → void
├─ HttpRequest(url, options) → Response
├─ QueueJob(job_name, params) → JobID
└─ GetHealthStatus() → HealthStatus

Extensions CANNOT call:
├─ Component methods directly (MagDS, MagUI, etc.)
├─ Other extension methods
├─ MagMoBo core methods
└─ System methods (exec, shell_exec, etc.)
```

**API Call Validation:**

```
Every kernel API call is validated:
1. Check extension is ENABLED
2. Check extension has required capability
3. Check rate limit not exceeded
4. Validate parameters (schema check)
5. Execute method (sandboxed)
6. Validate result (schema check)
7. Audit log entry
8. Return result to extension

Example validation for ExecuteQuery:
kernel.ExecuteQuery(sql, params):
  1. Check extension has "database.read" or "database.write"
  2. Check rate limit: < 100 queries/minute
  3. Validate SQL: No DROP, TRUNCATE, etc. (unless database.admin)
  4. Validate params: No SQL injection
  5. Execute query via MagDS adapter
  6. Validate result: < 1000 rows (unless higher limit granted)
  7. Audit log: {action: "query.executed", sql: "...", rows: 5}
  8. Return result
```

**Rate Limiting:**

```
Rate limits per extension:
├─ API calls: 1000/minute (configurable)
├─ Database queries: 100/minute (configurable)
├─ Event publications: 100/minute (configurable)
├─ HTTP requests: 100/minute (configurable)
└─ Background jobs: 10/minute (configurable)

Rate limit algorithm: Token bucket
├─ Bucket size: 1000 tokens
├─ Refill rate: 1000 tokens/minute
├─ Each API call consumes 1 token
└─ If bucket empty → Error: "Rate limit exceeded"

Rate limit headers (returned to extension):
X-RateLimit-Limit: 1000
X-RateLimit-Remaining: 750
X-RateLimit-Reset: 1696512000
```

### Language Runtime Isolation

**PHP Extensions:**

```
PHP configuration (php.ini):
disable_functions = exec,passthru,shell_exec,system,proc_open,popen,curl_exec,curl_multi_exec,parse_ini_file,show_source
open_basedir = /extensions/{extension_id}/:/tmp/extensions/{extension_id}/
memory_limit = 256M
max_execution_time = 30
upload_max_filesize = 10M
post_max_size = 10M
allow_url_fopen = Off
allow_url_include = Off

Additional restrictions:
├─ No eval() or create_function()
├─ No reflection (unless capability granted)
├─ No file_get_contents() on URLs
└─ No stream wrappers (except file://)

Allowed functions:
├─ Database: PDO, mysqli (via kernel API only)
├─ String: strlen, substr, str_replace, etc.
├─ Array: array_map, array_filter, etc.
├─ JSON: json_encode, json_decode
└─ Math: abs, ceil, floor, round, etc.
```

**Node.js Extensions:**

```
VM2 sandbox configuration:
const {VM} = require('vm2');

const vm = new VM({
  timeout: 30000, // 30 seconds
  sandbox: {
    // Whitelisted globals
    console: sandboxedConsole,
    setTimeout: sandboxedSetTimeout,
    setInterval: sandboxedSetInterval,
    Buffer: Buffer,
    
    // Kernel API
    kernel: kernelAPI,
  },
  require: {
    // Whitelisted modules
    external: ['lodash', 'moment', 'axios'],
    builtin: ['util', 'crypto'],
    root: '/extensions/{extension_id}/node_modules/',
    mock: {
      // Mock dangerous modules
      fs: sandboxedFS,
      child_process: {},
      net: {},
      http: sandboxedHTTP,
      https: sandboxedHTTPS,
    }
  }
});

// Run extension code
vm.run(extensionCode);

Blocked modules:
├─ fs (except sandboxed version)
├─ child_process
├─ net
├─ dgram
├─ cluster
└─ worker_threads

Sandboxed modules:
├─ fs: Only access to extension directory
├─ http/https: Only via kernel API (capability check)
└─ process: Limited access (no exit, no env vars)
```

**Python Extensions:**

```
RestrictedPython configuration:
from RestrictedPython import compile_restricted, safe_globals

# Compile extension code
code = compile_restricted(
    extension_code,
    filename='<extension>',
    mode='exec'
)

# Safe globals (whitelisted)
safe_globals_dict = {
    '__builtins__': safe_globals,
    'kernel': kernel_api,
    'json': json,
    'datetime': datetime,
    'math': math,
    're': re,
}

# Execute extension code
exec(code, safe_globals_dict)

Blocked functions:
├─ __import__ (except whitelisted modules)
├─ open (except via kernel API)
├─ exec, eval, compile
├─ input, raw_input
├─ file, execfile
└─ reload, __builtins__

Whitelisted modules:
├─ json, datetime, math, re
├─ collections, itertools, functools
├─ hashlib, hmac, base64
└─ requests (via kernel API only)

Resource limits:
├─ Max recursion depth: 100
├─ Max string length: 1 MB
├─ Max list length: 10,000
└─ Max dict size: 10,000
```

### Escape Detection & Prevention

**Sandbox Escape Attempts:**

```
Common escape techniques (detected and blocked):
1. Path traversal: ../../etc/passwd
   └─ Blocked by: open_basedir, chroot

2. Command injection: ; rm -rf /
   └─ Blocked by: disable_functions, no shell access

3. Code injection: eval($_GET['code'])
   └─ Blocked by: No eval, input validation

4. File inclusion: include($_GET['file'])
   └─ Blocked by: allow_url_include = Off

5. Symlink attacks: ln -s /etc/passwd data/passwd
   └─ Blocked by: Filesystem permissions, chroot

6. Process spawning: exec('bash')
   └─ Blocked by: disable_functions, no fork

7. Network pivoting: Connect to internal services
   └─ Blocked by: Firewall rules, DNS restrictions

8. Resource exhaustion: while(true) {}
   └─ Blocked by: max_execution_time, CPU limits

9. Memory exhaustion: $a = str_repeat('x', 1e9)
   └─ Blocked by: memory_limit

10. Privilege escalation: setuid(0)
    └─ Blocked by: User namespace, no capabilities
```

**Escape Detection:**

```
Kernel monitors for escape attempts:
├─ Syscall monitoring (seccomp-bpf)
├─ File access monitoring (inotify)
├─ Network monitoring (iptables logging)
├─ Process monitoring (process tree inspection)
└─ Resource monitoring (cgroups)

If escape attempt detected:
1. Immediate extension termination (SIGKILL)
2. Automatic disable
3. Alert admin (critical severity)
4. Audit log entry (critical severity)
5. Report to MagFlock security team (if opted in)
6. Extension blacklisted (cannot be re-enabled without review)
7. All projects using extension notified
```

**Seccomp-BPF (Linux):**

```
Syscall whitelist (allowed):
├─ read, write, open, close
├─ stat, fstat, lstat
├─ mmap, munmap, mprotect
├─ brk, sbrk
├─ socket, connect, send, recv (if network capability)
└─ exit, exit_group

Syscall blacklist (blocked):
├─ execve, fork, clone, vfork
├─ kill, tkill, tgkill
├─ ptrace, process_vm_readv, process_vm_writev
├─ mount, umount, pivot_root
├─ reboot, kexec_load
├─ setuid, setgid, setreuid, setregid
└─ ioctl (most operations)

Example seccomp-bpf filter:
seccomp_rule_add(ctx, SCMP_ACT_ALLOW, SCMP_SYS(read), 0);
seccomp_rule_add(ctx, SCMP_ACT_ALLOW, SCMP_SYS(write), 0);
seccomp_rule_add(ctx, SCMP_ACT_KILL, SCMP_SYS(execve), 0);
seccomp_rule_add(ctx, SCMP_ACT_KILL, SCMP_SYS(fork), 0);
seccomp_load(ctx);
```

---

## Extension Manifest Specification

### Manifest Schema

Every extension must include a `manifest.json` file:

```json
{
  "$schema": "https://magflock.com/schemas/extension-manifest-v1.json",
  
  "id": "magflock/magrag",
  "name": "MagRAG",
  "display_name": "MagRAG - Document Search & AI",
  "description": "Add vector search and AI-powered document querying to your databases",
  "version": "1.2.3",
  "author": "MagFlock Team",
  "author_email": "extensions@magflock.com",
  "license": "MIT",
  "homepage": "https://magflock.com/extensions/magrag",
  "repository": "https://github.com/magflock/magrag",
  "documentation": "https://docs.magflock.com/extensions/magrag",
  "icon": "icon.svg",
  "screenshots": [
    "screenshots/query-interface.png",
    "screenshots/document-upload.png"
  ],
  
  "compatibility": {
    "min_magmobo_version": "1.0.0",
    "max_magmobo_version": "2.0.0",
    "php_version": ">=8.1",
    "postgres_version": ">=14.0"
  },
  
  "dependencies": {
    "required": [],
    "optional": ["magflock/maganalytics"],
    "conflicts": ["other-vendor/rag-extension"]
  },
  
  "capabilities": {
    "database.read": {
      "reason": "Read documents from database for vectorization",
      "optional": false
    },
    "database.write": {
      "reason": "Store vector embeddings in database",
      "optional": false
    },
    "database.schema": {
      "reason": "Create vector extension and embedding tables",
      "optional": false
    },
    "network.outbound": {
      "reason": "Call OpenAI API for embeddings",
      "optional": true,
      "fallback": "Use local embedding model if denied"
    },
    "system.background_jobs": {
      "reason": "Process large document batches asynchronously",
      "optional": false
    }
  },
  
  "hooks": {
    "database.after_migration": {
      "handler": "onMigrationComplete",
      "priority": 50,
      "description": "Update vector indexes after schema changes"
    },
    "database.before_backup": {
      "handler": "onBeforeBackup",
      "priority": 80,
      "description": "Flush vector cache before backup"
    }
  },
  
  "subscriptions": [
    {
      "topic": "database.query.executed",
      "handler": "onQueryExecuted",
      "filter": {
        "data.duration_ms": {"$gt": 1000}
      },
      "priority": 50,
      "description": "Log slow queries for optimization"
    }
  ],
  
  "configuration": {
    "schema": {
      "type": "object",
      "properties": {
        "embedding_model": {
          "type": "string",
          "enum": ["openai", "local"],
          "default": "local",
          "description": "Embedding model to use"
        },
        "openai_api_key": {
          "type": "string",
          "description": "OpenAI API key (required if embedding_model=openai)",
          "secret": true
        },
        "chunk_size": {
          "type": "integer",
          "minimum": 100,
          "maximum": 2000,
          "default": 500,
          "description": "Document chunk size for vectorization"
        },
        "similarity_threshold": {
          "type": "number",
          "minimum": 0,
          "maximum": 1,
          "default": 0.7,
          "description": "Minimum similarity score for search results"
        }
      },
      "required": ["embedding_model"]
    },
    "ui": {
      "embedding_model": {
        "label": "Embedding Model",
        "help": "Choose between OpenAI (cloud) or local embedding model"
      },
      "openai_api_key": {
        "label": "OpenAI API Key",
        "help": "Get your API key from https://platform.openai.com/api-keys",
        "type": "password"
      },
      "chunk_size": {
        "label": "Chunk Size",
        "help": "Larger chunks = more context, smaller chunks = more precise"
      },
      "similarity_threshold": {
        "label": "Similarity Threshold",
        "help": "Higher threshold = more relevant results, fewer results"
      }
    }
  },
  
  "migrations": {
    "directory": "migrations",
    "naming": "timestamp",
    "reversible": true
  },
  
  "ui": {
    "widgets": [
      {
        "id": "magrag-search",
        "name": "Document Search",
        "component": "widgets/SearchWidget.php",
        "placement": ["dashboard", "project"],
        "size": "medium"
      }
    ],
    "panels": [
      {
        "id": "magrag-documents",
        "name": "Documents",
        "component": "panels/DocumentsPanel.php",
        "icon": "document-text",
        "route": "/magrag/documents"
      }
    ],
    "menu_items": [
      {
        "label": "Document Search",
        "route": "/magrag/search",
        "icon": "search",
        "parent": "tools"
      }
    ]
  },
  
  "api": {
    "endpoints": [
      {
        "method": "POST",
        "path": "/magrag/search",
        "handler": "api/SearchController@search",
        "auth": "required",
        "rate_limit": "100/minute"
      },
      {
        "method": "POST",
        "path": "/magrag/documents",
        "handler": "api/DocumentController@upload",
        "auth": "required",
        "rate_limit": "10/minute"
      }
    ]
  },
  
  "cli": {
    "commands": [
      {
        "name": "magrag:index",
        "description": "Index documents for vector search",
        "handler": "cli/IndexCommand.php"
      },
      {
        "name": "magrag:reindex",
        "description": "Re-index all documents",
        "handler": "cli/ReindexCommand.php"
      }
    ]
  },
  
  "background_jobs": [
    {
      "name": "magrag:process-documents",
      "handler": "jobs/ProcessDocumentsJob.php",
      "schedule": "*/5 * * * *",
      "description": "Process pending documents every 5 minutes"
    }
  ],
  
  "health_checks": [
    {
      "name": "database_connection",
      "critical": true,
      "description": "Check database connection"
    },
    {
      "name": "vector_extension",
      "critical": true,
      "description": "Check pgvector extension is installed"
    },
    {
      "name": "openai_api",
      "critical": false,
      "description": "Check OpenAI API availability (if configured)"
    }
  ],
  
  "metadata": {
    "category": "AI & ML",
    "tags": ["vector-search", "ai", "rag", "documents", "embeddings"],
    "pricing": "free",
    "support_email": "support@magflock.com",
    "support_url": "https://support.magflock.com/magrag",
    "changelog_url": "https://github.com/magflock/magrag/blob/main/CHANGELOG.md"
  }
}
```

### Manifest Validation

Kernel validates manifest on installation:

```
Validation Rules:
1. Schema validation (JSON Schema)
2. Required fields present
3. Version is valid semver
4. Compatibility versions are valid semver
5. Capabilities are valid (exist in capability taxonomy)
6. Hooks are valid (exist in hook registry)
7. Event topics are valid (exist in event catalog)
8. Configuration schema is valid JSON Schema
9. File paths exist (icon, screenshots, handlers)
10. No duplicate IDs (hooks, subscriptions, UI components)

If validation fails:
├─ Display detailed error message
├─ Show which field failed validation
├─ Show expected format
└─ Abort installation
```

### Manifest Versioning

Manifest schema is versioned:

```
Current version: v1
Schema URL: https://magflock.com/schemas/extension-manifest-v1.json

Future versions:
├─ v2: Add new fields, deprecate old fields
├─ v3: Breaking changes (rare)
└─ Kernel supports multiple versions (backwards compatible)

Version detection:
├─ Check $schema field in manifest
├─ If missing → Assume v1
├─ If present → Validate against specified version
└─ If unsupported version → Error
```

---

## Component-Specific Integration

### MagDS (Storage Component) Integration

**Hooks Provided:**

```
Provisioning Hooks:
├─ database.before_create
│  ├─ Context: {database_name, owner, template}
│  ├─ Result: {allow: boolean, modified_config: object}
│  └─ Use case: Validate database name, enforce naming conventions

├─ database.after_create
│  ├─ Context: {database_name, connection_string}
│  ├─ Result: {success: boolean}
│  └─ Use case: Initialize extension-specific tables, install extensions

├─ database.extension_profile_selected
│  ├─ Context: {database_name, profile: string, extensions: array}
│  ├─ Result: {additional_extensions: array}
│  └─ Use case: Add custom extensions to profile

Schema Hooks:
├─ database.before_migration
│  ├─ Context: {database_name, migration_file, migration_sql}
│  ├─ Result: {allow: boolean, warnings: array}
│  └─ Use case: Validate migration SQL, check for destructive changes

├─ database.after_migration
│  ├─ Context: {database_name, migration_file, success: boolean}
│  ├─ Result: {success: boolean}
│  └─ Use case: Update indexes, refresh materialized views

├─ database.rls_policy_created
│  ├─ Context: {database_name, table, policy_name, policy_sql}
│  ├─ Result: {success: boolean}
│  └─ Use case: Audit RLS policy changes

Operational Hooks:
├─ database.before_backup
│  ├─ Context: {database_name, backup_type, destination}
│  ├─ Result: {allow: boolean, pre_backup_tasks: array}
│  └─ Use case: Flush caches, checkpoint WAL

├─ database.after_backup
│  ├─ Context: {database_name, backup_file, size_bytes, duration_ms}
│  ├─ Result: {success: boolean}
│  └─ Use case: Upload backup to external storage, send notification

├─ database.before_restore
│  ├─ Context: {database_name, backup_file, restore_point}
│  ├─ Result: {allow: boolean, warnings: array}
│  └─ Use case: Validate backup file, warn about data loss

├─ database.after_restore
│  ├─ Context: {database_name, success: boolean, duration_ms}
│  ├─ Result: {success: boolean}
│  └─ Use case: Verify data integrity, rebuild indexes

├─ database.health_check
│  ├─ Context: {database_name, metrics: object}
│  ├─ Result: {status: string, recommendations: array}
│  └─ Use case: Custom health checks, performance recommendations
```

**Events Emitted:**

```
database.query.executed
database.migration.applied
database.backup.completed
database.restore.completed
database.connection.failed
database.connection.recovered
database.disk_space.low
database.replication.lag
database.deadlock.detected
database.slow_query.detected
```

**Adapter Implementation:**

```
MagDSAdapter:
├─ Translates kernel API calls to PostgreSQL queries
├─ Enforces RLS policies
├─ Validates SQL (prevent SQL injection)
├─ Applies query timeouts
├─ Logs all queries (if audit enabled)
└─ Emits events for monitoring

Example:
kernel.ExecuteQuery("SELECT * FROM users WHERE id = $1", [123])
  ↓
MagDSAdapter.ExecuteHook("database.query", context)
  ↓
MagDS.ExecuteQuery(prepared_statement)
  ↓
PostgreSQL: SELECT * FROM users WHERE id = $1 AND (RLS policy)
  ↓
Result: {rows: [{id: 123, name: "Alice"}], duration_ms: 5}
  ↓
MagDSAdapter.EmitEvent("database.query.executed", {...})
  ↓
Return result to extension
```

### MagUI (Control Plane) Integration

**Hooks Provided:**

```
UI Extension Points:
├─ ui.register_widget
│  ├─ Context: {widget_id, component, placement, size}
│  ├─ Result: {success: boolean}
│  └─ Use case: Add custom widgets to dashboard

├─ ui.register_panel
│  ├─ Context: {panel_id, component, route, icon}
│  ├─ Result: {success: boolean}
│  └─ Use case: Add full-page panels to UI

├─ ui.extend_schema_explorer
│  ├─ Context: {table, columns}
│  ├─ Result: {additional_actions: array}
│  └─ Use case: Add custom actions to schema explorer

Workflow Hooks:
├─ ui.before_project_create
│  ├─ Context: {project_name, database_config}
│  ├─ Result: {allow: boolean, warnings: array}
│  └─ Use case: Validate project name, enforce quotas

├─ ui.after_project_create
│  ├─ Context: {project_id, project_name}
│  ├─ Result: {success: boolean}
│  └─ Use case: Initialize extension data, send welcome email

├─ ui.before_schema_change
│  ├─ Context: {project_id, change_type, change_sql}
│  ├─ Result: {allow: boolean, warnings: array}
│  └─ Use case: Validate schema changes, warn about breaking changes

├─ ui.after_schema_change
│  ├─ Context: {project_id, change_type, success: boolean}
│  ├─ Result: {success: boolean}
│  └─ Use case: Update documentation, notify team

Audit Integration:
├─ ui.user_action
│  ├─ Context: {user_id, action, resource, details}
│  ├─ Result: {success: boolean}
│  └─ Use case: Custom audit logging, compliance tracking
```

**Events Emitted:**

```
ui.project.created
ui.project.deleted
ui.schema.changed
ui.user.invited
ui.user.removed
ui.settings.changed
ui.widget.rendered
ui.panel.opened
```

**Widget Example:**

```php
// widgets/SearchWidget.php
class SearchWidget extends Widget {
  public function render(): string {
    return view('magrag::search-widget', [
      'recent_searches' => $this->getRecentSearches(),
      'popular_documents' => $this->getPopularDocuments(),
    ]);
  }
  
  private function getRecentSearches(): array {
    return $this->kernel->ExecuteQuery(
      "SELECT * FROM magrag_searches WHERE user_id = $1 ORDER BY created_at DESC LIMIT 5",
      [$this->user->id]
    );
  }
}
```

### MagGate (API Layer) Integration

**Hooks Provided:**

```
Endpoint Hooks:
├─ api.register_endpoint
│  ├─ Context: {method, path, handler, auth, rate_limit}
│  ├─ Result: {success: boolean}
│  └─ Use case: Add custom API endpoints

├─ api.before_request
│  ├─ Context: {method, path, headers, body, user_id}
│  ├─ Result: {allow: boolean, modified_request: object}
│  └─ Use case: Request validation, authentication, rate limiting

├─ api.after_request
│  ├─ Context: {method, path, status_code, response_body, duration_ms}
│  ├─ Result: {modified_response: object}
│  └─ Use case: Response transformation, logging, analytics

├─ api.on_error
│  ├─ Context: {method, path, error, stack_trace}
│  ├─ Result: {custom_error_response: object}
│  └─ Use case: Custom error handling, error tracking

Policy Hooks:
├─ api.rbac_check
│  ├─ Context: {user_id, resource, action}
│  ├─ Result: {allow: boolean, reason: string}
│  └─ Use case: Custom RBAC logic

├─ api.rate_limit_check
│  ├─ Context: {user_id, endpoint, current_count}
│  ├─ Result: {allow: boolean, limit: int, remaining: int}
│  └─ Use case: Custom rate limiting strategies

├─ api.usage_analytics
│  ├─ Context: {user_id, endpoint, duration_ms, status_code}
│  ├─ Result: {success: boolean}
│  └─ Use case: Custom analytics, billing
```

**Events Emitted:**

```
api.request.received
api.response.sent
api.error.occurred
api.rate_limit.exceeded
api.auth.failed
api.endpoint.registered
```

**Custom Endpoint Example:**

```php
// api/SearchController.php
class SearchController {
  public function search(Request $request): Response {
    // Validate request
    $validated = $request->validate([
      'query' => 'required|string|max:500',
      'limit' => 'integer|min:1|max:100',
    ]);
    
    // Execute vector search
    $results = $this->kernel->ExecuteQuery(
      "SELECT * FROM magrag_search($1, $2)",
      [$validated['query'], $validated['limit'] ?? 10]
    );
    
    // Return response
    return response()->json([
      'results' => $results,
      'count' => count($results),
    ]);
  }
}
```

### MagWS (Real-Time) Integration

**Hooks Provided:**

```
Channel Registry:
├─ realtime.register_channel
│  ├─ Context: {channel_name, auth_required, presence_enabled}
│  ├─ Result: {success: boolean}
│  └─ Use case: Register custom WebSocket channels

├─ realtime.subscribe_to_channel
│  ├─ Context: {connection_id, channel_name, user_id}
│  ├─ Result: {allow: boolean}
│  └─ Use case: Custom channel authorization

├─ realtime.unsubscribe_from_channel
│  ├─ Context: {connection_id, channel_name}
│  ├─ Result: {success: boolean}
│  └─ Use case: Cleanup on unsubscribe

Broadcast Hooks:
├─ realtime.before_broadcast
│  ├─ Context: {channel_name, event_name, data}
│  ├─ Result: {allow: boolean, modified_data: object}
│  └─ Use case: Message filtering, transformation

├─ realtime.after_broadcast
│  ├─ Context: {channel_name, event_name, recipient_count}
│  ├─ Result: {success: boolean}
│  └─ Use case: Analytics, logging

Presence Hooks:
├─ realtime.presence_join
│  ├─ Context: {channel_name, user_id, user_info}
│  ├─ Result: {success: boolean}
│  └─ Use case: Track user presence

├─ realtime.presence_leave
│  ├─ Context: {channel_name, user_id}
│  ├─ Result: {success: boolean}
│  └─ Use case: Cleanup presence data
```

**Events Emitted:**

```
realtime.connection.opened
realtime.connection.closed
realtime.subscription.created
realtime.subscription.removed
realtime.message.sent
realtime.message.received
realtime.presence.updated
```

**Custom Channel Example:**

```php
// Register custom channel
$this->kernel->ExecuteHook('realtime.register_channel', [
  'channel_name' => 'magrag:search-results',
  'auth_required' => true,
  'presence_enabled' => false,
]);

// Broadcast search results
$this->kernel->ExecuteHook('realtime.broadcast', [
  'channel_name' => 'magrag:search-results',
  'event_name' => 'results.updated',
  'data' => [
    'query' => 'machine learning',
    'results' => $results,
    'timestamp' => time(),
  ],
]);
```

### MagCLI Integration

**Hooks Provided:**

```
Command Injection:
├─ cli.register_command
│  ├─ Context: {command_name, description, handler}
│  ├─ Result: {success: boolean}
│  └─ Use case: Add custom CLI commands

├─ cli.before_command
│  ├─ Context: {command_name, arguments, options}
│  ├─ Result: {allow: boolean}
│  └─ Use case: Validate command arguments

├─ cli.after_command
│  ├─ Context: {command_name, exit_code, output}
│  ├─ Result: {success: boolean}
│  └─ Use case: Log command execution

Output Formatting:
├─ cli.format_output
│  ├─ Context: {data, format: 'table'|'json'|'yaml'|'csv'}
│  ├─ Result: {formatted_output: string}
│  └─ Use case: Custom output formats
```

**Events Emitted:**

```
cli.command.executed
cli.command.failed
cli.output.generated
```

**Custom Command Example:**

```php
// cli/IndexCommand.php
class IndexCommand extends Command {
  protected $signature = 'magrag:index {--project=}';
  protected $description = 'Index documents for vector search';
  
  public function handle(): int {
    $projectId = $this->option('project');
    
    $this->info('Starting document indexing...');
    
    // Get pending documents
    $documents = $this->kernel->ExecuteQuery(
      "SELECT * FROM magrag_documents WHERE indexed = false AND project_id = $1",
      [$projectId]
    );
    
    $this->info("Found {$documents->count()} documents to index");
    
    // Index each document
    $progressBar = $this->output->createProgressBar($documents->count());
    
    foreach ($documents as $document) {
      $this->indexDocument($document);
      $progressBar->advance();
    }
    
    $progressBar->finish();
    $this->info("\nIndexing complete!");
    
    return 0;
  }
}
```

### MagAuth (PSU Component) Integration

**Hooks Provided:**

```
Provider Plugins:
├─ auth.register_provider
│  ├─ Context: {provider_name, provider_type: 'sso'|'oidc'|'saml'}
│  ├─ Result: {success: boolean}
│  └─ Use case: Add custom authentication providers

├─ auth.before_login
│  ├─ Context: {provider, user_identifier, credentials}
│  ├─ Result: {allow: boolean, reason: string}
│  └─ Use case: Custom login validation, MFA

├─ auth.after_login
│  ├─ Context: {user_id, provider, ip_address}
│  ├─ Result: {success: boolean}
│  └─ Use case: Log login, send notification

├─ auth.before_logout
│  ├─ Context: {user_id, session_id}
│  ├─ Result: {allow: boolean}
│  └─ Use case: Cleanup, audit

├─ auth.after_logout
│  ├─ Context: {user_id, session_duration_ms}
│  ├─ Result: {success: boolean}
│  └─ Use case: Analytics, cleanup

Policy Hooks:
├─ auth.role_template
│  ├─ Context: {role_name, permissions}
│  ├─ Result: {additional_permissions: array}
│  └─ Use case: Custom role templates

├─ auth.permission_check
│  ├─ Context: {user_id, resource, action}
│  ├─ Result: {allow: boolean, reason: string}
│  └─ Use case: Custom permission logic

├─ auth.token_refresh
│  ├─ Context: {user_id, old_token, new_token}
│  ├─ Result: {success: boolean}
│  └─ Use case: Token rotation, audit
```

**Events Emitted:**

```
auth.user.login
auth.user.logout
auth.token.issued
auth.token.expired
auth.token.refreshed
auth.permission.denied
auth.role.assigned
auth.role.revoked
```

---

## Pro Edition Strategy

### Pro as Extension Bundle

**Core Principle:**\
Pro Edition is NOT a separate product. It's a **bundle of extensions** installed on top of Community Edition.

**Benefits:**

```
✅ No migration needed (CE → Pro is just installing extensions)
✅ No code duplication (CE and Pro share same core)
✅ No feature flags (Pro features are extensions, not conditionals)
✅ Easy upgrades (Pro extensions upgrade independently)
✅ Flexible pricing (users can buy individual Pro extensions)
```

### Pro Pack Extensions

**Pro Pack includes:**

```
1. MagMultiTenant
   ├─ Capability: Multi-tenancy with tenant isolation
   ├─ Features:
   │  ├─ Tenant provisioning (automatic database creation)
   │  ├─ Tenant isolation (RLS policies, schema separation)
   │  ├─ Tenant management UI (admin panel)
   │  └─ Tenant analytics (usage, billing)
   └─ Hooks:
      ├─ database.before_create (add tenant_id)
      ├─ database.after_create (setup RLS policies)
      └─ api.before_request (inject tenant context)

2. MagBilling
   ├─ Capability: Subscription management, usage tracking
   ├─ Features:
   │  ├─ Stripe integration (subscriptions, invoices)
   │  ├─ Usage metering (API calls, storage, queries)
   │  ├─ Billing UI (invoices, payment methods)
   │  └─ Webhooks (payment success, failure)
   └─ Hooks:
      ├─ api.after_request (track API usage)
      ├─ database.query.executed (track query usage)
      └─ ui
```
