# 🎯 STRATEGIC DEEP DIVE: MAGFLOCK THREAT MODEL & ARCHITECTURE

You're absolutely right. Before we write a single line of code, we need to **think like attackers, architects, and users simultaneously**.

---

## 🧠 **THE MAGFLOCK ECOSYSTEM MAP**

### **Who Uses MagFlock?**

```
┌─────────────────────────────────────────────────────────────────┐
│                        MAGFLOCK USERS                           │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│  👤 HUMAN USERS                                                │
│  ├─ Solo Dev (1 project, 10 API calls/day)                    │
│  ├─ Startup Team (5 devs, 10 projects, 10k API calls/day)     │
│  ├─ Enterprise (100 devs, 1000 projects, 10M API calls/day)   │
│  └─ Malicious Actor (trying to exploit, steal, DDoS)          │
│                                                                 │
│  🤖 AI AGENTS                                                  │
│  ├─ ChatGPT Plugin (queries user's data)                      │
│  ├─ Autonomous Agent (writes data, triggers workflows)        │
│  ├─ RAG System (indexes documents, answers questions)         │
│  └─ Rogue AI (tries to access other users' data)              │
│                                                                 │
│  📱 IoT DEVICES                                                │
│  ├─ Smart Home (1 device, 1 API call/minute)                  │
│  ├─ Industrial Sensors (1000 devices, 100 API calls/second)   │
│  ├─ Fleet Management (10k vehicles, constant streaming)       │
│  └─ Compromised Device (botnet, DDoS participant)             │
│                                                                 │
│  🔌 EXTENSIONS                                                 │
│  ├─ Trusted (MagRAG, MagGate - built by you)                  │
│  ├─ Verified (marketplace extensions, code-reviewed)          │
│  ├─ Community (unverified, user-submitted)                    │
│  └─ Malicious (tries to steal data, inject code)              │
│                                                                 │
│  🌐 EXTERNAL SERVICES                                          │
│  ├─ Webhooks (receives events from MagFlock)                  │
│  ├─ OAuth Providers (Google, GitHub, etc.)  (pro)               │
│  ├─ Payment Processors (Stripe, PayPal) (pro)                   │
│  └─ Spoofed Services (phishing, MITM attacks) (partial CE & PRO)│
│                                                                 │
└─────────────────────────────────────────────────────────────────┘
```

---

## 🎯 **USE CASE MATRIX**

### **Scenario 1: Solo Dev Building SaaS**
```
User: Sarah (solo dev)
Project: TaskMaster (todo app)
Scale: 100 users, 1k API calls/day
Needs:
  ✓ PostgreSQL database
  ✓ REST API (auto-generated)
  ✓ Realtime updates (WebSocket)
  ✓ User authentication
  ✓ File storage (avatars)
  
Threats:
  ⚠️ SQL injection via API
  ⚠️ Unauthorized data access (user A sees user B's todos)
  ⚠️ API key leaked in GitHub repo
  ⚠️ DDoS attack (competitor floods API)
  ⚠️ Data loss (accidental DELETE query)
```

### **Scenario 2: Startup with AI Agent**
```
User: TechCorp (5 devs)
Project: CustomerAI (AI customer support)
Scale: 1k customers, 100k API calls/day
Needs:
  ✓ PostgreSQL + pgvector (embeddings)
  ✓ MagRAG extension (natural language queries)
  ✓ AI agent with scoped permissions
  ✓ Audit log (compliance)
  ✓ Rate limiting (per customer)
  
Threats:
  ⚠️ AI agent accesses sensitive data (PII, credit cards)
  ⚠️ Prompt injection (user tricks AI into revealing data)
  ⚠️ AI generates malicious SQL (DROP TABLE)
  ⚠️ Agent credentials stolen (used to exfiltrate data)
  ⚠️ Compliance violation (GDPR, HIPAA)
```

### **Scenario 3: IoT Fleet Management**
```
User: LogisticsCo (enterprise)
Project: FleetTracker (10k vehicles)
Scale: 10k devices, 1M API calls/day
Needs:
  ✓ TimescaleDB (time-series data)
  ✓ MQTT broker (device communication)
  ✓ Geospatial queries (PostGIS)
  ✓ Edge sync (offline-first)
  ✓ Device authentication (per-device keys)
  
Threats:
  ⚠️ Device spoofing (fake GPS data)
  ⚠️ Botnet attack (compromised devices DDoS)
  ⚠️ Man-in-the-middle (intercept device data)
  ⚠️ Replay attack (resend old GPS coordinates)
  ⚠️ Resource exhaustion (1 device floods API)
```

