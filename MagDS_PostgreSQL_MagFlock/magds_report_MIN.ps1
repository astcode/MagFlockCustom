# ===== ZERO-FRICTION REPORT (Windows PowerShell, host-side) =====
$Container = "magdsdb-pg"; $Db = "magdsdb"; $User = "magdsdb_admin"; $Report = "MagDS_Report.md"
if (Test-Path $Report) { Remove-Item $Report -Force }; New-Item -ItemType File -Path $Report -Force | Out-Null
Add-Content $Report "# MagDS Instance Report  $(Get-Date)"
Add-Content $Report "- Container: $Container  |  DB: $Db  |  User: $User"
Add-Content $Report ""

# Helper aliases (inline to avoid functions)
$P="docker exec $Container psql -U $User -d $Db -q -c"
$R="docker exec $Container psql -U $User -d $Db -q -t -A -c"

# ---- Platform ----
Add-Content $Report "# Platform`n"
Add-Content $Report "## PostgreSQL Version`n````text"
iex "$P `"SELECT version();`" " | Add-Content $Report
Add-Content $Report "`````n"
Add-Content $Report "## Instance Uptime`n````text"
iex "$P `"SELECT date_trunc('second', now() - pg_postmaster_start_time()) AS uptime;`" " | Add-Content $Report
Add-Content $Report "`````n"
Add-Content $Report "## shared_preload_libraries`n````text"
iex "$P `"SHOW shared_preload_libraries;`" " | Add-Content $Report
Add-Content $Report "`````n"

# ---- Cluster Inventory ----
Add-Content $Report "# Cluster Inventory`n"
Add-Content $Report "## Databases name owner size`n````text"
iex "$P `"SELECT datname AS db, pg_get_userbyid(datdba) AS owner, pg_size_pretty(pg_database_size(datname)) AS size FROM pg_database WHERE datistemplate=false ORDER BY datname;`" " | Add-Content $Report
Add-Content $Report "`````n"
Add-Content $Report "## Roles capabilities`n````text"
iex "$P `"SELECT rolname, rolsuper, rolcreatedb, rolcreaterole, rolreplication FROM pg_roles ORDER BY rolname;`" " | Add-Content $Report
Add-Content $Report "`````n"
Add-Content $Report "## Role memberships`n````text"
iex "$P `"SELECT r.rolname AS role, m.rolname AS member FROM pg_auth_members am JOIN pg_roles r ON r.oid = am.roleid JOIN pg_roles m ON m.oid = am.member ORDER BY r.rolname, m.rolname;`" " | Add-Content $Report
Add-Content $Report "`````n"

# ---- Settings ----
Add-Content $Report "# Settings`n"
Add-Content $Report "## Key settings`n````text"
iex "$P `"SELECT name, setting FROM pg_settings WHERE name IN ('max_connections','shared_buffers','effective_cache_size','work_mem','maintenance_work_mem','wal_level','max_wal_size','pg_stat_statements.track','pgaudit.log') ORDER BY 1;`" " | Add-Content $Report
Add-Content $Report "`````n"
Add-Content $Report "_Tip: run SHOW ALL; inside psql for the complete list._`n"

# ---- Extensions ----
Add-Content $Report "# Extensions`n"
Add-Content $Report "## Installed extensions in magdsdb`n````text"
iex "$P `"SELECT extname, extversion FROM pg_extension ORDER BY 1;`" " | Add-Content $Report
Add-Content $Report "`````n"

# ---- Activity and Locks ----
Add-Content $Report "# Activity and Locks`n"
Add-Content $Report "## Connections by db and state`n````text"
iex "$P `"SELECT datname, state, count(*) AS sessions FROM pg_stat_activity GROUP BY 1,2 ORDER BY 1,2;`" " | Add-Content $Report
Add-Content $Report "`````n"
Add-Content $Report "## Current locks compact`n````text"
iex "$P `"SELECT coalesce(d.datname,'-' ) AS db, coalesce(c.relname,'-') AS rel, mode, granted FROM pg_locks l LEFT JOIN pg_database d ON d.oid = l.database LEFT JOIN pg_class c ON c.oid = l.relation ORDER BY granted DESC, db, rel LIMIT 200;`" " | Add-Content $Report
Add-Content $Report "`````n"

# ---- Largest Objects ----
Add-Content $Report "# Largest Objects`n"
Add-Content $Report "## Top relation sizes 25`n````text"
iex "$P `"SELECT n.nspname AS schema, c.relname AS name, pg_size_pretty(pg_total_relation_size(c.oid)) AS total_size FROM pg_class c JOIN pg_namespace n ON n.oid = c.relnamespace WHERE c.relkind IN ('r','m','i') AND n.nspname NOT IN ('pg_toast','pg_catalog','information_schema') ORDER BY pg_total_relation_size(c.oid) DESC LIMIT 25;`" " | Add-Content $Report
Add-Content $Report "`````n"

# ---- Feature Reports ----
Add-Content $Report "# Feature Reports`n"
Add-Content $Report "## pg_stat_statements top 25 by total time`n````text"
iex "$P `"SELECT queryid, calls, round(total_exec_time::numeric,2) AS total_ms, rows, left(query,160) AS sample FROM pg_stat_statements ORDER BY total_exec_time DESC LIMIT 25;`" " | Add-Content $Report
Add-Content $Report "`````n"

Add-Content $Report "## Timescale hypertables`n````text"
iex "$P `"SELECT * FROM timescaledb_information.hypertables ORDER BY hypertable_schema, hypertable_name;`" " | Add-Content $Report
Add-Content $Report "`````n"

Add-Content $Report "## pg_cron jobs`n````text"
iex "$P `"SELECT jobid, schedule, command, active FROM cron.job ORDER BY jobid;`" " | Add-Content $Report
Add-Content $Report "`````n"

Add-Content $Report "## pg_cron recent runs jsonl latest 50`n````text"
$cron = iex "$R `"SELECT to_jsonb(jrd) FROM cron.job_run_details jrd ORDER BY runid DESC LIMIT 50;`" "
if ([string]::IsNullOrWhiteSpace($cron)) { $cron = "(no rows)" }
Add-Content $Report $cron
Add-Content $Report "`````n"

Add-Content $Report "## PostGIS full version`n````text"
$gisFlag = (iex "$R `"SELECT EXISTS (SELECT 1 FROM pg_extension WHERE extname='postgis');`" ").Trim()
if ($gisFlag -eq "t") { iex "$P `"SELECT PostGIS_Full_Version();`" " | Add-Content $Report } else { Add-Content $Report "(postgis not installed in $Db)" }
Add-Content $Report "`````n"

# ---- Health ----
Add-Content $Report "# Health`n"
Add-Content $Report "## mg_ctl.health_check`n````text"
$health = (iex "$R `"SELECT mg_ctl.health_check();`" ").Trim()
if ([string]::IsNullOrWhiteSpace($health)) { $health = "(function not defined)" }
Add-Content $Report $health
Add-Content $Report "`````n"

# ---- Summary ----
Add-Content $Report "# Summary`n"
$dbc  = (iex "$R `"SELECT count(*) FROM pg_database WHERE datistemplate=false;`" ").Trim()
$extc = (iex "$R `"SELECT count(*) FROM pg_extension;`" ").Trim()
Add-Content $Report ("- Databases: **{0}**" -f $dbc)
Add-Content $Report ("- Extensions in {0}: **{1}**" -f $Db,$extc)
Add-Content $Report ""

Write-Host ("Report written: {0}" -f (Resolve-Path $Report)) -ForegroundColor Cyan
