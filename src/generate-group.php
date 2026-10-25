<?php
declare(strict_types=1);

require __DIR__ . '/group_export.php';

if (PHP_SAPI === 'cli') {
    $outputFile = $argv[1] ?? default_group_output_path();

    $config = require __DIR__ . '/config.php';
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
        $count = export_group_to_file($pdo, $outputFile);
    } catch (RuntimeException $e) {
        fwrite(STDERR, $e->getMessage() . "\n");
        exit(1);
    }

    echo "Wrote {$count} group line(s) to {$outputFile}\n";
    exit(0);
}

require __DIR__ . '/auth.php';
require_login();

try {
    $pdo = db_connect($config);
} catch (\PDOException $e) {
    http_response_code(500);
    die('Database connection failed: ' . htmlspecialchars($e->getMessage()));
}

$outputFile = default_group_output_path();

try {
    $count = export_group_to_file($pdo, $outputFile);
} catch (RuntimeException $e) {
    http_response_code(500);
    die(htmlspecialchars($e->getMessage()));
}

$msg = sprintf('Wrote %d group line(s) to exports/sample-group.txt', $count);
header('Location: index.php?msg=' . rawurlencode($msg));
exit;