### **Scenario 4: Malicious Extension Developer**
```
User: EvilCorp (attacker)
Project: "Free Analytics Extension"
Scale: Installed by 1000 users
Intent: Steal data, inject backdoors
Capabilities:
  ✗ Read all user data
  ✗ Modify database schema
  ✗ Exfiltrate API keys
  ✗ Install crypto miner
  ✗ Inject XSS into admin panel
  
Threats:
  ⚠️ Extension reads data from other projects
  ⚠️ Extension makes external HTTP calls (data exfiltration)
  ⚠️ Extension modifies MagMoBo core files
  ⚠️ Extension creates backdoor user account
  ⚠️ Extension mines cryptocurrency (resource theft)
```

### **Scenario 5: Spoofed MagFlock Instance**
```
Attacker: Creates fake "magflock.io" site
Target: Steal user credentials, API keys
Method: Phishing emails, typosquatting
Capabilities:
  ✗ Clone MagUI interface
  ✗ Harvest login credentials
  ✗ Steal API keys
  ✗ Redirect API calls to attacker's server
  
Threats:
  ⚠️ Users can't distinguish real from fake
  ⚠️ API keys work on both real and fake instances
  ⚠️ Data sent to attacker's database
  ⚠️ Attacker proxies requests (MITM)
```

---

## 🛡️ **SECURITY ARCHITECTURE**

### **Defense Layer 1: Identity & Authentication**

```
┌─────────────────────────────────────────────────────────────┐
│                    IDENTITY HIERARCHY                       │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  🏢 Organization (tenant isolation)                        │
│   └─ 👤 Human User (email + password + MFA)               │
│       ├─ 🔑 API Key (scoped, rotatable, revocable)        │
│       ├─ 🤖 AI Agent (identity, audit trail)              │
│       ├─ 📱 IoT Device (device cert, per-device key)      │
│       └─ 🔌 Extension (sandboxed, capability-based)       │
│                                                             │
└─────────────────────────────────────────────────────────────┘
```

#### **Authentication Methods**

| Identity Type | Auth Method | Rotation | Revocation | Audit |
|---------------|-------------|----------|------------|-------|
| **Human** | Email + Password + MFA | Password: 90 days | Instant | Full |
| **API Key** | Bearer token (JWT) | Manual/Auto | Instant | Full |
| **AI Agent** | Service account + JWT | Auto (24h) | Instant | Full |
| **IoT Device** | mTLS cert + device key | Auto (30d) | Instant | Full |
| **Extension** | Capability token | N/A (scoped) | Instant | Full |

#### **MFA Requirements**

```php
// cp_auth.mfa_policies
[
    'org_admin' => ['required' => true, 'methods' => ['totp', 'webauthn']],
    'project_owner' => ['required' => true, 'methods' => ['totp', 'sms']],
    'developer' => ['required' => false, 'methods' => ['totp']],
    'ai_agent' => ['required' => false], // Service accounts
    'iot_device' => ['required' => false], // Certificate-based
]
```

---

### **Defense Layer 2: Authorization (RBAC + ABAC)**

```
┌─────────────────────────────────────────────────────────────┐
│                  AUTHORIZATION MODEL                        │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  RBAC (Role-Based Access Control)                          │
│  ├─ Org Admin (full org access)                           │
│  ├─ Project Owner (full project access)                   │
│  ├─ Developer (read/write project data)                   │
│  ├─ Viewer (read-only)                                    │
│  └─ AI Agent (custom role, scoped permissions)            │
│                                                             │
│  ABAC (Attribute-Based Access Control)                    │
│  ├─ Time-based (only during business hours)               │
│  ├─ IP-based (only from office network)                   │
│  ├─ Resource-based (only tables X, Y, Z)                  │
│  ├─ Action-based (read-only, no DELETE)                   │
│  └─ Context-based (only if MFA verified)                  │
│                                                             │
└─────────────────────────────────────────────────────────────┘
```

#### **Permission Scoping**

```php
// API Key with scoped permissions
{
    "key_id": "ak_1234567890",
    "scopes": {
        "projects": ["proj_abc123"],           // Only this project
        "tables": ["users", "posts"],          // Only these tables
        "actions": ["read", "create"],         // No update/delete
        "rate_limit": 1000,                    // 1k requests/hour
        "ip_whitelist": ["203.0.113.0/24"],   // Only from this IP
        "expires_at": "2025-12-31T23:59:59Z"  // Time-limited
    }
}
```

