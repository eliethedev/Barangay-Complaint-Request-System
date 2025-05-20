<?php
session_start();
require_once 'baby_capstone_connection.php';
require_once 'includes/payment/PaymentProcessor.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$paymentProcessor = new \Barangay\Payment\PaymentProcessor($pdo, $_SESSION['user_id']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Validate CSRF token
        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
            throw new Exception('Invalid CSRF token');
        }

        $requestType = trim($_POST['request_type']);
        $details = trim($_POST['details']);
        $payment_amount = floatval($_POST['payment_amount'] ?? 0);
        $payment_reference = trim($_POST['payment_reference'] ?? '');
        $attachments = $_FILES['attachments'] ?? null;
        $proof_of_payment = $_FILES['proof_of_payment'] ?? null;

        // Validate required fields
        if (empty($requestType) || empty($details)) {
            throw new Exception('All fields are required');
        }

        // Get user info
        $stmt = $pdo->prepare("SELECT full_name, email FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        // Get the expected payment amount but don't strictly validate it
        // This allows the admin to verify the payment manually through the uploaded proof
        $calculated_amount = $paymentProcessor->calculatePaymentAmount($requestType);
        
        // Use the calculated amount rather than the user-provided one
        $payment_amount = $calculated_amount;

        // Generate payment reference if not provided
        if (empty($payment_reference)) {
            $payment_reference = $paymentProcessor->generatePaymentReference();
        }

        // Insert request
        $stmt = $pdo->prepare("
            INSERT INTO requests 
            (type, name, phone, details, purpose, validity, location, urgency, 
             parties, mediation_date, assistance_type, beneficiaries, attachments, 
             payment_status, payment_method, payment_amount, payment_reference, 
             created_at, user_id, proof_of_payment) 
            VALUES 
            (:type, :name, :phone, :details, :purpose, :validity, :location, :urgency, 
             :parties, :mediation_date, :assistance_type, :beneficiaries, :attachments, 
             'pending', 'gcash', :payment_amount, :payment_reference, :created_at, :user_id, NULL)
        ");

        $stmt->execute([
            ':type' => $requestType,
            ':name' => $user['full_name'] ?? 'Anonymous',
            ':phone' => $user['email'] ?? '',
            ':details' => $details,
            ':purpose' => $_POST['purpose'] ?? '',
            ':validity' => $_POST['validity'] ?? '',
            ':location' => $_POST['location'] ?? '',
            ':urgency' => $_POST['urgency'] ?? '',
            ':parties' => $_POST['parties'] ?? '',
            ':mediation_date' => $_POST['mediation_date'] ?? '',
            ':assistance_type' => $_POST['assistance_type'] ?? '',
            ':beneficiaries' => $_POST['beneficiaries'] ?? '',
            ':attachments' => !empty($attachments) ? json_encode($attachments) : null,
            ':payment_amount' => $payment_amount,
            ':payment_reference' => $payment_reference,
            ':created_at' => date('Y-m-d H:i:s'),
            ':user_id' => $_SESSION['user_id']
        ]);

        $request_id = $pdo->lastInsertId();

        // Handle payment proof upload
        if ($payment_amount > 0 && !empty($proof_of_payment['name'])) {
            try {
                // Log payment proof details for debugging
                error_log("Payment proof info: " . json_encode([
                    'request_id' => $request_id,
                    'filename' => $proof_of_payment['name'],
                    'type' => $proof_of_payment['type'],
                    'size' => $proof_of_payment['size'],
                    'tmp_name' => $proof_of_payment['tmp_name'],
                    'error' => $proof_of_payment['error']
                ]));
                
                // Check if the file was uploaded properly
                if ($proof_of_payment['error'] !== UPLOAD_ERR_OK) {
                    $uploadErrors = [
                        UPLOAD_ERR_INI_SIZE => 'The uploaded file exceeds the upload_max_filesize directive in php.ini',
                        UPLOAD_ERR_FORM_SIZE => 'The uploaded file exceeds the MAX_FILE_SIZE directive in the HTML form',
                        UPLOAD_ERR_PARTIAL => 'The uploaded file was only partially uploaded',
                        UPLOAD_ERR_NO_FILE => 'No file was uploaded',
                        UPLOAD_ERR_NO_TMP_DIR => 'Missing a temporary folder',
                        UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
                        UPLOAD_ERR_EXTENSION => 'A PHP extension stopped the file upload'
                    ];
                    
                    $errorMessage = isset($uploadErrors[$proof_of_payment['error']]) 
                        ? $uploadErrors[$proof_of_payment['error']] 
                        : 'Unknown upload error';
                    
                    error_log("Upload error: " . $errorMessage);
                    throw new \Exception("Payment proof upload failed: " . $errorMessage);
                }
                
                $result = $paymentProcessor->handlePaymentProof($request_id, $proof_of_payment);
                if (!$result) {
                    error_log("Failed to store payment proof for request ID: $request_id");
                } else {
                    error_log("Successfully stored payment proof for request ID: $request_id");
                }
            } catch (\Exception $e) {
                $_SESSION['error'] = $e->getMessage();
                error_log("Payment proof upload error: " . $e->getMessage());
                // Continue with the submission despite the error
            }
        } else {
            error_log("No payment proof uploaded: " . 
                ($payment_amount > 0 ? "Payment amount is positive but no file uploaded" : "Payment amount is 0") . 
                " - Proof info: " . json_encode($_FILES));
        }

        // Create notification
        $paymentProcessor->createPaymentNotification($request_id);

        // Format success message
        $success_message = "<div class='text-left'>"
            . "<h3 class='font-bold text-lg mb-2'>Request Submitted Successfully!</h3>"
            . "<div class='space-y-1'>"
            . "<p><span class='font-medium'>Request ID:</span> #$request_id</p>"
            . "<p><span class='font-medium'>Type:</span> $requestType</p>";
            
        if ($payment_amount > 0) {
            $success_message .= "<p><span class='font-medium'>Payment Amount:</span> ₱" . number_format($payment_amount, 2) . "</p>"
                . "<p><span class='font-medium'>Payment Reference:</span> $payment_reference</p>"
                . "<p><span class='font-medium'>Status:</span> <span class='text-yellow-600'>Pending Admin Verification</span></p>"
                . "<p class='text-xs text-yellow-600 mt-1'>Your payment proof has been uploaded and will be verified by the administrator.</p>";
        }
        
        $success_message .= "</div></div>";
        
        $_SESSION['success'] = $success_message;
        header("Location: submit_request.php");
        exit();

    } catch (Exception $e) {
        $_SESSION['error'] = $e->getMessage();
        header("Location: submit_request.php");
        exit();
    }
}

