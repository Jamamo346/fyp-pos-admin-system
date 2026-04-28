<?php
// Start session to track logged-in admin and terminal
session_start();

// Check if admin and terminal session variables exist
if (!isset($_SESSION["admin_username"]) || !isset($_SESSION["terminal"])) {
    header("Location: login.html"); // Redirect to login if not authenticated
    exit; // Stop script execution
}

// Include database connection
require_once "db.php";

// Store session values for current admin and terminal
$admin = $_SESSION["admin_username"]; // Logged-in admin username
$terminal = $_SESSION["terminal"]; // Assigned terminal ID

/* ===============================
   LOAD PRODUCTS FROM DATABASE
   UPDATED: PRODUCTS NOW LINK TO SUPPLIERS
================================*/

// Array to store product data
$productOptions = [];

// Query to retrieve products along with supplier information
$productResult = $mysqli->query("
    SELECT 
        p.prodName,                
        p.prodPrice,              
        p.prodQuantity,           
        p.category,                
        p.image,                   
        COALESCE(s.company_name, 'No supplier assigned') AS supplier_name -- Supplier name or fallback
    FROM Product p
    LEFT JOIN suppliers s ON p.supplier_id = s.supplier_id -- Join suppliers table
    ORDER BY p.prodName ASC -- Sort products alphabetically
");

// Loop through results and store each product
if ($productResult) {
    while ($row = $productResult->fetch_assoc()) {
        $productOptions[] = $row; // Add product to array
    }
}


   //DEMO CUSTOMERS FOR REALISM


// Static list of demo customers used for testing transactions
$customers = [
    [
        "full_name" => "John Doe",       // Full display name
        "first_name" => "John",          // First name
        "last_name" => "Doe",            // Last name
        "email" => "john@example.com"   // Email address
    ],
    [
        "full_name" => "Jane Smith",
        "first_name" => "Jane",
        "last_name" => "Smith",
        "email" => "jane@example.com"
    ],
    [
        "full_name" => "Michael Brown",
        "first_name" => "Michael",
        "last_name" => "Brown",
        "email" => "michael@example.com"
    ],
    [
        "full_name" => "Sarah Johnson",
        "first_name" => "Sarah",
        "last_name" => "Johnson",
        "email" => "sarah@example.com"
    ]
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>POS / Sales</title>
<link rel="stylesheet" href="style.css">

<style>
body.pos-modern-page {
    margin: 0;
    font-family: Arial, sans-serif;
    background: #0b1220;
    color: #f8fafc;
}

.pos-page-shell {
    min-height: 100vh;
    padding: 32px;
    box-sizing: border-box;
}

.pos-modern-main {
    box-sizing: border-box;
}

.pos-modern-grid {
    display: grid;
    grid-template-columns: minmax(0, 1fr) 360px;
    gap: 24px;
    align-items: start;
}

.pos-top-title h1 {
    margin: 0 0 8px 0;
    font-size: 30px;
    color: #ffffff;
}

.pos-top-title p {
    margin: 0 0 18px 0;
    color: #94a3b8;
    font-size: 16px;
}

.pos-top-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 30px;
    margin-bottom: 22px;
    color: #e2e8f0;
    font-size: 15px;
}

.pos-search {
    margin-bottom: 22px;
}

.pos-search input {
    width: 100%;
    padding: 14px 16px;
    border-radius: 12px;
    border: 1px solid #334155;
    background: #111827;
    color: #ffffff;
    outline: none;
    font-size: 15px;
    box-sizing: border-box;
}

.pos-product-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 18px;
}

.pos-product-card {
    background: #1e293b;
    border: 1px solid #334155;
    border-radius: 18px;
    padding: 14px;
    cursor: pointer;
    transition: transform 0.18s ease, border-color 0.18s ease, box-shadow 0.18s ease;
    box-sizing: border-box;
}

.pos-product-card:hover {
    transform: translateY(-2px);
    border-color: #3b82f6;
    box-shadow: 0 8px 24px rgba(0,0,0,0.18);
}

.pos-product-card.selected {
    border-color: #3b82f6;
    box-shadow: 0 0 0 2px rgba(59,130,246,0.18);
}

.pos-product-card img {
    width: 100%;
    height: 140px;
    object-fit: contain;
    border-radius: 12px;
    background: #0f172a;
}

