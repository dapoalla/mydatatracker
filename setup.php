<?php
// Standalone installer: do not include config.php to avoid redirects/DB coupling.
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$message = '';
$success = false;

function render_form($message = '') {
    $safeMsg = $message ? '<p style="color:' . (str_contains($message, 'error') ? 'red' : 'green') . '">' . htmlspecialchars($message) . '</p>' : '';
    echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>My Data Tracker - Setup</title><style>body{font-family:system-ui,Segoe UI,Arial;margin:24px;}form{max-width:640px;padding:16px;border:1px solid #ddd;border-radius:8px;background:#fafafa}label{display:block;margin-top:10px}input[type=text],input[type=password]{width:100%;padding:8px;margin-top:4px}button{margin-top:16px;padding:10px 16px}code{background:#eee;padding:2px 4px;border-radius:3px}</style></head><body>';
    echo '<h1>My Data Tracker - Setup</h1>' . $safeMsg;
    echo '<p>Provide your MySQL credentials. Optionally wipe existing tables and initialize a fresh schema.</p>';
    echo '<form method="post" action="setup.php">';
    echo '<label>DB Server <input type="text" name="db_server" value="' . htmlspecialchars($_POST['db_server'] ?? 'localhost') . '" required></label>';
    echo '<label>DB Username <input type="text" name="db_username" value="' . htmlspecialchars($_POST['db_username'] ?? 'root') . '" required></label>';
    echo '<label>DB Password <input type="password" name="db_password" value="' . htmlspecialchars($_POST['db_password'] ?? '') . '"></label>';
    echo '<label>DB Name <input type="text" name="db_name" value="' . htmlspecialchars($_POST['db_name'] ?? 'cyberros_Vehicletrack') . '" required></label>';
    echo '<label><input type="checkbox" name="wipe" value="1" ' . (!empty($_POST['wipe']) ? 'checked' : '') . '> Wipe all existing data (DROP tables)</label>';
    echo '<h3>Admin Account</h3>';
    echo '<p>An admin user will be created if it does not exist.</p>';
    echo '<label>Admin Username <input type="text" name="admin_username" value="' . htmlspecialchars($_POST['admin_username'] ?? 'admin') . '" required></label>';
    echo '<label>Admin Password <input type="text" name="admin_password" value="' . htmlspecialchars($_POST['admin_password'] ?? 'admin123') . '" required></label>';
    echo '<button type="submit">Run Setup</button>';
    echo '</form>';
    echo '<p>After successful setup, you will be redirected to <code>index.php</code>.</p>';
    echo '</body></html>';
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    render_form();
    exit;
}

// Handle POST
$dbServer = trim($_POST['db_server'] ?? 'localhost');
$dbUser   = trim($_POST['db_username'] ?? 'root');
$dbPass   = (string)($_POST['db_password'] ?? '');
$dbName   = trim($_POST['db_name'] ?? 'cyberros_Vehicletrack');
$wipe     = !empty($_POST['wipe']);
$adminU   = trim($_POST['admin_username'] ?? 'admin');
$adminP   = (string)($_POST['admin_password'] ?? 'admin123');

try {
    // Connect to server first (without selecting DB)
    $link = mysqli_connect($dbServer, $dbUser, $dbPass);
    // Create DB if missing
    mysqli_query($link, 'CREATE DATABASE IF NOT EXISTS `' . mysqli_real_escape_string($link, $dbName) . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    mysqli_select_db($link, $dbName);
    mysqli_set_charset($link, 'utf8mb4');

    // Wipe existing tables if requested
    if ($wipe) {
        $tables = [
            'cooking_gas_logs',
            'fuel_logs',
            'electricity_logs',
            'electricity_meters',
            'maintenance_logs',
            'mileage_logs',
            'items',
            'vehicles',
            'users'
        ];
        foreach ($tables as $t) {
            mysqli_query($link, 'DROP TABLE IF EXISTS `' . $t . '`');
        }
    }

    // Create schema (idempotent)
    $schemaSql = [
        // Users
        'CREATE TABLE IF NOT EXISTS `users` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `username` VARCHAR(64) NOT NULL UNIQUE,
            `password_hash` VARCHAR(255) NOT NULL,
            `role` VARCHAR(16) NOT NULL DEFAULT "user",
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',
        // Vehicles
        'CREATE TABLE IF NOT EXISTS `vehicles` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `user_id` INT UNSIGNED NOT NULL,
            `make` VARCHAR(64) NOT NULL,
            `model` VARCHAR(64) NOT NULL,
            `plate_number` VARCHAR(32) NOT NULL UNIQUE,
            `vehicle_type` VARCHAR(32) NULL,
            `year` VARCHAR(16) NULL,
            INDEX (`user_id`),
            CONSTRAINT `fk_vehicles_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',
        // Items
        'CREATE TABLE IF NOT EXISTS `items` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `name` VARCHAR(64) NOT NULL,
            `item_type` VARCHAR(32) NOT NULL,
            `vehicle_id` INT UNSIGNED NOT NULL,
            `user_id` INT UNSIGNED NOT NULL,
            `expiry_date` DATE NULL,
            `last_renewal_date` DATE NULL,
            `last_renewal_cost` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            UNIQUE KEY `uq_items_name_vehicle_user` (`name`, `vehicle_id`, `user_id`),
            INDEX (`vehicle_id`), INDEX (`user_id`),
            CONSTRAINT `fk_items_vehicle` FOREIGN KEY (`vehicle_id`) REFERENCES `vehicles`(`id`) ON DELETE CASCADE,
            CONSTRAINT `fk_items_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',
        // Mileage logs
        'CREATE TABLE IF NOT EXISTS `mileage_logs` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `vehicle_id` INT UNSIGNED NOT NULL,
            `user_id` INT UNSIGNED NOT NULL,
            `mileage_reading` INT UNSIGNED NOT NULL,
            `log_date` DATE NOT NULL,
            `comment` VARCHAR(255) NULL,
            INDEX (`vehicle_id`), INDEX (`user_id`),
            CONSTRAINT `fk_mileage_vehicle` FOREIGN KEY (`vehicle_id`) REFERENCES `vehicles`(`id`) ON DELETE CASCADE,
            CONSTRAINT `fk_mileage_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',
        // Maintenance logs
        'CREATE TABLE IF NOT EXISTS `maintenance_logs` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `vehicle_id` INT UNSIGNED NOT NULL,
            `user_id` INT UNSIGNED NOT NULL,
            `maintenance_date` DATE NOT NULL,
            `work_done` VARCHAR(255) NOT NULL,
            `cost` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            INDEX (`vehicle_id`), INDEX (`user_id`),
            CONSTRAINT `fk_maint_vehicle` FOREIGN KEY (`vehicle_id`) REFERENCES `vehicles`(`id`) ON DELETE CASCADE,
            CONSTRAINT `fk_maint_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',
        // Electricity meters
        'CREATE TABLE IF NOT EXISTS `electricity_meters` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `user_id` INT UNSIGNED NOT NULL,
            `meter_number` VARCHAR(64) NOT NULL,
            `description` VARCHAR(255) NULL,
            INDEX (`user_id`),
            CONSTRAINT `fk_meter_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',
        // Electricity logs
        'CREATE TABLE IF NOT EXISTS `electricity_logs` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `meter_id` INT UNSIGNED NOT NULL,
            `purchase_date` DATE NOT NULL,
            `units_purchased` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            `cost` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            `notes` VARCHAR(255) NULL,
            INDEX (`meter_id`),
            CONSTRAINT `fk_log_meter` FOREIGN KEY (`meter_id`) REFERENCES `electricity_meters`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',
        // Fuel logs (vehicle/user scoped)
        'CREATE TABLE IF NOT EXISTS `fuel_logs` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `vehicle_id` INT UNSIGNED NOT NULL,
            `user_id` INT UNSIGNED NOT NULL,
            `purchase_date` DATE NOT NULL,
            `volume` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            `cost` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            `odometer_reading` INT UNSIGNED NULL,
            INDEX (`vehicle_id`), INDEX (`user_id`),
            CONSTRAINT `fk_fuel_vehicle` FOREIGN KEY (`vehicle_id`) REFERENCES `vehicles`(`id`) ON DELETE CASCADE,
            CONSTRAINT `fk_fuel_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',
        // Cooking gas logs (user scoped)
        'CREATE TABLE IF NOT EXISTS `cooking_gas_logs` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `user_id` INT UNSIGNED NOT NULL,
            `purchase_date` DATE NOT NULL,
            `location` VARCHAR(255) NOT NULL,
            `quantity` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            `cost` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            `notes` VARCHAR(255) NULL,
            INDEX (`user_id`),
            CONSTRAINT `fk_gas_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',
    ];
    foreach ($schemaSql as $sql) { mysqli_query($link, $sql); }

    // --- Migrations for existing installations ---
    // Ensure vehicles table has vehicle_type column even if table existed before this update
    try {
        $colCheck = mysqli_query($link, "SHOW COLUMNS FROM `vehicles` LIKE 'vehicle_type'");
        if ($colCheck && mysqli_num_rows($colCheck) === 0) {
            mysqli_query($link, "ALTER TABLE `vehicles` ADD COLUMN `vehicle_type` VARCHAR(32) NULL AFTER `plate_number`");
        }
        if ($colCheck) { mysqli_free_result($colCheck); }
    } catch (Throwable $e) {
        // Non-fatal: continue setup even if migration fails
        error_log('Setup migration warning (vehicles.vehicle_type): ' . $e->getMessage());
    }

    // Ensure mileage_logs table has comment column
    try {
        $colCheck2 = mysqli_query($link, "SHOW COLUMNS FROM `mileage_logs` LIKE 'comment'");
        if ($colCheck2 && mysqli_num_rows($colCheck2) === 0) {
            mysqli_query($link, "ALTER TABLE `mileage_logs` ADD COLUMN `comment` VARCHAR(255) NULL AFTER `log_date`");
        }
        if ($colCheck2) { mysqli_free_result($colCheck2); }
    } catch (Throwable $e) {
        error_log('Setup migration warning (mileage_logs.comment): ' . $e->getMessage());
    }

    // Create admin user if missing
    $stmt = mysqli_prepare($link, 'SELECT id FROM users WHERE username = ?');
    mysqli_stmt_bind_param($stmt, 's', $adminU);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_store_result($stmt);
    if (mysqli_stmt_num_rows($stmt) === 0) {
        mysqli_stmt_close($stmt);
        $hash = password_hash($adminP, PASSWORD_DEFAULT);
        $stmtIns = mysqli_prepare($link, 'INSERT INTO users (username, password_hash, role) VALUES (?, ?, "admin")');
        mysqli_stmt_bind_param($stmtIns, 'ss', $adminU, $hash);
        mysqli_stmt_execute($stmtIns);
        mysqli_stmt_close($stmtIns);
    } else {
        mysqli_stmt_close($stmt);
    }

    // Write config.local.php with provided credentials
    $configLocal = <<<PHP
<?php
define('DB_SERVER', '{$dbServer}');
define('DB_USERNAME', '{$dbUser}');
define('DB_PASSWORD', '{$dbPass}');
define('DB_NAME', '{$dbName}');
PHP;
    file_put_contents(__DIR__ . '/config.local.php', $configLocal);

    // Create install lock
    file_put_contents(__DIR__ . '/install.lock', 'installed ' . date('c'));

    $success = true;
    $message = 'Setup completed successfully. Redirecting to dashboard...';
} catch (Throwable $e) {
    $message = 'Setup error: ' . $e->getMessage();
}

if ($success) {
    echo '<!DOCTYPE html><meta http-equiv="refresh" content="2;url=index.php">';
    render_form($message);
} else {
    render_form($message);
}
?>