#!/usr/bin/env bash
# Prepare .verify/baseline: the original PHP 5.5 code (initial commit) with a config.php
# pointing at the compose MariaDB. Local verification only.
set -euo pipefail
cd "$(git rev-parse --show-toplevel)"

base_rev=$(git rev-list --max-parents=0 HEAD)
dir=.verify/baseline
rm -rf "$dir"
mkdir -p "$dir"
git archive "$base_rev" | tar -x -C "$dir"

# The original config.php was never committed; rebuild it from the template with env lookups
# PHP 5.5 understands (no typed params / arrow functions).
sed -E \
    -e 's/^\$dbname = .*/$dbname = getenv("UTSTATS_DB_NAME");/' \
    -e 's/^\$hostname = .*/$hostname = getenv("UTSTATS_DB_HOST");/' \
    -e 's/^\$uname = .*/$uname = getenv("UTSTATS_DB_USER");/' \
    -e 's/^\$upass = .*/$upass = getenv("UTSTATS_DB_PASS");/' \
    -e 's/^\$import_adminkey = .*/$import_adminkey = getenv("UTSTATS_ADMIN_KEY");/' \
    "$dir/includes/config.php.example" >"$dir/includes/config.php"
mkdir -p "$dir/logs"
chmod -R a+rwX "$dir/logs"
echo "baseline ready at $dir ($base_rev)"
