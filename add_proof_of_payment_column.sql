-- Add proof_of_payment column to requests table
ALTER TABLE requests 
ADD COLUMN proof_of_payment VARCHAR(255) NULL AFTER payment_reference;

-- Add index for faster queries
ALTER TABLE requests 
ADD INDEX idx_proof_of_payment (proof_of_payment);
