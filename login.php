<?php
// Enable strict typing for better type safety
declare(strict_types=1);

// Start session to manage user login state
session_start();

// Include database connection
require_once "db.php";

// Function to redirect user back to login page with an error message
function redirect_with_error(string $message = "Invalid username or password."): void
{
    header("Location: login.html?error=" . urlencode($message)); // Pass error via URL
    exit; // Stop script execution
}

// Ensure the request is POST (form submission)
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: login.html"); // Redirect if accessed directly
    exit;
}

// Retrieve and clean username and password input
$username = strtolower(trim((string)($_POST["username"] ?? ""))); // Convert to lowercase and trim
$password = trim((string)($_POST["password"] ?? "")); // Trim password

// Validate that both fields are provided
if ($username === "" || $password === "") {
    redirect_with_error("Please enter both username and password.");
}

// Prepare SQL statement to fetch user data
$stmt = $mysqli->prepare("
    SELECT username, password, role
    FROM admins
    WHERE username = ?
    LIMIT 1
");

// Check if statement preparation failed
if (!$stmt) {
    redirect_with_error("Login system unavailable.");
}

// Bind username parameter to query
$stmt->bind_param("s", $username);

// Execute query
$stmt->execute();

// Get query result
$result = $stmt->get_result();

// Fetch user record if exists
$user = $result ? $result->fetch_assoc() : null;

// Close statement
$stmt->close();

// Check if user exists
if (!$user) {
    redirect_with_error("Invalid username or password.");
}

// Verify password using hash-safe comparison
if (!hash_equals((string)$user["password"], $password)) {
    redirect_with_error("Invalid username or password.");
}

// Regenerate session ID to prevent session fixation
session_regenerate_id(true);

// Store user details in session
$_SESSION["admin_username"] = (string)$user["username"]; // Username
$_SESSION["role"] = (string)$user["role"]; // Role

// Map usernames to terminal IDs
$terminalMap = [
    "admin_jama"    => "POS-01",
    "manager_store" => "POS-02",
    "cashier_pos1"  => "POS-03",
    "cashier_pos2"  => "POS-04",
];

// Assign terminal ID based on username
$_SESSION["terminal"] = $terminalMap[$user["username"]] ?? "POS-00"; // Default fallback

// Prepare SQL to update last login timestamp
$updateStmt = $mysqli->prepare("
    UPDATE admins
    SET last_login = NOW()
    WHERE username = ?
    LIMIT 1
");

// Execute update if statement is valid
if ($updateStmt) {
    $updateStmt->bind_param("s", $user["username"]); // Bind username
    $updateStmt->execute(); // Run update
    $updateStmt->close(); // Close statement
}

// Redirect user to dashboard after successful login
header("Location: dashboard.php");
exit;