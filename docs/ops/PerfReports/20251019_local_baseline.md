# MagDS Chaos/Latency Baseline – 2025-10-19

- Environment: local dockerised MagDS/Postgres
- SLIs:
  - Query p95 latency ≤150 ms (interactive) / ≤400 ms (analytical)
  - Failover promotion ≤30 s
- Chaos harness result: all scenarios passed (`db.down`, `db.latency.500ms`, `component.crash`, `config.invalid`)
- Latency probe (`db.latency.500ms`):
  - Measured: ~520 ms
  - Outcome: within tolerance (≤800 ms upper bound)
- Failover heartbeat duration: auto-promote succeeded, ~120 ms
- Evidence: `docs/ops/ChaosReports/<timestamp>.json`, telemetry `chaos.scenarios_total`.
