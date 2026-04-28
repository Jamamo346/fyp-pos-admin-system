<?php
// logout.php
declare(strict_types=1);
session_start();  // Start the session so we can access and destroy it

$_SESSION = [];    // This ensures the user is fully logged out.
session_destroy(); //This prevents the user from accessing protected pages after logout.

header("Location: login.html");
exit;  // REDIRECT TO LOGIN PAGE

