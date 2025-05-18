<?php
// Include database connection and auth check
require_once '../baby_capstone_connection.php';
require_once 'auth_check.php';

// Initialize variables
$update_success = false;
$update_error = '';
$create_success = false;
$create_error = '';
$filter_zone = isset($_GET['zone']) ? $_GET['zone'] : 'all';
$filter_status = isset($_GET['status']) ? $_GET['status'] : 'all';
$search_query = isset($_GET['search']) ? $_GET['search'] : '';

// Handle form submission for creating new resident
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_resident'])) {
    // Check for CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $create_error = "Security validation failed. Please try again.";
    } else {
        $full_name = $_POST['full_name'] ?? '';
        $email = $_POST['email'] ?? '';
        $phone = $_POST['phone'] ?? '';
        $address = $_POST['address'] ?? '';
        $gender = $_POST['gender'] ?? '';
        $birthdate = $_POST['birthdate'] ?? '';
        
        // Basic validation
        if (empty($full_name) || empty($email) || empty($phone) || empty($address)) {
            $create_error = "All required fields must be filled out.";
        } else {
            try {
                // Generate a secure password (can be changed later)
                $password = password_hash(substr(md5(rand()), 0, 10), PASSWORD_DEFAULT);
                
                // Calculate age from birthdate
                $age = !empty($birthdate) ? date_diff(date_create($birthdate), date_create('today'))->y : null;
                
                // Handle profile picture upload if provided
                $profile_pic = null;
                if (isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] == 0) {
                    $allowed = ['jpg', 'jpeg', 'png', 'gif'];
                    $filename = $_FILES['profile_pic']['name'];
                    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
                    
                    if (in_array($ext, $allowed)) {
                        $new_filename = uniqid() . '_' . $filename;
                        $upload_path = '../uploads/profile_pics/' . $new_filename;
                        
                        if (move_uploaded_file($_FILES['profile_pic']['tmp_name'], $upload_path)) {
                            $profile_pic = $new_filename;
                        }
                    }
                }
                
                // Insert new resident into database
                $stmt = $pdo->prepare("INSERT INTO users (full_name, email, password, phone, address, gender, birthdate, age, profile_pic, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
                $stmt->execute([$full_name, $email, $password, $phone, $address, $gender, $birthdate, $age, $profile_pic]);
                
                if ($stmt->rowCount() > 0) {
                    $create_success = true;
                    
                    // Log the activity
                    $admin_id = $_SESSION['admin_id'];
                    $new_user_id = $pdo->lastInsertId();
                    $activity_stmt = $pdo->prepare("INSERT INTO activity_log (admin_id, activity_type, reference_id, details, created_at) VALUES (?, 'resident_create', ?, ?, NOW())");
                    $activity_stmt->execute([$admin_id, $new_user_id, "Added new resident: {$full_name}"]);
                } else {
                    $create_error = "Failed to add resident. Please try again.";
                }
            } catch (Exception $e) {
                $create_error = "Database error: " . $e->getMessage();
            }
        }
    }
}

// Handle form submission for updating resident
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_resident'])) {
    // Check for CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $update_error = "Security validation failed. Please try again.";
    } else {
        $user_id = $_POST['user_id'] ?? '';
        $full_name = $_POST['full_name'] ?? '';
        $email = $_POST['email'] ?? '';
        $phone = $_POST['phone'] ?? '';
        $address = $_POST['address'] ?? '';
        $status = $_POST['status'] ?? 'Active';
        $is_voter = isset($_POST['is_voter']) ? 1 : 0;
        $is_senior = isset($_POST['is_senior']) ? 1 : 0;
        $is_pwd = isset($_POST['is_pwd']) ? 1 : 0;
        
        if (empty($user_id) || empty($full_name) || empty($email)) {
            $update_error = "User ID, name and email are required fields.";
        } else {
            try {
                // Update resident information
                $stmt = $pdo->prepare("UPDATE users SET 
                    full_name = ?, 
                    email = ?, 
                    phone = ?, 
                    address = ?, 
                    status = ?,
                    is_voter = ?,
                    is_senior = ?,
                    is_pwd = ?,
                    updated_at = NOW() 
                    WHERE id = ?");
                $stmt->execute([$full_name, $email, $phone, $address, $status, $is_voter, $is_senior, $is_pwd, $user_id]);
                
                if ($stmt->rowCount() > 0) {
                    $update_success = true;
                    
                    // Log the activity
                    $admin_id = $_SESSION['admin_id'];
                    $activity_stmt = $pdo->prepare("INSERT INTO activity_log (admin_id, activity_type, reference_id, details, created_at) VALUES (?, 'resident_update', ?, ?, NOW())");
                    $activity_stmt->execute([$admin_id, $user_id, "Updated resident information: {$full_name}"]);
                } else {
                    $update_error = "No changes were made or resident not found.";
                }
            } catch (Exception $e) {
                $update_error = "Failed to update resident: " . $e->getMessage();
            }
        }
    }
}

// Pagination setup
$items_per_page = 10;
$current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($current_page - 1) * $items_per_page;

// Build the query based on filters
$base_query = "SELECT id, full_name, email, phone, address, gender, birthdate, age, profile_pic, status, created_at FROM users WHERE 1=1";
$params = [];
// We'll convert to named parameters later

// Convert to using named parameters for consistency
$named_params = [];
$param_index = 1;

// Apply zone filter (if implemented in your database)
if ($filter_zone !== 'all' && !empty($filter_zone)) {
    $base_query .= " AND address LIKE :zone_param";
    $named_params[':zone_param'] = "%{$filter_zone}%";
}

