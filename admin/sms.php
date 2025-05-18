<?php 
// Start session and check admin access
session_start();
require_once '../baby_capstone_connection.php';

try {
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    // Test the connection
    $pdo->query("SELECT 1");
} catch (PDOException $e) {
    error_log("Database connection error: " . $e->getMessage());
    die("Database connection failed. Please check the server logs.");
}

// Get all users with phone numbers
try {
    $stmt = $pdo->prepare("SELECT id, full_name, phone FROM users WHERE phone IS NOT NULL AND phone != '' AND status = 'active' ORDER BY full_name");
    $stmt->execute();
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error fetching users: " . $e->getMessage());
    $_SESSION['error'] = "Error fetching users for selection: " . $e->getMessage();
    header('Location: sms.php');
    exit;
}

// Handle SMS sending
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $userId = $_POST['user_id'];
    $message = $_POST['message'];

    // Ensure admin_id is available from the session
    $adminId = $_SESSION['admin_id'] ?? null; // Assuming admin ID is stored here

    // Validate inputs, including adminId
    if (empty($userId) || empty($message)) {
        $_SESSION['error'] = "Please select a user and enter a message.";
        header('Location: sms.php');
        exit;
    }
    
    if ($adminId === null || (int)$adminId <= 0) { // Added check for valid adminId integer
         $_SESSION['error'] = "Administrator not properly logged in. Please log in again.";
         header('Location: sms.php');
         exit;
    }


    // Sanitize message
    $message = preg_replace('/[^\x20-\x7E]/', '', $message); // Only printable ASCII

    // Get user details - Ensure user exists and is active before attempting to send
    try {
        $stmt = $pdo->prepare("SELECT id, full_name, phone FROM users WHERE id = ? AND phone IS NOT NULL AND phone != '' AND status = 'active'");
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error fetching user details for SMS send: " . $e->getMessage());
        $_SESSION['error'] = "Error fetching user details for sending: " . $e->getMessage();
        header('Location: sms.php');
        exit;
    }

    if ($user) {
        // Format phone number
        $raw_number = preg_replace('/[^0-9]/', '', $user['phone']);
        if (substr($raw_number, 0, 1) === '0') {
            $phone_number = '+63' . substr($raw_number, 1);
        } elseif (substr($raw_number, 0, 2) === '63') {
            $phone_number = '+' . $raw_number;
        } elseif (substr($raw_number, 0, 3) !== '+63') {
            $phone_number = '+63' . $raw_number;
        } else {
            $phone_number = $raw_number;
        }

        // Prepare data for PhilSMS
        $send_data = [
            "sender_id" => "PhilSMS",
            "recipient" => $phone_number,
            "message" => $message
        ];
        // NOTE: Replace with your actual PhilSMS token
        $token = ""; 

        // --- START PhilSMS API CALL (Intact as requested) ---
        try {
            $parameters = json_encode($send_data);
             if ($parameters === false || $parameters === null) {
                 error_log("JSON encoding failed for SMS data: " . json_last_error_msg());
                 $_SESSION['error'] = "Failed to prepare SMS data.";
                 header('Location: sms.php');
                 exit;
             }

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, "https://app.philsms.com/api/v3/sms/send");
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $parameters);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                "Content-Type: application/json",
                "Authorization: Bearer $token"
            ]);

            $response = curl_exec($ch);
            $http_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $result = json_decode($response, true);
            curl_close($ch);

            // Log full API response and status code
            error_log("PhilSMS API Response: " . json_encode($result) . " | HTTP Status: " . $http_status);

            $sms_status = 'failed'; // Default status
            $session_message = "Failed to send SMS: " . ($result['message'] ?? 'Unknown API error');


            if (isset($result['status']) && $result['status'] === 'success') {
                 $sms_status = 'sent';
                 $session_message = "SMS sent successfully to " . $user['full_name'];
            } else {
                 // Log more details if sending failed
                 error_log("PhilSMS sending failed for user ID: " . $userId . " | Response: " . json_encode($result));
                 if ($http_status !== 200) {
                     $session_message .= " (HTTP Status: " . $http_status . ")";
                 }
            }
        // --- END PhilSMS API CALL ---

            // --- START DATABASE INSERTION (Corrected) ---
            try {
                // Validate IDs before inserting - Important check!
                $validUserId = (int)$userId;
                $validAdminId = (int)$adminId;

                 if ($validUserId <= 0) {
                     error_log("SMS Log Insertion Skipped: Invalid user_id provided (" . $userId . ").");
                     $_SESSION['error'] = "Failed to log SMS history: Invalid user selected or user ID is 0."; // Set error here too
                 } elseif ($validAdminId <= 0) {
                     error_log("SMS Log Insertion Skipped: Invalid admin_id provided from session (" . ($adminId ?? 'null') . ")."); // Log 'null' if $adminId is null
                     $_SESSION['error'] = "Failed to log SMS history: Administrator ID missing or invalid (" . ($adminId ?? 'null') . "). Please log in again."; // Set error here too
                 } else {
                     // Proceed with database insert only if IDs are valid
                    // Corrected INSERT statement: Include user_id and admin_id, remove id
                    $stmt = $pdo->prepare("INSERT INTO sms_notifications (
                        user_id,          -- Correctly added user_id
                        admin_id,         -- Correctly added admin_id
                        type,             -- Correct column list order
                        recipients,
                        message,
                        status,
                        response,         -- Column for the 7th value provided
                        scheduled_time,   -- Using NOW() directly in SQL
                        created_at,       -- Using NOW() directly in SQL
                        updated_at        -- Using NOW() directly in SQL
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW(), NOW())"); // Correct number of placeholders (7)

                    $stmt->execute([
                        $validUserId, // Value for user_id
                        $validAdminId, // Value for admin_id
                        'individual', // Value for type (Ensure this matches your ENUM 'individual','bulk','urgent')
                        json_encode([$phone_number]),
                        $message,
                        $sms_status,
                        json_encode($result), // Value for response (Matches the 7th placeholder)
                    ]);

                    // Log successful database insertion
                    $lastInsertId = $pdo->lastInsertId();
                    error_log("SMS notification successfully logged to DB. Insert ID: " . $lastInsertId);
                    
                    // Set session message based on PhilSMS result (success/error) AFTER successful DB insert
                    if ($sms_status === 'sent') {
                         $_SESSION['success'] = $session_message;
                     } else {
                         $_SESSION['error'] = $session_message; // Use the PhilSMS error message
                     }
                 }

            } catch (PDOException $e) {
                // Log detailed database error information
                $dbErrorMessage = "Database Error: " . $e->getMessage() . " | SQLSTATE: " . $e->getCode();
                error_log("Error inserting SMS notification into DB: " . $dbErrorMessage);
                error_log("Data attempted: " . json_encode([
                    'user_id' => (int)$userId, // Log casted values
                    'admin_id' => (int)$adminId, // Log casted values
                    'status' => $sms_status,
                    'message_snippet' => substr($message, 0, 50),
                    'recipients' => $phone_number,
                    'response_snippet' => substr(json_encode($result), 0, 100)
                ]));

                // Ensure an error message is set if DB insert failed, potentially overwriting a PhilSMS success message
                $_SESSION['error'] = ($sms_status === 'sent' ? "SMS sent but failed to log: " : "Failed to send/log SMS: ") . $dbErrorMessage;

            }
        // --- END DATABASE INSERTION ---

        } catch (Exception $e) {
            error_log("General Error during SMS sending process: " . $e->getMessage());
            $_SESSION['error'] = "Failed to send SMS due to an unexpected system error. Please check server logs.";
        }
    } else {
        $_SESSION['error'] = "Selected user not found or does not have a valid phone number.";
    }

    // Redirect after processing, ensuring session messages are carried over
    header('Location: sms.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SMS Dashboard - Barangay System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .sidebar {
            transition: all 0.3s ease;
        }
        .sms-status-sent {
            background-color: #dcfce7;
            color: #15803d;
        }
        .sms-status-failed {
            background-color: #fee2e2;
            color: #b91c1c;
        }
        .sms-status-pending {
            background-color: #fef3c7;
            color: #d97706;
        }
        .sms-type-bulk {
            background-color: #dbeafe;
            color: #1d4ed8;
        }
        .sms-type-individual {
            background-color: #ede9fe;
            color: #7c3aed;
        }
        .sms-type-urgent {
            background-color: #fce7f3;
            color: #be185d;
        }
    </style>
