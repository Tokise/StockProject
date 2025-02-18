<?php
session_start();

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
    require_once '../modules/config/db.php';
    
    $username = $_POST['username'] ?? '';
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $full_name = $_POST['full_name'] ?? '';
    $phone = $_POST['phone'] ?? '';
    $address = $_POST['address'] ?? '';
    
    $errors = [];
    
    // Validate username
    if (empty($username)) {
        $errors['username'] = "Username is required";
    } else {
        // Check if username exists
        $sql = "SELECT COUNT(*) FROM users WHERE username = ?";
        if (fetchValue($sql, [$username]) > 0) {
            $errors['username'] = "Username already exists";
        }
    }
    
    // Validate email
    if (empty($email)) {
        $errors['email'] = "Email is required";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = "Invalid email format";
    } else {
        // Check if email exists
        $sql = "SELECT COUNT(*) FROM users WHERE email = ?";
        if (fetchValue($sql, [$email]) > 0) {
            $errors['email'] = "Email already exists";
        }
    }
    
    // Validate password
    if (empty($password)) {
        $errors['password'] = "Password is required";
    } elseif (strlen($password) < 6) {
        $errors['password'] = "Password must be at least 6 characters";
    }
    
    // Validate confirm password
    if ($password !== $confirm_password) {
        $errors['confirm_password'] = "Passwords do not match";
    }
    
    // Validate full name
    if (empty($full_name)) {
        $errors['full_name'] = "Full name is required";
    }

    // Validate phone
    if (empty($phone)) {
        $errors['phone'] = "Phone number is required";
    }

    // Validate address
    if (empty($address)) {
        $errors['address'] = "Address is required";
    }
    
    if (empty($errors)) {
        try {
            // Start transaction
            $conn = getDBConnection();
            $conn->beginTransaction();
            
            // Create user account with explicit role
            $user_data = [
                'username' => $username,
                'password' => password_hash($password, PASSWORD_DEFAULT),
                'email' => $email,
                'full_name' => $full_name,
                'role' => 'customer',
                'status' => 'active',
                'created_by' => isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null,
                'created_at' => date('Y-m-d H:i:s')
            ];
            
            $user_id = insert('users', $user_data);
            
            // Check if customer already exists with this email
            $existing_customer = fetchOne("SELECT customer_id FROM customers WHERE email = ?", [$email]);
            
            if ($existing_customer) {
                // Update existing customer record
                $customer_data = [
                    'name' => $full_name,
                    'phone' => $phone,
                    'address' => $address,
                    'updated_at' => date('Y-m-d H:i:s')
                ];
                executeQuery("UPDATE customers SET name = ?, phone = ?, address = ?, updated_at = ? WHERE customer_id = ?",
                    [$customer_data['name'], $customer_data['phone'], $customer_data['address'], $customer_data['updated_at'], $existing_customer['customer_id']]);
                $customer_id = $existing_customer['customer_id'];
            } else {
                // Create new customer record
                $customer_data = [
                    'name' => $full_name,
                    'email' => $email,
                    'phone' => $phone,
                    'address' => $address,
                    'created_at' => date('Y-m-d H:i:s')
                ];
                $customer_id = insert('customers', $customer_data);
            }
            
            // Create customer profile
            $profile_data = [
                'user_id' => $user_id,
                'default_shipping_address' => $address,
                'default_billing_address' => $address,
                'created_at' => date('Y-m-d H:i:s')
            ];
            
            insert('customer_profiles', $profile_data);
            
            // Commit transaction
            $conn->commit();
            
            // Set success message
            $_SESSION['success'] = "Registration successful! You can now login.";
            header("Location: ../login/index.php");
            exit();
            
        } catch (Exception $e) {
            // Rollback transaction
            if (isset($conn) && $conn->inTransaction()) {
                $conn->rollBack();
            }
            $errors['general'] = "Registration failed. Please try again.";
            error_log("Registration error: " . $e->getMessage());
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NexInvent - Customer Registration</title>
    
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
        
        .register-container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
            overflow: hidden;
            width: 100%;
            max-width: 600px;
            padding: 2rem;
            margin: 2rem;
        }
        
        .register-header {
            text-align: center;
            margin-bottom: 2rem;
        }
        
        .register-header h1 {
            color: #2c3e50;
            font-size: 2rem;
            margin-bottom: 0.5rem;
        }
        
        .register-header p {
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
        
        .btn-register {
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
        
        .btn-register:hover {
            background: #2980b9;
            transform: translateY(-2px);
        }
        
        .alert {
            border-radius: 10px;
            border: none;
        }
    </style>
</head>
<body>
    <div class="register-container">
        <div class="register-header">
            <h1>NexInvent</h1>
            <p>Customer Registration</p>
        </div>
        
        <?php if (!empty($errors['general'])): ?>
            <div class="alert alert-danger" role="alert">
                <?php echo htmlspecialchars($errors['general']); ?>
            </div>
        <?php endif; ?>
        
        <form method="POST" action="">
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="username" class="form-label">Username</label>
                    <input type="text" class="form-control <?php echo isset($errors['username']) ? 'is-invalid' : ''; ?>" 
                           id="username" name="username" value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>">
                    <?php if (isset($errors['username'])): ?>
                        <div class="invalid-feedback"><?php echo $errors['username']; ?></div>
                    <?php endif; ?>
                </div>
                
                <div class="col-md-6 mb-3">
                    <label for="email" class="form-label">Email</label>
                    <input type="email" class="form-control <?php echo isset($errors['email']) ? 'is-invalid' : ''; ?>" 
                           id="email" name="email" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                    <?php if (isset($errors['email'])): ?>
                        <div class="invalid-feedback"><?php echo $errors['email']; ?></div>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="mb-3">
                <label for="full_name" class="form-label">Full Name</label>
                <input type="text" class="form-control <?php echo isset($errors['full_name']) ? 'is-invalid' : ''; ?>" 
                       id="full_name" name="full_name" value="<?php echo htmlspecialchars($_POST['full_name'] ?? ''); ?>">
                <?php if (isset($errors['full_name'])): ?>
                    <div class="invalid-feedback"><?php echo $errors['full_name']; ?></div>
                <?php endif; ?>
            </div>
            
            <div class="mb-3">
                <label for="phone" class="form-label">Phone Number</label>
                <input type="tel" class="form-control <?php echo isset($errors['phone']) ? 'is-invalid' : ''; ?>" 
                       id="phone" name="phone" value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>">
                <?php if (isset($errors['phone'])): ?>
                    <div class="invalid-feedback"><?php echo $errors['phone']; ?></div>
                <?php endif; ?>
            </div>
            
            <div class="mb-3">
                <label for="address" class="form-label">Address</label>
                <textarea class="form-control <?php echo isset($errors['address']) ? 'is-invalid' : ''; ?>" 
                          id="address" name="address" rows="3"><?php echo htmlspecialchars($_POST['address'] ?? ''); ?></textarea>
                <?php if (isset($errors['address'])): ?>
                    <div class="invalid-feedback"><?php echo $errors['address']; ?></div>
                <?php endif; ?>
            </div>
            
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="password" class="form-label">Password</label>
                    <input type="password" class="form-control <?php echo isset($errors['password']) ? 'is-invalid' : ''; ?>" 
                           id="password" name="password">
                    <?php if (isset($errors['password'])): ?>
                        <div class="invalid-feedback"><?php echo $errors['password']; ?></div>
                    <?php endif; ?>
                </div>
                
                <div class="col-md-6 mb-3">
                    <label for="confirm_password" class="form-label">Confirm Password</label>
                    <input type="password" class="form-control <?php echo isset($errors['confirm_password']) ? 'is-invalid' : ''; ?>" 
                           id="confirm_password" name="confirm_password">
                    <?php if (isset($errors['confirm_password'])): ?>
                        <div class="invalid-feedback"><?php echo $errors['confirm_password']; ?></div>
                    <?php endif; ?>
                </div>
            </div>
            
            <button type="submit" class="btn btn-primary btn-register mt-3">
                Register Account
            </button>
            
            <div class="text-center mt-4">
                <a href="../login/index.php" class="text-decoration-none">
                    Already have an account? Login here
                </a>
            </div>
        </form>
    </div>
    
    <!-- Bootstrap 5 JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>