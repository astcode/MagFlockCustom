# Config Promotion Runbook

## Purpose

Provide operators with a deterministic workflow for introducing, validating, and promoting configuration changes in the layered MagMoBoCE loader (`config/base`, `config/environments`, `config/secrets`), including hot-reload procedures and rollback handling.

## Layered Layout Overview

| Layer | Path | Contents |
| --- | --- | --- |
| Base defaults | `config/base/*.php` | Canonical platform defaults (`kernel`, `logging`, `database`, etc.) |
| Environment overrides | `config/environments/<env>.php` (optional directory) | Environment-specific deltas (e.g., staging vs production toggles) |
| Secrets | `config/secrets/*.php` | Protected credentials (keep out of VCS; load from secret manager or encrypted blobs) |
| Schema | `config/schema.php` | Machine-readable contract enforced by `ConfigSchemaValidator` |
| Redaction map | `config/redaction.php` | Dot-notation patterns consumed by `ConfigRedactor` + Logger |

`ConfigLoader` (see `mobo/Config/LayeredConfigLoader.php`) merges the layers in this order: base → environment → shared secrets → environment secrets. Validation is executed before the kernel accepts the change, and failure triggers an automatic rollback plus `config.reload_failed`.

## Promotion Workflow

1. **Plan the change**
   - Identify which layer should own the delta:
     - Cross-environment defaults → modify/create `config/base/*.php`
     - Environment-only adjustments → add/update `config/environments/<env>.php`
     - Sensitive credentials → stage via provider backing `config/secrets/*.php`
   - Duplicate keys are reconciled via deep merge, so only specify what differs.

2. **Edit configuration**
   - Use PHP arrays; return associative structures matching schema.
   - Keep secrets out of VCS—use deployment tooling to inject into `config/secrets` or the target secret store.
   - Commit changes for base/environments files as part of the release branch.

3. **Validate locally**
   - Run the config unit tests: `vendor/bin/phpunit --filter ConfigManagerTest`
   - Exercise hot reload path: `vendor/bin/phpunit --filter ConfigReloadTest`
   - Optional smoke: `composer kernel-smoke` (stubs) or `composer kernel-smoke-magds` (live MagDS)

4. **Deploy change**
   - Promote updated base/environment files to the target release artifact.
   - Sync secrets through approved secret management workflow (do *not* push plain-text secrets to git).

5. **Apply at runtime**
   - Once the artifact lands, trigger the runtime reload hook (currently via application control plane or programmatic call to `Kernel::reloadConfig()`).
   - Observe emitted events:
     - Success → `config.reloaded { version, changed_keys }`
     - Failure → `config.reload_failed { error }` and automatic rollback to prior snapshot
   - Tail `storage/logs/mobo.log` for redacted details; all sensitive keys matching `config/redaction.php` are masked automatically.

6. **Post-checks**
   - Confirm log level and other toggles reflect new config via `Kernel::getConfig()->get(<key>)` or forthcoming CLI.
   - Verify no `config.reload_failed` events remain unhandled in the EventBus history.

## Rollback Guidance

- The kernel automatically reverts to the previous known-good configuration if validation fails during reload.
- For manual rollback, restore the prior files in the affected layer(s) and repeat the reload procedure.
- Investigate validation errors in the log (`kernel.name must be a string`, enum violations, etc.). Adjust files to satisfy `config/schema.php`.

## Observability & Audit

- All loader operations log with contextual metadata (`instance_id`, `environment`) and obey the redaction map; expect `[REDACTED]`/hash tokens for sensitive fields.
- `config.reloaded` and `config.reload_failed` events are recorded on the EventBus for downstream monitoring; wire alerts via MagMonitor or external systems to watch for failure emissions.

## Troubleshooting Tips

- **Schema failures:** compare offending key with `config/schema.php`; ensure enum values (e.g., `kernel.environment`) use sanctioned values (`development`, `testing`, `staging`, `production`).
- **Missing overrides:** confirm layer file name matches loader expectations (`<env>.php`) and returns an associative array.
- **Redaction gaps:** update `config/redaction.php` when introducing new secrets to keep logs safe, then add/adjust PHPUnit coverage accordingly.
