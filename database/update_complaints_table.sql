-- Add new columns to complaints table
ALTER TABLE complaints
ADD COLUMN IF NOT EXISTS email VARCHAR(255) AFTER phone,
ADD COLUMN IF NOT EXISTS subject_type VARCHAR(255) AFTER address,
ADD COLUMN IF NOT EXISTS subject VARCHAR(255) AFTER subject_type,
ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at;

-- Update existing data to split subject and details
UPDATE complaints
SET subject_type = SUBSTRING_INDEX(details, ' - ', 1),
    details = SUBSTRING(details, LOCATE(' - ', details) + 3)
WHERE subject_type IS NULL AND details LIKE '% - %';

-- Add indexes for better query performance
CREATE INDEX IF NOT EXISTS idx_user_id ON complaints(user_id);
CREATE INDEX IF NOT EXISTS idx_status ON complaints(status);
CREATE INDEX IF NOT EXISTS idx_created_at ON complaints(created_at);
CREATE INDEX IF NOT EXISTS idx_subject_type ON complaints(subject_type);
