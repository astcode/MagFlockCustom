# MagDS Instance Report  09/25/2025 20:48:19
- Container: magdsdb-pg  |  DB: magdsdb  |  User: magdsdb_admin

# Platform

## PostgreSQL Version
``text
                                                          version                                                           
----------------------------------------------------------------------------------------------------------------------------
 PostgreSQL 17.6 (Debian 17.6-2.pgdg11+1) on x86_64-pc-linux-gnu, compiled by gcc (Debian 10.2.1-6) 10.2.1 20210110, 64-bit
(1 row)

``

## Instance Uptime
``text
  uptime  
----------
 00:47:29
(1 row)

``

## shared_preload_libraries
``text
                  shared_preload_libraries                   
-------------------------------------------------------------
 timescaledb,pg_stat_statements,pg_cron,pgaudit,auto_explain
(1 row)

``

# Cluster Inventory

## Databases name owner size
``text
    db     |     owner     |  size   
-----------+---------------+---------
 magdsdb   | magdsdb_admin | 21 MB
 magui_app | magui_admin   | 8179 kB
 postgres  | magdsdb_admin | 7475 kB
(3 rows)

``

## Roles capabilities
``text
           rolname           | rolsuper | rolcreatedb | rolcreaterole | rolreplication 
-----------------------------+----------+-------------+---------------+----------------
 magdsdb_admin               | t        | t           | t             | t
 magui_admin                 | f        | f           | f             | f
 pg_checkpoint               | f        | f           | f             | f
 pg_create_subscription      | f        | f           | f             | f
 pg_database_owner           | f        | f           | f             | f
 pg_execute_server_program   | f        | f           | f             | f
 pg_maintain                 | f        | f           | f             | f
 pg_monitor                  | f        | f           | f             | f
 pg_read_all_data            | f        | f           | f             | f
 pg_read_all_settings        | f        | f           | f             | f
 pg_read_all_stats           | f        | f           | f             | f
 pg_read_server_files        | f        | f           | f             | f
 pg_signal_backend           | f        | f           | f             | f
 pg_stat_scan_tables         | f        | f           | f             | f
 pg_use_reserved_connections | f        | f           | f             | f
 pg_write_all_data           | f        | f           | f             | f
 pg_write_server_files       | f        | f           | f             | f
(17 rows)

``

## Role memberships
``text
         role         |   member   
----------------------+------------
 pg_read_all_settings | pg_monitor
 pg_read_all_stats    | pg_monitor
 pg_stat_scan_tables  | pg_monitor
(3 rows)

``

# Settings

## Key settings
``text
           name           |       setting       
--------------------------+---------------------
 effective_cache_size     | 524288
 maintenance_work_mem     | 65536
 max_connections          | 300
 max_wal_size             | 1024
 pgaudit.log              | ddl,role,read,write
 pg_stat_statements.track | all
 shared_buffers           | 16384
 wal_level                | logical
 work_mem                 | 4096
(9 rows)

``

_Tip: run SHOW ALL; inside psql for the complete list._

# Extensions

## Installed extensions in magdsdb
``text
        extname         | extversion 
------------------------+------------
 btree_gin              | 1.3
 btree_gist             | 1.7
 citext                 | 1.6
 fuzzystrmatch          | 1.2
 hstore                 | 1.8
 hypopg                 | 1.4.2
 ltree                  | 1.3
 pg_cron                | 1.6
 pg_repack              | 1.5.2
 pg_stat_statements     | 1.11
 pg_trgm                | 1.6
 pgaudit                | 17.1
 pgcrypto               | 1.3
 plpgsql                | 1.0
 postgis                | 3.5.2
 postgis_tiger_geocoder | 3.5.2
 postgis_topology       | 3.5.2
 timescaledb            | 2.22.0
 unaccent               | 1.1
 vector                 | 0.8.1
(20 rows)

``

# Activity and Locks

## Connections by db and state
``text
 datname | state  | sessions 
---------+--------+----------
 magdsdb | active |        1
 magdsdb | idle   |        3
 magdsdb |        |        1
         |        |        6
(4 rows)

``

## Current locks compact
``text
   db    |                rel                |      mode       | granted 
---------+-----------------------------------+-----------------+---------
 -       | -                                 | ExclusiveLock   | t
 -       | pg_database                       | AccessShareLock | t
 -       | pg_database_datname_index         | AccessShareLock | t
 -       | pg_database_oid_index             | AccessShareLock | t
 magdsdb | pg_class                          | AccessShareLock | t
 magdsdb | pg_class_oid_index                | AccessShareLock | t
 magdsdb | pg_class_relname_nsp_index        | AccessShareLock | t
 magdsdb | pg_class_tblspc_relfilenode_index | AccessShareLock | t
 magdsdb | pg_locks                          | AccessShareLock | t
(9 rows)

``

# Largest Objects

## Top relation sizes 25
``text
        schema         |           name           | total_size 
