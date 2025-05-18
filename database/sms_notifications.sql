CREATE TABLE IF NOT EXISTS sms_notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    message_id VARCHAR(100),
    type ENUM('Bulk', 'Individual', 'Urgent', 'Request Update') NOT NULL,
    recipients JSON NOT NULL,
    message TEXT NOT NULL,
    status ENUM('Pending', 'Sent', 'Failed', 'Scheduled', 'Partial') NOT NULL DEFAULT 'Pending',
    response TEXT,
    scheduled_time DATETIME,
    created_at DATETIME NOT NULL,
    updated_at DATETIME,
    INDEX idx_status (status),
    INDEX idx_type (type),
    INDEX idx_message_id (message_id),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
