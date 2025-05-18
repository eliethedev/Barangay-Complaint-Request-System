<?php
// Function to determine if current page matches the given page
function isActive($page) {
    $current_page = basename($_SERVER['PHP_SELF']);
    return $current_page === $page . '.php';
}
?>

<div class="sidebar bg-white w-64 shadow-lg hidden md:block">
        <div class="p-4 border-b border-gray-200">
            <div class="flex items-center">
                <img src="admin/assets/images/barangay.jpeg" alt="Barangay Logo" class="h-10 w-10 rounded-full">
                <div class="ml-3">
                    <h2 class="text-sm font-semibold text-gray-800">Barangay Old Sagay Complaint and Request System</h2>
                </div>
            </div>
        </div>
        <nav class="p-4">
            <div class="mb-6">
                <h3 class="text-xs uppercase font-semibold text-gray-500 mb-3">Menu</h3>
                <ul>
                    <li class="mb-2">
                        <a href="user_dashboard.php" class="flex items-center p-2 <?php echo isActive('user_dashboard') ? 'text-white bg-blue-600' : 'text-gray-700 hover:text-blue-600 hover:bg-blue-50'; ?> rounded-lg">
                            <i class="fas fa-home mr-3"></i>
                            <span>Dashboard</span>
                        </a>
                    </li>
                    <li class="mb-2">
                        <a href="submit_complaint.php" class="flex items-center p-2 <?php echo isActive('submit_complaint') ? 'text-white bg-blue-600' : 'text-gray-700 hover:text-blue-600 hover:bg-blue-50'; ?> rounded-lg">
                            <i class="fas fa-plus-circle mr-3"></i>
                            <span>Submit a Complaint</span>
                        </a>
                    </li>
                    <li class="mb-2">
                        <a href="submit_request.php" class="flex items-center p-2 <?php echo isActive('submit_request') ? 'text-white bg-blue-600' : 'text-gray-700 hover:text-blue-600 hover:bg-blue-50'; ?> rounded-lg">
                            <i class="fas fa-plus-circle mr-3"></i>
                            <span>Submit a Request</span>
                        </a>
                    </li>
                    <li class="mb-2">
                        <a href="submission.php" class="flex items-center p-2 <?php echo isActive('submission') ? 'text-white bg-blue-600' : 'text-gray-700 hover:text-blue-600 hover:bg-blue-50'; ?> rounded-lg">
                            <i class="fas fa-list mr-3"></i>
                            <span>View My Submissions</span>
                        </a>
                    </li>
                </ul>
            </div>
            <div>
                <h3 class="text-xs uppercase font-semibold text-gray-500 mb-3">Account</h3>
                <ul>
                    <li class="mb-2">
                        <a href="profile.php" class="flex items-center p-2 <?php echo isActive('profile') ? 'text-white bg-blue-600' : 'text-gray-700 hover:text-blue-600 hover:bg-blue-50'; ?> rounded-lg">
                            <i class="fas fa-user mr-3"></i>
                            <span>Profile</span>
                        </a>
                    </li>
                    <li class="mb-2">
                        <a href="logout.php" class="flex items-center p-2 <?php echo isActive('logout') ? 'text-white bg-blue-600' : 'text-gray-700 hover:text-blue-600 hover:bg-blue-50'; ?> rounded-lg">
                            <i class="fas fa-sign-out-alt mr-3"></i>
                            <span>Logout</span>
                        </a>
                    </li>
                </ul>
            </div>
        </nav>
    </div>