-----------------------+--------------------------+------------
 public                | spatial_ref_sys          | 7136 kB
 tiger                 | pagc_rules               | 848 kB
 tiger                 | pagc_lex                 | 328 kB
 tiger                 | pagc_rules_pkey          | 216 kB
 public                | spatial_ref_sys_pkey     | 208 kB
 tiger                 | pagc_gaz                 | 128 kB
 tiger                 | street_type_lookup       | 128 kB
 tiger                 | pagc_lex_pkey            | 112 kB
 tiger                 | state_lookup             | 72 kB
 tiger                 | loader_lookuptables      | 64 kB
 _timescaledb_internal | bgw_job_stat_history     | 48 kB
 tiger                 | addrfeat                 | 48 kB
 _timescaledb_config   | bgw_job                  | 48 kB
 _timescaledb_catalog  | chunk                    | 48 kB
 tiger                 | pagc_gaz_pkey            | 40 kB
 tiger                 | direction_lookup         | 40 kB
 tiger                 | street_type_lookup_pkey  | 40 kB
 tiger                 | faces                    | 40 kB
 tiger                 | edges                    | 40 kB
 tiger                 | state                    | 40 kB
 tiger                 | secondary_unit_lookup    | 40 kB
 tiger                 | geocode_settings_default | 32 kB
 tiger                 | cousub                   | 32 kB
 tiger                 | place                    | 32 kB
 tiger                 | featnames                | 32 kB
(25 rows)

``

# Feature Reports

## pg_stat_statements top 25 by total time
``text
       queryid        | calls | total_ms | rows |                                                                              sample                                                                              
