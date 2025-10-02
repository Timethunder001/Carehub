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
$carer_query = $conn->query("SELECT id, name, email FROM users WHERE roles = 'carer' ORDER BY name");
if ($carer_query) {
    while ($row = $carer_query->fetch_assoc()) {
        $carers[] = $row;
    }
} else {
    $carers = [];
}

// Get assignments for each carer to show who they're assigned to
$carer_assignments = [];
foreach ($carers as $carer) {
    $carer_id = $carer['id'];
    
    // Get the most recent assignment for the carer (regardless of date)
    $assignment_query = $conn->query("
        SELECT a.*, u_elderly.name as elderly_name, e.room_number 
        FROM assignments a 
        LEFT JOIN users u_elderly ON a.elderly_id = u_elderly.id 
        LEFT JOIN elderly e ON a.elderly_id = e.id 
        WHERE a.carer_id = $carer_id 
        ORDER BY a.start_date DESC 
        LIMIT 1
    ");
    
    $current_assignment = null;
    if ($assignment_query && $assignment_query->num_rows > 0) {
        $current_assignment = $assignment_query->fetch_assoc();
    }
    
    $carer_assignments[$carer_id] = $current_assignment;
}

// For demonstration - set all carers to "Not Submitted" status
$report_status = [];
foreach ($carers as $carer) {
    $has_submitted = false;
    $submission_date = null;
    
    $report_status[$carer['id']] = [
        'has_submitted' => $has_submitted,
        'submission_date' => $submission_date
    ];
}

// Handle viewing report details
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['view_report'])) {
    $carer_id = intval($_POST['carer_id']);
    $carer_name = '';
    
    // Get carer name for message
    foreach ($carers as $carer) {
        if ($carer['id'] == $carer_id) {
            $carer_name = $carer['name'];
            break;
        }
    }
    
    $_SESSION['success'] = "Report viewing for $carer_name will be available when carer reporting functionality is implemented.";
    header("Location: reports.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CareHub - Reports</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .status-submit {
            background: #d4edda;
            color: #155724;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: bold;
        }
        
        .status-not-submit {
            background: #f8d7da;
            color: #721c24;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: bold;
        }
        
        .view-report-btn {
            background: #00A9FF;
            color: white;
            border: none;
            padding: 6px 12px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
        }
        
        .view-report-btn:disabled {
            background: #6c757d;
            cursor: not-allowed;
        }
        
        .no-reports {
            color: #666;
            font-style: italic;
        }
        
        .no-assignment {
            color: #999;
            font-style: italic;
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
                    <li><a href="reports.php" class="active">
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
                    <h1>Reports</h1>
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
         
            <div class="card schedule-card">
                <h2 class="card-header">Carer Reports Overview</h2>
                <div class="table-wrapper">
                    <table class="schedule-table">
                        <thead>
                            <tr>
                                <th>Carer Name</th>
                                <th>Assigned Elderly</th>
                                <th>Location</th>
                                <th>Last Report Date</th>
                                <th>Last Report Time</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($carers) > 0): ?>
                                <?php foreach ($carers as $carer): ?>
                                    <?php 
                                    $assignment = $carer_assignments[$carer['id']];
                                    $report_info = $report_status[$carer['id']];
                                    $has_assignment = $assignment !== null;
                                    $has_submitted = $report_info['has_submitted'];
                                    $submission_date = $report_info['submission_date'];
                                    ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($carer['name']); ?></td>
                                        <td>
                                            <?php if ($has_assignment && !empty($assignment['elderly_name'])): ?>
                                                <?php echo htmlspecialchars($assignment['elderly_name']); ?>
                                            <?php else: ?>
                                                <span class="no-assignment">Not assigned</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($has_assignment && !empty($assignment['room_number'])): ?>
                                                <?php echo htmlspecialchars($assignment['room_number']); ?>
                                            <?php else: ?>
                                                <span class="no-assignment">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($has_submitted && $submission_date): ?>
                                                <?php echo date('d/m/Y', strtotime($submission_date)); ?>
                                            <?php else: ?>
                                                <span class="no-reports">No reports</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($has_submitted && $submission_date): ?>
                                                <?php echo date('H:i A', strtotime($submission_date)); ?>
                                            <?php else: ?>
                                                <span class="no-reports">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="status-not-submit">Not Submitted</span>
                                        </td>
                                        <td>
                                            <button class="view-report-btn" disabled>No Reports</button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" style="text-align: center; color: #666;">No carers found</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>
</body>
</html>
<?php $conn->close(); ?>