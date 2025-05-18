<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    die('Unauthorized');
}

// DB connection
require_once 'baby_capstone_connection.php';

// Get parameters
$type = $_GET['type'] ?? '';
$id = $_GET['id'] ?? 0;

// Validate parameters
if (!in_array($type, ['complaint', 'request']) || !is_numeric($id)) {
    http_response_code(400);
    die('Invalid parameters');
}

// Get submission details based on type
if ($type === 'complaint') {
    $stmt = $pdo->prepare("SELECT * FROM complaints WHERE id = ? AND user_id = ?");
} else {
    $stmt = $pdo->prepare("SELECT * FROM requests WHERE id = ? AND user_id = ?");
}

$stmt->execute([$id, $_SESSION['user_id']]);
$submission = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$submission) {
    http_response_code(404);
    die('Submission not found');
}

// Prepare response
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

// Format dates
$createdAt = date('M d, Y h:i A', strtotime($submission['created_at']));
$updatedAt = isset($submission['updated_at']) ? date('M d, Y h:i A', strtotime($submission['updated_at'])) : 'N/A';

// Output HTML
?>
<div class="space-y-4">
    <div class="flex justify-between items-center">
        <h4 class="text-lg font-medium text-gray-900"><?= htmlspecialchars($submission['type'] ?? 'Submission') ?></h4>
        <span class="status-badge <?= $statusClass ?>">
            <i class="fas <?= $statusIcon ?> mr-1"></i>
            <?= ucfirst($status) ?>
        </span>
    </div>
    
    <?php if ($type === 'request' && isset($submission['payment_status'])): ?>
    <div>
        <h5 class="text-sm font-medium text-gray-700 mb-1">Payment Status</h5>
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
            <?= ucfirst($paymentStatus) ?>
        </span>
        
        <?php if (isset($submission['payment_amount'])): ?>

        <?php endif; ?>
    </div>
    <?php endif; ?>
    
    <div>
        <h5 class="text-sm font-medium text-gray-700 mb-1">Details</h5>
        <p class="text-gray-600"><?= nl2br(htmlspecialchars($submission['details'])) ?></p>
    </div>
    
    <?php if ($type === 'complaint' && isset($submission['location'])): ?>
    <div>
        <h5 class="text-sm font-medium text-gray-700 mb-1">Location</h5>
        <p class="text-gray-600"><?= htmlspecialchars($submission['location']) ?></p>
    </div>
    <?php endif; ?>
    
    <?php if (isset($submission['attachments']) && !empty($submission['attachments'])): ?>
    <div>
        <h5 class="text-sm font-medium text-gray-700 mb-1">Attachments</h5>
        <div class="grid grid-cols-2 gap-2">
            <?php 
            $attachments = json_decode($submission['attachments'], true);
            if (is_array($attachments)) {
                foreach ($attachments as $attachment): 
                    if (is_string($attachment)) {
                        $ext = pathinfo($attachment, PATHINFO_EXTENSION);
                        $icon = 'fa-file';
                        if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif'])) {
                            $icon = 'fa-file-image';
                        } elseif ($ext === 'pdf') {
                            $icon = 'fa-file-pdf';
                        } elseif (in_array($ext, ['doc', 'docx'])) {
                            $icon = 'fa-file-word';
                        }
                    } else {
                        $icon = 'fa-file';
                        $ext = 'unknown';
                    }
                    ?>
                    <a href="<?= htmlspecialchars($attachment) ?>" target="_blank" class="flex items-center p-2 border border-gray-200 rounded hover:bg-gray-50">
                        <i class="fas <?= $icon ?> text-blue-500 mr-2"></i>
                        <span class="text-sm text-gray-600 truncate"><?= htmlspecialchars(basename($attachment)) ?></span>
                    </a>
                <?php endforeach; 
            }
            ?>
        </div>
    </div>
    <?php endif; ?>
    
    <div class="grid grid-cols-2 gap-4">
        <div>
            <h5 class="text-sm font-medium text-gray-700 mb-1">Submitted On</h5>
            <p class="text-gray-600"><?= $createdAt ?></p>
        </div>
        <div>
            <h5 class="text-sm font-medium text-gray-700 mb-1">Last Updated</h5>
            <p class="text-gray-600"><?= $updatedAt ?></p>
        </div>
    </div>
</div>
