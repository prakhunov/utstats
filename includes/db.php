<?php

// PDO database access. Every query takes its values as bound parameters:
//   db_row('SELECT name FROM uts_pinfo WHERE id = ?', [$pid])
//   db_all('SELECT ... WHERE gid = :gid LIMIT :n', ['gid' => $gid, 'n' => 25])
// Identifiers (table/column names, ORDER BY, ASC/DESC) cannot be bound; pick them from a
// hardcoded allowlist with db_pick() instead of splicing request input into SQL.
//
// Fetched rows have both numeric and column-name keys and string values, like the
// mysql_fetch_array() results the pages were written against.

function db(): PDO
{
    static $pdo = null;
    if ($pdo !== null) {
        return $pdo;
    }

    global $hostname, $dbport, $dbname, $uname, $upass;
    $pdo = new PDO(
        "mysql:host=$hostname;port=$dbport;dbname=$dbname;charset=utf8",
        $uname,
        $upass,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_BOTH,
            // Return every value as a string, as ext/mysql did (pdo_mysql on 8.1+ returns native
            // ints/floats even with emulation). Client-side prepares stay injection-safe because
            // the connection charset is fixed in the DSN above.
            PDO::ATTR_STRINGIFY_FETCHES => true,
            PDO::ATTR_EMULATE_PREPARES => true,
            Pdo\Mysql::ATTR_MULTI_STATEMENTS => false,
            // The schema and queries were written for MySQL 5.5's permissive mode.
            Pdo\Mysql::ATTR_INIT_COMMAND => "SET SESSION sql_mode = 'NO_ENGINE_SUBSTITUTION'",
        ]
    );
    return $pdo;
}

// Run a statement with bound parameters. Ints/bools/nulls bind with their own type so they
// work in LIMIT/OFFSET; everything else binds as a string.
function db_query(string $sql, array $params = []): PDOStatement
{
    $stmt = db()->prepare($sql);
    foreach ($params as $key => $value) {
        $name = is_int($key) ? $key + 1 : ':' . ltrim($key, ':');
        $type = match (true) {
            is_int($value) => PDO::PARAM_INT,
            is_bool($value) => PDO::PARAM_BOOL,
            $value === null => PDO::PARAM_NULL,
            default => PDO::PARAM_STR,
        };
        $stmt->bindValue($name, is_float($value) ? (string) $value : $value, $type);
    }
    $stmt->execute();
    return $stmt;
}

// First row, or false when there is none (replaces small_query()).
function db_row(string $sql, array $params = []): array|false
{
    return db_query($sql, $params)->fetch();
}

// All rows.
function db_all(string $sql, array $params = []): array
{
    return db_query($sql, $params)->fetchAll();
}

// First column of the first row, or null when there is no row.
function db_value(string $sql, array $params = []): ?string
{
    $value = db_query($sql, $params)->fetchColumn();
    return $value === false ? null : $value;
}

// Number of rows a SELECT returns (replaces small_count()). Exact because pdo_mysql buffers results.
function db_count(string $sql, array $params = []): int
{
    return db_query($sql, $params)->rowCount();
}

// INSERT/UPDATE/DELETE/DDL; returns affected rows.
function db_exec(string $sql, array $params = []): int
{
    return db_query($sql, $params)->rowCount();
}

function db_insert_id(): int
{
    return (int) db()->lastInsertId();
}

// Return $value if it is one of $allowed, else $default. For identifiers that cannot be bound.
function db_pick(mixed $value, array $allowed, string $default): string
{
    return in_array($value, $allowed, true) ? $value : $default;
}

// --- Cached reads ---------------------------------------------------------------
// For expensive all-time aggregates (home page totals, list pages). Results are keyed on
// the SQL, its parameters and a cache generation that import.php bumps (db_cache_bump)
// only after a run has finished writing its matches, so a page is never cached from a
// half-imported match. Admin edits show up after DB_CACHE_TTL.
// Files live in the PHP container's temp dir, shared by all FPM workers and the CLI
// importer (same container), and are dropped on restart.

const DB_CACHE_TTL = 3600;

function db_cache_dir(): string
{
    $dir = sys_get_temp_dir() . '/utstats-cache';
    if (!is_dir($dir)) {
        @mkdir($dir, 0700, true);
    }
    return $dir;
}

function db_cache_generation(): string
{
    static $generation = null;
    return $generation ??= ((string) @file_get_contents(db_cache_dir() . '/generation') ?: '0');
}

// Start a new cache generation: every cached result is recomputed on next use.
function db_cache_bump(): void
{
    $file = db_cache_dir() . '/generation';
    $tmp = $file . '.' . getmypid();
    if (@file_put_contents($tmp, bin2hex(random_bytes(8))) !== false) {
        rename($tmp, $file);
    }
}

function db_all_cached(string $sql, array $params = []): array
{
    $dir = db_cache_dir();
    $file = $dir . '/' . hash('xxh128', db_cache_generation() . "\0" . $sql . "\0" . serialize($params));

    $rows = db_cache_read($file);
    if ($rows !== null) {
        return $rows;
    }

    // One worker computes a missing result; concurrent requests for it wait, then reuse it.
    $lock = @fopen($file . '.lock', 'c');
    if ($lock !== false) {
        flock($lock, LOCK_EX);
        $rows = db_cache_read($file);
        if ($rows !== null) {
            fclose($lock);
            return $rows;
        }
    }

    $rows = db_all($sql, $params);
    $tmp = $file . '.' . getmypid();
    if (@file_put_contents($tmp, serialize($rows)) !== false) {
        rename($tmp, $file); // atomic: readers never see a partial file
    }
    if ($lock !== false) {
        fclose($lock);
    }
    if (random_int(0, 200) === 0) {
        db_cache_prune($dir);
    }
    return $rows;
}

function db_cache_read(string $file): ?array
{
    $mtime = @filemtime($file);
    if ($mtime === false || time() - $mtime >= DB_CACHE_TTL) {
        return null;
    }
    $rows = @unserialize((string) @file_get_contents($file), ['allowed_classes' => false]);
    return is_array($rows) ? $rows : null;
}

function db_row_cached(string $sql, array $params = []): array|false
{
    return db_all_cached($sql, $params)[0] ?? false;
}

function db_cache_prune(string $dir): void
{
    foreach (glob($dir . '/*') ?: [] as $f) {
        if (basename($f) !== 'generation' && time() - (int) @filemtime($f) > DB_CACHE_TTL) {
            @unlink($f);
        }
    }
}
