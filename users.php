<?php
// Start session to track logged-in user
session_start();

// Check if admin is logged in
if (!isset($_SESSION["admin_username"])) {
    header("Location: login.html"); // Redirect to login if not logged in
    exit();
}

// Restrict access to Administrator role only
if (($_SESSION["role"] ?? "") !== "Administrator") {
    header("Location: dashboard.php"); // Redirect non-admin users
    exit();
}

// Include database connection
require_once "db.php";

// Get logged-in admin details
$loggedInAdmin = $_SESSION["admin_username"] ?? "Unknown"; // Username
$loggedInRole  = $_SESSION["role"] ?? "Unknown"; // Role

// Role-based permissions
$canViewReports   = ($loggedInRole === "Administrator" || $loggedInRole === "Manager"); // Reports access
$canManageUsers   = ($loggedInRole === "Administrator"); // User management access
$canViewSuppliers = ($loggedInRole === "Administrator"); // Supplier access

// Array to store staff users
$staffUsers = [];

// SQL query to fetch all admin users
$sql = "SELECT admin_id, username, role, last_login 
        FROM admins 
        ORDER BY admin_id ASC";

// Execute query
$result = $mysqli->query($sql);

// Store results into array
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $staffUsers[] = $row;
    }
}

// Count total users
$totalUsers = count($staffUsers);

// Initialise counters
$adminCount = 0;
$managerCount = 0;
$cashierCount = 0;
$activeCount = 0;

// Loop through users to calculate stats
foreach ($staffUsers as $user) {
    $role = $user["role"] ?? "";

    // Count roles
    if ($role === "Administrator") {
        $adminCount++;
    } elseif ($role === "Manager") {
        $managerCount++;
    } elseif ($role === "Cashier") {
        $cashierCount++;
    }

    // Count active users (those who have logged in)
    if (!empty($user["last_login"])) {
        $activeCount++;
    }
}

// Function to assign CSS class based on role
function roleBadgeClass(string $role): string {
    if ($role === "Administrator") {
        return "role-badge role-admin"; // Admin styling
    }

    if ($role === "Manager") {
        return "role-badge role-manager"; // Manager styling
    }

    return "role-badge role-cashier"; // Default cashier styling
}

