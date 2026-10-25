<?php
declare(strict_types=1);

require __DIR__ . '/src/passwd_export.php';

$outputFile = $argv[1] ?? default_passwd_output_path();

$config = require __DIR__ . '/src/config.php';
$db = $config['db'];
$port = getenv('DB_PORT') ?: '3306';

$dsn = sprintf(
    'mysql:host=%s;port=%s;dbname=%s;charset=%s',
    $db['host'],
    $port,
    $db['name'],
    $db['charset']
);

try {
    $pdo = new PDO($dsn, $db['user'], $db['pass'], [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
} catch (PDOException $e) {
    fwrite(STDERR, "Database connection failed: {$e->getMessage()}\n");
    exit(1);
}

try {
    $count = export_passwd_to_file($pdo, $outputFile);
} catch (RuntimeException $e) {
    fwrite(STDERR, $e->getMessage() . "\n");
    exit(1);
}

echo "Wrote {$count} passwd line(s) to {$outputFile}\n";
