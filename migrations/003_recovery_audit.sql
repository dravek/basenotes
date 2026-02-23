CREATE TABLE IF NOT EXISTS recovery_audit (
    id         TEXT    PRIMARY KEY,
    user_id    TEXT    NOT NULL,
    event      TEXT    NOT NULL,
    ip         TEXT    NOT NULL,
    user_agent TEXT    NOT NULL,
    created_at INTEGER NOT NULL,
    FOREIGN KEY(user_id) REFERENCES users(id)
);

CREATE INDEX IF NOT EXISTS idx_recovery_audit_user_created
    ON recovery_audit (user_id, created_at DESC);
