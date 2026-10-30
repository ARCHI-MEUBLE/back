-- 0004 security hardening
-- The baseline seeded an "admin" account whose bcrypt hash is public in the repository: lock it if it was never changed.
UPDATE admins
SET password = '!disabled', password_hash = NULL
WHERE password = '$2y$12$3uQQHtqzlQH5eptxZJkJoudv4TsExrfwl7T22u4gxIlzpJSxBbmtO'
   OR password_hash = '$2y$12$3uQQHtqzlQH5eptxZJkJoudv4TsExrfwl7T22u4gxIlzpJSxBbmtO';

-- user_session used to hold live PHP session identifiers of logged-in customers, exposed by a public endpoint.
UPDATE configurations SET user_session = NULL WHERE user_id IS NOT NULL AND user_session IS NOT NULL;

-- Reset tokens are now stored hashed: outstanding plaintext tokens can no longer match and are removed.
DELETE FROM password_resets;
