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

// Get all carers (users with role 'carer') from database
$carers = [];
$carer_query = $conn->query("SELECT id, name FROM users WHERE roles = 'carer' ORDER BY name");
if ($carer_query) {
    while ($row = $carer_query->fetch_assoc()) {
        $carers[] = $row;
    }
} else {
    $carers = [];
}

// Get all elderly members (users with role 'member') from database
$elderly_members = [];
$elderly_query = $conn->query("SELECT id, name FROM users WHERE roles = 'member' ORDER BY name");
if ($elderly_query) {
    while ($row = $elderly_query->fetch_assoc()) {
        $elderly_members[] = $row;
    }
} else {
    $elderly_members = [];
}

// Get assignments for each carer
$carer_assignments = [];
foreach ($carers as $carer) {
    $carer_id = $carer['id'];
    $assignment_query = $conn->query("
        SELECT a.*, u_elderly.name as elderly_name, e.room_number 
        FROM assignments a 
        LEFT JOIN users u_elderly ON a.elderly_id = u_elderly.id 
        LEFT JOIN elderly e ON a.elderly_id = e.id 
        WHERE a.carer_id = $carer_id 
        ORDER BY a.start_date DESC
    ");
    
    $assignments = [];
    if ($assignment_query) {
        while ($row = $assignment_query->fetch_assoc()) {
            $assignments[] = $row;
        }
    }
    
    $carer_assignments[$carer_id] = $assignments;
}

// Count elderly from users table (role = 'member')
$total_elderly = count($elderly_members);
$total_carers = count($carers);

// Count assignments
$assigned_carers_query = $conn->query("
    SELECT COUNT(DISTINCT carer_id) as assigned_count 
    FROM assignments 
    WHERE carer_id IS NOT NULL
");
$assigned_carers = $assigned_carers_query ? $assigned_carers_query->fetch_assoc()['assigned_count'] : 0;
$unassigned_carers = $total_carers - $assigned_carers;

// Count assigned elderly
$assigned_elderly_query = $conn->query("
    SELECT COUNT(DISTINCT elderly_id) as assigned_count 
    FROM assignments 
    WHERE elderly_id IS NOT NULL
");
$assigned_elderly = $assigned_elderly_query ? $assigned_elderly_query->fetch_assoc()['assigned_count'] : 0;
$unassigned_elderly = $total_elderly - $assigned_elderly;

// Get reports count
$reports_count = 0; // Hardcoded to 0 since carer reporting not implemented yet

// Get inventory status
$inventory_status = "High"; // Default to High
try {
    $inventory_query = $conn->query("SELECT * FROM inventory");
    if ($inventory_query && $inventory_query->num_rows > 0) {
        $has_low_stock = false;
        while ($item = $inventory_query->fetch_assoc()) {
            $percentage = ($item['current_stock'] / $item['max_stock']) * 100;
            if ($percentage < 50) {
                $has_low_stock = true;
                break;
            }
        }
        $inventory_status = $has_low_stock ? "Low" : "High";
    } else {
        // If no inventory data, check if table exists
        $table_check = $conn->query("SHOW TABLES LIKE 'inventory'");
        if (!$table_check || $table_check->num_rows == 0) {
            $inventory_status = "Low"; // Default to Low if no inventory system
        }
    }
} catch (Exception $e) {
    $inventory_status = "Low"; // Default to Low if error
}

// Handle form submission for new assignment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['assign_carer'])) {
    $elderly_id = intval($_POST['elderly_id']);
    $carer_id = intval($_POST['carer_id']);
    $location = $conn->real_escape_string($_POST['location']);
    
    // Process the date range from the form
    $date_range = $_POST['date_range'];
    $dates = explode(' to ', $date_range);
    
    if (count($dates) === 2) {
        // Convert dates from d/m/Y to Y-m-d format
        $start_date = DateTime::createFromFormat('d/m/Y', trim($dates[0]))->format('Y-m-d');
        $end_date = DateTime::createFromFormat('d/m/Y', trim($dates[1]))->format('Y-m-d');
    } else {
        $_SESSION['error'] = "Please select a valid date range!";
        header("Location: dashboard.php");
        exit();
    }
    
    // Get carer and elderly names for messages
    $carer_name_query = $conn->query("SELECT name FROM users WHERE id = $carer_id");
    $carer_name = $carer_name_query ? $carer_name_query->fetch_assoc()['name'] : 'Unknown';
    
    $elderly_name_query = $conn->query("SELECT name FROM users WHERE id = $elderly_id");
    $elderly_name = $elderly_name_query ? $elderly_name_query->fetch_assoc()['name'] : 'Unknown';
    
    // Check if elderly exists in elderly table, if not create entry
    $elderly_check = $conn->query("SELECT id FROM elderly WHERE id = $elderly_id");
    if (!$elderly_check || $elderly_check->num_rows == 0) {
        $conn->query("INSERT INTO elderly (id, name, room_number) VALUES ($elderly_id, '$elderly_name', '$location')");
    } else {
        $conn->query("UPDATE elderly SET room_number = '$location' WHERE id = $elderly_id");
    }
    
    // Check for duplicate assignment
    $duplicate_check = $conn->query("
        SELECT id FROM assignments 
        WHERE carer_id = $carer_id 
        AND elderly_id = $elderly_id
        AND (
            (start_date BETWEEN '$start_date' AND '$end_date') OR 
            (end_date BETWEEN '$start_date' AND '$end_date') OR
            ('$start_date' BETWEEN start_date AND end_date) OR
            ('$end_date' BETWEEN start_date AND end_date)
        )
    ");
    
    if ($duplicate_check && $duplicate_check->num_rows > 0) {
        $_SESSION['error'] = "This carer is already assigned to this elderly during the selected dates!";
        header("Location: dashboard.php");
        exit();
    }
    
    // Create assignment
    if ($conn->query("INSERT INTO assignments (elderly_id, carer_id, start_date, end_date) 
                  VALUES ($elderly_id, $carer_id, '$start_date', '$end_date')")) {
        $conn->query("UPDATE elderly SET carer_id = $carer_id WHERE id = $elderly_id");
        $_SESSION['success'] = "Assignment created successfully! $carer_name assigned to $elderly_name";
    } else {
        $_SESSION['error'] = "Failed to create assignment!";
    }
    
    header("Location: dashboard.php");
    exit();
}

// Handle unassign action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['unassign_carer'])) {
    $assignment_id = intval($_POST['assignment_id']);
    
    // Get assignment details for message
    $assignment_query = $conn->query("
        SELECT a.*, u_carer.name as carer_name, u_elderly.name as elderly_name 
        FROM assignments a 
        LEFT JOIN users u_carer ON a.carer_id = u_carer.id 
        LEFT JOIN users u_elderly ON a.elderly_id = u_elderly.id 
        WHERE a.id = $assignment_id
    ");
    
    if ($assignment_query && $assignment_query->num_rows > 0) {
        $assignment = $assignment_query->fetch_assoc();
        $carer_name = $assignment['carer_name'];
        $elderly_name = $assignment['elderly_name'];
        $elderly_id = $assignment['elderly_id'];
        
        // Delete the assignment
        if ($conn->query("DELETE FROM assignments WHERE id = $assignment_id")) {
            // Remove carer_id from elderly table
            $conn->query("UPDATE elderly SET carer_id = NULL WHERE id = $elderly_id");
            $_SESSION['success'] = "Assignment removed successfully! $carer_name unassigned from $elderly_name";
        } else {
            $_SESSION['error'] = "Failed to remove assignment!";
        }
    } else {
        $_SESSION['error'] = "Assignment not found!";
    }
    
    header("Location: dashboard.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CareHub - Dashboard</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>

        .chart-container {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            height: 100%;
        }
        
        .chart-legend-horizontal {
            display: flex;
            justify-content: center;
            gap: 20px;
            margin-top: 15px;
            width: 100%;
        }
        
        .legend-item {
            display: flex;
            align-items: center;
            font-size: 12px;
            font-family: 'Poppins', sans-serif;
            color: #333;
        }
        
        .legend-color {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            margin-right: 8px;
            display: inline-block;
        }
        
        .legend-assigned {
            background-color: #36A2EB;
        }
        
        .legend-unassigned {
            background-color: #FF6384;
        }
        
        .legend-elderly-assigned {
            background-color: #4BC0C0;
        }
        
        .legend-elderly-unassigned {
            background-color: #FF9F40;
        }
        
        .carer .content,
        .elderly .content {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            height: 100%;
            padding: 10px;
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
                    <li><a href="dashboard.php" class="active">
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
                    <li><a href="inventory.php">
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
                    <h1>Dashboard</h1>
                </div>
                <div class="user-profile">
                    <div class="avatar"></div>
                    <div class="user-details">
                        <h3><?php echo htmlspecialchars($manager['name']); ?></h3>
                        <p>Manager</p>
                    </div>
                </div>
            </header>
            <div class="content-wrapper">
                <div class="dashboard-grid">
                    <div class="card summary">
                        <h2 class="card-header">Summary</h2>
                        <div class="content">
                            <div class="details">
                                <div class="sumcontent1">
                                <img src="Pictures/SumImage.png" alt="First Icon">
                                <div class="sumtext">
                                <p>Reports</p>
                                <h2><?php echo $reports_count; ?></h2>
                                </div>
                                </div>
                                <div class="sumcontent2">
                                <img src="Pictures/SumImage2.png" alt="Second Icon">
                                <div class="sumtext">
                                <p>Inventory</p>
                                <h2><?php echo $inventory_status; ?></h2>
                                </div>
                                </div>
                                <div class="sumcontent3">
                                <img src="Pictures/SumImage3.png" alt="Third Icon">
                                <div class="sumtext">
                                <p>Unassign</p>
                                <h2 id="unassign-count"><?php echo $unassigned_carers; ?></h2>
                                </div>
                                </div>
                                <div class="sumcontent4">
                                <img src="Pictures/SumImage4.png" alt="Fouth Icon">
                                <div class="sumtext">
                                <p>Assigned</p>
                                <h2 id="assign-count"><?php echo $assigned_carers; ?></h2>
                                </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="card carer">
                        <h2 class="card-header">Carer</h2>
                        <div class="content">
                            <div class="chart-container">
                                <div class="chart-wrapper">
                                    <canvas id="carerPieChart"></canvas>
                                </div>

                                <div class="chart-legend-horizontal">
                                    <div class="legend-item">
                                        <span class="legend-color legend-assigned"></span>
                                        <span>Assigned</span>
                                    </div>
                                    <div class="legend-item">
                                        <span class="legend-color legend-unassigned"></span>
                                        <span>Unassigned</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="card elderly">
                        <h2 class="card-header">Elderly</h2>
                        <div class="content">
                            <div class="chart-container">
                                <div class="chart-wrapper">
                                    <canvas id="elderlyPieChart"></canvas>
                                </div>

                                <div class="chart-legend-horizontal">
                                    <div class="legend-item">
                                        <span class="legend-color legend-elderly-assigned"></span>
                                        <span>Assigned</span>
                                    </div>
                                    <div class="legend-item">
                                        <span class="legend-color legend-elderly-unassigned"></span>
                                        <span>Unassigned</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card schedule-card">
                        <h2 class="card-header">Carer Assignments</h2>
                        
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
                        
                        <div class="table-wrapper">
                            <table class="schedule-table">
                                <thead>
                                    <tr>
                                        <th>Carer Name</th>
                                        <th>Assigned To (Elderly)</th>
                                        <th>Location</th>
                                        <th>Date</th>
                                        <th>Status</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (count($carers) > 0): ?>
                                        <?php foreach ($carers as $carer): ?>
                                            <?php 
                                            $assignments = $carer_assignments[$carer['id']];
                                            if (count($assignments) > 0): ?>
                                                <!-- Show assignment details if carer has assignments -->
                                                <?php foreach ($assignments as $assignment): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($carer['name']); ?></td>
                                                    <td><?php echo htmlspecialchars($assignment['elderly_name']); ?></td>
                                                    <td><?php echo htmlspecialchars($assignment['room_number']); ?></td>
                                                    <td><?php echo date('d/m/Y', strtotime($assignment['start_date'])); ?> - <?php echo date('d/m/Y', strtotime($assignment['end_date'])); ?></td>
                                                    <td><span class="status-assigned">Assigned</span></td>
                                                    <td>
                                                        <form method="POST" action="dashboard.php" style="display: inline;">
                                                            <input type="hidden" name="assignment_id" value="<?php echo $assignment['id']; ?>">
                                                            <button type="submit" name="unassign_carer" class="unassign-btn" 
                                                                    style="background: #ff4444; color: white; border: none; padding: 6px 12px; border-radius: 4px; cursor: pointer;"
                                                                    onclick="return confirm('Are you sure you want to unassign <?php echo htmlspecialchars($carer['name']); ?> from <?php echo htmlspecialchars($assignment['elderly_name']); ?>?')">
                                                                Unassign
                                                            </button>
                                                        </form>
                                                    </td>
                                                </tr>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <!-- Show assignment form if carer has no assignments -->
                                                <tr>
                                                    <form method="POST" action="dashboard.php" class="assignment-form">
                                                        <td>
                                                            <strong><?php echo htmlspecialchars($carer['name']); ?></strong>
                                                            <input type="hidden" name="carer_id" value="<?php echo $carer['id']; ?>">
                                                        </td>
                                                        <td>
                                                            <select name="elderly_id" required class="form-select" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                                                                <option value="">Select Elderly</option>
                                                                <?php if (count($elderly_members) > 0): ?>
                                                                    <?php foreach ($elderly_members as $elderly): ?>
                                                                        <option value="<?php echo htmlspecialchars($elderly['id']); ?>"><?php echo htmlspecialchars($elderly['name']); ?></option>
                                                                    <?php endforeach; ?>
                                                                <?php else: ?>
                                                                    <option value="" disabled>No elderly members found</option>
                                                                <?php endif; ?>
                                                            </select>
                                                        </td>
                                                        <td>
                                                            <select name="location" required class="form-select" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                                                                <option value="">Select Room</option>
                                                                <?php for ($i = 101; $i <= 120; $i++): ?>
                                                                    <option value="Room <?php echo $i; ?>">Room <?php echo $i; ?></option>
                                                                <?php endfor; ?>
                                                            </select>
                                                        </td>
                                                        <td>
                                                            <input type="text" name="date_range" 
                                                                   placeholder="Select dates" required 
                                                                   class="form-input date-range-picker" 
                                                                   style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                                                        </td>
                                                        <td>
                                                            <span class="status-unassign">Ready to Assign</span>
                                                        </td>
                                                        <td>
                                                            <button type="submit" name="assign_carer" class="assign-btn" 
                                                                    style="background: #00A9FF; color: white; border: none; padding: 8px 16px; border-radius: 4px; cursor: pointer;">
                                                                Assign
                                                            </button>
                                                        </td>
                                                    </form>
                                                </tr>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="6" style="text-align: center; color: #666;">No carers found</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Pass PHP data to JavaScript for charts -->
    <script>
        // Carer chart data
        const carerChartData = {
            assigned: <?php echo $assigned_carers; ?>,
            unassigned: <?php echo $unassigned_carers; ?>
        };
        
        // Elderly chart data
        const elderlyChartData = {
            assigned: <?php echo $assigned_elderly; ?>,
            unassigned: <?php echo $unassigned_elderly; ?>
        };
    </script>

    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script>
        // Initialize date range pickers
        document.addEventListener('DOMContentLoaded', function() {
            flatpickr(".date-range-picker", {
                mode: "range",
                dateFormat: "d/m/Y",
                minDate: "today"
            });

            // Form validation
            document.querySelectorAll('.assignment-form').forEach(function(form) {
                form.addEventListener('submit', function(e) {
                    const elderlySelect = this.querySelector('select[name="elderly_id"]');
                    const locationSelect = this.querySelector('select[name="location"]');
                    const dateInput = this.querySelector('.date-range-picker');
                    
                    if (!elderlySelect.value || !locationSelect.value || !dateInput.value) {
                        e.preventDefault();
                        alert('Please fill all fields before assigning.');
                    }
                });
            });

            // Initialize Pie Charts
            const carerCtx = document.getElementById('carerPieChart').getContext('2d');
            const carerPieChart = new Chart(carerCtx, {
                type: 'pie',
                data: {
                    labels: ['Assigned', 'Unassigned'],
                    datasets: [{
                        data: [carerChartData.assigned, carerChartData.unassigned],
                        backgroundColor: [
                            '#36A2EB',
                            '#FF6384'
                        ],
                        hoverBackgroundColor: [
                            '#36A2EB',
                            '#FF6384'
                        ],
                        borderWidth: 2,
                        borderColor: '#fff'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    const label = context.label || '';
                                    const value = context.raw || 0;
                                    const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                    const percentage = total > 0 ? Math.round((value / total) * 100) : 0;
                                    return `${label}: ${value} carers (${percentage}%)`;
                                }
                            }
                        }
                    }
                }
            });

            // Elderly Pie Chart
            const elderlyCtx = document.getElementById('elderlyPieChart').getContext('2d');
            const elderlyPieChart = new Chart(elderlyCtx, {
                type: 'pie',
                data: {
                    labels: ['Assigned', 'Unassigned'],
                    datasets: [{
                        data: [elderlyChartData.assigned, elderlyChartData.unassigned],
                        backgroundColor: [
                            '#4BC0C0',
                            '#FF9F40'
                        ],
                        hoverBackgroundColor: [
                            '#4BC0C0',
                            '#FF9F40'
                        ],
                        borderWidth: 2,
                        borderColor: '#fff'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    const label = context.label || '';
                                    const value = context.raw || 0;
                                    const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                    const percentage = total > 0 ? Math.round((value / total) * 100) : 0;
                                    return `${label}: ${value} elderly (${percentage}%)`;
                                }
                            }
                        }
                    }
                }
            });
        });
    </script>
</body>
</html>
<?php $conn->close(); ?>