// Generate CSRF token
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
    <title>Submit Request - Barangay System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="js/payment.js"></script>
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
        
        .form-section {
            background: white;
            border-radius: 12px;
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
            padding: 30px;
        }
        
        .form-section h2 {
            color: #1e3a8a;
            margin-bottom: 1.5rem;
            font-size: 1.5rem;
            font-weight: 600;
        }
        
        .form-group {
            margin-bottom: 1.75rem;
        }
        
        .form-label {
            position: absolute;
            top: 0.75rem;
            left: 1rem;
            color: #6b7280;
            background: white;
            padding: 0 0.25rem;
            transition: all 0.2s ease;
            pointer-events: none;
        }
        
        .form-control:focus + .form-label,
        .form-control:not(:placeholder-shown) + .form-label {
            top: -0.5rem;
            left: 0.75rem;
            font-size: 0.75rem;
            color: #1e40af;
        }
        
        .form-control {
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 0.75rem 1rem;
            width: 100%;
            transition: all 0.3s ease;
            background: white;
        }
        
        .form-control:focus {
            border-color: #1e40af;
            box-shadow: 0 0 0 3px rgba(30, 64, 175, 0.1);
            outline: none;
        }
        
        .btn-primary {
            background-color: #1e40af;
            color: white;
            border-radius: 8px;
            padding: 0.875rem 2rem;
            font-weight: 600;
            transition: all 0.3s ease;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        }
        
        .btn-primary:hover {
            background-color: #1e3a8a;
            transform: translateY(-2px);
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
        }
        
        .notification {
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 1rem;
        }
        
        .notification-success {
            background-color: #dcfce7;
            border: 1px solid #bbf7d0;
            color: #166534;
        }
        
        .notification-error {
            background-color: #fee2e2;
            border: 1px solid #fecaca;
            color: #991b1b;
        }
        
        .close-btn {
            position: absolute;
            right: 1rem;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
        }
        
        /* Payment Modal Styles */
        .payment-modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 1000;
        }
        
        .payment-modal.active {
            display: flex;
        }
        
        .payment-modal-content {
            background: white;
            border-radius: 12px;
            padding: 2rem;
            max-width: 500px;
            width: 90%;
            position: relative;
        }
        
        .payment-modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }
        
        .modal-close {
            cursor: pointer;
            color: #6b7280;
            font-size: 1.5rem;
        }
        
        .payment-info {
            text-align: center;
            margin-bottom: 1.5rem;
        }
        
        .gcash-qr {
            max-width: 200px;
            margin: 0 auto 1rem;
            border-radius: 8px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }
        
        .payment-instructions {
            background: #f8fafc;
            border-radius: 8px;
            padding: 1rem;
            margin-top: 1rem;
        }
        
        /* Responsive Design */
        @media (max-width: 768px) {
            .form-section {
                padding: 1.5rem;
            }
            
            .payment-modal-content {
                padding: 1.5rem;
            }
        }
    </style>
