<?php
declare(strict_types=1);

/**
 * Read users from MySQL and write /etc/passwd-style lines to sample-passwd.txt.
 *
 * Usage:
 *   php generate-passwd.php
 *   php generate-passwd.php /path/to/output.txt
 *
 * From the host against Docker MariaDB (see docker-compose.yml):
 *   DB_HOST=127.0.0.1 DB_PORT=3307 php generate-passwd.php
 */

$outputFile = $argv[1] ?? (__DIR__ . '/sample-passwd.txt');

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

$users = $pdo->query('SELECT name, uid, gid, email, shell, home FROM users ORDER BY uid ASC')->fetchAll();

$lines = [];
foreach ($users as $user) {
    $lines[] = format_passwd_line($user);
}

$content = implode("\n", $lines);
if ($content !== '') {
    $content .= "\n";
}

if (file_put_contents($outputFile, $content) === false) {
    fwrite(STDERR, "Failed to write {$outputFile}\n");
    exit(1);
}

echo "Wrote " . count($lines) . " passwd line(s) to {$outputFile}\n";

function format_passwd_line(array $user): string
{
    $name = (string)$user['name'];
    $uid = (int)$user['uid'];
    $gid = (int)$user['gid'];
    $gecos = sanitize_passwd_field((string)$user['email']);
    $home = (string)$user['home'];
    $shell = trim((string)($user['shell'] ?? ''));
    if ($shell === '') {
        $shell = '/bin/bash';
    }

    return sprintf('%s:x:%d:%d:%s:%s:%s', $name, $uid, $gid, $gecos, $home, $shell);
}

/** Colons and newlines would break passwd field parsing. */
function sanitize_passwd_field(string $value): string
{
    return str_replace([':', "\r", "\n"], [' ', '', ''], $value);
}
