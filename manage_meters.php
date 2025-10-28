<?php
session_start();
require_once 'config.php'; // Include database configuration and helper functions

// Initialize variables
$meters = [];
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

// Fetch all electricity meters for display (filtered by user)
$sql_meters = "SELECT em.id, em.meter_number, em.description FROM electricity_meters em";
$params = [];
$types = "";

if (!$is_admin) { // If not admin, filter by user_id
    $sql_meters .= " WHERE em.user_id = ?";
    $params[] = $current_user_id;
    $types = "i";
}
$sql_meters .= " ORDER BY em.meter_number ASC";

if ($stmt_meters = mysqli_prepare($link, $sql_meters)) {
    if (!$is_admin) {
        mysqli_stmt_bind_param($stmt_meters, $types, ...$params);
    }
    mysqli_stmt_execute($stmt_meters);
    $result_meters = mysqli_stmt_get_result($stmt_meters);
    $meters = fetch_all_rows($result_meters);
    mysqli_stmt_close($stmt_meters);
} else {
    $message .= "<p class='error'>Error fetching electricity meters: " . mysqli_error($link) . "</p>";
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Data Tracker - Manage Electricity Meters</title> <!-- App name change -->
    <link rel="stylesheet" href="style.css">
    <!-- Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body>
    <header>
        <h1>My Data Tracker - Manage Electricity Meters</h1> <!-- App name change -->
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
            <li><a href="manage_meters.php" class="active">Manage Meters</a></li>
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

        <h2>Add New Electricity Meter</h2>
        <?php if ($current_user_id): ?>
            <form action="actions.php" method="POST" class="form-section">
                <input type="hidden" name="action" value="add_meter">

                <label for="meter_number">Meter Number:</label>
                <input type="text" id="meter_number" name="meter_number" required>

                <label for="description">Description (e.g., Home, Office, Solar):</label>
                <input type="text" id="description" name="description">

                <input type="submit" value="Add Meter" class="button-primary">
            </form>
        <?php else: ?>
            <p>Please log in to add new electricity meters.</p>
        <?php endif; ?>

        <h2>Your Electricity Meters</h2>
        <?php if (!empty($meters)): ?>
            <table>
                <thead>
                    <tr>
                        <th>Meter Number</th>
                        <th>Description</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($meters as $meter): ?>
                        <tr>
                            <td data-label="Meter Number"><?php echo htmlspecialchars($meter['meter_number']); ?></td>
                            <td data-label="Description"><?php echo htmlspecialchars($meter['description'] ?: 'N/A'); ?></td>
                            <td data-label="Actions">
                                <button class="button button-danger" onclick="openDeleteMeterModal(<?php echo $meter['id']; ?>, '<?php echo htmlspecialchars(addslashes($meter['meter_number'])); ?>')" title="Delete this meter">
                                    <i class="fas fa-trash-alt"></i> Delete
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p>No electricity meters added yet. Use the form above to add your first meter!</p>
        <?php endif; ?>

        <!-- Delete Meter Confirmation Modal -->
        <div id="deleteMeterModal" class="modal">
            <div class="modal-content">
                <span class="close-button" onclick="closeDeleteMeterModal()">&times;</span>
                <h3>Confirm Deletion</h3>
                <p>Are you sure you want to delete the meter: <strong id="meterToDeleteNumber"></strong>?</p>
                <p>This action cannot be undone and will also delete all associated electricity logs for this meter.</p>
                <form action="actions.php" method="POST" style="text-align: right;">
                    <input type="hidden" name="action" value="delete_meter">
                    <input type="hidden" name="meter_id" id="meterToDeleteId">
                    <button type="button" class="button" onclick="closeDeleteMeterModal()">Cancel</button>
                    <input type="submit" value="Delete Meter" class="button button-danger">
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

        // --- Delete Meter Modal Functions ---
        var deleteMeterModal = document.getElementById("deleteMeterModal");

        function openDeleteMeterModal(meterId, meterNumber) {
            document.getElementById("meterToDeleteId").value = meterId;
            document.getElementById("meterToDeleteNumber").innerText = meterNumber;
            deleteMeterModal.style.display = "block";
        }

        function closeDeleteMeterModal() {
            deleteMeterModal.style.display = "none";
        }

        // Close modal if user clicks outside of it
        window.onclick = function(event) {
            if (event.target == deleteMeterModal) closeDeleteMeterModal();
        }
    </script>
</body>
</html>
