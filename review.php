<?php
session_start();
require_once 'config.php'; // Database connection and helpers

// --- Initialize variables ---
$vehicles = [];
$selected_vehicle_id = isset($_SESSION['selected_vehicle_id']) ? intval($_SESSION['selected_vehicle_id']) : null;
$selected_vehicle_name = isset($_SESSION['selected_vehicle_name']) ? $_SESSION['selected_vehicle_name'] : 'No Vehicle Selected';


$start_date_filter = isset($_GET['start_date']) ? $_GET['start_date'] : '';
$end_date_filter = isset($_GET['end_date']) ? $_GET['end_date'] : '';

$renewal_logs = [];
$mileage_logs = [];
$maintenance_logs = [];
$fuel_logs = [];
$electricity_logs = []; // New for electricity
$cooking_gas_logs = []; // New for cooking gas

$total_renewal_costs_by_type_filtered = []; // Renamed for clarity
$overall_total_renewal_cost_all_time = 0; // New variable for all-time total
$total_maintenance_cost = 0;
$total_fuel_cost = 0;
$total_fuel_volume = 0;
$total_electricity_units = 0; // New for electricity
$total_electricity_cost = 0;  // New for electricity
$total_cooking_gas_cost = 0; // New for cooking gas

// --- Annual Summary Variables ---
$annual_start_date = date('Y-m-d', strtotime('-1 year'));
$annual_end_date = date('Y-m-d');

$annual_mileage_total = 0;
$annual_maintenance_cost_total = 0;
$annual_fuel_cost_total = 0;
$annual_fuel_volume_total = 0;
$annual_electricity_units_total = 0;
$annual_electricity_cost_total = 0;
$annual_cooking_gas_cost_total = 0;
$annual_cooking_gas_quantity_total = 0;
$annual_renewal_cost_total = 0;


$message = '';
if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    unset($_SESSION['message']);
}

// Get current user's ID and roles
$current_user_id = get_current_user_id();
$is_admin = is_admin();
$is_demo = is_demo_account();


// --- Build WHERE clause for user-specific data for initial vehicle fetch ---
$user_filter_sql_initial = "";
$user_filter_params_initial = [];
$user_filter_types_initial = "";

if (!$is_admin) { // If not admin, filter by user_id
    $user_filter_sql_initial = " WHERE user_id = ?";
    $user_filter_params_initial[] = $current_user_id;
    $user_filter_types_initial = "i";
}


// Fetch all vehicles for the dropdown (filtered by user)
$sql_all_vehicles = "SELECT id, make, model, plate_number FROM vehicles" . $user_filter_sql_initial . " ORDER BY make, model ASC";
if ($stmt_all_vehicles = mysqli_prepare($link, $sql_all_vehicles)) {
    if (!$is_admin) {
        mysqli_stmt_bind_param($stmt_all_vehicles, $user_filter_types_initial, ...$user_filter_params_initial);
    }
    mysqli_stmt_execute($stmt_all_vehicles);
    $result_all_vehicles = mysqli_stmt_get_result($stmt_all_vehicles);
    $vehicles = fetch_all_rows($result_all_vehicles);
    mysqli_stmt_close($stmt_all_vehicles);
} else {
    $message .= "<p class='error'>Error fetching vehicles for selection: " . mysqli_error($link) . "</p>";
}

