<?php
// Start session to manage user authentication
session_start();

// Check if user is logged in
if (!isset($_SESSION["admin_username"])) {
    header("Location: login.html"); // Redirect to login if not authenticated
    exit; // Stop execution
}

// Include database connection
require_once "db.php";

// Retrieve logged-in user details from session
$loggedInAdmin = $_SESSION["admin_username"] ?? "Unknown"; // Username
$loggedInTerminal = $_SESSION["terminal"] ?? "Unknown"; // Terminal ID
$loggedInRole = $_SESSION["role"] ?? "Unknown"; // User role

// Role-based permissions
$canManageUsers = ($loggedInRole === "Administrator"); // Only admin can manage users
$canViewReports = in_array($loggedInRole, ["Administrator", "Manager"], true); // Admin + Manager can view reports

// Initialise inventory KPI variables
$totalProducts = 0;
$lowStockItems = 0;
$outOfStockItems = 0;
$products = []; // Array to store product data

// Query total number of products
$result = $mysqli->query("SELECT COUNT(*) AS total FROM Product");
if ($result && $row = $result->fetch_assoc()) {
    $totalProducts = (int)$row["total"];
}

// Query products with low stock (between 1 and 10)
$result = $mysqli->query("SELECT COUNT(*) AS total FROM Product WHERE prodQuantity BETWEEN 1 AND 10");
if ($result && $row = $result->fetch_assoc()) {
    $lowStockItems = (int)$row["total"];
}

// Query products that are out of stock
$result = $mysqli->query("SELECT COUNT(*) AS total FROM Product WHERE prodQuantity = 0");
if ($result && $row = $result->fetch_assoc()) {
    $outOfStockItems = (int)$row["total"];
}

// Query full product list
$result = $mysqli->query("
    SELECT prodId, prodName, prodPrice, prodQuantity, category
    FROM Product
    ORDER BY prodName ASC
");

// Store product records into array
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $products[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"> <!-- Character encoding -->
<meta name="viewport" content="width=device-width, initial-scale=1.0"> <!-- Responsive layout -->
<title>Inventory | POS Admin</title> <!-- Page title -->
<link rel="stylesheet" href="style.css"> <!-- External stylesheet -->
</head>
<body>
<div class="layout"> <!-- Main layout container -->
    <div class="sidebar"> <!-- Sidebar navigation -->
        <h2>POS Admin</h2>
        <p class="sidebar-subtitle"><?php echo htmlspecialchars($loggedInRole); ?></p>

        <!-- Navigation links -->
        <a href="dashboard.php">Dashboard</a>
        <a href="index.php">POS / Sales</a>

        <!-- Conditional Reports access -->
        <?php if ($canViewReports): ?>
            <a href="reports.php">Reports</a>
        <?php endif; ?>

        <!-- Current active page -->
        <a href="Inventory.php" class="active">Inventory</a>

        <!-- Conditional Suppliers access -->
        <?php if ($canViewReports): ?>
            <a href="suppliers.php">Suppliers</a>
        <?php endif; ?>

        <a href="ai_assistant.php">AI Assistant</a>

        <!-- Only admin can access Users -->
        <?php if ($canManageUsers): ?>
            <a href="users.php">Users / Staff</a>
        <?php endif; ?>

        <a href="logout.php">Logout</a>
    </div>

    <div class="main"> <!-- Main content area -->
        <div class="header-box"> <!-- Page header -->
            <h1>Inventory Management</h1>
            <p><strong>Logged in as:</strong> <?php echo htmlspecialchars($loggedInAdmin); ?></p>
            <p><strong>Terminal:</strong> <?php echo htmlspecialchars($loggedInTerminal); ?></p>
            <p><strong>Role:</strong> <?php echo htmlspecialchars($loggedInRole); ?></p>
            <p>Stock and product overview for the POS administration system.</p>
        </div>

        <div class="kpi-grid"> <!-- KPI summary section -->
            <div class="kpi-card">
                <h3>Total Products</h3>
                <div class="value"><?php echo number_format($totalProducts); ?></div>
            </div>
            <div class="kpi-card">
                <h3>Low Stock Items</h3>
                <div class="value"><?php echo number_format($lowStockItems); ?></div>
            </div>
            <div class="kpi-card">
                <h3>Out of Stock</h3>
                <div class="value"><?php echo number_format($outOfStockItems); ?></div>
            </div>
        </div>

        <div class="table-card"> <!-- Inventory table -->
            <h2>Inventory Report</h2>
            <p>Product stock levels and inventory status.</p>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Product</th>
                        <th>Category</th>
                        <th>Price</th>
                        <th>Stock</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <!-- Loop through products -->
                    <?php foreach ($products as $row): ?>
                    <?php
                        // Determine stock quantity
                        $qty = (int)$row["prodQuantity"];

                        // Assign status class based on stock level
                        $statusClass = $qty === 0 ? "out-stock" : ($qty <= 10 ? "low-stock" : "in-stock");

                        // Assign status text label
                        $statusText = $qty === 0 ? "Out of Stock" : ($qty <= 10 ? "Low Stock" : "In Stock");
                    ?>
                    <tr>
                        <td><?php echo (int)$row["prodId"]; ?></td>
                        <td><?php echo htmlspecialchars($row["prodName"]); ?></td>
                        <td><?php echo htmlspecialchars($row["category"]); ?></td>
                        <td>£<?php echo number_format((float)$row["prodPrice"], 2); ?></td>
                        <td><?php echo number_format($qty); ?></td>
                        <td>
                            <span class="status <?php echo $statusClass; ?>">
                                <?php echo $statusText; ?>
                            </span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
</body>
</html>