</head>
<body class="bg-gray-100">
    <div class="flex h-screen overflow-hidden">
        <!-- Sidebar -->
        <?php include 'sidebar.php' ?>

        <!-- Main Content -->
        <div class="flex-1 overflow-auto">
            <!-- Top Navigation -->
            <?php include 'header.php' ?>

            <!-- SMS Dashboard Content -->
            <main class="p-4 md:p-6">
                <!-- Page Header -->
                <div class="flex justify-between items-center mb-6">
                    <h1 class="text-2xl font-bold text-gray-800">SMS Notifications</h1>
                </div>

                <!-- Notifications -->
                <?php if (isset($_SESSION['success'])): ?>
                    <div class="mb-4 p-4 bg-green-100 border border-green-400 text-green-700 rounded relative" role="alert">
                        <span class="block sm:inline"><?php echo htmlspecialchars($_SESSION['success']); ?></span>
                        <button type="button" class="absolute top-0 bottom-0 right-0 px-4 py-3" onclick="this.parentElement.style.display='none'">
                            <span class="sr-only">Close</span>
                            <svg class="h-6 w-6 text-green-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                    <?php unset($_SESSION['success']); ?>
                <?php endif; ?>

                <?php if (isset($_SESSION['error'])): ?>
                    <div class="mb-4 p-4 bg-red-100 border border-red-400 text-red-700 rounded relative" role="alert">
                        <span class="block sm:inline"><?php echo htmlspecialchars($_SESSION['error']); ?></span>
                        <button type="button" class="absolute top-0 bottom-0 right-0 px-4 py-3" onclick="this.parentElement.style.display='none'">
                            <span class="sr-only">Close</span>
                            <svg class="h-6 w-6 text-red-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                    <?php unset($_SESSION['error']); ?>
                <?php endif; ?>

                <!-- SMS Sending Form -->
                <div class="bg-white rounded-lg shadow p-4 mb-6">
                    <h3 class="text-lg font-medium text-gray-700 mb-4">Send New SMS</h3>
                    <form method="POST" class="space-y-4">
                        <div>
                            <label for="user_id" class="block text-sm font-medium text-gray-700">Select Recipient:</label>
                            <select name="user_id" id="user_id" required class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                                <option value="">Select recipient...</option>
                                <?php 
                                try {
                                    // Get all users with phone numbers
                                    $stmt = $pdo->prepare("SELECT id, full_name, phone FROM users WHERE phone IS NOT NULL AND phone != '' AND status = 'active' ORDER BY full_name");
                                    $stmt->execute();
                                    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

                                    foreach ($users as $user): 
                                        // Format phone number
                                        $formatted_phone = preg_replace('/[^0-9]/', '', $user['phone']);
                                        if (substr($formatted_phone, 0, 1) === '0') {
                                            $display_phone = '+63' . substr($formatted_phone, 1);
                                        } elseif (substr($formatted_phone, 0, 2) === '63') {
                                            $display_phone = '+' . $formatted_phone;
                                        } else {
                                            $display_phone = '+63' . $formatted_phone;
                                        }
                                ?>
                                <option value="<?php echo htmlspecialchars($user['id']); ?>">
                                    <?php echo htmlspecialchars($user['full_name']); ?> - <?php echo htmlspecialchars($display_phone); ?>
                                </option>
                                <?php 
                                    endforeach;
                                } catch (PDOException $e) {
                                    error_log("Error fetching users: " . $e->getMessage());
                                    echo '<option value="">Error loading users. Please refresh the page.</option>';
                                }
                            ?>
                            </select>
                        </div>
                        <div>
                            <label for="message" class="block text-sm font-medium text-gray-700">Message:</label>
                            <textarea name="message" id="message" required placeholder="Enter your message here..." maxlength="160" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm"></textarea>
                        </div>
                        <div class="flex justify-end">
                            <button type="submit" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                <i class="fas fa-paper-plane mr-2"></i> Send SMS
                            </button>
                        </div>
                    </form>
                </div>

                <!-- SMS History Table -->
                <div class="bg-white shadow rounded-lg overflow-hidden">
                    <div class="px-4 py-3 border-b border-gray-200">
                        <h3 class="text-sm font-medium text-gray-700">SMS History</h3>
                    </div>
                    <div class="overflow-x-auto">
                        <table id="smsTable" class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Recipient</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Message</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date Sent</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php
                                try {
                                    // Get recent SMS notifications with admin and user names
                                    // Corrected JOIN condition for user table
                                    $stmt = $pdo->prepare("SELECT 
                                        sn.id,
                                        sn.type,
                                        sn.recipients,
                                        sn.message,
                                        sn.status,
                                        sn.response,
                                        sn.scheduled_time,
                                        sn.created_at,
                                        sn.updated_at,
                                        u.full_name as user_full_name, -- Fetch user's full name
                                        u.phone as user_phone,        -- Fetch user's phone number directly
                                        a.full_name as admin_full_name  -- Fetch admin's full name
                                        FROM sms_notifications sn
                                        LEFT JOIN users u ON sn.user_id = u.id  -- Corrected JOIN condition
                                        LEFT JOIN users a ON sn.admin_id = a.id  -- Join to get admin info
                                        ORDER BY sn.created_at DESC
                                        LIMIT 50");
                                    
                                    $stmt->execute();
                                    $smsLogs = $stmt->fetchAll(PDO::FETCH_ASSOC);

                                    if (empty($smsLogs)) {
                                        echo '<tr><td colspan="4" class="px-6 py-4 text-sm text-gray-500 text-center">No SMS notifications found.</td></tr>';
                                    } else {
                                        foreach ($smsLogs as $log) { 
                                            // Format the status class
                                            $statusClass = '';
                                            switch($log['status']) {
                                                case 'sent':
                                                    $statusClass = 'bg-green-100 text-green-800';
                                                    break;
                                                case 'failed':
                                                    $statusClass = 'bg-red-100 text-red-800';
                                                    break;
                                                case 'pending':
                                                    $statusClass = 'bg-yellow-100 text-yellow-800';
                                                    break;
                                                default:
                                                    $statusClass = 'bg-gray-100 text-gray-800'; // Handle unexpected statuses
                                            }
                                    ?>
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?php 
                                        // Format and display recipient information
                                        // Prioritize user_full_name from join, fallback to 'Unknown User'
                                        $display_name = htmlspecialchars($log['user_full_name'] ?? 'Unknown User');
                                        
                                        // Prioritize user_phone from join
                                        $display_phone = htmlspecialchars($log['user_phone'] ?? '');

                                        // If user_phone is not available from the join, try parsing recipients (less reliable)
                                        if (empty($log['user_phone']) && !empty($log['recipients'])) {
                                            $stored_numbers = json_decode($log['recipients'], true);
                                            if (is_array($stored_numbers) && !empty($stored_numbers)) {
                                                // Take the first number from the stored array
                                                $display_phone = htmlspecialchars(reset($stored_numbers));
                                            } else {
                                                // Handle non-array or single stored value
                                                $display_phone = htmlspecialchars($log['recipients']);
                                            }
                                        }

                                        // Ensure the displayed phone number starts with +63 format if possible
                                        if (!empty($display_phone) && $display_phone !== 'N/A') {
                                            $clean_num = preg_replace('/[^0-9]/', '', $display_phone);
                                            if (substr($clean_num, 0, 1) === '0') {
                                                $display_phone = '+63' . substr($clean_num, 1);
                                            } elseif (substr($clean_num, 0, 2) === '63') {
                                                $display_phone = '+' . $clean_num;
                                            } elseif (substr($clean_num, 0, 3) !== '+63' && !empty($clean_num)) {
                                                 $display_phone = '+63' . $clean_num;
                                            } else {
                                                $display_phone = '+' . $clean_num; // Already in +63 format or similar
                                            }
                                            $display_phone = ' - ' . htmlspecialchars($display_phone);
                                        } else {
                                            $display_phone = " - N/A";
                                        }


                                        // Display recipient information including admin who sent it
                                        echo $display_name . 
                                             $display_phone .
                                             (!empty($log['admin_full_name']) ? ' (by ' . htmlspecialchars($log['admin_full_name']) . ')' : '');
                                        ?>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-500">
                                            <?php echo nl2br(htmlspecialchars($log['message'])); // Use nl2br to preserve line breaks ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo $statusClass; ?>">
                                            <?php echo ucfirst($log['status']); ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?php echo date('M d, Y H:i', strtotime($log['created_at'])); ?>
                                    </td>
                                </tr>
                                    <?php 
                                        }
                                    }
                                } catch (PDOException $e) {
                                    // Log the database error
                                    error_log("Error fetching SMS logs from DB: " . $e->getMessage());
                                    // Display a user-friendly error message on the page
                                    echo '<tr><td colspan="4" class="px-6 py-4 text-sm text-red-700 bg-red-100 text-center">Error loading SMS history: ' . htmlspecialchars($e->getMessage()) . '</td></tr>';
                                }
                            ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </main>
        </div>
    </div>
</body>
</html>