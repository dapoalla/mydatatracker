<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start(); // Start session to store messages
require_once 'config.php'; // Include database configuration and helper functions

// --- Initialize variables ---
$vehicles = [];
$meters = []; // Added for electricity logging
$cooking_gas_logs = []; // New for cooking gas logs
$selected_vehicle_id = isset($_SESSION['selected_vehicle_id']) ? intval($_SESSION['selected_vehicle_id']) : null;
$selected_vehicle_name = isset($_SESSION['selected_vehicle_name']) ? $_SESSION['selected_vehicle_name'] : 'No Vehicle Selected';

$items = [];
$current_mileage = null;
$last_maintenance = null;
$latest_electricity_log = null; // New for electricity

$message = '';

// Get current user's ID and roles
$current_user_id = get_current_user_id();
$is_admin = is_admin();
$is_demo = is_demo_account();

// Handle session messages
if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    unset($_SESSION['message']); // Clear the message after displaying
}

// Function to calculate days remaining for document summary
function get_days_remaining_for_display($expiry_date_str) {
    if (empty($expiry_date_str)) {
        return 'N/A';
    }
    try {
        $expiry_date = new DateTime($expiry_date_str);
        $current_date = new DateTime();
        $interval = $current_date->diff($expiry_date);
        $days = $interval->days;

        if ($interval->invert) { // Date is in the past
            return 'Expired (' . $days . ' days ago)';
        } else { // Date is in the future or today
            return $days . ' days to go';
        }
    } catch (Exception $e) {
        error_log("Error calculating days remaining for display: " . $e->getMessage());
        return 'Error';
    }
}


// --- Dynamic WHERE clause for user-specific data ---
$user_filter_sql = "";
$user_filter_params = [];
$user_filter_types = "";

if (!$is_admin) { // If not admin, filter by user_id
    $user_filter_sql = " WHERE user_id = ?";
    $user_filter_params[] = $current_user_id;
    $user_filter_types = "i";
    // If it's the demo account, also show demo data (assuming demo data has its own user_id)
    // This logic might need adjustment if demo data is truly global but only visible to demoaccount.
    // For simplicity, demoaccount will just see its own data like any other user.
}


// Fetch all vehicles for the dropdown (filtered by user)
$sql_all_vehicles = "SELECT id, make, model, plate_number FROM vehicles" . $user_filter_sql . " ORDER BY make, model ASC";
if ($stmt_all_vehicles = mysqli_prepare($link, $sql_all_vehicles)) {
    if (!$is_admin) {
        mysqli_stmt_bind_param($stmt_all_vehicles, $user_filter_types, ...$user_filter_params);
    }
    mysqli_stmt_execute($stmt_all_vehicles);
    $result_all_vehicles = mysqli_stmt_get_result($stmt_all_vehicles);
    $vehicles = fetch_all_rows($result_all_vehicles);
    mysqli_stmt_close($stmt_all_vehicles);

    // If no vehicle is selected, and there are vehicles available, select the first one by default
    if (!$selected_vehicle_id && !empty($vehicles)) {
        $_SESSION['selected_vehicle_id'] = $vehicles[0]['id'];
        $_SESSION['selected_vehicle_name'] = $vehicles[0]['make'] . ' ' . $vehicles[0]['model'] . ' (' . $vehicles[0]['plate_number'] . ')';
        $selected_vehicle_id = $_SESSION['selected_vehicle_id'];
        $selected_vehicle_name = $_SESSION['selected_vehicle_name'];
    }
} else {
    $message .= "<p class='error'>Error fetching vehicles for selection: " . mysqli_error($link) . "</p>";
}

// Fetch all electricity meters for the dropdown in the log electricity modal (filtered by user)
$sql_all_meters = "SELECT em.id, em.meter_number, em.description
                   FROM electricity_meters em" . $user_filter_sql . " ORDER BY em.meter_number ASC";
