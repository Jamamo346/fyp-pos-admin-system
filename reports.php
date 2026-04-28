<?php
// Start session to manage user authentication
session_start();

// Check if user is logged in
if (!isset($_SESSION["admin_username"])) {
    header("Location: login.html"); // Redirect to login if not authenticated
    exit; // Stop execution
}

// Get user role from session
$loggedInRole = $_SESSION["role"] ?? "";

// Allow only Administrator or Manager access
if (!in_array($loggedInRole, ["Administrator", "Manager"], true)) {
    header("Location: dashboard.php"); // Redirect unauthorized users
    exit;
}

// Include database connection
require_once "db.php";

// Store logged-in admin username
$loggedInAdmin = $_SESSION["admin_username"] ?? "Unknown";

// Role-based permissions
$canManageUsers = ($loggedInRole === "Administrator"); // Only admin can manage users
$canViewReports = in_array($loggedInRole, ["Administrator", "Manager"], true); // Admin + Manager can view reports


// Initialise KPI variables
$totalTransactions = 0;
$totalRevenue = 0;
$averageTransaction = 0;
$highestTransaction = 0;

// Get total number of transactions
$result = $mysqli->query("SELECT COUNT(*) AS total FROM transactions");
if ($result && $row = $result->fetch_assoc()) {
    $totalTransactions = (int)$row["total"];
}

// Get total revenue
$result = $mysqli->query("SELECT SUM(amount) AS total FROM transactions");
if ($result && $row = $result->fetch_assoc()) {
    $totalRevenue = (float)($row["total"] ?? 0);
}

// Get average transaction value
$result = $mysqli->query("SELECT AVG(amount) AS avg_value FROM transactions");
if ($result && $row = $result->fetch_assoc()) {
    $averageTransaction = (float)($row["avg_value"] ?? 0);
}

// Get highest transaction value
$result = $mysqli->query("SELECT MAX(amount) AS max_value FROM transactions");
if ($result && $row = $result->fetch_assoc()) {
    $highestTransaction = (float)($row["max_value"] ?? 0);
}

// Array to store transactions grouped by admin
$transactionsByAdmin = [];

// Track highest transaction count for chart scaling
$maxTransactions = 1;

// Query to group transactions by admin
$result = $mysqli->query("
    SELECT admin_username, COUNT(*) AS transactions, SUM(amount) AS revenue
    FROM transactions
    GROUP BY admin_username
    ORDER BY transactions DESC, admin_username ASC
");

// Store results and calculate max transactions
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $transactionsByAdmin[] = $row;

        // Update max transactions if higher value found
        if ((int)$row["transactions"] > $maxTransactions) {
            $maxTransactions = (int)$row["transactions"];
        }
    }
}

// Array to store recent transactions
$recentTransactions = [];

// Query to get latest 8 transactions
$result = $mysqli->query("
    SELECT transaction_id, admin_username, terminal, product_type, amount, transaction_type
    FROM transactions
    ORDER BY transaction_id DESC
    LIMIT 8
");

// Store recent transactions
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $recentTransactions[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"> <!-- Character encoding -->
<meta name="viewport" content="width=device-width, initial-scale=1.0"> <!-- Responsive layout -->
<title>Reports | POS Admin</title> <!-- Page title -->
<link rel="stylesheet" href="style.css"> <!-- External CSS -->
</head>
<body>
<div class="layout"> <!-- Main layout wrapper -->

<div class="sidebar"> <!-- Sidebar navigation -->
    <h2>POS Admin</h2>
    <p class="sidebar-subtitle"><?php echo htmlspecialchars($loggedInRole); ?></p>

    <!-- Navigation links -->
    <a href="dashboard.php">Dashboard</a>
    <a href="index.php">POS / Sales</a>

    <!-- Show Reports link if permitted -->
    <?php if ($canViewReports): ?>
        <a href="reports.php" class="active">Reports</a>
    <?php endif; ?>

    <a href="Inventory.php">Inventory</a>

    <!-- Show Suppliers if permitted -->
    <?php if ($canViewReports): ?>
        <a href="suppliers.php">Suppliers</a>
    <?php endif; ?>

    <a href="ai_assistant.php">AI Assistant</a>

    <!-- Show Users page only for admin -->
    <?php if ($canManageUsers): ?>
        <a href="users.php">Users / Staff</a>
    <?php endif; ?>

    <a href="logout.php">Logout</a>
</div>

<div class="main"> <!-- Main content -->

    <div class="header-box"> <!-- Header section -->
        <h1>Reports & Analytics</h1>
        <p><strong>Logged in as:</strong> <?php echo htmlspecialchars($loggedInAdmin); ?></p>
        <p><strong>Role:</strong> <?php echo htmlspecialchars($loggedInRole); ?></p>
        <p>Business performance insights from the POS prototype database.</p>
    </div>

    <div class="kpi-grid"> <!-- KPI cards -->
        <div class="kpi-card">
            <h3>Total Revenue</h3>
            <div class="value">£<?php echo number_format($totalRevenue, 2); ?></div>
        </div>
        <div class="kpi-card">
            <h3>Total Transactions</h3>
            <div class="value"><?php echo number_format($totalTransactions); ?></div>
        </div>
        <div class="kpi-card">
            <h3>Average Transaction</h3>
            <div class="value">£<?php echo number_format($averageTransaction, 2); ?></div>
        </div>
        <div class="kpi-card">
            <h3>Highest Transaction</h3>
            <div class="value">£<?php echo number_format($highestTransaction, 2); ?></div>
        </div>
    </div>

    <div class="chart-card"> <!-- Chart section -->
        <h2>Transactions Overview</h2>
        <div class="chart-wrapper">
            <div class="chart-grid">
                <?php foreach ($transactionsByAdmin as $row): ?>
                    <?php
                        // Calculate bar height based on transaction count
                        $transactions = (int)$row["transactions"];
                        $heightPercent = ($transactions / $maxTransactions) * 100;
                        $barHeight = max(40, $heightPercent * 2.4); // Minimum height enforced
                    ?>
                    <div class="chart-col">
                        <div class="bar" style="height: <?php echo $barHeight; ?>px;">
                            <?php echo $transactions; ?>
                        </div>
                        <div class="chart-label"><?php echo htmlspecialchars($row["admin_username"]); ?></div>
                        <div class="chart-subtext">£<?php echo number_format((float)$row["revenue"], 2); ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <div class="table-card"> <!-- Recent transactions table -->
        <h2>Recent Transactions</h2>
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Admin</th>
                    <th>Terminal</th>
                    <th>Product</th>
                    <th>Amount</th>
                    <th>Type</th>
                </tr>
            </thead>
            <tbody>
                <!-- Loop through recent transactions -->
                <?php foreach ($recentTransactions as $row): ?>
                <tr>
                    <td><?php echo (int)$row["transaction_id"]; ?></td>
                    <td><?php echo htmlspecialchars($row["admin_username"]); ?></td>
                    <td><?php echo htmlspecialchars($row["terminal"]); ?></td>
                    <td><?php echo htmlspecialchars($row["product_type"]); ?></td>
                    <td>£<?php echo number_format((float)$row["amount"], 2); ?></td>
                    <td><?php echo htmlspecialchars($row["transaction_type"]); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

</div>
</div>
</body>
</html>
<?php