// Apply status filter
if ($filter_status !== 'all' && !empty($filter_status)) {
    $base_query .= " AND status = :status_param";
    $named_params[':status_param'] = $filter_status;
}

// Apply search filter
if (!empty($search_query)) {
    $search_param = "%{$search_query}%";
    $base_query .= " AND (full_name LIKE :search_name OR email LIKE :search_email OR phone LIKE :search_phone OR address LIKE :search_address)"; 
    $named_params[':search_name'] = $search_param;
    $named_params[':search_email'] = $search_param;
    $named_params[':search_phone'] = $search_param;
    $named_params[':search_address'] = $search_param;
}

// Count total residents for pagination
$count_query = "SELECT COUNT(*) FROM users WHERE " . substr($base_query, strpos($base_query, "WHERE") + 6);
$count_stmt = $pdo->prepare($count_query);
$count_stmt->execute($named_params);
$total_residents = $count_stmt->fetchColumn();

// Add ordering and pagination to the main query
$query = $base_query . " ORDER BY created_at DESC LIMIT :offset, :limit";

// Fetch filtered residents with pagination
$stmt = $pdo->prepare($query);

// Bind all parameters (both filter and pagination)
foreach ($named_params as $param_name => $param_value) {
    $stmt->bindValue($param_name, $param_value);
}

// Bind pagination parameters
$stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
$stmt->bindParam(':limit', $items_per_page, PDO::PARAM_INT);

// Execute the query
$stmt->execute();
$residents = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Generate CSRF token
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Helper function to extract zone from address
function extractZone($address) {
    if (preg_match('/zone\s*(\d+)/i', $address, $matches)) {
        return 'Zone ' . $matches[1];
    }
    return 'Unknown';
}

