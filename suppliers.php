<?php
session_start();

if (!isset($_SESSION["admin_username"])) {
    header("Location: login.html");
    exit;
}

// Role-Based Access Control: only Administrator and Manager can access this page
$role = $_SESSION["role"] ?? "";
if (!in_array($role, ["Administrator", "Manager"], true)) {
    header("Location: dashboard.php");
    exit;
}

require_once "db.php";

$loggedInAdmin    = $_SESSION["admin_username"] ?? "Unknown";
$loggedInTerminal = $_SESSION["terminal"] ?? "Unknown";
$loggedInRole     = $_SESSION["role"] ?? "Unknown";
$canManageUsers   = ($loggedInRole === "Administrator");
$canViewReports   = in_array($loggedInRole, ["Administrator", "Manager"], true);


  /* CREATE SUPPLIERS TABLE IF NOT EXISTS */
$mysqli->query("
    CREATE TABLE IF NOT EXISTS suppliers (
        supplier_id INT AUTO_INCREMENT PRIMARY KEY,
        company_name VARCHAR(100) NOT NULL,
        contact_name VARCHAR(100) NOT NULL,
        email VARCHAR(150) DEFAULT '',
        phone VARCHAR(30) DEFAULT '',
        balance DECIMAL(10,2) DEFAULT 0.00,
        balance_type ENUM('Credit','Debt','Settled') DEFAULT 'Settled',
        total_purchases INT DEFAULT 0,
        last_purchase DATE DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");


   /* Suppliers are permanent records*/


   /*FETCH SUPPLIERS + LINKED PRODUCTS*/

$suppliers = [];

$result = $mysqli->query("
    SELECT 
        s.supplier_id,
        s.company_name,
        s.contact_name,
        s.email,
        s.phone,
        s.balance,
        s.balance_type,
        s.total_purchases,
        s.last_purchase,
        COUNT(p.prodName) AS product_count,
        GROUP_CONCAT(p.prodName ORDER BY p.prodName ASC SEPARATOR ', ') AS supplied_products
    FROM suppliers s
    LEFT JOIN Product p ON s.supplier_id = p.supplier_id
    GROUP BY 
        s.supplier_id,
        s.company_name,
        s.contact_name,
        s.email,
        s.phone,
        s.balance,
        s.balance_type,
        s.total_purchases,
        s.last_purchase
    ORDER BY s.company_name ASC
");

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $suppliers[] = $row;
    }
}

/* ---- KPIs ---- */
$totalSuppliers   = count($suppliers);
$outstandingBal   = 0;
$activeThisMonth  = 0;
$totalPurchases   = 0;

foreach ($suppliers as $s) {
    $outstandingBal += (float)$s["balance"];
    $totalPurchases += (int)$s["total_purchases"];

    if ($s["last_purchase"] && date("Y-m", strtotime($s["last_purchase"])) === date("Y-m")) {
        $activeThisMonth++;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Supplier Management | POS Admin</title>
<link rel="stylesheet" href="style.css">
</head>
<body>
<div class="layout">

    <!-- ===== SIDEBAR ===== -->
    <div class="sidebar">
        <h2>POS Admin</h2>
        <p class="sidebar-subtitle"><?php echo htmlspecialchars($loggedInRole); ?></p>
        <a href="dashboard.php">Dashboard</a>
        <a href="index.php">POS / Sales</a>
        <?php if ($canViewReports): ?>
            <a href="reports.php">Reports</a>
        <?php endif; ?>
        <a href="Inventory.php">Inventory</a>
        <?php if ($canViewReports): ?>
            <a href="suppliers.php" class="active">Suppliers</a>
        <?php endif; ?>
        <a href="ai_assistant.php">AI Assistant</a>
        <?php if ($canManageUsers): ?>
            <a href="users.php">Users / Staff</a>
        <?php endif; ?>
        <a href="logout.php">Logout</a>
    </div>

    <!-- ===== MAIN ===== -->
    <div class="main">

        <div class="header-box">
            <h1>Supplier Management</h1>
            <p><strong>Logged in as:</strong> <?php echo htmlspecialchars($loggedInAdmin); ?></p>
            <p><strong>Terminal:</strong> <?php echo htmlspecialchars($loggedInTerminal); ?></p>
            <p><strong>Role:</strong> <?php echo htmlspecialchars($loggedInRole); ?></p>
            <p>Manage your vendor relationships and the products supplied to the POS system.</p>
        </div>

        <!-- KPI Cards -->
        <div class="kpi-grid">
            <div class="kpi-card">
                <h3>Total Suppliers</h3>
                <div class="value"><?php echo number_format($totalSuppliers); ?></div>
            </div>
            <div class="kpi-card">
                <h3>Outstanding Balance</h3>
                <div class="value">£<?php echo number_format($outstandingBal, 2); ?></div>
            </div>
            <div class="kpi-card">
                <h3>Active This Month</h3>
                <div class="value"><?php echo number_format($activeThisMonth); ?></div>
            </div>
            <div class="kpi-card">
                <h3>Total Purchases</h3>
                <div class="value"><?php echo number_format($totalPurchases); ?></div>
            </div>
        </div>

        <!-- Suppliers Table -->
        <div class="table-card">
            <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px; margin-bottom: 16px;">
                <div>
                    <h2>Suppliers (<?php echo $totalSuppliers; ?>)</h2>
                    <p style="color:#6b7280; font-size:14px; margin:4px 0 0 0;">Vendor directory linked to the products sold through the POS system.</p>
                </div>

                <input type="text" id="supplierSearch" class="supplier-search" placeholder="Search suppliers, contacts or products...">
            </div>

            <?php if ($totalSuppliers > 0): ?>
            <div style="overflow-x:auto;">
                <table id="supplierTable">
                    <thead>
                        <tr>
                            <th>Company</th>
                            <th>Products Supplied</th>
                            <th>Contact</th>
                            <th>Balance</th>
                            <th>Purchases</th>
                            <th>Last Purchase</th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php foreach ($suppliers as $s): ?>
                        <?php
                            $balType  = $s["balance_type"];
                            $balClass = $balType === "Credit" ? "bal-credit" : ($balType === "Debt" ? "bal-debt" : "bal-settled");
                            $balLabel = $balType === "Settled" ? "Settled" : $balType . ": £" . number_format(abs((float)$s["balance"]), 2);
                            $lastPurchase = $s["last_purchase"] ? date("Y-m-d", strtotime($s["last_purchase"])) : "—";
                            $productCount = (int)($s["product_count"] ?? 0);
                            $suppliedProducts = $s["supplied_products"] ?? "";
                        ?>

                        <tr>
                            <td>
                                <strong><?php echo htmlspecialchars($s["company_name"]); ?></strong>
                            </td>

                            <td>
                                <?php if ($productCount > 0 && $suppliedProducts !== ""): ?>
                                    <div style="font-size:13px; color:#374151;">
                                        <?php echo htmlspecialchars($suppliedProducts); ?>
                                    </div>
                                    <div style="font-size:12px; color:#6b7280; margin-top:4px;">
                                        <?php echo number_format($productCount); ?> product(s)
                                    </div>
                                <?php else: ?>
                                    <span style="font-size:13px; color:#9ca3af;">No products assigned</span>
                                <?php endif; ?>
                            </td>

                            <td>
                                <div><?php echo htmlspecialchars($s["contact_name"]); ?></div>

                                <?php if ($s["email"]): ?>
                                    <div style="font-size:12px; color:#6b7280;">✉ <?php echo htmlspecialchars($s["email"]); ?></div>
                                <?php endif; ?>

                                <?php if ($s["phone"]): ?>
                                    <div style="font-size:12px; color:#6b7280;">✆ <?php echo htmlspecialchars($s["phone"]); ?></div>
                                <?php endif; ?>
                            </td>

                            <td>
                                <span class="bal-badge <?php echo $balClass; ?>">
                                    <?php echo $balLabel; ?>
                                </span>
                            </td>

                            <td><?php echo number_format((int)$s["total_purchases"]); ?></td>

                            <td><?php echo $lastPurchase; ?></td>
                        </tr>

                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
                <div style="padding:24px; border:1px dashed #d1d5db; border-radius:12px; background:#f9fafb; color:#6b7280; text-align:center;">
                    No suppliers found. Add your first supplier above.
                </div>
            <?php endif; ?>
        </div>

    </div>
</div>

<script>
/* Live search filter */
const searchInput = document.getElementById("supplierSearch");

if (searchInput) {
    searchInput.addEventListener("input", function () {
        const q = this.value.toLowerCase();
        const rows = document.querySelectorAll("#supplierTable tbody tr");

        rows.forEach(row => {
            row.style.display = row.textContent.toLowerCase().includes(q) ? "" : "none";
        });
    });
}
</script>

</body>
</html>