// --- Fetch Data (only if a user is selected) ---
if ($current_user_id) {

    // --- Build WHERE clauses for date filtering (now includes vehicle_id AND user_id) ---
    // For vehicle-specific logs (renewals, mileage, maintenance, fuel)
    $vehicle_log_params = [$selected_vehicle_id, $current_user_id];
    $vehicle_log_types = "ii";
    $vehicle_date_filter_sql = "";
    if (!empty($start_date_filter)) {
        $vehicle_log_params[] = $start_date_filter;
        $vehicle_log_types .= "s";
        $vehicle_date_filter_sql .= " AND log_date >= ?"; // Use 'log_date' as a generic name for date column
    }
    if (!empty($end_date_filter)) {
        $vehicle_log_params[] = $end_date_filter;
        $vehicle_log_types .= "s";
        $vehicle_date_filter_sql .= " AND log_date <= ?";
    }


    // --- Build WHERE clauses for general logs (electricity, cooking gas) ---
    // These are user-specific, but if admin, they see all.
    $general_log_where_clause = "";
    $general_log_params = [];
    $general_log_types = "";

    if (!$is_admin) {
        $general_log_where_clause = " WHERE user_id = ?";
        $general_log_params[] = $current_user_id;
        $general_log_types = "i";
    }

    $general_date_filter_sql = "";
    if (!empty($start_date_filter)) {
        $general_log_params[] = $start_date_filter;
        $general_log_types .= "s";
        $general_date_filter_sql .= " AND purchase_date >= ?"; // Use 'purchase_date' as a generic name for date column
    }
    if (!empty($end_date_filter)) {
        $general_log_params[] = $end_date_filter;
        $general_log_types .= "s";
        $general_date_filter_sql .= " AND purchase_date <= ?";
    }


    // 1. Renewal Logs (fetch all items that have had a renewal) - Filtered by vehicle, user and date
    if ($selected_vehicle_id) {
        $sql_renewals = "SELECT i.id, i.name, i.expiry_date, i.last_renewal_date, i.last_renewal_cost
                         FROM items i
                         WHERE i.vehicle_id = ? AND i.user_id = ? AND i.last_renewal_date IS NOT NULL ";
        if (!empty($start_date_filter)) $sql_renewals .= " AND i.last_renewal_date >= ?";
        if (!empty($end_date_filter)) $sql_renewals .= " AND i.last_renewal_date <= ?";
        $sql_renewals .= " ORDER BY i.last_renewal_date DESC";

        $stmt_renewals = mysqli_prepare($link, $sql_renewals);
        if ($stmt_renewals) {
            mysqli_stmt_bind_param($stmt_renewals, $vehicle_log_types, ...$vehicle_log_params);
            mysqli_stmt_execute($stmt_renewals);
            $result_renewals = mysqli_stmt_get_result($stmt_renewals);
            $renewal_logs = fetch_all_rows($result_renewals);
            mysqli_stmt_close($stmt_renewals);

            // Calculate total cost per renewal type for the FILTERED period
            foreach ($renewal_logs as $log) {
                if (!isset($total_renewal_costs_by_type_filtered[$log['name']])) {
                    $total_renewal_costs_by_type_filtered[$log['name']] = 0;
                }
                $total_renewal_costs_by_type_filtered[$log['name']] += $log['last_renewal_cost'];
            }
        } else {
            $message .= "<p class='error'>Error fetching renewal logs: " . mysqli_error($link) . "</p>";
        }

        // Calculate OVERALL Total Renewal Cost (All Time) for the selected vehicle and user
        $sql_overall_renewals = "SELECT SUM(last_renewal_cost) AS total_cost FROM items WHERE vehicle_id = ? AND user_id = ? AND last_renewal_cost IS NOT NULL";
        if ($stmt_overall_renewals = mysqli_prepare($link, $sql_overall_renewals)) {
            mysqli_stmt_bind_param($stmt_overall_renewals, "ii", $selected_vehicle_id, $current_user_id);
            mysqli_stmt_execute($stmt_overall_renewals);
            $result_overall_renewals = mysqli_stmt_get_result($stmt_overall_renewals);
            $overall_total_renewal_cost_row = fetch_single_row($result_overall_renewals);
            $overall_total_renewal_cost_all_time = $overall_total_renewal_cost_row['total_cost'] ?? 0;
            mysqli_stmt_close($stmt_overall_renewals);
        } else {
            $message .= "<p class='error'>Error fetching overall renewal total: " . mysqli_error($link) . "</p>";
        }


        // 2. Mileage Logs
        $sql_mileage = "SELECT ml.id, ml.log_date, ml.mileage_reading, ml.comment
                        FROM mileage_logs ml
                        WHERE ml.vehicle_id = ? AND ml.user_id = ? " . str_replace('log_date', 'ml.log_date', $vehicle_date_filter_sql);
        $sql_mileage .= " ORDER BY ml.log_date DESC, ml.id DESC";
        $stmt_mileage = mysqli_prepare($link, $sql_mileage);
        if ($stmt_mileage) {
            mysqli_stmt_bind_param($stmt_mileage, $vehicle_log_types, ...$vehicle_log_params);
            mysqli_stmt_execute($stmt_mileage);
            $result_mileage = mysqli_stmt_get_result($stmt_mileage);
            $mileage_logs = fetch_all_rows($result_mileage);
            mysqli_stmt_close($stmt_mileage);
        } else {
            $message .= "<p class='error'>Error fetching mileage logs: " . mysqli_error($link) . "</p>";
        }

        // 3. Maintenance Logs
        $sql_maintenance = "SELECT mnl.id, mnl.maintenance_date, mnl.work_done, mnl.cost
                            FROM maintenance_logs mnl
                            WHERE mnl.vehicle_id = ? AND mnl.user_id = ? " . str_replace('log_date', 'mnl.maintenance_date', $vehicle_date_filter_sql);
        $sql_maintenance .= " ORDER BY mnl.maintenance_date DESC, mnl.id DESC";
        $stmt_maintenance = mysqli_prepare($link, $sql_maintenance);
        if ($stmt_maintenance) {
            mysqli_stmt_bind_param($stmt_maintenance, $vehicle_log_types, ...$vehicle_log_params);
            mysqli_stmt_execute($stmt_maintenance);
            $result_maintenance = mysqli_stmt_get_result($stmt_maintenance);
            $maintenance_logs = fetch_all_rows($result_maintenance);
            mysqli_stmt_close($stmt_maintenance);
            foreach ($maintenance_logs as $log) {
                $total_maintenance_cost += $log['cost'];
            }
        } else {
            $message .= "<p class='error'>Error fetching maintenance logs: " . mysqli_error($link) . "</p>";
        }


        // 4. Fuel Logs
        $sql_fuel = "SELECT fl.id, fl.purchase_date, fl.volume, fl.cost, fl.odometer_reading
                     FROM fuel_logs fl
                     WHERE fl.vehicle_id = ? AND fl.user_id = ? " . str_replace('log_date', 'fl.purchase_date', $vehicle_date_filter_sql);
        $sql_fuel .= " ORDER BY fl.purchase_date DESC, fl.id DESC";
        $stmt_fuel = mysqli_prepare($link, $sql_fuel);
        if ($stmt_fuel) {
            mysqli_stmt_bind_param($stmt_fuel, $vehicle_log_types, ...$vehicle_log_params);
            mysqli_stmt_execute($stmt_fuel);
            $result_fuel = mysqli_stmt_get_result($stmt_fuel);
            $fuel_logs = fetch_all_rows($result_fuel);
            mysqli_stmt_close($stmt_fuel);
            foreach ($fuel_logs as $log) {
                $total_fuel_cost += $log['cost'];
                $total_fuel_volume += $log['volume'];
            }
        } else {
            $message .= "<p class='error'>Error fetching fuel logs: " . mysqli_error($link) . "</p>";
        }
    } else {
        $message .= "<p class='info'>Select a vehicle to view its specific logs (Renewals, Mileage, Maintenance, Fuel).</p>";
    }


    // 5. Electricity Logs (user-specific, not vehicle-specific)
    $sql_electricity = "SELECT el.id, el.purchase_date, el.units_purchased, el.cost, el.notes, em.meter_number, em.description
                        FROM electricity_logs el
                        JOIN electricity_meters em ON el.meter_id = em.id";

    // Conditionally add user_id filter for non-admin users
    if (!$is_admin) {
        $sql_electricity .= " WHERE em.user_id = ? ";
    } else {
        $sql_electricity .= " WHERE 1=1 "; // Always true for admin, to allow date filters
    }
    $sql_electricity .= str_replace('purchase_date', 'el.purchase_date', $general_date_filter_sql);
    $sql_electricity .= " ORDER BY el.purchase_date DESC, el.id DESC";

    $stmt_electricity = mysqli_prepare($link, $sql_electricity);
    if ($stmt_electricity) {
        // Bind parameters only if there are any (i.e., for non-admin or if date filters are present)
        if (!empty($general_log_params) || !$is_admin) { // Bind if non-admin or if date filters exist for admin
            mysqli_stmt_bind_param($stmt_electricity, $general_log_types, ...$general_log_params);
        }
        mysqli_stmt_execute($stmt_electricity);
        $result_electricity = mysqli_stmt_get_result($stmt_electricity);
        $electricity_logs = fetch_all_rows($result_electricity);
        mysqli_stmt_close($stmt_electricity);

        foreach ($electricity_logs as $log) {
            $total_electricity_units += $log['units_purchased'];
            $total_electricity_cost += $log['cost'];
        }
    } else {
        $message .= "<p class='error'>Error fetching electricity logs: " . mysqli_error($link) . "</p>";
    }

    // 6. Cooking Gas Logs (user-specific)
    $sql_cooking_gas = "SELECT id, purchase_date, location, quantity, cost, notes
                        FROM cooking_gas_logs";

    // Conditionally add user_id filter for non-admin users
    if (!$is_admin) {
        $sql_cooking_gas .= " WHERE user_id = ? ";
    } else {
        $sql_cooking_gas .= " WHERE 1=1 "; // Always true for admin, to allow date filters
    }
    $sql_cooking_gas .= str_replace('purchase_date', 'purchase_date', $general_date_filter_sql);
    $sql_cooking_gas .= " ORDER BY purchase_date DESC, id DESC";

    $stmt_cooking_gas = mysqli_prepare($link, $sql_cooking_gas);
    if ($stmt_cooking_gas) {
        // Bind parameters only if there are any (i.e., for non-admin or if date filters are present)
        if (!empty($general_log_params) || !$is_admin) { // Bind if non-admin or if date filters exist for admin
            mysqli_stmt_bind_param($stmt_cooking_gas, $general_log_types, ...$general_log_params);
        }
        mysqli_stmt_execute($stmt_cooking_gas);
        $result_cooking_gas = mysqli_stmt_get_result($stmt_cooking_gas);
        $cooking_gas_logs = fetch_all_rows($result_cooking_gas);
        mysqli_stmt_close($stmt_cooking_gas);

        foreach ($cooking_gas_logs as $log) {
            $total_cooking_gas_cost += $log['cost'];
        }
    } else {
        $message .= "<p class='error'>Error fetching cooking gas logs: " . mysqli_error($link) . "</p>";
    }


    // --- Fetch Data for Annual Summary (Last 365 Days) ---
    // These queries are distinct from the filtered reports above.

    // Annual Mileage
    $sql_annual_mileage = "SELECT MAX(mileage_reading) - MIN(mileage_reading) AS total_mileage
                           FROM mileage_logs
                           WHERE log_date BETWEEN ? AND ? ";
    $annual_mileage_params = [$annual_start_date, $annual_end_date];
    $annual_mileage_types = "ss";

    if (!$is_admin) {
        $sql_annual_mileage .= " AND user_id = ?";
        $annual_mileage_params[] = $current_user_id;
        $annual_mileage_types .= "i";
    }
    if ($selected_vehicle_id) {
        $sql_annual_mileage .= " AND vehicle_id = ?";
        $annual_mileage_params[] = $selected_vehicle_id;
        $annual_mileage_types .= "i";
    }

    if ($stmt_annual_mileage = mysqli_prepare($link, $sql_annual_mileage)) {
        mysqli_stmt_bind_param($stmt_annual_mileage, $annual_mileage_types, ...$annual_mileage_params);
        mysqli_stmt_execute($stmt_annual_mileage);
        $result_annual_mileage = mysqli_stmt_get_result($stmt_annual_mileage);
        $row = fetch_single_row($result_annual_mileage);
        $annual_mileage_total = $row['total_mileage'] ?? 0;
        mysqli_stmt_close($stmt_annual_mileage);
    }

    // Annual Maintenance Cost
    $sql_annual_maintenance = "SELECT SUM(cost) AS total_cost
                               FROM maintenance_logs
                               WHERE maintenance_date BETWEEN ? AND ? ";
    $annual_maintenance_params = [$annual_start_date, $annual_end_date];
    $annual_maintenance_types = "ss";
    if (!$is_admin) {
        $sql_annual_maintenance .= " AND user_id = ?";
        $annual_maintenance_params[] = $current_user_id;
        $annual_maintenance_types .= "i";
    }
    if ($selected_vehicle_id) {
        $sql_annual_maintenance .= " AND vehicle_id = ?";
        $annual_maintenance_params[] = $selected_vehicle_id;
        $annual_maintenance_types .= "i";
    }
    if ($stmt_annual_maintenance = mysqli_prepare($link, $sql_annual_maintenance)) {
        mysqli_stmt_bind_param($stmt_annual_maintenance, $annual_maintenance_types, ...$annual_maintenance_params);
        mysqli_stmt_execute($stmt_annual_maintenance);
        $result_annual_maintenance = mysqli_stmt_get_result($stmt_annual_maintenance);
        $row = fetch_single_row($result_annual_maintenance);
        $annual_maintenance_cost_total = $row['total_cost'] ?? 0;
        mysqli_stmt_close($stmt_annual_maintenance);
    }

    // Annual Fuel Cost and Volume
    $sql_annual_fuel = "SELECT SUM(cost) AS total_cost, SUM(volume) AS total_volume
                        FROM fuel_logs
                        WHERE purchase_date BETWEEN ? AND ? ";
    $annual_fuel_params = [$annual_start_date, $annual_end_date];
    $annual_fuel_types = "ss";
    if (!$is_admin) {
        $sql_annual_fuel .= " AND user_id = ?";
        $annual_fuel_params[] = $current_user_id;
        $annual_fuel_types .= "i";
    }
    if ($selected_vehicle_id) {
        $sql_annual_fuel .= " AND vehicle_id = ?";
        $annual_fuel_params[] = $selected_vehicle_id;
        $annual_fuel_types .= "i";
    }
    if ($stmt_annual_fuel = mysqli_prepare($link, $sql_annual_fuel)) {
        mysqli_stmt_bind_param($stmt_annual_fuel, $annual_fuel_types, ...$annual_fuel_params);
        mysqli_stmt_execute($stmt_annual_fuel);
        $result_annual_fuel = mysqli_stmt_get_result($stmt_annual_fuel);
        $row = fetch_single_row($result_annual_fuel);
        $annual_fuel_cost_total = $row['total_cost'] ?? 0;
        $annual_fuel_volume_total = $row['total_volume'] ?? 0;
        mysqli_stmt_close($stmt_annual_fuel);
    }

    // Annual Electricity Units and Cost
    $sql_annual_electricity = "SELECT SUM(el.units_purchased) AS total_units, SUM(el.cost) AS total_cost
                               FROM electricity_logs el
                               JOIN electricity_meters em ON el.meter_id = em.id
                               WHERE el.purchase_date BETWEEN ? AND ? ";
    $annual_electricity_params = [$annual_start_date, $annual_end_date];
    $annual_electricity_types = "ss";
    if (!$is_admin) {
        $sql_annual_electricity .= " AND em.user_id = ?"; // Filter by meter's user_id
        $annual_electricity_params[] = $current_user_id;
        $annual_electricity_types .= "i";
    }
    if ($stmt_annual_electricity = mysqli_prepare($link, $sql_annual_electricity)) {
        mysqli_stmt_bind_param($stmt_annual_electricity, $annual_electricity_types, ...$annual_electricity_params);
        mysqli_stmt_execute($stmt_annual_electricity);
        $result_annual_electricity = mysqli_stmt_get_result($stmt_annual_electricity);
        $row = fetch_single_row($result_annual_electricity);
        $annual_electricity_units_total = $row['total_units'] ?? 0;
        $annual_electricity_cost_total = $row['total_cost'] ?? 0;
        mysqli_stmt_close($stmt_annual_electricity);
    }

    // Annual Cooking Gas Cost and Quantity
    $sql_annual_cooking_gas = "SELECT SUM(quantity) AS total_quantity, SUM(cost) AS total_cost
                               FROM cooking_gas_logs
                               WHERE purchase_date BETWEEN ? AND ? ";
    $annual_cooking_gas_params = [$annual_start_date, $annual_end_date];
    $annual_cooking_gas_types = "ss";
    if (!$is_admin) {
        $sql_annual_cooking_gas .= " AND user_id = ?";
        $annual_cooking_gas_params[] = $current_user_id;
        $annual_cooking_gas_types .= "i";
    }
    if ($stmt_annual_cooking_gas = mysqli_prepare($link, $sql_annual_cooking_gas)) {
        mysqli_stmt_bind_param($stmt_annual_cooking_gas, $annual_cooking_gas_types, ...$annual_cooking_gas_params);
        mysqli_stmt_execute($stmt_annual_cooking_gas);
        $result_annual_cooking_gas = mysqli_stmt_get_result($stmt_annual_cooking_gas);
        $row = fetch_single_row($result_annual_cooking_gas);
        $annual_cooking_gas_quantity_total = $row['total_quantity'] ?? 0;
        $annual_cooking_gas_cost_total = $row['total_cost'] ?? 0;
        mysqli_stmt_close($stmt_annual_cooking_gas);
    }

    // Annual Renewal Cost (for selected vehicle if applicable, otherwise overall)
    $sql_annual_renewal = "SELECT SUM(last_renewal_cost) AS total_cost
                           FROM items
                           WHERE last_renewal_date BETWEEN ? AND ? ";
    $annual_renewal_params = [$annual_start_date, $annual_end_date];
    $annual_renewal_types = "ss";
    if (!$is_admin) {
        $sql_annual_renewal .= " AND user_id = ?";
        $annual_renewal_params[] = $current_user_id;
        $annual_renewal_types .= "i";
    }
    if ($selected_vehicle_id) { // If a vehicle is selected, sum renewals for that vehicle
        $sql_annual_renewal .= " AND vehicle_id = ?";
        $annual_renewal_params[] = $selected_vehicle_id;
        $annual_renewal_types .= "i";
    }
    if ($stmt_annual_renewal = mysqli_prepare($link, $sql_annual_renewal)) {
        mysqli_stmt_bind_param($stmt_annual_renewal, $annual_renewal_types, ...$annual_renewal_params);
        mysqli_stmt_execute($stmt_annual_renewal);
        $result_annual_renewal = mysqli_stmt_get_result($stmt_annual_renewal);
        $row = fetch_single_row($result_annual_renewal);
        $annual_renewal_cost_total = $row['total_cost'] ?? 0;
        mysqli_stmt_close($stmt_annual_renewal);
    }

} else {
    $message .= "<p class='error'>Please log in to view reports.</p>";
}