.pos-product-card h3 {
    margin: 0 0 8px 0;
    font-size: 17px;
    color: #ffffff;
}

.pos-price {
    margin: 0 0 10px 0;
    color: #22c55e;
    font-size: 28px;
    font-weight: 700;
}

.pos-card-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 10px;
}

.pos-category-badge,
.pos-stock-badge {
    display: inline-block;
    padding: 7px 10px;
    border-radius: 999px;
    font-size: 12px;
    font-weight: 600;
}

.pos-category-badge {
    background: #253248;
    color: #cbd5e1;
}

.pos-stock-badge {
    background: #2a2a2a;
    color: #ffffff;
}

.current-sale-panel {
    background: #1e293b;
    border: 1px solid #334155;
    border-radius: 22px;
    padding: 22px;
    box-sizing: border-box;
    position: sticky;
    top: 24px;
}

.current-sale-panel h2 {
    margin: 0 0 24px 0;
    font-size: 18px;
    color: #ffffff;
}

.current-sale-panel label {
    display: block;
    margin-bottom: 10px;
    font-weight: 700;
    color: #cbd5e1;
    font-size: 14px;
}

.current-sale-panel select,
.current-sale-panel input {
    width: 100%;
    padding: 14px 14px;
    border-radius: 12px;
    border: 1px solid #475569;
    background: #1f2937;
    color: #ffffff;
    box-sizing: border-box;
    margin-bottom: 16px;
    outline: none;
    font-size: 15px;
}

.sale-selected-item {
    background: #3a475d;
    border-radius: 12px;
    padding: 14px;
    margin-bottom: 12px;
}

.sale-selected-top {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 12px;
}

.sale-selected-top .left h3 {
    margin: 0 0 4px 0;
    font-size: 18px;
    color: #ffffff;
}

.sale-selected-top .left p {
    margin: 0;
    font-size: 14px;
    color: #cbd5e1;
}

.qty-controls {
    display: flex;
    align-items: center;
    gap: 12px;
}

.qty-btn {
    width: 34px;
    height: 34px;
    border-radius: 10px;
    border: 1px solid #1f2937;
    background: #374151;
    color: #ffffff;
    font-size: 20px;
    cursor: pointer;
}

.qty-value {
    min-width: 20px;
    text-align: center;
    font-weight: 700;
    color: #ffffff;
}

.sale-divider {
    height: 1px;
    background: #475569;
    margin: 18px 0;
}

.sale-totals p,
.sale-totals h3 {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin: 0 0 14px 0;
    color: #e2e8f0;
}

.sale-totals h3 {
    font-size: 22px;
    color: #ffffff;
    margin-top: 8px;
}

.card-payment-box {
    margin-top: 8px;
    margin-bottom: 18px;
}

.card-payment-single {
    display: flex;
    justify-content: center;
    align-items: center;
    padding: 15px 18px;
    border-radius: 12px;
    border: 1px solid #374151;
    background: #111827;
    color: #ffffff;
    font-weight: 700;
}

.complete-sale-btn {
    width: 100%;
    padding: 16px;
    border: none;
    border-radius: 12px;
    background: #16a34a;
    color: #07130a;
    font-size: 16px;
    font-weight: 700;
    cursor: pointer;
}

.complete-sale-btn:hover {
    background: #22c55e;
}

.sale-empty {
    color: #94a3b8;
    font-size: 15px;
    margin-bottom: 16px;
}

.hidden-inputs {
    display: none;
}

