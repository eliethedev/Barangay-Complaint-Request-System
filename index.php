<?php
session_start();
require_once 'baby_capstone_connection.php';

// Debug database connection
try {
    $pdo->query("SELECT 1");
    error_log("Database connection successful");
} catch (PDOException $e) {
    error_log("Database connection error: " . $e->getMessage());
}

// Initialize registration success variable
$registration_success = isset($_SESSION['registration_success']) && $_SESSION['registration_success'] === true;
if ($registration_success) {
    unset($_SESSION['registration_success']);
}

// Handle login POST request
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    error_log("Login attempt - Email: " . $email);
    error_log("Password length: " . strlen($password));

    if (empty($email) || empty($password)) {
        $_SESSION['login_error'] = "Email and Password are required.";
        header("Location: index.php");
        exit();
    }

    // First check if this is an admin user
    try {
        $admin_stmt = $pdo->prepare("SELECT * FROM admin_users WHERE email = ?");
        $admin_stmt->execute([$email]);
        $admin = $admin_stmt->fetch();

        error_log("Admin query executed successfully");
        error_log("Admin record found: " . (isset($admin) ? 'yes' : 'no'));
        
        if ($admin) {
            error_log("Admin details: " . print_r($admin, true));
            error_log("Password hash: " . $admin['password']);
            
            if ($admin['status'] === 'active' && password_verify($password, $admin['password'])) {
                error_log("Password verification successful");
                
                // This is an admin user
                $_SESSION['admin_id'] = $admin['id'];
                $_SESSION['admin_name'] = $admin['name'];
                $_SESSION['admin_email'] = $admin['email'];
                $_SESSION['is_admin'] = true;
                
                // Set admin session timeout (8 hours)
                $_SESSION['admin_last_activity'] = time();
                $_SESSION['admin_expire_time'] = 8 * 60 * 60; // 8 hours in seconds
                
                // Log admin login
                $log_stmt = $pdo->prepare("INSERT INTO admin_logs (admin_id, action, ip_address) VALUES (?, ?, ?)");
                $log_stmt->execute([$admin['id'], 'login', $_SERVER['REMOTE_ADDR']]);
                
                // Redirect to admin dashboard
                error_log("Redirecting to admin dashboard");
                header("Location: admin/dashboard.php");
                exit();
            } else {
                error_log("Password verification failed or admin is inactive");
            }
        } else {
            error_log("No admin record found");
            
            // If not an admin, check regular users
            $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();
            
            error_log("User query executed successfully");
            error_log("User record found: " . (isset($user) ? 'yes' : 'no'));
            
            if ($user) {
                error_log("User details: " . print_r($user, true));
                error_log("User password hash: " . $user['password']);
                
                if (password_verify($password, $user['password'])) {
                    error_log("User password verification successful");
                    
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['full_name'] = $user['full_name'];
                    $_SESSION['email'] = $user['email'];
                    $_SESSION['is_admin'] = false;
                    
                    // Store the profile picture path if available
                    if (!empty($user['profile_pic'])) {
                        $_SESSION['profile_pic'] = $user['profile_pic'];
                    }
                    
                    // Set user session timeout (2 hours)
                    $_SESSION['last_activity'] = time();
                    $_SESSION['expire_time'] = 2 * 60 * 60; // 2 hours in seconds
                    
                    error_log("Redirecting to user dashboard");
                    header("Location: user_dashboard.php");
                    exit();
                } else {
                    error_log("User password verification failed");
                    $_SESSION['login_error'] = "Invalid email or password.";
                    header("Location: index.php");
                    exit();
                }
            } else {
                error_log("No user record found");
                $_SESSION['login_error'] = "Invalid email or password.";
                header("Location: index.php");
                exit();
            }
        }
    } catch (PDOException $e) {
        error_log("Database error: " . $e->getMessage());
        $_SESSION['login_error'] = "An error occurred. Please try again later.";
        header("Location: index.php");
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Barangay System - Login</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .login-bg {
            background-image: url('data:image/svg+xml,%3Csvg xmlns="http://www.w3.org/2000/svg" width="100" height="100" viewBox="0 0 100 100"%3E%3Cpath d="M11 18c3.866 0 7-3.134 7-7s-3.134-7-7-7-7 3.134-7 7 3.134 7 7 7zm48 25c3.866 0 7-3.134 7-7s-3.134-7-7-7-7 3.134-7 7 3.134 7 7 7zm-43-7c1.657 0 3-1.343 3-3s-1.343-3-3-3-3 1.343-3 3 1.343 3 3 3zm63 31c1.657 0 3-1.343 3-3s-1.343-3-3-3-3 1.343-3 3 1.343 3 3 3zM34 90c1.657 0 3-1.343 3-3s-1.343-3-3-3-3 1.343-3 3 1.343 3 3 3zm56-76c1.657 0 3-1.343 3-3s-1.343-3-3-3-3 1.343-3 3 1.343 3 3 3zM12 86c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm28-65c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm23-11c2.76 0 5-2.24 5-5s-2.24-5-5-5-5 2.24-5 5 2.24 5 5 5zm-6 60c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm29 22c2.76 0 5-2.24 5-5s-2.24-5-5-5-5 2.24-5 5 2.24 5 5 5zM32 63c2.76 0 5-2.24 5-5s-2.24-5-5-5-5 2.24-5 5 2.24 5 5 5zm57-13c2.76 0 5-2.24 5-5s-2.24-5-5-5-5 2.24-5 5 2.24 5 5 5zm-9-21c1.105 0 2-.895 2-2s-.895-2-2-2-2 .895-2 2 .895 2 2 2zM60 91c1.105 0 2-.895 2-2s-.895-2-2-2-2 .895-2 2 .895 2 2 2zM35 41c1.105 0 2-.895 2-2s-.895-2-2-2-2 .895-2 2 .895 2 2 2zM12 60c1.105 0 2-.895 2-2s-.895-2-2-2-2 .895-2 2 .895 2 2 2z" fill="%232563eb" fill-opacity="0.05" fill-rule="evenodd"/%3E%3C/svg%3E');
        }
        
        .floating-label {
            position: absolute;
            pointer-events: none;
            left: 12px;
            top: 15px;
            transition: 0.2s ease all;
        }
        
        input:focus ~ .floating-label,
        input:not(:placeholder-shown) ~ .floating-label {
            transform: translateY(-20px) scale(0.85);
            color: #3b82f6;
            background-color: white;
            padding: 0 5px;
        }
        
        .card-shine {
            position: relative;
            overflow: hidden;
        }
        
        .card-shine::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 50%;
            height: 100%;
            background: linear-gradient(
                to right, 
                rgba(255, 255, 255, 0) 0%,
                rgba(255, 255, 255, 0.1) 100%
            );
            transform: skewX(-25deg);
            animation: shine 3s infinite;
        }
        
        @keyframes shine {
            0% {
                left: -100%;
            }
            20% {
                left: 100%;
            }
            100% {
                left: 100%;
            }
        }
    </style>
