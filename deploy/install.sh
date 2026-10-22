#!/usr/bin/env bash
# Install/upgrade UTStats on the host from an unpacked release. Run as root by
# /usr/local/sbin/utstats-deploy (cloud-init on first boot, `just deploy` afterwards).
# Idempotent: safe to re-run with the same release.
set -euo pipefail

src=${1:?usage: install.sh <unpacked release dir>}
region=${UTSTATS_REGION:?}
bucket_region=${UTSTATS_BUCKET_REGION:?}
# The importer pulls (and deletes) logs from the game servers' FTP. Only one host may do
# that, so the timer stays off until /utstats/IMPORT_ENABLED is "true" (set at cutover,
# after the old host's cron is stopped).
ssm_prefix=/utstats
geoip_uri="s3://fragmath-build-artifacts/geoip/GeoLite2-Country.mmdb"
www_uid=33 # www-data in the php image

log() { echo "[utstats-install] $*"; }

# 1. Code: replace /opt/utstats/current atomically.
install -d -m 755 /opt/utstats
rm -rf /opt/utstats/next
cp -a "$src" /opt/utstats/next
rm -rf /opt/utstats/previous
[[ -d /opt/utstats/current ]] && mv /opt/utstats/current /opt/utstats/previous
mv /opt/utstats/next /opt/utstats/current
log "release $(cat /opt/utstats/current/RELEASE 2>/dev/null || echo unknown) installed"

# 2. Secrets: SSM SecureStrings -> root-only env files.
install -d -m 700 /etc/utstats
params=$(aws ssm get-parameters-by-path --region "$region" --path "$ssm_prefix" --with-decryption \
    --query 'Parameters[].[Name,Value]' --output json)
get() { jq -r --arg n "$ssm_prefix/$1" '.[] | select(.[0] == $n) | .[1]' <<<"$params"; }
for name in UTSTATS_DB_NAME UTSTATS_DB_USER UTSTATS_DB_PASS UTSTATS_ADMIN_KEY UTSTATS_FTP_SERVERS MARIADB_ROOT_PASSWORD; do
    value=$(get "$name")
    [[ -n $value && $value != unset ]] || { echo "SSM parameter $ssm_prefix/$name is not set (run: just secrets-push)" >&2; exit 1; }
done
umask 077
{
    echo "UTSTATS_DB_HOST=db"
    echo "UTSTATS_DB_NAME=$(get UTSTATS_DB_NAME)"
    echo "UTSTATS_DB_USER=$(get UTSTATS_DB_USER)"
    echo "UTSTATS_DB_PASS=$(get UTSTATS_DB_PASS)"
    echo "UTSTATS_ADMIN_KEY=$(get UTSTATS_ADMIN_KEY)"
    echo "UTSTATS_FTP_SERVERS=$(get UTSTATS_FTP_SERVERS)"
    echo "UTSTATS_GEOIP_DB=/data/GeoLite2-Country.mmdb"
} >/etc/utstats/app.env
{
    echo "MARIADB_ROOT_PASSWORD=$(get MARIADB_ROOT_PASSWORD)"
    echo "MARIADB_DATABASE=$(get UTSTATS_DB_NAME)"
    echo "MARIADB_USER=$(get UTSTATS_DB_USER)"
    echo "MARIADB_PASSWORD=$(get UTSTATS_DB_PASS)"
} >/etc/utstats/db.env
echo "UTSTATS_SITE=utstats.ut.banko.gg, utstats.passionategaming.eu" >/etc/utstats/caddy.env
umask 022

# 3. Runtime state dirs + GeoIP database.
install -d -o "$www_uid" -g "$www_uid" /var/lib/utstats/data /var/lib/utstats/data/renderings \
    /var/lib/utstats/data/logs /var/lib/utstats/data/logs/{backup,ac,ace,utdc}
if aws s3 cp --region "$bucket_region" --only-show-errors "$geoip_uri" /var/lib/utstats/data/GeoLite2-Country.mmdb.new; then
    mv /var/lib/utstats/data/GeoLite2-Country.mmdb.new /var/lib/utstats/data/GeoLite2-Country.mmdb
else
    log "WARNING: GeoIP download failed; keeping the previous mmdb (if any)"
fi

# 4. PHP image, built from the release on this (arm64) host.
podman build --pull=newer -t localhost/utstats-php:latest -f /opt/utstats/current/docker/php/Containerfile /opt/utstats/current
podman image prune -f >/dev/null

# 5. Units.
install -m 644 /opt/utstats/current/deploy/quadlet/* /etc/containers/systemd/
install -m 644 /opt/utstats/current/deploy/systemd/* /etc/systemd/system/
systemctl daemon-reload
systemctl restart utstats-db.service
systemctl restart utstats-php.service utstats-caddy.service
if [[ $(aws ssm get-parameter --region "$region" --name "$ssm_prefix/IMPORT_ENABLED" --query Parameter.Value --output text 2>/dev/null) == true ]]; then
    systemctl enable --now utstats-import.timer
else
    systemctl disable --now utstats-import.timer 2>/dev/null || true
    log "importer timer disabled (set $ssm_prefix/IMPORT_ENABLED=true to enable)"
fi
log "services: $(systemctl is-active utstats-db utstats-php utstats-caddy | tr '\n' ' ')"
