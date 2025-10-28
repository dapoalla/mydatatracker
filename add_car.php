<?php
session_start();
require_once 'config.php'; // Include database configuration and helper functions

// Initialize variables
$vehicles = [];
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

// Fetch all vehicles for display (filtered by user)
$sql_vehicles = "SELECT id, make, model, plate_number, vehicle_type FROM vehicles";
$params = [];
$types = "";

if (!$is_admin) { // If not admin, filter by user_id
    $sql_vehicles .= " WHERE user_id = ?";
    $params[] = $current_user_id;
    $types = "i";
}
$sql_vehicles .= " ORDER BY make, model ASC";

if ($stmt_vehicles = mysqli_prepare($link, $sql_vehicles)) {
    if (!$is_admin) {
        mysqli_stmt_bind_param($stmt_vehicles, $types, ...$params);
    }
    mysqli_stmt_execute($stmt_vehicles);
    $result_vehicles = mysqli_stmt_get_result($stmt_vehicles);
    $vehicles = fetch_all_rows($result_vehicles);
    mysqli_stmt_close($stmt_vehicles);
} else {
    $message .= "<p class='error'>Error fetching vehicles: " . mysqli_error($link) . "</p>";
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Data Tracker - Manage Vehicles</title> <!-- App name change -->
    <link rel="stylesheet" href="style.css">
    <!-- Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body>
    <header>
        <h1>My Data Tracker - Manage Vehicles</h1> <!-- App name change -->
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
            <li><a href="add_car.php" class="active">Manage Vehicles</a></li>
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

        <h2>Add New Vehicle</h2>
        <?php if ($current_user_id): ?>
            <form action="actions.php" method="POST" class="form-section">
                <input type="hidden" name="action" value="add_vehicle"> <!-- Ensure this matches 'add_vehicle' in actions.php -->

                <label for="make">Make:</label>
                <input type="text" id="make" name="make" required>

                <label for="model">Model:</label>
                <input type="text" id="model" name="model" required>

                <label for="plate_number">Plate Number:</label>
                <input type="text" id="plate_number" name="plate_number" required>

                <label for="vehicle_type">Vehicle Type (e.g., Sedan, SUV, Truck):</label>
                <input type="text" id="vehicle_type" name="vehicle_type">

                <input type="submit" value="Add Vehicle" class="button-primary">
            </form>
        <?php else: ?>
            <p>Please log in to add new vehicles.</p>
        <?php endif; ?>

        <h2>Your Vehicles</h2>
        <?php if (!empty($vehicles)): ?>
            <table>
                <thead>
                    <tr>
                        <th>Make</th>
                        <th>Model</th>
                        <th>Plate Number</th>
                        <th>Type</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($vehicles as $vehicle): ?>
                        <tr>
                            <td data-label="Make"><?php echo htmlspecialchars($vehicle['make']); ?></td>
                            <td data-label="Model"><?php echo htmlspecialchars($vehicle['model']); ?></td>
                            <td data-label="Plate Number"><?php echo htmlspecialchars($vehicle['plate_number']); ?></td>
                            <td data-label="Type"><?php echo htmlspecialchars($vehicle['vehicle_type'] ?: 'N/A'); ?></td>
                            <td data-label="Actions">
                                <form action="actions.php" method="POST" style="display:inline-block; margin: 0;">
                                    <input type="hidden" name="action" value="set_vehicle">
                                    <input type="hidden" name="vehicle_id" value="<?php echo $vehicle['id']; ?>">
                                    <button type="submit" class="button" title="Select this vehicle">
                                        <?php if (isset($_SESSION['selected_vehicle_id']) && $_SESSION['selected_vehicle_id'] == $vehicle['id']): ?>
                                            <i class="fas fa-check-circle"></i> Selected
                                        <?php else: ?>
                                            <i class="fas fa-car"></i> Select
                                        <?php endif; ?>
                                    </button>
                                </form>
                                <button class="button button-danger" onclick="openDeleteVehicleModal(<?php echo $vehicle['id']; ?>, '<?php echo htmlspecialchars(addslashes($vehicle['make'] . ' ' . $vehicle['model'] . ' (' . $vehicle['plate_number'] . ')')); ?>')" title="Delete this vehicle">
                                    <i class="fas fa-trash-alt"></i> Delete
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p>No vehicles added yet. Use the form above to add your first car!</p>
        <?php endif; ?>

        <!-- Delete Vehicle Confirmation Modal -->
        <div id="deleteVehicleModal" class="modal">
            <div class="modal-content">
                <span class="close-button" onclick="closeDeleteVehicleModal()">&times;</span>
                <h3>Confirm Deletion</h3>
                <p>Are you sure you want to delete the vehicle: <strong id="vehicleToDeleteName"></strong>?</p>
                <p>This action cannot be undone and will also delete all associated renewal items, mileage logs, maintenance logs, and fuel logs for this vehicle.</p>
                <form action="actions.php" method="POST" style="text-align: right;">
                    <input type="hidden" name="action" value="delete_vehicle">
                    <input type="hidden" name="vehicle_id" id="vehicleToDeleteId">
                    <button type="button" class="button" onclick="closeDeleteVehicleModal()">Cancel</button>
                    <input type="submit" value="Delete Vehicle" class="button button-danger">
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

        // --- Delete Vehicle Modal Functions ---
        var deleteVehicleModal = document.getElementById("deleteVehicleModal");

        function openDeleteVehicleModal(vehicleId, vehicleName) {
            document.getElementById("vehicleToDeleteId").value = vehicleId;
            document.getElementById("vehicleToDeleteName").innerText = vehicleName;
            deleteVehicleModal.style.display = "block";
        }

        function closeDeleteVehicleModal() {
            deleteVehicleModal.style.display = "none";
        }

        // Close modal if user clicks outside of it
        window.onclick = function(event) {
            if (event.target == deleteVehicleModal) closeDeleteVehicleModal();
        }
    </script>
</body>
</html>