if ($stmt_all_meters = mysqli_prepare($link, $sql_all_meters)) {
    if (!$is_admin) {
        mysqli_stmt_bind_param($stmt_all_meters, $user_filter_types, ...$user_filter_params);
    }
    mysqli_stmt_execute($stmt_all_meters);
    $result_all_meters = mysqli_stmt_get_result($stmt_all_meters);
    $meters = fetch_all_rows($result_all_meters);
    mysqli_stmt_close($stmt_all_meters);
} else {
    $message .= "<p class='error'>Error fetching electricity meters for logging: " . mysqli_error($link) . "</p>";
}


// --- Fetch data for Dashboard (only if a vehicle is selected AND current user is not admin or demo) ---
// Or if admin, show all data, if demo, show demo data.
// The user_filter_sql already handles this.
if ($selected_vehicle_id && $current_user_id) {
    // 1. Fetch Vehicle Items (Insurance, Road Worthiness, etc.) for the selected vehicle and user
    $sql_items = "SELECT id, name, item_type, expiry_date, last_renewal_date, last_renewal_cost FROM items WHERE vehicle_id = ? AND user_id = ? ORDER BY name ASC";
    if ($stmt = mysqli_prepare($link, $sql_items)) {
        mysqli_stmt_bind_param($stmt, "ii", $selected_vehicle_id, $current_user_id);
        mysqli_stmt_execute($stmt);
        $result_items = mysqli_stmt_get_result($stmt);
        $items = fetch_all_rows($result_items);
        mysqli_stmt_close($stmt);
    } else {
        $message .= "<p class='error'>Error preparing statement for items: " . mysqli_error($link) . "</p>";
    }

    // Ensure items table is populated if it's empty for the selected vehicle (basic setup)
    if (empty($items) && $selected_vehicle_id) {
        initialize_vehicle_items($link, $selected_vehicle_id, $current_user_id); // This function is in config.php
        // Re-fetch items after initialization
        if ($stmt = mysqli_prepare($link, $sql_items)) {
            mysqli_stmt_bind_param($stmt, "ii", $selected_vehicle_id, $current_user_id);
            mysqli_stmt_execute($stmt);
            $result_items_retry = mysqli_stmt_get_result($stmt);
            $items = fetch_all_rows($result_items_retry);
            mysqli_stmt_close($stmt);
        }
    }


    // 2. Fetch Current Mileage (latest entry) for the selected vehicle and user
    $sql_mileage = "SELECT mileage_reading, log_date FROM mileage_logs WHERE vehicle_id = ? AND user_id = ? ORDER BY log_date DESC, id DESC LIMIT 1";
    if ($stmt = mysqli_prepare($link, $sql_mileage)) {
        mysqli_stmt_bind_param($stmt, "ii", $selected_vehicle_id, $current_user_id);
        mysqli_stmt_execute($stmt);
        $result_mileage = mysqli_stmt_get_result($stmt);
        $current_mileage = fetch_single_row($result_mileage);
        mysqli_stmt_close($stmt);
    } else {
        $message .= "<p class='error'>Error preparing statement for mileage: " . mysqli_error($link) . "</p>";
    }

    // 3. Fetch Last Maintenance for the selected vehicle and user
    $sql_maintenance = "SELECT maintenance_date, work_done, cost FROM maintenance_logs WHERE vehicle_id = ? AND user_id = ? ORDER BY maintenance_date DESC, id DESC LIMIT 1";
    if ($stmt = mysqli_prepare($link, $sql_maintenance)) {
        mysqli_stmt_bind_param($stmt, "ii", $selected_vehicle_id, $current_user_id);
        mysqli_stmt_execute($stmt);
        $result_maintenance = mysqli_stmt_get_result($stmt);
        $last_maintenance = fetch_single_row($result_maintenance);
        mysqli_stmt_close($stmt);
    } else {
        $message .= "<p class='error'>Error preparing statement for maintenance: " . mysqli_error($link) . "</p>";
    }

    // 4. Fetch Latest Electricity Log for meters associated with the selected user (not vehicle specific)
    // Removed JOIN to vehicles and WHERE em.vehicle_id = ?
    $sql_electricity = "SELECT el.purchase_date, el.units_purchased, el.cost, el.notes, em.meter_number, em.description
                        FROM electricity_logs el
                        JOIN electricity_meters em ON el.meter_id = em.id
                        WHERE em.user_id = ?
                        ORDER BY el.purchase_date DESC, el.id DESC LIMIT 1";
    if ($stmt = mysqli_prepare($link, $sql_electricity)) {
        mysqli_stmt_bind_param($stmt, "i", $current_user_id);
        mysqli_stmt_execute($stmt);
        $result_electricity = mysqli_stmt_get_result($stmt);
        $latest_electricity_log = fetch_single_row($result_electricity);
        mysqli_stmt_close($stmt);
    } else {
        $message .= "<p class='error'>Error preparing statement for electricity logs: " . mysqli_error($link) . "</p>";
    }

} else {
    // If no vehicle is selected, still allow electricity logs to show if meters exist for the user
    // (This block is for when $selected_vehicle_id is null, but a user is logged in)
    if ($current_user_id) {
        $message .= "<p class='error'>Please add and select a vehicle to view vehicle-specific dashboard data.</p>";

        // Fetch Latest Electricity Log for meters associated with the current user
        $sql_electricity = "SELECT el.purchase_date, el.units_purchased, el.cost, el.notes, em.meter_number, em.description
                            FROM electricity_logs el
                            JOIN electricity_meters em ON el.meter_id = em.id
                            WHERE em.user_id = ?
                            ORDER BY el.purchase_date DESC, el.id DESC LIMIT 1";
        if ($stmt = mysqli_prepare($link, $sql_electricity)) {
            mysqli_stmt_bind_param($stmt, "i", $current_user_id);
            mysqli_stmt_execute($stmt);
            $result_electricity = mysqli_stmt_get_result($stmt);
            $latest_electricity_log = fetch_single_row($result_electricity);
            mysqli_stmt_close($stmt);
        } else {
            $message .= "<p class='error'>Error preparing statement for electricity logs: " . mysqli_error($link) . "</p>";
        }
    } else {
        $message .= "<p class='error'>Please log in to view dashboard data.</p>";
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Data Tracker - Dashboard</title> <!-- App name change -->
    <link rel="stylesheet" href="style.css">
    <!-- Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body>
    <header>
        <h1>My Data Tracker - Dashboard</h1> <!-- App name change -->
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
            <li><a href="index.php" class="active">Dashboard</a></li>
            <li><a href="review.php">Review & Reports</a></li>
            <li><a href="add_car.php">Manage Vehicles</a></li>
            <li><a href="manage_meters.php">Manage Meters</a></li>
            <li>
                <form action="actions.php" method="POST" style="display:inline;">
                    <input type="hidden" name="action" value="logout">
                    <button type="submit" class="nav-button">Logout</button>
                </form>
            </li>
        </ul>
    </nav>

    <div class="container">
        <?php if (!empty($message)): ?>
            <div class="message <?php echo strpos($message, 'Error') !== false || strpos($message, 'Failed') !== false ? 'error' : 'success'; ?>">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <div class="vehicle-selection-section form-section">
            <form action="actions.php" method="POST" style="display: flex; align-items: center; justify-content: flex-end; gap: 10px;">
                <input type="hidden" name="action" value="set_vehicle">
                <label for="vehicle_select" style="margin-bottom: 0;">Viewing Vehicle:</label>
                <select name="vehicle_id" id="vehicle_select" onchange="this.form.submit()" style="flex-grow: 1; max-width: 300px;">
                    <?php if (!empty($vehicles)): ?>
                        <?php foreach ($vehicles as $vehicle): ?>
                            <option value="<?php echo htmlspecialchars($vehicle['id']); ?>"
                                <?php echo ($selected_vehicle_id == $vehicle['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($vehicle['make'] . ' ' . $vehicle['model'] . ' (' . $vehicle['plate_number'] . ')'); ?>
                            </option>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <option value="">No vehicles added</option>
                    <?php endif; ?>
                </select>
                <?php if (!$selected_vehicle_id): ?>
                    <a href="add_car.php" class="button button-primary">Add New Car</a>
                <?php endif; ?>
            </form>
            <?php if ($selected_vehicle_id): ?>
                <p style="text-align: right; margin-top: 5px;">Currently selected: <strong><?php echo htmlspecialchars($selected_vehicle_name); ?></strong></p>
            <?php endif; ?>
        </div>

        <?php if ($selected_vehicle_id && $current_user_id): // Only show dashboard data if a vehicle is selected and user logged in ?>
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h2>Expiry Dates &amp; Renewals</h2>
                <button class="button button-primary" onclick="openDocumentSummaryModal()">View Document Summary</button>
            </div>

            <?php if (!empty($items)): ?>
                <table>
                    <thead>
                        <tr>
                            <th>Item Name</th>
                            <th>Expiry Date</th>
                            <th>Last Renewal Date</th>
                            <th>Last Renewal Cost</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($items as $item): ?>
                            <?php
                                $row_class = '';
                                $status_text = 'Up to date';
                                $expiry_date_display = 'N/A';
                                $last_renewal_date_display = 'N/A';

                                if (!empty($item['expiry_date']) && strtotime($item['expiry_date'])) {
                                    $expiry_dt = new DateTime($item['expiry_date']);
                                    $today_dt = new DateTime();

                                    // 1. Check if Expired FIRST
                                    if ($expiry_dt < $today_dt->setTime(0,0,0)) { // Compare dates only, ignoring time
                                        $row_class = 'expired';
                                        $status_text = 'Expired';
                                    }
                                    // 2. Then check if Expiring this month
                                    elseif (is_expiring_this_month($item['expiry_date'])) {
                                        $row_class = 'expiring-soon';
                                        $status_text = 'Expiring this month';
                                    }
                                    // Default to Up to date if not expired and not expiring soon
                                    else {
                                        $row_class = '';
                                        $status_text = 'Up to date';
                                    }
                                    $expiry_date_display = date('d M Y', $expiry_dt->getTimestamp());
                                } else {
                                    $status_text = 'Not set';
                                }

                                if (!empty($item['last_renewal_date']) && strtotime($item['last_renewal_date'])) {
                                    $last_renewal_date_display = date('d M Y', strtotime($item['last_renewal_date']));
                                }
                            ?>
                            <tr class="<?php echo $row_class; ?>">
                                <td data-label="Item Name"><?php echo htmlspecialchars($item['name']); ?></td>
                                <td data-label="Expiry Date"><?php echo htmlspecialchars($expiry_date_display); ?></td>
                                <td data-label="Last Renewal Date"><?php echo htmlspecialchars($last_renewal_date_display); ?></td>
                                <td data-label="Last Renewal Cost"><?php echo htmlspecialchars($item['last_renewal_cost'] ? 'N' . number_format($item['last_renewal_cost'], 2) : 'N/A'); ?></td>
                                <td data-label="Status"><?php echo $status_text; ?></td>
                                <td data-label="Action">
                                    <button class="button button-update" onclick="openUpdateModal(<?php echo $item['id']; ?>, '<?php echo htmlspecialchars(addslashes($item['name'])); ?>', '<?php echo htmlspecialchars($item['expiry_date'] ?? ''); ?>', '<?php echo htmlspecialchars($item['last_renewal_cost'] ?? ''); ?>')">
                                        Update Renewal
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p>No renewal items found for this vehicle. Ensure default items are set up or add new ones.</p>
            <?php endif; ?>

            <div id="updateRenewalModal" class="modal">
                <div class="modal-content">
                    <span class="close-button" onclick="closeUpdateModal()">&times;</span>
                    <h3>Update Renewal for <span id="itemNameUpdate"></span></h3>
                    <form action="actions.php" method="POST">
                        <input type="hidden" name="action" value="update_renewal">
                        <input type="hidden" name="item_id" id="itemIdUpdate">

                        <label for="new_expiry_date">New Expiry Date:</label>
                        <input type="date" id="new_expiry_date" name="new_expiry_date" required>

                        <label for="renewal_date">Renewal Date (Today or Past):</label>
                        <input type="date" id="renewal_date" name="renewal_date" value="<?php echo date('Y-m-d'); ?>" required>

                        <label for="renewal_cost">Renewal Cost (NGN):</label>
                        <input type="number" id="renewal_cost" name="renewal_cost" step="0.01" min="0" placeholder="0.00" required>

                        <input type="submit" value="Save Renewal Update">
                    </form>
                </div>
            </div>

            <!-- Document Summary Modal -->
            <div id="documentSummaryModal" class="modal">
                <div class="modal-content">
                    <span class="close-button" onclick="closeDocumentSummaryModal()">&times;</span>
                    <h3>Document Summary for <?php echo htmlspecialchars($selected_vehicle_name); ?></h3>
                    <?php if (!empty($items)): ?>
                        <div class="summary-cards">
                            <?php foreach ($items as $item): ?>
                                <?php
                                    $summary_status_text = '';
                                    $summary_expiry_date_display = 'N/A';
                                    $days_to_go = get_days_remaining_for_display($item['expiry_date']);
                                    $card_class = '';

                                    if (!empty($item['expiry_date']) && strtotime($item['expiry_date'])) {
                                        $expiry_dt = new DateTime($item['expiry_date']);
                                        $today_dt = new DateTime();

                                        if ($expiry_dt < $today_dt->setTime(0,0,0)) {
                                            $summary_status_text = 'Expired';
                                            $card_class = 'expired-card';
                                        } elseif (is_expiring_this_month($item['expiry_date'])) {
                                            $summary_status_text = 'Expiring Soon';
                                            $card_class = 'expiring-soon-card';
                                        } else {
                                            $summary_status_text = 'Up to Date';
                                            $card_class = 'up-to-date-card';
                                        }
                                        $summary_expiry_date_display = date('d M Y', $expiry_dt->getTimestamp());
                                    } else {
                                        $summary_status_text = 'Not Set';
                                        $card_class = 'not-set-card';
                                    }
                                ?>
                                <div class="summary-card <?php echo $card_class; ?>">
                                    <h4><?php echo htmlspecialchars($item['name']); ?></h4>
                                    <p><strong>Status:</strong> <?php echo $summary_status_text; ?></p>
                                    <p><strong>Expiry:</strong> <?php echo htmlspecialchars($summary_expiry_date_display); ?></p>
                                    <p><strong>Days to Go:</strong> <?php echo htmlspecialchars($days_to_go); ?></p>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p>No document items found for this vehicle.</p>
                    <?php endif; ?>
                </div>
            </div>


            <hr style="margin: 30px 0;">

            <h3>Current Mileage</h3>
            <?php if ($current_mileage): ?>
                <p><strong>Latest Mileage:</strong> <?php echo htmlspecialchars(number_format($current_mileage['mileage_reading'])); ?> km/miles (Logged on: <?php echo htmlspecialchars(date('d M Y', strtotime($current_mileage['log_date']))); ?>)</p>
            <?php else: ?>
                <p>No mileage logged yet for this vehicle.</p>
            <?php endif; ?>
            <button class="button" onclick="openLogMileageModal()">Log New Mileage</button>

            <div id="logMileageModal" class="modal">
                <div class="modal-content">
                    <span class="close-button" onclick="closeLogMileageModal()">&times;</span>
                    <h3>Log New Mileage</h3>
                    <form action="actions.php" method="POST">
                        <input type="hidden" name="action" value="log_mileage">

                        <label for="mileage_reading">Mileage Reading:</label>
                        <input type="number" id="mileage_reading" name="mileage_reading" required min="0">

                        <label for="mileage_log_date">Log Date:</label>
                        <input type="date" id="mileage_log_date" name="mileage_log_date" value="<?php echo date('Y-m-d'); ?>" required>

                        <label for="mileage_comment">Comment (Optional):</label>
                        <textarea id="mileage_comment" name="mileage_comment" rows="3"></textarea>

                        <input type="submit" value="Save Mileage Log">
                    </form>
                </div>
            </div>

            <h2>Maintenance Log</h2>
            <?php if ($last_maintenance): ?>
                <h3>Last Maintenance Details</h3>
                <p><strong>Date:</strong> <?php echo htmlspecialchars(date('d M Y', strtotime($last_maintenance['maintenance_date']))); ?></p>
                <p><strong>Work Done:</strong> <?php echo nl2br(htmlspecialchars($last_maintenance['work_done'])); ?></p>
                <p><strong>Cost:</strong> <?php echo htmlspecialchars($last_maintenance['cost'] ? 'N' . number_format($last_maintenance['cost'], 2) : 'N/A'); ?></p>
            <? else: ?>
                <p>No maintenance records found for this vehicle.</p>
            <?php endif; ?>
            <button class="button" onclick="openLogMaintenanceModal()">Log New Maintenance</button>

            <div id="logMaintenanceModal" class="modal">
                <div class="modal-content">
                    <span class="close-button" onclick="closeLogMaintenanceModal()">&times;</span>
                    <h3>Log New Maintenance</h3>
                    <form action="actions.php" method="POST">
                        <input type="hidden" name="action" value="log_maintenance">

                        <label for="maintenance_date">Maintenance Date:</label>
                        <input type="date" id="maintenance_date" name="maintenance_date" value="<?php echo date('Y-m-d'); ?>" required>

                        <label for="work_done">Work Done:</label>
                        <textarea id="work_done" name="work_done" rows="4" required></textarea>

                        <label for="maintenance_cost">Cost (NGN, Optional):</label>
                        <input type="number" id="maintenance_cost" name="maintenance_cost" step="0.01" min="0" placeholder="0.00">

                        <input type="submit" value="Save Maintenance Log">
                    </form>
                </div>
            </div>

            <hr style="margin: 30px 0;">

            <h2>Fuel Log</h2>
            <button class="button" onclick="openLogFuelModal()">Log Fuel Purchase</button>

            <div id="logFuelModal" class="modal">
                <div class="modal-content">
                    <span class="close-button" onclick="closeLogFuelModal()">&times;</span>
                    <h3>Log Fuel Purchase</h3>
                    <form action="actions.php" method="POST">
                        <input type="hidden" name="action" value="log_fuel">

                        <label for="purchase_date">Purchase Date:</label>
                        <input type="date" id="purchase_date" name="purchase_date" value="<?php echo date('Y-m-d'); ?>" required>

                        <label for="volume">Volume (Liters):</label>
                        <input type="number" id="volume" name="volume" step="0.01" min="0" required>

                        <label for="fuel_cost">Total Cost (NGN):</label>
                        <input type="number" id="fuel_cost" name="fuel_cost" step="0.01" min="0" required>

                        <label for="odometer_reading_fuel">Odometer Reading (Optional):</label>
                        <input type="number" id="odometer_reading_fuel" name="odometer_reading" min="0">

                        <input type="submit" value="Save Fuel Log">
                    </form>
                </div>
            </div>
        <?php else: ?>
            <p class='info'>Select a vehicle above to view its specific dashboard data.</p>
        <?php endif; ?>

        <hr style="margin: 30px 0;">

        <h2>Electricity Logs</h2>
        <?php if (!empty($meters) && $current_user_id): // Only show if meters exist for the user ?>
            <?php if ($latest_electricity_log): ?>
                <h3>Latest Electricity Purchase:</h3>
                <p><strong>Meter:</strong> <?php echo htmlspecialchars($latest_electricity_log['meter_number']); ?> (<?php echo htmlspecialchars($latest_electricity_log['description']); ?>)</p>
                <p><strong>Date:</strong> <?php echo htmlspecialchars(date('d M Y', strtotime($latest_electricity_log['purchase_date']))); ?></p>
                <p><strong>Units Purchased:</strong> <?php echo htmlspecialchars(number_format($latest_electricity_log['units_purchased'], 2)); ?> units</p>
                <p><strong>Cost:</strong> NGN <?php echo htmlspecialchars(number_format($latest_electricity_log['cost'], 2)); ?></p>
            <?php else: ?>
                <p>No electricity logs found for your meters.</p>
            <?php endif; ?>
            <button class="button" onclick="openLogElectricityModal()">Log Electricity Purchase</button>
        <?php elseif ($current_user_id): ?>
            <p>No electricity meters set up for your account. <a href="manage_meters.php">Manage Meters</a> to add one.</p>
        <?php else: ?>
            <p>Please log in to manage electricity meters.</p>
        <?php endif; ?>

        <div id="logElectricityModal" class="modal">
            <div class="modal-content">
                <span class="close-button" onclick="closeLogElectricityModal()">&times;</span>
                <h3>Log Electricity Purchase</h3>
                <form action="actions.php" method="POST">
                    <input type="hidden" name="action" value="log_electricity">

                    <label for="meter_id">Select Meter:</label>
                    <select id="meter_id" name="meter_id" required>
                        <option value="">-- Choose a Meter --</option>
                        <?php foreach ($meters as $meter): ?>
                            <option value="<?php echo htmlspecialchars($meter['id']); ?>">
                                <?php echo htmlspecialchars($meter['meter_number']); ?>
                                <?php if (!empty($meter['description'])) echo ' (' . htmlspecialchars($meter['description']) . ')'; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (empty($meters) && $current_user_id): ?>
                        <p class="error">No meters available. Please <a href="manage_meters.php">add a meter</a> first.</p>
                    <?php endif; ?>

                    <label for="electricity_purchase_date">Purchase Date:</label>
                    <input type="date" id="electricity_purchase_date" name="electricity_purchase_date" value="<?php echo date('Y-m-d'); ?>" required>

                    <label for="units_purchased">Units Purchased (e.g., kWh):</label>
                    <input type="number" id="units_purchased" name="units_purchased" step="0.01" min="0" required>

                    <label for="electricity_cost">Total Cost (NGN):</label>
                    <input type="number" id="electricity_cost" name="electricity_cost" step="0.01" min="0" required>

                    <label for="electricity_notes">Notes (Optional):</label>
                    <textarea id="electricity_notes" name="electricity_notes" rows="3"></textarea>

                    <input type="submit" value="Save Electricity Log">
                </form>
            </div>
        </div>

        <hr style="margin: 30px 0;">

        <h2>Cooking Gas Logs</h2>
        <?php if ($current_user_id): ?>
            <button class="button" onclick="openLogCookingGasModal()">Log Cooking Gas Purchase</button>
        <?php else: ?>
            <p>Please log in to track cooking gas purchases.</p>
        <?php endif; ?>

        <div id="logCookingGasModal" class="modal">
            <div class="modal-content">
                <span class="close-button" onclick="closeLogCookingGasModal()">&times;</span>
                <h3>Log Cooking Gas Purchase</h3>
                <form action="actions.php" method="POST">
                    <input type="hidden" name="action" value="log_cooking_gas">

                    <label for="gas_purchase_date">Purchase Date:</label>
                    <input type="date" id="gas_purchase_date" name="gas_purchase_date" value="<?php echo date('Y-m-d'); ?>" required>

                    <label for="gas_location">Location:</label>
                    <input type="text" id="gas_location" name="gas_location" placeholder="e.g., Gas station name, Home delivery" required>

                    <label for="gas_quantity">Quantity (e.g., kg or Liters):</label>
                    <input type="number" id="gas_quantity" name="gas_quantity" step="0.01" min="0" required>

                    <label for="gas_cost">Total Cost (NGN):</label>
                    <input type="number" id="gas_cost" name="gas_cost" step="0.01" min="0" required>

                    <label for="gas_notes">Notes (Optional):</label>
                    <textarea id="gas_notes" name="gas_notes" rows="3"></textarea>

                    <input type="submit" value="Save Gas Log">
                </form>
            </div>
        </div>

    </div>

    <footer>
        <p style="text-align:center; margin-top: 20px; color: var(--text-color);">&copy; <?php echo date('Y'); ?> My Data Tracker</p> <!-- App name change -->
    </footer>

    <script>
        // --- Dark Mode Toggle ---
        const checkbox = document.getElementById('checkbox');
        const currentTheme = localStorage.getItem('theme');

        if (currentTheme) {
            document.body.classList.add(currentTheme);
            if (currentTheme === 'dark-mode') {
                checkbox.checked = true;
            }
        }

        checkbox.addEventListener('change', () => {
            if (checkbox.checked) {
                document.body.classList.add('dark-mode');
                localStorage.setItem('theme', 'dark-mode');
            } else {
                document.body.classList.remove('dark-mode');
                localStorage.setItem('theme', '');
            }
        });

        // Get modals
        var updateRenewalModal = document.getElementById("updateRenewalModal");
        var logMileageModal = document.getElementById("logMileageModal");
        var logMaintenanceModal = document.getElementById("logMaintenanceModal");
        var logFuelModal = document.getElementById("logFuelModal");
        var logElectricityModal = document.getElementById("logElectricityModal");
        var logCookingGasModal = document.getElementById("logCookingGasModal"); // New cooking gas modal
        var documentSummaryModal = document.getElementById("documentSummaryModal"); // New document summary modal

        // Functions to open modals
        function openUpdateModal(itemId, itemName, currentExpiry, currentCost) {
            console.log("openUpdateModal called with:", { itemId, itemName, currentExpiry, currentCost });
            document.getElementById("itemIdUpdate").value = itemId;
            document.getElementById("itemNameUpdate").innerText = itemName;
            document.getElementById("renewal_cost").value = parseFloat(currentCost) || '';
            // Ensure currentExpiry is an empty string if null/empty for the date input
            document.getElementById("new_expiry_date").value = currentExpiry ? currentExpiry : '';
            document.getElementById("renewal_date").value = '<?php echo date('Y-m-d'); ?>'; // Always default renewal date to today
            updateRenewalModal.style.display = "block";
        }
        function openLogMileageModal() { logMileageModal.style.display = "block"; }
        function openLogMaintenanceModal() { logMaintenanceModal.style.display = "block"; }
        function openLogFuelModal() { logFuelModal.style.display = "block"; }
        function openLogElectricityModal() { logElectricityModal.style.display = "block"; }
        function openLogCookingGasModal() { logCookingGasModal.style.display = "block"; } // New cooking gas modal function
        function openDocumentSummaryModal() { documentSummaryModal.style.display = "block"; } // New document summary modal function

        // Functions to close modals
        function closeUpdateModal() { updateRenewalModal.style.display = "none"; }
        function closeLogMileageModal() { logMileageModal.style.display = "none"; }
        function closeLogMaintenanceModal() { logMaintenanceModal.style.display = "none"; }
        function closeLogFuelModal() { logFuelModal.style.display = "none"; }
        function closeLogElectricityModal() { logElectricityModal.style.display = "none"; }
        function closeLogCookingGasModal() { logCookingGasModal.style.display = "none"; }
        function closeDocumentSummaryModal() { documentSummaryModal.style.display = "none"; } // New document summary modal close

        // Close modal if user clicks outside of it
        window.onclick = function(event) {
            if (event.target == updateRenewalModal) closeUpdateModal();
            if (event.target == logMileageModal) closeLogMileageModal();
            if (event.target == logMaintenanceModal) closeLogMaintenanceModal();
            if (event.target == logFuelModal) closeLogFuelModal();
            if (event.target == logElectricityModal) closeLogElectricityModal();
            if (event.target == logCookingGasModal) closeLogCookingGasModal();
            if (event.target == documentSummaryModal) closeDocumentSummaryModal(); // New document summary modal close
        }
    </script>
    <style>
        /* Styles for the summary cards */
        .summary-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 15px;
            margin-top: 20px;
        }

        .summary-card {
            background-color: var(--container-bg);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            padding: 15px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            color: var(--text-color);
        }

        .summary-card h4 {
            margin-top: 0;
            margin-bottom: 10px;
            color: var(--header-bg); /* Use a distinct color for card titles */
        }

        .summary-card p {
            margin-bottom: 5px;
            font-size: 0.95em;
        }

        /* Specific card styles based on status */
        .expired-card {
            border-left: 5px solid var(--error-text);
            background-color: var(--error-bg);
        }
        .expiring-soon-card {
            border-left: 5px solid var(--expiring-soon-text);
            background-color: var(--expiring-soon-bg);
        }
        .up-to-date-card {
            border-left: 5px solid var(--success-text);
            background-color: var(--success-bg);
        }
        .not-set-card {
            border-left: 5px solid #888;
            background-color: #f0f0f0; /* Light grey for not set */
        }

        /* Responsive adjustments for smaller screens */
        @media (max-width: 768px) {
            .summary-cards {
                grid-template-columns: 1fr; /* Stack cards on small screens */
            }
        }
    </style>

</body>
</html>