----------------------+-------+----------+------+------------------------------------------------------------------------------------------------------------------------------------------------------------------
 -1884893766038378000 |     2 |  1022.11 |   50 | SELECT queryid, calls, round(total_exec_time::numeric,$1) AS total_ms, rows, left(query,$2) AS sample FROM pg_stat_statements ORDER BY total_exec_time DESC LIMI
 -2162374691561999245 |     3 |   104.64 |   75 | SELECT n.nspname AS schema, c.relname AS name, pg_size_pretty(pg_total_relation_size(c.oid)) AS total_size FROM pg_class c JOIN pg_namespace n ON n.oid = c.reln
  4257905382497591664 |     1 |    70.21 |    0 | CREATE DATABASE magui_app                                                                                                                                       +
                      |       |          |      |   WITH OWNER magui_admin                                                                                                                                        +
                      |       |          |      |        TEMPLATE template0                                                                                                                                       +
                      |       |          |      |        ENCODING 'UTF8'                                                                                                                                          +
                      |       |          |      |        LC_COLLATE 'C'                                                                                                                                           +
                      |       |          |      |        LC_CTYPE 'C'
 -6497283603979657926 |     3 |    19.18 |   27 | SELECT coalesce(d.datname,$1 ) AS db, coalesce(c.relname,$2) AS rel, mode, granted FROM pg_locks l LEFT JOIN pg_database d ON d.oid = l.database LEFT JOIN pg_cl
 -8180552026662279724 |     2 |    12.50 |    2 | SELECT public._postgis_pgsql_version()
 -6545394284668858529 |     2 |    12.22 |    2 | SELECT CASE WHEN pg_catalog.split_part(s,$1,$2)::integer > $3 THEN pg_catalog.split_part(s,$4,$5) || $6                                                         +
                      |       |          |      |         ELSE pg_catalog.split_part(s,$7, $8) || pg_catalog.spli
 -8338531667447066918 |     2 |     5.11 |    0 | DO $$BEGIN                                                                                                                                                      +
                      |       |          |      |   IF NOT EXISTS (SELECT 1 FROM pg_roles WHERE rolname = 'magui_admin') THEN                                                                                     +
                      |       |          |      |     CREATE ROLE magui_admin LOGIN PASSWORD 'magui_admin';                                                                                                       +
                      |       |          |      |   ELSE                                                                                                                                                          +
                      |       |          |      |     ALTE
 -3790038546857850628 |     2 |     4.34 |    0 | CREATE EXTENSION IF NOT EXISTS pg_stat_statements
 -7070546716962401873 |     3 |     3.81 |    9 | SELECT datname AS db, pg_get_userbyid(datdba) AS owner, pg_size_pretty(pg_database_size(datname)) AS size FROM pg_database WHERE datistemplate=$1 ORDER BY datna
  9176134405282301762 |     2 |     2.88 |    0 | ALTER ROLE magui_admin WITH LOGIN PASSWORD 'magui_admin'
  3239379075563120900 |     2 |     2.58 |    0 | DO $$\r                                                                                                                                                         +
                      |       |          |      | BEGIN\r                                                                                                                                                         +
                      |       |          |      |   IF NOT EXISTS (SELECT 1 FROM pg_roles WHERE rolname = 'magui_admin') THEN\r                                                                                   +
                      |       |          |      |     CREATE ROLE magui_admin WITH LOGIN PASSWORD 'magui_admin';\r                                                                                                +
                      |       |          |      |   END
  6384272946482952304 |     3 |     1.80 |   27 | SELECT name, setting FROM pg_settings WHERE name IN ($1,$2,$3,$4,$5,$6,$7,$8,$9) ORDER BY 1
  5633647727856335573 |     2 |     1.72 |    2 | EXISTS ( SELECT oid FROM pg_catalog.pg_proc WHERE proname LIKE $1 )
  6456632021462058075 |     1 |     1.53 |    0 | DO $$                                                                                                                                                           +
                      |       |          |      | BEGIN                                                                                                                                                           +
                      |       |          |      |   IF NOT EXISTS (SELECT 1 FROM pg_roles WHERE rolname = 'magui_admin') THEN                                                                                     +
                      |       |          |      |     CREATE ROLE magui_admin WITH LOGIN PASSWORD 'magui_admin';                                                                                                  +
                      |       |          |      |   END IF;
  3786612502829763692 |     1 |     1.23 |    0 | create table "jobs" ("id" bigserial not null primary key, "queue" varchar(255) not null, "payload" text not null, "attempts" smallint not null, "reserved_at" in
  6961040936060315450 |     3 |     1.15 |    0 | CREATE SCHEMA IF NOT EXISTS mg_ctl
  1441231887867171477 |     1 |     1.13 |    0 | create table "migrations" ("id" serial not null primary key, "migration" varchar(255) not null, "batch" integer not null)
 -1313409079621819576 |    21 |     1.06 |   21 | update "sessions" set "payload" = $1, "last_activity" = $2, "user_id" = $3, "ip_address" = $4, "user_agent" = $5 where "id" = $6
 -2204390228254518886 |     1 |     0.77 |    0 | create table "users" ("id" bigserial not null primary key, "name" varchar(255) not null, "email" varchar(255) not null, "email_verified_at" timestamp(0) without
  9015779333150529694 |     1 |     0.77 |    0 | CREATE OR REPLACE FUNCTION mg_ctl.health_check()                                                                                                                +
                      |       |          |      | RETURNS jsonb                                                                                                                                                   +
                      |       |          |      | LANGUAGE sql                                                                                                                                                    +
                      |       |          |      | AS $$                                                                                                                                                           +
                      |       |          |      |   SELECT jsonb_build_object(                                                                                                                                    +
                      |       |          |      |     'now', now(),                                                                                                                                               +
                      |       |          |      |     'version', version(),                                                                                                                                       +
                      |       |          |      |     '
 -6019633076326039178 |     1 |     0.74 |    0 | create table "personal_access_tokens" ("id" bigserial not null primary key, "tokenable_type" varchar(255) not null, "tokenable_id" bigint not null, "name" text 
 -7385071278824734236 |     1 |     0.69 |    0 | create table "activity_log" ("id" bigserial not null primary key, "log_name" varchar(255) null, "description" text not null, "subject_type" varchar(255) null, "
 -1807609391104987844 |     1 |     0.67 |    0 | alter table "model_has_permissions" add constraint "model_has_permissions_permission_id_foreign" foreign key ("permission_id") references "permissions" ("id") o
   932551361256582385 |     3 |     0.62 |    9 | SELECT r.rolname AS role, m.rolname AS member FROM pg_auth_members am JOIN pg_roles r ON r.oid = am.roleid JOIN pg_roles m ON m.oid = am.member ORDER BY r.rolna
  4799360028626805999 |     1 |     0.59 |    0 | create table "permissions" ("id" bigserial not null primary key, "name" varchar(255) not null, "guard_name" varchar(255) not null, "created_at" timestamp(0) wit
(25 rows)

``

## Timescale hypertables
``text
 hypertable_schema | hypertable_name | owner | num_dimensions | num_chunks | compression_enabled | tablespaces | primary_dimension | primary_dimension_type 
-------------------+-----------------+-------+----------------+------------+---------------------+-------------+-------------------+------------------------
(0 rows)

``

## pg_cron jobs
``text
 jobid | schedule | command | active 
-------+----------+---------+--------
(0 rows)

``

## pg_cron recent runs jsonl latest 50
``text
(no rows)
``

## PostGIS full version
``text
                                                                                                                                                                        postgis_full_version                                                                                                                                                                        
--------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------
 POSTGIS="3.5.2 dea6d0a" [EXTENSION] PGSQL="170" GEOS="3.9.0-CAPI-1.16.2" PROJ="7.2.1 NETWORK_ENABLED=OFF URL_ENDPOINT=https://cdn.proj.org USER_WRITABLE_DIRECTORY=/var/lib/postgresql/.local/share/proj DATABASE_PATH=/usr/share/proj/proj.db" (compiled against PROJ 7.2.1) LIBXML="2.9.10" LIBJSON="0.15" LIBPROTOBUF="1.3.3" WAGYU="0.5.0 (Internal)" TOPOLOGY
(1 row)

``

# Health

## mg_ctl.health_check
``text
{"now": "2025-09-25T20:48:21.3402-04:00", "uptime": "00:47:31.524106", "version": "PostgreSQL 17.6 (Debian 17.6-2.pgdg11+1) on x86_64-pc-linux-gnu, compiled by gcc (Debian 10.2.1-6) 10.2.1 20210110, 64-bit", "db_count": 3, "ext_count": 20}
``

# Summary

- Databases: **3**
- Extensions in magdsdb: **20**

