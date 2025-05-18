<?php
// Function to determine if current page matches the given page
function isActive($page) {
    $current_page = basename($_SERVER['PHP_SELF']);
    return $current_page === $page . '.php';
}
?>

<!-- Sidebar -->
<div class="sidebar bg-white w-64 shadow-lg hidden md:block">
            <div class="p-4 border-b border-gray-200">
                <div class="flex items-center">
                    <img src="assets/images/barangay.jpeg" alt="Barangay Logo" class="h-12 w-12 rounded-full">
                    <div class="ml-3">
                        <h2 class="text-lg font-semibold text-gray-800">Barangay System</h2>
                        <p class="text-xs text-gray-500">Complaint & Request Portal</p>
                    </div>
                </div>
            </div>
            <nav class="p-4">
                <div class="mb-6">
                    <h3 class="text-xs uppercase font-semibold text-gray-500 mb-3">Main Menu</h3>
                    <ul>
                        <li class="mb-2">
                            <a href="dashboard.php" class="flex items-center p-2 <?php echo isActive('dashboard') ? 'text-blue-600 bg-blue-50' : 'text-gray-700 hover:text-blue-600 hover:bg-blue-50'; ?> rounded-lg">
                                <i class="fas fa-home mr-3"></i>
                                <span>Dashboard</span>
                            </a>
                        </li>
                        <li class="mb-2">
                            <a href="complaint.php" class="flex items-center p-2 <?php echo isActive('complaint') ? 'text-blue-600 bg-blue-50' : 'text-gray-700 hover:text-blue-600 hover:bg-blue-50'; ?> rounded-lg">
                                <i class="fas fa-exclamation-circle mr-3"></i>
                                <span>Complaints</span>
                            </a>
                        </li>
                        <li class="mb-2">
                            <a href="request.php" class="flex items-center p-2 <?php echo isActive('request') ? 'text-blue-600 bg-blue-50' : 'text-gray-700 hover:text-blue-600 hover:bg-blue-50'; ?> rounded-lg">
                                <i class="fas fa-hand-paper mr-3"></i>
                                <span>Requests</span>
                            </a>
                        </li>
                        <li class="mb-2">
                            <a href="residents.php" class="flex items-center p-2 <?php echo isActive('residents') ? 'text-blue-600 bg-blue-50' : 'text-gray-700 hover:text-blue-600 hover:bg-blue-50'; ?> rounded-lg">
                                <i class="fas fa-users mr-3"></i>
                                <span>Residents</span>
                            </a>
                        </li>
                        <li class="mb-2">
                            <a href="sms.php" class="flex items-center p-2 <?php echo isActive('sms') ? 'text-blue-600 bg-blue-50' : 'text-gray-700 hover:text-blue-600 hover:bg-blue-50'; ?> rounded-lg">
                                <i class="fas fa-sms mr-3"></i>
                                <span>SMS Notifications</span>
                            </a>
                        </li>
                    </ul>
                </div>
                <div>
                    <h3 class="text-xs uppercase font-semibold text-gray-500 mb-3">Settings</h3>
                    <ul>
                        <li class="mb-2">
                            <a href="settings.php" class="flex items-center p-2 <?php echo isActive('settings') ? 'text-blue-600 bg-blue-50' : 'text-gray-700 hover:text-blue-600 hover:bg-blue-50'; ?> rounded-lg">
                                <i class="fas fa-cog mr-3"></i>
                                <span>System Settings</span>
                            </a>
                        </li>
                        <li class="mb-2">
                            <a href="logout.php" class="flex items-center p-2 text-gray-700 hover:text-blue-600 hover:bg-blue-50 rounded-lg">
                                <i class="fas fa-sign-out-alt mr-3"></i>
                                <span>Logout</span>
                            </a>
                        </li>
                    </ul>
                </div>
            </nav>
        </div>