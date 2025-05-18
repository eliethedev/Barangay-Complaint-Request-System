<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// DB connection
require_once 'baby_capstone_connection.php'; // make sure this contains $pdo (PDO connection)

// Get user ID from session
$user_id = $_SESSION['user_id'];

// Set default filter values
$filter_type = $_GET['filter_type'] ?? 'all';
$search_query = $_GET['search'] ?? '';
$status_filter = $_GET['status'] ?? 'all';
$sort_by = $_GET['sort_by'] ?? 'date_desc';
$page = max(1, intval($_GET['page'] ?? 1));
$items_per_page = 10;

// Build the base queries with user_id filter
$complaints_query = "SELECT id, type, name, phone, address, details, status, created_at FROM complaints WHERE user_id = :user_id";
$requests_query = "SELECT id, type, name, phone, details, payment_status, payment_amount, payment_reference, status, created_at FROM requests WHERE user_id = :user_id";

// Apply search filter if provided
if (!empty($search_query)) {
    $complaints_query .= " AND (type LIKE :search OR details LIKE :search)";
    $requests_query .= " AND (type LIKE :search OR details LIKE :search)";
}

// Apply status filter if not 'all'
if ($status_filter !== 'all') {
    $complaints_query .= " AND status = :status";
    $requests_query .= " AND status = :status";
}

// Apply sorting
switch ($sort_by) {
    case 'date_asc':
        $order_by = "ORDER BY created_at ASC";
        break;
    case 'type_asc':
        $order_by = "ORDER BY type ASC";
        break;
    case 'type_desc':
        $order_by = "ORDER BY type DESC";
        break;
    case 'date_desc':
    default:
        $order_by = "ORDER BY created_at DESC";
        break;
}

$complaints_query .= " $order_by";
$requests_query .= " $order_by";

// Calculate pagination limits
$offset = ($page - 1) * $items_per_page;

// Prepare and execute queries based on filter type
$all_submissions = [];

if ($filter_type === 'all' || $filter_type === 'complaints') {
    $stmt = $pdo->prepare($complaints_query . " LIMIT :offset, :limit");
    $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
    $stmt->bindParam(':limit', $items_per_page, PDO::PARAM_INT);
    
    if (!empty($search_query)) {
        $search_param = "%$search_query%";
        $stmt->bindParam(':search', $search_param, PDO::PARAM_STR);
    }
    
    if ($status_filter !== 'all') {
        $stmt->bindParam(':status', $status_filter, PDO::PARAM_STR);
    }
    
    $stmt->execute();
    $complaints = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Add submission type for display purposes
    foreach ($complaints as &$complaint) {
        $complaint['submission_type'] = 'complaint';
        $all_submissions[] = $complaint;
    }
}

if ($filter_type === 'all' || $filter_type === 'requests') {
    $stmt2 = $pdo->prepare($requests_query . " LIMIT :offset, :limit");
    $stmt2->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $stmt2->bindParam(':offset', $offset, PDO::PARAM_INT);
    $stmt2->bindParam(':limit', $items_per_page, PDO::PARAM_INT);
    
    if (!empty($search_query)) {
        $search_param = "%$search_query%";
        $stmt2->bindParam(':search', $search_param, PDO::PARAM_STR);
    }
    
    if ($status_filter !== 'all') {
        $stmt2->bindParam(':status', $status_filter, PDO::PARAM_STR);
    }
    
    $stmt2->execute();
    $requests = $stmt2->fetchAll(PDO::FETCH_ASSOC);
    
    // Add submission type for display purposes
    foreach ($requests as &$request) {
        $request['submission_type'] = 'request';
        $all_submissions[] = $request;
    }
}

// Sort all submissions by date if showing both types
if ($filter_type === 'all') {
    usort($all_submissions, function($a, $b) use ($sort_by) {
        if ($sort_by === 'date_asc') {
            return strtotime($a['created_at']) - strtotime($b['created_at']);
        } else if ($sort_by === 'type_asc') {
            return strcmp($a['type'], $b['type']);
        } else if ($sort_by === 'type_desc') {
            return strcmp($b['type'], $a['type']);
        } else { // date_desc default
            return strtotime($b['created_at']) - strtotime($a['created_at']);
        }
    });
}

// Count total items for pagination
$count_query_complaints = str_replace("SELECT id, type, name, phone, address, details, status, created_at", "SELECT COUNT(*)", $complaints_query);
$count_query_requests = str_replace("SELECT id, type, name, phone, details, payment_status, payment_amount, payment_reference, status, created_at", "SELECT COUNT(*)", $requests_query);

