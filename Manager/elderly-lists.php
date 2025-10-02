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

// Get all elderly members (users with role 'member') from database
$elderly_members = [];
$elderly_query = $conn->query("
    SELECT u.id, u.name, e.room_number, e.carer_id, uc.name as carer_name
    FROM users u 
    LEFT JOIN elderly e ON u.id = e.id 
    LEFT JOIN users uc ON e.carer_id = uc.id 
    WHERE u.roles = 'member' 
    ORDER BY u.name
");
if ($elderly_query) {
    while ($row = $elderly_query->fetch_assoc()) {
        $elderly_members[] = $row;
    }
} else {
    $elderly_members = [];
}

// Get assignments for each elderly
$elderly_assignments = [];
foreach ($elderly_members as $elderly) {
    $elderly_id = $elderly['id'];
    $assignment_query = $conn->query("
        SELECT a.*, u_carer.name as carer_name
        FROM assignments a 
        LEFT JOIN users u_carer ON a.carer_id = u_carer.id 
        WHERE a.elderly_id = $elderly_id 
        ORDER BY a.start_date DESC
        LIMIT 1
    ");
    
    $assignment = null;
    if ($assignment_query && $assignment_query->num_rows > 0) {
        $assignment = $assignment_query->fetch_assoc();
    }
    
    $elderly_assignments[$elderly_id] = $assignment;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CareHub - Elderly Lists</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <style>
        /* Modal Styles */
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
            margin: 5% auto;
            padding: 30px;
            border-radius: 8px;
            width: 80%;
            max-width: 600px;
            max-height: 80vh;
            overflow-y: auto;
        }

        .modal-large {
            max-width: 95%;
            width: 95%;
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

        .form-group textarea,
        .form-group input,
        .form-group select {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }

        .form-group textarea {
            height: 120px;
            resize: vertical;
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

        .time-slots {
            display: grid;
            grid-template-columns: 1fr;
            gap: 10px;
            margin-top: 15px;
        }

        .time-slot {
            display: grid;
            grid-template-columns: 100px 1fr;
            gap: 10px;
            align-items: center;
        }

        .time-label {
            font-weight: bold;
            color: #333;
        }

        .calendar-day-header {
            font-weight: bold;
            text-align: center;
            padding: 10px;
            background-color: #f8f9fa;
            border: 1px solid #ddd;
        }

        .calendar-day {
            border: 1px solid #ddd;
            padding: 10px;
            cursor: pointer;
            text-align: center;
            min-height: 80px;
            background-color: white;
            transition: background-color 0.2s;
        }

        .calendar-day:hover {
            background-color: #f0f8ff;
        }

        .calendar-day.empty {
            background-color: #f8f9fa;
            cursor: default;
        }

        .calendar-day.today {
            background-color: #e7f3ff;
            border: 2px solid #00A9FF;
        }

        .calendar-day.selected {
            background-color: #00A9FF;
            color: white;
        }

        .bill-items {
            margin-bottom: 20px;
        }

        .bill-item {
            display: grid;
            grid-template-columns: 2fr 1fr 1fr auto;
            gap: 10px;
            margin-bottom: 10px;
            align-items: center;
        }

        .bill-total {
            border-top: 2px solid #333;
            padding-top: 10px;
            font-weight: bold;
            text-align: right;
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
                    <li><a href="elderly-lists.php" class="active">
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
                    <h1>Elderly Lists</h1>
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
                <h2 class="card-header">Elderly Management</h2>
                <div class="table-wrapper">
                    <table class="schedule-table">
                        <thead>
                            <tr>
                                <th>Elderly Name</th>
                                <th>Carer Assigned</th>
                                <th>Location</th>
                                <th>Date</th>
                                <th>Status</th>
                                <th>Add Note</th>
                                <th>Add Schedule</th>
                                <th>Bill</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($elderly_members) > 0): ?>
                                <?php foreach ($elderly_members as $elderly): ?>
                                    <?php 
                                    $assignment = $elderly_assignments[$elderly['id']];
                                    $has_assignment = $assignment !== null;
                                    ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($elderly['name']); ?></td>
                                        <td>
                                            <?php if ($has_assignment): ?>
                                                <?php echo htmlspecialchars($assignment['carer_name']); ?>
                                            <?php else: ?>
                                                <span style="color: #666;">Not Assigned</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($elderly['room_number'] ?? 'Not Set'); ?></td>
                                        <td>
                                            <?php if ($has_assignment): ?>
                                                <?php echo date('d/m/Y', strtotime($assignment['start_date'])); ?> - <?php echo date('d/m/Y', strtotime($assignment['end_date'])); ?>
                                            <?php else: ?>
                                                -
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($has_assignment): ?>
                                                <span class="status-assigned">Assigned</span>
                                            <?php else: ?>
                                                <span class="status-unassign">Unassigned</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <button class="btn btn-primary" onclick="openNoteModal(<?php echo $elderly['id']; ?>, '<?php echo htmlspecialchars($elderly['name']); ?>')">
                                                Add Note
                                            </button>
                                        </td>
                                        <td>
                                            <button class="btn btn-primary" onclick="openScheduleModal(<?php echo $elderly['id']; ?>, '<?php echo htmlspecialchars($elderly['name']); ?>')">
                                                Add Schedule
                                            </button>
                                        </td>
                                        <td>
                                            <button class="btn btn-primary" onclick="openBillModal(<?php echo $elderly['id']; ?>, '<?php echo htmlspecialchars($elderly['name']); ?>')">
                                                Bill
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="8" style="text-align: center; color: #666;">No elderly members found</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>

    <!-- Note Modal -->
    <div id="noteModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeNoteModal()">&times;</span>
            <h2>Add Note for <span id="noteElderlyName"></span></h2>
            <form id="noteForm">
                <input type="hidden" id="noteElderlyId" name="elderly_id">
                <div class="form-group">
                    <label for="noteTitle">Note Title:</label>
                    <input type="text" id="noteTitle" name="note_title" required>
                </div>
                <div class="form-group">
                    <label for="noteContent">Note Content:</label>
                    <textarea id="noteContent" name="note_content" placeholder="Enter specific support requirements or notes for the carer..." required></textarea>
                </div>
                <div class="form-group">
                    <label for="notePriority">Priority:</label>
                    <select id="notePriority" name="note_priority">
                        <option value="low">Low</option>
                        <option value="medium">Medium</option>
                        <option value="high">High</option>
                    </select>
                </div>
                <div style="text-align: right;">
                    <button type="button" class="btn btn-secondary" onclick="closeNoteModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Note</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Schedule Modal -->
    <div id="scheduleModal" class="modal">
        <div class="modal-content modal-large">
            <span class="close" onclick="closeScheduleModal()">&times;</span>
            <h2>Manage Schedule for <span id="scheduleElderlyName"></span></h2>
            
            <!-- Month Navigation -->
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <button class="btn btn-secondary" onclick="changeMonth(-1)">Previous</button>
                <h3 id="currentMonth">January 2024</h3>
                <button class="btn btn-secondary" onclick="changeMonth(1)">Next</button>
            </div>

            <!-- Calendar Grid -->
            <div class="calendar-grid" id="calendarGrid">
                <!-- Calendar will be generated by JavaScript -->
            </div>

            <!-- Day Schedule -->
            <div id="daySchedule" style="display: none; margin-top: 30px;">
                <h3>Daily Schedule for <span id="selectedDate"></span></h3>
                <div class="time-slots">
                    <?php for ($hour = 8; $hour <= 17; $hour++): ?>
                        <div class="time-slot">
                            <div class="time-label">
                                <?php echo sprintf('%02d:00', $hour); ?> - <?php echo sprintf('%02d:00', $hour + 1); ?>
                            </div>
                            <input type="text" class="activity-input" placeholder="Enter activity for this time slot" 
                                   data-hour="<?php echo $hour; ?>">
                        </div>
                    <?php endfor; ?>
                </div>
                <div style="text-align: right; margin-top: 20px;">
                    <button type="button" class="btn btn-secondary" onclick="closeDaySchedule()">Cancel</button>
                    <button type="button" class="btn btn-primary" onclick="saveDaySchedule()">Save Schedule</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Bill Modal -->
    <div id="billModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeBillModal()">&times;</span>
            <h2>Create Bill for <span id="billElderlyName"></span></h2>
            <form id="billForm">
                <input type="hidden" id="billElderlyId" name="elderly_id">
                <div class="form-group">
                    <label for="billDate">Billing Date:</label>
                    <input type="date" id="billDate" name="bill_date" required>
                </div>
                <div class="form-group">
                    <label for="billPeriod">Billing Period:</label>
                    <input type="text" id="billPeriod" name="bill_period" placeholder="e.g., January 2024" required>
                </div>
                
                <div class="bill-items">
                    <h4>Bill Items</h4>
                    <div id="billItemsContainer">
                        <div class="bill-item">
                            <input type="text" name="item_description[]" placeholder="Item description" required>
                            <input type="number" name="item_quantity[]" placeholder="Qty" min="1" value="1" required>
                            <input type="number" name="item_price[]" placeholder="Price" step="0.01" min="0" required>
                            <button type="button" class="btn btn-secondary" onclick="removeBillItem(this)">Remove</button>
                        </div>
                    </div>
                    <button type="button" class="btn btn-primary" onclick="addBillItem()">Add Item</button>
                </div>
                
                <div class="form-group">
                    <label for="billNotes">Notes:</label>
                    <textarea id="billNotes" name="bill_notes" placeholder="Additional notes for the bill..."></textarea>
                </div>
                
                <div class="bill-total">
                    Total: $<span id="billTotal">0.00</span>
                </div>
                
                <div style="text-align: right; margin-top: 20px;">
                    <button type="button" class="btn btn-secondary" onclick="closeBillModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary">Send Bill</button>
                </div>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script>
        // Modal Management
        let currentElderlyId = null;
        let currentElderlyName = null;
        let selectedCalendarDate = null;
        let currentDate = new Date();

        // Note Modal Functions
        function openNoteModal(elderlyId, elderlyName) {
            currentElderlyId = elderlyId;
            currentElderlyName = elderlyName;
            document.getElementById('noteElderlyName').textContent = elderlyName;
            document.getElementById('noteElderlyId').value = elderlyId;
            document.getElementById('noteModal').style.display = 'block';
        }

        function closeNoteModal() {
            document.getElementById('noteModal').style.display = 'none';
            document.getElementById('noteForm').reset();
        }

        // Schedule Modal Functions
        function openScheduleModal(elderlyId, elderlyName) {
            currentElderlyId = elderlyId;
            currentElderlyName = elderlyName;
            document.getElementById('scheduleElderlyName').textContent = elderlyName;
            document.getElementById('scheduleModal').style.display = 'block';
            
            // Reset to current month
            currentDate = new Date();
            initializeCalendar();
        }

        function closeScheduleModal() {
            document.getElementById('scheduleModal').style.display = 'none';
            document.getElementById('daySchedule').style.display = 'none';
        }

        // Bill Modal Functions
        function openBillModal(elderlyId, elderlyName) {
            currentElderlyId = elderlyId;
            currentElderlyName = elderlyName;
            document.getElementById('billElderlyName').textContent = elderlyName;
            document.getElementById('billElderlyId').value = elderlyId;
            document.getElementById('billDate').valueAsDate = new Date();
            document.getElementById('billModal').style.display = 'block';
        }

        function closeBillModal() {
            document.getElementById('billModal').style.display = 'none';
            document.getElementById('billForm').reset();
            resetBillItems();
        }

        // Calendar Management
        function initializeCalendar() {
            renderCalendar(currentDate);
        }

        function renderCalendar(date) {
            const calendarGrid = document.getElementById('calendarGrid');
            const monthNames = ["January", "February", "March", "April", "May", "June",
                "July", "August", "September", "October", "November", "December"];
            
            // Update month header
            document.getElementById('currentMonth').textContent = 
                `${monthNames[date.getMonth()]} ${date.getFullYear()}`;
            
            // Clear previous calendar
            calendarGrid.innerHTML = '';
            
            // Create calendar header (days of week)
            const daysOfWeek = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
            daysOfWeek.forEach(day => {
                const dayHeader = document.createElement('div');
                dayHeader.className = 'calendar-day-header';
                dayHeader.textContent = day;
                calendarGrid.appendChild(dayHeader);
            });
            
            // Get first day of month and number of days
            const firstDay = new Date(date.getFullYear(), date.getMonth(), 1);
            const lastDay = new Date(date.getFullYear(), date.getMonth() + 1, 0);
            const startingDay = firstDay.getDay();
            const daysInMonth = lastDay.getDate();
            
            // Add empty cells for days before the first day of month
            for (let i = 0; i < startingDay; i++) {
                const emptyCell = document.createElement('div');
                emptyCell.className = 'calendar-day empty';
                calendarGrid.appendChild(emptyCell);
            }
            
            // Add days of the month
            for (let day = 1; day <= daysInMonth; day++) {
                const dayCell = document.createElement('div');
                dayCell.className = 'calendar-day';
                dayCell.textContent = day;
                
                // Add click event
                const cellDate = new Date(date.getFullYear(), date.getMonth(), day);
                dayCell.addEventListener('click', () => selectDate(cellDate));
                
                calendarGrid.appendChild(dayCell);
            }
            
            // Add CSS for calendar grid
            calendarGrid.style.display = 'grid';
            calendarGrid.style.gridTemplateColumns = 'repeat(7, 1fr)';
            calendarGrid.style.gap = '2px';
            calendarGrid.style.backgroundColor = '#ddd';
            calendarGrid.style.padding = '2px';
            calendarGrid.style.borderRadius = '4px';
        }

        function changeMonth(direction) {
            currentDate.setMonth(currentDate.getMonth() + direction);
            renderCalendar(currentDate);
        }

        function selectDate(date) {
            selectedCalendarDate = date;
            const dateString = date.toISOString().split('T')[0];
            const formattedDate = date.toLocaleDateString('en-GB');
            
            document.getElementById('selectedDate').textContent = formattedDate;
            document.getElementById('daySchedule').style.display = 'block';
            loadDaySchedule(dateString);
        }

        function loadDaySchedule(date) {
            // Clear previous activities
            const inputs = document.querySelectorAll('.activity-input');
            inputs.forEach(input => input.value = '');
            console.log('Loading schedule for:', date, 'elderly:', currentElderlyId);
            const sampleActivities = {
                '8': 'Morning medication',
                '9': 'Breakfast',
                '10': 'Physical therapy',
                '12': 'Lunch',
                '14': 'Afternoon walk',
                '16': 'Social activity'
            };
            
            inputs.forEach(input => {
                const hour = input.dataset.hour;
                if (sampleActivities[hour]) {
                    input.value = sampleActivities[hour];
                }
            });
        }

        function closeDaySchedule() {
            document.getElementById('daySchedule').style.display = 'none';
        }

        function saveDaySchedule() {
            const activities = {};
            const inputs = document.querySelectorAll('.activity-input');
            
            inputs.forEach(input => {
                const hour = input.dataset.hour;
                const activity = input.value.trim();
                if (activity) {
                    activities[hour] = activity;
                }
            });
            console.log('Saving schedule for:', selectedCalendarDate, 'activities:', activities);
            alert('Schedule saved successfully!');
            closeDaySchedule();
        }

        // Bill Management
        function addBillItem() {
            const container = document.getElementById('billItemsContainer');
            const newItem = document.createElement('div');
            newItem.className = 'bill-item';
            newItem.innerHTML = `
                <input type="text" name="item_description[]" placeholder="Item description" required>
                <input type="number" name="item_quantity[]" placeholder="Qty" min="1" value="1" required onchange="calculateTotal()">
                <input type="number" name="item_price[]" placeholder="Price" step="0.01" min="0" required onchange="calculateTotal()">
                <button type="button" class="btn btn-secondary" onclick="removeBillItem(this)">Remove</button>
            `;
            container.appendChild(newItem);
        }

        function removeBillItem(button) {
            if (document.querySelectorAll('.bill-item').length > 1) {
                button.parentElement.remove();
                calculateTotal();
            }
        }

        function resetBillItems() {
            const container = document.getElementById('billItemsContainer');
            container.innerHTML = `
                <div class="bill-item">
                    <input type="text" name="item_description[]" placeholder="Item description" required>
                    <input type="number" name="item_quantity[]" placeholder="Qty" min="1" value="1" required onchange="calculateTotal()">
                    <input type="number" name="item_price[]" placeholder="Price" step="0.01" min="0" required onchange="calculateTotal()">
                    <button type="button" class="btn btn-secondary" onclick="removeBillItem(this)">Remove</button>
                </div>
            `;
            calculateTotal();
        }

        function calculateTotal() {
            let total = 0;
            const items = document.querySelectorAll('.bill-item');
            
            items.forEach(item => {
                const quantity = parseFloat(item.querySelector('input[name="item_quantity[]"]').value) || 0;
                const price = parseFloat(item.querySelector('input[name="item_price[]"]').value) || 0;
                total += quantity * price;
            });
            
            document.getElementById('billTotal').textContent = total.toFixed(2);
        }

        // Form Submissions
        document.getElementById('noteForm').addEventListener('submit', function(e) {
            e.preventDefault();
            console.log('Saving note for elderly:', currentElderlyId);
            alert('Note saved successfully!');
            closeNoteModal();
        });

        document.getElementById('billForm').addEventListener('submit', function(e) {
            e.preventDefault();
            console.log('Sending bill for elderly:', currentElderlyId);
            alert('Bill sent successfully!');
            closeBillModal();
        });

        // Close modals when clicking outside
        window.onclick = function(event) {
            const noteModal = document.getElementById('noteModal');
            const scheduleModal = document.getElementById('scheduleModal');
            const billModal = document.getElementById('billModal');
            
            if (event.target === noteModal) closeNoteModal();
            if (event.target === scheduleModal) closeScheduleModal();
            if (event.target === billModal) closeBillModal();
        }

        // Initialize bill total on load
        document.addEventListener('DOMContentLoaded', function() {
            calculateTotal();
        });
    </script>
</body>
</html>
<?php $conn->close(); ?>