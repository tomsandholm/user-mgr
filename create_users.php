<?php
// Database configuration
$db_host = '127.0.0.1';
$db_name = 'users';
$db_user = 'sysadm';
$db_pass = 'theresa1';

try {
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage() . "\n");
}

// Fetch users from the database
$stmt = $pdo->query("SELECT * FROM users");
$users = $stmt->fetchAll();

if (empty($users)) {
    echo "No users found in the database.\n";
    exit;
}

foreach ($users as $user) {
    $name  = $user['name'];
    $uid   = (int)$user['uid'];
    $gid   = (int)$user['gid'];
    $shell = $user['shell'];
    $home  = $user['home'];
    $key   = $user['public_key'];

    echo "=========================================\n";
    echo "Processing User: $name\n";
    echo "=========================================\n";

    // 1. Check if group exists, create if missing
    if (!posix_getgrgid($gid)) {
        echo "Creating system group for GID $gid...\n";
        exec("sudo groupadd -g $gid $name 2>&1", $g_output, $g_status);
    }

    // 2. Check if user exists system-wide
    if (posix_getpwuid($uid) || posix_getpwnam($name)) {
        echo "Warning: User '$name' or UID $uid already exists on this system. Skipping creation.\n";
    } else {
        // Construct the useradd command
        // -m forces home creation if missing, -u specifies UID, -g specifies primary GID, -s specifies shell
        $cmd = sprintf(
            "sudo useradd -m -u %d -g %d -s %s -d %s %s 2>&1",
            $uid, $gid, escapeshellarg($shell), escapeshellarg($home), escapeshellarg($name)
        );

        exec($cmd, $output, $status);

        if ($status === 0) {
            echo "System user '$name' created successfully.\n";

            // 3. Inject SSH Public Key if provided
            if (!empty($key)) {
                $ssh_dir = $home . '/.ssh';
                if (!is_dir($ssh_dir)) {
                    mkdir($ssh_dir, 0700, true);
                }
                
                $auth_keys = $ssh_dir . '/authorized_keys';
                file_put_contents($auth_keys, $key . "\n", FILE_APPEND);
                chmod($auth_keys, 0600);

                // Fix file ownership away from root to the new user
                exec("sudo chown -R $uid:$gid " . escapeshellarg($ssh_dir));
                echo "SSH public key installed.\n";
            }
        } else {
            echo "Failed to create user '$name'. Error: " . implode("\n", $output) . "\n";
            continue;
        }
    }

    // 4. Generate the exact structural string layouts for the user records
    echo "\n[Generated Configuration Entries]\n";
    echo "📄 /etc/passwd entry:\n";
    printf("%s:x:%d:%d:%s:%s:%s\n", $name, $uid, $gid, $user['email'], $home, $shell);

    echo "\n👥 /etc/group entry:\n";
    printf("%s:x:%d:\n", $name, $gid);
    echo "=========================================\n\n";
}