?>
<!DOCTYPE html>
<html lang="en" class="grey-mode">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Data Tracker - Review & Reports</title> <!-- App name change -->
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="styles.css">
    <!-- Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body>
    <header>
        <h1>My Data Tracker - Review & Reports</h1> <!-- App name change -->
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
            <li><a href="review.php" class="active">Review & Reports</a></li>
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

        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h2>Filter Logs by Date</h2>
            <?php if ($current_user_id): ?>
                <button class="button button-primary" onclick="openAnnualSummaryModal()">View Annual Summary</button>
            <?php endif; ?>
        </div>
        <form action="review.php" method="GET" class="form-section">
            <label for="start_date">Start Date:</label>
            <input type="date" id="start_date" name="start_date" value="<?php echo htmlspecialchars($start_date_filter); ?>">

            <label for="end_date">End Date:</label>
            <input type="date" id="end_date" name="end_date" value="<?php echo htmlspecialchars($end_date_filter); ?>">

            <input type="submit" value="Filter Logs">
            <a href="review.php" class="button" style="margin-left: 10px; background-color: #777;">Clear Filters</a>
        </form>

        <?php if ($selected_vehicle_id && $current_user_id): // Only show vehicle-specific reports if a vehicle and user is selected ?>
            <h2>Renewal Logs</h2>
            <?php if (!empty($renewal_logs)): ?>
                <table>
                    <thead>
                        <tr>
                            <th>Item Name</th>
                            <th>Renewal Date</th>
                            <th>New Expiry Date</th>
                            <th>Cost (NGN)</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($renewal_logs as $log): ?>
                        <?php
                            $log_expiry_date_display = 'N/A';
                            if (!empty($log['expiry_date']) && strtotime($log['expiry_date'])) {
                                $log_expiry_date_display = date('d M Y', strtotime($log['expiry_date']));
                            }
                        ?>
                        <tr>
                            <td data-label="Item Name"><?php echo htmlspecialchars($log['name']); ?></td>
                            <td data-label="Renewal Date"><?php echo htmlspecialchars(date('d M Y', strtotime($log['last_renewal_date']))); ?></td>
                            <td data-label="New Expiry Date"><?php echo htmlspecialchars($log_expiry_date_display); ?></td>
                            <td data-label="Cost (NGN)"><?php echo htmlspecialchars(number_format($log['last_renewal_cost'], 2)); ?></td>
                            <td data-label="Action">
                                <button class="button button-danger" onclick="openDeleteLogModal('item', <?php echo $log['id']; ?>, '<?php echo htmlspecialchars(addslashes($log['name'] . ' renewal on ' . date('d M Y', strtotime($log['last_renewal_date'])))); ?>')">
                                    <i class="fas fa-trash-alt"></i> Delete
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <h3>Total Renewal Costs by Type (within filter for selected vehicle):</h3>
                <ul>
                    <?php foreach ($total_renewal_costs_by_type_filtered as $type => $total_cost): ?>
                    <li><strong><?php echo htmlspecialchars($type); ?>:</strong> NGN <?php echo htmlspecialchars(number_format($total_cost, 2)); ?></li>
                    <?php endforeach; ?>
                    <?php if (empty($total_renewal_costs_by_type_filtered)): ?><li>No renewal costs in selected period for this vehicle.</li><?php endif; ?>
                </ul>
                <p><strong>Overall Total Renewal Cost (All Time for selected vehicle):</strong> NGN <?php echo htmlspecialchars(number_format($overall_total_renewal_cost_all_time, 2)); ?></p>
            <?php else: ?>
                <p>No renewal logs found for the selected vehicle and period.</p>
                <p><strong>Overall Total Renewal Cost (All Time for selected vehicle):</strong> NGN <?php echo htmlspecialchars(number_format($overall_total_renewal_cost_all_time, 2)); ?></p>
            <?php endif; ?>

            <h2>Mileage Logs</h2>
            <?php if (!empty($mileage_logs)): ?>
                <table>
                    <thead>
                        <tr>
                            <th>Log Date</th>
                            <th>Mileage Reading</th>
                            <th>Comment</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($mileage_logs as $log): ?>
                        <tr>
                            <td data-label="Log Date"><?php echo htmlspecialchars(date('d M Y', strtotime($log['log_date']))); ?></td>
                            <td data-label="Mileage Reading"><?php echo htmlspecialchars(number_format($log['mileage_reading'])); ?></td>
                            <td data-label="Comment"><?php echo htmlspecialchars($log['comment'] ? $log['comment'] : '-'); ?></td>
                            <td data-label="Action">
                                <button class="button button-danger" onclick="openDeleteLogModal('mileage', <?php echo $log['id']; ?>, 'Mileage on <?php echo htmlspecialchars(date('d M Y', strtotime($log['log_date']))); ?>')">
                                    <i class="fas fa-trash-alt"></i> Delete
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p>No mileage logs found for the selected vehicle and period.</p>
            <?php endif; ?>

            <h2>Maintenance Logs</h2>
            <?php if (!empty($maintenance_logs)): ?>
                <table>
                    <thead>
                        <tr>
                            <th>Maintenance Date</th>
                            <th>Work Done</th>
                            <th>Cost (NGN)</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($maintenance_logs as $log): ?>
                        <tr>
                            <td data-label="Maintenance Date"><?php echo htmlspecialchars(date('d M Y', strtotime($log['maintenance_date']))); ?></td>
                            <td data-label="Work Done"><?php echo nl2br(htmlspecialchars($log['work_done'])); ?></td>
                            <td data-label="Cost (NGN)"><?php echo htmlspecialchars($log['cost'] ? number_format($log['cost'], 2) : '0.00'); ?></td>
                            <td data-label="Action">
                                <button class="button button-danger" onclick="openDeleteLogModal('maintenance', <?php echo $log['id']; ?>, 'Maintenance on <?php echo htmlspecialchars(date('d M Y', strtotime($log['maintenance_date']))); ?>')">
                                    <i class="fas fa-trash-alt"></i> Delete
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <p><strong>Total Maintenance Cost (within filter):</strong> NGN <?php echo htmlspecialchars(number_format($total_maintenance_cost, 2)); ?></p>
            <?php else: ?>
                <p>No maintenance logs found for the selected vehicle and period.</p>
            <?php endif; ?>

            <h2>Fuel Purchase Logs</h2>
            <?php if (!empty($fuel_logs)): ?>
                <table>
                    <thead>
                        <tr>
                            <th>Purchase Date</th>
                            <th>Volume (Liters)</th>
                            <th>Total Cost (NGN)</th>
                            <th>Odometer Reading</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($fuel_logs as $log): ?>
                        <tr>
                            <td data-label="Purchase Date"><?php echo htmlspecialchars(date('d M Y', strtotime($log['purchase_date']))); ?></td>
                            <td data-label="Volume (Liters)"><?php echo htmlspecialchars(number_format($log['volume'], 2)); ?></td>
                            <td data-label="Total Cost (NGN)"><?php echo htmlspecialchars(number_format($log['cost'], 2)); ?></td>
                            <td data-label="Odometer Reading"><?php echo htmlspecialchars($log['odometer_reading'] ? number_format($log['odometer_reading']) : '-'); ?></td>
                            <td data-label="Action">
                                <button class="button button-danger" onclick="openDeleteLogModal('fuel', <?php echo $log['id']; ?>, 'Fuel on <?php echo htmlspecialchars(date('d M Y', strtotime($log['purchase_date']))); ?>')">
                                    <i class="fas fa-trash-alt"></i> Delete
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <p><i>Total Fuel Volume (within filter):</i> <?php echo htmlspecialchars(number_format($total_fuel_volume, 2)); ?> Liters</p>
                <p><b>Total Fuel Cost (within filter):</b> NGN <?php echo htmlspecialchars(number_format($total_fuel_cost, 2)); ?></p>
            <?php else: ?>
                <p>No fuel logs found for the selected vehicle and period.</p>
            <?php endif; ?>
        <?php else: ?>
            <p class='info'>Select a vehicle above to view its specific logs (Renewals, Mileage, Maintenance, Fuel).</p>
        <?php endif; ?> <!-- End of vehicle-specific reports section -->

        <h2>Electricity Purchase Logs</h2>
        <?php if (!empty($electricity_logs)): ?>
            <table>
                <thead>
                    <tr>
                        <th>Purchase Date</th>
                        <th>Meter</th>
                        <th>Units Purchased</th>
                        <th>Cost (NGN)</th>
                        <th>Notes</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($electricity_logs as $log): ?>
                    <tr>
                        <td data-label="Purchase Date"><?php echo htmlspecialchars(date('d M Y', strtotime($log['purchase_date']))); ?></td>
                        <td data-label="Meter"><?php echo htmlspecialchars($log['meter_number'] . (empty($log['description']) ? '' : ' (' . $log['description'] . ')')); ?></td>
                        <td data-label="Units Purchased"><?php echo htmlspecialchars(number_format($log['units_purchased'], 2)); ?></td>
                        <td data-label="Cost (NGN)"><?php echo htmlspecialchars(number_format($log['cost'], 2)); ?></td>
                        <td data-label="Notes"><?php echo htmlspecialchars($log['notes'] ?: '-'); ?></td>
                        <td data-label="Action">
                            <button class="button button-danger" onclick="openDeleteLogModal('electricity', <?php echo $log['id']; ?>, 'Electricity on <?php echo htmlspecialchars(date('d M Y', strtotime($log['purchase_date'])) . ' for meter ' . $log['meter_number']); ?>')">
                                <i class="fas fa-trash-alt"></i> Delete
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <p><i>Total Electricity Units (within filter):</i> <?php echo htmlspecialchars(number_format($total_electricity_units, 2)); ?> units</p>
            <p><b>Total Electricity Cost (within filter):</b> NGN <?php echo htmlspecialchars(number_format($total_electricity_cost, 2)); ?></p>
        <?php else: ?>
            <p>No electricity logs found for the selected period.</p>
        <?php endif; ?>

        <h2>Cooking Gas Purchase Logs</h2>
        <?php if (!empty($cooking_gas_logs)): ?>
            <table>
                <thead>
                    <tr>
                        <th>Purchase Date</th>
                        <th>Location</th>
                        <th>Quantity</th>
                        <th>Cost (NGN)</th>
                        <th>Notes</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($cooking_gas_logs as $log): ?>
                    <tr>
                        <td data-label="Purchase Date"><?php echo htmlspecialchars(date('d M Y', strtotime($log['purchase_date']))); ?></td>
                        <td data-label="Location"><?php echo htmlspecialchars($log['location']); ?></td>
                        <td data-label="Quantity"><?php echo htmlspecialchars(number_format($log['quantity'], 2)); ?></td>
                        <td data-label="Cost (NGN)"><?php echo htmlspecialchars(number_format($log['cost'], 2)); ?></td>
                        <td data-label="Notes"><?php echo htmlspecialchars($log['notes'] ?: '-'); ?></td>
                        <td data-label="Action">
                            <button class="button button-danger" onclick="openDeleteLogModal('cooking_gas', <?php echo $log['id']; ?>, 'Cooking Gas on <?php echo htmlspecialchars(date('d M Y', strtotime($log['purchase_date'])) . ' at ' . $log['location']); ?>')">
                                <i class="fas fa-trash-alt"></i> Delete
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <p><b>Total Cooking Gas Cost (within filter):</b> NGN <?php echo htmlspecialchars(number_format($total_cooking_gas_cost, 2)); ?></p>
        <?php else: ?>
            <p>No cooking gas logs found for the selected period.</p>
        <?php endif; ?>


        <!-- Delete Confirmation Modal -->
        <div id="deleteLogModal" class="modal">
            <div class="modal-content">
                <span class="close-button" onclick="closeDeleteLogModal()">&times;</span>
                <h3>Confirm Deletion</h3>
                <p>Are you sure you want to delete this <span id="logTypeToDelete"></span> record for <strong id="logNameToDelete"></strong>?</p>
                <p>This action cannot be undone.</p>
                <form action="actions.php" method="POST" style="text-align: right;">
                    <input type="hidden" name="action" id="deleteActionInput">
                    <input type="hidden" name="id" id="logIdToDelete">
                    <button type="button" class="button" onclick="closeDeleteLogModal()">Cancel</button>
                    <input type="submit" value="Delete Record" class="button button-danger">
                </form>
            </div>
        </div>

        <!-- Annual Summary Modal -->
        <div id="annualSummaryModal" class="modal">
            <div class="modal-content">
                <span class="close-button" onclick="closeAnnualSummaryModal()">&times;</span>
                <h3>Annual Summary (Last 365 Days)</h3>
                <p>From: <strong><?php echo htmlspecialchars(date('d M Y', strtotime($annual_start_date))); ?></strong> to <strong><?php echo htmlspecialchars(date('d M Y', strtotime($annual_end_date))); ?></strong></p>
                <?php if ($current_user_id): ?>
                    <?php if ($selected_vehicle_id): ?>
                        <p>For Vehicle: <strong><?php echo htmlspecialchars($selected_vehicle_name); ?></strong></p>
                    <?php else: ?>
                        <p><i>Note: Vehicle-specific data below is for all vehicles if no vehicle is selected.</i></p>
                    <?php endif; ?>

                    <div class="summary-details">
                        <h4>Vehicle Related Costs/Usage:</h4>
                        <ul>
                            <li><strong>Total Mileage:</strong> <?php echo htmlspecialchars(number_format($annual_mileage_total)); ?> km/miles</li>
                            <li><strong>Total Maintenance Cost:</strong> NGN <?php echo htmlspecialchars(number_format($annual_maintenance_cost_total, 2)); ?></li>
                            <li><strong>Total Fuel Volume:</strong> <?php echo htmlspecialchars(number_format($annual_fuel_volume_total, 2)); ?> Liters</li>
                            <li><strong>Total Fuel Cost:</strong> NGN <?php echo htmlspecialchars(number_format($annual_fuel_cost_total, 2)); ?></li>
                            <li><strong>Total Renewal Cost:</strong> NGN <?php echo htmlspecialchars(number_format($annual_renewal_cost_total, 2)); ?></li>
                        </ul>

                        <h4>Other Costs/Usage:</h4>
                        <ul>
                            <li><strong>Total Electricity Units:</strong> <?php echo htmlspecialchars(number_format($annual_electricity_units_total, 2)); ?> units</li>
                            <li><strong>Total Electricity Cost:</strong> NGN <?php echo htmlspecialchars(number_format($annual_electricity_cost_total, 2)); ?></li>
                            <li><strong>Total Cooking Gas Quantity:</strong> <?php echo htmlspecialchars(number_format($annual_cooking_gas_quantity_total, 2)); ?></li>
                            <li><strong>Total Cooking Gas Cost:</strong> NGN <?php echo htmlspecialchars(number_format($annual_cooking_gas_cost_total, 2)); ?></li>
                        </ul>
                    </div>
                <?php else: ?>
                    <p>Please log in to view the annual summary.</p>
                <?php endif; ?>
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

        // --- Delete Log Modal Functions ---
        var deleteLogModal = document.getElementById("deleteLogModal");
        var annualSummaryModal = document.getElementById("annualSummaryModal"); // New annual summary modal

        function openDeleteLogModal(logType, logId, logName) {
            document.getElementById("logTypeToDelete").innerText = logType;
            document.getElementById("logNameToDelete").innerText = logName;
            document.getElementById("logIdToDelete").value = logId;
            document.getElementById("deleteActionInput").value = 'delete_' + logType; // e.g., 'delete_mileage', 'delete_cooking_gas'

            deleteLogModal.style.display = "block";
        }

        function closeDeleteLogModal() {
            deleteLogModal.style.display = "none";
        }

        function openAnnualSummaryModal() { // New function to open annual summary modal
            annualSummaryModal.style.display = "block";
        }

        function closeAnnualSummaryModal() { // New function to close annual summary modal
            annualSummaryModal.style.display = "none";
        }

        // Close modal if user clicks outside of it
        window.onclick = function(event) {
            if (event.target == deleteLogModal) closeDeleteLogModal();
            if (event.target == annualSummaryModal) closeAnnualSummaryModal(); // New close handler
        }
    </script>
    <style>
        /* Styles for the summary details in the annual summary modal */
        .summary-details ul {
            list-style: none;
            padding: 0;
            margin-top: 10px;
        }

        .summary-details li {
            background-color: var(--form-bg);
            border: 1px solid var(--border-color);
            border-radius: 5px;
            padding: 10px 15px;
            margin-bottom: 8px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 1.0em;
        }

        .summary-details li strong {
            color: var(--text-color);
        }

        .summary-details h4 {
            margin-top: 20px;
            margin-bottom: 10px;
            color: var(--header-bg);
            border-bottom: 1px solid var(--border-color);
            padding-bottom: 5px;
        }
    </style>
</body>
</html>
