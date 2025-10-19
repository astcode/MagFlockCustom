# Observability Dashboards & Alerts

Use these artefacts to seed Grafana dashboards and Prometheus alerting for MagMoBoCE. Keep them in sync with `docs/kernel/CONTRACTS.md` whenever metrics change.

---

## Grafana Dashboard — `kernel_overview.json`

```json
{
  "title": "MagMoBoCE Kernel Overview",
  "uid": "magmobo-kernel-overview",
  "timezone": "utc",
  "refresh": "30s",
  "schemaVersion": 37,
  "version": 1,
  "panels": [
    {
      "title": "Component Restarts (5m)",
      "type": "stat",
      "gridPos": { "h": 5, "w": 6, "x": 0, "y": 0 },
      "targets": [
        { "expr": "sum(increase(component_restarts_total[5m]))", "legendFormat": "restarts" }
      ]
    },
    {
      "title": "Failed Components",
      "type": "table",
      "gridPos": { "h": 8, "w": 12, "x": 6, "y": 0 },
      "targets": [
        {
          "expr": "increase(component_state_changes_total{state=\"failed\"}[5m])",
          "legendFormat": "{{component}}"
        }
      ]
    },
    {
      "title": "MagDB Health Latency (p95)",
      "type": "graph",
      "gridPos": { "h": 9, "w": 12, "x": 0, "y": 5 },
      "targets": [
        {
          "expr": "histogram_quantile(0.95, sum(rate(magdb_health_latency_ms_bucket[5m])) by (le, name))",
          "legendFormat": "{{name}}"
        }
      ]
    },
    {
      "title": "Config Reload Outcome",
      "type": "stat",
      "gridPos": { "h": 5, "w": 6, "x": 12, "y": 5 },
      "targets": [
        {
          "expr": "sum(increase(config_reload_attempts_total{result=\"failure\"}[30m]))",
          "legendFormat": "failures"
        }
      ]
    }
  ]
}
```

---

## Prometheus Alert Rules — `magmoboce-critical.yaml`

```yaml
groups:
  - name: magmoboce-critical
    interval: 30s
    rules:
      - alert: MagMoBoComponentCrash
        expr: increase(component_state_changes_total{state="failed"}[3m]) > 0
        for: 2m
        labels:
          severity: critical
        annotations:
          summary: "Component crash detected ({{ $labels.component }})"
          description: "Component {{ $labels.component }} entered failed state {{ $value }} times in the last 3 minutes."

      - alert: MagMoBoRestartStorm
        expr: sum(increase(component_restarts_total[5m])) > 5
        for: 5m
        labels:
          severity: warning
        annotations:
          summary: "Restart storm across components"
          description: "Kernel attempted {{ $value }} restarts across components within five minutes."

      - alert: MagDBHealthDegraded
        expr: histogram_quantile(0.95, sum(rate(magdb_health_latency_ms_bucket[5m])) by (le, name)) > 250
        for: 5m
        labels:
          severity: high
        annotations:
          summary: "MagDB latency exceeds 250ms p95"
          description: "Database {{ $labels.name }} 95th percentile latency is {{ $value }}ms (>250ms) over five minutes."

      - alert: ConfigReloadFailures
        expr: increase(config_reload_attempts_total{result=\"failure\"}[15m]) > 0
        for: 1m
        labels:
          severity: warning
        annotations:
          summary: "Configuration reload failures detected"
          description: "At least one configuration reload failed in the last 15 minutes. Check audit logs and validation output."
```

---

## Alertmanager Routing (Email-first) — `magmoboce-alertmanager-email.yaml`

Laragon’s SMTP relay (`127.0.0.1:1025`) lets us deliver alerts immediately via email while we prepare Slack or PagerDuty integrations. Drop this file next to your Alertmanager config and adjust the `to:` address to your on-call list.

```yaml
global:
  smtp_smarthost: '127.0.0.1:1025'
  smtp_from: 'no-reply@magmoboce.local'

route:
  receiver: magmoboce-email
  group_wait: 30s
  group_interval: 5m
  repeat_interval: 3h
  routes:
    - matchers:
        - severity = critical
      receiver: magmoboce-email

receivers:
  - name: magmoboce-email
    email_configs:
      - to: 'oncall@magmoboce.local'
        require_tls: false
        headers:
          Subject: '[MagMoBoCE][{{ .CommonLabels.severity | toUpper }}] {{ .CommonAnnotations.summary }}'
```

When Slack or PagerDuty come online, add additional receivers and extend the `routes` section instead of replacing this email path. Monitor new failover metrics (`magdb_failovers_total`, `magdb_replica_health`) in dashboards and alert rules.

---

## Alert Pipeline Smoke Test (`bin/alert-email-test.php`)

Use this CLI to verify the email channel end-to-end before enabling Alertmanager:

```bash
php bin/alert-email-test.php \
  --to="oncall@magmoboce.local" \
  --subject="[MagMoBoCE][TEST] Alert path check"
```

The script honours `MAIL_FROM_ADDRESS`/`MAIL_FROM_NAME` from `.env` and defaults to `oncall@magmoboce.local` if `--to` is omitted. Check MailHog (Laragon default `http://127.0.0.1:8025`) to confirm delivery.
