#!/usr/bin/env bash
# Load a UTStats mysqldump (MySQL 5.5, MyISAM) into the compose MariaDB and convert tables to InnoDB.
# Usage: scripts/db-load.sh <dump.sql|dump.sql.gz>
set -euo pipefail

dump=${1:?usage: db-load.sh <dump.sql[.gz]>}
[[ -f $dump ]] || { echo "no such dump: $dump" >&2; exit 1; }

compose=(podman compose)
"${compose[@]}" up -d --wait db

db_exec() {
    "${compose[@]}" exec -T db sh -c 'mariadb -u"$MARIADB_USER" -p"$MARIADB_PASSWORD" "$MARIADB_DATABASE" "$@"' _ "$@"
}

echo "Loading $dump ..."
if [[ $dump == *.gz ]]; then gzip -dc -- "$dump"; else cat -- "$dump"; fi | db_exec

echo "Converting MyISAM tables to InnoDB ..."
db_exec -N -e "SELECT table_name FROM information_schema.tables WHERE table_schema = DATABASE() AND engine = 'MyISAM'" |
    while read -r table; do
        echo "  $table"
        db_exec -e "ALTER TABLE \`$table\` ENGINE=InnoDB" </dev/null
    done

db_exec -e "SELECT table_name, engine, table_rows FROM information_schema.tables WHERE table_schema = DATABASE() ORDER BY table_name"
