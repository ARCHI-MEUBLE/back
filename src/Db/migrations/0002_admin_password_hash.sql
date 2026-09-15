ALTER TABLE admins ADD COLUMN IF NOT EXISTS password_hash TEXT;
UPDATE admins SET password_hash = password WHERE password_hash IS NULL AND password IS NOT NULL;
