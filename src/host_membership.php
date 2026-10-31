<?php
declare(strict_types=1);

const DEFAULT_HOST_MEMBERS = ['ansible'];

/** Names that are system groups on Linux, not valid gpasswd member users. */
const RESERVED_NON_USER_MEMBERS = ['sudo', 'wheel'];

function is_valid_member_username(string $name): bool
{
    $name = strtolower(trim($name));

    return $name !== '' && !in_array($name, RESERVED_NON_USER_MEMBERS, true);
}

function normalize_members(string $members): string
{
    $parts = preg_split('/[\s,]+/', trim($members), -1, PREG_SPLIT_NO_EMPTY) ?: [];
    $parts = array_map('strtolower', $parts);
    $ordered = DEFAULT_HOST_MEMBERS;
    foreach ($parts as $p) {
        if (!is_valid_member_username($p)) {
            continue;
        }
        if (!in_array($p, $ordered, true)) {
            $ordered[] = $p;
        }
    }

    return implode(',', $ordered);
}

function normalize_access_host(string $access): string
{
    $access = strtolower(trim($access));
    if ($access === '%' || $access === '') {
        return 'all';
    }

    return $access;
}

function host_machine_group_key(array $host): string
{
    return str_replace('.', '-', strtolower(trim($host['groupname'] ?? '')));
}

function access_host_matches_host(string $normalized_access, array $host): bool
{
    if ($normalized_access === 'none' || $normalized_access === '') {
        return false;
    }
    if ($normalized_access === 'all') {
        return true;
    }
    $fqdn = strtolower(trim($host['fqdn'] ?? ''));
    $groupname = strtolower(trim($host['groupname'] ?? ''));
    $as_group = str_replace('.', '-', $normalized_access);

    return $normalized_access === $fqdn
        || $normalized_access === $groupname
        || $as_group === $groupname
        || $as_group === host_machine_group_key($host);
}

function parse_members_list(string $members): array
{
    return array_values(array_filter(
        array_map('trim', explode(',', $members)),
        fn(string $m) => $m !== ''
    ));
}

function user_should_be_host_member(string $access_host, array $host): bool
{
    $access = normalize_access_host($access_host);

    return access_host_matches_host($access, $host);
}

/** Add or remove a username on each host row to match access_host (idempotent). */
function apply_user_host_membership(PDO $pdo, string $username, string $access_host): void
{
    $username = strtolower(trim($username));
    if ($username === '') {
        return;
    }

    $hosts = $pdo->query('SELECT id, fqdn, groupname, members FROM hosts')->fetchAll();
    $update = $pdo->prepare('UPDATE hosts SET members = ? WHERE id = ?');

    foreach ($hosts as $host) {
        $members = parse_members_list((string)($host['members'] ?? ''));
        $should_have = user_should_be_host_member($access_host, $host);
        $has_user = in_array($username, $members, true);

        if ($should_have && !$has_user) {
            $members[] = $username;
        } elseif (!$should_have && $has_user) {
            $members = array_values(array_filter(
                $members,
                fn(string $m) => $m !== $username
            ));
        } else {
            continue;
        }

        $normalized = normalize_members(implode(',', $members));
        if ($normalized !== (string)$host['members']) {
            $update->execute([$normalized, $host['id']]);
        }
    }
}

function sync_user_host_members(
    PDO $pdo,
    string $username,
    string $old_access_host = '',
    string $new_access_host = '',
    string $old_username = ''
): void {
    $username = trim($username);
    $old_username = trim($old_username) !== '' ? trim($old_username) : $username;
    if ($username === '') {
        return;
    }

    if ($old_username !== '' && $old_username !== $username) {
        remove_user_from_host_members($pdo, $old_username);
    }

    apply_user_host_membership($pdo, $username, $new_access_host);
}

function remove_user_from_host_members(PDO $pdo, string $username): void
{
    $username = strtolower(trim($username));
    if ($username === '') {
        return;
    }

    $hosts = $pdo->query('SELECT id, members FROM hosts')->fetchAll();
    $update = $pdo->prepare('UPDATE hosts SET members = ? WHERE id = ?');

    foreach ($hosts as $host) {
        $members = array_values(array_filter(
            parse_members_list((string)($host['members'] ?? '')),
            fn(string $m) => $m !== $username
        ));
        $normalized = normalize_members(implode(',', $members));
        if ($normalized !== (string)$host['members']) {
            $update->execute([$normalized, $host['id']]);
        }
    }
}

/** Reconcile every user's access_host rule against all host member lists. */
function reconcile_all_user_host_members(PDO $pdo): void
{
    $users = $pdo->query('SELECT name, access_host FROM users')->fetchAll();
    foreach ($users as $user) {
        apply_user_host_membership($pdo, (string)$user['name'], (string)$user['access_host']);
    }
}

/** Rewrite hosts.members to drop legacy non-user names (sudo, wheel) and re-normalize. */
function sanitize_legacy_host_member_names(PDO $pdo): void
{
    $hosts = $pdo->query('SELECT id, members FROM hosts')->fetchAll();
    $update = $pdo->prepare('UPDATE hosts SET members = ? WHERE id = ?');

    foreach ($hosts as $host) {
        $normalized = normalize_members((string)($host['members'] ?? ''));
        if ($normalized !== (string)$host['members']) {
            $update->execute([$normalized, $host['id']]);
        }
    }
}
