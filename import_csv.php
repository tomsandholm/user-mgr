<?php
// Database configuration
$db_host = '10.0.0.44';
$db_port = '3306';
$db_name = 'users';
$db_user = 'root';
$db_pass = 'theresa1';

$csv_file = 'users.csv';

if (!file_exists($csv_file)) {
    die("Error: CSV file '$csv_file' not found.\n");
}

try {
    $pdo = new PDO("mysql:host=$db_host;port=$db_port;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage() . "\n");
}

if (($handle = fopen($csv_file, 'r')) !== false) {
    // 1. Extract and clean header row
    $headers = fgetcsv($handle, 0, ',');
    if (!$headers) {
        die("Error: The CSV file is empty.\n");
    }
    $headers = array_map('trim', $headers);
    $headerCount = count($headers);

    // 2. Prepare the upsert statement
    $sql = "INSERT INTO users (name, uid, gid, email, shell, home, public_key, access_host) 
            VALUES (:name, :uid, :gid, :email, :shell, :home, :public_key, :access_host)
            ON DUPLICATE KEY UPDATE 
                uid = VALUES(uid), gid = VALUES(gid), email = VALUES(email), 
                shell = VALUES(shell), home = VALUES(home), public_key = VALUES(public_key), 
                access_host = VALUES(access_host)";
                
    $stmt = $pdo->prepare($sql);
    $lineNum = 1; // Track line numbers for easy debugging
    $successCount = 0;

    echo "Starting CSV data import...\n";
    $pdo->beginTransaction();

    try {
        // 3. Process rows with mismatched column validation
        while (($data = fgetcsv($handle, 0, ',')) !== false) {
            $lineNum++;
            
            // Skip purely empty rows
            if ($data === [null] || empty(array_filter($data))) {
                continue;
            }

            $dataCount = count($data);

            // FIX: Check if columns match before calling array_combine
            if ($headerCount !== $dataCount) {
                echo "⚠️ Skipping line $lineNum: Column mismatch. (Headers: $headerCount, Row Data: $dataCount)\n";
                echo "   Row Data snippet: " . implode(', ', array_slice($data, 0, 3)) . "...\n";
                continue; // Skip this bad line and keep importing the rest
            }
            
            // Safe to combine now
            $row = array_combine($headers, $data);
            
            $params = [
                ':name'        => trim($row['name'] ?? ''),
                ':uid'         => (int)($row['uid'] ?? 0),
                ':gid'         => (int)($row['gid'] ?? 0),
                ':email'       => trim($row['email'] ?? ''),
                ':shell'       => !empty($row['shell']) ? trim($row['shell']) : '/bin/bash',
                ':home'        => trim($row['home'] ?? ''),
                ':public_key'  => !empty($row['public-key']) ? trim($row['public-key']) : null,
                ':access_host' => !empty($row['access-host']) ? trim($row['access-host']) : 'localhost'
            ];

            $stmt->execute($params);
            $successCount++;
        }

        $pdo->commit();
        echo "Import complete! Successfully imported $successCount rows.\n";

    } catch (Exception $e) {
        $pdo->rollBack();
        echo "Critical Database Error on line $lineNum: " . $e->getMessage() . "\n";
    }

    fclose($handle);
}

