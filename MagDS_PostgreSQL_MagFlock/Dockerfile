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