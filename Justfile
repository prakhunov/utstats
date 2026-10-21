# UTStats task runner. `just` lists recipes.

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
    mkdir -p .verify/data/logs .verify/data/renderings
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
