<?php
namespace Barangay\Payment;

use PDO;

class PaymentProcessor {
    private $pdo;
    private $user_id;
    private $request_id;

    public function __construct($pdo, $user_id, $request_id = null) {
        $this->pdo = $pdo;
        $this->user_id = $user_id;
        $this->request_id = $request_id;
    }

    /**
     * Calculate payment amount based on request type
     * @param string $request_type
     * @return float
     */
    public function calculatePaymentAmount($request_type) {
        $payment_rules = [
            'Barangay Clearance' => 150,
            'Certificate of Residency' => 100,
            'Certificate of Indigency' => 50,
            'Certificate of Good Moral Character' => 75,
            'Blotter Request Copy' => 50,
            'Streetlight Repair' => 500,
            'Road Maintenance' => 500,
            'Dispute Mediation' => 300,
            'Domestic Assistance' => 300,
            'Medical Assistance' => 400,
            'Educational Support' => 400,
            'default' => 100
        ];

        return $payment_rules[$request_type] ?? $payment_rules['default'];
    }

    /**
     * Generate a unique payment reference number
     * @return string
     */
    public function generatePaymentReference() {
        return 'BRGY-' . date('YmdHis') . '-' . uniqid();
    }

    /**
     * Validate payment details
     * @param string $reference
     * @param float $amount
     * @return bool
     */
    public function validatePayment($reference, $amount) {
        if (empty($reference) || empty($amount)) {
            return false;
        }

        $stmt = $this->pdo->prepare("
            SELECT id FROM requests 
            WHERE payment_reference = ? 
            AND payment_status != 'paid'
        ");
        $stmt->execute([$reference]);
        return $stmt->fetch() !== false;
    }

    /**
     * Complete payment for a request
     * @param string $reference
     * @param float $amount
     * @return bool
     */
    public function completePayment($reference, $amount) {
        $stmt = $this->pdo->prepare("
            UPDATE requests SET 
                payment_status = 'paid', 
                payment_reference = ?, 
                payment_amount = ?, 
                updated_at = NOW() 
            WHERE id = ? AND user_id = ?
        ");
        
        return $stmt->execute([$reference, $amount, $this->request_id, $this->user_id]);
    }

    /**
     * Get request details
     * @param int $request_id
     * @return array|null
     */
    public function getRequestDetails($request_id) {
        $stmt = $this->pdo->prepare("
            SELECT * FROM requests 
            WHERE id = ? AND user_id = ?
        ");
        $stmt->execute([$request_id, $this->user_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Handle payment proof upload
     * @param int $request_id
     * @param array $proof_file
     * @return bool
     */
    public function handlePaymentProof($request_id, $proof_file) {
        if (empty($proof_file['name'])) {
            return false;
        }

        // Validate file type
        $allowed_types = ['image/jpeg', 'image/png', 'application/pdf'];
        if (!in_array($proof_file['type'], $allowed_types)) {
            throw new \Exception("Only JPG, PNG, or PDF files are allowed for proof of payment");
        }
        
        // Use a relative path from the root directory
        $upload_dir = 'payment_proofs/';
        
        // Log directory information for debugging
        error_log("Upload directory: " . $upload_dir);
        
        // Create directory if it doesn't exist
        if (!file_exists($upload_dir)) {
            if (!mkdir($upload_dir, 0777, true)) {
                error_log("Failed to create upload directory: " . $upload_dir);
                throw new \Exception("Failed to create upload directory");
            }
        }
        
        // Ensure directory has write permissions
        chmod($upload_dir, 0777);
        
        $file_ext = pathinfo($proof_file['name'], PATHINFO_EXTENSION);
        $new_filename = 'payment_' . $request_id . '_' . uniqid() . '.' . $file_ext;
        $target_path = $upload_dir . $new_filename;
        
        // Log target path for debugging
        error_log("Target file path: " . $target_path);
        error_log("File information: tmp_name=" . $proof_file['tmp_name'] . ", error=" . $proof_file['error']);
        
        if (!move_uploaded_file($proof_file['tmp_name'], $target_path)) {
            $upload_error = error_get_last();
            error_log("Failed to move uploaded file. PHP Error: " . ($upload_error ? $upload_error['message'] : 'Unknown') . ". Upload error code: " . $proof_file['error']);
            throw new \Exception("Failed to upload payment proof. Error code: " . $proof_file['error']);
        }
        
        // Make sure the uploaded file is readable by the web server
        chmod($target_path, 0644);
        
        // Store path in database
        $db_path = $upload_dir . $new_filename;
        
        // Update request with proof of payment
        $stmt = $this->pdo->prepare("
            UPDATE requests SET 
                proof_of_payment = ?,
                updated_at = NOW() 
            WHERE id = ?
        ");
        
        // Debug output to check if the update is successful
        $update_result = $stmt->execute([$db_path, $request_id]);
        if (!$update_result) {
            error_log("Failed to update proof_of_payment for request ID: $request_id. PDO Error: " . json_encode($stmt->errorInfo()));
        } else {
            error_log("Successfully updated proof_of_payment for request ID: $request_id. Path: $db_path");
        }
        
        return $update_result;
    }

    /**
     * Create payment notification
     * @param int $request_id
     * @return bool
     */
    public function createPaymentNotification($request_id) {
        require_once __DIR__ . '/../../create_notification.php';
        $message = "Payment completed for request #$request_id";
        return createNotification('payment', $message);
    }
}