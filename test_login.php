<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'StockManagementProject/src/modules/config/db.php';

try {
    // Check database connection
    $conn = getDBConnection();
    echo "Database connection successful!<br><br>";

    // Get admin user with full details including password hash
    $admin = fetchOne("SELECT * FROM users WHERE username = 'admin'");
    
    if ($admin) {
        echo "Admin user found:<br>";
        echo "User ID: " . $admin['user_id'] . "<br>";
        echo "Username: " . $admin['username'] . "<br>";
        echo "Email: " . $admin['email'] . "<br>";
        echo "Role: " . $admin['role'] . "<br>";
        echo "Status: " . $admin['status'] . "<br>";
        
        // Test password verification
        $test_password = 'admin123';
        $is_password_valid = password_verify($test_password, $admin['password']);
        echo "<br>Password verification test:<br>";
        echo "Testing password: " . $test_password . "<br>";
        echo "Password hash in DB: " . $admin['password'] . "<br>";
        echo "Password verification result: " . ($is_password_valid ? "VALID" : "INVALID") . "<br>";
        
        // If password is invalid, let's create a new hash for comparison
        $new_hash = password_hash($test_password, PASSWORD_DEFAULT);
        echo "<br>New hash for 'admin123': " . $new_hash . "<br>";
        
        // Update admin password if verification fails
        if (!$is_password_valid) {
            echo "<br>Updating admin password...<br>";
            executeQuery("UPDATE users SET password = ? WHERE user_id = ?", [$new_hash, $admin['user_id']]);
            echo "Password updated successfully!<br>";
        }
    } else {
        echo "No admin user found in database.<br>";
        
        // Create admin user if not exists
        $password = password_hash('admin123', PASSWORD_DEFAULT);
        $adminData = [
            'username' => 'admin',
            'password' => $password,
            'email' => 'admin@nexinvent.local',
            'full_name' => 'System Administrator',
            'role' => 'admin',
            'status' => 'active',
            'created_at' => date('Y-m-d H:i:s')
        ];
        
        $admin_id = insert('users', $adminData);
        echo "<br>Created new admin user with ID: " . $admin_id . "<br>";
    }

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "<br>";
    echo "Stack trace: <pre>" . $e->getTraceAsString() . "</pre>";
} 