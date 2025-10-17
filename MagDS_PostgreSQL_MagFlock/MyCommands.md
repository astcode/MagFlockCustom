
// This command allows you to open an interactive PostgreSQL shell inside a running Docker container named "magdsdb-pg".
// 
// - `docker exec -it magdsdb-pg ...` runs a command inside the "magdsdb-pg" container with interactive terminal support.
// - `psql` is the PostgreSQL command-line client.
// - `-U magui_admin` specifies the database user to connect as ("magui_admin").
// - `-d magui_appagui` specifies the database to connect to ("magui_app").
//
// When you run this command, you will be dropped into a PostgreSQL prompt inside the container, authenticated as "magdsdb_admin" or "magui_app" and connected to the "magui_app" database. This is useful for running SQL queries, inspecting tables, or performing administrative tasks directly within the database environment.
`docker exec -it magdsdb-pg psql -U magui_app -d magdsdb_admin`
`docker exec -it magdsdb-pg psql -U magui_app -d magui_app`



# Fill in your connection details
$env:PGPASSWORD = 'magdsdb_admin'
psql -h localhost -p 5432 -U magdsdb_admin -d magui_app -f .\db_inventory.sql > db_inventory_report.txt
