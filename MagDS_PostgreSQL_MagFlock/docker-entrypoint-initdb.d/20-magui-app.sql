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
