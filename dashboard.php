<?php
// Start session to access logged-in user details
session_start();

// Check if user is logged in
if (!isset($_SESSION["admin_username"])) {
    header("Location: login.html"); // Redirect to login page if not logged in
    exit; // Stop script execution
}

// Include database connection
require_once "db.php";

// Store logged-in user session details
$loggedInAdmin = $_SESSION["admin_username"] ?? "Unknown"; // Logged-in admin username
$loggedInTerminal = $_SESSION["terminal"] ?? "Unknown"; // Assigned terminal
$loggedInRole = $_SESSION["role"] ?? "Unknown"; // Logged-in user role

// Set role-based permissions
$canManageUsers = (($_SESSION["role"] ?? "") === "Administrator"); // Administrator only
$canViewReports = in_array(($_SESSION["role"] ?? ""), ["Administrator", "Manager"], true); // Administrator and Manager


   //KPI QUERIES 


// Initialise total transactions variable
$totalTransactions = 0;

// Query total number of transactions
$result = $mysqli->query("SELECT COUNT(*) AS total FROM transactions");
if ($result && $row = $result->fetch_assoc()) {
    $totalTransactions = (int)$row["total"];
}

// Initialise highest transaction variable
$highestTransaction = 0;

// Query highest transaction amount
$result = $mysqli->query("SELECT MAX(amount) AS max_value FROM transactions");
if ($result && $row = $result->fetch_assoc()) {
    $highestTransaction = (float)($row["max_value"] ?? 0);
}

// Initialise today's sales variable
$todaysSales = 0;

// Query total sales for today
$result = $mysqli->query("
    SELECT SUM(amount) AS total_today
    FROM transactions
    WHERE DATE(created_at) = CURDATE()
");
if ($result && $row = $result->fetch_assoc()) {
    $todaysSales = (float)($row["total_today"] ?? 0);
}

// Initialise today's transaction count
$transactionsToday = 0;

// Query number of transactions today
$result = $mysqli->query("
    SELECT COUNT(*) AS total_today
    FROM transactions
    WHERE DATE(created_at) = CURDATE()
");
if ($result && $row = $result->fetch_assoc()) {
    $transactionsToday = (int)$row["total_today"];
}

// Default values for top product
$topProductName = "No data";
$topProductCount = 0;

// Prepare query to find top product for logged-in admin
$stmt = $mysqli->prepare("
    SELECT product_type, COUNT(*) AS count
    FROM transactions
    WHERE admin_username = ?
      AND product_type IS NOT NULL
      AND product_type <> ''
    GROUP BY product_type
    ORDER BY count DESC, product_type ASC
    LIMIT 1
");

// Execute top product query if prepared successfully
if ($stmt) {
    $stmt->bind_param("s", $loggedInAdmin); // Bind logged-in admin username
    $stmt->execute(); // Execute query
    $productResult = $stmt->get_result(); // Get result

    // Store top product result
    if ($productResult && $row = $productResult->fetch_assoc()) {
        $topProductName = $row["product_type"];
        $topProductCount = (int)$row["count"];
    }

    $stmt->close(); // Close prepared statement
}

// Default values for top admin
$topAdminName = "No data";
$topAdminOrders = 0;

// Query admin with highest number of orders
$result = $mysqli->query("
    SELECT admin_username, COUNT(*) AS total_orders
    FROM transactions
    GROUP BY admin_username
    ORDER BY total_orders DESC, admin_username ASC
    LIMIT 1
");

// Store top admin result
if ($result && $row = $result->fetch_assoc()) {
    $topAdminName = $row["admin_username"];
    $topAdminOrders = (int)$row["total_orders"];
}

// Array to store transaction breakdown by admin
$transactionsByAdmin = [];

// Query transactions grouped by admin
$result = $mysqli->query("
    SELECT admin_username,
           COUNT(*) AS transactions,
           COALESCE(SUM(amount), 0) AS revenue
    FROM transactions
    GROUP BY admin_username
    ORDER BY transactions DESC, revenue DESC
");

// Store admin transaction breakdown
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $transactionsByAdmin[] = $row;
    }
}

// Array to store transaction breakdown by terminal
$transactionsByTerminal = [];

// Query transactions grouped by terminal
$result = $mysqli->query("
    SELECT terminal,
           COUNT(*) AS transactions,
           COALESCE(SUM(amount), 0) AS revenue
    FROM transactions
    GROUP BY terminal
    ORDER BY transactions DESC, revenue DESC
");

// Store terminal transaction breakdown
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $transactionsByTerminal[] = $row;
    }
}


   /*TOP SELLERS (TODAY ONLY)*/

// Array to store today's top sellers
$topSellers = [];

// Query top sellers for today's transactions only
$result = $mysqli->query("
    SELECT admin_username,
           COUNT(*) AS total_transactions,
           COALESCE(SUM(amount), 0) AS total_revenue
    FROM transactions
    WHERE DATE(created_at) = CURDATE()
    GROUP BY admin_username
    ORDER BY total_transactions DESC, total_revenue DESC
    LIMIT 5
");

// Store today's top sellers
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $topSellers[] = $row;
    }
}

/* ===============================
   WEEKLY SALES (LAST 7 DAYS WITH ZERO VALUES INCLUDED)
================================*/

// Array to store daily sales using date as key
$dailySalesMap = [];

// Query sales totals for the last 7 days
$result = $mysqli->query("
    SELECT DATE(created_at) AS sale_date,
           COALESCE(SUM(amount), 0) AS daily_total
    FROM transactions
    WHERE DATE(created_at) >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
    GROUP BY DATE(created_at)
    ORDER BY DATE(created_at) ASC
");

// Store daily sales totals
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $dailySalesMap[$row["sale_date"]] = (float)$row["daily_total"];
    }
}

