<?php
declare(strict_types=1);

require_once __DIR__ . '/sample_file_write.php';

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

function export_passwd_to_file(PDO $pdo, string $outputFile): int
{
    $users = $pdo->query(
        'SELECT name, uid, gid, email, shell, home FROM users ORDER BY uid ASC'
    )->fetchAll();

    $lines = [];
    foreach ($users as $user) {
        $lines[] = format_passwd_line($user);
    }

    $content = implode("\n", $lines);
    if ($content !== '') {
        $content .= "\n";
    }

    write_sample_file($outputFile, $content);

    return count($lines);
}

function default_passwd_output_path(): string
{
    return default_export_dir() . '/sample-passwd.txt';
}