#### **AI Agent Permissions**

```php
// AI agents get MINIMAL permissions by default
{
    "agent_id": "agent_xyz789",
    "type": "ai_agent",
    "permissions": {
        "read": ["users.name", "users.email"],  // Column-level
        "write": [],                             // No writes by default
        "execute": ["approved_functions"],       // Whitelist functions
        "rate_limit": 100,                       // Lower than humans
        "audit": "full",                         // Every query logged
        "require_approval": true                 // Human-in-the-loop
    }
}
```

---

### **Defense Layer 3: Data Isolation (Multi-Tenancy)**

```
┌─────────────────────────────────────────────────────────────┐
│                   TENANT ISOLATION                          │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  Strategy 1: Database-per-Project (Strongest)              │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐    │
│  │ Project A DB │  │ Project B DB │  │ Project C DB │    │
│  │ (isolated)   │  │ (isolated)   │  │ (isolated)   │    │
│  └──────────────┘  └──────────────┘  └──────────────┘    │
│  ✓ Complete isolation                                      │
│  ✓ Easy backup/restore per project                        │
│  ✗ Higher resource usage                                   │
│                                                             │
│  Strategy 2: Schema-per-Project (Balanced)                 │
│  ┌─────────────────────────────────────────────────┐      │
│  │              Shared Database                    │      │
│  │  ┌──────────┐  ┌──────────┐  ┌──────────┐     │      │
│  │  │ Schema A │  │ Schema B │  │ Schema C │     │      │
│  │  └──────────┘  └──────────┘  └──────────┘     │      │
│  └─────────────────────────────────────────────────┘      │
│  ✓ Good isolation                                          │
│  ✓ Lower resource usage                                    │
│  ✗ Shared connection pool                                  │
│                                                             │
│  Strategy 3: Row-Level Security (Weakest)                  │
│  ┌─────────────────────────────────────────────────┐      │
│  │              Shared Database + Schema           │      │
│  │  ┌────────────────────────────────────┐        │      │
│  │  │ Table: users                       │        │      │
│  │  │ ├─ user_1 (project_id = A)        │        │      │
│  │  │ ├─ user_2 (project_id = B)        │        │      │
│  │  │ └─ user_3 (project_id = C)        │        │      │
│  │  └────────────────────────────────────┘        │      │
│  └─────────────────────────────────────────────────┘      │
│  ✓ Lowest resource usage                                   │
│  ✗ Risk of data leakage (SQL injection, bugs)             │
│                                                             │
└─────────────────────────────────────────────────────────────┘
```

#### **Recommendation: Hybrid Approach**

```
Control Plane (magui_app):
  - Single database
  - Row-level security (cp_org.*, cp_proj.*)
  - Shared by all tenants

Data Plane (user projects):
  - Database-per-project (CE, Pro)
  - Schema-per-project (Enterprise, shared cluster)
  - Connection pooling per project
```

---

### **Defense Layer 4: Extension Sandboxing**

```
┌─────────────────────────────────────────────────────────────┐
│                  EXTENSION SANDBOX                          │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  🔒 Capability-Based Security                              │
│  ├─ Extension declares required capabilities               │
│  ├─ User approves capabilities during install              │
│  ├─ Runtime enforces capability limits                     │
│  └─ Revoke capabilities without uninstall                  │
│                                                             │
│  📦 Resource Quotas                                        │
│  ├─ CPU: Max 10% of 1 core                                │
│  ├─ Memory: Max 256 MB                                     │
│  ├─ Disk: Max 1 GB                                         │
│  ├─ Network: Max 100 req/min                               │
│  └─ Database: Max 1000 queries/hour                        │
│                                                             │
│  🚫 Restrictions                                           │
│  ├─ No file system access (except temp dir)               │
│  ├─ No shell execution                                     │
│  ├─ No raw SQL (only query builder)                       │
│  ├─ No access to other projects' data                     │
│  └─ No modification of MagMoBo core                       │
│                                                             │
└─────────────────────────────────────────────────────────────┘
```

#### **Extension Manifest (Capability Declaration)**

