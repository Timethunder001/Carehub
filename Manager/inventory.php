<?php
// Enable error reporting at the VERY TOP
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once '../config.php';

// Check database connection
if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}

// Check if user is logged in and is a manager
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'manager') {
    header("Location: ../login.php");
    exit();
}

// Display success/error messages
$success_message = '';
$error_message = '';

if (isset($_SESSION['success'])) {
    $success_message = $_SESSION['success'];
    unset($_SESSION['success']);
}

if (isset($_SESSION['error'])) {
    $error_message = $_SESSION['error'];
    unset($_SESSION['error']);
}

// Get manager data
$user_id = $_SESSION['user_id'];
$manager_query = $conn->prepare("SELECT name FROM users WHERE id = ?");
$manager_query->bind_param("i", $user_id);
$manager_query->execute();
$manager_result = $manager_query->get_result();
$manager = $manager_result->fetch_assoc();

// Initialize default inventory items
$default_inventory = [
    ['item_name' => 'Water', 'current_stock' => 70, 'max_stock' => 100],
    ['item_name' => 'Food', 'current_stock' => 45, 'max_stock' => 100],
    ['item_name' => 'Meds', 'current_stock' => 85, 'max_stock' => 100]
];

// Get current inventory levels
$inventory_items = [];
try {
    $inventory_query = $conn->query("SELECT * FROM inventory ORDER BY item_name");
    if ($inventory_query) {
        while ($row = $inventory_query->fetch_assoc()) {
            $inventory_items[] = $row;
        }
    }
    
    // If no inventory data exists, use defaults
    if (empty($inventory_items)) {
        $inventory_items = $default_inventory;
    }
} catch (Exception $e) {
    // If table doesn't exist, use default data
    $inventory_items = $default_inventory;
}

