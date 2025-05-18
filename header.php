<header class="bg-white shadow-sm sticky top-0 left-0 right-0 z-50">
    <div class="flex justify-between items-center p-4">
        <div class="flex items-center">
            <button id="sidebarToggle" class="md:hidden text-gray-600 mr-4">
                <i class="fas fa-bars text-xl"></i>
            </button>
            <h1 class="text-xl font-semibold text-gray-800"></h1>
        </div>
        <div class="flex items-center space-x-4">
            <span class="text-sm font-medium"><?= htmlspecialchars($_SESSION['full_name'] ?? 'User') ?>!</span>
            <?php
            // Get the user's ID from session
            $userId = $_SESSION['user_id'] ?? null;
            
            // Construct the profile picture path
            $profilePicture = $userId ? "uploads/profile_pics/{$userId}.jpg" : 'assets/images/default-profile.avif';
            
            // Check if the profile picture exists
            if (!file_exists($_SERVER['DOCUMENT_ROOT'] . '/' . $profilePicture)) {
                $profilePicture = 'admin/assets/images/default-profile.avif';
            }
            ?>
            <img src="<?= htmlspecialchars($profilePicture) ?>" alt="User" class="h-8 w-8 rounded-full object-cover">
        </div>
    </div>
</header>