```json
{
    "name": "analytics-extension",
    "version": "1.0.0",
    "capabilities": {
        "database": {
            "read": ["users", "events"],
            "write": ["analytics_cache"],
            "max_queries_per_hour": 1000
        },
        "network": {
            "outbound": ["https://api.analytics.com"],
            "max_requests_per_minute": 10
        },
        "storage": {
            "max_size_mb": 100
        },
        "ui": {
            "admin_page": true,
            "dashboard_widget": true
        }
    },
    "signature": "sha256:abc123..." // Code signing
}
```

#### **Runtime Enforcement**

```php
// MagMoBo enforces capabilities at runtime
class ExtensionSandbox
{
    public function executeQuery(Extension $ext, string $query): Result {
        // Check if extension has database.read capability
        if (!$ext->hasCapability('database.read')) {
            throw new SecurityException('Extension lacks database.read capability');
        }
        
        // Check query quota
        if ($ext->getQueryCount() >= $ext->getMaxQueries()) {
            throw new QuotaExceededException('Query quota exceeded');
        }
        
        // Parse query, ensure only allowed tables
        $tables = $this->parseTablesFromQuery($query);
        foreach ($tables as $table) {
            if (!$ext->canAccessTable($table)) {
                throw new SecurityException("Extension cannot access table: {$table}");
            }
        }
        
        // Execute in isolated transaction
        return $this->db->transaction(function() use ($query) {
            return $this->db->query($query);
        });
    }
}
```

---

### **Defense Layer 5: Network Security**

```
┌─────────────────────────────────────────────────────────────┐
│                    NETWORK SECURITY                         │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  🌐 Public Internet                                        │
│       │                                                     │
│       ▼                                                     │
│  ┌─────────────────────────────────────┐                  │
│  │  WAF (Web Application Firewall)     │                  │
│  │  - DDoS protection                  │                  │
│  │  - SQL injection detection          │                  │
│  │  - XSS filtering                    │                  │
│  │  - Rate limiting (global)           │                  │
│  └──────────────┬──────────────────────┘                  │
│                 ▼                                           │
│  ┌─────────────────────────────────────┐                  │
│  │  Load Balancer (TLS termination)    │                  │
│  │  - HTTPS only (TLS 1.3)             │                  │
│  │  - Certificate pinning              │                  │
│  │  - HSTS headers                     │                  │
│  └──────────────┬──────────────────────┘                  │
│                 ▼                                           │
│  ┌─────────────────────────────────────┐                  │
│  │  API Gateway (MagGate)              │                  │
│  │  - API key validation               │                  │
│  │  - Rate limiting (per key)          │                  │
│  │  - Request signing                  │                  │
│  │  - Audit logging                    │                  │
│  └──────────────┬──────────────────────┘                  │
│                 ▼                                           │
│  ┌─────────────────────────────────────┐                  │
│  │  Internal Network (Private)         │                  │
│  │  - PostgreSQL (no public access)    │                  │
│  │  - Redis (no public access)         │                  │
│  │  - MagUI (admin only)               │                  │
│  └─────────────────────────────────────┘                  │
│                                                             │
└─────────────────────────────────────────────────────────────┘
```

#### **TLS/mTLS for IoT Devices**

```php
// IoT devices use mutual TLS (client certificates)
{
    "device_id": "device_abc123",
    "certificate": {
        "subject": "CN=device_abc123,O=LogisticsCo",
        "issuer": "CN=MagFlock CA",
        "serial": "1234567890",
        "not_before": "2025-01-01T00:00:00Z",
        "not_after": "2026-01-01T00:00:00Z",
        "fingerprint": "sha256:def456..."
    },
    "revocation_check": true  // Check CRL/OCSP
}
```

---

### **Defense Layer 6: Audit & Monitoring**

```
┌─────────────────────────────────────────────────────────────┐
│                   AUDIT & MONITORING                        │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  📝 Audit Log (Immutable)                                  │
│  ├─ Every API request                                      │
│  ├─ Every database query                                   │
│  ├─ Every authentication attempt                           │
│  ├─ Every permission change                                │
│  ├─ Every extension install/uninstall                      │
│  └─ Every admin action                                     │
│                                                             │
│  🚨 Anomaly Detection                                      │
│  ├─ Unusual API usage patterns                             │
│  ├─ Failed auth attempts (brute force)                     │
│  ├─ Data exfiltration (large exports)                      │
│  ├─ Privilege escalation attempts                          │
│  └─ Extension misbehavior                                  │
│                                                             │
│  📊 Metrics & Alerts                                       │
│  ├─ Request rate per API key                               │
│  ├─ Error rate per endpoint                                │
│  ├─ Database query performance                             │
│  ├─ Extension resource usage                               │
│  └─ Security events (failed auth, etc.)                    │
│                                                             │
└─────────────────────────────────────────────────────────────┘
```

