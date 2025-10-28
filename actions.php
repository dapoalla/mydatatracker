<?php
ini_set('display_errors', 1); // Temporarily enable display of all errors for debugging
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start(); // Required to set session messages
require_once 'config.php'; // Database connection and helpers

// --- Default redirect location ---
$redirect_url = 'index.php'; // Default redirect back to dashboard
$message = '';
$message_type = 'error'; // 'success' or 'error'

// Get the current user's ID
$current_user_id = get_current_user_id();

// Check if it's a POST request and an action is set
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action'])) {
    $action = $_POST['action'];

    // Get the current vehicle ID from the session, if available
    $current_vehicle_id = isset($_SESSION['selected_vehicle_id']) ? intval($_SESSION['selected_vehicle_id']) : null;
    // Get the current meter ID from the session, if available (will be set when logging electricity)
    $current_meter_id = isset($_SESSION['selected_meter_id']) ? intval($_SESSION['selected_meter_id']) : null;


    // --- Action: Update Renewal ---
    if ($action == 'update_renewal') {
        $item_id = isset($_POST['item_id']) ? intval($_POST['item_id']) : 0;
        $new_expiry_date = $_POST['new_expiry_date'];
        $renewal_date = $_POST['renewal_date'];
        $renewal_cost = isset($_POST['renewal_cost']) ? floatval($_POST['renewal_cost']) : 0.00;

        if ($item_id > 0 && !empty($new_expiry_date) && !empty($renewal_date) && $current_user_id) {
            // Ensure the item belongs to the selected vehicle and current user for security
            $sql = "UPDATE items SET expiry_date = ?, last_renewal_date = ?, last_renewal_cost = ? WHERE id = ? AND vehicle_id = ? AND user_id = ?";
            if ($stmt = mysqli_prepare($link, $sql)) {
                mysqli_stmt_bind_param($stmt, "ssddii", $new_expiry_date, $renewal_date, $renewal_cost, $item_id, $current_vehicle_id, $current_user_id);
                if (mysqli_stmt_execute($stmt)) {
                    $message = "Renewal updated successfully for item ID: " . $item_id;
                    $message_type = 'success';
                } else {
                    $message = "Error updating renewal: " . mysqli_stmt_error($stmt);
                }
                mysqli_stmt_close($stmt);
            } else {
                $message = "Error preparing statement: " . mysqli_error($link);
            }
        } else {
            $message = "Invalid data provided for renewal update or no user/vehicle selected.";
        }
    }

    // --- Action: Log Mileage ---
    elseif ($action == 'log_mileage') {
        $mileage_reading = isset($_POST['mileage_reading']) ? intval($_POST['mileage_reading']) : 0;
        $log_date = $_POST['mileage_log_date'];
        $comment = isset($_POST['mileage_comment']) ? trim($_POST['mileage_comment']) : null;

        if ($mileage_reading > 0 && !empty($log_date) && $current_vehicle_id && $current_user_id) {
            $sql = "INSERT INTO mileage_logs (vehicle_id, user_id, mileage_reading, log_date, comment) VALUES (?, ?, ?, ?, ?)";
            if ($stmt = mysqli_prepare($link, $sql)) {
                mysqli_stmt_bind_param($stmt, "iiiss", $current_vehicle_id, $current_user_id, $mileage_reading, $log_date, $comment);
                if (mysqli_stmt_execute($stmt)) {
                    $message = "Mileage logged successfully for vehicle ID: " . $current_vehicle_id;
                    $message_type = 'success';
                } else {
                    $message = "Error logging mileage: " . mysqli_stmt_error($stmt);
                }
                mysqli_stmt_close($stmt);
            } else {
                $message = "Error preparing statement: " . mysqli_error($link);
            }
        } else {
            $message = "Invalid data provided for mileage log or no vehicle/user selected.";
        }
    }

    // --- Action: Log Maintenance ---
    elseif ($action == 'log_maintenance') {
        $maintenance_date = $_POST['maintenance_date'];
        $work_done = trim($_POST['work_done']);
        $cost = isset($_POST['maintenance_cost']) && $_POST['maintenance_cost'] !== '' ? floatval($_POST['maintenance_cost']) : null;

        if (!empty($maintenance_date) && !empty($work_done) && $current_vehicle_id && $current_user_id) {
            $sql = "INSERT INTO maintenance_logs (vehicle_id, user_id, maintenance_date, work_done, cost) VALUES (?, ?, ?, ?, ?)";
            if ($stmt = mysqli_prepare($link, $sql)) {
                mysqli_stmt_bind_param($stmt, "iissd", $current_vehicle_id, $current_user_id, $maintenance_date, $work_done, $cost);
                if (mysqli_stmt_execute($stmt)) {
                    $message = "Maintenance logged successfully for vehicle ID: " . $current_vehicle_id;
                    $message_type = 'success';
                } else {
                    $message = "Error logging maintenance: " . mysqli_stmt_error($stmt);
                }
                mysqli_stmt_close($stmt);
            } else {
                $message = "Error preparing statement: " . mysqli_error($link);
            }
        } else {
            $message = "Maintenance date, work done details, or vehicle/user not selected.";
        }
    }

    // --- Action: Log Fuel ---
    elseif ($action == 'log_fuel') {
        $purchase_date = $_POST['purchase_date'];
        $volume = isset($_POST['volume']) ? floatval($_POST['volume']) : 0;
        $fuel_cost = isset($_POST['fuel_cost']) ? floatval($_POST['fuel_cost']) : 0;
        $odometer_reading = isset($_POST['odometer_reading']) && $_POST['odometer_reading'] !== '' ? intval($_POST['odometer_reading']) : null;

        if (!empty($purchase_date) && $volume > 0 && $fuel_cost >= 0 && $current_vehicle_id && $current_user_id) {
            $sql = "INSERT INTO fuel_logs (vehicle_id, user_id, purchase_date, volume, cost, odometer_reading) VALUES (?, ?, ?, ?, ?, ?)";
            if ($stmt = mysqli_prepare($link, $sql)) {
                mysqli_stmt_bind_param($stmt, "iisddi", $current_vehicle_id, $current_user_id, $purchase_date, $volume, $fuel_cost, $odometer_reading);
                if (mysqli_stmt_execute($stmt)) {
                    $message = "Fuel purchase logged successfully for vehicle ID: " . $current_vehicle_id;
                    $message_type = 'success';
                } else {
                    $message = "Error logging fuel purchase: " . mysqli_stmt_error($stmt);
                }
                mysqli_stmt_close($stmt);
            } else {
                $message = "Error preparing statement: " . mysqli_error($link);
            }
        } else {
            $message = "Purchase date, volume, cost, or vehicle/user not selected are required for fuel log.";
        }
    }

    // --- Action: Add Vehicle ---
    elseif ($action == 'add_vehicle') {
        $make = trim($_POST['make']);
        $model = trim($_POST['model']);
        $plate_number = trim($_POST['plate_number']);
        $vehicle_type = trim($_POST['vehicle_type']);

        if (!empty($make) && !empty($model) && !empty($plate_number) && $current_user_id) {
            // Check if plate number already exists for this user
            $check_sql = "SELECT id FROM vehicles WHERE plate_number = ? AND user_id = ?";
            if ($stmt_check = mysqli_prepare($link, $check_sql)) {
                mysqli_stmt_bind_param($stmt_check, "si", $plate_number, $current_user_id);
                mysqli_stmt_execute($stmt_check);
                mysqli_stmt_store_result($stmt_check);
                if (mysqli_stmt_num_rows($stmt_check) > 0) {
                    $message = "Error: Vehicle with this plate number already exists for your account.";
                    $message_type = 'error';
                } else {
                    $insert_sql = "INSERT INTO vehicles (make, model, plate_number, vehicle_type, user_id) VALUES (?, ?, ?, ?, ?)";
                    if ($stmt = mysqli_prepare($link, $insert_sql)) {
                        mysqli_stmt_bind_param($stmt, "ssssi", $make, $model, $plate_number, $vehicle_type, $current_user_id);
                        if (mysqli_stmt_execute($stmt)) {
                            $new_vehicle_id = mysqli_insert_id($link); // Get the ID of the newly inserted vehicle
                            initialize_vehicle_items($link, $new_vehicle_id, $current_user_id); // Initialize default items for the new vehicle
                            $message = "Vehicle '{$make} {$model}' ({$plate_number}) added successfully! Default items initialized.";
                            $message_type = 'success';
                            // Automatically select this new vehicle
                            $_SESSION['selected_vehicle_id'] = $new_vehicle_id;
                            $_SESSION['selected_vehicle_name'] = $make . ' ' . $model . ' (' . $plate_number . ')';

                        } else {
                            $message = "Error adding vehicle: " . mysqli_stmt_error($stmt);
                        }
                        mysqli_stmt_close($stmt);
                    } else {
                        $message = "Error preparing statement: " . mysqli_error($link);
                    }
                }
                mysqli_stmt_close($stmt_check);
            } else {
                $message = "Error preparing check statement: " . mysqli_error($link);
            }
        } else {
            $message = "Make, Model, Plate Number, and User are required to add a vehicle.";
        }
        $redirect_url = 'add_car.php'; // Redirect back to add car page or dashboard
    }

    // --- Action: Set Selected Vehicle ---
    elseif ($action == 'set_vehicle') {
        $selected_id = isset($_POST['vehicle_id']) ? intval($_POST['vehicle_id']) : null;

        if ($selected_id && $current_user_id) {
            // Ensure the selected vehicle belongs to the current user
            $sql = "SELECT make, model, plate_number FROM vehicles WHERE id = ? AND user_id = ?";
            if ($stmt = mysqli_prepare($link, $sql)) {
                mysqli_stmt_bind_param($stmt, "ii", $selected_id, $current_user_id);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_bind_result($stmt, $make, $model, $plate_number);
                if (mysqli_stmt_fetch($stmt)) {
                    $_SESSION['selected_vehicle_id'] = $selected_id;
                    $_SESSION['selected_vehicle_name'] = $make . ' ' . $model . ' (' . $plate_number . ')';
                    $message = "Vehicle set to: " . $_SESSION['selected_vehicle_name'];
                    $message_type = 'success';
                } else {
                    $message = "Error: Selected vehicle not found or does not belong to your account.";
                }
                mysqli_stmt_close($stmt);
            } else {
                $message = "Error preparing statement: " . mysqli_error($link);
            }
        } else {
            $message = "No vehicle selected or no user logged in.";
        }
        $redirect_url = 'index.php'; // Always redirect to dashboard after setting vehicle
    }

    // --- Action: Add Electricity Meter ---
    elseif ($action == 'add_meter') {
        $meter_number = trim($_POST['meter_number']);
        $description = trim($_POST['description']);

        if (!empty($meter_number) && $current_user_id) {
            // Check if meter number already exists for this user
            $check_sql = "SELECT id FROM electricity_meters WHERE meter_number = ? AND user_id = ?";
            if ($stmt_check = mysqli_prepare($link, $check_sql)) {
                mysqli_stmt_bind_param($stmt_check, "si", $meter_number, $current_user_id);
                mysqli_stmt_execute($stmt_check);
                mysqli_stmt_store_result($stmt_check);
                if (mysqli_stmt_num_rows($stmt_check) > 0) {
                    $message = "Error: Electricity meter with this number already exists for your account.";
                    $message_type = 'error';
                } else {
                    $insert_sql = "INSERT INTO electricity_meters (meter_number, description, user_id) VALUES (?, ?, ?)";
                    if ($stmt = mysqli_prepare($link, $insert_sql)) {
                        mysqli_stmt_bind_param($stmt, "ssi", $meter_number, $description, $current_user_id);
                        if (mysqli_stmt_execute($stmt)) {
                            $message = "Electricity meter '{$meter_number}' added successfully!";
                            $message_type = 'success';
                        } else {
                            $message = "Error adding electricity meter: " . mysqli_stmt_error($stmt);
                        }
                        mysqli_stmt_close($stmt);
                    } else {
                        $message = "Error preparing statement: " . mysqli_error($link);
                    }
                }
                mysqli_stmt_close($stmt_check);
            } else {
                $message = "Error preparing check statement: " . mysqli_error($link);
            }
        } else {
            $message = "Meter Number and User are required to add an electricity meter.";
        }
        $redirect_url = 'manage_meters.php'; // Redirect back to manage meters page
    }

    // --- Action: Log Electricity ---
    elseif ($action == 'log_electricity') {
        $meter_id = isset($_POST['meter_id']) ? intval($_POST['meter_id']) : 0;
        $purchase_date = $_POST['electricity_purchase_date'];
        $units_purchased = isset($_POST['units_purchased']) ? floatval($_POST['units_purchased']) : 0;
        $cost = isset($_POST['electricity_cost']) ? floatval($_POST['electricity_cost']) : 0;
        $notes = isset($_POST['electricity_notes']) ? trim($_POST['electricity_notes']) : null;

        if ($meter_id > 0 && !empty($purchase_date) && $units_purchased > 0 && $cost >= 0 && $current_user_id) {
            // Ensure the meter belongs to the current user
            $check_meter_sql = "SELECT id FROM electricity_meters WHERE id = ? AND user_id = ?";
            if ($stmt_check_meter = mysqli_prepare($link, $check_meter_sql)) {
                mysqli_stmt_bind_param($stmt_check_meter, "ii", $meter_id, $current_user_id);
                mysqli_stmt_execute($stmt_check_meter);
                mysqli_stmt_store_result($stmt_check_meter);
                if (mysqli_stmt_num_rows($stmt_check_meter) == 0) {
                    $message = "Error: Selected meter not found or does not belong to your account.";
                    $message_type = 'error';
                } else {
                    $sql = "INSERT INTO electricity_logs (meter_id, purchase_date, units_purchased, cost, notes) VALUES (?, ?, ?, ?, ?)";
                    if ($stmt = mysqli_prepare($link, $sql)) {
                        mysqli_stmt_bind_param($stmt, "isdds", $meter_id, $purchase_date, $units_purchased, $cost, $notes);
                        if (mysqli_stmt_execute($stmt)) {
                            $message = "Electricity purchase logged successfully for meter ID: " . $meter_id;
                            $message_type = 'success';
                        } else {
                            $message = "Error logging electricity purchase: " . mysqli_stmt_error($stmt);
                        }
                        mysqli_stmt_close($stmt);
                    } else {
                        $message = "Error preparing statement: " . mysqli_error($link);
                    }
                }
                mysqli_stmt_close($stmt_check_meter);
            } else {
                $message = "Error preparing meter check statement: " . mysqli_error($link);
            }
        } else {
            $message = "Meter, purchase date, units, cost, and user are required for electricity log.";
        }
        $redirect_url = 'index.php'; // Redirect to dashboard, or review if a dedicated page is made
    }

    // --- Action: Log Cooking Gas ---
    elseif ($action == 'log_cooking_gas') {
        $purchase_date = $_POST['gas_purchase_date'];
        $location = trim($_POST['gas_location']);
        $quantity = isset($_POST['gas_quantity']) ? floatval($_POST['gas_quantity']) : 0;
        $cost = isset($_POST['gas_cost']) ? floatval($_POST['gas_cost']) : 0;
        $notes = isset($_POST['gas_notes']) ? trim($_POST['gas_notes']) : null;

        if (!empty($purchase_date) && !empty($location) && $quantity > 0 && $cost >= 0 && $current_user_id) {
            $sql = "INSERT INTO cooking_gas_logs (user_id, purchase_date, location, quantity, cost, notes) VALUES (?, ?, ?, ?, ?, ?)";
            if ($stmt = mysqli_prepare($link, $sql)) {
                mysqli_stmt_bind_param($stmt, "isssds", $current_user_id, $purchase_date, $location, $quantity, $cost, $notes);
                if (mysqli_stmt_execute($stmt)) {
                    $message = "Cooking gas purchase logged successfully!";
                    $message_type = 'success';
                } else {
                    $message = "Error logging cooking gas purchase: " . mysqli_stmt_error($stmt);
                }
                mysqli_stmt_close($stmt);
            } else {
                $message = "Error preparing statement: " . mysqli_error($link);
            }
        } else {
            $message = "Purchase date, location, quantity, cost, and user are required for cooking gas log.";
        }
        $redirect_url = 'index.php'; // Or review.php if logs are primarily viewed there
    }

    // --- Action: Delete Item (Renewal Type) ---
    elseif ($action == 'delete_item') {
        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        if ($id > 0 && $current_vehicle_id && $current_user_id) {
            $sql = "DELETE FROM items WHERE id = ? AND vehicle_id = ? AND user_id = ?";
            if ($stmt = mysqli_prepare($link, $sql)) {
                mysqli_stmt_bind_param($stmt, "iii", $id, $current_vehicle_id, $current_user_id);
                if (mysqli_stmt_execute($stmt)) {
                    $message = "Item (Renewal) deleted successfully.";
                    $message_type = 'success';
                } else {
                    $message = "Error deleting item: " . mysqli_stmt_error($stmt);
                }
                mysqli_stmt_close($stmt);
            } else {
                $message = "Error preparing statement: " . mysqli_error($link);
            }
        } else {
            $message = "Invalid item ID, no vehicle selected, or no user selected for deletion.";
        }
        $redirect_url = 'review.php'; // Redirect back to review page after deletion
    }

    // --- Action: Delete Mileage Log ---
    elseif ($action == 'delete_mileage') {
        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        if ($id > 0 && $current_vehicle_id && $current_user_id) {
            $sql = "DELETE FROM mileage_logs WHERE id = ? AND vehicle_id = ? AND user_id = ?";
            if ($stmt = mysqli_prepare($link, $sql)) {
                mysqli_stmt_bind_param($stmt, "iii", $id, $current_vehicle_id, $current_user_id);
                if (mysqli_stmt_execute($stmt)) {
                    $message = "Mileage log deleted successfully.";
                    $message_type = 'success';
                } else {
                    $message = "Error deleting mileage log: " . mysqli_stmt_error($stmt);
                }
                mysqli_stmt_close($stmt);
            } else {
                $message = "Error preparing statement: " . mysqli_error($link);
            }
        } else {
            $message = "Invalid mileage log ID, no vehicle selected, or no user selected for deletion.";
        }
        $redirect_url = 'review.php';
    }

    // --- Action: Delete Maintenance Log ---
    elseif ($action == 'delete_maintenance') {
        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        if ($id > 0 && $current_vehicle_id && $current_user_id) {
            $sql = "DELETE FROM maintenance_logs WHERE id = ? AND vehicle_id = ? AND user_id = ?";
            if ($stmt = mysqli_prepare($link, $sql)) {
                mysqli_stmt_bind_param($stmt, "iii", $id, $current_vehicle_id, $current_user_id);
                if (mysqli_stmt_execute($stmt)) {
                    $message = "Maintenance log deleted successfully.";
                    $message_type = 'success';
                } else {
                    $message = "Error deleting maintenance log: " . mysqli_stmt_error($stmt);
                }
                mysqli_stmt_close($stmt);
            } else {
                $message = "Error preparing statement: " . mysqli_error($link);
            }
        } else {
            $message = "Invalid maintenance log ID, no vehicle selected, or no user selected for deletion.";
        }
        $redirect_url = 'review.php';
    }

    // --- Action: Delete Fuel Log ---
    elseif ($action == 'delete_fuel') {
        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        if ($id > 0 && $current_vehicle_id && $current_user_id) {
            $sql = "DELETE FROM fuel_logs WHERE id = ? AND vehicle_id = ? AND user_id = ?";
            if ($stmt = mysqli_prepare($link, $sql)) {
                mysqli_stmt_bind_param($stmt, "iii", $id, $current_vehicle_id, $current_user_id);
                if (mysqli_stmt_execute($stmt)) {
                    $message = "Fuel log deleted successfully.";
                    $message_type = 'success';
                } else {
                    $message = "Error deleting fuel log: " . mysqli_stmt_error($stmt);
                }
                mysqli_stmt_close($stmt);
            } else {
                $message = "Error preparing statement: " . mysqli_error($link);
            }
        } else {
            $message = "Invalid fuel log ID, no vehicle selected, or no user selected for deletion.";
        }
        $redirect_url = 'review.php';
    }

    // --- Action: Delete Electricity Log ---
    elseif ($action == 'delete_electricity') {
        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        if ($id > 0 && $current_user_id) {
            // Ensure the electricity log's meter belongs to the current user
            $sql = "DELETE el FROM electricity_logs el
                    JOIN electricity_meters em ON el.meter_id = em.id
                    WHERE el.id = ? AND em.user_id = ?";
            if ($stmt = mysqli_prepare($link, $sql)) {
                mysqli_stmt_bind_param($stmt, "ii", $id, $current_user_id);
                if (mysqli_stmt_execute($stmt)) {
                    $message = "Electricity log deleted successfully.";
                    $message_type = 'success';
                } else {
                    $message = "Error deleting electricity log: " . mysqli_stmt_error($stmt);
                }
                mysqli_stmt_close($stmt);
            } else {
                $message = "Error preparing statement: " . mysqli_error($link);
            }
        } else {
            $message = "Invalid electricity log ID or no user selected for deletion.";
        }
        $redirect_url = 'review.php'; // Redirect back to review page after deletion
    }

    // --- Action: Delete Cooking Gas Log ---
    elseif ($action == 'delete_cooking_gas') {
        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        if ($id > 0 && $current_user_id) {
            $sql = "DELETE FROM cooking_gas_logs WHERE id = ? AND user_id = ?";
            if ($stmt = mysqli_prepare($link, $sql)) {
                mysqli_stmt_bind_param($stmt, "ii", $id, $current_user_id);
                if (mysqli_stmt_execute($stmt)) {
                    $message = "Cooking gas log deleted successfully.";
                    $message_type = 'success';
                } else {
                    $message = "Error deleting cooking gas log: " . mysqli_stmt_error($stmt);
                }
                mysqli_stmt_close($stmt);
            } else {
                $message = "Error preparing statement: " . mysqli_error($link);
            }
        } else {
            $message = "Invalid cooking gas log ID or no user selected for deletion.";
        }
        $redirect_url = 'review.php'; // Redirect back to review page after deletion
    }


    // --- Action: Delete Vehicle ---
    elseif ($action == 'delete_vehicle') {
        $id = isset($_POST['vehicle_id']) ? intval($_POST['vehicle_id']) : 0;

        if ($id > 0 && $current_user_id) {
            // Delete associated items first (due to foreign key constraints if not CASCADE)
            // Or rely on CASCADE DELETE as defined in SQL schema
            $delete_sql = "DELETE FROM vehicles WHERE id = ? AND user_id = ?";
            if ($stmt = mysqli_prepare($link, $delete_sql)) {
                mysqli_stmt_bind_param($stmt, "ii", $id, $current_user_id);
                if (mysqli_stmt_execute($stmt)) {
                    $message = "Vehicle and all associated records deleted successfully.";
                    $message_type = 'success';
                    // If the deleted vehicle was the currently selected one, clear session
                    if (isset($_SESSION['selected_vehicle_id']) && $_SESSION['selected_vehicle_id'] == $id) {
                        unset($_SESSION['selected_vehicle_id']);
                        unset($_SESSION['selected_vehicle_name']);
                        $redirect_url = 'index.php'; // Redirect to dashboard to force vehicle selection
                    }
                } else {
                    $message = "Error deleting vehicle: " . mysqli_stmt_error($stmt);
                }
                mysqli_stmt_close($stmt);
            } else {
                $message = "Error preparing delete vehicle statement: " . mysqli_error($link);
            }
        } else {
            $message = "Invalid vehicle ID or no user selected for deletion.";
        }
        $redirect_url = 'add_car.php'; // Typically redirect back to add/manage car page
    }

    // --- Action: Delete Meter ---
    elseif ($action == 'delete_meter') {
        $id = isset($_POST['meter_id']) ? intval($_POST['meter_id']) : 0;

        if ($id > 0 && $current_user_id) {
            $delete_sql = "DELETE FROM electricity_meters WHERE id = ? AND user_id = ?";
            if ($stmt = mysqli_prepare($link, $delete_sql)) {
                mysqli_stmt_bind_param($stmt, "ii", $id, $current_user_id);
                if (mysqli_stmt_execute($stmt)) {
                    $message = "Electricity meter and all associated logs deleted successfully.";
                    $message_type = 'success';
                } else {
                    $message = "Error deleting electricity meter: " . mysqli_stmt_error($stmt);
                }
                mysqli_stmt_close($stmt);
            } else {
                $message = "Error preparing delete meter statement: " . mysqli_error($link);
            }
        } else {
            $message = "Invalid meter ID or no user selected for deletion.";
        }
        $redirect_url = 'manage_meters.php'; // Redirect back to manage meters page
    }

    // --- Action: Logout ---
    elseif ($action == 'logout') {
        // Unset all of the session variables
        $_SESSION = array();

        // Destroy the session.
        session_destroy();

        // Redirect to login page
        $redirect_url = 'login.php';
        $message = "You have been logged out successfully.";
        $message_type = 'success';
    }

    // --- Unknown action ---
    else {
        $message = "Unknown action specified. Action received: " . htmlspecialchars($action); // Added for debugging
    }
} else {
    // If not a POST request or no action, just redirect or show an error
    $message = "Invalid request method or no action specified.";
}

// --- Store message in session and redirect ---
// Add message type to distinguish in the display
$_SESSION['message'] = "<p class='" . $message_type . "'>" . htmlspecialchars($message) . "</p>";

// Close database connection before redirecting
mysqli_close($link);

header("Location: " . $redirect_url);
exit();
?>
