<?php
// Include admin authentication check
require_once 'auth_check.php';

// Include database connection
require_once '../baby_capstone_connection.php';

// Initialize variables
$update_success = false;
$update_error = null;
$smsSent = false;
$contact = '';
$message = '';
$filterStatus = isset($_GET['status']) ? $_GET['status'] : 'all';
$searchTerm = isset($_GET['search']) ? $_GET['search'] : '';
$sortBy = isset($_GET['sort']) ? $_GET['sort'] : 'newest';

// Pagination settings
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = 10;
$offset = ($page - 1) * $perPage;

// Build the query based on filters
$whereClause = "1";
$params = [];

if ($filterStatus !== 'all') {
    $whereClause .= " AND status = ?";
    $params[] = $filterStatus;
}

if (!empty($searchTerm)) {
    $whereClause .= " AND (name LIKE ? OR type LIKE ? OR details LIKE ? OR phone LIKE ?)"; 
    $likeParam = "%{$searchTerm}%";
    $params[] = $likeParam;
    $params[] = $likeParam;
    $params[] = $likeParam;
    $params[] = $likeParam;
}

// Determine sort order
$orderBy = "created_at DESC"; // Default newest first
if ($sortBy === 'oldest') {
    $orderBy = "created_at ASC";
} elseif ($sortBy === 'priority') {
    $orderBy = "FIELD(status, 'In Progress', 'Pending', 'Rejected', 'Resolved'), created_at DESC";
}

// Count total complaints for pagination
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM complaints WHERE {$whereClause}");
$countStmt->execute($params);
$totalComplaints = $countStmt->fetchColumn();
$totalPages = ceil($totalComplaints / $perPage);

