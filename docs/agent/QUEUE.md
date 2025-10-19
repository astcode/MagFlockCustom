[AGENT CARD - DONE] Implement config hot reload (Layered loader + automated rollback merged 2025-10-18)
- Read `docs/kernel/CONTRACTS.md` for `config.reloaded` / `config.reload_failed` events.
- Build/extend `ConfigLoader` to validate → swap → emit events → rollback on failure.
- Add PHPUnit coverage in `tests/Kernel/ConfigReloadTest.php`.
- ? Definition of Done met: Section 1 DoD boxes checked in `docs/CurrentKernelMagDSStack_todoBeforeMagWS.md`.

[AGENT CARD - IN PROGRESS] Extend failover to enterprise-grade replica management\n- Weighted replica scoring (latency/region tags).\n- CLI to register/unregister replicas (php mag magds:replica register|unregister).\n- Session fencing & WAL sync checks before reintegration.\n- Shared heartbeat cache/state across kernel instances.\n- Replica lag telemetry + alerts.\n- Automated post-failover validation and documentation updates.\n\n[AGENT CARD] Instrument Prometheus exporter
- Reference `docs/kernel/CONTRACTS.md` metrics block.
- Expose `/metrics` on configurable port (default 9500) with kernel + MagDB counters/histograms.
- Add smoke test ensuring endpoint returns valid Prometheus text.
- Update `docs/ops/Alerts.md` with new metric names.

[AGENT CARD - DONE] Add MagDB migrations scaffold
- Create `migrations/README.md` with naming conventions.
- Implement `MagMigrate` component + CLI (`mag migrate:up/down/status`).
- Persist version table `schema_migrations`.
- Verify with integration test and DoD in section 4.

[AGENT CARD - DONE] Build MagDS failover manager
- Extend MagDB to track primary/replicas, heartbeats, fencing.
- Implement `mag magds:replica-status` / `magds:failover` commands with integration tests.
- Emit failover telemetry and update `docs/ops/Runbook_Failover.md`.
