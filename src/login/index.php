<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Check if there's a success message from registration
if (isset($_SESSION['success'])) {
    $success = $_SESSION['success'];
    unset($_SESSION['success']);
}

// If user is already logged in, redirect to appropriate dashboard
if (isset($_SESSION['user_id'])) {
    if ($_SESSION['role'] === 'customer') {
        header("Location: ../modules/customer/index.php");
    } else {
        header("Location: ../modules/index.php");
    }
    exit();
}

// Check if form is submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once __DIR__ . '/../modules/config/db.php';
    
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    try {
        // Get user by username
        $sql = "SELECT * FROM users WHERE username = ?";
        $user = fetchOne($sql, [$username]);
        
        if (!$user) {
            $error = "Invalid username or password";
        } elseif ($user['status'] !== 'active') {
            $error = "Your account is not active. Please contact support.";
        } elseif (!password_verify($password, $user['password'])) {
            error_log("Login failed for user $username - Password verification failed");
            error_log("Provided password hash: " . password_hash($password, PASSWORD_DEFAULT));
            error_log("Stored password hash: " . $user['password']);
            $error = "Invalid username or password";
        } else {
            // Start transaction
            $conn = getDBConnection();
            $conn->beginTransaction();

            try {
                // Set session variables
                $_SESSION['user_id'] = $user['user_id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['full_name'] = $user['full_name'];
                
                if ($user['role'] === 'customer') {
                    // Check if customer record exists by email
                    $customer = fetchOne("SELECT customer_id FROM customers WHERE email = ?", [$user['email']]);
                    
                    if (!$customer) {
                        // Only create customer record if it doesn't exist
                        $customer_data = [
                            'name' => $user['full_name'],
                            'email' => $user['email'],
                            'created_at' => date('Y-m-d H:i:s')
                        ];
                        $customer_id = insert('customers', $customer_data);
                        $_SESSION['customer_id'] = $customer_id;
                    } else {
                        $_SESSION['customer_id'] = $customer['customer_id'];
                    }

                    // Check if customer profile exists
                    $profile = fetchOne("SELECT * FROM customer_profiles WHERE user_id = ?", [$user['user_id']]);
                    if (!$profile) {
                        $profile_data = [
                            'user_id' => $user['user_id'],
                            'created_at' => date('Y-m-d H:i:s')
                        ];
                        insert('customer_profiles', $profile_data);
                    }
                }
                
                // Update last login timestamp
                executeQuery("UPDATE users SET last_login = CURRENT_TIMESTAMP WHERE user_id = ?", [$user['user_id']]);
                
                // Commit transaction
                $conn->commit();
                
                // Clear any existing error messages
                unset($error);
                
                // Redirect based on role
                if ($user['role'] === 'customer') {
                    header("Location: ../modules/customer/index.php");
                } else {
                    header("Location: ../modules/index.php");
                }
                exit();
            } catch (Exception $e) {
                $conn->rollBack();
                error_log("Login error: " . $e->getMessage());
                $error = "An error occurred during login. Please try again.";
            }
        }
    } catch (Exception $e) {
        error_log("Login error: " . $e->getMessage());
        $error = "An error occurred. Please try again later.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NexInvent - Login</title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    
    <style>
        body {
            background: linear-gradient(135deg, #2c3e50 0%, #3498db 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .login-container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
            overflow: hidden;
            width: 100%;
            max-width: 400px;
            padding: 2rem;
        }
        
        .login-header {
            text-align: center;
            margin-bottom: 2rem;
        }
        
        .login-header h1 {
            color: #2c3e50;
            font-size: 2rem;
            margin-bottom: 0.5rem;
        }
        
        .login-header p {
            color: #7f8c8d;
            margin-bottom: 0;
        }
        
        .form-control {
            border-radius: 10px;
            padding: 12px 15px;
            border: 1px solid #e0e0e0;
            transition: all 0.3s;
        }
        
        .form-control:focus {
            border-color: #3498db;
            box-shadow: 0 0 0 0.2rem rgba(52, 152, 219, 0.25);
        }
        
        .btn-login {
            background: #3498db;
            border: none;
            border-radius: 10px;
            padding: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
            transition: all 0.3s;
            width: 100%;
        }
        
        .btn-login:hover {
            background: #2980b9;
            transform: translateY(-2px);
        }
        
        .form-label {
            color: #2c3e50;
            font-weight: 500;
        }
        
        .alert {
            border-radius: 10px;
            border: none;
        }
        
        .input-group-text {
            background: none;
            border-left: none;
            cursor: pointer;
        }
        
        .password-toggle {
            border: 1px solid #e0e0e0;
            border-left: none;
            border-top-right-radius: 10px;
            border-bottom-right-radius: 10px;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <h1>NexInvent</h1>
            <p>Stock Management System</p>
        </div>
        
        <?php if (isset($error)): ?>
            <div class="alert alert-danger" role="alert">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($success)): ?>
            <div class="alert alert-success" role="alert">
                <?php echo htmlspecialchars($success); ?>
            </div>
        <?php endif; ?>
        
        <form method="POST" action="">
            <div class="mb-3">
                <label for="username" class="form-label">Username</label>
                <div class="input-group">
                    <span class="input-group-text">
                        <i class="bi bi-person"></i>
                    </span>
                    <input type="text" class="form-control" id="username" name="username" required 
                           value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>">
                </div>
            </div>
            
            <div class="mb-4">
                <label for="password" class="form-label">Password</label>
                <div class="input-group">
                    <span class="input-group-text">
                        <i class="bi bi-lock"></i>
                    </span>
                    <input type="password" class="form-control" id="password" name="password" required>
                    <span class="input-group-text password-toggle" onclick="togglePassword()">
                        <i class="bi bi-eye"></i>
                    </span>
                </div>
            </div>
            
            <button type="submit" class="btn btn-primary btn-login">
                Sign In
            </button>
            
            <div class="text-center mt-4">
                <p class="mb-2">
                    <a href="../register/index.php" class="text-decoration-none">
                        New customer? Register here
                    </a>
                </p>
                <small class="text-muted">
                    Note: Staff accounts are created by administrators only.
                </small>
            </div>
        </form>
    </div>
    
    <!-- Bootstrap 5 JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
    function togglePassword() {
        const passwordInput = document.getElementById('password');
        const icon = document.querySelector('.password-toggle i');
        
        if (passwordInput.type === 'password') {
            passwordInput.type = 'text';
            icon.classList.remove('bi-eye');
            icon.classList.add('bi-eye-slash');
        } else {
            passwordInput.type = 'password';
            icon.classList.remove('bi-eye-slash');
            icon.classList.add('bi-eye');
        }
    }
    </script>
</body>
</html>