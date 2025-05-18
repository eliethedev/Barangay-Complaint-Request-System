<?php
session_start();

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit();
}

// DB connection
require_once '../baby_capstone_connection.php';

// Get all pending payments
$stmt = $pdo->prepare("SELECT r.id, r.type, r.name, r.payment_amount, r.payment_reference, r.created_at, u.full_name 
                      FROM requests r 
                      JOIN users u ON r.user_id = u.id 
                      WHERE r.payment_status = 'paid' AND r.status = 'pending'");
$stmt->execute();
$payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Process payment verification
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $request_id = $_POST['request_id'] ?? 0;
    $action = $_POST['action'] ?? '';
    
    if ($action === 'verify') {
        $stmt = $pdo->prepare("UPDATE requests SET status = 'approved', updated_at = NOW() WHERE id = ?");
        $stmt->execute([$request_id]);
        
        // Create notification for user
        $message = "Your payment for request #$request_id has been verified";
        createNotification('payment_verified', $message, $request_id);
        
        $_SESSION['success'] = "Payment verified successfully!";
    } elseif ($action === 'reject') {
        $stmt = $pdo->prepare("UPDATE requests SET payment_status = 'failed', updated_at = NOW() WHERE id = ?");
        $stmt->execute([$request_id]);
        
        // Create notification for user
        $message = "Your payment for request #$request_id was rejected";
        createNotification('payment_rejected', $message, $request_id);
        
        $_SESSION['success'] = "Payment rejected successfully!";
    }
    
    header("Location: manage_payments.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Payments - Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-100 font-sans">
<div class="flex h-screen overflow-hidden">
    <!-- Sidebar -->
    <?php include 'sidebar.php'; ?>
    
    <!-- Main Content -->
    <div class="flex-1 overflow-auto">
        <?php include 'header.php'; ?>

        <!-- Main Content -->
        <main class="p-4 md:p-6">
            <div class="mb-6 flex justify-between items-center">
                <h1 class="text-2xl font-bold text-gray-800">Manage Payments</h1>
            </div>
            
            <?php if (isset($_SESSION['success'])): ?>
                <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                    <?= htmlspecialchars($_SESSION['success']) ?>
                    <?php unset($_SESSION['success']); ?>
                </div>
            <?php endif; ?>
            
            <div class="bg-white rounded-lg shadow-sm overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Request ID</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">User</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Amount</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Reference</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php if (count($payments) > 0): ?>
                                <?php foreach ($payments as $payment): ?>
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">#<?= htmlspecialchars($payment['id']) ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?= htmlspecialchars($payment['type']) ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?= htmlspecialchars($payment['full_name']) ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?= htmlspecialchars($payment['payment_amount']) ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?= htmlspecialchars($payment['payment_reference']) ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?= date('M d, Y', strtotime($payment['created_at'])) ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                            <form method="POST" class="inline">
                                                <input type="hidden" name="request_id" value="<?= $payment['id'] ?>">
                                                <button type="submit" name="action" value="verify" class="text-green-600 hover:text-green-900 mr-3">
                                                    <i class="fas fa-check-circle mr-1"></i> Verify
                                                </button>
                                                <button type="submit" name="action" value="reject" class="text-red-600 hover:text-red-900">
                                                    <i class="fas fa-times-circle mr-1"></i> Reject
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" class="px-6 py-4 text-center text-sm text-gray-500">
                                        No pending payments to verify
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>
</div>
</body>
</html>
