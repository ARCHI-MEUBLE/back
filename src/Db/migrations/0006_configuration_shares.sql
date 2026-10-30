-- 0006 configuration shares: a furniture configuration reachable through a short link or a QR code.
-- Only the furniture is stored, nothing about the person who shared it (no name, email, session or IP).
-- content_hash lets the same configuration shared twice keep the same link.
CREATE TABLE IF NOT EXISTS configuration_shares (
    code TEXT PRIMARY KEY,
    configurator_id TEXT NOT NULL,
    prompt TEXT NOT NULL,
    config_data JSONB NOT NULL,
    content_hash TEXT NOT NULL UNIQUE,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_opened_at TIMESTAMP
);
