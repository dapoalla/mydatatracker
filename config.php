<?php
// --- Database Configuration ---
// Prefer environment variables; fall back to production defaults.
// For local XAMPP, we auto-switch to user 'root' with empty password.
// Change DB_NAME if your local database differs.

$current_page = basename($_SERVER['PHP_SELF'] ?? '');
// If not installed yet, redirect all pages (except setup.php) to setup
if (!file_exists(__DIR__ . '/install.lock') && $current_page !== 'setup.php') {
    header('Location: setup.php');
    exit;
}

$envDbServer   = getenv('DB_SERVER') ?: 'localhost';
$envDbUser     = getenv('DB_USERNAME') ?: 'cyberros_aiuser';
$envDbPassword = getenv('DB_PASSWORD') ?: 'Admin4gpt*';
$envDbName     = getenv('DB_NAME') ?: 'cyberros_Vehicletrack';

$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$isLocal = stripos(PHP_OS, 'WIN') !== false || preg_match('/localhost|127\.0\.0\.1/i', $host);
if ($isLocal) {
    // XAMPP typical defaults
    $envDbUser = getenv('DB_USERNAME') ?: 'root';
    $envDbPassword = getenv('DB_PASSWORD') ?: '';
    // Keep DB name consistent unless overridden via env
}

// If setup has written local config, prefer it
if (file_exists(__DIR__ . '/config.local.php')) {
    require_once __DIR__ . '/config.local.php';
} else {
    define('DB_SERVER', $envDbServer);
    define('DB_USERNAME', $envDbUser);
    define('DB_PASSWORD', $envDbPassword);
    define('DB_NAME', $envDbName);
}

// --- Establish Database Connection ---
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
try {
    $link = mysqli_connect(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);
    mysqli_set_charset($link, 'utf8mb4');
} catch (mysqli_sql_exception $e) {
    error_log('Database connection failed: ' . $e->getMessage());
    die('Database connection error. Please check configuration.');
}

// --- Session and Authentication Management ---
// Start the session at the very beginning of the script
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Define public pages that don't require authentication
$public_pages = ['login.php', 'register.php'];

// Get the current page name
$current_page = basename($_SERVER['PHP_SELF']);

// Redirect to login page if not authenticated and not on a public page
// Allow access to register.php only if it's the register page itself and not logged in
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    if (!in_array($current_page, $public_pages)) {
        header("location: login.php");
        exit;
    }
}

/**
 * Fetches a single row from a query result.
 * @param mysqli_result $result The result set from mysqli_query.
 * @return array|null Associative array of the row, or null if no rows.
 */
function fetch_single_row($result) {
    if ($result && mysqli_num_rows($result) > 0) {
        return mysqli_fetch_assoc($result);
    }
    return null;
}

/**
 * Fetches all rows from a query result.
 * @param mysqli_result $result The result set from mysqli_query.
 * @return array Array of associative arrays, or empty array if no rows.
 */
function fetch_all_rows($result) {
    $rows = [];
    if ($result && mysqli_num_rows($result) > 0) {
        while ($row = mysqli_fetch_assoc($result)) {
            $rows[] = $row;
        }
    }
    return $rows;
}

/**
 * Checks if an item's expiry date is within the current month.
 * @param string $expiry_date_str The expiry date as a string (YYYY-MM-DD).
 * @return bool True if expiring this month, false otherwise.
 */
function is_expiring_this_month($expiry_date_str) {
    if (empty($expiry_date_str)) {
        return false;
    }
    try {
        $expiry_date = new DateTime($expiry_date_str);
        $current_date = new DateTime();
        // Check if the year and month are the same AND the expiry date is in the future or today
        return ($expiry_date->format('Y-m') === $current_date->format('Y-m')) && ($expiry_date >= $current_date->setTime(0,0,0));
    } catch (Exception $e) {
        error_log("Error parsing date in is_expiring_this_month: " . $e->getMessage());
        return false;
    }
}

/**
 * Gets the current logged-in user's ID from the session.
 * @return int|null The user ID or null if not logged in.
 */
function get_current_user_id() {
    return isset($_SESSION['id']) ? intval($_SESSION['id']) : null;
}

/**
 * Checks if the current logged-in user is the 'admin' account.
 * @return bool True if the user is 'admin', false otherwise.
 */
