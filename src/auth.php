<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$config = require __DIR__ . '/config.php';

function db_connect(array $config): PDO
{
    $db = $config['db'];
    $dsn = "mysql:host={$db['host']};dbname={$db['name']};charset={$db['charset']}";
    $pdo = new PDO($dsn, $db['user'], $db['pass'], [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
    ensure_schema($pdo);
    return $pdo;
}

function ensure_schema(PDO $pdo): void
{
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS hosts (
            id INT AUTO_INCREMENT PRIMARY KEY,
            fqdn VARCHAR(255) NOT NULL UNIQUE,
            groupname VARCHAR(255) NOT NULL UNIQUE,
            gid INT NOT NULL UNIQUE,
            members TEXT NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
    );
}

function is_logged_in(): bool
{
    return !empty($_SESSION['authenticated']);
}

function require_login(): void
{
    if (!is_logged_in()) {
        header('Location: login.php');
        exit;
    }
}

function attempt_login(string $username, string $password, array $config): bool
{
    $admin = $config['admin'];
    if (!hash_equals($admin['user'], $username)) {
        return false;
    }
    if (!hash_equals($admin['pass'], $password)) {
        return false;
    }
    session_regenerate_id(true);
    $_SESSION['authenticated'] = true;
    $_SESSION['username'] = $username;
    return true;
}

function logout(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
}
