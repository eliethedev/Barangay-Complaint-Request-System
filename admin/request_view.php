<?php
// Include database connection and auth check
require_once '../baby_capstone_connection.php';
require_once 'auth_check.php';

// Initialize variables
$request_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$update_success = false;
$update_error = '';

// Check if a valid request ID was provided
if ($request_id <= 0) {
    header("Location: request.php");
    exit();
}

// Process form submission if this is a POST request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_request'])) {
    // Check for CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $update_error = "Security validation failed. Please try again.";
    } else {
        $status = $_POST['status'];
        $payment_status = $_POST['payment_status'];
        $admin_notes = isset($_POST['admin_notes']) ? $_POST['admin_notes'] : '';
        
        if (empty($status)) {
            $update_error = "Status is a required field.";
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
                } else {
                    $current_status = $result['status'];
                    $user_id = $result['user_id'];
                    $current_payment_status = $result['payment_status'];
                    
                    // Update request status and payment status
                    $stmt = $pdo->prepare("UPDATE requests SET status = ?, admin_notes = ?, payment_status = ?, updated_at = NOW() WHERE id = ?");
                    $stmt->execute([$status, $admin_notes, $payment_status, $request_id]);
                    
                    if ($stmt->rowCount() > 0) {
                        // Include notification function
                        require_once 'create_notification.php';
                        
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
                        
                        // Redirect to prevent form resubmission
                        header("Location: request.php");
                        exit();
                    } else {
                        $update_error = "No changes were made.";
                    }
                }
            } catch (Exception $e) {
                $update_error = "Failed to update request: " . $e->getMessage();
            }
        }
    }
}

// Fetch request data from database
$stmt = $pdo->prepare("SELECT * FROM requests WHERE id = ?");
$stmt->execute([$request_id]);
$request = $stmt->fetch(PDO::FETCH_ASSOC);

// If request doesn't exist, redirect back to list
if (!$request) {
    header("Location: request.php");
    exit();
}

// Get user details if needed
$user_stmt = $pdo->prepare("SELECT full_name, email, phone FROM users WHERE id = ?");
$user_stmt->execute([$request['user_id']]);
$user = $user_stmt->fetch(PDO::FETCH_ASSOC);

// Format date for display
function formatDate($dateString) {
    return date('M d, Y', strtotime($dateString));
}

