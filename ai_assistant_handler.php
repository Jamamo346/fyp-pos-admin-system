<?php
declare(strict_types=1);

session_start();
require_once "db.php";

header("Content-Type: application/json");


// AUTHENTICATION CHECK

if (!isset($_SESSION["admin_username"])) {
    echo json_encode([
        "success" => false,
        "answer"  => "You must be logged in to use the AI assistant."
    ]);
    exit;
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    echo json_encode([
        "success" => false,
        "answer"  => "Invalid request method."
    ]);
    exit;
}


// INPUT COLLECTION

$question = trim((string)($_POST["question"] ?? ""));
$role     = trim((string)($_SESSION["role"] ?? ""));
$username = trim((string)($_SESSION["admin_username"] ?? "Unknown"));

if ($question === "") {
    echo json_encode([
        "success" => false,
        "answer"  => "Please enter a question."
    ]);
    exit;
}


// ATTEMPT 1: CALL PYTHON SCRIPT

// The Python script is the AI-powered backend.
// We pass JSON via stdin and read JSON from stdout.

$pythonPath = __DIR__ . "/ai_assistant.py";

if (file_exists($pythonPath)) {
    $payload = json_encode([
        "question" => $question,
        "role"     => $role,
        "username" => $username
    ]);

    // Try python3 first, then python
    $pythonBin = "python3";
    $testPython = shell_exec("which python3 2>/dev/null");

    if (empty(trim((string)$testPython))) {
        $pythonBin = "python";
    }

    $escapedPath = escapeshellarg($pythonPath);
    $escapedPayload = escapeshellarg($payload);

    $command = "echo {$escapedPayload} | {$pythonBin} {$escapedPath} 2>&1";
    $output = shell_exec($command);

    if ($output !== null) {
        $decoded = json_decode(trim($output), true);

        if (is_array($decoded) && !empty($decoded["success"]) && isset($decoded["answer"])) {
            echo json_encode($decoded);
            exit;
        }
    }

    // If Python failed, fall through to PHP fallback below
}


// ATTEMPT 2: PHP FALLBACK

// If Python is unavailable or failed, handle the
// question directly in PHP using the same database.

function normalise_question(string $text): string {
    $text = strtolower(trim($text));
    $text = preg_replace('/\s+/', ' ', $text);
    return $text ?? '';
}

function format_money($value): string {
    return '£' . number_format((float)$value, 2);
}

function can_access_reports(string $role): bool {
    return in_array($role, ["Administrator", "Manager"], true);
}

function can_access_users(string $role): bool {
    return $role === "Administrator";
}

function json_success(string $answer): void {
    echo json_encode([
        "success"   => true,
        "answer"    => $answer,
        "source"    => "php_fallback",
        "timestamp" => date("Y-m-d H:i:s")
    ]);
    exit;
}

function json_error(string $answer): void {
    echo json_encode([
        "success" => false,
        "answer"  => $answer
    ]);
    exit;
}

$q = normalise_question($question);


// HELPER: word boundary match

function word_match(string $haystack, string $word): bool {
    return (bool)preg_match('/\b' . preg_quote($word, '/') . '\b/', $haystack);
}

function any_match(string $haystack, array $phrases): bool {
    foreach ($phrases as $phrase) {
        if (strpos($haystack, $phrase) !== false) {
            return true;
        }
    }
    return false;
}


// GREETING (whole-word match only)

// Uses word boundaries so "highest" won't match "hi"
// and "which" won't trigger a greeting
if (
    word_match($q, "hello") ||
    word_match($q, "hey") ||
    word_match($q, "good morning") ||
    word_match($q, "good afternoon") ||
    preg_match('/^hi[,.\s!?]*$/', $q) ||
    preg_match('/^hi\s/', $q)
) {
    json_success("Hello {$username}! I'm your Sales Intelligence Assistant. Ask me about revenue, transactions, products, stock levels, or staff performance.");
}


// TODAY'S REVENUE SALES

