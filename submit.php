<?php
// Start session to track logged-in admin
session_start();

// Include database connection file
require_once "db.php";

// Check if admin session exists, otherwise redirect to login
if (!isset($_SESSION["admin_username"]) || !isset($_SESSION["terminal"])) {
    header("Location: login.html"); // Redirect if not logged in
    exit; // Stop script execution
}

// Store session values into variables
$admin = $_SESSION["admin_username"]; // Logged-in admin username
$terminal = $_SESSION["terminal"]; // Terminal ID or name
$transactionType = "Card Payment"; // Transaction type label

// Only allow POST requests (form submission)
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: index.php"); // Redirect if accessed incorrectly
    exit; // Stop script
}

// Retrieve and trim form inputs
$firstName = trim($_POST["first_name"] ?? ""); // Customer first name
$lastName = trim($_POST["last_name"] ?? ""); // Customer last name
$email = trim($_POST["email"] ?? ""); // Customer email
$cardNumber = trim($_POST["card_number"] ?? ""); // Full card number
$cardholderName = trim($_POST["cardholder_name"] ?? ""); // Name on card
$expiry = trim($_POST["expiry"] ?? ""); // Card expiry date
$cvv = trim($_POST["cvv"] ?? ""); // Card CVV
$amount = trim($_POST["amount"] ?? ""); // Transaction amount


// Raw basket data received from form
$basketProductsRaw = $_POST["basket_products"] ?? "";

// Combine first and last name into full customer name
$customerName = trim($firstName . " " . $lastName);

// Extract last 4 digits of card for storage/display
$cardLast4 = substr($cardNumber, -4);

// Validate required fields (ensure none are empty)
if (
    $firstName === "" ||
    $lastName === "" ||
    $email === "" ||
    $cardNumber === "" ||
    $cardholderName === "" ||
    $expiry === "" ||
    $cvv === "" ||
    $amount === "" ||
    $basketProductsRaw === ""
) {
    die("All fields are required."); // Stop if validation fails
}

// Convert JSON basket into PHP array
$basketProducts = json_decode($basketProductsRaw, true);

// Ensure basket is valid and not empty
if (!is_array($basketProducts) || count($basketProducts) === 0) {
    die("No basket items were received.");
}


   //VALIDATE BASKET ITEMS


// Loop through each basket item and validate structure
foreach ($basketProducts as $item) {
    if (
        !isset($item["name"]) || // Check product name exists
        !isset($item["quantity"]) || // Check quantity exists
        trim($item["name"]) === "" || // Ensure name is not empty
        (int)$item["quantity"] <= 0 // Ensure quantity is valid
    ) {
        die("Invalid basket item data."); // Stop if invalid
    }
}


   //CHECK STOCK FOR EACH ITEM


