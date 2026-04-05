CREATE TABLE IF NOT EXISTS audit_log (
    id            TEXT    PRIMARY KEY,
    actor_user_id TEXT    NULL,
    target_user_id TEXT    NULL,
    event         TEXT    NOT NULL,
    entity_type   TEXT    NULL,
    entity_id     TEXT    NULL,
    ip            TEXT    NOT NULL,
    user_agent    TEXT    NOT NULL,
    metadata_json TEXT    NOT NULL DEFAULT '{}',
    created_at    INTEGER NOT NULL,
    FOREIGN KEY(actor_user_id) REFERENCES users(id),
    FOREIGN KEY(target_user_id) REFERENCES users(id)
);

CREATE INDEX IF NOT EXISTS idx_audit_log_event_created
    ON audit_log (event, created_at DESC);

CREATE INDEX IF NOT EXISTS idx_audit_log_actor_created
    ON audit_log (actor_user_id, created_at DESC);

CREATE INDEX IF NOT EXISTS idx_audit_log_target_created
    ON audit_log (target_user_id, created_at DESC);
