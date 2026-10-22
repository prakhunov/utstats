# UTStats — utstats.passionategaming.eu

UTStats for the Passionate Gaming UT99 servers, ported from PHP 5.5 + ext/mysql to
PHP 8.5 + PDO and containerized (MariaDB 12.3, PHP-FPM 8.5, Caddy).

## Local setup

```bash
direnv allow                 # nix devshell: php 8.5, composer, just, mariadb client
composer install             # dev tools: phpstan, phpcs + PHPCompatibility, rector
cp .env.example .env         # fill in DB creds, admin key, FTP servers (JSON)
just up                      # db + php + caddy on http://localhost:8080
just db-load <dump.sql[.gz]> # load a UTStats mysqldump, converts tables to InnoDB
```

## Common tasks

| Command | What |
|---|---|
| `just lint` | `php -l` on every file, PHPCompatibility (8.5), PHPStan level 0 |
| `just import` | run the log importer once (CLI; `/import.php` is not public) |
| `just sql -e "'SELECT ...'"` | query the dev DB |
| `just baseline-up` | original PHP 5.5 code on :8055 against the same DB (local only) |
| `just crawl` | diff every route in `.verify/routes.txt` between :8055 and :8080 |

## Layout notes

- Secrets come only from the environment (`includes/config.php` reads `UTSTATS_*`).
- All SQL goes through `includes/db.php` (bound parameters; `db_pick()` for identifiers).
- GeoIP uses a GeoLite2-Country `.mmdb` at `UTSTATS_GEOIP_DB` (default `/data/GeoLite2-Country.mmdb`).
- Runtime state (downloaded/backup logs, FTP timestamp, renderings) lives in `logs/` and
  `renderings/`, which are the `/data` volume in the image.
- Caddy only exposes `/`, `index.php`, `pic.php`, `api/*.php`, `assets/` and `robots.txt`;
  the admin UI and the web importer are not reachable from outside.

## Production

Host: t4g.small in eu-central-1 (`infra/`, OpenTofu), canonical name `utstats.ut.banko.gg`;
`utstats.passionategaming.eu` is a CNAME to it at NFO. Services are podman quadlets
(`deploy/quadlet/`) plus a minutely importer timer (`deploy/systemd/`).

| Command | What |
|---|---|
| `just deploy` | package HEAD to S3 and install it on the host |
| `just secrets-push` | push `.env.prod` (gitignored) to SSM `/utstats/*`; the only writer of those params |
| `just db-push <dump.sql.gz>` | replace the production DB with a dump |
| `just importer on\|off` | toggle the FTP importer (only ONE host may pull the game servers' logs) |
| `just host-run "<cmd>"` / `just host-shell` | run a command / open a shell on the host via SSM |
