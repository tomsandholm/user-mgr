<?php
require __DIR__ . '/auth.php';
require_login();

try {
    $pdo = db_connect($config);
} catch (\PDOException $e) {
    http_response_code(500);
    die('Database connection failed: ' . $e->getMessage());
}

function sql_quote(?string $value): string
{
    if ($value === null) {
        return 'NULL';
    }
    return "'" . str_replace(
        ["\\", "\0", "\n", "\r", "'", '"', "\x1a"],
        ["\\\\", "\\0", "\\n", "\\r", "\\'", '\\"', "\\Z"],
        $value
    ) . "'";
}

function dump_table(PDO $pdo, string $table): string
{
    $out = [];
    $out[] = "--";
    $out[] = "-- Table: `{$table}`";
    $out[] = "--";
    $out[] = "";

    $create = $pdo->query("SHOW CREATE TABLE `{$table}`")->fetch(PDO::FETCH_NUM);
    if ($create) {
        $out[] = "DROP TABLE IF EXISTS `{$table}`;";
        $out[] = $create[1] . ";";
        $out[] = "";
    }

    $numericCols = [];
    foreach ($pdo->query("SHOW COLUMNS FROM `{$table}`")->fetchAll() as $col) {
        $type = strtolower($col['Type']);
        if (preg_match('/^(tiny|small|medium|big)?int|decimal|float|double|bit/', $type)) {
            $numericCols[$col['Field']] = true;
        }
    }

    $rows = $pdo->query("SELECT * FROM `{$table}`")->fetchAll(PDO::FETCH_ASSOC);
    if (count($rows) === 0) {
        $out[] = "-- No rows in `{$table}`";
        $out[] = "";
        return implode("\n", $out);
    }

    $columns = array_keys($rows[0]);
    $colList = '`' . implode('`, `', $columns) . '`';

    $out[] = "LOCK TABLES `{$table}` WRITE;";
    $out[] = "/*!40000 ALTER TABLE `{$table}` DISABLE KEYS */;";

    foreach ($rows as $row) {
        $values = [];
        foreach ($columns as $col) {
            $val = $row[$col];
            if ($val === null) {
                $values[] = 'NULL';
            } elseif (isset($numericCols[$col]) && $val !== '') {
                $values[] = (string)$val;
            } else {
                $values[] = sql_quote((string)$val);
            }
        }
        $out[] = "INSERT INTO `{$table}` ({$colList}) VALUES (" . implode(', ', $values) . ");";
    }

    $out[] = "/*!40000 ALTER TABLE `{$table}` ENABLE KEYS */;";
    $out[] = "UNLOCK TABLES;";
    $out[] = "";

    return implode("\n", $out);
}

$tables = ['users', 'hosts'];
$stamp = date('Y-m-d_His');
$filename = "user-mgr-dump_{$stamp}.sql";

$sql = [];
$sql[] = "-- user-mgr SQL dump";
$sql[] = "-- Generated: " . date('c');
$sql[] = "-- Tables: " . implode(', ', $tables);
$sql[] = "";
$sql[] = "SET SQL_MODE = \"NO_AUTO_VALUE_ON_ZERO\";";
$sql[] = "START TRANSACTION;";
$sql[] = "SET time_zone = \"+00:00\";";
$sql[] = "";
$sql[] = "/*!40101 SET NAMES utf8mb4 */;";
$sql[] = "";

foreach ($tables as $table) {
    $exists = $pdo->query("SHOW TABLES LIKE " . $pdo->quote($table))->fetch();
    if (!$exists) {
        $sql[] = "-- Table `{$table}` does not exist";
        $sql[] = "";
        continue;
    }
    $sql[] = dump_table($pdo, $table);
}

$sql[] = "COMMIT;";
$sql[] = "";

$body = implode("\n", $sql);

header('Content-Type: application/sql; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . strlen($body));
header('Cache-Control: no-store');
echo $body;
exit;
