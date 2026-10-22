#!/usr/bin/env bash
# On the host: load a mysqldump from S3 into utstats-db and convert tables to InnoDB.
# Replaces table contents. Usage: db-restore.sh s3://bucket/utstats/dumps/<file>.sql.gz
set -euo pipefail

uri=${1:?usage: db-restore.sh <s3 uri of .sql or .sql.gz>}
bucket_region=${UTSTATS_BUCKET_REGION:-us-east-2}

db() {
    podman exec -i utstats-db sh -c 'mariadb -u root -p"$MARIADB_ROOT_PASSWORD" "$MARIADB_DATABASE" "$@"' _ "$@"
}

echo "Stopping importer + PHP during the restore"
systemctl stop utstats-import.timer utstats-php.service 2>/dev/null || true

echo "Loading $uri"
if [[ $uri == *.gz ]]; then
    aws s3 cp --region "$bucket_region" --only-show-errors "$uri" - | gzip -dc | db
else
    aws s3 cp --region "$bucket_region" --only-show-errors "$uri" - | db
fi

echo "Converting MyISAM tables to InnoDB"
db -N -e "SELECT table_name FROM information_schema.tables WHERE table_schema = DATABASE() AND engine = 'MyISAM'" |
    while read -r table; do
        echo "  $table"
        db -e "ALTER TABLE \`$table\` ENGINE=InnoDB" </dev/null
    done

db -e "SELECT COUNT(*) AS matches, MAX(time) AS newest FROM uts_match"

# Caddy Requires= php, so stopping php above stopped it too.
systemctl start utstats-php.service utstats-caddy.service
if systemctl is-enabled --quiet utstats-import.timer 2>/dev/null; then systemctl start utstats-import.timer; fi
echo "Restore done"
