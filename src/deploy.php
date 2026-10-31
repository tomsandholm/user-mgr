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

function deploy_send_busy_page(): void
{
    header('Content-Type: text/html; charset=UTF-8');
    header('X-Accel-Buffering: no');
    echo <<<'HTML'
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Deploy in progress…</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light d-flex align-items-center justify-content-center min-vh-100">
    <div class="card shadow-lg border-0" style="min-width: 18rem; max-width: 90vw;">
        <div class="card-body text-center px-4 py-4">
            <div class="spinner-border text-warning mb-3" style="width: 3rem; height: 3rem;" role="status">
                <span class="visually-hidden">Loading…</span>
            </div>
            <p class="mb-1 fw-semibold">Running Ansible sync…</p>
            <p class="small text-muted mb-0">Updating groups and users from the database. This may take several minutes.</p>
        </div>
    </div>
</body>
</html>
HTML;
    while (ob_get_level() > 0) {
        ob_end_flush();
    }
    flush();
}

function deploy_finish_redirect(): never
{
    $url = 'index.php?deploy_result=1#deploy-log-panel';
    echo '<script>window.location.replace(' . json_encode($url) . ');</script>';
    echo '<noscript><meta http-equiv="refresh" content="0;url='
        . htmlspecialchars($url, ENT_QUOTES, 'UTF-8')
        . '"></noscript>';
    exit;
}

deploy_send_busy_page();

try {
    $pdo = db_connect($config);
    reconcile_all_user_host_members($pdo);
    sanitize_legacy_host_member_names($pdo);
} catch (\PDOException $e) {
    $_SESSION['deploy_output'] = 'Database reconcile before deploy failed: ' . $e->getMessage();
    $_SESSION['deploy_exit_code'] = 1;
    deploy_finish_redirect();
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

deploy_finish_redirect();
