<?php
// db.php (MySQLi)

// Enable strict typing for better code reliability
declare(strict_types=1);

// Database host (server location)
$DB_HOST = "phpmyadmin.ecs.westminster.ac.uk";

// Database name
$DB_NAME = "w1987586_0";

// Database username
$DB_USER = "w1987586";

// Database password
$DB_PASS = "XgN5JF1mWnOt";

// Create a new MySQLi connection using the credentials above
$mysqli = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);

// Check if there is a connection error
if ($mysqli->connect_errno) {
    // Set HTTP response code to indicate server error
    http_response_code(500);
    
    // Stop execution and display a generic error message
    exit("Database connection failed.");
}

// Set character encoding to utf8mb4 for proper text handling
$mysqli->set_charset("utf8mb4");
