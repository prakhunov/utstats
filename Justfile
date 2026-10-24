# UTStats task runner. `just` lists recipes.

set shell := ["bash", "-cu"]

compose := "podman compose"
dump := env_var_or_default("UTSTATS_DUMP", env_var("HOME") / "workspace/fragmath/backtesting/sources/2026-03-07/stats.sql")

default:
    @just --list

# --- Lint ---------------------------------------------------------------

# Syntax-check every PHP file with the devshell's PHP 8.5
lint-syntax:
    #!/usr/bin/env bash
    set -euo pipefail
    fail=0
    while IFS= read -r -d '' f; do
        out=$(php -d error_reporting=E_ALL -l "$f" 2>&1) || fail=1
        grep -v '^No syntax errors' <<<"$out" || true
    done < <(git ls-files -z --cached --others --exclude-standard -- '*.php' '*.inc')
    exit $fail

# PHPCompatibility sniffs for PHP 8.5
lint-compat *args:
    phpcs {{args}}

# PHPStan (level 0: unknown functions/classes/constants, arg counts)
lint-stan *args:
    phpstan analyse --memory-limit=1G --no-progress {{args}}

# All lint gates
lint: lint-syntax lint-compat lint-stan

# --- Containers ---------------------------------------------------------

# Build and start db + php + caddy (http://localhost:8080)
up:
    mkdir -p .verify/data/logs/{backup,ac,ace,utdc} .verify/data/renderings
    {{compose}} up -d --build db php caddy

down:
    {{compose}} --profile baseline down

logs *args:
    {{compose}} logs {{args}}

# Load the UTStats MySQL dump into MariaDB and convert to InnoDB (replaces table contents)
db-load file=dump:
    scripts/db-load.sh {{file}}

# Interactive MariaDB shell (or `just sql -e "<query>"`)
sql *args:
    {{compose}} exec db sh -c 'mariadb -u"$MARIADB_USER" -p"$MARIADB_PASSWORD" "$MARIADB_DATABASE" "$@"' _ {{args}}

# Run the importer once from the CLI (the web importer is blocked at Caddy)
import *args:
    {{compose}} exec -u www-data php php import.php {{args}}

# Serve the original PHP 5.5 code on :8055 for diffing (local only)
baseline-up:
    scripts/baseline-setup.sh
    {{compose}} --profile baseline up -d --build baseline

# --- Verification ---------------------------------------------------------

# Regenerate .verify/routes.txt from the loaded DB
crawl-routes:
    scripts/crawl_diff.py routes

# Diff 5.5 baseline vs 8.5 port (`just crawl --grep p=match -v`)
crawl *args:
    scripts/crawl_diff.py {{args}}

# Format PHP (php-cs-fixer, PSR-12)
fmt:
    php-cs-fixer fix

fmt-check:
    php-cs-fixer fix --dry-run --diff

# --- Production (EC2 host, see infra/ and deploy/) --------------------------

releases := "s3://fragmath-build-artifacts/utstats"
s3_region := "us-east-2"
host_region := "eu-central-1"

# Package HEAD as a release tarball and upload it (also as latest)
release:
    #!/usr/bin/env bash
    set -euo pipefail
    [[ -z $(git status --porcelain) ]] || { echo "commit first: working tree is dirty" >&2; exit 1; }
    rev=$(git rev-parse --short=12 HEAD)
    tmp=$(mktemp -d); trap 'rm -rf "$tmp"' EXIT
    git archive --format=tar --prefix=./ HEAD > "$tmp/r.tar"
    echo "$rev" > "$tmp/RELEASE"; tar -rf "$tmp/r.tar" -C "$tmp" ./RELEASE
    gzip -9 "$tmp/r.tar"
    aws s3 cp --region {{s3_region}} --only-show-errors "$tmp/r.tar.gz" "{{releases}}/releases/$rev.tar.gz"
    aws s3 cp --region {{s3_region}} --only-show-errors "$tmp/r.tar.gz" "{{releases}}/releases/latest.tar.gz"
    echo "released $rev"

# Run a command on the host via SSM and print its output (`just host-run "systemctl status utstats-php"`)
host-run cmd:
    #!/usr/bin/env bash
    set -euo pipefail
    id=$(tofu -chdir=infra output -raw instance_id)
    cmd={{quote(cmd)}}
    cid=$(aws ssm send-command --region {{host_region}} --instance-ids "$id" --document-name AWS-RunShellScript \
        --timeout-seconds 3600 --parameters "$(jq -cn --arg c "$cmd" '{commands:[$c], executionTimeout:["3600"]}')" \
        --query Command.CommandId --output text)
    while s=$(aws ssm get-command-invocation --region {{host_region}} --command-id "$cid" --instance-id "$id" --query Status --output text 2>/dev/null); [[ $s == Pending || $s == InProgress || $s == Delayed || -z $s ]]; do sleep 3; done
    aws ssm get-command-invocation --region {{host_region}} --command-id "$cid" --instance-id "$id" \
        --query '[StandardOutputContent,StandardErrorContent]' --output text
    [[ $s == Success ]] || { echo "status: $s" >&2; exit 1; }

# Release HEAD and install it on the host
deploy: release
    just host-run "/usr/local/sbin/utstats-deploy $(git rev-parse --short=12 HEAD)"

# Shell as the utstats user, who owns the rootless containers (`podman ps`,
# `systemctl --user status utstats-php`). For root use `just host-root-shell`
host-shell:
    aws ssm start-session --region {{host_region}} --target $(tofu -chdir=infra output -raw instance_id) \
        --document-name AWS-StartInteractiveCommand --parameters command="sudo -iu utstats"

# Root shell on the host (installs, journalctl -u amazon-ssm-agent, utstats-podman ...)
host-root-shell:
    aws ssm start-session --region {{host_region}} --target $(tofu -chdir=infra output -raw instance_id) \
        --document-name AWS-StartInteractiveCommand --parameters command="sudo -i"

# Push production secrets from a local env file (default .env.prod) to SSM
secrets-push file=".env.prod":
    #!/usr/bin/env bash
    set -euo pipefail
    set -a; source "{{file}}"; set +a
    for name in UTSTATS_DB_NAME UTSTATS_DB_USER UTSTATS_DB_PASS UTSTATS_ADMIN_KEY UTSTATS_FTP_SERVERS MARIADB_ROOT_PASSWORD; do
        [[ -n ${!name:-} ]] || { echo "$name missing in {{file}}" >&2; exit 1; }
        aws ssm put-parameter --region {{host_region}} --name "/utstats/$name" --type SecureString --overwrite --value "${!name}" >/dev/null
        echo "set /utstats/$name"
    done

# Upload a mysqldump and load it on the host (replaces the production DB contents)
db-push file:
    aws s3 cp --region {{s3_region}} --only-show-errors "{{file}}" "{{releases}}/dumps/$(basename {{file}})"
    just host-run "UTSTATS_BUCKET_REGION={{s3_region}} bash /opt/utstats/current/deploy/db-restore.sh {{releases}}/dumps/$(basename {{file}})"

# Turn the minutely importer on/off on the host (only ONE host may pull the FTP logs)
importer state:
    aws ssm put-parameter --region {{host_region}} --name /utstats/IMPORT_ENABLED --type String --overwrite --value {{ if state == "on" { "true" } else { "false" } }} >/dev/null
    just host-run "utstats-systemctl {{ if state == "on" { "enable --now" } else { "disable --now" } }} utstats-import.timer; utstats-systemctl is-active utstats-import.timer || true"
