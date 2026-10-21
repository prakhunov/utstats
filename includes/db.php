<?php
// PDO database access. Every query takes its values as bound parameters:
//   db_row('SELECT name FROM uts_pinfo WHERE id = ?', [$pid])
//   db_all('SELECT ... WHERE gid = :gid LIMIT :n', ['gid' => $gid, 'n' => 25])
// Identifiers (table/column names, ORDER BY, ASC/DESC) cannot be bound; pick them from a
// hardcoded allowlist with db_pick() instead of splicing request input into SQL.
//
// Fetched rows have both numeric and column-name keys and string values, like the
// mysql_fetch_array() results the pages were written against.

function db(): PDO {
	static $pdo = null;
	if ($pdo !== null) return $pdo;

	global $hostname, $dbport, $dbname, $uname, $upass;
	$pdo = new PDO(
		"mysql:host=$hostname;port=$dbport;dbname=$dbname;charset=utf8",
		$uname,
		$upass,
		[
			PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
			PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_BOTH,
			// Client-side prepares return every value as a string (as ext/mysql did) and stay
			// injection-safe because the connection charset is fixed in the DSN above.
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
function db_query(string $sql, array $params = []): PDOStatement {
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
function db_row(string $sql, array $params = []): array|false {
	return db_query($sql, $params)->fetch();
}

// All rows.
function db_all(string $sql, array $params = []): array {
	return db_query($sql, $params)->fetchAll();
}

// First column of the first row, or null when there is no row.
function db_value(string $sql, array $params = []): ?string {
	$value = db_query($sql, $params)->fetchColumn();
	return $value === false ? null : $value;
}

// Number of rows a SELECT returns (replaces small_count()). Exact because pdo_mysql buffers results.
function db_count(string $sql, array $params = []): int {
	return db_query($sql, $params)->rowCount();
}

// INSERT/UPDATE/DELETE/DDL; returns affected rows.
function db_exec(string $sql, array $params = []): int {
	return db_query($sql, $params)->rowCount();
}

function db_insert_id(): int {
	return (int) db()->lastInsertId();
}

// Return $value if it is one of $allowed, else $default. For identifiers that cannot be bound.
function db_pick(mixed $value, array $allowed, string $default): string {
	return in_array($value, $allowed, true) ? $value : $default;
}
