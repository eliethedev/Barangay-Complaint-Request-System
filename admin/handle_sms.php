<?php
require_once '../baby_capstone_connection.php';
require_once 'sms_functions.php';
require_once 'auth_check.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $response = ['success' => false, 'message' => 'Invalid action'];

    switch ($action) {
        case 'send':
            $type = $_POST['type'] ?? '';
            $recipients = $_POST['recipients'] ?? '';
            $message = $_POST['message'] ?? '';
            $scheduledTime = !empty($_POST['scheduled_time']) ? $_POST['scheduled_time'] : null;

            if (empty($type) || empty($recipients) || empty($message)) {
                $response = ['success' => false, 'message' => 'Missing required fields'];
                break;
            }

            $smsId = createSMSNotification($type, $recipients, $message, $scheduledTime);
            if ($smsId) {
                // Process each recipient
                $successCount = 0;
                $failedCount = 0;
                $failedNumbers = [];
                
                foreach ($recipients as $phone) {
                    // Send SMS to each recipient
                    $smsResult = sendSMS($phone, $message, $smsId);
                    
                    if ($smsResult['success']) {
                        $successCount++;
                    } else {
                        $failedCount++;
                        $failedNumbers[] = $phone;
                    }
                }

                // Update overall status
                if ($failedCount === 0) {
                    updateSMSStatus($smsId, 'Sent', "Successfully sent to $successCount recipients");
                    $response = ['success' => true, 'message' => 'All SMS sent successfully'];
                } else if ($successCount > 0) {
                    updateSMSStatus($smsId, 'Partial', "Sent to $successCount recipients, failed to send to $failedCount numbers");
                    $response = ['success' => true, 'message' => "Partial success: Sent to $successCount recipients, failed to send to $failedCount numbers"];
                } else {
                    updateSMSStatus($smsId, 'Failed', "Failed to send to all $failedCount numbers: " . implode(', ', $failedNumbers));
                    $response = ['success' => false, 'message' => "Failed to send to all numbers: " . implode(', ', $failedNumbers)];
                }
            }
            break;
            break;

        case 'get_recipients':
            $category = $_POST['category'] ?? '';
            $zone = $_POST['zone'] ?? '';

            if (!empty($category)) {
                $recipients = getRecipientsByCategory($category);
                $response = ['success' => true, 'recipients' => $recipients];
            } elseif (!empty($zone)) {
                $recipients = getRecipientsByZone($zone);
                $response = ['success' => true, 'recipients' => $recipients];
            } else {
                $response = ['success' => false, 'message' => 'Invalid recipient filter'];
            }
            break;

        case 'delete':
            $smsId = $_POST['sms_id'] ?? '';
            if (empty($smsId)) {
                $response = ['success' => false, 'message' => 'SMS ID is required'];
                break;
            }

            if (deleteSMSNotification($smsId)) {
                $response = ['success' => true, 'message' => 'SMS notification deleted successfully'];
            } else {
                $response = ['success' => false, 'message' => 'Failed to delete SMS notification'];
            }
            break;

        case 'resend':
            $smsId = $_POST['sms_id'] ?? '';
            if (empty($smsId)) {
                $response = ['success' => false, 'message' => 'SMS ID is required'];
                break;
            }

            // Here you would re-queue the message with your SMS gateway
            updateSMSStatus($smsId, 'Pending', 'Message re-queued for sending');
            $response = ['success' => true, 'message' => 'SMS notification queued for resending'];
            break;
    }

    echo json_encode($response);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = $_GET['action'] ?? '';
    $response = ['success' => false, 'message' => 'Invalid action'];

    switch ($action) {
        case 'list':
            $filters = [
                'type' => $_GET['type'] ?? '',
                'status' => $_GET['status'] ?? '',
                'date' => $_GET['date'] ?? ''
            ];

            $notifications = getSMSNotifications($filters);
            $response = ['success' => true, 'notifications' => $notifications];
            break;

        case 'details':
            $smsId = $_GET['sms_id'] ?? '';
            if (empty($smsId)) {
                $response = ['success' => false, 'message' => 'SMS ID is required'];
                break;
            }
            
            $smsDetails = getSMSDetails($smsId);
            if ($smsDetails) {
                $response = ['success' => true, 'details' => $smsDetails];
            } else {
                $response = ['success' => false, 'message' => 'SMS notification not found'];
            }
            break;
    }

    echo json_encode($response);
    exit;
}

// Invalid request method
echo json_encode(['success' => false, 'message' => 'Invalid request method']);
?>