#### **Audit Log Schema**

```sql
-- cp_audit.audit_log (immutable, append-only)
CREATE TABLE cp_audit.audit_log (
    id BIGSERIAL PRIMARY KEY,
    timestamp TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    
    -- Identity
    org_id UUID NOT NULL,
    project_id UUID,
    identity_id UUID NOT NULL,
    identity_type VARCHAR(50) NOT NULL, -- human, api_key, agent, device, extension
    
    -- Action
    action VARCHAR(100) NOT NULL, -- api.request, db.query, auth.login, etc.
    resource VARCHAR(255),        -- table name, endpoint, etc.
    method VARCHAR(10),           -- GET, POST, SELECT, INSERT, etc.
    
    -- Context
    ip_address INET,
    user_agent TEXT,
    request_id UUID,
    
    -- Payload
    payload JSONB,                -- Request/response data (sanitized)
    
    -- Result
    status VARCHAR(20),           -- success, failure, denied
    error_message TEXT,
    
    -- Compliance
    retention_policy VARCHAR(50), -- standard, legal_hold, gdpr_delete
    
    -- Immutability
    hash VARCHAR(64) NOT NULL,    -- SHA-256 of (prev_hash + current_row)
    prev_hash VARCHAR(64)         -- Chain of custody
);

-- Index for fast queries
CREATE INDEX idx_audit_log_org_time ON cp_audit.audit_log(org_id, timestamp DESC);
CREATE INDEX idx_audit_log_identity ON cp_audit.audit_log(identity_id, timestamp DESC);
CREATE INDEX idx_audit_log_action ON cp_audit.audit_log(action, timestamp DESC);
```

---

### **Defense Layer 7: Anti-Spoofing**

```
┌─────────────────────────────────────────────────────────────┐
│                   ANTI-SPOOFING MEASURES                    │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  🔐 Request Signing (HMAC)                                 │
│  ├─ Client signs request with secret key                   │
│  ├─ Server verifies signature                              │
│  ├─ Prevents replay attacks (nonce + timestamp)            │
│  └─ Prevents MITM tampering                                │
│                                                             │
│  🆔 Instance Identity                                      │
│  ├─ Each MagFlock instance has unique ID                   │
│  ├─ Signed by MagFlock CA                                  │
│  ├─ Clients verify instance identity                       │
│  └─ Prevents fake instances                                │
│                                                             │
│  🌐 Domain Verification                                    │
│  ├─ Official domains: magflock.io, *.magflock.io          │
│  ├─ DNSSEC enabled                                         │
│  ├─ CAA records (only Let's Encrypt)                       │
│  └─ HSTS preload list                                      │
│                                                             │
│  📱 Client SDK Verification                                │
│  ├─ Official SDKs are code-signed                          │
│  ├─ SDK verifies server certificate                        │
│  ├─ SDK checks instance identity                           │
│  └─ SDK warns on suspicious behavior                       │
│                                                             │
└─────────────────────────────────────────────────────────────┘
```

#### **Request Signing (HMAC-SHA256)**

```php
// Client-side (SDK)
class MagFlockClient
{
    public function request(string $method, string $path, array $data = []): Response {
        $timestamp = time();
        $nonce = bin2hex(random_bytes(16));
        
        // Build signature payload
        $payload = implode("\n", [
            $method,
            $path,
            $timestamp,
            $nonce,
            json_encode($data)
        ]);
        
        // Sign with API secret
        $signature = hash_hmac('sha256', $payload, $this->apiSecret);
        
        // Send request with signature headers
        return $this->http->request($method, $path, [
            'headers' => [
                'X-MagFlock-Key' => $this->apiKey,
                'X-MagFlock-Timestamp' => $timestamp,
                'X-MagFlock-Nonce' => $nonce,
                'X-MagFlock-Signature' => $signature
            ],
            'json' => $data
        ]);
    }
}

// Server-side (MagGate)
class RequestSignatureMiddleware
{
    public function handle(Request $request): Response {
        $apiKey = $request->header('X-MagFlock-Key');
        $timestamp = $request->header('X-MagFlock-Timestamp');
        $nonce = $request->header('X-MagFlock-Nonce');
        $signature = $request->header('X-MagFlock-Signature');
        
        // Check timestamp (prevent replay attacks)
        if (abs(time() - $timestamp) > 300) { // 5 minutes
            throw new SecurityException('Request timestamp expired');
        }
        
        // Check nonce (prevent replay attacks)
        if ($this->nonceCache->has($nonce)) {
            throw new SecurityException('Nonce already used');
        }
        $this->nonceCache->set($nonce, true, 600); // Cache for 10 minutes
        
        // Verify signature
        $apiSecret = $this->getApiSecret($apiKey);
        $payload = implode("\n", [
            $request->method(),
            $request->path(),
            $timestamp,
            $nonce,
            $request->getContent()
        ]);
        $expectedSignature = hash_hmac('sha256', $payload, $apiSecret);
        
        if (!hash_equals($expectedSignature, $signature)) {
            throw new SecurityException('Invalid signature');
        }
        
        return $next($request);
    }
}
```

