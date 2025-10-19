# MagMigrate Runbook

Use the MagMoBoCE CLI to manage schema migrations. Commands are invoked from the project root with `php mag ...`.

## Directory Layout
- Migrations live in `magmoboce/migrations/<component>` (default: `magds`).
- Each file returns an array with `id`, `description`, `up`, and `down` statements. See `migrations/README.md` for the full contract.

## Common Commands
| Command | Purpose |
| --- | --- |
| `php mag migrate:status [--component=magds] [--connection=magdsdb]` | List migrations and their applied state. |
| `php mag migrate:up [--component=magds] [--connection=magdsdb] [--target=<id>]` | Run pending migrations (optionally stop at `target`). |
| `php mag migrate:down [--component=magds] [--connection=magdsdb] [--steps=1] [--target=<id>]` | Roll back recent migrations either by step count or down to `target`. |
| `php mag migrate:baseline --target=<id> [--component=magds] [--connection=magdsdb]` | Mark migrations up to `target` as applied without executing SQL. |

## Operational Notes
- Always run `migrate:status` before and after an operation; commit results to change logs when working in team environments.
- Baseline is intended for adopting existing databases; ensure `schema_migrations` table matches expectations before use.
- CLI loads `.env` via `bootstrap.php`, so configure credentials there. Override target directories or connections via command options if needed.
- For new migrations, follow `migrations/README.md` naming conventions and include reversible statements.
- Record changes in the deployment checklist and ensure backups are recent before running destructive operations.