$total_items = 0;

if ($filter_type === 'all' || $filter_type === 'complaints') {
    $count_stmt = $pdo->prepare($count_query_complaints);
    $count_stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    
    if (!empty($search_query)) {
        $search_param = "%$search_query%";
        $count_stmt->bindParam(':search', $search_param, PDO::PARAM_STR);
    }
    
    if ($status_filter !== 'all') {
        $count_stmt->bindParam(':status', $status_filter, PDO::PARAM_STR);
    }
    
    $count_stmt->execute();
    $total_items += $count_stmt->fetchColumn();
}

if ($filter_type === 'all' || $filter_type === 'requests') {
    $count_stmt = $pdo->prepare($count_query_requests);
    $count_stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    
    if (!empty($search_query)) {
        $search_param = "%$search_query%";
        $count_stmt->bindParam(':search', $search_param, PDO::PARAM_STR);
    }
    
    if ($status_filter !== 'all') {
        $count_stmt->bindParam(':status', $status_filter, PDO::PARAM_STR);
    }
    
    $count_stmt->execute();
    $total_items += $count_stmt->fetchColumn();
}

$total_pages = ceil($total_items / $items_per_page);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Submissions - Barangay System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .gradient-bg {
            background: linear-gradient(135deg, #1e3a8a 0%, #1e40af 100%);
        }
        
        .sidebar {
            transition: all 0.3s ease;
        }
        
        .sidebar-collapsed {
            width: 64px;
        }
        
        .sidebar-collapsed .sidebar-link span {
            display: none;
        }
        
        .sidebar-collapsed .sidebar-link i {
            margin-left: 0;
        }
        
        .card {
            transition: all 0.3s ease;
        }
        
        .card:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
        }
        
        .status-badge {
            display: inline-flex;
            align-items: center;
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        
        .status-pending {
            background-color: #fef3c7;
            color: #92400e;
        }
        
        .status-approved {
            background-color: #d1fae5;
            color: #065f46;
        }
        
        .status-rejected {
            background-color: #fee2e2;
            color: #b91c1c;
        }
        
        .status-processing {
            background-color: #e0f2fe;
            color: #0369a1;
        }
        
        .status-completed {
            background-color: #e0e7ff;
            color: #4338ca;
        }
        
        .filter-active {
            background-color: #1e40af;
            color: white;
        }
        
        .payment-badge {
            display: inline-flex;
            align-items: center;
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        
        .payment-pending {
            background-color: #fef3c7;
            color: #92400e;
        }
        
        .payment-paid {
            background-color: #d1fae5;
            color: #065f46;
        }
        
        .payment-failed {
            background-color: #fee2e2;
            color: #b91c1c;
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

        <!-- Dashboard Content -->
        <main class="p-4 md:p-6">
            <!-- Page Header with Filters -->
            <div class="mb-6">
                <div class="flex flex-col md:flex-row md:items-center md:justify-between">
                    <h1 class="text-2xl font-bold text-gray-800 mb-4 md:mb-0">My Submissions</h1>
                    
                    <!-- Search Bar -->
                    <div class="w-full md:w-auto">
                        <form action="" method="GET" class="flex">
                            <input type="hidden" name="filter_type" value="<?= htmlspecialchars($filter_type) ?>">
                            <input type="hidden" name="status" value="<?= htmlspecialchars($status_filter) ?>">
                            <input type="hidden" name="sort_by" value="<?= htmlspecialchars($sort_by) ?>">
                            <div class="relative flex-grow">
                                <input type="text" name="search" value="<?= htmlspecialchars($search_query) ?>" placeholder="Search submissions..." class="w-full px-4 py-2 border border-gray-300 rounded-l-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            </div>
                            <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-r-lg hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
                                <i class="fas fa-search"></i>
                            </button>
                        </form>
                    </div>
                </div>
            </div>
            
            <!-- Filters -->
            <div class="bg-white rounded-lg shadow-sm p-4 mb-6">
                <div class="flex flex-col md:flex-row md:items-center md:justify-between space-y-4 md:space-y-0">
                    <!-- Submission Type Filter -->
                    <div class="flex space-x-2">
                        <span class="text-sm font-medium text-gray-700 mr-2">Type:</span>
                        <a href="?filter_type=all&status=<?= urlencode($status_filter) ?>&sort_by=<?= urlencode($sort_by) ?>&search=<?= urlencode($search_query) ?>" class="px-3 py-1 text-sm rounded-full <?= $filter_type === 'all' ? 'filter-active' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' ?>">All</a>
                        <a href="?filter_type=complaints&status=<?= urlencode($status_filter) ?>&sort_by=<?= urlencode($sort_by) ?>&search=<?= urlencode($search_query) ?>" class="px-3 py-1 text-sm rounded-full <?= $filter_type === 'complaints' ? 'filter-active' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' ?>">Complaints</a>
                        <a href="?filter_type=requests&status=<?= urlencode($status_filter) ?>&sort_by=<?= urlencode($sort_by) ?>&search=<?= urlencode($search_query) ?>" class="px-3 py-1 text-sm rounded-full <?= $filter_type === 'requests' ? 'filter-active' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' ?>">Requests</a>
                    </div>
                    
                    <!-- Status Filter -->
                    <div class="flex space-x-2">
                        <span class="text-sm font-medium text-gray-700 mr-2">Status:</span>
                        <a href="?filter_type=<?= urlencode($filter_type) ?>&status=all&sort_by=<?= urlencode($sort_by) ?>&search=<?= urlencode($search_query) ?>" class="px-3 py-1 text-sm rounded-full <?= $status_filter === 'all' ? 'filter-active' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' ?>">All</a>
                        <a href="?filter_type=<?= urlencode($filter_type) ?>&status=pending&sort_by=<?= urlencode($sort_by) ?>&search=<?= urlencode($search_query) ?>" class="px-3 py-1 text-sm rounded-full <?= $status_filter === 'pending' ? 'filter-active' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' ?>">Pending</a>
                        <a href="?filter_type=<?= urlencode($filter_type) ?>&status=approved&sort_by=<?= urlencode($sort_by) ?>&search=<?= urlencode($search_query) ?>" class="px-3 py-1 text-sm rounded-full <?= $status_filter === 'approved' ? 'filter-active' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' ?>">Approved</a>
                        <a href="?filter_type=<?= urlencode($filter_type) ?>&status=completed&sort_by=<?= urlencode($sort_by) ?>&search=<?= urlencode($search_query) ?>" class="px-3 py-1 text-sm rounded-full <?= $status_filter === 'completed' ? 'filter-active' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' ?>">Completed</a>
                    </div>
                    
                    <!-- Sort Options -->
                    <div class="flex items-center">
                        <span class="text-sm font-medium text-gray-700 mr-2">Sort by:</span>
                        <select onchange="window.location=this.value" class="text-sm border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            <option value="?filter_type=<?= urlencode($filter_type) ?>&status=<?= urlencode($status_filter) ?>&sort_by=date_desc&search=<?= urlencode($search_query) ?>" <?= $sort_by === 'date_desc' ? 'selected' : '' ?>>Newest First</option>
                            <option value="?filter_type=<?= urlencode($filter_type) ?>&status=<?= urlencode($status_filter) ?>&sort_by=date_asc&search=<?= urlencode($search_query) ?>" <?= $sort_by === 'date_asc' ? 'selected' : '' ?>>Oldest First</option>
                            <option value="?filter_type=<?= urlencode($filter_type) ?>&status=<?= urlencode($status_filter) ?>&sort_by=type_asc&search=<?= urlencode($search_query) ?>" <?= $sort_by === 'type_asc' ? 'selected' : '' ?>>Type (A-Z)</option>
                            <option value="?filter_type=<?= urlencode($filter_type) ?>&status=<?= urlencode($status_filter) ?>&sort_by=type_desc&search=<?= urlencode($search_query) ?>" <?= $sort_by === 'type_desc' ? 'selected' : '' ?>>Type (Z-A)</option>
                        </select>
                    </div>
                </div>
            </div>
            
            <!-- Submissions Grid -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <?php if (count($all_submissions) > 0): ?>
                    <?php foreach ($all_submissions as $submission): ?>
                        <div class="card bg-white rounded-lg shadow-sm overflow-hidden hover:shadow-md">
                            <!-- Card Header with Type and Date -->
                            <div class="px-4 py-3 border-b border-gray-200 flex justify-between items-center">
                                <div class="flex items-center">
                                    <?php if ($submission['submission_type'] === 'complaint'): ?>
                                        <i class="fas fa-exclamation-circle text-red-500 mr-2"></i>
                                        <span class="font-medium text-gray-800">Complaint</span>
                                    <?php else: ?>
                                        <i class="fas fa-file-alt text-blue-500 mr-2"></i>
                                        <span class="font-medium text-gray-800">Request</span>
                                    <?php endif; ?>
                                </div>
                                <span class="text-xs text-gray-500"><?= date('M d, Y', strtotime($submission['created_at'])) ?></span>
                            </div>
                            
                            <!-- Card Body -->
                            <div class="p-4">
                                <h3 class="font-semibold text-gray-800 mb-2"><?= htmlspecialchars($submission['type']) ?></h3>
                                <p class="text-gray-600 text-sm mb-4 line-clamp-2"><?= htmlspecialchars($submission['details']) ?></p>
                                
                                <!-- Status and Actions -->
                                <div class="flex flex-wrap justify-between items-center">
                                    <!-- Status Badge -->
                                    <div>
                                        <?php 
                                        $status = $submission['status'] ?? 'pending';
                                        $statusClass = 'status-pending';
                                        $statusIcon = 'fa-clock';
                                        
                                        switch($status) {
                                            case 'approved':
                                                $statusClass = 'status-approved';
                                                $statusIcon = 'fa-check-circle';
                                                break;
                                            case 'rejected':
                                                $statusClass = 'status-rejected';
                                                $statusIcon = 'fa-times-circle';
                                                break;
                                            case 'processing':
                                                $statusClass = 'status-processing';
                                                $statusIcon = 'fa-spinner';
                                                break;
                                            case 'completed':
                                                $statusClass = 'status-completed';
                                                $statusIcon = 'fa-check-double';
                                                break;
                                        }
                                        ?>
                                        <span class="status-badge <?= $statusClass ?>">
                                            <i class="fas <?= $statusIcon ?> mr-1"></i>
                                            <?= ucfirst($status) ?>
                                        </span>
                                    </div>
                                    
                                    <!-- Payment Status for Requests -->
                                    <?php if ($submission['submission_type'] === 'request' && isset($submission['payment_status'])): ?>
                                        <div class="mt-2 md:mt-0">
                                            <?php 
                                            $paymentStatus = $submission['payment_status'] ?? 'pending';
                                            $paymentClass = 'payment-pending';
                                            $paymentIcon = 'fa-clock';
                                            
                                            switch($paymentStatus) {
                                                case 'paid':
                                                    $paymentClass = 'payment-paid';
                                                    $paymentIcon = 'fa-check-circle';
                                                    break;
                                                case 'failed':
                                                    $paymentClass = 'payment-failed';
                                                    $paymentIcon = 'fa-times-circle';
                                                    break;
                                            }
                                            ?>
                                            <span class="payment-badge <?= $paymentClass ?>">
                                                <i class="fas <?= $paymentIcon ?> mr-1"></i>
                                                Payment: <?= ucfirst($paymentStatus) ?>
                                            </span>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <!-- Card Footer with Actions -->
                            <div class="px-4 py-3 bg-gray-50 border-t border-gray-200 flex justify-between">
                                <button onclick="showSubmissionModal('<?= $submission['submission_type'] ?>', <?= $submission['id'] ?>)" class="text-sm text-blue-600 hover:text-blue-800">
                                    <i class="fas fa-eye mr-1"></i> View Details
                                </button>
                                <?php if ($submission['submission_type'] === 'request' && isset($submission['payment_status']) && $submission['payment_status'] === 'pending'): ?>
                                    <a href="complete_payment.php?id=<?= $submission['id'] ?>" class="text-sm text-green-600 hover:text-green-800">
                                        <i class="fas fa-credit-card mr-1"></i> Complete Payment
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="col-span-full bg-white rounded-lg shadow-sm p-8 text-center">
                        <i class="fas fa-search text-gray-400 text-5xl mb-4"></i>
                        <h3 class="text-xl font-medium text-gray-700 mb-2">No submissions found</h3>
                        <p class="text-gray-500 mb-6">We couldn't find any submissions matching your criteria.</p>
                        <div class="flex justify-center space-x-4">
                            <a href="submit_complaint.php" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
                                <i class="fas fa-plus-circle mr-2"></i> Submit a Complaint
                            </a>
                            <a href="submit_request.php" class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2">
                                <i class="fas fa-plus-circle mr-2"></i> Submit a Request
                            </a>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Submission Details Modal -->
            <div id="submissionModal" class="fixed inset-0 z-50 hidden overflow-y-auto">
                <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                    <!-- Background overlay -->
                    <div class="fixed inset-0 transition-opacity" aria-hidden="true">
                        <div class="absolute inset-0 bg-gray-500 opacity-75"></div>
                    </div>

                    <!-- Modal container -->
                    <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-2xl sm:w-full">
                        <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                            <div class="sm:flex sm:items-start">
                                <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left w-full">
                                    <div class="flex justify-between items-center mb-4">
                                        <h3 class="text-lg leading-6 font-medium text-gray-900" id="modalTitle">Submission Details</h3>
                                        <button type="button" class="text-gray-400 hover:text-gray-500" onclick="hideSubmissionModal()">
                                            <i class="fas fa-times"></i>
                                        </button>
                                    </div>
                                    
                                    <div class="mt-2">
                                        <div id="submissionDetails" class="space-y-4">
                                            <!-- Content will be loaded via AJAX -->
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                            <button type="button" class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm" onclick="hideSubmissionModal()">
                                Close
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
            <div class="mt-6 flex justify-center">
                <nav class="inline-flex rounded-md shadow-sm">
                    <?php if ($page > 1): ?>
                        <a href="?filter_type=<?= urlencode($filter_type) ?>&status=<?= urlencode($status_filter) ?>&sort_by=<?= urlencode($sort_by) ?>&search=<?= urlencode($search_query) ?>&page=<?= $page - 1 ?>" class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                            <i class="fas fa-chevron-left"></i>
                        </a>
                    <?php else: ?>
                        <span class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 bg-gray-100 text-sm font-medium text-gray-400 cursor-not-allowed">
                            <i class="fas fa-chevron-left"></i>
                        </span>
                    <?php endif; ?>
                    
                    <?php 
                    $start_page = max(1, $page - 2);
                    $end_page = min($total_pages, $start_page + 4);
                    if ($end_page - $start_page < 4 && $total_pages > 4) {
                        $start_page = max(1, $end_page - 4);
                    }
                    
                    for ($i = $start_page; $i <= $end_page; $i++): 
                    ?>
                        <?php if ($i == $page): ?>
                            <span class="relative inline-flex items-center px-4 py-2 border border-blue-500 bg-blue-50 text-sm font-medium text-blue-600">
                                <?= $i ?>
                            </span>
                        <?php else: ?>
                            <a href="?filter_type=<?= urlencode($filter_type) ?>&status=<?= urlencode($status_filter) ?>&sort_by=<?= urlencode($sort_by) ?>&search=<?= urlencode($search_query) ?>&page=<?= $i ?>" class="relative inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-700 hover:bg-gray-50">
                                <?= $i ?>
                            </a>
                        <?php endif; ?>
                    <?php endfor; ?>
                    
                    <?php if ($page < $total_pages): ?>
                        <a href="?filter_type=<?= urlencode($filter_type) ?>&status=<?= urlencode($status_filter) ?>&sort_by=<?= urlencode($sort_by) ?>&search=<?= urlencode($search_query) ?>&page=<?= $page + 1 ?>" class="relative inline-flex items-center px-2 py-2 rounded-r-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                            <i class="fas fa-chevron-right"></i>
                        </a>
                    <?php else: ?>
                        <span class="relative inline-flex items-center px-2 py-2 rounded-r-md border border-gray-300 bg-gray-100 text-sm font-medium text-gray-400 cursor-not-allowed">
                            <i class="fas fa-chevron-right"></i>
                        </span>
                    <?php endif; ?>
                </nav>
            </div>
            <?php endif; ?>
        </main>
    </div>
</div>

<script>
    document.getElementById('sidebarToggle').addEventListener('click', function () {
        document.querySelector('.sidebar').classList.toggle('hidden');
    });
    
    // Show submission modal
    function showSubmissionModal(type, id) {
        const modal = document.getElementById('submissionModal');
        modal.classList.remove('hidden');
        
        // Load content via AJAX
        fetch(`get_submission_details.php?type=${type}&id=${id}`)
            .then(response => response.text())
            .then(html => {
                document.getElementById('submissionDetails').innerHTML = html;
            })
            .catch(error => {
                console.error('Error loading submission details:', error);
                document.getElementById('submissionDetails').innerHTML = 
                    '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded">Error loading submission details</div>';
            });
    }
    
    // Hide submission modal
    function hideSubmissionModal() {
        document.getElementById('submissionModal').classList.add('hidden');
    }
    
    // Close modal when clicking outside
    document.getElementById('submissionModal').addEventListener('click', function(e) {
        if (e.target === this) {
            hideSubmissionModal();
        }
    });
</script>
</body>
</html>
