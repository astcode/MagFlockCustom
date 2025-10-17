# MagDS Postgres for MagFlock

## File Tree for MagDS_PostgreSQL_MagFlock

* MagDS_PostgreSQL_MagFlock
* [docker/](.\MagDS_PostgreSQL_MagFlock\docker)
  * [db/](.\MagDS_PostgreSQL_MagFlock\docker\db)
    * [Dockerfile](.\MagDS_PostgreSQL_MagFlock\docker\db\Dockerfile)
* [docker-entrypoint-initdb.d/](.\MagDS_PostgreSQL_MagFlock\docker-entrypoint-initdb.d)
  * [10-extensions.sql](.\MagDS_PostgreSQL_MagFlock\docker-entrypoint-initdb.d\10-extensions.sql)
  * [20-magui-app.sql](.\MagDS_PostgreSQL_MagFlock\docker-entrypoint-initdb.d\20-magui-app.sql)
* [.env](.\MagDS_PostgreSQL_MagFlock\.env)
* [docker-compose.yml](.\MagDS_PostgreSQL_MagFlock\docker-compose.yml)
* [DockerCommands.md](.\MagDS_PostgreSQL_MagFlock\DockerCommands.md)
* [DockerSetupFiles.md](.\MagDS_PostgreSQL_MagFlock\DockerSetupFiles.md)
* [MagDS_Report.md](.\MagDS_PostgreSQL_MagFlock\MagDS_Report.md)
* [magds_report_MIN.ps1](.\MagDS_PostgreSQL_MagFlock\magds_report_MIN.ps1)
* [MyCommands.md](.\MagDS_PostgreSQL_MagFlock\MyCommands.md)


## docker-compose.yml
```yml
services:
  postgres:
    build:
      context: .
      dockerfile: docker/db/Dockerfile
    container_name: magdsdb-pg
    environment:
      POSTGRES_USER: magdsdb_admin
      POSTGRES_PASSWORD: magdsdb_admin
      POSTGRES_DB: magdsdb
      TZ: America/New_York
    ports:
      - "5433:5432"
    command: >
      postgres
      -c wal_level=logical
      -c max_wal_senders=10
      -c max_replication_slots=10
      -c shared_preload_libraries=timescaledb,pg_stat_statements,pg_cron,pgaudit,auto_explain
      -c cron.database_name=magdsdb
      -c pg_stat_statements.track=all
      -c pg_stat_statements.track_utility=on
      -c auto_explain.log_min_duration=500ms
      -c auto_explain.log_analyze=on
      -c pgaudit.log=ddl,role,read,write
      -c pgaudit.log_parameter=on
      -c max_connections=300
      -c statement_timeout=30s
      -c timezone=America/New_York
    volumes:
      - magdsdb_pgdata:/var/lib/postgresql/data
      - ./docker-entrypoint-initdb.d:/docker-entrypoint-initdb.d:ro
    restart: unless-stopped
    healthcheck:
      test: ["CMD-SHELL", "pg_isready -U $$POSTGRES_USER -d $$POSTGRES_DB || exit 1"]
      interval: 10s
      timeout: 5s
      retries: 6
    logging:
      driver: json-file
      options:
        max-size: "10m"
        max-file: "5"

volumes:
  magdsdb_pgdata:
```


## Dockerfile
O:\phpLaragon\www\magflock\MagDS_PostgreSQL_MagFlock\docker\db\Dockerfile
```cmd
# Debian-based PG17 + PostGIS (apt available)
FROM postgis/postgis:17-3.5

# Base tools
RUN apt-get update && apt-get install -y --no-install-recommends \
    curl gnupg ca-certificates lsb-release && rm -rf /var/lib/apt/lists/*

# Add PGDG repo and upgrade PostgreSQL 17 to latest minor (e.g., 17.6)
RUN . /etc/os-release && echo "deb http://apt.postgresql.org/pub/repos/apt ${VERSION_CODENAME}-pgdg main" \
    > /etc/apt/sources.list.d/pgdg.list && \
    curl -fsSL https://www.postgresql.org/media/keys/ACCC4CF8.asc | gpg --dearmor \
      -o /etc/apt/trusted.gpg.d/pgdg.gpg && \
    apt-get update && apt-get install -y --no-install-recommends \
      postgresql-17 postgresql-17-pgvector && \
    rm -rf /var/lib/apt/lists/*

# Add TimescaleDB repo and install TimescaleDB for PG17
RUN curl -fsSL https://packagecloud.io/install/repositories/timescale/timescaledb/script.deb.sh | bash && \
    apt-get update && apt-get install -y --no-install-recommends \
      timescaledb-2-postgresql-17 && \
    rm -rf /var/lib/apt/lists/*

# --- JOB SCHEDULER: pg_cron ---
    RUN apt-get update && apt-get install -y --no-install-recommends \
    postgresql-17-cron && rm -rf /var/lib/apt/lists/*

# --- AUDIT: pgaudit ---
RUN apt-get update && apt-get install -y --no-install-recommends \
    postgresql-17-pgaudit && rm -rf /var/lib/apt/lists/*

# --- MAINTENANCE: pg_repack (online vacuum/reorg without long locks) ---
RUN apt-get update && apt-get install -y --no-install-recommends \
    postgresql-17-repack && rm -rf /var/lib/apt/lists/*

# --- WHAT-IF INDEXES: hypopg (plan hypothetical indexes before creating) ---
RUN apt-get update && apt-get install -y --no-install-recommends \
    postgresql-17-hypopg && rm -rf /var/lib/apt/lists/*


# --- HTTP from SQL (pgsql-http) ---
# Uses PGDG repo you already added earlier in this Dockerfile.
RUN apt-get update && apt-get install -y --no-install-recommends \
    postgresql-17-http && \
    rm -rf /var/lib/apt/lists/*


# Copy init scripts that should run on FIRST volume init only
COPY docker-entrypoint-initdb.d/*.sql /docker-entrypoint-initdb.d/
```

