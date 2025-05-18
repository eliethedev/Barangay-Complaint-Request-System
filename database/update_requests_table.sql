-- Add payment-related fields to requests table
ALTER TABLE requests
ADD COLUMN payment_status ENUM('pending', 'paid', 'failed') DEFAULT 'pending',
ADD COLUMN payment_method VARCHAR(50),
ADD COLUMN gcash_qr_url VARCHAR(255),
ADD COLUMN gcash_reference VARCHAR(50),
ADD COLUMN payment_amount DECIMAL(10,2),
ADD COLUMN payment_due_date DATETIME,
ADD COLUMN payment_instructions TEXT;