// Function to format last login time
function lastLoginLabel(?string $value): string {
    if ($value === null || trim($value) === "") {
        return "Never"; // No login recorded
    }

    $timestamp = strtotime($value); // Convert to timestamp

    if ($timestamp === false) {
        return $value; // Return original if conversion fails
    }

    return date("Y-m-d H:i", $timestamp); // Format date
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"> <!-- Character encoding -->
<meta name="viewport" content="width=device-width, initial-scale=1.0"> <!-- Responsive design -->
<title>Users / Staff | POS Admin</title> <!-- Page title -->
<link rel="stylesheet" href="style.css"> <!-- External stylesheet -->

<style>
.user-toolbar {
    display: grid;
    grid-template-columns: minmax(0, 1fr) 180px;
    gap: 16px;
    margin-bottom: 24px;
}

.user-search,
.user-filter {
    padding: 14px 16px;
    border: 1px solid #d1d5db;
    border-radius: 10px;
    background: #ffffff;
    font-size: 15px;
    color: #111827;
}

.user-search:focus,
.user-filter:focus {
    outline: none;
    border-color: #166534;
    box-shadow: 0 0 0 3px rgba(22, 101, 52, 0.12);
}

.user-table-head {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 16px;
    flex-wrap: wrap;
    margin-bottom: 8px;
}

.table-note {
    margin: 0 0 14px 0;
    color: #6b7280;
    font-size: 14px;
}

.role-badge,
.status-badge {
    display: inline-block;
    padding: 6px 10px;
    border-radius: 999px;
    font-size: 12px;
    font-weight: 700;
}

.role-admin {
    background: #fee2e2;
    color: #b91c1c;
}

.role-manager {
    background: #dbeafe;
    color: #1d4ed8;
}

.role-cashier {
    background: #dcfce7;
    color: #166534;
}

.status-active {
    background: #dcfce7;
    color: #166534;
}

.status-never {
    background: #e5e7eb;
    color: #6b7280;
}

.empty-state {
    padding: 24px;
    border: 1px dashed #d1d5db;
    border-radius: 12px;
    background: #f9fafb;
    color: #6b7280;
}

@media (max-width: 900px) {
    .user-toolbar {
        grid-template-columns: 1fr;
    }

    .user-table-wrap {
        overflow-x: auto;
    }

    .user-table-wrap table {
        min-width: 680px;
    }
}
</style>
</head>

<body>
<div class="layout"> <!-- Main layout container -->

    <div class="sidebar"> <!-- Sidebar navigation -->
        <h2>POS Admin</h2>
        <p class="sidebar-subtitle"><?php echo htmlspecialchars($loggedInRole); ?></p>

        <!-- Navigation links -->
        <a href="dashboard.php">Dashboard</a>
        <a href="index.php">POS / Sales</a>

        <!-- Conditional access to reports -->
        <?php if ($canViewReports): ?>
            <a href="reports.php">Reports</a>
        <?php endif; ?>

        <a href="Inventory.php">Inventory</a>

        <!-- Conditional access to suppliers -->
        <?php if ($canViewSuppliers): ?>
            <a href="suppliers.php">Suppliers</a>
        <?php endif; ?>

        <a href="ai_assistant.php">AI Assistant</a>

        <!-- Only admin can manage users -->
        <?php if ($canManageUsers): ?>
            <a href="users.php" class="active">Users / Staff</a>
        <?php endif; ?>

        <a href="logout.php">Logout</a>
    </div>

    <div class="main"> <!-- Main content area -->

        <div class="header-box"> <!-- Header section -->
            <h1>User Management</h1>
            <p><strong>Logged in as:</strong> <?php echo htmlspecialchars($loggedInAdmin); ?></p>
            <p><strong>Role:</strong> <?php echo htmlspecialchars($loggedInRole); ?></p>
            <p>Manage staff access and view the roles assigned to each account.</p>
        </div>

        <div class="kpi-grid"> <!-- KPI summary section -->
            <div class="kpi-card">
                <h3>Total Staff Users</h3>
                <div class="value"><?php echo number_format($totalUsers); ?></div>
            </div>

            <div class="kpi-card">
                <h3>Active Users</h3>
                <div class="value"><?php echo number_format($activeCount); ?></div>
            </div>

            <div class="kpi-card">
                <h3>Administrators</h3>
                <div class="value"><?php echo number_format($adminCount); ?></div>
            </div>

            <div class="kpi-card">
                <h3>Managers</h3>
                <div class="value"><?php echo number_format($managerCount); ?></div>
            </div>

            <div class="kpi-card">
                <h3>Cashiers</h3>
                <div class="value"><?php echo number_format($cashierCount); ?></div>
            </div>
        </div>

        <div class="table-card"> <!-- Table container -->
            <div class="user-table-head">
                <div>
                    <h2>Staff Accounts</h2>
                    <p class="table-note">Only the Administrator can access this page.</p>
                </div>
            </div>

            <div class="user-toolbar"> <!-- Search + filter -->
                <input type="text" id="userSearch" class="user-search" placeholder="Search users by username...">

                <select id="roleFilter" class="user-filter">
                    <option value="All Roles">All Roles</option>
                    <option value="Administrator">Administrator</option>
                    <option value="Manager">Manager</option>
                    <option value="Cashier">Cashier</option>
                </select>
            </div>

            <!-- Check if users exist -->
            <?php if (count($staffUsers) > 0): ?>
                <div class="user-table-wrap">
                    <table id="usersTable">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Username</th>
                                <th>Role</th>
                                <th>Status</th>
                                <th>Last Login</th>
                            </tr>
                        </thead>

                        <tbody>
                            <!-- Loop through users -->
                            <?php foreach ($staffUsers as $user): ?>
                                <tr>
                                    <td><?php echo (int)$user["admin_id"]; ?></td>

                                    <td class="username-cell">
                                        <?php echo htmlspecialchars($user["username"] ?? ""); ?>
                                    </td>

                                    <td class="role-cell">
                                        <span class="<?php echo roleBadgeClass((string)($user["role"] ?? "")); ?>">
                                            <?php echo htmlspecialchars($user["role"] ?? ""); ?>
                                        </span>
                                    </td>

                                    <td>
                                        <!-- Show active or inactive -->
                                        <?php if (!empty($user["last_login"])): ?>
                                            <span class="status-badge status-active">Active</span>
                                        <?php else: ?>
                                            <span class="status-badge status-never">No login yet</span>
                                        <?php endif; ?>
                                    </td>

                                    <td>
                                        <?php echo htmlspecialchars(lastLoginLabel($user["last_login"] ?? null)); ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <!-- Display if no users -->
                <div class="empty-state">No staff accounts were found in the admins table.</div>
            <?php endif; ?>

        </div>
    </div>
</div>

<script>
// Get DOM elements
const searchInput = document.getElementById("userSearch");
const roleFilter = document.getElementById("roleFilter");
const rows = document.querySelectorAll("#usersTable tbody tr");

// Function to filter users based on search and role
function filterUsers() {
    const searchValue = searchInput.value.toLowerCase().trim(); // Search input
    const roleValue = roleFilter.value; // Selected role

    rows.forEach(row => {
        const username = row.querySelector(".username-cell").textContent.toLowerCase();
        const role = row.querySelector(".role-cell").textContent.trim();

        const matchesSearch = username.includes(searchValue); // Check username match
        const matchesRole = roleValue === "All Roles" || role === roleValue; // Check role match

        // Show or hide row
        row.style.display = matchesSearch && matchesRole ? "" : "none";
    });
}

// Event listeners for filtering
searchInput.addEventListener("input", filterUsers);
roleFilter.addEventListener("change", filterUsers);
</script>

</body>
</html>