// Helper function to calculate age from birthdate
function calculateAge($birthdate) {
    if (empty($birthdate)) return null;
    return date_diff(date_create($birthdate), date_create('today'))->y;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Residents Management - Barangay System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .sidebar {
            transition: all 0.3s ease;
        }
        .notification-badge {
            position: absolute;
            top: -5px;
            right: -5px;
            background-color: #ef4444;
            color: white;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
        }
        .status-active {
            background-color: #dcfce7;
            color: #15803d;
        }
        .status-inactive {
            background-color: #fee2e2;
            color: #b91c1c;
        }
        .status-pending {
            background-color: #fef3c7;
            color: #d97706;
        }
        .resident-male {
            background-color: #dbeafe;
            color: #1d4ed8;
        }
        .resident-female {
            background-color: #fce7f3;
            color: #be185d;
        }
        .resident-senior {
            background-color: #ede9fe;
            color: #7c3aed;
        }
        .resident-pwd {
            background-color: #ecfccb;
            color: #65a30d;
        }
    </style>
</head>
<body class="bg-gray-100 font-sans">
    <div class="flex h-screen overflow-hidden">
        <!-- Sidebar -->
        <?php include 'sidebar.php' ?>

        <!-- Main Content -->
        <div class="flex-1 overflow-auto">
            <!-- Top Navigation -->
            <?php include 'header.php' ?>

            <!-- Residents Content -->
            <main class="p-4 md:p-6">
                <!-- Filters and Actions -->
                <div class="bg-white rounded-lg shadow p-4 mb-6">
                    <div class="flex flex-col md:flex-row md:items-center md:justify-between">
                        <div class="mb-4 md:mb-0">
                            <h2 class="text-lg font-semibold text-gray-800">Barangay Residents</h2>
                            <p class="text-sm text-gray-500">Manage all registered residents in the barangay</p>
                        </div>
                        <form method="GET" action="residents.php" class="flex flex-col sm:flex-row space-y-2 sm:space-y-0 sm:space-x-2">
                            <div class="relative">
                                <select name="zone" id="zoneFilter" class="block w-full pl-3 pr-10 py-2 text-base border border-gray-300 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm rounded-md">
                                    <option value="all" <?= $filter_zone === 'all' ? 'selected' : '' ?>>All Zones</option>
                                    <option value="Zone 1" <?= $filter_zone === 'Zone 1' ? 'selected' : '' ?>>Zone 1</option>
                                    <option value="Zone 2" <?= $filter_zone === 'Zone 2' ? 'selected' : '' ?>>Zone 2</option>
                                    <option value="Zone 3" <?= $filter_zone === 'Zone 3' ? 'selected' : '' ?>>Zone 3</option>
                                    <option value="Zone 4" <?= $filter_zone === 'Zone 4' ? 'selected' : '' ?>>Zone 4</option>
                                    <option value="Zone 5" <?= $filter_zone === 'Zone 5' ? 'selected' : '' ?>>Zone 5</option>
                                </select>
                            </div>
                            <div class="relative">
                                <select name="status" id="statusFilter" class="block w-full pl-3 pr-10 py-2 text-base border border-gray-300 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm rounded-md">
                                    <option value="all" <?= $filter_status === 'all' ? 'selected' : '' ?>>All Status</option>
                                    <option value="Active" <?= $filter_status === 'Active' ? 'selected' : '' ?>>Active</option>
                                    <option value="Inactive" <?= $filter_status === 'Inactive' ? 'selected' : '' ?>>Inactive</option>
                                    <option value="Pending" <?= $filter_status === 'Pending' ? 'selected' : '' ?>>Pending</option>
                                </select>
                            </div>
                            <div class="relative">
                                <input type="text" name="search" value="<?= htmlspecialchars($search_query) ?>" placeholder="Search residents..." class="block w-full pl-3 pr-10 py-2 text-base border border-gray-300 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm rounded-md">
                            </div>
                            <button type="submit" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                <i class="fas fa-filter mr-2"></i> Apply Filters
                            </button>
                            <a href="residents.php" class="inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md shadow-sm text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                <i class="fas fa-times mr-2"></i> Clear
                            </a>
                        </form>
                    </div>
                </div>

                <!-- Residents Table -->
                <div class="bg-white shadow rounded-lg overflow-hidden">
                    <div class="px-4 py-3 border-b border-gray-200 flex justify-between items-center">
                        <div>
                            <h3 class="text-sm font-medium text-gray-700">Registered Residents</h3>
                            <p class="text-xs text-gray-500">Showing 1 to 8 of 256 residents</p>
                        </div>
                        <div>
                            <button class="inline-flex items-center px-3 py-1.5 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                <i class="fas fa-download mr-2"></i> Export
                            </button>
                            <button onclick="openNewResidentModal()" class="ml-2 inline-flex items-center px-3 py-1.5 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                <i class="fas fa-plus mr-2"></i> New Resident
                            </button>
                        </div>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Resident ID</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Contact</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Address</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Category</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                    <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php
                                // Display success/error messages if any
                                if ($update_success): ?>
                                    <tr>
                                        <td colspan="7" class="px-6 py-4">
                                            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4" role="alert">
                                                <strong class="font-bold">Success!</strong>
                                                <span class="block sm:inline">Resident information has been updated successfully.</span>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                                
                                <?php if ($update_error): ?>
                                    <tr>
                                        <td colspan="7" class="px-6 py-4">
                                            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
                                                <strong class="font-bold">Error!</strong>
                                                <span class="block sm:inline"><?= htmlspecialchars($update_error) ?></span>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                                
                                <?php if ($create_success): ?>
                                    <tr>
                                        <td colspan="7" class="px-6 py-4">
                                            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4" role="alert">
                                                <strong class="font-bold">Success!</strong>
                                                <span class="block sm:inline">New resident has been added successfully.</span>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                                
                                <?php if ($create_error): ?>
                                    <tr>
                                        <td colspan="7" class="px-6 py-4">
                                            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
                                                <strong class="font-bold">Error!</strong>
                                                <span class="block sm:inline"><?= htmlspecialchars($create_error) ?></span>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                                
                                <?php if (count($residents) === 0): ?>
                                    <tr>
                                        <td colspan="7" class="px-6 py-4 text-center text-gray-500">
                                            No residents found matching your criteria. Try adjusting your filters.
                                        </td>
                                    </tr>
                                <?php endif; ?>
                                
                                <?php foreach ($residents as $resident):
                                    // Determine resident status class
                                    $statusClass = '';
                                    switch ($resident['status'] ?? 'Active') {
                                        case 'Active':
                                            $statusClass = 'status-active';
                                            break;
                                        case 'Inactive':
                                            $statusClass = 'status-inactive';
                                            break;
                                        case 'Pending':
                                            $statusClass = 'status-pending';
                                            break;
                                        default:
                                            $statusClass = 'status-active';
                                    }
                                    
                                    // Determine category based on age and other factors
                                    $category = 'Regular';
                                    $categoryClass = '';
                                    
                                    // Check if senior citizen (age >= 60)
                                    $isSenior = ($resident['age'] ?? 0) >= 60;
                                    
                                    if ($isSenior) {
                                        $category = 'Senior';
                                        $categoryClass = 'resident-senior';
                                    } else {
                                        // Default to gender-based styling if not senior
                                        $categoryClass = ($resident['gender'] ?? '') == 'Male' ? 'resident-male' : 'resident-female';
                                    }
                                    
                                    // Format resident ID
                                    $residentId = 'RES-' . str_pad($resident['id'], 6, '0', STR_PAD_LEFT);
                                    
                                    // Get profile picture or use placeholder
                                    $profilePic = !empty($resident['profile_pic']) ? 
                                        '../uploads/profile_pics/' . htmlspecialchars($resident['profile_pic']) : 
                                        'https://ui-avatars.com/api/?name=' . urlencode($resident['full_name']) . '&background=random';
                                ?>
                                    <tr class="hover:bg-gray-50" data-id="<?= $resident['id'] ?>" 
                                        data-profile-pic="<?= htmlspecialchars($resident['profile_pic'] ?? '') ?>"
                                        data-is-voter="<?= isset($resident['is_voter']) && $resident['is_voter'] ? '1' : '0' ?>"
                                        data-is-pwd="<?= isset($resident['is_pwd']) && $resident['is_pwd'] ? '1' : '0' ?>"
                                        data-age="<?= htmlspecialchars($resident['age'] ?? '') ?>"
                                        data-gender="<?= htmlspecialchars($resident['gender'] ?? '') ?>"
                                        data-birthdate="<?= htmlspecialchars($resident['birthdate'] ?? '') ?>">
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?= $residentId ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="flex items-center">
                                                <div class="flex-shrink-0 h-10 w-10">
                                                    <img class="h-10 w-10 rounded-full" src="<?= $profilePic ?>" alt="">
                                                </div>
                                                <div class="ml-4">
                                                    <div class="text-sm font-medium text-gray-900"><?= htmlspecialchars($resident['full_name']) ?></div>
                                                    <div class="text-sm text-gray-500"><?= ($resident['age'] ?? 'N/A') ?> years old</div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?= htmlspecialchars($resident['phone'] ?? 'N/A') ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?= htmlspecialchars($resident['address'] ?? 'N/A') ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?= $categoryClass ?>"><?= $category ?></span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?= $statusClass ?>"><?= htmlspecialchars($resident['status'] ?? 'Active') ?></span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                            <button onclick="viewResident('<?= $resident['id'] ?>')" class="text-blue-600 hover:text-blue-900 mr-3" title="View Details"><i class="fas fa-eye"></i></button>
                                            <button onclick="editResident('<?= $resident['id'] ?>')" class="text-yellow-600 hover:text-yellow-900 mr-3" title="Edit Resident"><i class="fas fa-edit"></i></button>
                                            <button onclick="sendSMS('<?= htmlspecialchars($resident['phone'] ?? '') ?>', '<?= htmlspecialchars($resident['full_name']) ?>')" class="text-green-600 hover:text-green-900" title="Send SMS"><i class="fas fa-sms"></i></button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="bg-white px-4 py-3 flex items-center justify-between border-t border-gray-200 sm:px-6">
                        <?php
                        // Pagination setup
                        $items_per_page = 10;
                        $current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
                        $offset = ($current_page - 1) * $items_per_page;
                        
                        // Calculate total pages
                        $total_pages = ceil($total_residents / $items_per_page);
                        
                        // Ensure current page is valid
                        if ($current_page < 1) $current_page = 1;
                        if ($current_page > $total_pages && $total_pages > 0) $current_page = $total_pages;
                        
                        // Calculate display range for residents
                        $start_item = $offset + 1;
                        $end_item = min($offset + $items_per_page, $total_residents);
                        
                        // Build pagination URL with existing filters
                        function buildPaginationUrl($page) {
                            $params = $_GET;
                            $params['page'] = $page;
                            return 'residents.php?' . http_build_query($params);
                        }
                        ?>
                        <div class="flex-1 flex justify-between sm:hidden">
                            <?php if ($current_page > 1): ?>
                                <a href="<?= buildPaginationUrl($current_page - 1) ?>" class="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">Previous</a>
                            <?php else: ?>
                                <span class="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-300 bg-gray-100 cursor-not-allowed">Previous</span>
                            <?php endif; ?>
                            
                            <?php if ($current_page < $total_pages): ?>
                                <a href="<?= buildPaginationUrl($current_page + 1) ?>" class="ml-3 relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">Next</a>
                            <?php else: ?>
                                <span class="ml-3 relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-300 bg-gray-100 cursor-not-allowed">Next</span>
                            <?php endif; ?>
                        </div>
                        <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
                            <div>
                                <p class="text-sm text-gray-700">
                                    <?php if ($total_residents > 0): ?>
                                        Showing <span class="font-medium"><?= $start_item ?></span> to <span class="font-medium"><?= $end_item ?></span> of <span class="font-medium"><?= $total_residents ?></span> residents
                                    <?php else: ?>
                                        No residents found
                                    <?php endif; ?>
                                </p>
                            </div>
                            <?php if ($total_pages > 1): ?>
                            <div>
                                <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px" aria-label="Pagination">
                                    <!-- Previous page link -->
                                    <?php if ($current_page > 1): ?>
                                        <a href="<?= buildPaginationUrl($current_page - 1) ?>" class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                                            <span class="sr-only">Previous</span>
                                            <i class="fas fa-chevron-left"></i>
                                        </a>
                                    <?php else: ?>
                                        <span class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 bg-gray-100 text-sm font-medium text-gray-300 cursor-not-allowed">
                                            <span class="sr-only">Previous</span>
                                            <i class="fas fa-chevron-left"></i>
                                        </span>
                                    <?php endif; ?>
                                    
                                    <!-- Page numbers -->
                                    <?php
                                    $range = 2; // How many pages to show on each side of current page
                                    
                                    // Always show first page
                                    if ($current_page > $range + 1): ?>
                                        <a href="<?= buildPaginationUrl(1) ?>" class="relative inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-700 hover:bg-gray-50">1</a>
                                        
                                        <?php if ($current_page > $range + 2): ?>
                                            <span class="relative inline-flex items-center px-4 py-2 border border-gray-300 bg-gray-50 text-sm font-medium text-gray-500">...</span>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                    
                                    <!-- Pages around current page -->
                                    <?php
                                    $start_page = max(1, $current_page - $range);
                                    $end_page = min($total_pages, $current_page + $range);
                                    
                                    for ($i = $start_page; $i <= $end_page; $i++): ?>
                                        <?php if ($i == $current_page): ?>
                                            <span class="z-10 bg-blue-50 border-blue-500 text-blue-600 relative inline-flex items-center px-4 py-2 border text-sm font-medium"><?= $i ?></span>
                                        <?php else: ?>
                                            <a href="<?= buildPaginationUrl($i) ?>" class="bg-white border-gray-300 text-gray-500 hover:bg-gray-50 relative inline-flex items-center px-4 py-2 border text-sm font-medium"><?= $i ?></a>
                                        <?php endif; ?>
                                    <?php endfor; ?>
                                    
                                    <!-- Always show last page -->
                                    <?php if ($current_page < $total_pages - $range): ?>
                                        <?php if ($current_page < $total_pages - $range - 1): ?>
                                            <span class="relative inline-flex items-center px-4 py-2 border border-gray-300 bg-gray-50 text-sm font-medium text-gray-500">...</span>
                                        <?php endif; ?>
                                        
                                        <a href="<?= buildPaginationUrl($total_pages) ?>" class="bg-white border-gray-300 text-gray-500 hover:bg-gray-50 relative inline-flex items-center px-4 py-2 border text-sm font-medium"><?= $total_pages ?></a>
                                    <?php endif; ?>
                                    
                                    <!-- Next page link -->
                                    <?php if ($current_page < $total_pages): ?>
                                        <a href="<?= buildPaginationUrl($current_page + 1) ?>" class="relative inline-flex items-center px-2 py-2 rounded-r-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                                            <span class="sr-only">Next</span>
                                            <i class="fas fa-chevron-right"></i>
                                        </a>
                                    <?php else: ?>
                                        <span class="relative inline-flex items-center px-2 py-2 rounded-r-md border border-gray-300 bg-gray-100 text-sm font-medium text-gray-300 cursor-not-allowed">
                                            <span class="sr-only">Next</span>
                                            <i class="fas fa-chevron-right"></i>
                                        </span>
                                    <?php endif; ?>
                                </nav>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Resident Details Modal -->
                <div id="residentModal" class="hidden fixed inset-0 overflow-y-auto z-50">
                    <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                        <div class="fixed inset-0 transition-opacity" aria-hidden="true">
                            <div class="absolute inset-0 bg-gray-500 opacity-75"></div>
                        </div>
                        <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
                        <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-2xl sm:w-full">
                            <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                                <div class="sm:flex sm:items-start">
                                    <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left w-full">
                                        <h3 class="text-lg leading-6 font-medium text-gray-900" id="modalTitle">Resident Details</h3>
                                        <div class="mt-2">
                                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                                <div class="md:col-span-2">
                                                    <div class="flex items-center">
                                                        <img id="modalResidentPhoto" class="h-16 w-16 rounded-full" src="" alt="">
                                                        <div class="ml-4">
                                                            <h4 id="modalResidentName" class="text-lg font-semibold"></h4>
                                                            <p id="modalResidentAgeGender" class="text-sm text-gray-500"></p>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div>
                                                    <p class="text-sm font-medium text-gray-500">Resident ID</p>
                                                    <p id="modalResidentId" class="mt-1 text-sm text-gray-900"></p>
                                                </div>
                                                <div>
                                                    <p class="text-sm font-medium text-gray-500">Date Registered</p>
                                                    <p id="modalDateRegistered" class="mt-1 text-sm text-gray-900"></p>
                                                </div>
                                                <div>
                                                    <p class="text-sm font-medium text-gray-500">Contact Number</p>
                                                    <p id="modalContact" class="mt-1 text-sm text-gray-900"></p>
                                                </div>
                                                <div>
                                                    <p class="text-sm font-medium text-gray-500">Email Address</p>
                                                    <p id="modalEmail" class="mt-1 text-sm text-gray-900"></p>
                                                </div>
                                                <div>
                                                    <p class="text-sm font-medium text-gray-500">Status</p>
                                                    <p id="modalStatus" class="mt-1 text-sm text-gray-900"></p>
                                                </div>
                                                <div>
                                                    <p class="text-sm font-medium text-gray-500">Category</p>
                                                    <p id="modalCategory" class="mt-1 text-sm text-gray-900"></p>
                                                </div>
                                                <div class="md:col-span-2">
                                                    <p class="text-sm font-medium text-gray-500">Complete Address</p>
                                                    <p id="modalAddress" class="mt-1 text-sm text-gray-900"></p>
                                                </div>
                                                <div class="md:col-span-2">
                                                    <p class="text-sm font-medium text-gray-500">Additional Information</p>
                                                    <div class="mt-2 grid grid-cols-2 gap-2">
                                                        <div class="flex items-center">
                                                            <input id="modalVoter" type="checkbox" class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded" disabled>
                                                            <label for="modalVoter" class="ml-2 block text-sm text-gray-700">Registered Voter</label>
                                                        </div>
                                                        <div class="flex items-center">
                                                            <input id="modalPWD" type="checkbox" class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded" disabled>
                                                            <label for="modalPWD" class="ml-2 block text-sm text-gray-700">Person with Disability</label>
                                                        </div>
                                                        <div class="flex items-center">
                                                            <input id="modalSenior" type="checkbox" class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded" disabled>
                                                            <label for="modalSenior" class="ml-2 block text-sm text-gray-700">Senior Citizen</label>
                                                        </div>
                                                        <div class="flex items-center">
                                                            <input id="modalHead" type="checkbox" class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded" disabled>
                                                            <label for="modalHead" class="ml-2 block text-sm text-gray-700">Household Head</label>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="md:col-span-2">
                                                    <p class="text-sm font-medium text-gray-500">Family Members</p>
                                                    <div id="modalFamilyMembers" class="mt-1 text-sm text-gray-900">
                                                        <!-- Family members will be added here dynamically -->
                                                    </div>
                                                </div>
                                                <div class="md:col-span-2">
                                                    <p class="text-sm font-medium text-gray-500">Status</p>
                                                    <select id="modalStatus" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                                                        <option value="Active">Active</option>
                                                        <option value="Inactive">Inactive</option>
                                                        <option value="Pending">Pending</option>
                                                    </select>
                                                </div>
                                                <div class="md:col-span-2">
                                                    <p class="text-sm font-medium text-gray-500">Notes</p>
                                                    <textarea id="modalNotes" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm"></textarea>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                                <button type="button" onclick="updateResident()" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-blue-600 text-base font-medium text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:ml-3 sm:w-auto sm:text-sm">
                                    Update Resident
                                </button>
                                <button type="button" onclick="closeModal()" class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
                                    Close
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- New Resident Modal -->
                <div id="newResidentModal" class="hidden fixed inset-0 overflow-y-auto z-50">
                    <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                        <div class="fixed inset-0 transition-opacity" aria-hidden="true">
                            <div class="absolute inset-0 bg-gray-500 opacity-75"></div>
                        </div>
                        <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
                        <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-2xl sm:w-full">
                            <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                                <div class="sm:flex sm:items-start">
                                    <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left w-full">
                                        <h3 class="text-lg leading-6 font-medium text-gray-900">Register New Resident</h3>
                                        <div class="mt-2">
                                            <form id="newResidentForm">
                                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                                    <div class="md:col-span-2">
                                                        <label class="block text-sm font-medium text-gray-700">Photo</label>
                                                        <div class="mt-1 flex items-center">
                                                            <span class="inline-block h-12 w-12 rounded-full overflow-hidden bg-gray-100">
                                                                <svg class="h-full w-full text-gray-300" fill="currentColor" viewBox="0 0 24 24">
                                                                    <path d="M24 20.993V24H0v-2.996A14.977 14.977 0 0112.004 15c4.904 0 9.26 2.354 11.996 5.993zM16.002 8.999a4 4 0 11-8 0 4 4 0 018 0z" />
                                                                </svg>
                                                            </span>
                                                            <button type="button" class="ml-5 bg-white py-2 px-3 border border-gray-300 rounded-md shadow-sm text-sm leading-4 font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                                                Upload
                                                            </button>
                                                        </div>
                                                    </div>
                                                    <div>
                                                        <label for="firstName" class="block text-sm font-medium text-gray-700">First Name</label>
                                                        <input type="text" id="firstName" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                                                    </div>
                                                    <div>
                                                        <label for="middleName" class="block text-sm font-medium text-gray-700">Middle Name</label>
                                                        <input type="text" id="middleName" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                                                    </div>
                                                    <div>
                                                        <label for="lastName" class="block text-sm font-medium text-gray-700">Last Name</label>
                                                        <input type="text" id="lastName" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                                                    </div>
                                                    <div>
                                                        <label for="suffix" class="block text-sm font-medium text-gray-700">Suffix</label>
                                                        <input type="text" id="suffix" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                                                    </div>
                                                    <div>
                                                        <label for="birthDate" class="block text-sm font-medium text-gray-700">Date of Birth</label>
                                                        <input type="date" id="birthDate" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                                                    </div>
                                                    <div>
                                                        <label for="gender" class="block text-sm font-medium text-gray-700">Gender</label>
                                                        <select id="gender" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                                                            <option value="">Select Gender</option>
                                                            <option value="Male">Male</option>
                                                            <option value="Female">Female</option>
                                                        </select>
                                                    </div>
                                                    <div>
                                                        <label for="civilStatus" class="block text-sm font-medium text-gray-700">Civil Status</label>
                                                        <select id="civilStatus" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                                                            <option value="">Select Status</option>
                                                            <option value="Single">Single</option>
                                                            <option value="Married">Married</option>
                                                            <option value="Widowed">Widowed</option>
                                                            <option value="Separated">Separated</option>
                                                        </select>
                                                    </div>
                                                    <div>
                                                        <label for="contactNumber" class="block text-sm font-medium text-gray-700">Contact Number</label>
                                                        <input type="tel" id="contactNumber" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                                                    </div>
                                                    <div>
                                                        <label for="email" class="block text-sm font-medium text-gray-700">Email Address</label>
                                                        <input type="email" id="email" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                                                    </div>
                                                    <div>
                                                        <label for="zone" class="block text-sm font-medium text-gray-700">Zone</label>
                                                        <select id="zone" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                                                            <option value="">Select Zone</option>
                                                            <option value="1">Zone 1</option>
                                                            <option value="2">Zone 2</option>
                                                            <option value="3">Zone 3</option>
                                                            <option value="4">Zone 4</option>
                                                            <option value="5">Zone 5</option>
                                                        </select>
                                                    </div>
                                                    <div class="md:col-span-2">
                                                        <label for="address" class="block text-sm font-medium text-gray-700">Complete Address</label>
                                                        <textarea id="address" rows="2" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm"></textarea>
                                                    </div>
                                                    <div class="md:col-span-2">
                                                        <label class="block text-sm font-medium text-gray-700">Additional Information</label>
                                                        <div class="mt-2 grid grid-cols-2 gap-2">
                                                            <div class="flex items-center">
                                                                <input id="isVoter" name="isVoter" type="checkbox" class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                                                                <label for="isVoter" class="ml-2 block text-sm text-gray-700">Registered Voter</label>
                                                            </div>
                                                            <div class="flex items-center">
                                                                <input id="isPWD" name="isPWD" type="checkbox" class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                                                                <label for="isPWD" class="ml-2 block text-sm text-gray-700">Person with Disability</label>
                                                            </div>
                                                            <div class="flex items-center">
                                                                <input id="isSenior" name="isSenior" type="checkbox" class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                                                                <label for="isSenior" class="ml-2 block text-sm text-gray-700">Senior Citizen</label>
                                                            </div>
                                                            <div class="flex items-center">
                                                                <input id="isHead" name="isHead" type="checkbox" class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                                                                <label for="isHead" class="ml-2 block text-sm text-gray-700">Household Head</label>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                                <button type="button" onclick="submitNewResident()" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-blue-600 text-base font-medium text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:ml-3 sm:w-auto sm:text-sm">
                                    Register Resident
                                </button>
                                <button type="button" onclick="closeNewResidentModal()" class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
                                    Cancel
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- SMS Modal -->
                <div id="smsModal" class="hidden fixed inset-0 overflow-y-auto z-50">
                    <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                        <div class="fixed inset-0 transition-opacity" aria-hidden="true">
                            <div class="absolute inset-0 bg-gray-500 opacity-75"></div>
                        </div>
                        <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
                        <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                            <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                                <div class="sm:flex sm:items-start">
                                    <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left w-full">
                                        <h3 class="text-lg leading-6 font-medium text-gray-900">Send SMS Notification</h3>
                                        <div class="mt-2">
                                            <div class="mb-4">
                                                <label for="smsRecipient" class="block text-sm font-medium text-gray-700">Recipient</label>
                                                <input type="text" id="smsRecipient" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm" readonly>
                                            </div>
                                            <div class="mb-4">
                                                <label for="smsMessage" class="block text-sm font-medium text-gray-700">Message</label>
                                                <textarea id="smsMessage" rows="4" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">Dear resident, this is a notification from your barangay.</textarea>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                                <button type="button" onclick="sendSMSNow()" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-green-600 text-base font-medium text-white hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 sm:ml-3 sm:w-auto sm:text-sm">
                                    <i class="fas fa-paper-plane mr-2"></i> Send SMS
                                </button>
                                <button type="button" onclick="closeSMSModal()" class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
                                    Cancel
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script>
        // Toggle mobile sidebar
        document.getElementById('sidebarToggle').addEventListener('click', function() {
            document.querySelector('.sidebar').classList.toggle('hidden');
        });

        // View resident details
        function viewResident(residentId) {
            console.log('Viewing resident with ID:', residentId); // Debugging

            // Find the resident row in the table
            const row = document.querySelector(`tr[data-id="${residentId}"]`);
            if (!row) {
                alert('Resident not found');
                return;
            }

            // Get resident data from the row attributes
            const name = row.querySelector('.text-sm.font-medium.text-gray-900').textContent;
            const age = row.getAttribute('data-age') || 'N/A';
            const gender = row.getAttribute('data-gender') || 'N/A';
            const profilePic = row.querySelector('.h-10.w-10.rounded-full').src;
            const email = row.cells[2].querySelector('.text-sm.text-gray-500') ? 
                         row.cells[2].querySelector('.text-sm.text-gray-500').textContent : 'N/A';
            const phone = row.cells[2].textContent;
            const address = row.cells[3].textContent;
            const status = row.cells[5].querySelector('span').textContent;
            const isVoter = row.getAttribute('data-is-voter') === '1';
            const isSenior = row.getAttribute('data-is-senior') === '1' || parseInt(age) >= 60;
            const isPWD = row.getAttribute('data-is-pwd') === '1';
            
            // Format resident ID
            const formattedId = 'RES-' + residentId.padStart(6, '0');
            
            // Populate modal with resident data
            document.getElementById('modalResidentId').textContent = formattedId;
            document.getElementById('modalResidentName').textContent = name;
            document.getElementById('modalResidentAgeGender').textContent = `${age} years old, ${gender}`;
            document.getElementById('modalResidentPhoto').src = profilePic;
            document.getElementById('modalEmail').textContent = email;
            document.getElementById('modalContact').textContent = phone;
            document.getElementById('modalAddress').textContent = address;
            
            // Set the status dropdown
            const statusSelect = document.getElementById('modalStatus');
            statusSelect.value = status;
            
            // Set category based on age and other factors
            let category = 'Regular';
            if (isSenior) {
                category = 'Senior Citizen';
            } else if (isPWD) {
                category = 'Person with Disability';
            } else {
                category = gender === 'Male' ? 'Male' : 'Female';
            }
            document.getElementById('modalCategory').textContent = category;
            
            // Set checkboxes
            document.getElementById('modalVoter').checked = isVoter;
            document.getElementById('modalSenior').checked = isSenior;
            document.getElementById('modalPWD').checked = isPWD;
            
            // Show the modal
            document.getElementById('residentModal').classList.remove('hidden');
        }
        
        // Edit resident details
        function editResident(residentId) {
            // First view the resident details
            viewResident(residentId);
            
            // Then enable editing of fields
            setTimeout(() => {
                // Make fields editable
                document.getElementById('modalResidentName').contentEditable = true;
                document.getElementById('modalResidentName').classList.add('border', 'border-blue-300', 'px-2');
                document.getElementById('modalEmail').contentEditable = true;
                document.getElementById('modalEmail').classList.add('border', 'border-blue-300', 'px-2');
                document.getElementById('modalContact').contentEditable = true;
                document.getElementById('modalContact').classList.add('border', 'border-blue-300', 'px-2');
                document.getElementById('modalAddress').contentEditable = true;
                document.getElementById('modalAddress').classList.add('border', 'border-blue-300', 'px-2');
                
                // Make checkboxes editable
                document.getElementById('modalVoter').disabled = false;
                document.getElementById('modalSenior').disabled = false;
                document.getElementById('modalPWD').disabled = false;
                
                // Create a hidden form for submission
                if (!document.getElementById('editResidentForm')) {
                    const form = document.createElement('form');
                    form.id = 'editResidentForm';
                    form.style.display = 'none';
                    document.body.appendChild(form);
                    
                    // Add hidden input for user_id
                    const userIdInput = document.createElement('input');
                    userIdInput.type = 'hidden';
                    userIdInput.name = 'user_id';
                    userIdInput.value = residentId;
                    form.appendChild(userIdInput);
                } else {
                    document.getElementById('editResidentForm').querySelector('input[name="user_id"]').value = residentId;
                }
            }, 100); // Give time for the modal to fully open
        }

        // Close modal
        function closeModal() {
            document.getElementById('residentModal').classList.add('hidden');
            // Reset editable fields
            if (document.getElementById('modalResidentName')) {
                document.getElementById('modalResidentName').contentEditable = false;
                document.getElementById('modalResidentName').classList.remove('border', 'border-blue-300', 'px-2');
            }
            if (document.getElementById('modalEmail')) {
                document.getElementById('modalEmail').contentEditable = false;
                document.getElementById('modalEmail').classList.remove('border', 'border-blue-300', 'px-2');
            }
            if (document.getElementById('modalContact')) {
                document.getElementById('modalContact').contentEditable = false;
                document.getElementById('modalContact').classList.remove('border', 'border-blue-300', 'px-2');
            }
            if (document.getElementById('modalAddress')) {
                document.getElementById('modalAddress').contentEditable = false;
                document.getElementById('modalAddress').classList.remove('border', 'border-blue-300', 'px-2');
            }
            
            // Disable checkboxes again
            if (document.getElementById('modalVoter')) document.getElementById('modalVoter').disabled = true;
            if (document.getElementById('modalSenior')) document.getElementById('modalSenior').disabled = true;
            if (document.getElementById('modalPWD')) document.getElementById('modalPWD').disabled = true;
        }

        // Update resident
        function updateResident() {
            // Get values from editable fields
            const userId = document.getElementById('editResidentForm').querySelector('input[name="user_id"]').value;
            const fullName = document.getElementById('modalResidentName').textContent;
            const email = document.getElementById('modalEmail').textContent;
            const phone = document.getElementById('modalContact').textContent;
            const address = document.getElementById('modalAddress').textContent;
            const isVoter = document.getElementById('modalVoter').checked ? 1 : 0;
            const isSenior = document.getElementById('modalSenior').checked ? 1 : 0;
            const isPWD = document.getElementById('modalPWD').checked ? 1 : 0;
            const status = document.getElementById('modalStatus').value;
            
            // Create form data for submission
            const formData = new FormData();
            formData.append('update_resident', '1');
            formData.append('csrf_token', '<?= $_SESSION["csrf_token"] ?>');
            formData.append('user_id', userId);
            formData.append('full_name', fullName);
            formData.append('email', email);
            formData.append('phone', phone);
            formData.append('address', address);
            formData.append('is_voter', isVoter);
            formData.append('is_senior', isSenior);
            formData.append('is_pwd', isPWD);
            formData.append('status', status);
            
            // Send update request
            fetch('residents.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.text())
            .then(data => {
                alert('Resident information updated successfully');
                closeModal();
                // Reload the page to show updated data
                window.location.reload();
            })
            .catch(error => {
                console.error('Error updating resident:', error);
                alert('Failed to update resident information. Please try again.');
            });
        }

        // Open new resident modal
        function openNewResidentModal() {
            document.getElementById('newResidentModal').classList.remove('hidden');
        }

        // Close new resident modal
        function closeNewResidentModal() {
            document.getElementById('newResidentModal').classList.add('hidden');
        }

        // Submit new resident
        function submitNewResident() {
            // Get form data from the new resident form
            const firstName = document.getElementById('firstName').value;
            const middleName = document.getElementById('middleName').value;
            const lastName = document.getElementById('lastName').value;
            const suffix = document.getElementById('suffix').value;
            const fullName = [firstName, middleName, lastName, suffix].filter(Boolean).join(' ');
            
            const email = document.getElementById('email').value;
            const phone = document.getElementById('contactNumber').value;
            const address = document.getElementById('address').value;
            const gender = document.getElementById('gender').value;
            const birthdate = document.getElementById('birthDate').value;
            
            // Basic validation
            if (!firstName || !lastName || !email || !phone || !address || !gender || !birthdate) {
                alert('Please fill in all required fields');
                return;
            }
            
            // Create form data for submission
            const formData = new FormData();
            formData.append('create_resident', '1');
            formData.append('csrf_token', '<?= $_SESSION["csrf_token"] ?>');
            formData.append('full_name', fullName);
            formData.append('email', email);
            formData.append('phone', phone);
            formData.append('address', address);
            formData.append('gender', gender);
            formData.append('birthdate', birthdate);
            formData.append('is_voter', document.getElementById('isVoter').checked ? 1 : 0);
            formData.append('is_senior', document.getElementById('isSenior').checked ? 1 : 0);
            formData.append('is_pwd', document.getElementById('isPWD').checked ? 1 : 0);
            
            // Submit form via AJAX
            fetch('residents.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.text())
            .then(data => {
                alert('New resident registered successfully');
                closeNewResidentModal();
                // Reload the page to show the new resident
                window.location.reload();
            })
            .catch(error => {
                console.error('Error registering resident:', error);
                alert('Failed to register resident. Please try again.');
            });
        }
        
        // Send SMS notification
        function sendSMS(contact, name) {
            document.getElementById('smsRecipient').value = name + ' (' + contact + ')';
            document.getElementById('smsModal').classList.remove('hidden');
        }

        // Close SMS modal
        function closeSMSModal() {
            document.getElementById('smsModal').classList.add('hidden');
        }

        // Actually send SMS (demo)
        function sendSMSNow() {
            const message = document.getElementById('smsMessage').value;
            alert('SMS would be sent with message:\n\n' + message);
            closeSMSModal();
        }
    </script>
</body>
</html>