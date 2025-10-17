# ===============================
# magds_report_plus.ps1  (Windows PowerShell, host-side)
# One Markdown report + optional health objects + optional pg_cron
# ===============================

# --- CONFIG ---
$Container = "magdsdb-pg"      # docker container name
$Db        = "magdsdb"         # system/controller database
$User      = "magdsdb_admin"   # superuser

# Behavior toggles
$EnsureHealthObjects = $true    # create mg_ctl schema/function/table if missing
$ScheduleHealthCron  = $true    # schedule hourly pg_cron snapshot
$CronSchedule        = "0 * * * *"

# Output report
$Stamp  = Get-Date -Format "yyyyMMdd_HHmm"
$Report = "MagDS_Report_$Stamp.md"
if (Test-Path $Report) { Remove-Item $Report -Force }
New-Item -ItemType File -Path $Report -Force | Out-Null

# --- Block writer (no nested calls in parameters) ---
function AddBlock {
  param([string]$Title, [string]$Content)
  Add-Content $Report "## $Title"
  Add-Content $Report "````text"
  Add-Content $Report ($Content.TrimEnd())
  Add-Content $Report "````"
  Add-Content $Report ""
}

# --- Header ---
Add-Content $Report "# MagDS Instance Report  `$(Get-Date)`"
Add-Content $Report "- Container: **$Container**  |  DB: **$Db**  |  User: **$User**"
Add-Content $Report ""

# ======================================
# 1) Platform
# ======================================
Add-Content $Report "# Platform"; Add-Content $Report ""

$ver = $(docker exec $Container psql -U $User -d $Db -q -c "SELECT version();")
$upt = $(docker exec $Container psql -U $User -d $Db -q -c "SELECT date_trunc('second', now() - pg_postmaster_start_time()) AS uptime;")
$pre = $(docker exec $Container psql -U $User -d $Db -q -c "SHOW shared_preload_libraries;")

AddBlock "PostgreSQL-version" $ver
AddBlock "Instance-uptime"    $upt
AddBlock "shared-preload-libraries" $pre

# ======================================
# 2) Cluster inventory
# ======================================
Add-Content $Report "# Cluster-inventory"; Add-Content $Report ""

$sqlDbs = @"
SELECT datname AS db,
       pg_get_userbyid(datdba) AS owner,
       pg_size_pretty(pg_database_size(datname)) AS size
FROM pg_database
WHERE datistemplate=false
ORDER BY datname;
"@
$dbs = $(docker exec $Container psql -U $User -d $Db -q -c $sqlDbs)
AddBlock "Databases-name-owner-size" $dbs

$sqlRoles = @"
SELECT rolname, rolsuper, rolcreatedb, rolcreaterole, rolreplication
FROM pg_roles
ORDER BY rolname;
"@
$roles = $(docker exec $Container psql -U $User -d $Db -q -c $sqlRoles)
AddBlock "Roles-capabilities" $roles

$sqlMembers = @"
SELECT r.rolname AS role, m.rolname AS member
FROM pg_auth_members am
JOIN pg_roles r ON r.oid = am.roleid
JOIN pg_roles m ON m.oid = am.member
ORDER BY r.rolname, m.rolname;
"@
$members = $(docker exec $Container psql -U $User -d $Db -q -c $sqlMembers)
AddBlock "Role-memberships" $members

# ======================================
# 3) Settings
# ======================================
Add-Content $Report "# Settings"; Add-Content $Report ""

$sqlKey = @"
SELECT name, setting
FROM pg_settings
WHERE name IN ('max_connections','shared_buffers','effective_cache_size','work_mem',
               'maintenance_work_mem','wal_level','max_wal_size',
               'pg_stat_statements.track','pgaudit.log')
ORDER BY 1;
"@
$key = $(docker exec $Container psql -U $User -d $Db -q -c $sqlKey)
AddBlock "Key-settings" $key
Add-Content $Report "_Tip: run SHOW ALL; inside psql for the complete list._"
Add-Content $Report ""

# ======================================
# 4) Extensions
# ======================================
Add-Content $Report "# Extensions"; Add-Content $Report ""
$ext = $(docker exec $Container psql -U $User -d $Db -q -c "SELECT extname, extversion FROM pg_extension ORDER BY 1;")
AddBlock "Installed-extensions-in-magdsdb" $ext

# ======================================
# 5) Activity & locks
# ======================================
Add-Content $Report "# Activity-and-locks"; Add-Content $Report ""

$act = $(docker exec $Container psql -U $User -d $Db -q -c "SELECT COALESCE(datname,'-') AS db, COALESCE(state,'-') AS state, count(*) AS sessions FROM pg_stat_activity GROUP BY 1,2 ORDER BY 1,2;")
AddBlock "Connections-by-db-and-state" $act

$sqlLocks = @"
SELECT COALESCE(d.datname,'-') AS db,
       COALESCE(c.relname,'-') AS rel,
       mode, granted
FROM pg_locks l
LEFT JOIN pg_database d ON d.oid = l.database
LEFT JOIN pg_class    c ON c.oid = l.relation
ORDER BY granted DESC, db, rel
LIMIT 200;
"@
$locks = $(docker exec $Container psql -U $User -d $Db -q -c $sqlLocks)
AddBlock "Current-locks-compact" $locks

# ======================================
# 6) Largest objects
# ======================================
Add-Content $Report "# Largest-objects"; Add-Content $Report ""

$sqlTop = @"
SELECT n.nspname AS schema, c.relname AS name,
       pg_size_pretty(pg_total_relation_size(c.oid)) AS total_size
FROM pg_class c
JOIN pg_namespace n ON n.oid = c.relnamespace
WHERE c.relkind IN ('r','m','i')
  AND n.nspname NOT IN ('pg_toast','pg_catalog','information_schema')
