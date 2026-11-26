-- 0009 customer last activity: date of the last login, so that the personal-data purge (PersonalDataRetention)
-- never removes an account that is still used. Existing accounts start from their last update.
ALTER TABLE customers ADD COLUMN IF NOT EXISTS last_activity_at TIMESTAMP;
UPDATE customers SET last_activity_at = COALESCE(updated_at, created_at) WHERE last_activity_at IS NULL;
ALTER TABLE customers ALTER COLUMN last_activity_at SET DEFAULT CURRENT_TIMESTAMP;