// Get restock history
$restock_history = [];
try {
    $history_query = $conn->query("
        SELECT rh.*, u.name as restocked_by_name 
        FROM restock_history rh 
        LEFT JOIN users u ON rh.restocked_by = u.id 
        ORDER BY rh.restock_date DESC 
        LIMIT 10
    ");
    if ($history_query) {
        while ($row = $history_query->fetch_assoc()) {
            $restock_history[] = $row;
        }
    }
} catch (Exception $e) {
    // If table doesn't exist, empty history
    $restock_history = [];
}

// Handle inventory update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_inventory'])) {
    $item_name = $conn->real_escape_string($_POST['item_name']);
    $new_stock = intval($_POST['new_stock']);
    $max_stock = intval($_POST['max_stock']);
    
    try {
        // Check if inventory table exists and create it if not
        $table_check = $conn->query("SHOW TABLES LIKE 'inventory'");
        if (!$table_check || $table_check->num_rows == 0) {
            // Create inventory table
            $conn->query("CREATE TABLE inventory (
                id INT AUTO_INCREMENT PRIMARY KEY,
                item_name VARCHAR(50) NOT NULL,
                current_stock INT NOT NULL,
                max_stock INT NOT NULL,
                last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                updated_by INT
            )");
        }
        
        // Check if restock_history table exists and create it if not
        $history_check = $conn->query("SHOW TABLES LIKE 'restock_history'");
        if (!$history_check || $history_check->num_rows == 0) {
            // Create restock_history table
            $conn->query("CREATE TABLE restock_history (
                id INT AUTO_INCREMENT PRIMARY KEY,
                item_name VARCHAR(50) NOT NULL,
                quantity INT NOT NULL,
                restocked_by INT,
                restock_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                status VARCHAR(20) DEFAULT 'completed'
            )");
        }
        
        // Check if item exists in inventory
        $check_query = $conn->query("SELECT id FROM inventory WHERE item_name = '$item_name'");
        
        if ($check_query && $check_query->num_rows > 0) {
            // Update existing item
            $update_query = $conn->query("UPDATE inventory SET current_stock = $new_stock, max_stock = $max_stock, updated_by = $user_id WHERE item_name = '$item_name'");
        } else {
            // Insert new item
            $update_query = $conn->query("INSERT INTO inventory (item_name, current_stock, max_stock, updated_by) VALUES ('$item_name', $new_stock, $max_stock, $user_id)");
        }
        
        if ($update_query) {
            // Add to restock history
            $conn->query("INSERT INTO restock_history (item_name, quantity, restocked_by) VALUES ('$item_name', $new_stock, $user_id)");
            
            $_SESSION['success'] = "Inventory updated successfully for $item_name!";
        } else {
            $_SESSION['error'] = "Failed to update inventory!";
        }
    } catch (Exception $e) {
        $_SESSION['error'] = "Database error: " . $e->getMessage();
    }
    
    header("Location: inventory.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CareHub - Inventory</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
        }

        .modal-content {
            background-color: white;
            margin: 10% auto;
            padding: 30px;
            border-radius: 8px;
            width: 80%;
            max-width: 500px;
        }

        .close {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }

        .close:hover {
            color: black;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }

        .form-group input,
        .form-group select {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }

        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
        }

        .btn-primary {
            background: #00A9FF;
            color: white;
        }

        .btn-secondary {
            background: #6c757d;
            color: white;
        }

        .table-footer {
            padding: 20px;
            text-align: center;
            border-top: 1px solid #eee;
        }

        .inventory-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
        }

        .chart-box {
            grid-column: 1;
        }

        .table-box {
            grid-column: 2;
        }

        @media (max-width: 1024px) {
            .inventory-row {
                grid-template-columns: 1fr;
            }
            .chart-box, .table-box {
                grid-column: 1;
            }
        }
        
        .status.completed {
            background: #d4edda;
            color: #155724;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="container">
        <aside class="sidebar">
            <div class="logo">
                <svg width="34" height="34" viewBox="0 0 34 34" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M17 0L19.9829 14.0171L34 17L19.9829 19.9829L17 34L14.0171 19.9829L0 17L14.0171 14.0171L17 0Z" fill="white"/><path d="M17 9.13158L18.6095 15.3905L24.8684 17L18.6095 18.6095L17 24.8684L15.3905 18.6095L9.13158 17L15.3905 15.3905L17 9.13158Z" fill="#00A9FF"/></svg>
                <h1>CareHub</h1>
            </div>
            <nav>
                <ul>
                    <li><a href="dashboard.php">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M3 13H11V3H3V13ZM3 21H11V15H3V21ZM13 21H21V11H13V21ZM13 3V9H21V3H13Z" stroke="#333" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        Dashboard
                    </a></li>
                    <li><a href="assign-staff.php">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M17 21V19C17 17.9391 16.5786 16.9217 15.8284 16.1716C15.0783 15.4214 14.0609 15 13 15H5C3.93913 15 2.92172 15.4214 2.17157 16.1716C1.42143 16.9217 1 17.9391 1 19V21M17 7C17 9.20914 15.2091 11 13 11C10.7909 11 9 9.20914 9 7C9 4.79086 10.7909 3 13 3C15.2091 3 17 4.79086 17 7ZM23 21V19C22.9992 18.2323 22.7551 17.4842 22.3023 16.8525C21.8496 16.2208 21.2111 15.7394 20.47 15.48M17 3C17.7946 3.23595 18.4114 3.65345 18.8995 4.19539C19.3876 4.73733 19.7283 5.38136 19.89 6.1" stroke="#333" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        Assign Staff
                    </a></li>
                    <li><a href="elderly-lists.php">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M17 21V19C17 17.9391 16.5786 16.9217 15.8284 16.1716C15.0783 15.4214 14.0609 15 13 15H5C3.93913 15 2.92172 15.4214 2.17157 16.1716C1.42143 16.9217 1 17.9391 1 19V21M17 7C17 9.20914 15.2091 11 13 11C10.7909 11 9 9.20914 9 7C9 4.79086 10.7909 3 13 3C15.2091 3 17 4.79086 17 7ZM23 21V19C22.9992 18.2323 22.7551 17.4842 22.3023 16.8525C21.8496 16.2208 21.2111 15.7394 20.47 15.48M17 3C17.7946 3.23595 18.4114 3.65345 18.8995 4.19539C19.3876 4.73733 19.7283 5.38136 19.89 6.1" stroke="#333" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        Elderly Lists
                    </a></li>
                    <li><a href="reports.php">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M11 4H4C3.46957 4 2.96086 4.21071 2.58579 4.58579C2.21071 4.96086 2 5.46957 2 6V20C2 20.5304 2.21071 21.0391 2.58579 21.4142C2.96086 21.7893 3.46957 22 4 22H18C18.5304 22 19.0391 21.7893 19.4142 21.4142C19.7893 21.0391 20 20.5304 20 20V13M18.5 2.5C18.8978 2.10218 19.4374 1.87868 20 1.87868C20.5626 1.87868 21.1022 2.10218 21.5 2.5C21.8978 2.89782 22.1213 3.43739 22.1213 4C22.1213 4.56261 21.8978 5.10218 21.5 5.5L12 15L8 16L9 12L18.5 2.5Z" stroke="#333" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        Reports
                    </a></li>
                    <li><a href="inventory.php" class="active">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M11 4H4C3.46957 4 2.96086 4.21071 2.58579 4.58579C2.21071 4.96086 2 5.46957 2 6V20C2 20.5304 2.21071 21.0391 2.58579 21.4142C2.96086 21.7893 3.46957 22 4 22H18C18.5304 22 19.0391 21.7893 19.4142 21.4142C19.7893 21.0391 20 20.5304 20 20V13M18.5 2.5C18.8978 2.10218 19.4374 1.87868 20 1.87868C20.5626 1.87868 21.1022 2.10218 21.5 2.5C21.8978 2.89782 22.1213 3.43739 22.1213 4C22.1213 4.56261 21.8978 5.10218 21.5 5.5L12 15L8 16L9 12L18.5 2.5Z" stroke="#333" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        Inventory
                    </a></li>
                </ul>
            </nav>
        </aside>
        <main class="main-content">
            <header class="header">
                <div class="header-title">
                    <p>Welcome back, <?php echo htmlspecialchars($manager['name']); ?>!</p>
                    <h1>Inventory</h1>
                </div>
                <div class="user-profile">
                    <div class="avatar"></div>
                    <div class="user-details">
                        <h3><?php echo htmlspecialchars($manager['name']); ?></h3>
                        <p>Manager</p>
                    </div>
                </div>
            </header>

            <!-- Success/Error Messages -->
            <?php if (!empty($success_message)): ?>
                <div class="alert alert-success" style="background: #d4edda; color: #155724; padding: 12px; border-radius: 4px; margin: 10px 20px; border: 1px solid #c3e6cb;">
                    <?php echo htmlspecialchars($success_message); ?>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($error_message)): ?>
                <div class="alert alert-error" style="background: #f8d7da; color: #721c24; padding: 12px; border-radius: 4px; margin: 10px 20px; border: 1px solid #f5c6cb;">
                    <?php echo htmlspecialchars($error_message); ?>
                </div>
            <?php endif; ?>

            <div class="content-wrapper">
                <div class="inventory-row">
                    <!-- Inventory Chart -->
                    <div class="inventory-card chart-box">
                        <h2 class="card-header">Inventory Levels</h2>
                        <div class="chart-container">
                            <canvas id="inventoryChart"></canvas>
                        </div>
                    </div>

                    <!-- Restock History Table -->
                    <div class="inventory-card table-box">
                        <h2 class="card-header">Restock History</h2>
                        <div class="table-wrapper">
                            <table class="schedule-table">
                                <thead>
                                    <tr>
                                        <th>Item</th>
                                        <th>Quantity</th>
                                        <th>Restocked By</th>
                                        <th>Date</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (count($restock_history) > 0): ?>
                                        <?php foreach ($restock_history as $history): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($history['item_name']); ?></td>
                                                <td><?php echo htmlspecialchars($history['quantity']); ?> <?php echo $history['item_name'] === 'Water' ? 'Litres' : ($history['item_name'] === 'Food' ? 'Packs' : 'Units'); ?></td>
                                                <td><?php echo htmlspecialchars($history['restocked_by_name'] ?? 'System'); ?></td>
                                                <td><?php echo date('d/m/Y', strtotime($history['restock_date'])); ?></td>
                                                <td class="status completed"><?php echo htmlspecialchars(ucfirst($history['status'])); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="5" style="text-align: center; color: #666;">No restock history found</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                        <div class="table-footer">
                            <button class="btn btn-primary" onclick="openUpdateModal()">Update Stock</button>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Update Inventory Modal -->
    <div id="updateModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeUpdateModal()">&times;</span>
            <h2>Update Inventory Stock</h2>
            <form id="updateForm" method="POST" action="inventory.php">
                <input type="hidden" name="update_inventory" value="1">
                <div class="form-group">
                    <label for="item_name">Item:</label>
                    <select id="item_name" name="item_name" required onchange="updateCurrentStock()">
                        <option value="">Select Item</option>
                        <option value="Water">Water</option>
                        <option value="Food">Food</option>
                        <option value="Meds">Meds</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="current_stock">Current Stock:</label>
                    <input type="number" id="current_stock" name="new_stock" min="0" max="1000" required>
                </div>
                <div class="form-group">
                    <label for="max_stock">Maximum Capacity:</label>
                    <input type="number" id="max_stock" name="max_stock" min="1" max="1000" value="100" required>
                </div>
                <div style="text-align: right;">
                    <button type="button" class="btn btn-secondary" onclick="closeUpdateModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Stock</button>
                </div>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        // Pass PHP data to JavaScript
        const inventoryData = <?php echo json_encode($inventory_items); ?>;

        // Initialize chart
        const ctx = document.getElementById('inventoryChart').getContext('2d');
        const inventoryChart = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: inventoryData.map(item => item.item_name),
                datasets: [{
                    label: 'Inventory Level (%)',
                    data: inventoryData.map(item => Math.round((item.current_stock / item.max_stock) * 100)),
                    backgroundColor: ['#00A9FF', '#4CAF50', '#FF9800']
                }]
            },
            options: {
                indexAxis: 'y',
                scales: {
                    x: {
                        min: 0,
                        max: 100,
                        ticks: {
                            callback: function(value) {
                                return value + '%';
                            }
                        }
                    },
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });

        // Modal functions
        function openUpdateModal() {
            document.getElementById('updateModal').style.display = 'block';
        }

        function closeUpdateModal() {
            document.getElementById('updateModal').style.display = 'none';
            document.getElementById('updateForm').reset();
        }

        function updateCurrentStock() {
            const itemName = document.getElementById('item_name').value;
            const currentItem = inventoryData.find(item => item.item_name === itemName);
            
            if (currentItem) {
                document.getElementById('current_stock').value = currentItem.current_stock;
                document.getElementById('max_stock').value = currentItem.max_stock;
            } else {
                document.getElementById('current_stock').value = '';
                document.getElementById('max_stock').value = 100;
            }
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('updateModal');
            if (event.target === modal) {
                closeUpdateModal();
            }
        }
    </script>
</body>
</html>
<?php $conn->close(); ?>