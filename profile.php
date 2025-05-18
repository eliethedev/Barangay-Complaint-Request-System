<?php
session_start();

// DB connection
require_once 'baby_capstone_connection.php'; // contains $pdo

// Fetch user data
$user_id = $_SESSION['user_id'] ?? null;
$stmt = $pdo->prepare("SELECT id, full_name, email, phone, address FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Check if user exists
if (!$user) {
    // Handle user not found (redirect or show error)
    header("Location: user_dashboard.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $first_name = trim($_POST['first-name']);
    $last_name = trim($_POST['last-name']);
    $full_name = $first_name . ' ' . $last_name;

    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $address = trim($_POST['address'] ?? '');

    if (empty($full_name) || empty($email)) {
        $_SESSION['error'] = "Full name and email are required.";
        header("Location: profile.php");
        exit();
    }

    $profile_pic = $user['profile_pic'] ?? '';

    if (isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] === UPLOAD_ERR_OK) {
        $tmp_name = $_FILES['profile_pic']['tmp_name'];
        $upload_dir = 'uploads/profile_pics/';
        
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }

        // Use user ID as filename
        $filename = $user_id . '.jpg';
        $destination = $upload_dir . $filename;

        $file_type = mime_content_type($tmp_name);
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
        
        if (!in_array($file_type, $allowed_types)) {
            $_SESSION['error'] = "Invalid file type. Only JPG, PNG, and GIF files are allowed.";
            header("Location: profile.php");
            exit();
        }

        // Convert to JPEG if not already
        if ($file_type !== 'image/jpeg') {
            $image = imagecreatefromstring(file_get_contents($tmp_name));
            if ($image !== false) {
                imagejpeg($image, $destination, 90);
                imagedestroy($image);
            } else {
                $_SESSION['error'] = "Failed to process image.";
                header("Location: profile.php");
                exit();
            }
        } else {
            if (!move_uploaded_file($tmp_name, $destination)) {
                $_SESSION['error'] = "Failed to upload profile picture.";
                header("Location: profile.php");
                exit();
            }
        }

        // Store relative path in database
        $profile_pic = "uploads/profiles/{$filename}";

    try {
        $stmt = $pdo->prepare("UPDATE users SET full_name = ?, email = ?, phone = ?, address = ?, profile_pic = ? WHERE id = ?");
        $stmt->execute([$full_name, $email, $phone, $address, $profile_pic, $user_id]);
        $_SESSION['success'] = "Profile updated successfully!";
    } catch (PDOException $e) {
        $_SESSION['error'] = "Error updating profile: " . $e->getMessage();
    }

    header("Location: profile.php");
    exit();
}

// Handle password update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_password'])) {
    $current_password = $_POST['current-password'];
    $new_password = $_POST['new-password'];
    $confirm_password = $_POST['confirm-password'];

    // Validate password inputs
    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $_SESSION['error'] = "All password fields are required.";
        header("Location: profile.php");
        exit();
    }

    if ($new_password !== $confirm_password) {
        $_SESSION['error'] = "New password and confirmation do not match.";
        header("Location: profile.php");
        exit();
    }

    // Verify current password
    if (!password_verify($current_password, $user['password'])) {
        $_SESSION['error'] = "Current password is incorrect.";
        header("Location: profile.php");
        exit();
    }

    // Update password
    try {
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
        $stmt->execute([$hashed_password, $user_id]);
        $_SESSION['success'] = "Password updated successfully!";
        header("Location: profile.php");
        exit();
    } catch (PDOException $e) {
        $_SESSION['error'] = "Error updating password: " . $e->getMessage();
        header("Location: profile.php");
        exit();
    }
}

