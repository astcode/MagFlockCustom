# Kernel & MagDS Artifact Map

Use this map to locate configuration layers, docs, runbooks, and data artifacts. Paths are relative to the repository root.

```
config/
  base/                     (layered defaults segmented by concern)
    kernel.php
    logging.php
    database.php
    health.php
    recovery.php
    services.php
    system.php
    urls.php
    magds.php
    observability.php
  environments/            (per-environment overrides, e.g. testing.php)
  secrets/                 (deployment-managed secret overlays)
  schema.php               (ConfigSchemaValidator contract)
  redaction.php            (logger/telemetry redaction map)
  mobo.php                 (aggregator returning layered loader result)
  database.php             (MagDS connection config)
  components.php           (component registry)

docs/
  kernel/
    CONTRACTS.md
    ARTIFACTS.md
  compliance/
    SOC2_ControlMatrix.md
    DataResidency_Policy.md

magmoboce/docs/
  ops/
    Runbook_ConfigPromotion.md
    Runbook_AuditLogging.md
    Runbook_IncidentResponse.md
    Runbook_Failover.md
    Runbook_Migrations.md
    Alerts.md

migrations/
  README.md
  magds/
    (component PHP migrations – see README for format)

CLI /
  mag (application dispatcher for `php mag ...` commands)

storage/
  logs/
    mobo.log
    audit.log
  backups/
    (timestamped backup artifacts)
  state/
    system.json
  telemetry/
    metrics.prom

tests/
  Kernel/
    ConfigReloadTest.php
    Config/ConfigManagerTest.php
    Security/AuditWriterTest.php
    LifecycleManagerTest.php
  Observability/
    MetricsEndpointTest.php
  Telemetry/
    TelemetryTest.php
  MagMigrate/
    MagMigrateTest.php
  Components/
    MagDBTest.php
    MagDBSchemaTest.php

bin/
  kernel-smoke.php
  kernel-smoke-magds.php
  alert-email-test.php
  metrics-dump.php
  metrics-router.php

docs/agent/
  QUEUE.md
```

Update this file whenever new artifacts are introduced so agents know where to read/write.
