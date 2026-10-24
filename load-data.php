<?php
// Database configuration
$db_host = '10.0.0.44';
$db_port = '3306';
$db_name = 'users';
$db_user = 'root';
$db_pass = 'theresa1';

// Path to your CSV data file
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

// Open the CSV file for reading
if (($handle = fopen($csv_file, 'r')) !== false) {
    // 1. Extract the header row to dynamic map columns
    $headers = fgetcsv($handle, 1000, ',');
    
    if (!$headers) {
        die("Error: The CSV file is empty.\n");
    }

    // Trim whitespace from headers to avoid mapping mismatches
    $headers = array_map('trim', $headers);

    // 2. Prepare the transactional insertion SQL statement
    // Maps CSV dashes (public-key, access-host) to database underscores (public_key, access_host)
    $sql = "INSERT INTO users (name, uid, gid, email, shell, home, public_key, access_host) 
            VALUES (:name, :uid, :gid, :email, :shell, :home, :public_key, :access_host)
            ON DUPLICATE KEY UPDATE 
                uid = VALUES(uid), 
                gid = VALUES(gid), 
                email = VALUES(email), 
                shell = VALUES(shell), 
                home = VALUES(home), 
                public_key = VALUES(public_key), 
                access_host = VALUES(access_host)";
                
    $stmt = $pdo->prepare($sql);

    $rowCount = 0;
    $successCount = 0;

    echo "Starting CSV data import...\n";

    // Begin a database transaction for massive speed optimization
    $pdo->beginTransaction();

    try {
        // 3. Loop through each remaining data row
        while (($data = fgetcsv($handle, 1000, ',')) !== false) {
            $rowCount++;
            
            // Combine headers with row values into an associative array
            $row = array_combine($headers, $data);
            
            // Clean values and handle defaults if empty string is passed
            $params = [
                ':name'        => trim($row['name']),
                ':uid'         => (int)$row['uid'],
                ':gid'         => (int)$row['gid'],
                ':email'       => trim($row['email']),
                ':shell'       => !empty($row['shell']) ? trim($row['shell']) : '/bin/bash',
                ':home'        => trim($row['home']),
                ':public_key'  => !empty($row['public-key']) ? trim($row['public-key']) : null,
                ':access_host' => !empty($row['access-host']) ? trim($row['access-host']) : 'localhost'
            ];

            // Execute single record payload injection
            $stmt->execute($params);
            $successCount++;
        }

        // Commit all inserts to the database at once
        $pdo->commit();
        echo "Import complete! Successfully processed $successCount out of $rowCount rows.\n";

    } catch (Exception $e) {
        // Roll back structural mutations if an error occurs to keep table synchronized
        $pdo->rollBack();
        echo "Critical Error: Import halted. Changes rolled back. Reason: " . $e->getMessage() . "\n";
    }

    fclose($handle);
} else {
    echo "Error: Could not open the file stream for '$csv_file'.\n";
}