if (strpos($q, "today") !== false && any_match($q, ["revenue", "sales", "sale", "earning", "income", "made today"])) {
    $result = $mysqli->query("
        SELECT COALESCE(SUM(amount), 0) AS total_today
        FROM transactions
        WHERE DATE(created_at) = CURDATE()
    ");
    if ($result && $row = $result->fetch_assoc()) {
        json_success("Today's total revenue is " . format_money($row["total_today"]) . ".");
    }
    json_error("I could not retrieve today's revenue.");
}


// TODAY'S TRANSACTION COUNT

if (strpos($q, "today") !== false && any_match($q, ["transaction", "order", "how many"])) {
    $result = $mysqli->query("
        SELECT COUNT(*) AS total_today
        FROM transactions
        WHERE DATE(created_at) = CURDATE()
    ");
    if ($result && $row = $result->fetch_assoc()) {
        json_success("There have been " . (int)$row["total_today"] . " transaction(s) today.");
    }
    json_error("I could not retrieve today's transaction count.");
}


// OVERALL TOTAL REVENUE

if (
    any_match($q, ["total revenue", "overall revenue", "all revenue"]) ||
    (word_match($q, "revenue") && !strpos($q, "today") && !strpos($q, "terminal"))
) {
    if (!can_access_reports($role)) {
        json_success("You do not have permission to access revenue analytics.");
    }
    $result = $mysqli->query("SELECT COALESCE(SUM(amount), 0) AS total_revenue FROM transactions");
    if ($result && $row = $result->fetch_assoc()) {
        json_success("The overall revenue is " . format_money($row["total_revenue"]) . ".");
    }
    json_error("I could not retrieve the total revenue.");
}


// HIGHEST TRANSACTION

if (any_match($q, ["highest transaction", "largest transaction", "biggest transaction", "maximum transaction", "biggest sale", "largest sale"])) {
    if (!can_access_reports($role)) {
        json_success("You do not have permission to access transaction analytics.");
    }
    $result = $mysqli->query("SELECT COALESCE(MAX(amount), 0) AS max_value FROM transactions");
    if ($result && $row = $result->fetch_assoc()) {
        json_success("The highest transaction recorded is " . format_money($row["max_value"]) . ".");
    }
    json_error("I could not retrieve the highest transaction.");
}


// AVERAGE TRANSACTION

if (any_match($q, ["average transaction", "avg transaction", "mean transaction", "average sale", "average order"])) {
    if (!can_access_reports($role)) {
        json_success("You do not have permission to access transaction analytics.");
    }
    $result = $mysqli->query("SELECT COALESCE(AVG(amount), 0) AS avg_value FROM transactions");
    if ($result && $row = $result->fetch_assoc()) {
        json_success("The average transaction value is " . format_money($row["avg_value"]) . ".");
    }
    json_error("I could not retrieve the average transaction value.");
}


// MOST POPULAR PRODUCT

if (
    any_match($q, ["most popular product", "top product", "best selling", "popular product", "most sold"]) ||
    (word_match($q, "popular") && word_match($q, "product"))
) {
    $result = $mysqli->query("
        SELECT product_type, COUNT(*) AS total_count
        FROM transactions
        WHERE product_type IS NOT NULL AND product_type <> ''
        GROUP BY product_type
        ORDER BY total_count DESC, product_type ASC
        LIMIT 1
    ");
    if ($result && $row = $result->fetch_assoc()) {
        json_success("The most popular product is " . $row["product_type"] . ", with " . (int)$row["total_count"] . " recorded sale(s).");
    }
    json_success("No transaction data is available yet for product analysis.");
}


// TOP ADMIN / STAFF

if (
    any_match($q, ["top admin", "best admin", "top performing", "top staff", "best staff"]) ||
    (any_match($q, ["highest sales", "most sales", "most transactions", "highest orders", "most orders"]) && !strpos($q, "product")) ||
    (word_match($q, "who") && any_match($q, ["highest", "most", "best", "top"]))
) {
    if (!can_access_reports($role)) {
        json_success("You do not have permission to access admin performance analytics.");
    }
    $result = $mysqli->query("
        SELECT admin_username, COUNT(*) AS total_orders
        FROM transactions
        GROUP BY admin_username
        ORDER BY total_orders DESC, admin_username ASC
        LIMIT 1
    ");
    if ($result && $row = $result->fetch_assoc()) {
        json_success("The top performing admin is " . $row["admin_username"] . " with " . (int)$row["total_orders"] . " order(s).");
    }
    json_success("No transaction data is available yet for admin performance analysis.");
}


// LOW STOCK ITEMS

if (any_match($q, ["low stock", "low in stock", "running low", "almost out", "stock low", "items low"])) {
    $result = $mysqli->query("
        SELECT prodName, prodQuantity
        FROM Product
        WHERE prodQuantity BETWEEN 1 AND 10
        ORDER BY prodQuantity ASC, prodName ASC
        LIMIT 10
    ");
    if ($result && $result->num_rows > 0) {
        $parts = [];
        while ($row = $result->fetch_assoc()) {
            $parts[] = $row["prodName"] . " (" . (int)$row["prodQuantity"] . " left)";
        }
        json_success("Low stock items: " . implode(", ", $parts) . ".");
    }
    json_success("There are currently no low stock items.");
}


// OUT OF STOCK ITEMS

if (any_match($q, ["out of stock", "no stock", "zero stock", "sold out"])) {
    $result = $mysqli->query("
        SELECT prodName
        FROM Product
        WHERE prodQuantity = 0
        ORDER BY prodName ASC
        LIMIT 10
    ");
    if ($result && $result->num_rows > 0) {
        $parts = [];
        while ($row = $result->fetch_assoc()) {
            $parts[] = $row["prodName"];
        }
        json_success("Out of stock items: " . implode(", ", $parts) . ".");
    }
    json_success("There are currently no out of stock items.");
}


// STOCK (general — catch "stock" alone)

if (word_match($q, "stock") && !any_match($q, ["low", "out", "no", "zero"])) {
    $result = $mysqli->query("
        SELECT prodName, prodQuantity, category
        FROM Product
        ORDER BY prodQuantity ASC, prodName ASC
        LIMIT 10
    ");
    if ($result && $result->num_rows > 0) {
        $parts = [];
        while ($row = $result->fetch_assoc()) {
            $parts[] = $row["prodName"] . " (" . (int)$row["prodQuantity"] . ")";
        }
        json_success("Current stock levels: " . implode(", ", $parts) . ".");
    }
    json_success("No product data available.");
}


// PRODUCTS (general — catch "products" alone)

if (
    any_match($q, ["total products", "how many products", "number of products", "all products", "list products"]) ||
    (word_match($q, "products") && strlen($q) < 30)
) {
    $result = $mysqli->query("SELECT COUNT(*) AS total_products FROM Product");
    if ($result && $row = $result->fetch_assoc()) {
        $total = (int)$row["total_products"];
    } else {
        $total = 0;
    }

    $result2 = $mysqli->query("
        SELECT prodName, prodPrice, prodQuantity, category
        FROM Product
        ORDER BY prodName ASC
        LIMIT 10
    ");
    if ($result2 && $result2->num_rows > 0) {
        $parts = [];
        while ($row2 = $result2->fetch_assoc()) {
            $parts[] = $row2["prodName"] . " (£" . number_format((float)$row2["prodPrice"], 2) . ", stock: " . (int)$row2["prodQuantity"] . ")";
        }
        json_success("There are {$total} product(s) in total: " . implode(", ", $parts) . ".");
    }
    json_success("There are {$total} product(s) in the inventory.");
}


// TRANSACTIONS (general — catch "transactions" alone)

if (
    any_match($q, ["total transaction", "how many transaction", "number of transaction", "all transaction"]) ||
    (word_match($q, "transactions") && strlen($q) < 30)
) {
    $result = $mysqli->query("SELECT COUNT(*) AS total FROM transactions");
    if ($result && $row = $result->fetch_assoc()) {
        json_success("There have been " . (int)$row["total"] . " transaction(s) in total.");
    }
    json_error("I could not retrieve the total transaction count.");
}


// ACTIVE USERS

if (any_match($q, ["active users", "active staff", "how many active", "who is active", "staff logged in"])) {
    if (!can_access_users($role)) {
        json_success("You do not have permission to access user activity analytics.");
    }
    $result = $mysqli->query("SELECT COUNT(*) AS active_users FROM admins WHERE last_login IS NOT NULL");
    if ($result && $row = $result->fetch_assoc()) {
        json_success("There are " . (int)$row["active_users"] . " active staff user(s).");
    }
    json_error("I could not retrieve active user information.");
}


// ROLE COUNTS

if (any_match($q, ["how many managers", "number of managers", "manager count"])) {
    if (!can_access_users($role)) { json_success("You do not have permission to access user role analytics."); }
    $result = $mysqli->query("SELECT COUNT(*) AS c FROM admins WHERE role = 'Manager'");
    if ($result && $row = $result->fetch_assoc()) { json_success("There are " . (int)$row["c"] . " manager account(s)."); }
}

if (any_match($q, ["how many cashiers", "number of cashiers", "cashier count"])) {
    if (!can_access_users($role)) { json_success("You do not have permission to access user role analytics."); }
    $result = $mysqli->query("SELECT COUNT(*) AS c FROM admins WHERE role = 'Cashier'");
    if ($result && $row = $result->fetch_assoc()) { json_success("There are " . (int)$row["c"] . " cashier account(s)."); }
}

if (any_match($q, ["how many administrators", "how many admins", "admin count"])) {
    if (!can_access_users($role)) { json_success("You do not have permission to access user role analytics."); }
    $result = $mysqli->query("SELECT COUNT(*) AS c FROM admins WHERE role = 'Administrator'");
    if ($result && $row = $result->fetch_assoc()) { json_success("There are " . (int)$row["c"] . " administrator account(s)."); }
}


// STAFF ACTIVITY (catch "staff activity" etc.)

if (any_match($q, ["staff activity", "staff performance", "employee activity", "team activity"])) {
    if (!can_access_reports($role)) {
        json_success("You do not have permission to access staff activity analytics.");
    }
    $result = $mysqli->query("
        SELECT admin_username, COUNT(*) AS total_orders, SUM(amount) AS total_revenue
        FROM transactions
        GROUP BY admin_username
        ORDER BY total_orders DESC
        LIMIT 5
    ");
    if ($result && $result->num_rows > 0) {
        $parts = [];
        while ($row = $result->fetch_assoc()) {
            $parts[] = $row["admin_username"] . ": " . (int)$row["total_orders"] . " orders (" . format_money($row["total_revenue"]) . ")";
        }
        json_success("Staff activity overview: " . implode(", ", $parts) . ".");
    }
    json_success("No staff activity data available yet.");
}


// REVENUE BY TERMINAL

if (strpos($q, "terminal") !== false && any_match($q, ["revenue", "sales", "performance"])) {
    if (!can_access_reports($role)) { json_success("You do not have permission to access terminal revenue analytics."); }
    $result = $mysqli->query("
        SELECT terminal, COALESCE(SUM(amount), 0) AS total_revenue
        FROM transactions
        GROUP BY terminal
        ORDER BY total_revenue DESC, terminal ASC
        LIMIT 5
    ");
    if ($result && $result->num_rows > 0) {
        $parts = [];
        while ($row = $result->fetch_assoc()) {
            $parts[] = $row["terminal"] . ": " . format_money($row["total_revenue"]);
        }
        json_success("Revenue by terminal: " . implode(", ", $parts) . ".");
    }
    json_success("No terminal revenue data is available yet.");
}


// HELP / WHAT CAN YOU DO

if (any_match($q, ["help", "what can you", "what do you", "how to use", "commands", "options"])) {
    json_success(
        "I can help you with:\n" .
        "• Revenue — \"What is our total revenue?\" or \"Today's sales\"\n" .
        "• Transactions — \"How many transactions today?\" or just \"transactions\"\n" .
        "• Products — \"What is the most popular product?\" or just \"products\"\n" .
        "• Stock — \"What items are low in stock?\" or \"out of stock\"\n" .
        "• Staff — \"Who has the highest sales?\" or \"staff activity\"\n" .
        "• Analytics — \"Average transaction value\" or \"Revenue by terminal\""
    );
}


// FALLBACK

json_success(
    "I could not understand that question yet. Try asking:\n" .
    "• What is our total revenue?\n" .
    "• How many transactions today?\n" .
    "• What is the most popular product?\n" .
    "• Which admin has the highest sales?\n" .
    "• What items are low in stock?\n" .
    "• Show me stock levels\n" .
    "• Type \"help\" to see all available commands."
);