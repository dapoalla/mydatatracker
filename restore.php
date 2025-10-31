<?php
require_once 'config.php';

if (!is_admin()) {
    header('HTTP/1.1 403 Forbidden');
    echo 'Access denied. Admins only.';
    exit;
}

$message = '';
$message_type = 'success';

function execute_sql_file(mysqli $link, string $sql): bool {
    // Normalize line endings and remove BOM
    $sql = preg_replace('/^\xEF\xBB\xBF/', '', $sql);
    // Remove comments
    $lines = preg_split('/\R/', $sql);
    $clean = [];
    foreach ($lines as $line) {
        // strip -- comments and # comments
        $line = preg_replace('/\s*--.*$/', '', $line);
        $line = preg_replace('/\s*#.*$/', '', $line);
        $clean[] = $line;
    }
    $sql = implode("\n", $clean);

    // Split by semicolon taking care of quoted strings
    $statements = [];
    $buffer = '';
    $in_string = false;
    $string_char = '';
    $len = strlen($sql);
    for ($i = 0; $i < $len; $i++) {
        $ch = $sql[$i];
        $next = $i + 1 < $len ? $sql[$i + 1] : '';
        if ($in_string) {
            if ($ch === $string_char && $sql[$i - 1] !== '\\') {
                $in_string = false;
            }
            $buffer .= $ch;
            continue;
        }
        if ($ch === '\'' || $ch === '"') {
            $in_string = true;
            $string_char = $ch;
            $buffer .= $ch;
            continue;
        }
        if ($ch === ';') {
            $stmt = trim($buffer);
            if ($stmt !== '') {
                $statements[] = $stmt;
            }
            $buffer = '';
        } else {
            $buffer .= $ch;
        }
    }
    $tail = trim($buffer);
    if ($tail !== '') { $statements[] = $tail; }

    // Execute within a transaction
    mysqli_begin_transaction($link);
    try {
        mysqli_query($link, 'SET FOREIGN_KEY_CHECKS=0');
        foreach ($statements as $stmt) {
            if ($stmt === '') continue;
            $t = ltrim($stmt);
            // Skip environment/transaction directives that can conflict
            if (preg_match('/^(SET\s+SQL_MODE|SET\s+time_zone|START\s+TRANSACTION|COMMIT)\b/i', $t)) {
                continue;
            }
            // Allow MySQL executable comments /*! ... */ to pass through
            mysqli_query($link, $stmt);
        }
        mysqli_query($link, 'SET FOREIGN_KEY_CHECKS=1');
        mysqli_commit($link);
        return true;
    } catch (Throwable $e) {
        mysqli_rollback($link);
        error_log('Restore failed: ' . $e->getMessage());
        return false;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_FILES['sql_file']) || $_FILES['sql_file']['error'] !== UPLOAD_ERR_OK) {
        $message = 'Please select a valid .sql file to upload.';
        $message_type = 'error';
    } else {
        $tmp = $_FILES['sql_file']['tmp_name'];
        $sql = file_get_contents($tmp);
        if ($sql === false) {
            $message = 'Unable to read uploaded file.';
            $message_type = 'error';
        } else {
            $ok = execute_sql_file($link, $sql);
            if ($ok) {
                $message = 'Database restore completed successfully.';
                $message_type = 'success';
            } else {
                $message = 'Database restore failed. Check error logs for details.';
                $message_type = 'error';
            }
        }
    }
}

?>
<!DOCTYPE html>
<html lang="en" class="grey-mode">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Restore Database - My Data Tracker</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <header>
        <h1>Restore Database</h1>
        <div class="theme-switch-wrapper">
            <label class="theme-switch" for="checkbox">
                <input type="checkbox" id="checkbox" />
                <div class="slider round"></div>
            </label>
            <em>Dark Mode</em>
        </div>
    </header>
    <nav>
        <ul>
            <li><a href="index.php">Dashboard</a></li>
            <li><a href="review.php">Review & Reports</a></li>
            <li><a href="add_car.php">Manage Vehicles</a></li>
            <li><a href="manage_meters.php">Manage Meters</a></li>
            <li><a href="backup.php">Download Backup</a></li>
        </ul>
    </nav>
    <div class="container">
        <?php if (!empty($message)): ?>
            <div class="message <?php echo $message_type === 'error' ? 'error' : 'success'; ?>">
                <p><?php echo htmlspecialchars($message); ?></p>
            </div>
        <?php endif; ?>

        <section class="form-section">
            <h2>Upload SQL Backup to Restore</h2>
            <form method="POST" enctype="multipart/form-data">
                <label for="sql_file">Select .sql file</label>
                <input type="file" name="sql_file" id="sql_file" accept=".sql" required>
                <button type="submit" class="button button-primary">Restore</button>
            </form>
            <p class="note">Tip: If upload fails, increase <code>upload_max_filesize</code> and <code>post_max_size</code> in your <code>php.ini</code>.</p>
        </section>
    </div>
    <script>
    // Restore theme preference
    (function(){
        const t = localStorage.getItem('theme');
        if (t === 'dark') document.body.classList.add('dark-mode');
        const cb = document.getElementById('checkbox');
        if (cb) {
            cb.checked = document.body.classList.contains('dark-mode');
            cb.addEventListener('change', function(){
                document.body.classList.toggle('dark-mode');
                localStorage.setItem('theme', document.body.classList.contains('dark-mode') ? 'dark' : 'light');
            });
        }
    })();
    </script>
</body>
</html>