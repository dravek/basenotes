CREATE TABLE IF NOT EXISTS recovery_codes (
    id         TEXT    PRIMARY KEY,
    user_id    TEXT    NOT NULL,
    code_hash  TEXT    NOT NULL,
    created_at INTEGER NOT NULL,
    used_at    INTEGER NULL,
    FOREIGN KEY(user_id) REFERENCES users(id)
);

CREATE INDEX IF NOT EXISTS idx_recovery_codes_user
    ON recovery_codes (user_id);

CREATE UNIQUE INDEX IF NOT EXISTS idx_recovery_codes_hash
    ON recovery_codes (code_hash);