ORDER BY pg_total_relation_size(c.oid) DESC
LIMIT 25;
"@
$top = $(docker exec $Container psql -U $User -d $Db -q -c $sqlTop)
AddBlock "Top-relation-sizes-25" $top

# ======================================
# 7) Feature reports
# ======================================
Add-Content $Report "# Feature-reports"; Add-Content $Report ""

$sqlPgss = @"
SELECT queryid,
       calls,
       round(total_exec_time::numeric,2) AS total_ms,
       rows,
       left(query,160) AS sample
FROM pg_stat_statements
ORDER BY total_exec_time DESC
LIMIT 25;
"@
$pgss = $(docker exec $Container psql -U $User -d $Db -q -c $sqlPgss)
AddBlock "pg_stat_statements-top25-by-total-time" $pgss

$hy = $(docker exec $Container psql -U $User -d $Db -q -c "SELECT * FROM timescaledb_information.hypertables ORDER BY hypertable_schema, hypertable_name;")
AddBlock "Timescale-hypertables" $hy

$cronJobs = $(docker exec $Container psql -U $User -d $Db -q -c "SELECT jobid, schedule, command, active FROM cron.job ORDER BY jobid;")
AddBlock "pg_cron-jobs" $cronJobs

$cronRuns = $(docker exec $Container psql -U $User -d $Db -q -t -A -c "SELECT to_jsonb(jrd) FROM cron.job_run_details jrd ORDER BY runid DESC LIMIT 50;")
if ([string]::IsNullOrWhiteSpace($cronRuns)) { $cronRuns = "(no rows)" }
AddBlock "pg_cron-recent-runs-jsonl-latest-50" $cronRuns

$postgisInstalled = $(docker exec $Container psql -U $User -d $Db -q -t -A -c "SELECT EXISTS (SELECT 1 FROM pg_extension WHERE extname='postgis');").Trim()
if ($postgisInstalled -eq 't') {
  $gis = $(docker exec $Container psql -U $User -d $Db -q -c "SELECT PostGIS_Full_Version();")
  AddBlock "PostGIS-full-version" $gis
} else {
  AddBlock "PostGIS-full-version" "(postgis not installed in $Db)"
}

# ======================================
# 8) Health (guarded, with optional creation + cron)
# ======================================
Add-Content $Report "# Health"; Add-Content $Report ""

$hasHealth = $(docker exec $Container psql -U $User -d $Db -q -t -A -c @"
SELECT EXISTS (
  SELECT 1
  FROM pg_proc p
  JOIN pg_namespace n ON n.oid = p.pronamespace
  WHERE n.nspname = 'mg_ctl' AND p.proname = 'health_check'
);
"@).Trim()

if ($EnsureHealthObjects -and $hasHealth -ne 't') {
  $createHealth = @"
CREATE SCHEMA IF NOT EXISTS mg_ctl;

CREATE OR REPLACE FUNCTION mg_ctl.health_check()
RETURNS jsonb
LANGUAGE sql
AS $$
  SELECT jsonb_build_object(
    'now', now(),
    'version', version(),
    'uptime', (now() - pg_postmaster_start_time()),
    'db_count', (SELECT count(*) FROM pg_database WHERE datistemplate=false),
    'ext_count', (SELECT count(*) FROM pg_extension)
  );
$$;

CREATE TABLE IF NOT EXISTS mg_ctl.health_history (
  ts      timestamptz NOT NULL DEFAULT now(),
  payload jsonb       NOT NULL
);

CREATE OR REPLACE FUNCTION mg_ctl.capture_health()
RETURNS void LANGUAGE sql AS $$
  INSERT INTO mg_ctl.health_history(payload)
  SELECT mg_ctl.health_check();
$$;
"@
  $null = $createHealth | docker exec -i $Container psql -U $User -d $Db -v ON_ERROR_STOP=1
  $hasHealth = 't'
}

if ($ScheduleHealthCron -and $hasHealth -eq 't') {
  $cronSql = @"
DO $do$
BEGIN
  IF NOT EXISTS (SELECT 1 FROM cron.job WHERE command = 'SELECT mg_ctl.capture_health();') THEN
    PERFORM cron.schedule('$CronSchedule', $$SELECT mg_ctl.capture_health();$$);
  ELSE
    UPDATE cron.job SET schedule = '$CronSchedule', active = true
    WHERE command = 'SELECT mg_ctl.capture_health();';
  END IF;
END
$do$;
"@
  $null = $cronSql | docker exec -i $Container psql -U $User -d $Db -v ON_ERROR_STOP=1
}

$health = $(docker exec $Container psql -U $User -d $Db -q -t -A -c "SELECT mg_ctl.health_check();").Trim()
if ([string]::IsNullOrWhiteSpace($health)) { $health = "(function not defined)" }
AddBlock "mg_ctl-health_check" $health

# ======================================
# 9) Summary
# ======================================
Add-Content $Report "# Summary"; Add-Content $Report ""
$dbc  = $(docker exec $Container psql -U $User -d $Db -q -t -A -c "SELECT count(*) FROM pg_database WHERE datistemplate=false;").Trim()
$extc = $(docker exec $Container psql -U $User -d $Db -q -t -A -c "SELECT count(*) FROM pg_extension;").Trim()
Add-Content $Report ("- Databases: **{0}**" -f $dbc)
Add-Content $Report ("- Extensions in {0}: **{1}**" -f $Db,$extc)
Add-Content $Report ""

Write-Host ("Report written: {0}" -f (Resolve-Path $Report)) -ForegroundColor Cyan
if ($ScheduleHealthCron) {
  Write-Host "pg_cron job ensured for mg_ctl.capture_health() on: $CronSchedule" -ForegroundColor DarkCyan
}
