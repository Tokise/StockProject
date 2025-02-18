<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'StockManagementProject/src/modules/config/db.php';

try {
    // Check database connection
    $conn = getDBConnection();
    echo "Database connection successful!<br><br>";

    // Check if admin exists
    $admin = fetchOne("SELECT user_id, username, email, role, status FROM users WHERE username = 'admin'");
    
    if ($admin) {
        echo "Admin user found:<br>";
        echo "User ID: " . $admin['user_id'] . "<br>";
        echo "Username: " . $admin['username'] . "<br>";
        echo "Email: " . $admin['email'] . "<br>";
        echo "Role: " . $admin['role'] . "<br>";
        echo "Status: " . $admin['status'] . "<br>";
    } else {
        echo "No admin user found in database.<br>";
    }

    // Check tables
    $tables = ['users', 'permissions', 'role_permissions'];
    foreach ($tables as $table) {
        $count = fetchValue("SELECT COUNT(*) FROM $table");
        echo "<br>Number of records in $table: $count<br>";
    }

} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
} 