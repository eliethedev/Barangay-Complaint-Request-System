<?php
// Include database connection and auth check
require_once '../baby_capstone_connection.php';
require_once 'auth_check.php';

// Initialize variables
$update_success = false;
$update_error = '';
$filter_type = isset($_GET['type']) ? $_GET['type'] : 'all';
$filter_status = isset($_GET['status']) ? $_GET['status'] : 'all';
$filter_payment_status = isset($_GET['payment_status']) ? $_GET['payment_status'] : 'all';
$search_query = isset($_GET['search']) ? $_GET['search'] : '';

// Include notification function
require_once 'create_notification.php';

// Handle form submission for updating request status
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_request'])) {
    // Check for CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $update_error = "Security validation failed. Please try again.";
    } else {
        $request_id = $_POST['request_id'];
        $status = $_POST['status'];
        $payment_status = $_POST['payment_status'];
        $admin_notes = isset($_POST['admin_notes']) ? $_POST['admin_notes'] : '';
        
        if (empty($request_id) || empty($status)) {
            $update_error = "Request ID and status are required fields.";
        } elseif (!in_array($status, ['Pending', 'In Progress', 'Resolved', 'Rejected'])) {
            $update_error = "Invalid status provided.";
        } elseif (!in_array($payment_status, ['pending', 'paid'])) {
            $update_error = "Invalid payment status provided.";
        } else {
            try {
                // Get current status and payment status
                $stmt = $pdo->prepare("SELECT status, user_id, payment_status FROM requests WHERE id = ?");
                $stmt->execute([$request_id]);
                $result = $stmt->fetch();
                
                if (!$result) {
                    $update_error = "Request not found.";
                    return;
                }

                $current_status = $result['status'];
                $user_id = $result['user_id'];
                $current_payment_status = $result['payment_status'];
                
                // Get payment status from form
                $payment_status = $_POST['payment_status'];
                
                // Update request status and payment status
                $stmt = $pdo->prepare("UPDATE requests SET status = ?, admin_notes = ?, payment_status = ?, updated_at = NOW() WHERE id = ?");
                $stmt->execute([$status, $admin_notes, $payment_status, $request_id]);
                
                if ($stmt->rowCount() > 0) {
                    // Create notification if status or payment status changed
                    if ($status !== $current_status || $payment_status !== $current_payment_status) {
                        $message = "Request #" . $request_id . " status updated from " . $current_status . " to " . $status;
                        if ($payment_status !== $current_payment_status) {
                            $message .= " and payment status updated from " . $current_payment_status . " to " . $payment_status;
                        }
                        createNotification('request', $message, $user_id);
                    }
                    
                    $update_success = true;
                    
                    // Log the activity
                    $admin_id = $_SESSION['admin_id'];
                    $activity_stmt = $pdo->prepare("INSERT INTO activity_log (admin_id, activity_type, reference_id, details, created_at) VALUES (?, 'request_update', ?, ?, NOW())");
                    $activity_stmt->execute([$admin_id, $request_id, "Updated request status to {$status}"]);
                } else {
                    $update_error = "No changes were made.";
                }
            } catch (Exception $e) {
                $update_error = "Failed to update request: " . $e->getMessage();
            }
        }
    }
}

// Handle new request submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_request'])) {
    $user_id = $_POST['user_id'];
    $type = $_POST['type'];
    $name = $_POST['name'];
    $phone = $_POST['phone'];
    $details = $_POST['details'];
    
    // Insert request
    $sql = "INSERT INTO requests (user_id, type, name, phone, details, status) 
            VALUES (?, ?, ?, ?, ?, 'Pending')";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$user_id, $type, $name, $phone, $details]);
    
    if ($stmt->rowCount() > 0) {
        // Create notification for new request
        $request_id = $pdo->lastInsertId();
        $message = "New request #" . $request_id . " submitted by " . $name;
        createNotification('request', $message, $user_id);
        
        // Redirect to requests page
        header("Location: request.php");
        exit();
    }
}