---

## ⚡ **PERFORMANCE ARCHITECTURE**

### **Caching Strategy**

```
┌─────────────────────────────────────────────────────────────┐
│                     CACHING LAYERS                          │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  L1: Application Cache (RAM - in-process)                  │
│  ├─ Hot data (accessed every request)                      │
│  ├─ TTL: 60 seconds                                        │
│  ├─ Size: 256 MB per process                               │
│  └─ Example: API key → permissions mapping                 │
│                                                             │
│  L2: Distributed Cache (Redis)                             │
│  ├─ Warm data (accessed frequently)                        │
│  ├─ TTL: 5-60 minutes                                      │
│  ├─ Size: 8 GB cluster                                     │
│  └─ Example: User sessions, query results                  │
│                                                             │
│  L3: CDN Cache (CloudFlare, Fastly)                        │
│  ├─ Static assets (JS, CSS, images)                       │
│  ├─ TTL: 24 hours                                          │
│  ├─ Size: Unlimited                                        │
│  └─ Example: MagUI frontend, docs                          │
│                                                             │
│  L4: Database Query Cache (PostgreSQL)                     │
│  ├─ Prepared statements                                    │
│  ├─ Materialized views                                     │
│  ├─ Partial indexes                                        │
│  └─ Example: Aggregated analytics                          │
│                                                             │
└─────────────────────────────────────────────────────────────┘
```

### **Connection Pooling**

```php
// Per-project connection pools
{
    "project_abc123": {
        "pool_size": 10,           // Max connections
        "min_idle": 2,             // Keep 2 warm
        "max_lifetime": 3600,      // Recycle after 1 hour
        "idle_timeout": 600,       // Close after 10 min idle
        "connection_timeout": 5    // Fail fast
    }
}
```

### **Rate Limiting (Token Bucket)**

```php
// Per API key rate limits
class RateLimiter
{
    public function allow(string $apiKey): bool {
        $bucket = $this->getBucket($apiKey);
        
        // Refill tokens
        $now = microtime(true);
        $elapsed = $now - $bucket['last_refill'];
        $tokensToAdd = $elapsed * $bucket['refill_rate'];
        $bucket['tokens'] = min(
            $bucket['capacity'],
            $bucket['tokens'] + $tokensToAdd
        );
        $bucket['last_refill'] = $now;
        
        // Check if token available
        if ($bucket['tokens'] >= 1) {
            $bucket['tokens'] -= 1;
            $this->saveBucket($apiKey, $bucket);
            return true;
        }
        
        return false; // Rate limit exceeded
    }
}
```

---

## 🎯 **NEXT STEPS**

I've mapped out:
✅ **User types** (humans, AI, IoT, extensions, attackers)  
✅ **Use cases** (solo dev, startup, enterprise, malicious)  
✅ **Threat model** (SQL injection, spoofing, data exfiltration, etc.)  
✅ **Security layers** (auth, authz, isolation, sandboxing, network, audit, anti-spoofing)  
✅ **Performance architecture** (caching, pooling, rate limiting)

### **What do you want to dive deeper into?**

1. **Extension Sandboxing** - How to safely run untrusted code
2. **AI Agent Security** - Preventing prompt injection, data leakage
3. **IoT Device Management** - Certificate provisioning, revocation
4. **Multi-Tenancy Strategy** - Database-per-project vs schema-per-project
5. **Audit Log Design** - Immutable, tamper-proof logging
6. **Anti-Spoofing** - Request signing, instance identity
7. **Start Building MagMoBo** - Let's write code

**Or should I create a comprehensive security specification document?** 📄

Let me know where you want to focus next. 🚀