</head>
<body class="bg-gray-100 font-sans">
    <div class="flex h-screen overflow-hidden">
        <!-- Sidebar -->
        <?php include 'sidebar.php' ?>
        <!-- Main Content wrapper -->
        <div class="flex-1 overflow-auto">
            <?php include 'header.php'; ?>
            
            <!-- Content area -->
            <div class="container mx-auto py-3 px-6">
                <!-- Notification for success or error -->
                <?php if (isset($_SESSION['success'])): ?>
                    <div class="notification notification-success relative">
                        <div class="flex items-start">
                            <div class="flex-shrink-0">
                                <i class="fas fa-check-circle text-green-500 text-xl mr-3"></i>
                            </div>
                            <div>
                                <?= $_SESSION['success'] ?>
                            </div>
                        </div>
                        <button class="close-btn" onclick="this.parentElement.style.display='none';">&times;</button>
                    </div>
                    <?php unset($_SESSION['success']); ?>
                <?php endif; ?>

                <?php if (isset($_SESSION['error'])): ?>
                    <div class="notification notification-error relative">
                        <strong class="font-bold">Error!</strong>
                        <span class="block sm:inline"><?= htmlspecialchars($_SESSION['error']) ?></span>
                        <button class="close-btn" onclick="this.parentElement.style.display='none';">&times;</button>
                    </div>
                    <?php unset($_SESSION['error']); ?>
                <?php endif; ?>

                <div class="form-section">
                <h1 class="text-3xl font-bold text-gray-800 mb-5">Submit a Request</h1>
                    <form id="requestForm" action="submit_request.php" method="POST" enctype="multipart/form-data">
                        <!-- CSRF Token -->
                        <input type="hidden" name="csrf_token" id="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                        
                        <!-- Hidden fields for payment information -->
                        <input type="hidden" name="payment_status" id="payment_status" value="pending">
                        <input type="hidden" name="payment_method" id="payment_method" value="gcash">
                        <input type="hidden" name="payment_amount" id="payment_amount_field" value="0">
                        <input type="hidden" name="payment_reference" id="payment_reference_field" value="">
                        
                        <!-- Hidden file input for proof of payment -->
                        <input type="file" id="proof_of_payment" name="proof_of_payment" accept="image/*,.pdf" class="hidden" onchange="updateProofFileList()">
                        
                        <div class="form-group">
                            <label for="request_type" class="block text-gray-700 text-sm font-bold mb-2">Request Type:</label>
                            <select id="request_type" name="request_type" class="form-control w-full" onchange="showAdditionalFields()">
                                <option value="">Select Request Type</option>
                                <!-- Document and Certification Requests -->
                                <optgroup label="Document and Certification Requests">
                                    <option value="Barangay Clearance">Barangay Clearance (For employment, business permits, or personal transactions)</option>
                                    <option value="Certificate of Residency">Certificate of Residency (Proof of residence)</option>
                                    <option value="Certificate of Indigency">Certificate of Indigency (For financial assistance, scholarships, or medical help)</option>
                                    <option value="Certificate of Good Moral Character">Certificate of Good Moral Character (For job applications or school)</option>
                                    <option value="Blotter Request Copy">Blotter Request Copy (For incidents recorded in the barangay logbook)</option>
                                </optgroup>

                                <!-- Community and Infrastructure Requests -->
                                <optgroup label="Community and Infrastructure Requests">
                                    <option value="Streetlight Repair">Streetlight Repair or Installation</option>
                                    <option value="Road Maintenance">Road or drainage maintenance</option>
                                    <option value="Garbage Collection">Garbage collection scheduling</option>
                                    <option value="CCTV Installation">Installation of CCTV in public areas</option>
                                    <option value="Noise Enforcement">Noise or curfew enforcement</option>
                                </optgroup>

                                <!-- Personal or Community Mediation Requests -->
                                <optgroup label="Personal or Community Mediation Requests">
                                    <option value="Dispute Mediation">Request for mediation on disputes (Neighbor, family, tenant/landlord issues)</option>
                                    <option value="Domestic Assistance">Request for assistance in domestic issues</option>
                                </optgroup>

                                <!-- Social Assistance or Support -->
                                <optgroup label="Social Assistance or Support">
                                    <option value="Medical Assistance">Medical or burial assistance</option>
                                    <option value="Educational Support">Educational support referrals</option>
                                    <option value="Livelihood Aid">Livelihood or relief aid</option>
                                    <option value="Feeding Program">Feeding programs or health missions</option>
                                </optgroup>

                                <!-- Other Requests -->
                                <option value="Other">Other (Please specify below)</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="details" class="block text-gray-700 text-sm font-bold mb-2">Details:</label>
                            <div class="space-y-4">
                                <textarea id="details" name="details" rows="5" class="form-control w-full" placeholder="Enter request details" required></textarea>
                                
                                <!-- Additional fields for specific request types -->
                                <div id="additionalFields" class="mt-4" style="display: none;">
                                    <div id="certificateFields" class="space-y-4" style="display: none;">
                                        <div class="form-group">
                                            <label for="purpose" class="block text-gray-700 text-sm font-bold mb-2">Purpose:</label>
                                            <input type="text" id="purpose" name="purpose" class="form-control w-full" placeholder="Specify the purpose of the certificate">
                                        </div>
                                        <div class="form-group">
                                            <label for="validity" class="block text-gray-700 text-sm font-bold mb-2">Validity Period:</label>
                                            <input type="number" id="validity" name="validity" class="form-control w-full" placeholder="Number of days">
                                        </div>
                                    </div>

                                    <div id="infrastructureFields" class="space-y-4" style="display: none;">
                                        <div class="form-group">
                                            <label for="location" class="block text-gray-700 text-sm font-bold mb-2">Location:</label>
                                            <input type="text" id="location" name="location" class="form-control w-full" placeholder="Specify the location">
                                        </div>
                                        <div class="form-group">
                                            <label for="urgency" class="block text-gray-700 text-sm font-bold mb-2">Urgency Level:</label>
                                            <select id="urgency" name="urgency" class="form-control w-full">
                                                <option value="low">Low</option>
                                                <option value="medium">Medium</option>
                                                <option value="high">High</option>
                                            </select>
                                        </div>
                                    </div>

                                    <div id="mediationFields" class="space-y-4" style="display: none;">
                                        <div class="form-group">
                                            <label for="parties" class="block text-gray-700 text-sm font-bold mb-2">Involved Parties:</label>
                                            <textarea id="parties" name="parties" rows="3" class="form-control w-full" placeholder="List all involved parties"></textarea>
                                        </div>
                                        <div class="form-group">
                                            <label for="mediation_date" class="block text-gray-700 text-sm font-bold mb-2">Preferred Date:</label>
                                            <input type="date" id="mediation_date" name="mediation_date" class="form-control w-full">
                                        </div>
                                    </div>

                                    <div id="assistanceFields" class="space-y-4" style="display: none;">
                                        <div class="form-group">
                                            <label for="assistance_type" class="block text-gray-700 text-sm font-bold mb-2">Type of Assistance Needed:</label>
                                            <textarea id="assistance_type" name="assistance_type" rows="3" class="form-control w-full" placeholder="Specify the type of assistance needed"></textarea>
                                        </div>
                                        <div class="form-group">
                                            <label for="beneficiaries" class="block text-gray-700 text-sm font-bold mb-2">Number of Beneficiaries:</label>
                                            <input type="number" id="beneficiaries" name="beneficiaries" class="form-control w-full" placeholder="Number of people who will benefit">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="attachments" class="block text-gray-700 text-sm font-bold mb-2">Attachments (optional):</label>
                            <div class="file-upload-container border-2 border-dashed border-gray-300 rounded-lg p-6 text-center hover:border-blue-500 transition-colors cursor-pointer bg-gray-50" id="dropZone">
                                <input type="file" id="attachments" name="attachments[]" multiple class="hidden" onchange="updateFileList()">
                                <div class="flex flex-col items-center">
                                    <i class="fas fa-cloud-upload-alt text-3xl text-gray-400 mb-2"></i>
                                    <p class="text-gray-600 mb-1">Drag and drop files here or</p>
                                    <button type="button" class="text-blue-500 font-medium hover:text-blue-700" onclick="document.getElementById('attachments').click()">Browse files</button>
                                    <p class="text-xs text-gray-500 mt-2">Supported formats: PDF, JPG, PNG, DOC, DOCX</p>
                                </div>
                            </div>
                            <div id="fileList" class="mt-3 space-y-2 hidden">
                                <!-- Selected files will appear here -->
                            </div>
                        </div>

                        <div class="flex items-center justify-between">
                            <button type="button" class="btn-primary" onclick="showPaymentModal()">
                                <i class="fas fa-paper-plane mr-2"></i> Submit Request
                            </button>
                            <a href="user_dashboard.php" class="text-blue-600 hover:text-blue-800 text-sm">
                                <i class="fas fa-arrow-left mr-2"></i> Back to Dashboard
                            </a>
                        </div>
                    </form>

                    <!-- Payment Modal -->
                    <div class="payment-modal" id="paymentModal">
                        <div class="payment-modal-content">
                            <div class="payment-modal-header">
                                <h2 class="text-lg font-bold text-gray-800">Payment Details</h2>
                                <span class="modal-close" onclick="closePaymentModal()">&times;</span>
                            </div>
                            
                            <div class="payment-modal-body overflow-y-auto max-h-[70vh] p-4">
                                <div class="payment-info">
                                    <p class="text-base font-semibold text-gray-800 mb-2">Payment Amount: <span id="paymentAmount">₱0.00</span></p>
                                    <p class="text-gray-600 text-sm">Please complete the payment using GCash</p>
                                    
                                    <div class="gcash-qr-container mt-3">
                                        <img src="uploads/gcash/gcash.jfif" alt="GCash QR Code" class="gcash-qr w-48 h-48 object-contain" id="gcashQR">
                                        <p class="text-xs text-gray-500 mt-1">Barangay Official GCash Account</p>
                                    </div>
                                    
                                    <div class="payment-reference mt-3">
                                        <p class="font-medium text-gray-800">Gcash Number: 09708228108</span</p>
                                        <p class="font-medium text-gray-800">Reference Number: <span id="paymentReference">1234567890</span></p>
                                    </div>
                                </div>
                                
                                <div class="payment-instructions mt-4">
                                    <h3 class="text-xs font-semibold text-gray-700 mb-1">Payment Instructions:</h3>
                                    <ol class="list-decimal list-inside text-xs text-gray-600">
                                        <li>Scan the GCash QR code using your GCash app</li>
                                        <li>Enter the exact amount shown above</li>
                                        <li>Use the reference number when making the payment</li>
                                        <li>Upload screenshot of your payment confirmation</li>
                                        <li>Admin will verify your payment after submission</li>
                                    </ol>
                                    <p class="mt-1 text-xs text-yellow-600 font-medium">Note: Your request will remain in "Pending" status until admin verifies your payment proof.</p>
                                </div>
                                
                                <!-- Proof of Payment Upload -->
                                <div class="mt-4">
                                    <label class="block text-xs font-medium text-gray-700 mb-1">Proof of Payment (Required)</label>
                                    <div class="file-upload-container border-2 border-dashed border-gray-300 rounded-lg p-3 text-center hover:border-blue-500 transition-colors cursor-pointer bg-gray-50">
                                        <div class="flex flex-col items-center">
                                            <i class="fas fa-camera text-2xl text-gray-400 mb-1"></i>
                                            <p class="text-gray-600 text-sm mb-1">Upload screenshot of GCash payment</p>
                                            <button type="button" class="text-blue-500 font-medium text-sm hover:text-blue-700" onclick="document.getElementById('proof_of_payment').click()">Browse files</button>
                                            <p class="text-xs text-gray-500 mt-1">Supported formats: JPG, PNG, PDF</p>
                                        </div>
                                    </div>
                                    <div id="proofFileList" class="mt-2 text-xs text-gray-600 hidden"></div>
                                </div>
                            </div>
                            
                            <div class="payment-modal-footer p-4">
                                <button type="button" class="btn-primary w-full text-sm" onclick="validatePayment()" id="confirmPaymentBtn">
                                    <i class="fas fa-check mr-2"></i> Confirm Payment
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Payment-related variables
        let paymentAmount = 0;
        let paymentReference = '';
        
        // File upload handling
        document.addEventListener('DOMContentLoaded', function() {
            const dropZone = document.getElementById('dropZone');
            const fileInput = document.getElementById('attachments');
            
            // Prevent default drag behaviors
            ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
                dropZone.addEventListener(eventName, preventDefaults, false);
                document.body.addEventListener(eventName, preventDefaults, false);
            });
            
            // Highlight drop zone when item is dragged over it
            ['dragenter', 'dragover'].forEach(eventName => {
                dropZone.addEventListener(eventName, highlight, false);
            });
            
            ['dragleave', 'drop'].forEach(eventName => {
                dropZone.addEventListener(eventName, unhighlight, false);
            });
            
            // Handle dropped files
            dropZone.addEventListener('drop', handleDrop, false);
            
            function preventDefaults(e) {
                e.preventDefault();
                e.stopPropagation();
            }
            
            function highlight() {
                dropZone.classList.add('border-blue-500');
                dropZone.classList.add('bg-blue-50');
            }
            
            function unhighlight() {
                dropZone.classList.remove('border-blue-500');
                dropZone.classList.remove('bg-blue-50');
            }
            
            function handleDrop(e) {
                const dt = e.dataTransfer;
                const files = dt.files;
                fileInput.files = files;
                updateFileList();
            }
        });
        
        function updateFileList() {
            const fileInput = document.getElementById('attachments');
            const fileList = document.getElementById('fileList');
            
            // Clear previous file list
            fileList.innerHTML = '';
            
            if (fileInput.files.length > 0) {
                fileList.classList.remove('hidden');
                
                // Create file items
                for (let i = 0; i < fileInput.files.length; i++) {
                    const file = fileInput.files[i];
                    const fileSize = formatFileSize(file.size);
                    const fileItem = document.createElement('div');
                    
                    fileItem.className = 'flex items-center justify-between bg-white p-3 rounded-lg shadow-sm';
                    fileItem.innerHTML = `
                        <div class="flex items-center">
                            <i class="fas fa-file-alt text-blue-500 mr-3"></i>
                            <div>
                                <p class="text-sm font-medium text-gray-700">${file.name}</p>
                                <p class="text-xs text-gray-500">${fileSize}</p>
                            </div>
                        </div>
                        <button type="button" class="text-red-500 hover:text-red-700" onclick="removeFile(${i})">
                            <i class="fas fa-times"></i>
                        </button>
                    `;
                    
                    fileList.appendChild(fileItem);
                }
            } else {
                fileList.classList.add('hidden');
            }
        }
        
        function removeFile(index) {
            const fileInput = document.getElementById('attachments');
            const dt = new DataTransfer();
            
            // Add all files except the one to remove
            for (let i = 0; i < fileInput.files.length; i++) {
                if (i !== index) {
                    dt.items.add(fileInput.files[i]);
                }
            }
            
            // Update the file input with the new file list
            fileInput.files = dt.files;
            updateFileList();
        }
        
        function formatFileSize(bytes) {
            if (bytes === 0) return '0 Bytes';
            const k = 1024;
            const sizes = ['Bytes', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
        }
        
        function calculatePaymentAmount() {
            const requestType = document.getElementById("request_type").value;
            let paymentAmount = 0;

            switch(requestType) {
                case 'Barangay Clearance':
                    paymentAmount = 150;
                    break;
                case 'Certificate of Residency':
                    paymentAmount = 100;
                    break;
                case 'Certificate of Indigency':
                    paymentAmount = 50;
                    break;
                case 'Certificate of Good Moral Character':
                    paymentAmount = 75;
                    break;
                case 'Blotter Request Copy':
                    paymentAmount = 50;
                    break;
                case 'Streetlight Repair':
                case 'Road Maintenance':
                    paymentAmount = 500;
                    break;
                case 'Dispute Mediation':
                case 'Domestic Assistance':
                    paymentAmount = 300;
                    break;
                case 'Medical Assistance':
                case 'Educational Support':
                    paymentAmount = 400;
                    break;
                default:
                    paymentAmount = 100;
            }

            document.getElementById("paymentAmount").textContent = `₱${paymentAmount.toFixed(2)}`;
            document.getElementById("payment_amount_field").value = paymentAmount;
            
            // Generate reference
            paymentReference = `BRGY-${Date.now()}`;
            document.getElementById("paymentReference").textContent = paymentReference;
            document.getElementById("payment_reference_field").value = paymentReference;
        }

        function validateForm() {
            let requestType = document.getElementById("request_type").value;
            let details = document.getElementById("details").value;
            let isValid = true;
            let errorMessage = "";

            // Basic validation
            if (requestType === "") {
                errorMessage += "Please select a request type.\n";
                isValid = false;
            }

            if (details === "") {
                errorMessage += "Please provide request details.\n";
                isValid = false;
            }

            // Additional field validation based on request type
            if (requestType) {
                if (requestType.includes("Certificate")) {
                    let purpose = document.getElementById("purpose").value;
                    if (purpose === "") {
                        errorMessage += "Please specify the purpose of the certificate.\n";
                        isValid = false;
                    }
                } else if (requestType.includes("Streetlight") || requestType.includes("Road") || requestType.includes("Garbage") || requestType.includes("CCTV") || requestType.includes("Noise")) {
                    let location = document.getElementById("location").value;
                    if (location === "") {
                        errorMessage += "Please specify the location.\n";
                        isValid = false;
                    }
                } else if (requestType.includes("Dispute") || requestType.includes("Domestic")) {
                    let parties = document.getElementById("parties").value;
                    if (parties === "") {
                        errorMessage += "Please list all involved parties.\n";
                        isValid = false;
                    }
                } else if (requestType.includes("Medical") || requestType.includes("Educational") || requestType.includes("Livelihood") || requestType.includes("Feeding")) {
                    let assistanceType = document.getElementById("assistance_type").value;
                    if (assistanceType === "") {
                        errorMessage += "Please specify the type of assistance needed.\n";
                        isValid = false;
                    }
                }
            }

            if (!isValid) {
                alert(errorMessage);
            }
            
            return isValid;
        }

        function showPaymentModal() {
            if (!validateForm()) {
                return;
            }
            
            calculatePaymentAmount();
            document.getElementById("paymentModal").classList.add("active");
        }

        function closePaymentModal() {
            document.getElementById("paymentModal").classList.remove("active");
        }

        function validatePayment() {
            const proofInput = document.getElementById('proof_of_payment');
            
            if (!proofInput.files || proofInput.files.length === 0) {
                alert('Please upload proof of payment before confirming');
                return;
            }
            
            // Validate payment amount
            const paymentAmount = parseFloat(document.getElementById('payment_amount_field').value);
            if (isNaN(paymentAmount) || paymentAmount <= 0) {
                alert('Invalid payment amount');
                return;
            }
            
            // Get the current CSRF token from the meta tag
            const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
            // Update the form's CSRF token with the current one
            document.getElementById('csrf_token').value = csrfToken;
            
            // Show loading state on button
            const confirmButton = document.getElementById('confirmPaymentBtn');
            confirmButton.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Processing...';
            confirmButton.disabled = true;
            
            // Submit the form
            document.getElementById('requestForm').submit();
        }

        function showAdditionalFields() {
            const requestType = document.getElementById("request_type").value;
            const additionalFields = document.getElementById("additionalFields");
            
            // Hide all fields first
            document.getElementById("certificateFields").style.display = "none";
            document.getElementById("infrastructureFields").style.display = "none";
            document.getElementById("mediationFields").style.display = "none";
            document.getElementById("assistanceFields").style.display = "none";

            // Show appropriate fields based on request type
            if (requestType) {
                additionalFields.style.display = "block";
                
                if (requestType.includes("Certificate")) {
                    document.getElementById("certificateFields").style.display = "block";
                } else if (requestType.includes("Streetlight") || requestType.includes("Road") || requestType.includes("Garbage") || requestType.includes("CCTV") || requestType.includes("Noise")) {
                    document.getElementById("infrastructureFields").style.display = "block";
                } else if (requestType.includes("Dispute") || requestType.includes("Domestic")) {
                    document.getElementById("mediationFields").style.display = "block";
                } else if (requestType.includes("Medical") || requestType.includes("Educational") || requestType.includes("Livelihood") || requestType.includes("Feeding")) {
                    document.getElementById("assistanceFields").style.display = "block";
                }
            } else {
                additionalFields.style.display = "none";
            }
        }

        // Proof of payment handling
        function updateProofFileList() {
            const fileInput = document.getElementById('proof_of_payment');
            const fileList = document.getElementById('proofFileList');
            
            if (fileInput.files.length > 0) {
                fileList.classList.remove('hidden');
                fileList.innerHTML = `
                    <div class="flex items-center justify-between bg-white p-2 rounded-lg shadow-sm">
                        <div class="flex items-center">
                            <i class="fas fa-file-image text-green-500 mr-2"></i>
                            <span class="text-sm">${fileInput.files[0].name}</span>
                        </div>
                        <button type="button" class="text-red-500 hover:text-red-700" onclick="removeProofFile()">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                `;
            } else {
                fileList.classList.add('hidden');
                fileList.innerHTML = '';
            }
        }
        
        function removeProofFile() {
            document.getElementById('proof_of_payment').value = '';
            document.getElementById('proofFileList').classList.add('hidden');
            document.getElementById('proofFileList').innerHTML = '';
        }
    </script>
</body>
</html>