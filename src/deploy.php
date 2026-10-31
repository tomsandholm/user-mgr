<?php
declare(strict_types=1);

require __DIR__ . '/auth.php';
require_once __DIR__ . '/host_membership.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

$token = $_POST['csrf_token'] ?? '';
if (!verify_csrf($token)) {
    http_response_code(403);
    die('Invalid or missing CSRF token.');
}

if (empty($_POST['deploy'])) {
    header('Location: index.php');
    exit;
}

@set_time_limit(900);
@ini_set('max_execution_time', '900');

try {
    $pdo = db_connect($config);
    reconcile_all_user_host_members($pdo);
    sanitize_legacy_host_member_names($pdo);
} catch (\PDOException $e) {
    $_SESSION['deploy_output'] = 'Database reconcile before deploy failed: ' . $e->getMessage();
    $_SESSION['deploy_exit_code'] = 1;
    header('Location: index.php?deploy_result=1');
    exit;
}

$cmd = 'sudo -n /ansible/run-sync-from-db.sh 2>&1';
$output = [];
$exitCode = 0;
exec($cmd, $output, $exitCode);

$header = [
    '=== Deploy: ansible/playbooks/sync-accounts-from-db.yml ===',
    'Options: -vvv --diff, stdout_callback=yaml, show_task_path=on',
    '',
];

$_SESSION['deploy_output'] = implode("\n", array_merge($header, $output));
$_SESSION['deploy_exit_code'] = $exitCode;
$_SESSION['deploy_at'] = gmdate('c');

header('Location: index.php?deploy_result=1');
exit;