## docker-entrypoint-initdb.d
O:\phpLaragon\www\magflock\MagDS_PostgreSQL_MagFlock\docker-entrypoint-initdb.d\10-extensions.sql
```sql
-- Create your full extension set in the default DB at first init
-- NOTE: entrypoint runs these as superuser (postgres)
CREATE EXTENSION IF NOT EXISTS pg_stat_statements;
CREATE EXTENSION IF NOT EXISTS pgcrypto;
CREATE EXTENSION IF NOT EXISTS citext;
CREATE EXTENSION IF NOT EXISTS hstore;
CREATE EXTENSION IF NOT EXISTS ltree;
CREATE EXTENSION IF NOT EXISTS pg_trgm;
CREATE EXTENSION IF NOT EXISTS unaccent;
CREATE EXTENSION IF NOT EXISTS fuzzystrmatch;
CREATE EXTENSION IF NOT EXISTS btree_gist;
CREATE EXTENSION IF NOT EXISTS btree_gin;
CREATE EXTENSION IF NOT EXISTS hypopg;
CREATE EXTENSION IF NOT EXISTS pg_repack;
CREATE EXTENSION IF NOT EXISTS pgaudit;
CREATE EXTENSION IF NOT EXISTS postgis;
CREATE EXTENSION IF NOT EXISTS postgis_topology;
CREATE EXTENSION IF NOT EXISTS postgis_tiger_geocoder;
CREATE EXTENSION IF NOT EXISTS vector;
-- Timescale requires preload; see compose. Then this works:
CREATE EXTENSION IF NOT EXISTS timescaledb;
-- pg_cron requires preload too; compose sets cron.database_name.
CREATE EXTENSION IF NOT EXISTS pg_cron;
```


O:\phpLaragon\www\magflock\MagDS_PostgreSQL_MagFlock\docker-entrypoint-initdb.d\20-magui-app.sql
```sql
-- Create the separate app database + owner for Laravel
DO $$
BEGIN
  IF NOT EXISTS (SELECT 1 FROM pg_roles WHERE rolname = 'magui_admin') THEN
    CREATE ROLE magui_admin WITH LOGIN PASSWORD 'magui_admin';
  END IF;
END$$;

-- Create magui_app DB owned by magui_admin (if not present)
DO $$
BEGIN
  IF NOT EXISTS (SELECT 1 FROM pg_database WHERE datname = 'magui_app') THEN
    CREATE DATABASE magui_app OWNER magui_admin TEMPLATE template0 ENCODING 'UTF8';
  END IF;
END$$;
```



# .env file for Laravel 12+
```env
APP_NAME=ApplicationName
APP_ENV=local
APP_KEY=
APP_DEBUG=true
APP_URL=http://sitehere.test
ASSET_URL=http://sitehere.test


APP_LOCALE=en
APP_FALLBACK_LOCALE=en
APP_FAKER_LOCALE=en_US

APP_MAINTENANCE_DRIVER=file
# APP_MAINTENANCE_STORE=database

PHP_CLI_SERVER_WORKERS=4

BCRYPT_ROUNDS=12

LOG_CHANNEL=stack
LOG_STACK=single
LOG_DEPRECATIONS_CHANNEL=null
LOG_LEVEL=debug

DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5433
DB_DATABASE=magui_app
DB_USERNAME=magui_admin
DB_PASSWORD=magui_admin

SESSION_DRIVER=database
SESSION_LIFETIME=120
SESSION_ENCRYPT=false
SESSION_PATH=/
SESSION_DOMAIN=null
SESSION_SECURE_COOKIE=false    # <— http on Laragon
SESSION_SAME_SITE=lax          # <— default good for Filament/Livewire

SESSION_COOKIE=magui_session   # <— unique cookie name per app

BROADCAST_CONNECTION=pusher
FILESYSTEM_DISK=local
QUEUE_CONNECTION=database

CACHE_STORE=array
# CACHE_PREFIX=

MEMCACHED_HOST=127.0.0.1

REDIS_CLIENT=phpredis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379

MAIL_MAILER=smtp
MAIL_HOST=127.0.0.1
MAIL_PORT=1025
MAIL_USERNAME=null
MAIL_PASSWORD=null
MAIL_ENCRYPTION=null
MAIL_FROM_ADDRESS=no-reply@magui.local
MAIL_FROM_NAME="MagUI"
QUEUE_CONNECTION=sync   # so emails send immediately without a queue worker


AWS_ACCESS_KEY_ID=
AWS_SECRET_ACCESS_KEY=
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=
AWS_USE_PATH_STYLE_ENDPOINT=false

VITE_APP_NAME="${APP_NAME}"



PGADMIN_HOST=127.0.0.1
PGADMIN_PORT=5433
PGADMIN_DATABASE=magdsdb
PGADMIN_USERNAME=magdsdb_admin
PGADMIN_PASSWORD=magdsdb_admin


BROADCAST_DRIVER=pusher
PUSHER_APP_ID=magui-local
PUSHER_APP_KEY=localkey
PUSHER_APP_SECRET=localsecret
PUSHER_HOST=127.0.0.1
PUSHER_PORT=6001
PUSHER_SCHEME=http
PUSHER_APP_CLUSTER=mt1




SANCTUM_STATEFUL_DOMAINS=magui.magflock.test,magapp.magflock.test
```