// Arrays for chart labels and values
$chartLabels = [];
$chartValues = [];
$maxSale = 1;

// Build chart data for the last 7 days
for ($i = 6; $i >= 0; $i--) {
    $date = date("Y-m-d", strtotime("-$i days")); // Date value
    $label = date("D", strtotime($date)); // Day label
    $value = $dailySalesMap[$date] ?? 0; // Sales value or zero

    $chartLabels[] = $label; // Add label to chart
    $chartValues[] = $value; // Add value to chart

    // Track highest sale value for chart scaling
    if ($value > $maxSale) {
        $maxSale = $value;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>POS Admin Dashboard</title>
<link rel="stylesheet" href="style.css">

<style>


.dash-card-dark {
background: white;
border: 1px solid #e5e7eb;
border-radius: 12px;
padding: 20px;
margin-bottom: 25px;
box-shadow: 0 2px 8px rgba(0,0,0,0.08);
}

.dash-dark-title {
margin: 0 0 18px 0;
font-size: 18px;
font-weight: 700;
color: #111827;
}


.seller-row {
display: flex;
align-items: center;
justify-content: space-between;
gap: 14px;
padding: 14px 16px;
margin-bottom: 10px;
background: #f9fafb; 
border: 1px solid #e5e7eb;
border-radius: 12px;
}

.seller-rank {
width: 36px;
height: 36px;
border-radius: 50%;
display: flex;
align-items: center;
justify-content: center;
font-size: 14px;
font-weight: 800;
flex-shrink: 0;
color: #111827;
}

.seller-rank.rank-1 {
background: linear-gradient(135deg, #f59e0b, #fbbf24);
}

.seller-rank.rank-2 {
background: linear-gradient(135deg, #94a3b8, #cbd5e1);
}

.seller-rank.rank-3 {
background: linear-gradient(135deg, #f97316, #fb923c);
}

.seller-rank.rank-default {
background: #e5e7eb; 
color: #6b7280;
}

.seller-info {
flex: 1;
}

.seller-name {
font-size: 15px;
font-weight: 700;
color: #111827; 
}

.seller-count {
font-size: 13px;
color: #6b7280;
margin-top: 2px;
}

.seller-revenue {
font-size: 18px;
font-weight: 700;
color: #22c55e;
white-space: nowrap;
}


.line-chart-container {
width: 100%;
overflow-x: auto;
background: #ffffff; 
border-radius: 12px;
padding: 10px;
}

.line-chart-svg {
width: 100%;
height: auto;
display: block;
}


@media (max-width: 768px) {
.seller-row {
flex-wrap: wrap;
align-items: flex-start;
}

```
.seller-revenue {
    width: 100%;
    margin-left: 50px;
    margin-top: 6px;
}
```

}
</style>
</head>
<body>

<div class="layout"> <!-- Main page layout -->
    <div class="sidebar"> <!-- Sidebar navigation -->
        <h2>POS Admin</h2>
        <p class="sidebar-subtitle"><?php echo htmlspecialchars($loggedInRole); ?></p>

        <!-- Navigation links -->
        <a href="dashboard.php" class="active">Dashboard</a>
        <a href="index.php">POS / Sales</a>

        <!-- Show reports link only if user has permission -->
        <?php if ($canViewReports): ?>
            <a href="reports.php">Reports</a>
        <?php endif; ?>

        <a href="Inventory.php">Inventory</a>

        <!-- Show suppliers link only if user has permission -->
        <?php if ($canViewReports): ?>
            <a href="suppliers.php">Suppliers</a>
        <?php endif; ?>

        <a href="ai_assistant.php">AI Assistant</a>

        <!-- Show users page only for Administrator -->
        <?php if ($canManageUsers): ?>
            <a href="users.php">Users / Staff</a>
        <?php endif; ?>

        <a href="logout.php">Logout</a>
    </div>

    <div class="main"> <!-- Main dashboard content -->
        <div class="header-box"> <!-- Header section -->
            <h1>POS Admin Dashboard</h1>
            <p><strong>Logged in as:</strong> <?php echo htmlspecialchars($loggedInAdmin); ?></p>
            <p><strong>Terminal:</strong> <?php echo htmlspecialchars($loggedInTerminal); ?></p>
            <p><strong>Role:</strong> <?php echo htmlspecialchars($loggedInRole); ?></p>
            <p>Live transaction analytics from the POS database.</p>
        </div>

        <div class="kpi-grid"> <!-- KPI cards section -->
            <div class="kpi-card">
                <h3>Total Transactions</h3>
                <div class="value"><?php echo number_format($totalTransactions); ?></div>
            </div>

            <div class="kpi-card">
                <h3>Today's Sales</h3>
                <div class="value">£<?php echo number_format($todaysSales, 2); ?></div>
            </div>

            <div class="kpi-card">
                <h3>Transactions Today</h3>
                <div class="value"><?php echo number_format($transactionsToday); ?></div>
            </div>

            <div class="kpi-card">
                <h3>Highest Transaction</h3>
                <div class="value">£<?php echo number_format($highestTransaction, 2); ?></div>
            </div>

            <div class="kpi-card">
                <h3>Your Top Product</h3>
                <div class="value" style="font-size:22px;">
                    <?php echo htmlspecialchars($topProductName); ?>
                </div>
                <div class="subtext">
                    Sold <?php echo number_format($topProductCount); ?> time(s)
                </div>
            </div>

            <div class="kpi-card">
                <h3>Top Admin (By Transactions)</h3>
                <div class="value" style="font-size:22px;">
                    <?php echo htmlspecialchars($topAdminName); ?>
                </div>
                <div class="subtext">
                    Orders: <?php echo number_format($topAdminOrders); ?>
                </div>
            </div>
        </div>

        <div class="section-grid"> <!-- Analytics tables section -->
            <div class="table-card">
                <h2>Transactions by Staff</h2>
                <table>
                    <thead>
                        <tr>
                            <th>Admin</th>
                            <th>Transactions</th>
                            <th>Total Revenue</th>
                        </tr>
                    </thead>
                    <tbody>
                        <!-- Loop through staff transaction data -->
                        <?php foreach ($transactionsByAdmin as $row) { ?>
                        <tr>
                            <td><?php echo htmlspecialchars($row["admin_username"]); ?></td>
                            <td><?php echo number_format((int)$row["transactions"]); ?></td>
                            <td>£<?php echo number_format((float)$row["revenue"], 2); ?></td>
                        </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>

            <div class="table-card">
                <h2>Transactions by Terminal</h2>
                <table>
                    <thead>
                        <tr>
                            <th>Terminal</th>
                            <th>Transactions</th>
                            <th>Total Revenue</th>
                        </tr>
                    </thead>
                    <tbody>
                        <!-- Loop through terminal transaction data -->
                        <?php foreach ($transactionsByTerminal as $row) { ?>
                        <tr>
                            <td><?php echo htmlspecialchars($row["terminal"]); ?></td>
                            <td><?php echo number_format((int)$row["transactions"]); ?></td>
                            <td>£<?php echo number_format((float)$row["revenue"], 2); ?></td>
                        </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="section-grid"> <!-- Dashboard visual sections -->
            <div class="dash-card-dark">
                <h2 class="dash-dark-title">🏆 Today's Top Sellers</h2>

                <!-- Display top sellers if available -->
                <?php if (count($topSellers) > 0): ?>
                    <?php $rank = 0; ?>

                    <!-- Loop through top sellers -->
                    <?php foreach ($topSellers as $seller): ?>
                        <?php
                            // Increase rank number
                            $rank++;

                            // Assign rank styling class
                            $rankClass = $rank <= 3 ? "rank-" . $rank : "rank-default";
                        ?>

                        <div class="seller-row">
                            <div class="seller-rank <?php echo $rankClass; ?>"><?php echo $rank; ?></div>
                            <div class="seller-info">
                                <div class="seller-name"><?php echo htmlspecialchars($seller["admin_username"]); ?></div>
                                <div class="seller-count"><?php echo (int)$seller["total_transactions"]; ?> transactions</div>
                            </div>
                            <div class="seller-revenue">£<?php echo number_format((float)$seller["total_revenue"], 2); ?></div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <!-- Message shown when no seller data exists -->
                    <p style="color: #94a3b8; padding: 20px 0;">No transaction data available yet.</p>
                <?php endif; ?>
            </div>

            <div class="dash-card-dark">
                <h2 class="dash-dark-title">Sales Over Time</h2>

                <!-- SVG line chart container -->
                <div class="line-chart-container">
                    <svg viewBox="0 0 500 280" class="line-chart-svg">

                        <!-- Draw horizontal grid lines and y-axis labels -->
                        <?php for ($i = 0; $i <= 4; $i++): ?>
                            <?php $y = 30 + ($i * 55); ?>
                            <line x1="60" y1="<?php echo $y; ?>" x2="480" y2="<?php echo $y; ?>" stroke="#334155" stroke-width="0.8" stroke-dasharray="4,4" />
                            <text x="50" y="<?php echo $y + 4; ?>" fill="#94a3b8" font-size="11" text-anchor="end">£<?php echo number_format($maxSale * (4 - $i) / 4, 0); ?></text>
                        <?php endfor; ?>

                        <!-- Draw chart axes -->
                        <line x1="60" y1="30" x2="60" y2="250" stroke="#475569" stroke-width="1" />
                        <line x1="60" y1="250" x2="480" y2="250" stroke="#475569" stroke-width="1" />

                        <?php
                            // Calculate chart spacing and points
                            $totalPoints = count($chartValues);
                            $spacing = 420 / max($totalPoints - 1, 1);
                            $points = [];

                            // Convert sales values into SVG coordinates
                            foreach ($chartValues as $idx => $val) {
                                $x = 60 + ($idx * $spacing);
                                $y = 250 - (($val / $maxSale) * 220);
                                $points[] = $x . "," . $y;
                            }

                            // Build polyline points
                            $polyline = implode(" ", $points);

                            // Get final x position for filled chart area
                            $lastX = 60 + (($totalPoints - 1) * $spacing);
                        ?>

                        <!-- Filled area under line chart -->
                        <polygon
                            points="60,250 <?php echo $polyline; ?> <?php echo $lastX; ?>,250"
                            fill="url(#chartGradient)"
                            opacity="0.18"
                        />

                        <!-- Line chart stroke -->
                        <polyline
                            points="<?php echo $polyline; ?>"
                            fill="none"
                            stroke="#3b82f6"
                            stroke-width="3"
                            stroke-linejoin="round"
                            stroke-linecap="round"
                        />

                        <!-- Draw chart points and x-axis labels -->
                        <?php foreach ($chartValues as $idx => $val): ?>
                            <?php
                                $x = 60 + ($idx * $spacing);
                                $y = 250 - (($val / $maxSale) * 220);
                            ?>
                            <circle cx="<?php echo $x; ?>" cy="<?php echo $y; ?>" r="4.5" fill="#3b82f6" stroke="#111827" stroke-width="2" />
                            <text x="<?php echo $x; ?>" y="268" fill="#94a3b8" font-size="11" text-anchor="middle"><?php echo htmlspecialchars($chartLabels[$idx]); ?></text>
                        <?php endforeach; ?>

                        <!-- SVG gradient definition -->
                        <defs>
                            <linearGradient id="chartGradient" x1="0" y1="0" x2="0" y2="1">
                                <stop offset="0%" stop-color="#3b82f6" stop-opacity="0.45" />
                                <stop offset="100%" stop-color="#3b82f6" stop-opacity="0" />
                            </linearGradient>
                        </defs>
                    </svg>
                </div>
            </div>
        </div>
    </div>
</div>

</body>
</html>