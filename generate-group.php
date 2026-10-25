<?php
declare(strict_types=1);

/**
 * Read hosts from MySQL and write /etc/group-style lines to sample-group.txt.
 *
 * Format: groupname:x:gid:member1,member2,...
 *
 * Usage:
 *   php generate-group.php
 *   php generate-group.php /path/to/output.txt
 *
 * From the host against Docker MariaDB (see docker-compose.yml):
 *   DB_HOST=127.0.0.1 DB_PORT=3307 php generate-group.php
 */

$outputFile = $argv[1] ?? (__DIR__ . '/sample-group.txt');

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

$hosts = $pdo->query('SELECT groupname, gid, members FROM hosts ORDER BY gid ASC')->fetchAll();

$lines = [];
foreach ($hosts as $host) {
    $lines[] = format_group_line($host);
}

$content = implode("\n", $lines);
if ($content !== '') {
    $content .= "\n";
}

if (file_put_contents($outputFile, $content) === false) {
    fwrite(STDERR, "Failed to write {$outputFile}\n");
    exit(1);
}

echo "Wrote " . count($lines) . " group line(s) to {$outputFile}\n";

function format_group_line(array $host): string
{
    $groupname = sanitize_group_field((string)$host['groupname']);
    $gid = (int)$host['gid'];
    $members = format_group_members((string)($host['members'] ?? ''));

    return sprintf('%s:x:%d:%s', $groupname, $gid, $members);
}

function format_group_members(string $members): string
{
    $parts = preg_split('/[\s,]+/', trim($members), -1, PREG_SPLIT_NO_EMPTY) ?: [];
    $clean = [];
    foreach ($parts as $part) {
        $name = sanitize_group_field($part);
        if ($name !== '') {
            $clean[] = $name;
        }
    }

    return implode(',', $clean);
}

/** Colons and newlines would break group field parsing. */
function sanitize_group_field(string $value): string
{
    return str_replace([':', "\r", "\n"], ['', '', ''], trim($value));
}
