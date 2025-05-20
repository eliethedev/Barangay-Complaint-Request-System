-- Add resolution_notes column to complaints table if it doesn't exist
ALTER TABLE complaints
ADD COLUMN IF NOT EXISTS resolution_notes TEXT;

-- Add resolution_notes column to requests table if it doesn't exist
ALTER TABLE requests
ADD COLUMN IF NOT EXISTS resolution_notes TEXT;