// Function to fetch user details by user_id
function getUserDetails($pdo, $user_id) {
    $stmt = $pdo->prepare("SELECT full_name, email, phone FROM users WHERE id = :user_id");
    $stmt->execute([':user_id' => $user_id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

// Build the query based on filters
$query = "SELECT id, user_id, type, name, phone, details, attachments, status, created_at, updated_at, admin_notes, payment_status, payment_amount, proof_of_payment FROM requests WHERE 1=1";
$params = [];

// Apply type filter
if ($filter_type !== 'all') {
    $query .= " AND type = ?";
    $params[] = $filter_type;
}

// Apply status filter
if ($filter_status !== 'all') {
    $query .= " AND status = ?";
    $params[] = $filter_status;
}

// Apply payment status filter
if ($filter_payment_status !== 'all') {
    $query .= " AND payment_status = ?";
    $params[] = $filter_payment_status;
}

// Apply search filter
if (!empty($search_query)) {
    $query .= " AND (name LIKE ? OR details LIKE ? OR phone LIKE ?)"; 
    $search_param = "%{$search_query}%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

// Add ordering
$query .= " ORDER BY created_at DESC";

// Fetch filtered requests
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$requests = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Generate CSRF token
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Requests Management - Barangay System</title>
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
        .status-completed {
            background-color: #dcfce7;
            color: #15803d;
        }
        .status-rejected {
            background-color: #fee2e2;
            color: #b91c1c;
        }
        .request-document {
            background-color: #e0f2fe;
            color: #0369a1;
        }
        .request-permit {
            background-color: #ede9fe;
            color: #7c3aed;
        }
        .request-assistance {
            background-color: #fce7f3;
            color: #be185d;
        }
        .request-other {
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
            <!-- Requests Content -->
            <main class="p-4 md:p-6">
                <!-- Filters and Actions -->
                <div class="bg-white rounded-lg shadow p-4 mb-6">
                    <div class="flex flex-col md:flex-row md:items-center md:justify-between">
                        <div class="mb-4 md:mb-0">
                            <h2 class="text-lg font-semibold text-gray-800">Service Requests</h2>
                            <p class="text-sm text-gray-500">Manage all barangay service requests from residents</p>
                        </div>
                        <form method="GET" action="request.php" class="flex flex-col sm:flex-row space-y-2 sm:space-y-0 sm:space-x-2">
                            <div class="relative">
                                <select name="type" id="typeFilter" class="block w-full pl-3 pr-10 py-2 text-base border border-gray-300 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm rounded-md">
                                    <option value="all" <?= $filter_type === 'all' ? 'selected' : '' ?>>All Types</option>
                                    <option value="Document Request" <?= $filter_type === 'Document Request' ? 'selected' : '' ?>>Document Request</option>
                                    <option value="Permit Application" <?= $filter_type === 'Permit Application' ? 'selected' : '' ?>>Permit Application</option>
                                    <option value="Assistance" <?= $filter_type === 'Assistance' ? 'selected' : '' ?>>Assistance</option>
                                    <option value="Other" <?= $filter_type === 'Other' ? 'selected' : '' ?>>Other</option>
                                </select>
                            </div>
                            <div class="relative">
                                <select name="status" id="statusFilter" class="block w-full pl-3 pr-10 py-2 text-base border border-gray-300 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm rounded-md">
                                    <option value="all" <?= $filter_status === 'all' ? 'selected' : '' ?>>All Status</option>
                                    <option value="Pending" <?= $filter_status === 'Pending' ? 'selected' : '' ?>>Pending</option>
                                    <option value="In Progress" <?= $filter_status === 'In Progress' ? 'selected' : '' ?>>In Progress</option>
                                    <option value="Resolved" <?= $filter_status === 'Resolved' ? 'selected' : '' ?>>Resolved</option>
                                    <option value="Rejected" <?= $filter_status === 'Rejected' ? 'selected' : '' ?>>Rejected</option>
                                </select>
                            </div>
                            <div class="relative">
                                <select name="payment_status" id="paymentStatusFilter" class="block w-full pl-3 pr-10 py-2 text-base border border-gray-300 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm rounded-md">
                                    <option value="all" <?= $filter_payment_status === 'all' ? 'selected' : '' ?>>All Payment Status</option>
                                    <option value="pending" <?= $filter_payment_status === 'pending' ? 'selected' : '' ?>>Pending</option>
                                    <option value="paid" <?= $filter_payment_status === 'paid' ? 'selected' : '' ?>>Paid</option>
                                </select>
                            </div>
                            <div class="relative">
                                <input type="text" name="search" value="<?= htmlspecialchars($search_query) ?>" placeholder="Search requests..." class="block w-full pl-3 pr-10 py-2 text-base border border-gray-300 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm rounded-md">
                            </div>
                            <button type="submit" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                <i class="fas fa-filter mr-2"></i> Apply Filters
                            </button>
                            <a href="request.php" class="inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md shadow-sm text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                <i class="fas fa-times mr-2"></i> Clear
                            </a>
                        </form>
                    </div>
                </div>

                <!-- Requests Table -->
                <div class="bg-white shadow rounded-lg overflow-hidden">
                    <div class="px-4 py-3 border-b border-gray-200 flex justify-between items-center">
                        <div>
                            <h3 class="text-sm font-medium text-gray-700">Recent Requests</h3>
                            <p class="text-xs text-gray-500">Showing <?= count($requests) ?> request(s)</p>
                        </div>
                        <div>
                            <!-- Success and Error Messages -->
                            <?php if ($update_success): ?>
                            <div class="mb-3 bg-green-100 border-l-4 border-green-500 text-green-700 p-2 rounded" role="alert">
                                <p class="text-sm">Request status updated successfully!</p>
                            </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($update_error)): ?>
                            <div class="mb-3 bg-red-100 border-l-4 border-red-500 text-red-700 p-2 rounded" role="alert">
                                <p class="text-sm"><?= htmlspecialchars($update_error) ?></p>
                            </div>
                            <?php endif; ?>
                        </div>
                        <div>
                            <button class="inline-flex items-center px-3 py-1.5 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                <i class="fas fa-download mr-2"></i> Export
                            </button>
                            <button onclick="openNewRequestModal()" class="ml-2 inline-flex items-center px-3 py-1.5 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                <i class="fas fa-plus mr-2"></i> New Request
                            </button>
                        </div>
                    </div>
                    <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
    <thead class="bg-gray-50">
        <tr>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Request ID</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Resident</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Payment Status</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Payment Proof</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Request</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
        </tr>
    </thead>
    <tbody class="bg-white divide-y divide-gray-200">
        <?php foreach ($requests as $request): ?>
            <?php
                // Optional: fetch user details if needed
                // $user = getUserDetails($pdo, $request['user_id']);

                $statusClasses = [
                    'Pending'     => 'bg-yellow-100 text-yellow-800',
                    'In Progress' => 'bg-blue-100 text-blue-800',
                    'Resolved'    => 'bg-green-100 text-green-800',
                    'Rejected'    => 'bg-red-100 text-red-800'
                ];
                $statusClass = $statusClasses[$request['status']] ?? 'bg-gray-100 text-gray-800';

                $typeClasses = [
                    'Document'    => 'bg-indigo-100 text-indigo-800',
                    'Permit'      => 'bg-purple-100 text-purple-800',
                    'Assistance'  => 'bg-pink-100 text-pink-800',
                    'Other'       => 'bg-gray-100 text-gray-800'
                ];
                $typeClass = $typeClasses[$request['type']] ?? 'bg-gray-100 text-gray-800';
            ?>
            <tr class="hover:bg-gray-50">
                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?= htmlspecialchars($request['id']) ?></td>
                <td class="px-6 py-4 whitespace-nowrap">
                    <div class="text-sm font-medium text-gray-900"><?= htmlspecialchars($request['name']) ?></div>
                    <div class="text-sm text-gray-500"><?= htmlspecialchars($request['phone']) ?></div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?= $typeClass ?>">
                        <?= htmlspecialchars($request['type']) ?>
                    </span>
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                    <?php if ($request['payment_status'] === 'paid'): ?>
                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                            Paid
                        </span>
                    <?php else: ?>
                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-yellow-100 text-yellow-800">
                            Pending
                        </span>
                    <?php endif; ?>
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                    <?php if ($request['proof_of_payment']): ?>
                        <a href="<?= htmlspecialchars('../' . $request['proof_of_payment']) ?>" target="_blank" class="text-blue-600 hover:text-blue-800">
                            <i class="fas fa-file-image mr-1"></i> View Proof
                        </a>
                    <?php endif; ?>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                    <?= htmlspecialchars($request['details']) ?>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                    <?= date('M d, Y', strtotime($request['created_at'])) ?>
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?= $statusClass ?>">
                        <?= htmlspecialchars($request['status']) ?>
                    </span>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                    <a href="request_view.php?id=<?= $request['id'] ?>" class="text-blue-600 hover:text-blue-900" title="View Details"><i class="fas fa-eye"></i></a>
                </td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>
                    </div>
                    <div class="bg-white px-4 py-3 flex items-center justify-between border-t border-gray-200 sm:px-6">
                        <div class="flex-1 flex justify-between sm:hidden">
                            <a href="#" class="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">Previous</a>
                            <a href="#" class="ml-3 relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">Next</a>
                        </div>
                        <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
                            <div>
                                <p class="text-sm text-gray-700">
                                    Showing <span class="font-medium">1</span> to <span class="font-medium">8</span> of <span class="font-medium">89</span> results
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

                <!-- Request Details Modal -->
                <div id="requestModal" class="hidden fixed inset-0 overflow-y-auto z-50">
                    <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                        <div class="fixed inset-0 transition-opacity" aria-hidden="true">
                            <div class="absolute inset-0 bg-gray-500 opacity-75"></div>
                        </div>
                        <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
                        <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-2xl sm:w-full">
                            <form method="POST" action="request.php">
                                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                <input type="hidden" name="update_request" value="1">
                                <input type="hidden" name="request_id" id="form_request_id" value="">
                                
                                <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                                    <div class="sm:flex sm:items-start">
                                        <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left w-full">
                                            <h3 class="text-lg leading-6 font-medium text-gray-900" id="modalTitle">Request Details</h3>
                                            <div class="mt-2">
                                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                                    <div>
                                                        <p class="text-sm font-medium text-gray-500">Request ID</p>
                                                        <p id="modalRequestId" class="mt-1 text-sm text-gray-900"></p>
                                                    </div>
                                                    <div>
                                                        <p class="text-sm font-medium text-gray-500">Date Filed</p>
                                                        <p id="modalDate" class="mt-1 text-sm text-gray-900"></p>
                                                    </div>
                                                    <div>
                                                        <p class="text-sm font-medium text-gray-500">Resident Name</p>
                                                        <p id="modalName" class="mt-1 text-sm text-gray-900"></p>
                                                    </div>
                                                    <div>
                                                        <p class="text-sm font-medium text-gray-500">Contact Number</p>
                                                        <p id="modalContact" class="mt-1 text-sm text-gray-900"></p>
                                                    </div>
                                                    <div>
                                                        <p class="text-sm font-medium text-gray-500">Request Type</p>
                                                        <p id="modalType" class="mt-1 text-sm text-gray-900"></p>
                                                    </div>
                                                    <div>
                                                        <p class="text-sm font-medium text-gray-500">Payment Status</p>
                                                        <select name="payment_status" id="modalPaymentStatus" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                                                            <option value="pending">Pending</option>
                                                            <option value="paid">Paid</option>
                                                        </select>
                                                    </div>
                                                    <div>
                                                        <p class="text-sm font-medium text-gray-500">Payment Proof</p>
                                                        <div id="modalPaymentProof" class="mt-1 text-sm text-gray-900">
                                                            <p id="proofText" class="text-sm text-gray-500">No proof of payment uploaded</p>
                                                            <div id="proofContainer" class="mt-2 hidden">
                                                                <a id="viewProofLink" href="#" target="_blank" class="text-blue-600 hover:text-blue-800">
                                                                    <i class="fas fa-eye mr-1"></i> View Payment Proof
                                                                </a>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div>
                                                        <p class="text-sm font-medium text-gray-500">Status</p>
                                                        <select name="status" id="modalStatusSelect" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                                                            <option value="Pending">Pending</option>
                                                            <option value="In Progress">In Progress</option>
                                                            <option value="Resolved">Resolved</option>
                                                            <option value="Rejected">Rejected</option>
                                                        </select>
                                                    </div>
                                                    <div class="md:col-span-2">
                                                        <p class="text-sm font-medium text-gray-500">Request Details</p>
                                                        <p id="modalRequestDetails" class="mt-1 text-sm text-gray-900"></p>
                                                    </div>
                                                    <div class="md:col-span-2">
                                                        <p class="text-sm font-medium text-gray-500">Required Documents</p>
                                                        <p id="modalDocuments" class="mt-1 text-sm text-gray-900"></p>
                                                    </div>
                                                    <div class="md:col-span-2">
                                                        <p class="text-sm font-medium text-gray-500">Admin Notes</p>
                                                        <textarea name="admin_notes" id="modalAdminNotes" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm" placeholder="Add notes about this request..."></textarea>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                                    <button type="submit" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-blue-600 text-base font-medium text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:ml-3 sm:w-auto sm:text-sm">
                                        Update Request
                                    </button>
                                    <button type="button" onclick="closeModal()" class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
                                        Close
                                    </button>
                                    <button type="button" onclick="sendSMSFromModal()" class="mt-3 w-full inline-flex justify-center rounded-md border border-green-500 shadow-sm px-4 py-2 bg-green-50 text-base font-medium text-green-700 hover:bg-green-100 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
                                        <i class="fas fa-sms mr-2"></i> Send SMS
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- New Request Modal -->
                <div id="newRequestModal" class="hidden fixed inset-0 overflow-y-auto z-50">
                    <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                        <div class="fixed inset-0 transition-opacity" aria-hidden="true">
                            <div class="absolute inset-0 bg-gray-500 opacity-75"></div>
                        </div>
                        <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
                        <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-2xl sm:w-full">
                            <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                                <div class="sm:flex sm:items-start">
                                    <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left w-full">
                                        <h3 class="text-lg leading-6 font-medium text-gray-900">Create New Request</h3>
                                        <div class="mt-2">
                                            <form id="newRequestForm">
                                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                                    <div>
                                                        <label for="residentName" class="block text-sm font-medium text-gray-700">Resident Name</label>
                                                        <select id="residentName" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                                                            <option value="">Select Resident</option>
                                                            <option value="Juan Dela Cruz">Juan Dela Cruz</option>
                                                            <option value="Maria Santos">Maria Santos</option>
                                                            <option value="Pedro Reyes">Pedro Reyes</option>
                                                            <option value="Ana Martinez">Ana Martinez</option>
                                                        </select>
                                                    </div>
                                                    <div>
                                                        <label for="requestType" class="block text-sm font-medium text-gray-700">Request Type</label>
                                                        <select id="requestType" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                                                            <option value="">Select Type</option>
                                                            <option value="Document">Document Request</option>
                                                            <option value="Permit">Permit Application</option>
                                                            <option value="Assistance">Assistance</option>
                                                            <option value="Other">Other</option>
                                                        </select>
                                                    </div>
                                                    <div class="md:col-span-2">
                                                        <label for="requestDetails" class="block text-sm font-medium text-gray-700">Request Details</label>
                                                        <select id="requestDetails" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                                                            <option value="">Select Request</option>
                                                            <option value="Barangay Clearance">Barangay Clearance</option>
                                                            <option value="Business Permit">Business Permit</option>
                                                            <option value="Certificate of Residency">Certificate of Residency</option>
                                                            <option value="Barangay ID">Barangay ID</option>
                                                            <option value="Financial Aid">Financial Aid</option>
                                                            <option value="Medical Assistance">Medical Assistance</option>
                                                            <option value="Construction Permit">Construction Permit</option>
                                                            <option value="Community Garden Plot">Community Garden Plot</option>
                                                        </select>
                                                    </div>
                                                    <div class="md:col-span-2">
                                                        <label for="additionalInfo" class="block text-sm font-medium text-gray-700">Additional Information</label>
                                                        <textarea id="additionalInfo" rows="3" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm"></textarea>
                                                    </div>
                                                    <div class="md:col-span-2">
                                                        <label class="block text-sm font-medium text-gray-700">Required Documents</label>
                                                        <div class="mt-2 space-y-2">
                                                            <div class="flex items-center">
                                                                <input id="doc1" name="documents" type="checkbox" class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                                                                <label for="doc1" class="ml-2 block text-sm text-gray-700">Valid ID</label>
                                                            </div>
                                                            <div class="flex items-center">
                                                                <input id="doc2" name="documents" type="checkbox" class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                                                                <label for="doc2" class="ml-2 block text-sm text-gray-700">Proof of Residency</label>
                                                            </div>
                                                            <div class="flex items-center">
                                                                <input id="doc3" name="documents" type="checkbox" class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                                                                <label for="doc3" class="ml-2 block text-sm text-gray-700">Application Form</label>
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
                                <button type="button" onclick="submitNewRequest()" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-blue-600 text-base font-medium text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:ml-3 sm:w-auto sm:text-sm">
                                    Submit Request
                                </button>
                                <button type="button" onclick="closeNewRequestModal()" class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
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
                                                <label for="smsRequestId" class="block text-sm font-medium text-gray-700">Request ID</label>
                                                <input type="text" id="smsRequestId" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm" readonly>
                                            </div>
                                            <div class="mb-4">
                                                <label for="smsMessage" class="block text-sm font-medium text-gray-700">Message</label>
                                                <textarea id="smsMessage" rows="4" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">Your request #REQ-2023-001 has been received and is currently being processed. We will update you on the status. Thank you.</textarea>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                                <button type="button" onclick="submitSMS()" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-green-600 text-base font-medium text-white hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 sm:ml-3 sm:w-auto sm:text-sm">
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

        // View request details
        async function viewRequest(requestId) {
            try {
                // Show modal first with loading state
                document.getElementById('requestModal').classList.remove('hidden');
                document.getElementById('modalRequestId').textContent = 'Loading...';
                
                // Fetch request data
                const response = await fetch(`get_request_details.php?id=${requestId}`);
                const data = await response.json();
                
                if (data.error) {
                    document.getElementById('modalRequestId').textContent = 'Error: ' + data.error;
                    return;
                }
                
                // Fill modal with data
                document.getElementById('modalRequestId').textContent = data.id;
                document.getElementById('form_request_id').value = data.id;
                document.getElementById('modalName').textContent = data.name || data.full_name || 'N/A';
                document.getElementById('modalContact').textContent = data.phone || data.user_phone || 'N/A';
                document.getElementById('modalType').textContent = data.type || 'N/A';
                document.getElementById('modalDate').textContent = formatDate(data.created_at);
                document.getElementById('modalStatusSelect').value = data.status || 'Pending';
                document.getElementById('modalPaymentStatus').value = data.payment_status || 'pending';
                document.getElementById('modalRequestDetails').textContent = data.details || 'N/A';
                
                // Set documents info
                let docInfo = [];
                if (data.attachments) docInfo.push(data.attachments);
                document.getElementById('modalDocuments').textContent = docInfo.length > 0 ? docInfo.join(', ') : 'None';
                
                // Set admin notes
                document.getElementById('modalAdminNotes').value = data.admin_notes || '';
                
                // Set payment proof
                if (data.proof_of_payment) {
                    document.getElementById('proofText').textContent = 'Payment proof uploaded';
                    document.getElementById('proofContainer').classList.remove('hidden');
                    document.getElementById('viewProofLink').href = '../' + data.proof_of_payment;
                } else {
                    document.getElementById('proofText').textContent = 'No proof of payment uploaded';
                    document.getElementById('proofContainer').classList.add('hidden');
                }
                
                // Store data for SMS
                document.getElementById('requestModal').dataset.phone = data.phone || data.user_phone || '';
                document.getElementById('requestModal').dataset.name = data.name || data.full_name || '';
            } catch (error) {
                document.getElementById('modalRequestId').textContent = 'Error loading data';
                console.error('Error fetching request data:', error);
            }
        }
        
        // Format date helper function
        function formatDate(dateString) {
            if (!dateString) return 'N/A';
            const options = { year: 'numeric', month: 'short', day: 'numeric' };
            return new Date(dateString).toLocaleDateString(undefined, options);
        }

        // Close modal
        function closeModal() {
            document.getElementById('requestModal').classList.add('hidden');
            document.getElementById('newRequestModal').classList.add('hidden');
            document.getElementById('smsModal').classList.add('hidden');
        }

        // Close new request modal
        function closeNewRequestModal() {
            document.getElementById('newRequestModal').classList.add('hidden');
        }

        // Open new request modal
        function openNewRequestModal() {
            // In a production environment, this would fetch residents from the server
            // For now, we'll just show the modal with static data
            document.getElementById('newRequestModal').classList.remove('hidden');
        }

        // Send SMS notification from the request modal
        function sendSMSFromModal() {
            const modal = document.getElementById('requestModal');
            const phone = modal.dataset.phone;
            const name = modal.dataset.name;
            const requestId = document.getElementById('form_request_id').value;
            
            sendSMS(phone, name, requestId);
        }

        // Send SMS notification
        function sendSMS(phone, name, requestId) {
            document.getElementById('smsRecipient').value = name + ' (' + phone + ')';
            document.getElementById('smsRequestId').value = requestId;
            document.getElementById('smsMessage').value = `Your request ${requestId} has been received and is currently being processed. We will update you on the status. Thank you.`;
            document.getElementById('smsModal').classList.remove('hidden');
        }

        // Close SMS modal
        function closeSMSModal() {
            document.getElementById('smsModal').classList.add('hidden');
        }

        // Actually send SMS (demo)
        function submitSMS() {
            const message = document.getElementById('smsMessage').value;
            alert('SMS would be sent with message:\n\n' + message);
            closeSMSModal();
        }

        // Submit new request
        function submitNewRequest() {
            const resident = document.getElementById('residentName').value;
            const type = document.getElementById('requestType').value;
            const details = document.getElementById('requestDetails').value;
            const info = document.getElementById('additionalInfo').value;
            
            if (!resident || !type || !details) {
                alert('Please fill in all required fields');
                return;
            }
            
            alert(`New request submitted:\n\nResident: ${resident}\nType: ${type}\nDetails: ${details}\nAdditional Info: ${info}`);
            closeNewRequestModal();
            document.getElementById('newRequestForm').reset();
        }
    </script>
</body>
</html>