#!/usr/bin/env bash
# Install/upgrade UTStats on the host from an unpacked release. Run as root by
# /usr/local/sbin/utstats-deploy (cloud-init on first boot, `just deploy` afterwards).
# Root only installs files; every container runs ROOTLESS under the `utstats` user's
# systemd manager (quadlets in ~utstats/.config/containers/systemd). Idempotent.
set -euo pipefail

src=${1:?usage: install.sh <unpacked release dir>}
region=${UTSTATS_REGION:?}
bucket_region=${UTSTATS_BUCKET_REGION:?}
ssm_prefix=/utstats
geoip_uri="s3://fragmath-build-artifacts/geoip/GeoLite2-Country.mmdb"
home=/var/lib/utstats
data=$home/data

log() { echo "[utstats-install] $*"; }

# 1. Service user: subuids for rootless podman, linger so its units run without a login.
if ! id utstats >/dev/null 2>&1; then
    useradd --system --create-home --home-dir "$home" --shell /bin/bash --comment "UTStats containers" utstats
fi
grep -q '^utstats:' /etc/subuid || usermod --add-subuids 1000000-1065535 --add-subgids 1000000-1065535 utstats
loginctl enable-linger utstats
uid=$(id -u utstats)
for _ in $(seq 30); do [[ -d /run/user/$uid ]] && break; sleep 1; done
# Login shells (just host-shell) need the user runtime dir for podman/systemctl --user.
cat >"$home/.bash_profile" <<EOF
export XDG_RUNTIME_DIR=/run/user/$uid
cd ~
EOF
chown utstats:utstats "$home/.bash_profile"
install -d -o utstats -g utstats "$home/.config" "$home/.config/containers" "$home/.config/containers/systemd" \
    "$home/.config/systemd" "$home/.config/systemd/user"

# Rootless Caddy binds 80/443.
echo "net.ipv4.ip_unprivileged_port_start = 80" >/etc/sysctl.d/90-utstats.conf
sysctl -q --system

# Helpers for root (host-run, db-restore): act as the utstats user.
cat >/usr/local/sbin/utstats-podman <<EOF
#!/bin/sh
cd $home && exec runuser -u utstats -- env XDG_RUNTIME_DIR=/run/user/$uid HOME=$home podman "\$@"
EOF
cat >/usr/local/sbin/utstats-systemctl <<'EOF'
#!/bin/sh
exec systemctl --user -M utstats@ "$@"
EOF
chmod 755 /usr/local/sbin/utstats-podman /usr/local/sbin/utstats-systemctl

# 2. Code: replace /opt/utstats/current atomically. Readable by the containers.
install -d -m 755 /opt/utstats
rm -rf /opt/utstats/next
cp -a "$src" /opt/utstats/next
chmod -R a+rX /opt/utstats/next
rm -rf /opt/utstats/previous
[[ -d /opt/utstats/current ]] && mv /opt/utstats/current /opt/utstats/previous
mv /opt/utstats/next /opt/utstats/current
chcon -R -t container_file_t /opt/utstats/current
log "release $(cat /opt/utstats/current/RELEASE 2>/dev/null || echo unknown) installed"

# 3. Secrets: SSM SecureStrings -> env files readable only by root and utstats.
install -d -m 750 -o root -g utstats /etc/utstats
params=$(aws ssm get-parameters-by-path --region "$region" --path "$ssm_prefix" --with-decryption \
    --query 'Parameters[].[Name,Value]' --output json)
get() { jq -r --arg n "$ssm_prefix/$1" '.[] | select(.[0] == $n) | .[1]' <<<"$params"; }
for name in UTSTATS_DB_NAME UTSTATS_DB_USER UTSTATS_DB_PASS UTSTATS_ADMIN_KEY UTSTATS_FTP_SERVERS MARIADB_ROOT_PASSWORD; do
    value=$(get "$name")
    [[ -n $value && $value != unset ]] || { echo "SSM parameter $ssm_prefix/$name is not set (run: just secrets-push)" >&2; exit 1; }
done
write_env() { install -m 640 -o root -g utstats /dev/stdin "/etc/utstats/$1"; }
write_env app.env <<EOF
UTSTATS_DB_HOST=db
UTSTATS_DB_NAME=$(get UTSTATS_DB_NAME)
UTSTATS_DB_USER=$(get UTSTATS_DB_USER)
UTSTATS_DB_PASS=$(get UTSTATS_DB_PASS)
UTSTATS_ADMIN_KEY=$(get UTSTATS_ADMIN_KEY)
UTSTATS_FTP_SERVERS=$(get UTSTATS_FTP_SERVERS)
UTSTATS_GEOIP_DB=/data/GeoLite2-Country.mmdb
EOF
write_env db.env <<EOF
MARIADB_ROOT_PASSWORD=$(get MARIADB_ROOT_PASSWORD)
MARIADB_DATABASE=$(get UTSTATS_DB_NAME)
MARIADB_USER=$(get UTSTATS_DB_USER)
MARIADB_PASSWORD=$(get UTSTATS_DB_PASS)
EOF
write_env caddy.env <<<"UTSTATS_SITE=utstats.ut.banko.gg, utstats.passionategaming.eu"

# 4. Runtime state: owned by www-data (uid 33) inside the containers' user namespace.
install -d -m 755 -o utstats -g utstats "$data"
utstats-podman unshare sh -c "mkdir -p $data/logs/backup $data/logs/ac $data/logs/ace $data/logs/utdc $data/renderings \
    && chown 33:33 $data/logs $data/logs/backup $data/logs/ac $data/logs/ace $data/logs/utdc $data/renderings"
if aws s3 cp --region "$bucket_region" --only-show-errors "$geoip_uri" "$data/GeoLite2-Country.mmdb.new"; then
    chmod 644 "$data/GeoLite2-Country.mmdb.new"
    mv "$data/GeoLite2-Country.mmdb.new" "$data/GeoLite2-Country.mmdb"
else
    log "WARNING: GeoIP download failed; keeping the previous mmdb (if any)"
fi
chcon -R -t container_file_t "$data"

# 5. PHP image, built from the release into the utstats user's storage.
utstats-podman build --pull=newer -t localhost/utstats-php:latest -f /opt/utstats/current/docker/php/Containerfile /opt/utstats/current
utstats-podman image prune -f >/dev/null

# 6. User units.
# (config dirs were created in step 1)
install -m 644 -o utstats -g utstats /opt/utstats/current/deploy/quadlet/* "$home/.config/containers/systemd/"
install -m 644 -o utstats -g utstats /opt/utstats/current/deploy/systemd/* "$home/.config/systemd/user/"
utstats-systemctl daemon-reload
utstats-systemctl restart utstats-db.service
utstats-systemctl restart utstats-php.service utstats-caddy.service

# The importer pulls (and deletes) logs from the game servers' FTP. Only one host may do
# that, so the timer stays off until /utstats/IMPORT_ENABLED is "true".
if [[ $(aws ssm get-parameter --region "$region" --name "$ssm_prefix/IMPORT_ENABLED" --query Parameter.Value --output text 2>/dev/null) == true ]]; then
    utstats-systemctl enable --now utstats-import.timer
else
    utstats-systemctl disable --now utstats-import.timer 2>/dev/null || true
    log "importer timer disabled (set $ssm_prefix/IMPORT_ENABLED=true to enable)"
fi
log "services: $(utstats-systemctl is-active utstats-db utstats-php utstats-caddy | tr '\n' ' ')"
