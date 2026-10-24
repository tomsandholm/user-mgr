<?php
require __DIR__ . '/auth.php';
require_login();

$message = '';
$error = '';

function split_sql_statements(string $sql): array
{
    $statements = [];
    $buffer = '';
    $inSingle = false;
    $inDouble = false;
    $inBacktick = false;
    $escaped = false;
    $len = strlen($sql);

    for ($i = 0; $i < $len; $i++) {
        $ch = $sql[$i];
        $next = $i + 1 < $len ? $sql[$i + 1] : '';

        // Skip line comments outside strings
        if (!$inSingle && !$inDouble && !$inBacktick && $ch === '-' && $next === '-') {
            while ($i < $len && $sql[$i] !== "\n") {
                $i++;
            }
            continue;
        }

        // Skip /* */ comments / conditional comments markers we still want to keep as statements
        // Handle plain /* ... */ by skipping; keep /*! ... */ content
        if (!$inSingle && !$inDouble && !$inBacktick && $ch === '/' && $next === '*') {
            if ($i + 2 < $len && $sql[$i + 2] === '!') {
                // MariaDB conditional comment — keep inner SQL, strip wrappers later
                $buffer .= $ch;
                continue;
            }
            $i += 2;
            while ($i + 1 < $len && !($sql[$i] === '*' && $sql[$i + 1] === '/')) {
                $i++;
            }
            $i++; // skip closing /
            continue;
        }

        if ($escaped) {
            $buffer .= $ch;
            $escaped = false;
            continue;
        }

        if (($inSingle || $inDouble) && $ch === '\\') {
            $buffer .= $ch;
            $escaped = true;
            continue;
        }

        if ($ch === "'" && !$inDouble && !$inBacktick) {
            $inSingle = !$inSingle;
            $buffer .= $ch;
            continue;
        }
        if ($ch === '"' && !$inSingle && !$inBacktick) {
            $inDouble = !$inDouble;
            $buffer .= $ch;
            continue;
        }
        if ($ch === '`' && !$inSingle && !$inDouble) {
            $inBacktick = !$inBacktick;
            $buffer .= $ch;
            continue;
        }

        if ($ch === ';' && !$inSingle && !$inDouble && !$inBacktick) {
            $stmt = trim($buffer);
            if ($stmt !== '') {
                $statements[] = $stmt;
            }
            $buffer = '';
            continue;
        }

        $buffer .= $ch;
    }

    $stmt = trim($buffer);
    if ($stmt !== '') {
        $statements[] = $stmt;
    }

    return $statements;
}

function normalize_statement(string $stmt): string
{
    // Unwrap /*!NNNNN ... */ to inner SQL
    if (preg_match('/^\/\*![0-9]+\s*(.*?)\s*\*\/$/s', $stmt, $m)) {
        return trim($m[1]);
    }
    return $stmt;
}

function statement_allowed(string $stmt): bool
{
    $stmt = normalize_statement($stmt);
    if ($stmt === '') {
        return false;
    }

    $upper = strtoupper(ltrim($stmt));

    // Session / transaction controls from our dumps
    if (preg_match('/^(SET|START\s+TRANSACTION|COMMIT|ROLLBACK|LOCK\s+TABLES|UNLOCK\s+TABLES)\b/', $upper)) {
        return true;
    }

    // Only touch users / hosts
    if (preg_match('/^(DROP\s+TABLE(\s+IF\s+EXISTS)?|CREATE\s+TABLE(\s+IF\s+EXISTS)?|INSERT\s+INTO|ALTER\s+TABLE|TRUNCATE\s+TABLE)\b/i', $stmt)) {
        return (bool)preg_match('/\b(`?users`?|`?hosts`?)\b/i', $stmt);
    }

    return false;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo = db_connect($config);

        if (!isset($_FILES['dump_file']) || $_FILES['dump_file']['error'] !== UPLOAD_ERR_OK) {
            throw new RuntimeException('Please choose a valid .sql dump file to upload.');
        }

        $file = $_FILES['dump_file'];
        $name = $file['name'] ?? '';
        if (!preg_match('/\.sql$/i', $name)) {
            throw new RuntimeException('Only .sql files are accepted.');
        }
        if (($file['size'] ?? 0) > 10 * 1024 * 1024) {
            throw new RuntimeException('Dump file is too large (max 10 MB).');
        }

        $sql = file_get_contents($file['tmp_name']);
        if ($sql === false || trim($sql) === '') {
            throw new RuntimeException('Uploaded file is empty or unreadable.');
        }

        $statements = split_sql_statements($sql);
        $executed = 0;
        $skipped = 0;

        // No wrapping transaction: CREATE/DROP TABLE cause implicit commits in MariaDB/MySQL.
        foreach ($statements as $raw) {
            $stmt = normalize_statement($raw);
            if ($stmt === '' || str_starts_with(ltrim($stmt), '--')) {
                continue;
            }
            if (!statement_allowed($stmt)) {
                $skipped++;
                continue;
            }

            $upper = strtoupper(ltrim($stmt));
            if (preg_match('/^(LOCK\s+TABLES|UNLOCK\s+TABLES|START\s+TRANSACTION|COMMIT|ROLLBACK)\b/', $upper)) {
                $skipped++;
                continue;
            }

            $pdo->exec($stmt);
            $executed++;
        }

        $message = "Import complete: executed {$executed} statement(s)"
            . ($skipped ? ", skipped {$skipped}" : '')
            . '.';
    } catch (\Throwable $e) {
        $error = $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Import Dump — Server Account Manager</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<div class="container" style="max-width: 560px; margin-top: 8vh;">
    <div class="card shadow-sm">
        <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
            <h5 class="mb-0">Import SQL dump</h5>
            <a href="index.php" class="btn btn-sm btn-outline-light">Back</a>
        </div>
        <div class="card-body">
            <p class="text-muted small">
                Upload a dump created by <strong>Dump users &amp; hosts</strong>.
                Only statements affecting the <code>users</code> and <code>hosts</code> tables are applied.
            </p>

            <?php if ($message): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
                <a href="index.php" class="btn btn-success">Return to panel</a>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <?php if (!$message): ?>
                <form method="POST" action="import.php" enctype="multipart/form-data">
                    <div class="mb-3">
                        <label class="form-label fw-bold">SQL dump file</label>
                        <input type="file" name="dump_file" class="form-control" accept=".sql,text/sql,application/sql" required>
                    </div>
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary"
                                onclick="return confirm('This will replace users/hosts data from the dump. Continue?');">
                            Import dump
                        </button>
                        <a href="index.php" class="btn btn-secondary">Cancel</a>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </div>
</div>

</body>
</html>