</head>
<body class="bg-gradient-to-r from-blue-50 to-indigo-100 login-bg font-sans flex items-center justify-center min-h-screen p-4">
    <div class="w-full max-w-md">
        <!-- Logo or Brand -->
        <div class="text-center mb-6">
            <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-gradient-to-r from-blue-600 to-indigo-600 text-white text-2xl font-bold mb-2">
                <i class="fas fa-building"></i>
            </div>
            <h1 class="text-gray-800 text-2xl font-bold">Barangay Complaint & Request Management System</h1>
        </div>
        
        <!-- Success alert after registration -->
        <?php if ($registration_success): ?>
        <div class="mb-6 p-4 bg-green-50 border-l-4 border-green-500 text-green-700 rounded-md shadow-sm" role="alert">
            <div class="flex">
                <div class="flex-shrink-0">
                    <i class="fas fa-check-circle mt-1"></i>
                </div>
                <div class="ml-3">
                    <p class="font-medium">Registration successful!</p>
                    <p class="text-sm">Please log in with your new account.</p>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Login Card -->
        <div class="bg-white rounded-xl shadow-lg border border-gray-200 card-shine">
            <div class="p-8">
                <h2 class="text-2xl font-bold text-center text-gray-800 mb-8">Welcome Back</h2>

                <?php if (isset($_SESSION['login_error'])): ?>
                <div class="bg-red-50 border-l-4 border-red-500 text-red-700 p-4 rounded-md mb-6">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <i class="fas fa-exclamation-circle"></i>
                        </div>
                        <div class="ml-3">
                            <p><?= $_SESSION['login_error']; unset($_SESSION['login_error']); ?></p>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <form id="loginForm" action="index.php" method="POST">
                    <div class="mb-6 relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <i class="fas fa-envelope text-gray-400"></i>
                        </div>
                        <input type="email" id="email" name="email" placeholder=" " 
                               class="pl-10 w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors">
                        <label for="email" class="floating-label" style="margin-left: 20px;">Email Address</label>
                    </div>
                    
                    <div class="mb-6 relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <i class="fas fa-lock text-gray-400"></i>
                        </div>
                        <input type="password" id="password" name="password" placeholder=" "
                               class="pl-10 w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors">
                        <label for="password" class="floating-label" style="margin-left: 20px;">Password</label>
                        <div class="absolute inset-y-0 right-0 pr-3 flex items-center">
                            <button type="button" id="togglePassword" class="text-gray-400 hover:text-gray-600 focus:outline-none">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                    </div>
                    
                    <div class="flex items-center justify-between mb-6">
                        <div class="flex items-center">
                            <input id="remember_me" name="remember_me" type="checkbox" class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                            <label for="remember_me" class="ml-2 block text-sm text-gray-700">
                                Remember me
                            </label>
                        </div>
                        <div class="text-sm">
                            <a href="#" class="font-medium text-blue-600 hover:text-blue-500">
                                Forgot password?
                            </a>
                        </div>
                    </div>
                    
                    <button type="submit" class="w-full bg-gradient-to-r from-blue-600 to-indigo-600 text-white py-3 px-4 rounded-lg hover:from-blue-700 hover:to-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors flex items-center justify-center">
                        <i class="fas fa-sign-in-alt mr-2"></i>
                        Sign in
                    </button>
                </form>

                <div class="mt-8 pt-6 border-t border-gray-200">
                    <p class="text-center text-gray-600">
                        Don't have an account?
                        <a href="register.php" class="font-medium text-blue-600 hover:text-blue-500 transition-colors">
                            Create an account
                        </a>
                    </p>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Toggle password visibility
        document.getElementById('togglePassword').addEventListener('click', function() {
            const passwordInput = document.getElementById('password');
            const icon = this.querySelector('i');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                passwordInput.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        });
        
        // Floating label effect for empty inputs when form loads
        document.addEventListener('DOMContentLoaded', function() {
            const inputs = document.querySelectorAll('input[placeholder=" "]');
            inputs.forEach(input => {
                if (input.value !== '') {
                    const label = input.nextElementSibling;
                    if (label && label.classList.contains('floating-label')) {
                        label.classList.add('transform', 'scale-75', '-translate-y-6');
                    }
                }
            });
        });
    </script>
</body>
</html> 