// Prepare SQL statement to check stock
$stockStmt = $mysqli->prepare("
    SELECT prodQuantity
    FROM Product
    WHERE prodName = ?
    LIMIT 1
");

// Check if statement preparation failed
if (!$stockStmt) {
    die("Prepare failed (stock check): " . $mysqli->error);
}

// Array to store stock values
$stockMap = [];

// Loop through each item to check stock availability
foreach ($basketProducts as $item) {
    $productName = trim($item["name"]); // Product name
    $qtyNeeded = (int)$item["quantity"]; // Quantity required

    $stockStmt->bind_param("s", $productName); // Bind product name
    $stockStmt->execute(); // Execute query
    $stockResult = $stockStmt->get_result(); // Get result

    // Check if product exists in database
    if (!$stockResult || !($row = $stockResult->fetch_assoc())) {
        $stockStmt->close();
        die("Selected product was not found in the Product table: " . htmlspecialchars($productName));
    }

    $currentStock = (int)$row["prodQuantity"]; 

    // Check if enough stock is available
    if ($currentStock < $qtyNeeded) {
        $stockStmt->close();
        die("Not enough stock for " . htmlspecialchars($productName) . ". Available: " . $currentStock);
    }

    // Store stock for reference
    $stockMap[$productName] = $currentStock;
}

// Close stock check statement
$stockStmt->close();


   //BUILD PRODUCT SUMMARY


// Array to store product summary strings
$productSummaryParts = [];

// Counter for total quantity
$totalQuantity = 0;

// Build summary string and calculate total items
foreach ($basketProducts as $item) {
    $productName = trim($item["name"]); // Product name
    $qty = (int)$item["quantity"]; // Quantity

    $productSummaryParts[] = $productName . " x" . $qty; // Add to summary
    $totalQuantity += $qty; // Add to total count
}

// Convert array to comma-separated string
$productType = implode(", ", $productSummaryParts);


/*INSERT TRANSACTION*/

// Prepare SQL insert statement for transaction
$stmt = $mysqli->prepare("
    INSERT INTO transactions
    (admin_username, terminal, customer_name, email, amount, card_last4, cardholder_name, expiry, transaction_type, product_type, quantity)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
");

// Check if preparation failed
if (!$stmt) {
    die("Prepare failed: " . $mysqli->error);
}

// Bind parameters to insert query
$stmt->bind_param(
    "ssssdsssssi", // Data types
    $admin,
    $terminal,
    $customerName,
    $email,
    $amount,
    $cardLast4,
    $cardholderName,
    $expiry,
    $transactionType,
    $productType,
    $totalQuantity
);

// Execute insert query
if (!$stmt->execute()) {
    die("Execute failed: " . $stmt->error);
}

// Close statement
$stmt->close();


// Prepare SQL statement to update stock
$updateStmt = $mysqli->prepare("
    UPDATE Product
    SET prodQuantity = prodQuantity - ?
    WHERE prodName = ? AND prodQuantity >= ?
");

// Check preparation success
if (!$updateStmt) {
    die("Prepare failed (stock update): " . $mysqli->error);
}

// Loop through each item and reduce stock
foreach ($basketProducts as $item) {
    $productName = trim($item["name"]); // Product name
    $qtyNeeded = (int)$item["quantity"]; // Quantity to deduct

    $updateStmt->bind_param("isi", $qtyNeeded, $productName, $qtyNeeded);

    // Execute update query
    if (!$updateStmt->execute()) {
        $updateStmt->close();
        die("Stock update failed for " . htmlspecialchars($productName) . ": " . $updateStmt->error);
    }
}


$updateStmt->close();

// Mask card number for display (security)
$maskedCard = "**** **** **** " . htmlspecialchars($cardLast4);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"> 
<meta name="viewport" content="width=device-width, initial-scale=1.0"> 
<title>Order Placed | POS Admin</title> <!-- Page title -->

<style>

*{
    box-sizing:border-box;
}

/* Page body styling */
body{
    margin:0; 
    font-family:Arial, sans-serif; 
    background:linear-gradient(135deg,#000814,#001d3d); 
    color:white; 
    min-height:100vh; 
    padding:40px 20px; 
}

/* Main container */
.container{
    max-width:760px; 
    margin:auto; 
}

/* Card container styling */
.card{
    background:rgba(31,41,55,0.82); 
    border:1px solid rgba(255,255,255,0.08);
    border-radius:20px; 
    padding:28px; 
    box-shadow:0 10px 30px rgba(0,0,0,0.25); 
}

/* Main heading */
h1{
    margin:0 0 18px 0; 
    font-size:24px; 
    color:#ffffff;
}

/* Paragraph styling */
p{
    margin:0 0 14px 0; 
    font-size:16px; 
    color:#e5e7eb; 
}


p strong{
    color:#ffffff; 
}

/* Links container */
.result-links{
    display:flex; 
    justify-content:space-between; 
    margin-top:24px; 
    gap:20px; 
    flex-wrap:wrap; 
}

/* POS link styling */
.pos-link{
    color:#8b5cf6; 
    text-decoration:none; 
    font-size:14px; 
    font-weight:600; 
}

/* Hover effect for POS link */
.pos-link:hover{
    text-decoration:underline;
}

/* Dashboard link styling */
.dashboard-link{
    color:#60a5fa; /* Blue color */
    text-decoration:none;
    font-size:14px;
    font-weight:600;
}

/* Hover effect for dashboard link */
.dashboard-link:hover{
    text-decoration:underline;
}

/* Success message box */
.stock-note{
    margin-top:18px;
    padding:14px;
    border-radius:12px;
    background:rgba(34,197,94,0.12); 
    border:1px solid rgba(34,197,94,0.35);
    color:#dcfce7; 
}

/* Item list styling */
.item-list{
    margin-top:16px;
    padding-left:18px;
    color:#e5e7eb;
}

/* Each list item */
.item-list li{
    margin-bottom:8px;
}

/* Responsive design for small screens */
@media (max-width: 768px){
    .result-links{
        flex-direction:column; 
        gap:12px;
    }
}
</style>
</head>
<body>

<div class="container"> <!-- Main container -->
    <div class="card"> <!-- Card layout -->
        <h1>Order Placed Successfully</h1> 

        <!-- Display transaction details -->
        <p><strong>Terminal:</strong> <?php echo htmlspecialchars($terminal); ?></p>
        <p><strong>Staff:</strong> <?php echo htmlspecialchars($admin); ?></p>
        <p><strong>Type:</strong> <?php echo htmlspecialchars($transactionType); ?></p>
        <p><strong>Customer:</strong> <?php echo htmlspecialchars($customerName); ?></p>
        <p><strong>Email:</strong> <?php echo htmlspecialchars($email); ?></p>
        <p><strong>Amount (£):</strong> <?php echo number_format((float)$amount, 2); ?></p>
        <p><strong>Card:</strong> <?php echo $maskedCard; ?></p>
        <p><strong>Cardholder:</strong> <?php echo htmlspecialchars($cardholderName); ?></p>
        <p><strong>Expiry:</strong> <?php echo htmlspecialchars($expiry); ?></p>
        <p><strong>Total Items:</strong> <?php echo (int)$totalQuantity; ?></p>

        <!-- Success message -->
        <div class="stock-note">
            Thank you — your order has been placed and payment received.
        </div>

        <!-- Display purchased items -->
        <ul class="item-list">
            <?php foreach ($basketProducts as $item): ?>
                <li>
                    <?php echo htmlspecialchars($item["name"]); ?> × <?php echo (int)$item["quantity"]; ?>
                </li>
            <?php endforeach; ?>
        </ul>

        <!-- Navigation links -->
        <div class="result-links">
            <a class="pos-link" href="index.php">New Transaction</a>
            <a class="dashboard-link" href="dashboard.php">Back to Dashboard</a>
        </div>
    </div>
</div>

</body>
</html>