@media (max-width: 1200px) {
    .pos-product-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

@media (max-width: 900px) {
    .pos-modern-grid {
        grid-template-columns: 1fr;
    }

    .current-sale-panel {
        position: static;
    }
}

@media (max-width: 640px) {
    .pos-product-grid {
        grid-template-columns: 1fr;
    }

    .pos-page-shell {
        padding: 18px;
    }
}
</style>
</head>
<body class="pos-modern-page">

<div class="pos-page-shell">

    <main class="pos-modern-main">
        <div class="pos-modern-grid">

            <!-- Product selection section -->
            <section>
                <div class="pos-top-title">
                    <h1>Point of Sale</h1>
                    <p>POS administration interface for secure cloud-based transaction submission</p>
                </div>

                <!-- Display POS session details -->
                <div class="pos-top-meta">
                    <div><strong>Terminal ID:</strong> <?php echo htmlspecialchars($terminal); ?></div>
                    <div><strong>Transaction Type:</strong> Card Payment</div>
                    <div><strong>Admin:</strong> <?php echo htmlspecialchars($admin); ?></div>
                </div>

                <!-- Dashboard navigation button -->
                <div style="margin-bottom: 16px;">
                    <a href="dashboard.php" class="back-btn">Back to Dashboard</a>
                </div>

                <!-- Product search input -->
                <div class="pos-search">
                    <input type="text" id="productSearch" placeholder="Search products...">
                </div>

                <!-- Product grid -->
                <div class="pos-product-grid" id="productGrid">
                    <?php foreach ($productOptions as $product): ?>
                        <?php
                        // Store product values from database
                        $prodName = $product["prodName"];
                        $prodPrice = number_format((float)$product["prodPrice"], 2, '.', '');
                        $prodQuantity = (int)$product["prodQuantity"];
                        $category = $product["category"] ?? "General";
                        $supplierName = $product["supplier_name"] ?? "No supplier assigned";
                        $image = trim((string)($product["image"] ?? ""));
                        $imagePath = $image !== "" ? rawurlencode($image) : "";
                        ?>

                        <!-- Product card with product data attributes -->
                        <div
                            class="pos-product-card"
                            data-name="<?php echo htmlspecialchars($prodName); ?>"
                            data-price="<?php echo htmlspecialchars($prodPrice); ?>"
                            data-stock="<?php echo htmlspecialchars((string)$prodQuantity); ?>"
                            data-category="<?php echo htmlspecialchars($category); ?>"
                            data-supplier="<?php echo htmlspecialchars($supplierName); ?>"
                            data-image="<?php echo htmlspecialchars($imagePath); ?>"
                        >
                            <!-- Product image -->
                            <?php if ($imagePath !== ""): ?>
                                <img src="<?php echo htmlspecialchars($imagePath); ?>" alt="<?php echo htmlspecialchars($prodName); ?>">
                            <?php else: ?>
                                <img src="" alt="<?php echo htmlspecialchars($prodName); ?>">
                            <?php endif; ?>

                            <!-- Product details -->
                            <h3><?php echo htmlspecialchars($prodName); ?></h3>
                            <p class="pos-price">£<?php echo htmlspecialchars($prodPrice); ?></p>

                            <p>Supplier: <?php echo htmlspecialchars($supplierName); ?></p>

                            <!-- Product category and stock display -->
                            <div class="pos-card-row">
                                <span class="pos-category-badge"><?php echo htmlspecialchars($category); ?></span>
                                <span class="pos-stock-badge"><?php echo $prodQuantity; ?> in stock</span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>

            <!-- Current sale panel -->
            <aside class="current-sale-panel">
                <h2>🛒 Current Sale</h2>

                <!-- Sale submission form -->
                <form action="submit.php" method="POST" id="saleForm">
                    <label for="customer_select">Customer</label>

                    <!-- Customer dropdown -->
                    <select id="customer_select">
                        <?php foreach ($customers as $customer): ?>
                            <option
                                value="<?php echo htmlspecialchars($customer["full_name"]); ?>"
                                data-first="<?php echo htmlspecialchars($customer["first_name"]); ?>"
                                data-last="<?php echo htmlspecialchars($customer["last_name"]); ?>"
                                data-email="<?php echo htmlspecialchars($customer["email"]); ?>"
                            >
                                <?php echo htmlspecialchars($customer["full_name"]); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <!-- Selected cart items appear here -->
                    <div id="selectedItemWrapper" class="sale-empty">
                        Select a product to begin the sale.
                    </div>

                    <div class="sale-divider"></div>

                    <!-- Sale totals -->
                    <div class="sale-totals">
                        <p><span>Subtotal:</span> <span>£<span id="subtotal">0.00</span></span></p>
                        <h3><span>Total:</span> <span>£<span id="total">0.00</span></span></h3>
                    </div>

                    <label>Payment Method</label>

                    <!-- Fixed payment method -->
                    <div class="card-payment-box">
                        <div class="card-payment-single">Card Payment Only</div>
                    </div>

                    <!-- Visible card payment fields -->
                    <label for="cardholder_name_visible">Cardholder Name</label>
                    <input type="text" id="cardholder_name_visible" placeholder="Enter cardholder name" required>

                    <label for="card_number_visible">Card Number</label>
                    <input type="text" id="card_number_visible" placeholder="Enter 16 digits" maxlength="16" pattern="\d{16}" required>

                    <label for="expiry_visible">Expiry Date</label>
                    <input type="text" id="expiry_visible" placeholder="MM/YYYY" pattern="(0[1-9]|1[0-2])\/\d{4}" required>

                    <label for="cvv_visible">CVV</label>
                    <input type="password" id="cvv_visible" maxlength="3" pattern="\d{3}" required>

                    <!-- Hidden fields submitted to submit.php -->
                    <div class="hidden-inputs">
                        <input type="hidden" name="first_name" id="first_name">
                        <input type="hidden" name="last_name" id="last_name">
                        <input type="hidden" name="email" id="email">
                        <input type="hidden" name="cardholder_name" id="cardholder_name">
                        <input type="hidden" name="card_number" id="card_number">
                        <input type="hidden" name="expiry" id="expiry">
                        <input type="hidden" name="cvv" id="cvv">
                        <input type="hidden" name="product_type" id="product_type">
                        <input type="hidden" name="amount" id="amount">
                        <input type="hidden" name="quantity" id="quantity" value="1">
                        <input type="hidden" name="basket_products" id="basket_products">
                    </div>

                    <!-- Submit payment button -->
                    <button type="submit" class="complete-sale-btn">Complete Secure Card Payment</button>
                </form>
            </aside>

        </div>
    </main>

</div>

<script>
// Select all product cards
const productCards = document.querySelectorAll(".pos-product-card");

// Get cart display and total elements
const selectedItemWrapper = document.getElementById("selectedItemWrapper");
const subtotalEl = document.getElementById("subtotal");
const totalEl = document.getElementById("total");

// Get customer form elements
const customerSelect = document.getElementById("customer_select");
const firstNameInput = document.getElementById("first_name");
const lastNameInput = document.getElementById("last_name");
const emailInput = document.getElementById("email");

// Get visible card input fields
const cardholderVisible = document.getElementById("cardholder_name_visible");
const cardNumberVisible = document.getElementById("card_number_visible");
const expiryVisible = document.getElementById("expiry_visible");
const cvvVisible = document.getElementById("cvv_visible");

// Get hidden card input fields
const cardholderInput = document.getElementById("cardholder_name");
const cardNumberInput = document.getElementById("card_number");
const expiryInput = document.getElementById("expiry");
const cvvInput = document.getElementById("cvv");

// Get hidden transaction fields
const productTypeInput = document.getElementById("product_type");
const amountInput = document.getElementById("amount");
const quantityInput = document.getElementById("quantity");
const basketProductsInput = document.getElementById("basket_products");

// Get product search input
const productSearch = document.getElementById("productSearch");

// Cart object stores selected products
let cart = {};

// Copy selected customer data into hidden fields
function syncCustomerFields() {
    const selected = customerSelect.options[customerSelect.selectedIndex];
    firstNameInput.value = selected.getAttribute("data-first") || "";
    lastNameInput.value = selected.getAttribute("data-last") || "";
    emailInput.value = selected.getAttribute("data-email") || "";
}

// Copy visible card input values into hidden fields
function syncCardFields() {
    cardholderInput.value = cardholderVisible.value.trim();
    cardNumberInput.value = cardNumberVisible.value.trim();
    expiryInput.value = expiryVisible.value.trim();
    cvvInput.value = cvvVisible.value.trim();
}

// Highlight selected product cards
function updateCardSelections() {
    productCards.forEach(card => {
        const name = card.getAttribute("data-name");
        if (cart[name]) {
            card.classList.add("selected");
        } else {
            card.classList.remove("selected");
        }
    });
}

// Calculate subtotal, total, quantity and product summary
function calculateTotals() {
    let subtotal = 0;
    let totalQuantity = 0;
    let cartSummary = [];

    Object.values(cart).forEach(item => {
        subtotal += item.price * item.quantity;
        totalQuantity += item.quantity;
        cartSummary.push(`${item.name} x${item.quantity}`);
    });

    subtotalEl.textContent = subtotal.toFixed(2);
    totalEl.textContent = subtotal.toFixed(2);

    amountInput.value = subtotal > 0 ? subtotal.toFixed(2) : "";
    quantityInput.value = totalQuantity > 0 ? String(totalQuantity) : "0";
    productTypeInput.value = cartSummary.join(", ");
}

// Render selected cart items in the sale panel
function renderCart() {
    const items = Object.values(cart);

    if (items.length === 0) {
        selectedItemWrapper.className = "sale-empty";
        selectedItemWrapper.innerHTML = "Select a product to begin the sale.";
        return;
    }

    selectedItemWrapper.className = "";
    selectedItemWrapper.innerHTML = items.map(item => `
        <div class="sale-selected-item">
            <div class="sale-selected-top">
                <div class="left">
                    <h3>${item.name}</h3>
                    <p>£${item.price.toFixed(2)} each</p>
                </div>
                <div class="qty-controls">
                    <button type="button" class="qty-btn minus-btn" data-name="${item.name}">-</button>
                    <span class="qty-value">${item.quantity}</span>
                    <button type="button" class="qty-btn plus-btn" data-name="${item.name}">+</button>
                </div>
            </div>
        </div>
    `).join("");

    // Add event listeners for minus buttons
    document.querySelectorAll(".minus-btn").forEach(btn => {
        btn.addEventListener("click", function () {
            const name = this.getAttribute("data-name");
            if (!cart[name]) return;

            cart[name].quantity--;

            if (cart[name].quantity <= 0) {
                delete cart[name];
            }

            renderCart();
            calculateTotals();
            updateCardSelections();
        });
    });

    // Add event listeners for plus buttons
    document.querySelectorAll(".plus-btn").forEach(btn => {
        btn.addEventListener("click", function () {
            const name = this.getAttribute("data-name");
            if (!cart[name]) return;

            if (cart[name].quantity < cart[name].stock) {
                cart[name].quantity++;
            }

            renderCart();
            calculateTotals();
            updateCardSelections();
        });
    });
}

// Add selected product to cart when product card is clicked
productCards.forEach(card => {
    card.addEventListener("click", function () {
        const name = card.getAttribute("data-name");
        const price = parseFloat(card.getAttribute("data-price"));
        const stock = parseInt(card.getAttribute("data-stock"), 10);

        if (!cart[name]) {
            cart[name] = {
                name: name,
                price: price,
                stock: stock,
                quantity: 1
            };
        } else {
            if (cart[name].quantity < cart[name].stock) {
                cart[name].quantity++;
            }
        }

        renderCart();
        calculateTotals();
        updateCardSelections();
    });
});

// Update customer hidden fields when customer changes
customerSelect.addEventListener("change", syncCustomerFields);

// Update hidden card fields while typing
cardholderVisible.addEventListener("input", syncCardFields);
cardNumberVisible.addEventListener("input", syncCardFields);
expiryVisible.addEventListener("input", syncCardFields);
cvvVisible.addEventListener("input", syncCardFields);

// Validate sale form before submission
document.getElementById("saleForm").addEventListener("submit", function (e) {
    syncCustomerFields();
    syncCardFields();

    if (Object.keys(cart).length === 0) {
        e.preventDefault();
        alert("Please select at least one product.");
        return;
    }

    if (cardholderInput.value === "" || cardNumberInput.value === "" || expiryInput.value === "" || cvvInput.value === "") {
        e.preventDefault();
        alert("Please complete the card payment fields.");
        return;
    }

    // Convert cart into JSON for submit.php
    const basketArray = Object.values(cart).map(item => ({
        name: item.name,
        quantity: item.quantity
    }));

    basketProductsInput.value = JSON.stringify(basketArray);
});

// Search products by name, category, or supplier
productSearch.addEventListener("input", function () {
    const query = productSearch.value.toLowerCase().trim();

    productCards.forEach(card => {
        const name = card.getAttribute("data-name").toLowerCase();
        const category = card.getAttribute("data-category").toLowerCase();
        const supplier = card.getAttribute("data-supplier").toLowerCase();

        if (name.includes(query) || category.includes(query) || supplier.includes(query)) {
            card.style.display = "";
        } else {
            card.style.display = "none";
        }
    });
});

// Initialise form values and totals
syncCustomerFields();
syncCardFields();
calculateTotals();
updateCardSelections();
</script>
</body>
</html>