// Generate CSRF token if not exists
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Request Details - Barangay System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-100 font-sans">
    <div class="flex h-screen overflow-hidden">
        <!-- Sidebar -->
        <?php include 'sidebar.php' ?>

        <!-- Main Content -->
        <div class="flex-1 overflow-auto">
            <!-- Top Navigation -->
            <?php include 'header.php' ?>
            
            <!-- Request Details Content -->
            <main class="p-4 md:p-6">
                <div class="bg-white rounded-lg shadow p-4 mb-6">
                    <div class="flex items-center justify-between mb-4">
                        <h2 class="text-lg font-semibold text-gray-800">Request Details</h2>
                        <a href="request.php" class="text-blue-600 hover:text-blue-800">
                            <i class="fas fa-arrow-left mr-1"></i> Back to Requests
                        </a>
                    </div>
                    
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

                    <form method="POST" action="request_view.php?id=<?= $request_id ?>">
                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                        <input type="hidden" name="update_request" value="1">
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <p class="text-sm font-medium text-gray-500">Request ID</p>
                                <p class="mt-1 text-sm text-gray-900"><?= htmlspecialchars($request['id']) ?></p>
                            </div>
                            <div>
                                <p class="text-sm font-medium text-gray-500">Date Filed</p>
                                <p class="mt-1 text-sm text-gray-900"><?= formatDate($request['created_at']) ?></p>
                            </div>
                            <div>
                                <p class="text-sm font-medium text-gray-500">Resident Name</p>
                                <p class="mt-1 text-sm text-gray-900"><?= htmlspecialchars($request['name'] ?? $user['full_name'] ?? 'N/A') ?></p>
                            </div>
                            <div>
                                <p class="text-sm font-medium text-gray-500">Contact Number</p>
                                <p class="mt-1 text-sm text-gray-900"><?= htmlspecialchars(
                                    !empty($request['phone']) ? $request['phone'] : (
                                        !empty($user['phone']) ? $user['phone'] : 'N/A'
                                    )
                                ) ?></p>
                            </div>
                            <div>
                                <p class="text-sm font-medium text-gray-500">Request Type</p>
                                <p class="mt-1 text-sm text-gray-900"><?= htmlspecialchars($request['type'] ?? 'N/A') ?></p>
                            </div>
                            <div>
                                <p class="text-sm font-medium text-gray-500">Payment Status</p>
                                <select name="payment_status" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                                    <option value="pending" <?= ($request['payment_status'] === 'pending') ? 'selected' : '' ?>>Pending</option>
                                    <option value="paid" <?= ($request['payment_status'] === 'paid') ? 'selected' : '' ?>>Paid</option>
                                </select>
                            </div>
                            <div>
                                <p class="text-sm font-medium text-gray-500">Payment Proof</p>
                                <div class="mt-1 text-sm text-gray-900">
                                    <?php if ($request['proof_of_payment']): ?>
                                        <p class="text-sm text-gray-900">Payment proof uploaded</p>
                                        <div class="mt-2">
                                            <a href="<?= htmlspecialchars('../' . $request['proof_of_payment']) ?>" target="_blank" class="text-blue-600 hover:text-blue-800">
                                                <i class="fas fa-eye mr-1"></i> View Payment Proof
                                            </a>
                                        </div>
                                    <?php else: ?>
                                        <p class="text-sm text-gray-500">No proof of payment uploaded</p>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div>
                                <p class="text-sm font-medium text-gray-500">Status</p>
                                <select name="status" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                                    <option value="Pending" <?= ($request['status'] === 'Pending') ? 'selected' : '' ?>>Pending</option>
                                    <option value="In Progress" <?= ($request['status'] === 'In Progress') ? 'selected' : '' ?>>In Progress</option>
                                    <option value="Resolved" <?= ($request['status'] === 'Resolved') ? 'selected' : '' ?>>Resolved</option>
                                    <option value="Rejected" <?= ($request['status'] === 'Rejected') ? 'selected' : '' ?>>Rejected</option>
                                </select>
                            </div>
                            <div class="md:col-span-2">
                                <p class="text-sm font-medium text-gray-500">Request Details</p>
                                <p class="mt-1 text-sm text-gray-900"><?= htmlspecialchars($request['details'] ?? 'N/A') ?></p>
                            </div>
                            <div class="md:col-span-2">
                                <p class="text-sm font-medium text-gray-500">Required Documents</p>
                                <?php if (!empty($request['attachments'])): ?>
                                    <ul class="mt-1 list-disc list-inside space-y-1">
                                        <?php foreach(explode(',', $request['attachments']) as $doc): ?>
                                            <li class="text-sm text-gray-900"><?= htmlspecialchars(trim($doc)) ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php else: ?>
                                    <p class="mt-1 text-sm text-gray-500">None</p>
                                <?php endif; ?>
                            </div>
                            <div class="md:col-span-2">
                                <p class="text-sm font-medium text-gray-500">Admin Notes</p>
                                <textarea name="admin_notes" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm" placeholder="Add notes about this request..."><?= htmlspecialchars($request['admin_notes'] ?? '') ?></textarea>
                            </div>
                        </div>
                        
                        <div class="mt-6 flex flex-col sm:flex-row justify-end space-y-2 sm:space-y-0 sm:space-x-3">
                            <button type="submit" class="inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-blue-600 text-base font-medium text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:w-auto sm:text-sm">
                                Update Request
                            </button>
                            <a href="request.php" class="inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:w-auto sm:text-sm">
                                Cancel
                            </a>
                            <button type="button" onclick="sendSMS('<?= htmlspecialchars($request['phone'] ?? $user['phone'] ?? '') ?>', '<?= htmlspecialchars($request['name'] ?? $user['full_name'] ?? '') ?>', '<?= $request_id ?>')" class="inline-flex justify-center rounded-md border border-green-500 shadow-sm px-4 py-2 bg-green-50 text-base font-medium text-green-700 hover:bg-green-100 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 sm:w-auto sm:text-sm">
                                <i class="fas fa-sms mr-2"></i> Send SMS
                            </button>
                        </div>
                    </form>
                </div>
            </main>
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
                                    <textarea id="smsMessage" rows="4" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm"></textarea>
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

    <script>
        // Basic JavaScript for SMS functionality only
        function sendSMS(phone, name, requestId) {
            document.getElementById('smsRecipient').value = name + ' (' + phone + ')';
            document.getElementById('smsRequestId').value = requestId;
            document.getElementById('smsMessage').value = `Your request ${requestId} has been received and is currently being processed. We will update you on the status. Thank you.`;
            document.getElementById('smsModal').classList.remove('hidden');
        }

        function closeSMSModal() {
            document.getElementById('smsModal').classList.add('hidden');
        }

        function submitSMS() {
            const message = document.getElementById('smsMessage').value;
            const phone = '<?= htmlspecialchars(
                !empty($request['phone']) ? $request['phone'] : (
                    !empty($user['phone']) ? $user['phone'] : ''
                )
            ) ?>';

            fetch('sms_functions.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({
                    action: 'send_sms',
                    phone: phone,
                    message: message,
                    request_id: <?= $request_id ?>
                })
            })
            .then(response => response.json())
            .then(data => {
                if(data.success) {
                    alert('SMS sent successfully');
                } else {
                    alert('Failed to send SMS: ' + data.error);
                }
                closeSMSModal();
            });
        }
    </script>
</body>
</html>