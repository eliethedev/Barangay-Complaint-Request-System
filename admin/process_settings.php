<?php
session_start();
require_once '../baby_capstone_connection.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'error' => 'Unauthorized'
    ]);
    exit;
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get JSON data
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!$data || !isset($data['type'])) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'Invalid request data'
        ]);
        exit;
    }

    $type = $data['type'];
    $formData = $data['data'] ?? [];
    
    try {
        // Start transaction
        $pdo->beginTransaction();
        
        // Validate required fields based on type
        $validationErrors = [];
        
        switch ($type) {
            case 'profile':
                $requiredFields = ['first-name', 'last-name', 'email'];
                foreach ($requiredFields as $field) {
                    if (!isset($formData[$field]) || empty($formData[$field])) {
                        $validationErrors[] = ucfirst(str_replace('-', ' ', $field)) . ' is required';
                    }
                }
                
                // Update profile settings
                $stmt = $pdo->prepare("UPDATE users SET 
                    first_name = :first_name, 
                    last_name = :last_name, 
                    email = :email, 
                    position = :position, 
                    phone = :phone 
                    WHERE id = :user_id");
                
                $stmt->execute([
                    'first_name' => $formData['first-name'],
                    'last_name' => $formData['last-name'],
                    'email' => $formData['email'],
                    'position' => $formData['position'] ?? '',
                    'phone' => $formData['phone'] ?? '',
                    'user_id' => $_SESSION['user_id']
                ]);
                break;
            
            case 'security':
                // Validate password requirements
                if (isset($formData['new-password']) && !empty($formData['new-password'])) {
                    if (strlen($formData['new-password']) < 8) {
                        $validationErrors[] = 'Password must be at least 8 characters long';
                    }
                    if ($formData['new-password'] !== $formData['confirm-password']) {
                        $validationErrors[] = 'Passwords do not match';
                    }
                }
                
                // Update security settings
                $stmt = $pdo->prepare("UPDATE users SET 
                    password = :password, 
                    two_factor_auth = :two_factor_auth 
                    WHERE id = :user_id");
                
                $stmt->execute([
                    'password' => isset($formData['new-password']) ? 
                        password_hash($formData['new-password'], PASSWORD_DEFAULT) : null,
                    'two_factor_auth' => $formData['two-factor-auth'] ? 1 : 0,
                    'user_id' => $_SESSION['user_id']
                ]);
                break;
            
            case 'system':
                // Validate required system settings
                $requiredFields = ['barangay-name', 'city-municipality', 'province', 'region'];
                foreach ($requiredFields as $field) {
                    if (!isset($formData[$field]) || empty($formData[$field])) {
                        $validationErrors[] = ucfirst(str_replace('-', ' ', $field)) . ' is required';
                    }
                }
                
                // Update system settings
                $stmt = $pdo->prepare("INSERT INTO settings (
                    user_id,
                    barangay_name,
                    city_municipality,
                    province,
                    region,
                    email_notifications,
                    sms_notifications,
                    push_notifications,
                    backup_frequency,
                    backup_location,
                    auto_backup
                ) VALUES (
                    :user_id,
                    :barangay_name,
                    :city_municipality,
                    :province,
                    :region,
                    :email_notifications,
                    :sms_notifications,
                    :push_notifications,
                    :backup_frequency,
                    :backup_location,
                    :auto_backup
                ) ON DUPLICATE KEY UPDATE
                    barangay_name = VALUES(barangay_name),
                    city_municipality = VALUES(city_municipality),
                    province = VALUES(province),
                    region = VALUES(region),
                    email_notifications = VALUES(email_notifications),
                    sms_notifications = VALUES(sms_notifications),
                    push_notifications = VALUES(push_notifications),
                    backup_frequency = VALUES(backup_frequency),
                    backup_location = VALUES(backup_location),
                    auto_backup = VALUES(auto_backup)");
                
                $stmt->execute([
                    'user_id' => $_SESSION['user_id'],
                    'barangay_name' => $formData['barangay-name'],
                    'city_municipality' => $formData['city-municipality'],
                    'province' => $formData['province'],
                    'region' => $formData['region'],
                    'email_notifications' => $formData['email-notifications'] ? 1 : 0,
                    'sms_notifications' => $formData['sms-notifications'] ? 1 : 0,
                    'push_notifications' => $formData['push-notifications'] ? 1 : 0,
                    'backup_frequency' => $formData['backup-frequency'] ?? 'weekly',
                    'backup_location' => $formData['backup-location'] ?? '',
                    'auto_backup' => $formData['auto-backup'] ? 1 : 0
                ]);
                break;
            
            case 'sms':
                // Validate SMS settings
                if (!isset($formData['sms-provider'])) {
                    $validationErrors[] = 'SMS provider is required';
                }
                
                // Update SMS settings
                $stmt = $pdo->prepare("INSERT INTO sms_settings (
                    user_id,
                    provider,
                    api_key,
                    sender_name,
                    account_sid,
                    auth_token,
                    phone_number,
                    enable_sms,
                    test_mode,
                    test_number
                ) VALUES (
                    :user_id,
                    :provider,
                    :api_key,
                    :sender_name,
                    :account_sid,
                    :auth_token,
                    :phone_number,
                    :enable_sms,
                    :test_mode,
                    :test_number
                ) ON DUPLICATE KEY UPDATE
                    provider = VALUES(provider),
                    api_key = VALUES(api_key),
                    sender_name = VALUES(sender_name),
                    account_sid = VALUES(account_sid),
                    auth_token = VALUES(auth_token),
                    phone_number = VALUES(phone_number),
                    enable_sms = VALUES(enable_sms),
                    test_mode = VALUES(test_mode),
                    test_number = VALUES(test_number)");
                
                $stmt->execute([
                    'user_id' => $_SESSION['user_id'],
                    'provider' => $formData['sms-provider'],
                    'api_key' => $formData['semaphore-api-key'] ?? '',
                    'sender_name' => $formData['semaphore-sender-name'] ?? '',
                    'account_sid' => $formData['twilio-account-sid'] ?? '',
                    'auth_token' => $formData['twilio-auth-token'] ?? '',
                    'phone_number' => $formData['twilio-phone-number'] ?? '',
                    'enable_sms' => $formData['enable-sms'] ? 1 : 0,
                    'test_mode' => $formData['sms-test-mode'] ? 1 : 0,
                    'test_number' => $formData['sms-test-number'] ?? ''
                ]);
                break;
        }
        
        // If there are validation errors, rollback and return them
        if (!empty($validationErrors)) {
            $pdo->rollBack();
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'errors' => $validationErrors
            ]);
            exit;
        }
        
        // Commit transaction
        $pdo->commit();
        
        // Return success response
        echo json_encode([
            'success' => true,
            'message' => 'Settings updated successfully'
        ]);
        
    } catch (Exception $e) {
        // Rollback transaction on error
        $pdo->rollBack();
        
        // Return error response
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => 'An error occurred while updating settings: ' . $e->getMessage()
        ]);
    }
} else {
    // Return 405 for non-POST requests
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'error' => 'Method not allowed'
    ]);
}
