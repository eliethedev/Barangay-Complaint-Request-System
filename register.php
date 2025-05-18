<?php
session_start();
require_once("baby_capstone_connection.php");

// Initialize variables
$full_name = '';
$email = '';
$password = '';
$confirm_password = '';
$phone = '';
$address = '';
$gender = '';
$birthdate = '';
$errors = [];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $gender = $_POST['gender'] ?? '';
    $birthdate = $_POST['birthdate'] ?? '';

    // Validation
    if (empty($full_name)) $errors[] = "Full Name is required";
    if (empty($email)) {
        $errors[] = "Email is required";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Please enter a valid email address";
    } else {
        // Check if email already exists
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetchColumn()) {
            $errors[] = "Email address is already registered. Please use a different email.";
        }
    }
    
    if (empty($password)) {
        $errors[] = "Password is required";
    } elseif (strlen($password) < 3) {
        $errors[] = "Password must be at least 3 characters long";
    }
    
    if ($password !== $confirm_password) {
        $errors[] = "Passwords do not match";
    }
    
    if (!empty($phone) && !preg_match('/^[0-9]{10,11}$/', $phone)) {
        $errors[] = "Phone number must be 10-11 digits";
    }
    
    if (!empty($birthdate)) {
        $today = new DateTime();
        $birth = new DateTime($birthdate);
        $age = $today->diff($birth)->y;
        
        if ($age < 13) {
            $errors[] = "You must be at least 13 years old to register";
        }
    }

    // Handle profile picture upload
    $profile_pic = ''; // Default

    if (isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = 'uploads/profile_pics/';
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
        $filename = uniqid() . '_' . basename($_FILES['profile_pic']['name']);
        $destination = $upload_dir . $filename;

        // Get file extension and validate file type
        $file_extension = strtolower(pathinfo($_FILES['profile_pic']['name'], PATHINFO_EXTENSION));
        $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
        
        if (!in_array($file_extension, $allowed_extensions) || !in_array($_FILES['profile_pic']['type'], $allowed_types)) {
            $errors[] = "Only JPG, PNG, and GIF files are allowed.";
        } elseif ($_FILES['profile_pic']['size'] > 5000000) { // 5MB limit
            $errors[] = "File size must be less than 5MB.";
        } elseif (move_uploaded_file($_FILES['profile_pic']['tmp_name'], $destination)) {
            $profile_pic = $destination;
        } else {
            $errors[] = "Failed to upload profile picture.";
        }
    }

    if (empty($errors)) {
        try {
            // Calculate age if birthdate is provided
            $age = null;
            if (!empty($birthdate)) {
                $today = new DateTime();
                $birth = new DateTime($birthdate);
                $age = $today->diff($birth)->y;
            }
            
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO users (full_name, email, password, phone, address, profile_pic, gender, birthdate, age, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            
            if ($stmt->execute([$full_name, $email, $hashed_password, $phone, $address, $profile_pic, $gender, $birthdate, $age, 'active'])) {
                // Set a success message in session
                $_SESSION['registration_success'] = true;
                header("Location: index.php");
                exit();
            } else {
                $errors[] = "Failed to register. Please try again.";
            }
        } catch (PDOException $e) {
            $errors[] = "Database error: " . $e->getMessage();
        }
    }
}
// Pre-fill form values after error
$form_values = [
    'full_name' => $full_name ?? '',
    'email' => $email ?? '',
    'phone' => $phone ?? '',
    'address' => $address ?? ''
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Account | Your App Name</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gradient-to-r from-blue-50 to-indigo-100 flex items-center justify-center min-h-screen p-4">
    <div class="bg-white p-8 rounded-xl shadow-lg w-full max-w-md border border-gray-200">
        <div class="text-center mb-8">
            <h2 class="text-3xl font-bold text-gray-800">Create Account</h2>
            <p class="text-gray-600 mt-2">Join our community today</p>
        </div>

        <?php if (!empty($errors)): ?>
            <div class="mb-6 p-4 bg-red-50 border-l-4 border-red-500 text-red-700 rounded">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <i class="fas fa-exclamation-circle mt-1"></i>
                    </div>
                    <div class="ml-3">
                        <p class="font-medium">Please correct the following errors:</p>
                        <ul class="mt-2 list-disc list-inside text-sm">
                            <?php foreach ($errors as $error): ?>
                                <li><?= htmlspecialchars($error) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <form id="registerForm" action="register.php" method="post" enctype="multipart/form-data" class="space-y-5">
            <div>
                <label for="full_name" class="block text-sm font-medium text-gray-700 mb-1">Full Name</label>
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <i class="fas fa-user text-gray-400"></i>
                    </div>
                    <input type="text" name="full_name" id="full_name" required 
                           value="<?= htmlspecialchars($form_values['full_name']) ?>"
                           class="pl-10 w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors">
                </div>
            </div>

            <div>
                <label for="email" class="block text-sm font-medium text-gray-700 mb-1">Email Address</label>
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <i class="fas fa-envelope text-gray-400"></i>
                    </div>
                    <input type="email" name="email" id="email" required 
                           value="<?= htmlspecialchars($form_values['email']) ?>"
                           class="pl-10 w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors">
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label for="phone" class="block text-sm font-medium text-gray-700 mb-1">Phone Number</label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <i class="fas fa-phone text-gray-400"></i>
                        </div>
                        <input type="tel" name="phone" id="phone" placeholder="9123456789"
                               value="<?= htmlspecialchars($form_values['phone']) ?>"
                               class="pl-10 w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors">
                    </div>
                    <p class="text-xs text-gray-500 mt-1">Format: 10-11 digits without spaces or dashes</p>
                </div>

                <div>
                    <label for="address" class="block text-sm font-medium text-gray-700 mb-1">Address</label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <i class="fas fa-home text-gray-400"></i>
                        </div>
                        <input type="text" name="address" id="address" 
                               value="<?= htmlspecialchars($form_values['address']) ?>"
                               class="pl-10 w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors">
                    </div>
                </div>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label for="gender" class="block text-sm font-medium text-gray-700 mb-1">Gender</label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <i class="fas fa-venus-mars text-gray-400"></i>
                        </div>
                        <select name="gender" id="gender" class="pl-10 w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors">
                            <option value="" <?= empty($gender) ? 'selected' : '' ?>>Select gender</option>
                            <option value="Male" <?= $gender === 'Male' ? 'selected' : '' ?>>Male</option>
                            <option value="Female" <?= $gender === 'Female' ? 'selected' : '' ?>>Female</option>
                            <option value="Other" <?= $gender === 'Other' ? 'selected' : '' ?>>Other</option>
                            <option value="Prefer not to say" <?= $gender === 'Prefer not to say' ? 'selected' : '' ?>>Prefer not to say</option>
                        </select>
                    </div>
                </div>

                <div>
                    <label for="birthdate" class="block text-sm font-medium text-gray-700 mb-1">Birthdate</label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <i class="fas fa-calendar-alt text-gray-400"></i>
                        </div>
                        <input type="date" name="birthdate" id="birthdate" 
                               value="<?= htmlspecialchars($birthdate) ?>"
                               max="<?= date('Y-m-d') ?>"
                               class="pl-10 w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors">
                    </div>
                    <p class="text-xs text-gray-500 mt-1">You must be at least 13 years old to register</p>
                </div>
            </div>

            <div>
                <label for="profile_pic" class="block text-sm font-medium text-gray-700 mb-1">Profile Picture</label>
                <div class="mt-1 flex items-center">
                    <span class="inline-block h-12 w-12 rounded-full overflow-hidden bg-gray-100">
                        <svg class="h-full w-full text-gray-300" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M24 20.993V24H0v-2.996A14.977 14.977 0 0112.004 15c4.904 0 9.26 2.354 11.996 5.993zM16.002 8.999a4 4 0 11-8 0 4 4 0 018 0z" />
                        </svg>
                    </span>
                    <label for="file-upload" class="ml-5 bg-white py-2 px-3 border border-gray-300 rounded-md shadow-sm text-sm leading-4 font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 cursor-pointer">
                        Choose File
                        <input id="file-upload" name="profile_pic" type="file" accept="image/*" class="sr-only" onchange="updateFileName(this)">
                    </label>
                    <span id="file-name" class="ml-4 text-sm text-gray-500"></span>
                </div>
            </div>

            <div>
                <label for="password" class="block text-sm font-medium text-gray-700 mb-1">Password</label>
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <i class="fas fa-lock text-gray-400"></i>
                    </div>
                    <input type="password" name="password" id="password" required
                           class="pl-10 w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors">
                    <div class="absolute inset-y-0 right-0 pr-3 flex items-center">
                        <button type="button" id="togglePassword" class="text-gray-400 hover:text-gray-600 focus:outline-none">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                </div>
            </div>

            <div>
                <label for="confirm_password" class="block text-sm font-medium text-gray-700 mb-1">Confirm Password</label>
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <i class="fas fa-lock text-gray-400"></i>
                    </div>
                    <input type="password" name="confirm_password" id="confirm_password" required
                           class="pl-10 w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors">
                </div>
            </div>

            <div class="pt-2">
                <button type="submit" class="w-full bg-gradient-to-r from-blue-600 to-indigo-600 text-white py-3 px-4 rounded-lg hover:from-blue-700 hover:to-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors flex items-center justify-center">
                    <i class="fas fa-user-plus mr-2"></i>
                    Create Account
                </button>
            </div>

            <p class="text-center mt-6 text-gray-600">
                Already have an account? 
                <a href="index.php" class="font-medium text-blue-600 hover:text-blue-500 transition-colors">
                    Sign in
                </a>
            </p>
        </form>
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

        // Show selected filename
        function updateFileName(input) {
            const fileName = input.files[0]?.name;
            document.getElementById('file-name').textContent = fileName || '';
        }

        // Form validation
        document.getElementById('registerForm').addEventListener('submit', function(e) {
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            
            // Clear previous validation feedback
            document.querySelectorAll('.validation-error').forEach(el => el.remove());
            
            let hasErrors = false;
            
            // Validate password length
            if (password.length < 3) {
                addValidationError('password', 'Password must be at least 8 characters');
                hasErrors = true;
            }
            
            // Validate password match
            if (password !== confirmPassword) {
                addValidationError('confirm_password', 'Passwords do not match');
                hasErrors = true;
            }
            
            if (hasErrors) {
                e.preventDefault();
            }
        });
        
        function addValidationError(inputId, message) {
            const input = document.getElementById(inputId);
            const errorDiv = document.createElement('div');
            errorDiv.className = 'validation-error text-red-500 text-xs mt-1';
            errorDiv.textContent = message;
            input.parentNode.after(errorDiv);
            input.classList.add('border-red-500');
        }
    </script>
</body>
</html>