function is_admin() {
    return isset($_SESSION['username']) && $_SESSION['username'] === 'admin';
}

/**
 * Checks if the current logged-in user is the 'demoaccount'.
 * @return bool True if the user is 'demoaccount', false otherwise.
 */
function is_demo_account() {
    return isset($_SESSION['username']) && $_SESSION['username'] === 'demoaccount';
}


/**
 * Initializes essential vehicle items (Car Insurance, Road Worthiness, Vehicle Licence, Driver's License, Car Ownership Certificate)
 * for a specific vehicle if they don't already exist for that vehicle.
 * @param mysqli $db_link The database connection link.
 * @param int $vehicle_id The ID of the vehicle to initialize items for.
 * @param int $user_id The ID of the user owning the vehicle.
 */
function initialize_vehicle_items($db_link, $vehicle_id, $user_id) {
    // Validate parent rows exist to avoid FK violations
    try {
        // Check vehicle exists for this user
        $veh_sql = "SELECT id FROM vehicles WHERE id = ? AND user_id = ? LIMIT 1";
        if ($stmt_v = mysqli_prepare($db_link, $veh_sql)) {
            mysqli_stmt_bind_param($stmt_v, "ii", $vehicle_id, $user_id);
            mysqli_stmt_execute($stmt_v);
            mysqli_stmt_store_result($stmt_v);
            if (mysqli_stmt_num_rows($stmt_v) === 0) {
                mysqli_stmt_close($stmt_v);
                error_log("initialize_vehicle_items: vehicle {$vehicle_id} for user {$user_id} not found; skipping defaults.");
                return; // Parent vehicle missing; skip initialization
            }
            mysqli_stmt_close($stmt_v);
        }

        // Check user exists
        $usr_sql = "SELECT id FROM users WHERE id = ? LIMIT 1";
        if ($stmt_u = mysqli_prepare($db_link, $usr_sql)) {
            mysqli_stmt_bind_param($stmt_u, "i", $user_id);
            mysqli_stmt_execute($stmt_u);
            mysqli_stmt_store_result($stmt_u);
            if (mysqli_stmt_num_rows($stmt_u) === 0) {
                mysqli_stmt_close($stmt_u);
                error_log("initialize_vehicle_items: user {$user_id} not found; skipping defaults.");
                return; // Parent user missing; skip initialization
            }
            mysqli_stmt_close($stmt_u);
        }
    } catch (Throwable $e) {
        error_log('initialize_vehicle_items precheck error: ' . $e->getMessage());
        // Continue; insertion will still fail if parents truly missing
    }

    $item_types = [
        "Car Insurance" => "insurance",
        "Road Worthiness" => "roadworthiness",
        "Vehicle Licence" => "license",
        "Driver's License" => "driver_license", // New item type
        "Car Ownership Certificate" => "ownership_certificate" // New item type
    ];

    foreach ($item_types as $name => $type) {
        $check_sql = "SELECT id FROM items WHERE name = ? AND vehicle_id = ? AND user_id = ?";
        if ($stmt_check = mysqli_prepare($db_link, $check_sql)) {
            mysqli_stmt_bind_param($stmt_check, "sii", $name, $vehicle_id, $user_id);
            mysqli_stmt_execute($stmt_check);
            mysqli_stmt_store_result($stmt_check);
            if (mysqli_stmt_num_rows($stmt_check) == 0) {
                // Item does not exist for this vehicle and user, so insert it
                $insert_sql = "INSERT INTO items (name, item_type, vehicle_id, user_id, expiry_date, last_renewal_date, last_renewal_cost) VALUES (?, ?, ?, ?, NULL, NULL, 0.00)";
                if ($stmt_insert = mysqli_prepare($db_link, $insert_sql)) {
                    mysqli_stmt_bind_param($stmt_insert, "ssii", $name, $type, $vehicle_id, $user_id);
                    mysqli_stmt_execute($stmt_insert);
                    mysqli_stmt_close($stmt_insert);
                } else {
                    error_log("Error preparing insert statement for items: " . mysqli_error($db_link));
                }
            }
            mysqli_stmt_close($stmt_check);
        } else {
            error_log("Error preparing check statement for items: " . mysqli_error($db_link));
        }
    }
}
?>
