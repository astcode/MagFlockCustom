# To spin a fresh, clean DB from this package

## This deletes data in the current volume—exactly what “fresh” means.
```bat
docker compose down --volumes --remove-orphans
docker volume rm magdsdb_magdsdb_pgdata
docker compose up -d --build
docker logs -f magdsdb-pg
```

## Checks
```
docker exec -it magdsdb-pg psql -U magdsdb_admin -d magdsdb -c "SHOW server_version;"
docker exec -it magdsdb-pg psql -U magdsdb_admin -d magdsdb -c "SHOW shared_preload_libraries;"
docker exec -it magdsdb-pg psql -U magdsdb_admin -d magdsdb -c "SELECT name, installed_version FROM pg_available_extensions WHERE installed_version IS NOT NULL ORDER BY name;"
```



## Optional: export the image as a file (to share/install elsewhere)
```
docker image tag magdsdb-postgres:latest magdsdb-postgres:v1
docker save -o magdsdb-postgres_v1.tar magdsdb-postgres:v1
```

### On another machine:
```
docker load -i magdsdb-postgres_v1.tar
```
# then place the same docker-compose.yml and run `docker compose up -d`






### // This command allows you to open an interactive PostgreSQL shell inside a running Docker container named "magdsdb-pg".
 `docker exec -it magdsdb-pg ...` 
 ### runs a command inside the "magdsdb-pg" container with interactive terminal support.
#### `psql` is the PostgreSQL command-line client.
#### `-U magdsdb_admin` specifies the database user to connect as ("magdsdb_admin").
#### `-d magdsdb` specifies the database to connect to ("magdsdb").

### When you run this command, you will be dropped into a PostgreSQL prompt inside the container, authenticated as "magdsdb_admin" and connected to the "magdsdb" database. This is useful for running SQL queries, inspecting tables, or performing administrative tasks directly within the database environment.
`docker exec -it DATABASE psql -U DATABASE_USERNAME -d PASSWORD`
`docker exec -it magdsdb-pg psql -U magdsdb_admin -d magdsdb`