// Fetch complaints with filtering, sorting and pagination
$query = "SELECT id, type, name, phone, address, details, attachments, created_at, user_id, status, resolution_notes FROM complaints WHERE {$whereClause} ORDER BY {$orderBy} LIMIT {$offset}, {$perPage}";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$complaints = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Function to fetch user details
function getUserDetails($pdo, $user_id) {
    if (empty($user_id)) return false;
    
    $stmt = $pdo->prepare("SELECT full_name, email, phone FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

// Include notification function
require_once 'create_notification.php';

// Handle form submission for updating complaint status
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_complaint'])) {
        $complaint_id = $_POST['complaint_id'];
        
        // Get current complaint status
        $sql = "SELECT status FROM complaints WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $complaint_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $current_status = $result->fetch_assoc()['status'];

        // Get new status
        $new_status = $_POST['status'];
        
        // Create notification if status changed
        if ($new_status !== $current_status) {
            $user_id = $_POST['user_id'];
            $message = "Complaint #" . $complaint_id . " status updated from " . $current_status . " to " . $new_status;
            createNotification('complaint', $message, $user_id);
        }
    }
    
    // Handle new complaint submission
    if (isset($_POST['submit_complaint'])) {
        $user_id = $_POST['user_id'];
        $type = $_POST['type'];
        $name = $_POST['name'];
        $phone = $_POST['phone'];
        $email = $_POST['email'];
        $address = $_POST['address'];
        $subject_type = $_POST['subject_type'];
        $subject = $_POST['subject'];
        $details = $_POST['details'];
        
        // Insert complaint
        $sql = "INSERT INTO complaints (user_id, type, name, phone, email, address, subject_type, subject, details, status) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'Pending')";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("issssssss", $user_id, $type, $name, $phone, $email, $address, $subject_type, $subject, $details);
        
        if ($stmt->execute()) {
            // Create notification for new complaint
            $complaint_id = $stmt->insert_id;
            $message = "New complaint #" . $complaint_id . " submitted by " . $name;
            createNotification('complaint', $message, $user_id);
            
            // Redirect to complaints page
            header("Location: complaint.php");
            exit();
        }
    }
        $status = $_POST['status'];
        $resolution_notes = isset($_POST['resolution_notes']) ? $_POST['resolution_notes'] : null;
        
        try {
            // Update complaint status and notes
            $stmt = $pdo->prepare("UPDATE complaints SET status = ?, resolution_notes = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$status, $resolution_notes, $complaint_id]);
            
            // Get complaint details for notification
            $stmt = $pdo->prepare("SELECT name, phone, type FROM complaints WHERE id = ?");
            $stmt->execute([$complaint_id]);
            $complaint = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $_SESSION['success_message'] = "Complaint status updated successfully.";
            if (!empty($complaint['phone'])) {
                $contact = $complaint['phone'];
                $message = "Hello {$complaint['name']}, your {$complaint['type']} complaint (ID: {$complaint_id}) has been updated to {$status}. Thank you for your patience.";
                $smsSent = true;
                
                // Log the notification
                $stmt = $pdo->prepare("INSERT INTO notification_logs (user_id, complaint_id, message, sent_at) VALUES (?, ?, ?, NOW())");
                $stmt->execute([null, $complaint_id, $message]);
                $_SESSION['sms_message'] = "Notification sent to " . htmlspecialchars($contact);
            }
            
            // Redirect to avoid form resubmission
            header("Location: complaint.php?status={$filterStatus}&search={$searchTerm}&sort={$sortBy}&page={$page}");
            exit;
            
        } catch (PDOException $e) {
            $update_error = "Failed to update complaint: " . $e->getMessage();
        }
    }
    
    // Handle bulk action if implemented
    if (isset($_POST['bulk_action']) && isset($_POST['selected_complaints'])) {
        $action = $_POST['bulk_action'];
        $selected = $_POST['selected_complaints'];
        
        if (!empty($selected) && in_array($action, ['Pending', 'In Progress', 'Resolved', 'Rejected'])) {
            try {
                $placeholders = implode(',', array_fill(0, count($selected), '?'));
                $stmt = $pdo->prepare("UPDATE complaints SET status = ? WHERE id IN ({$placeholders})");
                $params = array_merge([$action], $selected);
                $stmt->execute($params);
                
                $_SESSION['success_message'] = "Complaint status updated successfully.";
                header("Location: complaint.php?status={$filterStatus}&search={$searchTerm}&sort={$sortBy}&page={$page}");
                exit;
            } catch (PDOException $e) {
                $update_error = "Failed to perform bulk action: " . $e->getMessage();
            }
        }
    }


?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Complaints Management - Barangay System</title>
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
        .status-pending {
            background-color: #fef3c7;
            color: #d97706;
        }
        .status-processing {
            background-color: #bfdbfe;
            color: #1d4ed8;
        }
        .status-resolved {
            background-color: #dcfce7;
            color: #15803d;
        }
        .status-rejected {
            background-color: #fee2e2;
            color: #b91c1c;
        }
    </style>
</head>
<body class="bg-gray-100 font-sans">
    <div class="flex h-screen overflow-hidden">
        <!-- Sidebar (same as dashboard) -->
        <?php include 'sidebar.php' ?>

        <!-- Main Content -->
        <div class="flex-1 overflow-auto">
            <!-- Top Navigation (same as dashboard) -->
            <?php include 'header.php' ?>

            <!-- Complaints Content -->
            <main class="p-4 md:p-6">
                <!-- Filters and Actions -->
                <div class="bg-white rounded-lg shadow p-4 mb-6">
                    <div class="flex flex-col md:flex-row md:items-center md:justify-between">
                        <div class="mb-4 md:mb-0">
                            <h2 class="text-lg font-semibold text-gray-800">All Complaints</h2>
                            <p class="text-sm text-gray-500">Manage and track all barangay complaints</p>
                        </div>
                        <div class="flex flex-col sm:flex-row space-y-2 sm:space-y-0 sm:space-x-2">
                            <div class="relative">
                                <select class="block w-full pl-3 pr-10 py-2 text-base border border-gray-300 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm rounded-md">
                                    <option>All Status</option>
                                    <option>Pending</option>
                                    <option>Processing</option>
                                    <option>Resolved</option>
                                    <option>Rejected</option>
                                </select>
                            </div>
                            <div class="relative">
                                <select class="block w-full pl-3 pr-10 py-2 text-base border border-gray-300 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm rounded-md">
                                    <option>Sort by: Newest</option>
                                    <option>Sort by: Oldest</option>
                                    <option>Sort by: Priority</option>
                                </select>
                            </div>
                            <button class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                <i class="fas fa-filter mr-2"></i> Apply Filters
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Complaints Table -->
                <div class="bg-white shadow rounded-lg overflow-hidden">
                    <div class="px-4 py-3 border-b border-gray-200 flex justify-between items-center">
                        <div>
                            <h3 class="text-sm font-medium text-gray-700">Recent Complaints</h3>
                        </div>
                        <div>
                            <button class="inline-flex items-center px-3 py-1.5 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                <i class="fas fa-download mr-2"></i> Export
                            </button>
                            <button class="ml-2 inline-flex items-center px-3 py-1.5 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                <i class="fas fa-plus mr-2"></i> New Complaint
                            </button>
                        </div>
                    </div>
                    <div class="overflow-x-auto">
                    <table class="min-w-full bg-white shadow-md rounded-lg overflow-hidden">
    <thead>
        <tr class="bg-gradient-to-r from-gray-50 to-gray-100 text-gray-700 border-b border-gray-200">
            <th class="px-5 py-4 text-left text-xs font-semibold uppercase tracking-wider">ID</th>
            <th class="px-5 py-4 text-left text-xs font-semibold uppercase tracking-wider">Complainant</th>
            <th class="px-5 py-4 text-left text-xs font-semibold uppercase tracking-wider">Contact</th>
            <th class="px-5 py-4 text-left text-xs font-semibold uppercase tracking-wider">Type</th>
            <th class="px-5 py-4 text-left text-xs font-semibold uppercase tracking-wider">Date</th>
            <th class="px-5 py-4 text-left text-xs font-semibold uppercase tracking-wider">Status</th>
            <th class="px-5 py-4 text-right text-xs font-semibold uppercase tracking-wider">Actions</th>
        </tr>
    </thead>

    <!-- Notification Area -->
    <tr>
        <td colspan="7" class="px-0 py-2">
            <?php if (isset($_SESSION['success_message'])): ?>
                <div id="successAlert" class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-3 rounded-md shadow-sm flex items-center justify-between" role="alert">
                    <div class="flex items-center">
                        <i class="fas fa-check-circle mr-2 text-green-500 text-lg"></i>
                        <div>
                            <strong class="font-bold">Success!</strong>
                            <span class="block sm:inline"><?php echo htmlspecialchars($_SESSION['success_message']); ?></span>
                        </div>
                    </div>
                    <button onclick="this.parentElement.remove()" class="text-green-700 hover:text-green-900">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <?php unset($_SESSION['success_message']); ?>
                <?php if (isset($_SESSION['sms_message'])): ?>
                    <div id="smsAlert" class="bg-blue-100 border-l-4 border-blue-500 text-blue-700 p-4 mb-3 rounded-md shadow-sm flex items-center justify-between" role="alert">
                        <div>
                            <i class="fas fa-envelope mr-2 text-blue-500 text-lg"></i>
                            <strong class="font-bold">SMS Sent!</strong>
                            <span class="block sm:inline"><?php echo htmlspecialchars($_SESSION['sms_message']); ?></span>
                        </div>
                        <button onclick="this.parentElement.remove()" class="text-blue-700 hover:text-blue-900">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                    <?php unset($_SESSION['sms_message']); ?>
       <p class="text-sm mt-1 text-blue-600"><?php echo htmlspecialchars($message); ?></p>
                        </div>
                        <button onclick="this.parentElement.remove()" class="text-blue-700 hover:text-blue-900">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
            
            <?php if (isset($update_error)): ?>
                <div id="errorAlert" class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-3 rounded-md shadow-sm flex items-center justify-between" role="alert">
                    <div class="flex items-center">
                        <i class="fas fa-exclamation-circle mr-2 text-red-500 text-lg"></i>
                        <div>
                            <strong class="font-bold">Error!</strong> 
                            <span class="block sm:inline"> <?php echo htmlspecialchars($update_error); ?></span>
                        </div>
                    </div>
                    <button onclick="this.parentElement.remove()" class="text-red-700 hover:text-red-900">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            <?php endif; ?>
        </td>
    </tr>

    <tbody class="divide-y divide-gray-200">
        <?php if (empty($complaints)): ?>
            <tr>
                <td colspan="7" class="px-5 py-12 text-center text-gray-500">
                    <i class="fas fa-folder-open text-4xl mb-3 text-gray-400"></i>
                    <p class="text-lg">No complaints found</p>
                    <p class="text-sm">When complaints are added, they will appear here.</p>
                </td>
            </tr>
        <?php else: ?>
            <?php foreach ($complaints as $complaint): ?>
                <?php $user = getUserDetails($pdo, $complaint['user_id']); ?>
                <tr class="hover:bg-gray-50 transition-colors duration-150 ease-in-out">
                    <td class="px-5 py-4 whitespace-nowrap">
                        <span class="text-sm font-medium text-gray-900">#<?php echo htmlspecialchars($complaint['id']); ?></span>
                    </td>
                    
                    <td class="px-5 py-4">
                        <div class="flex items-center">
                            <div class="flex-shrink-0 h-10 w-10 bg-gray-100 rounded-full flex items-center justify-center text-gray-500">
                                <i class="fas fa-user"></i>
                            </div> 
                            <div class="ml-4">
                                <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($complaint['name']); ?></div>
                                <?php if ($user): ?>
                                    <div class="text-xs text-gray-500 flex items-center">
                                        <i class="fas fa-id-badge mr-1"></i>
                                        <?php echo htmlspecialchars($user['full_name']); ?>
                                    </div>
                                <?php else: ?>
                                    <div class="text-xs text-gray-400">No user account</div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </td>
                    
                    <td class="px-5 py-4 whitespace-nowrap">
                        <div class="text-sm text-gray-900 font-medium flex items-center">
                            <i class="fas fa-phone-alt mr-2 text-gray-500"></i>
                            <?php 
                               // prefer the user's phone from users.phone, fallback to complaint record
                               echo htmlspecialchars($user['phone'] ?? $complaint['phone']); 
                            ?>
                        </div>
                    </td>
                    
                    <td class="px-5 py-4 whitespace-nowrap">
                        <div class="text-sm text-gray-900">
                            <?php 
                                $typeIcons = [
                                    'Noise' => 'fa-volume-up',
                                    'Sanitation' => 'fa-trash',
                                    'Security' => 'fa-shield-alt',
                                    'Infrastructure' => 'fa-road',
                                    'Neighbor Dispute' => 'fa-users',
                                    'Other' => 'fa-exclamation-circle'
                                ];
                                $icon = isset($typeIcons[$complaint['type']]) ? $typeIcons[$complaint['type']] : 'fa-file-alt';
                            ?>
                            <span class="inline-flex items-center">
                                <i class="fas <?php echo $icon; ?> mr-2 text-gray-500"></i>
                                <?php echo htmlspecialchars($complaint['type']); ?>
                            </span>
                        </div>
                    </td>
                    
                    <td class="px-5 py-4 whitespace-nowrap">
                        <div class="text-sm text-gray-900 flex items-center">
                            <i class="far fa-calendar-alt mr-2 text-gray-500"></i>
                            <?php echo date('M d, Y', strtotime($complaint['created_at'])); ?>
                        </div>
                    </td>
                    
                    <td class="px-5 py-4 whitespace-nowrap">
                        <div class="flex items-center">
                            <?php 
                                $statusColors = [
                                    'Pending' => [
                                        'bg' => 'bg-yellow-100',
                                        'text' => 'text-yellow-800',
                                        'icon' => 'fa-clock'
                                    ],
                                    'In Progress' => [
                                        'bg' => 'bg-blue-100',
                                        'text' => 'text-blue-800',
                                        'icon' => 'fa-spinner'
                                    ],
                                    'Resolved' => [
                                        'bg' => 'bg-green-100',
                                        'text' => 'text-green-800',
                                        'icon' => 'fa-check-circle'
                                    ],
                                    'Rejected' => [
                                        'bg' => 'bg-red-100',
                                        'text' => 'text-red-800',
                                        'icon' => 'fa-times-circle'
                                    ]
                                ];
                                
                                $statusInfo = $statusColors[$complaint['status']] ?? [
                                    'bg' => 'bg-gray-100',
                                    'text' => 'text-gray-800',
                                    'icon' => 'fa-question-circle'
                                ];
                            ?>
                            
                            <span class="flex items-center px-3 py-1 rounded-full text-sm font-medium <?php echo $statusInfo['bg'] . ' ' . $statusInfo['text']; ?>">
                                <i class="fas <?php echo $statusInfo['icon']; ?> mr-1"></i>
                                <?php echo htmlspecialchars($complaint['status']); ?>
                            </span>

                        </div>
                    </td>
                    
                    <td class="px-5 py-4 whitespace-nowrap text-right">
                        <div class="flex justify-end space-x-2">
                            <button onclick="viewComplaint('<?php echo htmlspecialchars($complaint['id']); ?>')" 
                                    class="text-blue-600 hover:text-blue-800 bg-blue-50 hover:bg-blue-100 rounded-full p-2 transition-colors"
                                    title="View details">
                                <i class="fas fa-eye"></i>
                            </button>
                            
                            <button onclick="openEditModal(<?php echo htmlspecialchars(json_encode($complaint)); ?>)"
                                    class="text-yellow-600 hover:text-yellow-800 bg-yellow-50 hover:bg-yellow-100 rounded-full p-2 transition-colors"
                                    title="Edit complaint">
                                <i class="fas fa-edit"></i>
                            </button>
                            
                            <button onclick="sendSMS('<?php echo htmlspecialchars($user['phone'] ?? $complaint['phone']); ?>', '<?php echo htmlspecialchars($complaint['name']); ?>', '<?php echo htmlspecialchars($complaint['id']); ?>')" 
                                    class="text-green-600 hover:text-green-800 bg-green-50 hover:bg-green-100 rounded-full p-2 transition-colors"
                                    title="Send SMS notification">
                                <i class="fas fa-sms"></i>
                            </button>

                            <?php if (!empty($complaint['attachments'])): ?>
                                <a href="<?php echo htmlspecialchars($complaint['attachments']); ?>" target="_blank" 
                                   class="text-purple-600 hover:text-purple-800 bg-purple-50 hover:bg-purple-100 rounded-full p-2 transition-colors"
                                   title="View attachment">
                                    <i class="fas fa-paperclip"></i>
                                </a>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
    </tbody>
</table>

<script>
    // Enhanced version of alert timeout
    document.addEventListener('DOMContentLoaded', function() {
        const alerts = ['successAlert', 'smsAlert', 'errorAlert'];
        alerts.forEach(id => {
            const el = document.getElementById(id);
            if (el) {
                // Fade out effect
                setTimeout(() => {
                    el.style.transition = 'opacity 1s ease-out';
                    el.style.opacity = '0';
                    setTimeout(() => {
                        el.remove();
                    }, 1000);
                }, 4000);
            }
        });
    });
    
    // Function to highlight row on hover
    document.querySelectorAll('tbody tr').forEach(row => {
        row.addEventListener('mouseenter', () => {
            row.classList.add('bg-gray-50', 'shadow-sm');
        });
        row.addEventListener('mouseleave', () => {
            row.classList.remove('bg-gray-50', 'shadow-sm');
        });
    });
</script>
                    </div>
                    <div class="bg-white px-4 py-3 flex items-center justify-between border-t border-gray-200 sm:px-6">
                        <div class="flex-1 flex justify-between sm:hidden">
                            <a href="#" class="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">Previous</a>
                            <a href="#" class="ml-3 relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">Next</a>
                        </div>
                        <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
                            <div>
                                <p class="text-sm text-gray-700">
                                    Showing results
                                </p>
                            </div>
                            <div>
                                <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px" aria-label="Pagination">
                                    <a href="#" class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                                        <span class="sr-only">Previous</span>
                                        <i class="fas fa-chevron-left"></i>
                                    </a>
                                    <a href="#" aria-current="page" class="z-10 bg-blue-50 border-blue-500 text-blue-600 relative inline-flex items-center px-4 py-2 border text-sm font-medium">1</a>
                                    <a href="#" class="bg-white border-gray-300 text-gray-500 hover:bg-gray-50 relative inline-flex items-center px-4 py-2 border text-sm font-medium">2</a>
                                    <a href="#" class="bg-white border-gray-300 text-gray-500 hover:bg-gray-50 relative inline-flex items-center px-4 py-2 border text-sm font-medium">3</a>
                                    <a href="#" class="relative inline-flex items-center px-2 py-2 rounded-r-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                                        <span class="sr-only">Next</span>
                                        <i class="fas fa-chevron-right"></i>
                                    </a>
                                </nav>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Complaint Details Modal -->
                <div id="complaintModal" class="hidden fixed inset-0 overflow-y-auto z-50">
                    <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                        <div class="fixed inset-0 transition-opacity" aria-hidden="true">
                            <div class="absolute inset-0 bg-gray-500 opacity-75"></div>
                        </div>
                        <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
                        <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-2xl sm:w-full">
                            <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                                <div class="sm:flex sm:items-start">
                                    <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left w-full">
                                        <h3 class="text-lg leading-6 font-medium text-gray-900" id="modalTitle">Complaint Details</h3>
                                        <div class="mt-2">
                                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                                <div>
                                                    <p class="text-sm font-medium text-gray-500">Complaint ID</p>
                                                    <p id="modalComplaintId" class="mt-1 text-sm text-gray-900"></p>
                                                </div>
                                                <div>
                                                    <p class="text-sm font-medium text-gray-500">Date Filed</p>
                                                    <p id="modalDate" class="mt-1 text-sm text-gray-900"></p>
                                                </div>
                                                <div>
                                                    <p class="text-sm font-medium text-gray-500">Complainant Name</p>
                                                    <p id="modalName" class="mt-1 text-sm text-gray-900"></p>
                                                </div>
                                                <div>
                                                    <p class="text-sm font-medium text-gray-500">Contact Number</p>
                                                    <p id="modalContact" class="mt-1 text-sm text-gray-900"></p>
                                                </div>
                                                <div>
                                                    <p class="text-sm font-medium text-gray-500">Complaint Type</p>
                                                    <p id="modalType" class="mt-1 text-sm text-gray-900"></p>
                                                </div>
                                                <div>
                                                    <p class="text-sm font-medium text-gray-500">Status</p>
                                                    <p id="modalStatus" class="mt-1 text-sm text-gray-900"></p>
                                                </div>
                                                <div class="md:col-span-2">
                                                    <p class="text-sm font-medium text-gray-500">Address</p>
                                                    <p id="modalAddress" class="mt-1 text-sm text-gray-900"></p>
                                                </div>
                                                <div class="md:col-span-2">
                                                    <p class="text-sm font-medium text-gray-500">Complaint Details</p>
                                                    <p id="modalDetails" class="mt-1 text-sm text-gray-900"></p>
                                                </div>
                                                <div class="md:col-span-2">
                                                    <p class="text-sm font-medium text-gray-500">Resolution Notes</p>
                                                    <textarea id="modalResolutionNotes" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm"></textarea>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                                <button type="button" onclick="updateComplaint()" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-blue-600 text-base font-medium text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:ml-3 sm:w-auto sm:text-sm">
                                    Update Status
                                </button>
                                <button type="button" onclick="closeModal()" class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
                                    Close
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <div id="editModal" class="hidden fixed inset-0 overflow-y-auto z-50">
                    <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                        <div class="fixed inset-0 transition-opacity" aria-hidden="true">
                            <div class="absolute inset-0 bg-gray-500 opacity-75"></div>
                        </div>
                        <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
                        <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-2xl sm:w-full">
                            <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                                <div class="sm:flex sm:items-start">
                                    <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left w-full">
                                        <div class="flex justify-between items-center mb-4">
                                            <h3 class="text-xl leading-6 font-semibold text-gray-900" id="modalTitle">Edit Complaint Details</h3>
                                            <button type="button" onclick="closeEditModal()" class="text-gray-400 hover:text-gray-500 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                                <span class="sr-only">Close</span>
                                                <i class="fas fa-times text-xl"></i>
                                            </button>
                                        </div>
                                        <div class="mt-4">
                                            <form id="editForm" method="POST" class="space-y-6">
                                                <input type="hidden" name="complaint_id" id="editComplaintId">
                                                
                                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                                    <div>
                                                        <label for="editId" class="block text-sm font-medium text-gray-700 mb-1">Complaint ID</label>
                                                        <div class="mt-1">
                                                            <input type="text" id="editId" class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm bg-gray-50 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm" readonly>
                                                        </div>
                                                    </div>
                                                    <div>
                                                        <label for="editCreatedAt" class="block text-sm font-medium text-gray-700 mb-1">Date Filed</label>
                                                        <div class="mt-1">
                                                            <input type="text" id="editCreatedAt" class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm bg-gray-50 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm" readonly>
                                                        </div>
                                                    </div>
                                                </div>

                                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                                    <div>
                                                        <label for="editName" class="block text-sm font-medium text-gray-700 mb-1">Complainant Name</label>
                                                        <div class="mt-1">
                                                            <input type="text" name="name" id="editName" class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm bg-gray-50 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm" readonly>
                                                        </div>
                                                    </div>
                                                    <div>
                                                        <label for="editPhone" class="block text-sm font-medium text-gray-700 mb-1">Phone</label>
                                                        <div class="mt-1">
                                                            <input type="text" name="phone" id="editPhone" class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm bg-gray-50 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm" readonly>
                                                        </div>
                                                    </div>
                                                </div>

                                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                                    <div>
                                                        <label for="editType" class="block text-sm font-medium text-gray-700 mb-1">Complaint Type</label>
                                                        <div class="mt-1">
                                                            <input type="text" name="type" id="editType" class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm bg-gray-50 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm" readonly>
                                                        </div>
                                                    </div>
                                                    <div>
                                                        <label for="editStatus" class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                                                        <div class="mt-1">
                                                            <select name="status" id="editStatus" class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                                                                <option value="Pending">Pending</option>
                                                                <option value="In Progress">In Progress</option>
                                                                <option value="Resolved">Resolved</option>
                                                                <option value="Rejected">Rejected</option>
                                                            </select>
                                                        </div>
                                                    </div>
                                                </div>

                                                <div class="grid grid-cols-1 gap-4">
                                                    <div>
                                                        <label for="editAddress" class="block text-sm font-medium text-gray-700 mb-1">Address</label>
                                                        <div class="mt-1">
                                                            <input type="text" id="editAddress" class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm bg-gray-50 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm" readonly>
                                                        </div>
                                                    </div>

                                                    <div>
                                                        <label for="editDetails" class="block text-sm font-medium text-gray-700 mb-1">Details</label>
                                                        <div class="mt-1">
                                                            <textarea id="editDetails" class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm bg-gray-50 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm" rows="3" readonly></textarea>
                                                        </div>
                                                    </div>

                                                    <div>
                                                        <label for="editResolutionNotes" class="block text-sm font-medium text-gray-700 mb-1">Resolution Notes</label>
                                                        <div class="mt-1">
                                                            <textarea name="resolution_notes" id="editResolutionNotes" class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm" rows="4"></textarea>
                                                        </div>
                                                    </div>
                                                </div>

                                                <div class="flex justify-end space-x-3">
                                                    <button type="button" onclick="closeEditModal()" class="px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                                        Cancel
                                                    </button>
                                                    <button type="submit" name="update_complaint" class="px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                                        Save Changes
                                                    </button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
<script>
function openEditModal(data) {
    // Set all fields
    document.getElementById('editComplaintId').value = data.id;
    document.getElementById('editId').value = data.id;
    document.getElementById('editCreatedAt').value = data.created_at ? new Date(data.created_at).toLocaleString() : '';
    document.getElementById('editName').value = data.name;
    document.getElementById('editPhone').value = data.phone;
    document.getElementById('editType').value = data.type;
    document.getElementById('editAddress').value = data.address || '';
    document.getElementById('editDetails').value = data.details || '';
    document.getElementById('editStatus').value = data.status;
    document.getElementById('editResolutionNotes').value = data.resolution_notes || '';

    document.getElementById('editModal').classList.remove('hidden');
}

function closeEditModal() {
    document.getElementById('editModal').classList.add('hidden');
}
</script>
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
                                                <label for="smsComplaintId" class="block text-sm font-medium text-gray-700">Complaint ID</label>
                                                <input type="text" id="smsComplaintId" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm" readonly>
                                            </div>
                                            <div class="mb-4">
                                                <label for="smsMessage" class="block text-sm font-medium text-gray-700">Message</label>
                                                <textarea id="smsMessage" rows="4" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">Your complaint #COMP-2023-001 has been received and is currently being processed. We will update you on the status. Thank you.</textarea>
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

        // Close modal
        function closeModal() {
            document.getElementById('complaintModal').classList.add('hidden');
        }

        // Update complaint status
        function updateComplaint() {
            const notes = document.getElementById('modalResolutionNotes').value;
            alert('Complaint status updated with notes:\n\n' + notes);
            closeModal();
        }

        // Send SMS notification
        function sendSMS(contact, name, complaintId) {
            document.getElementById('smsRecipient').value = name + ' (' + contact + ')';
            document.getElementById('smsComplaintId').value = complaintId;
            document.getElementById('smsMessage').value = `Your complaint ${complaintId} has been received and is currently being processed. We will update you on the status. Thank you.`;
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
            </main>
        </div>
    </div>
</body>
</html>