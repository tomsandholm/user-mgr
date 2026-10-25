<?php
declare(strict_types=1);

require_once __DIR__ . '/sample_file_write.php';

function format_host_group_line(array $host): string
{
    $groupname = sanitize_group_field((string)$host['groupname']);
    $gid = (int)$host['gid'];
    $members = format_group_members((string)($host['members'] ?? ''));

    return sprintf('%s:x:%d:%s', $groupname, $gid, $members);
}

/** Primary group for a user account (matches create_users.php). */
function format_user_group_line(array $user): string
{
    $name = sanitize_group_field((string)$user['name']);
    $gid = (int)$user['gid'];

    return sprintf('%s:x:%d:', $name, $gid);
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

function export_group_to_file(PDO $pdo, string $outputFile): int
{
    $users = $pdo->query('SELECT name, gid FROM users ORDER BY gid ASC')->fetchAll();
    $hosts = $pdo->query('SELECT groupname, gid, members FROM hosts ORDER BY gid ASC')->fetchAll();

    $lines = [];
    foreach ($hosts as $host) {
        $lines[] = format_host_group_line($host);
    }
    foreach ($users as $user) {
        $lines[] = format_user_group_line($user);
    }

    $content = implode("\n", $lines);
    if ($content !== '') {
        $content .= "\n";
    }

    write_sample_file($outputFile, $content);

    return count($lines);
}

function default_group_output_path(): string
{
    return default_export_dir() . '/sample-group.txt';
}
