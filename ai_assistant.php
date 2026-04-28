<?php
session_start();

if (!isset($_SESSION["admin_username"])) {
    header("Location: login.html");
    exit;
}

require_once "db.php";

$loggedInAdmin    = $_SESSION["admin_username"] ?? "Unknown";
$loggedInTerminal = $_SESSION["terminal"] ?? "Unknown";
$loggedInRole     = $_SESSION["role"] ?? "Unknown";
$canManageUsers   = ($loggedInRole === "Administrator");
$canViewReports   = in_array($loggedInRole, ["Administrator", "Manager"], true);

/* ---- Sidebar stats ---- */
$totalRecords = 0;
$result = $mysqli->query("SELECT COUNT(*) AS total FROM transactions");
if ($result && $row = $result->fetch_assoc()) {
    $totalRecords = (int)$row["total"];
}

$totalProducts = 0;
$result = $mysqli->query("SELECT COUNT(*) AS total FROM Product");
if ($result && $row = $result->fetch_assoc()) {
    $totalProducts = (int)$row["total"];
}

$dateFrom = "N/A";
$dateTo   = "N/A";
$result = $mysqli->query("SELECT MIN(created_at) AS earliest, MAX(created_at) AS latest FROM transactions");
if ($result && $row = $result->fetch_assoc()) {
    $dateFrom = $row["earliest"] ? date("M d, Y", strtotime($row["earliest"])) : "N/A";
    $dateTo   = $row["latest"]   ? date("M d, Y", strtotime($row["latest"]))   : "N/A";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Sales Intelligence Assistant | POS Admin</title>
<link rel="stylesheet" href="style.css">
</head>
<body>

<div class="layout">

    <!-- ===== MAIN SIDEBAR ===== -->
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
            <a href="suppliers.php">Suppliers</a>
        <?php endif; ?>
        <a href="ai_assistant.php" class="active">AI Assistant</a>
        <?php if ($canManageUsers): ?>
            <a href="users.php">Users / Staff</a>
        <?php endif; ?>
        <a href="logout.php">Logout</a>
    </div>

    <!-- ===== AI PAGE ===== -->
    <div class="ai-page">

        <!-- Left analytics panel -->
        <div class="ai-panel">
            <div class="ai-panel-brand">
                <div class="brand-icon">🤖</div>
                <div>
                    <h3>POS Admin</h3>
                    <p>Sales Intelligence Assistant</p>
                </div>
            </div>

            <div><span class="ai-badge">✓ Data Loaded</span></div>

            <div class="ai-stat-card">
                <p class="big"><?php echo number_format($totalRecords); ?></p>
                <p class="lbl">Total Records</p>
                <p class="rng"><span class="dot"></span> <?php echo htmlspecialchars($dateFrom); ?> → <?php echo htmlspecialchars($dateTo); ?></p>
            </div>

            <div class="ai-stat-card">
                <p class="big"><?php echo number_format($totalProducts); ?></p>
                <p class="lbl">Products in Inventory</p>
            </div>

            <button class="ai-clear-btn" onclick="clearChat()">🗑 Clear Chat</button>

        </div>

        <!-- Chat column -->
        <div class="ai-chat-col">

            <div class="ai-chat-head">
                <div class="head-icon">🤖</div>
                <h1>Sales Intelligence Assistant</h1>
                <p>Ask complex questions about your sales data in plain English.</p>
            </div>

            <div class="ai-status-bar">
                <span class="pulse"></span>
                Logged in as <strong>&nbsp;<?php echo htmlspecialchars($loggedInAdmin); ?></strong>&nbsp;·
                <?php echo htmlspecialchars($loggedInTerminal); ?>&nbsp;·
                <?php echo htmlspecialchars($loggedInRole); ?>
            </div>

            <div class="ai-msgs" id="chatMessages">
                <div class="ai-msg bot">
                    <div class="ava">🤖</div>
                    <div class="bbl">
                        Hello! My AI brain is online. I've analysed your <strong><?php echo number_format($totalRecords); ?></strong> records.
                        Ask me anything, like <em>"What is our total revenue?"</em> 
                    </div>
                </div>
            </div>

            <div class="ai-chips" id="chips">
                <button class="ai-chip" onclick="ask('What is our total revenue?')">Total revenue</button>
                <button class="ai-chip" onclick="ask('What is the most popular product?')">Most popular product</button>
                <button class="ai-chip" onclick="ask('How many transactions today?')">Today's transactions</button>
                <button class="ai-chip" onclick="ask('Show me low stock items')">Low stock items</button>
                <button class="ai-chip" onclick="ask('Which staff has the highest orders?')">Top staff member</button>
                <button class="ai-chip" onclick="ask('What is the average transaction value?')">Avg transaction</button>
            </div>

            <div class="ai-bar">
                <div class="ai-bar-inner">
                    <input type="text" id="chatInput" placeholder="Ask a question about your sales data..." autocomplete="off">
                    <button class="ai-send" id="sendBtn" onclick="sendMessage()">➤</button>
                </div>
            </div>

        </div>
    </div>
</div>
<script>

//*AI ASSISTANT — CHAT LOGIC//*


// Get DOM elements for chat interface
const msgs   = document.getElementById("chatMessages"); // Message container
const input  = document.getElementById("chatInput");    // Input field
const btn    = document.getElementById("sendBtn");      // Send button
const chips  = document.getElementById("chips");        // Quick suggestion buttons
let busy = false; // Prevent multiple requests at once

// Function to add a message to the chat
function addMsg(text, type) {
    const d = document.createElement("div"); // Create message container
    d.className = "ai-msg " + type; // Assign class based on type (user/bot)

    const icon = type === "bot" ? "🤖" : "👤"; // Choose icon

    // Format text: bold (**text**) and line breaks
    const formatted = text
        .replace(/\*\*(.*?)\*\*/g, "<strong>$1</strong>")
        .replace(/\n/g, "<br>");

    // Build message HTML
    d.innerHTML = '<div class="ava">' + icon + '</div><div class="bbl">' + formatted + '</div>';

    msgs.appendChild(d); // Add message to chat
    msgs.scrollTop = msgs.scrollHeight; // Auto-scroll to latest message
}

// Show typing indicator while waiting for AI response
function showTyping() {
    const d = document.createElement("div");
    d.className = "ai-msg bot"; 
    d.id = "typing"; // Assign ID for later removal

    // Typing animation
    d.innerHTML = '<div class="ava">🤖</div><div class="bbl"><div class="ai-typing"><span></span><span></span><span></span></div></div>';

    msgs.appendChild(d);
    msgs.scrollTop = msgs.scrollHeight;
}

// Remove typing indicator
function hideTyping() {
    const el = document.getElementById("typing");
    if (el) el.remove();
}

// Main function to send message to backend
async function sendMessage() {
    const q = input.value.trim(); // Get user input

    // Prevent empty messages or multiple submissions
    if (!q || busy) return;

    busy = true; // Lock input
    btn.disabled = true; // Disable send button
    input.value = ""; // Clear input field
    chips.style.display = "none"; // Hide suggestion chips

    addMsg(q, "user"); // Display user message
    showTyping(); // Show typing animation

    try {
        const fd = new FormData(); // Create form data
        fd.append("question", q); // Add question

        // Send request to PHP handler
        const res = await fetch("ai_assistant_handler.php", {
            method: "POST",
            body: fd
        });

        const data = await res.json(); // Parse JSON response

        hideTyping(); // Remove typing animation

        // Display AI response or fallback message
        addMsg(data.answer || "Sorry, I could not process that.", "bot");
    } catch (err) {
        hideTyping(); // Remove typing animation on error

        // Show error message
        addMsg("⚠ The AI assistant could not be reached. Please try again.", "bot");
    }

    busy = false; // Unlock input
    btn.disabled = false; // Enable send button
    input.focus(); // Focus back to input
}

// Helper function to send predefined questions
function ask(q) { 
    input.value = q; 
    sendMessage(); 
}

// Clear chat history
function clearChat() {
    msgs.innerHTML = ""; // Remove all messages

    // Add default message after clearing
    addMsg("Chat cleared. How can I help you with your sales data?", "bot");

    chips.style.display = "flex"; // Show suggestion chips again
}

// Send message when Enter key is pressed
input.addEventListener("keydown", function(e) { 
    if (e.key === "Enter") sendMessage(); 
});

// Focus input field on load
input.focus();

</script>
</body>
</html>