// Handle system configuration update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_system'])) {
    // Assuming you have fields for system configuration
    $barangay_name = trim($_POST['barangay-name']);
    $city_municipality = trim($_POST['city-municipality']);
    $province = trim($_POST['province']);
    $region = trim($_POST['region']);

    // Validate inputs
    if (empty($barangay_name) || empty($city_municipality) || empty($province) || empty($region)) {
        $_SESSION['error'] = "All fields are required.";
        header("Location: profile.php");
        exit();
    }

    // Update system settings in the database (assuming you have a settings table)
    try {
        $stmt = $pdo->prepare("UPDATE settings SET barangay_name = ?, city_municipality = ?, province = ?, region = ? WHERE id = 1");
        $stmt->execute([$barangay_name, $city_municipality, $province, $region]);
        $_SESSION['success'] = "System settings updated successfully!";
        header("Location: profile.php");
        exit();
    } catch (PDOException $e) {
        $_SESSION['error'] = "Error updating system settings: " . $e->getMessage();
        header("Location: profile.php");
        exit();
    }
}}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>User Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .gradient-bg {
            background: linear-gradient(135deg, #1e3a8a 0%, #1e40af 100%);
        }

        .sidebar {
            transition: all 0.3s ease;
        }
    </style>
</head>

<body class="bg-gray-100 font-sans">
    <div class="flex h-screen overflow-hidden">

        <!-- Sidebar -->
        <?php include 'sidebar.php'; ?>

        <!-- Main Content -->
        <div class="flex-1 overflow-auto">
        <?php include 'header.php'; ?>

            <main class="p-4 md:p-6">
                <!-- Settings Tabs -->
                <div class="bg-white rounded-lg shadow mb-6">
                    <div class="border-b border-gray-200">
                        <nav class="-mb-px flex space-x-8 px-6">
                            <button id="profileTab" class="py-4 px-1 border-b-2 font-medium text-sm tab-active">
                                <i class="fas fa-user mr-2"></i> Profile
                            </button>
                        </nav>
                    </div>
                </div>

                <!-- Profile Settings -->
                <div id="profileContent" class="settings-content">
                    <div class="bg-white shadow rounded-lg overflow-hidden">
                        <div class="px-4 py-5 sm:px-6 border-b border-gray-200">
                            <h3 class="text-lg leading-6 font-medium text-gray-900">
                                <i class="fas fa-user mr-2"></i> Profile Information
                            </h3>
                            <p class="mt-1 text-sm text-gray-500">Update your account's profile information and email
                                address.</p>
                        </div>
                        <div class="px-4 py-5 sm:p-6">
                            <form method="POST" enctype="multipart/form-data" action="profile.php" class="space-y-4">
                                <div class="grid grid-cols-1 gap-y-6 gap-x-4 sm:grid-cols-6">
                                    <div class="sm:col-span-3">
                                        <label for="first-name" class="block text-sm font-medium text-gray-700">First
                                            name</label>
                                        <div class="mt-1">
                                            <input type="text" name="first-name" id="first-name"
                                                autocomplete="given-name"
                                                value="<?php echo htmlspecialchars(explode(' ', $user['full_name'])[0]); ?>"
                                                class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                                        </div>
                                    </div>

                                    <div class="sm:col-span-3">
                                        <label for="last-name" class="block text-sm font-medium text-gray-700">Last
                                            name</label>
                                        <div class="mt-1">
                                            <input type="text" name="last-name" id="last-name"
                                                autocomplete="family-name"
                                                value="<?php echo htmlspecialchars(explode(' ', $user['full_name'])[1] ?? ''); ?>"
                                                class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                                        </div>
                                    </div>

                                    <div class="sm:col-span-4">
                                        <label for="email" class="block text-sm font-medium text-gray-700">Email
                                            address</label>
                                        <div class="mt-1">
                                            <input id="email" name="email" type="email" autocomplete="email"
                                                value="<?php echo htmlspecialchars($user['email']); ?>"
                                                class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                                        </div>
                                    </div>

                                    <div class="sm:col-span-4">
                                        <label for="address"
                                            class="block text-sm font-medium text-gray-700">Adress</label>
                                        <div class="mt-1">
                                            <input id="address" name="address" type="address" autocomplete="address"
                                                value="<?php echo htmlspecialchars($user['address']); ?>"
                                                class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                                        </div>
                                    </div>

                                    <div class="sm:col-span-3">
                                        <label for="phone" class="block text-sm font-medium text-gray-700">Phone</label>
                                        <div class="mt-1">
                                            <input type="text" name="phone" id="phone" autocomplete="tel"
                                                value="<?php echo htmlspecialchars($user['phone']); ?>"
                                                class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                                        </div>
                                    </div>

                                    <div class="sm:col-span-6">
                                        <label for="photo" class="block text-sm font-medium text-gray-700">Photo</label>
                                        <div class="mt-1 flex items-center">
                                            <span class="h-12 w-12 rounded-full overflow-hidden bg-gray-100">
                                                <img src="<?= !empty($user['profile_pic']) ? htmlspecialchars($user['profile_pic']) : 'https://via.placeholder.com/150' ?>"
                                                    class="h-8 w-8 rounded-full" alt="User">
                                            </span>
                                            <input type="file" name="profile_pic" id="profile_pic" accept="image/*"
                                                class="hidden">
                                            <label for="profile_pic"
                                                class="ml-5 bg-white py-2 px-3 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 hover:bg-gray-50 cursor-pointer">
                                                Change
                                            </label>
                                        </div>
                                    </div>
                                </div>

                                <div class="mt-8 flex justify-end">
                                    <button type="button"
                                        class="bg-white py-2 px-4 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                        Cancel
                                    </button>
                                    <button type="submit" name="update_profile"
                                        class="ml-3 inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                        Save
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
        </div>
        </main>
    </div>
    <script>
        // Toggle mobile sidebar
        document.getElementById('sidebarToggle').addEventListener('click', function () {
            document.querySelector('.sidebar').classList.toggle('hidden');
        });

        // Tab switching functionality
        const tabs = {
            profileTab: document.getElementById('profileTab'),
            securityTab: document.getElementById('securityTab'),
            systemTab: document.getElementById('systemTab'),
            smsTab: document.getElementById('smsTab'),
            profileContent: document.getElementById('profileContent'),
            securityContent: document.getElementById('securityContent'),
            systemContent: document.getElementById('systemContent'),
            smsContent: document.getElementById('smsContent')
        };

        function switchTab(activeTab) {
            // Reset all tabs
            Object.keys(tabs).forEach(key => {
                if (key.endsWith('Tab')) {
                    tabs[key].classList.remove('tab-active');
                    tabs[key].classList.add('border-transparent', 'text-gray-500', 'hover:text-gray-700', 'hover:border-gray-300');
                    tabs[key].classList.remove('text-blue-600');
                }
            });

            // Hide all content
            Object.keys(tabs).forEach(key => {
                if (key.endsWith('Content')) {
                    tabs[key].classList.add('hidden');
                }
            });

            // Activate selected tab
            activeTab.classList.add('tab-active');
            activeTab.classList.remove('border-transparent', 'text-gray-500', 'hover:text-gray-700', 'hover:border-gray-300');
            activeTab.classList.add('text-blue-600');

            // Show corresponding content
            const contentId = activeTab.id.replace('Tab', 'Content');
            document.getElementById(contentId).classList.remove('hidden');
        }

        // Add event listeners to tabs
        tabs.profileTab.addEventListener('click', () => switchTab(tabs.profileTab));
        tabs.securityTab.addEventListener('click', () => switchTab(tabs.securityTab));
        tabs.systemTab.addEventListener('click', () => switchTab(tabs.systemTab));
        tabs.smsTab.addEventListener('click', () => switchTab(tabs.smsTab));

        // Toggle password visibility
        function togglePassword(inputId) {
            const input = document.getElementById(inputId);
            const icon = input.nextElementSibling.querySelector('i');

            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }

        // SMS provider selection
        document.getElementById('sms-provider').addEventListener('change', function () {
            const provider = this.value;
            document.getElementById('semaphore-fields').classList.add('hidden');
            document.getElementById('twilio-fields').classList.add('hidden');

            if (provider === 'semaphore') {
                document.getElementById('semaphore-fields').classList.remove('hidden');
            } else if (provider === 'twilio') {
                document.getElementById('twilio-fields').classList.remove('hidden');
            }
        });

        // Test SMS connection
        function testSMSConnection() {
            const provider = document.getElementById('sms-provider').value;
            const testNumber = document.getElementById('sms-test-number').value;

            if (!testNumber) {
                alert('Please enter a test phone number');
                return;
            }

            alert(`Testing ${provider} SMS connection to ${testNumber}\n\nThis is a demo. In a real system, this would test the actual SMS gateway connection.`);
        }
    </script>
    </div>
</body>

</html>