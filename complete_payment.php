<?php
session_start();
require_once 'baby_capstone_connection.php';
require_once 'includes/payment/PaymentProcessor.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Initialize PaymentProcessor
$paymentProcessor = new \Barangay\Payment\PaymentProcessor($pdo, $_SESSION['user_id']);

// Get request ID
$request_id = $_GET['id'] ?? 0;

// Validate request ID
if (!is_numeric($request_id) || $request_id <= 0) {
    $_SESSION['error'] = "Invalid request ID";
    header("Location: submission.php");
    exit();
}

// Get request details
$request = $paymentProcessor->getRequestDetails($request_id);

if (!$request) {
    $_SESSION['error'] = "Request not found or doesn't belong to you";
    header("Location: submission.php");
    exit();
}

// Process payment
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Validate CSRF token
        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
            throw new Exception('Invalid CSRF token');
        }

        $payment_reference = trim($_POST['payment_reference'] ?? '');
        $payment_amount = floatval($_POST['payment_amount'] ?? 0);

        // Validate payment details
        if (empty($payment_reference) || $payment_amount <= 0) {
            throw new Exception('Please fill all payment details');
        }

        // Validate payment amount against request type
        $expected_amount = $paymentProcessor->calculatePaymentAmount($request['type']);
        if ($payment_amount !== $expected_amount) {
            throw new Exception('Invalid payment amount');
        }

        // Complete payment
        if ($paymentProcessor->completePayment($payment_reference, $payment_amount)) {
            // Create notification
            $paymentProcessor->createPaymentNotification($request_id);
            
            $_SESSION['success'] = "Payment completed successfully!";
            header("Location: submission.php");
            exit();
        } else {
            throw new Exception('Error processing payment');
        }
    } catch (Exception $e) {
        $_SESSION['error'] = $e->getMessage();
        header("Location: complete_payment.php?id=$request_id");
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Complete Payment - Barangay System</title>
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
            <div class="max-w-md mx-auto bg-white rounded-lg shadow-md overflow-hidden">
                <div class="bg-blue-600 px-4 py-3">
                    <h2 class="text-xl font-semibold text-white">Complete Payment</h2>
                </div>
                
                <div class="p-6">
                    <?php if (isset($_SESSION['error'])): ?>
                        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                            <?= htmlspecialchars($_SESSION['error']) ?>
                            <?php unset($_SESSION['error']); ?>
                        </div>
                    <?php endif; ?>
                    
                    <form method="POST" action="complete_payment.php?id=<?= $request_id ?>">
                        <div class="mb-4">
                            <label class="block text-gray-700 text-sm font-bold mb-2" for="payment_amount">
                                Amount
                            </label>
                            <input 
                                type="number" 
                                id="payment_amount" 
                                name="payment_amount" 
                                step="0.01"
                                class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" 
                                required>
                        </div>
                        
                        <div class="mb-6">
                            <label class="block text-gray-700 text-sm font-bold mb-2" for="payment_reference">
                                Reference Number
                            </label>
                            <input 
                                type="text" 
                                id="payment_reference" 
                                name="payment_reference" 
                                class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" 
                                required>
                        </div>
                        
                        <div class="flex items-center justify-between">
                            <a href="submission.php" class="text-sm text-blue-600 hover:text-blue-800">
                                Back to Submissions
                            </a>
                            <button 
                                type="submit" 
                                class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                                Complete Payment
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </main>
    </div>
</div>
</body>
</html>
