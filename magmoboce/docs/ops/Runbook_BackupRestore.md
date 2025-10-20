# MagDS Backup & Restore Runbook

Use this runbook to execute, verify, and restore MagDS backups through the MagMoBoCE CLI.

---

## 1. Backup Execution
- Backups are stored under `storage/backups/` by default (configurable via `magds.backup.path`).
- Trigger a manual backup:
  ```bash
  php mag magds:backup run --label=pre-release
  ```
- Output includes:
  - Backup identifier (`YYYYMMDD_HHMMSS[_label]`)
  - Manifest path (`<backup>/manifest.json`) with dataset checksums (default SHA-256).
- Telemetry: `magdb.backups_total`, `magdb.last_backup_epoch`.

### Retention
- Configure `magds.backup.retention.max_count` (default: 5). Older backups are pruned automatically post-run.

---

## 2. Verification
- Validates checksum integrity across datasets defined in `magds.backup.datasets`.
  ```bash
  php mag magds:backup verify --id=20251019_123000_pre-release
  ```
- Verification updates the manifest with `last_verified_at` ISO timestamp.
- Telemetry emits `magdb.backup.verify_completed`.
- Alerts: monitor for failed verification events and degraded metrics (`magdb.backup.verify_failed` notifies via EventBus).

---

## 3. Restore Procedure
- Dry run to inspect actions without touching files:
  ```bash
  php mag magds:restore --id=20251019_123000_pre-release --dry-run
  ```
- Perform actual restore (overwrites dataset sources specified in manifest):
  ```bash
  php mag magds:restore --id=20251019_123000_pre-release
  ```
- Telemetry: `magdb.restores_total`, `magdb.last_restore_epoch`.
- EventBus emits `magdb.restore.completed`.

### Checklist Post-Restore
1. Inspect `manifest.json` and ensure restored datasets match expected checksum.
2. Re-run `php mag magds:backup verify --id=<id>` to confirm integrity.
3. Capture validation evidence (copy manifest & CLI output to incident ticket).
4. Resume normal operations or trigger downstream migrations if required.

---

## 4. Configuration Reference
- `magds.backup.enabled` – master toggle (default: true).
- `magds.backup.path` – target directory for backups.
- `magds.backup.datasets` – list of dataset definitions:
  ```php
  [
      [
          'name' => 'kernel_state',
          'source' => 'storage/state/system.json',
          'type' => 'file',
      ],
  ]
  ```
- `magds.backup.verification.algorithm` – hash algorithm (default `sha256`).
- `magds.backup.retention.max_count` – maximum stored backups.

Update `docs/CurrentKernelMagDSStack_todoBeforeMagWS.md` with new datasets or retention policies whenever adjusted.

---

## 5. Evidence & Auditing
- Backup manifest and checksum proofs are stored alongside the backup.
- Audit trail:
  - `magdb.backup.completed`
  - `magdb.backup.verify_completed`
  - `magdb.restore.completed`
- Include relevant telemetry snapshots and manifest JSON in compliance reports.
