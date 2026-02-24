CREATE TABLE IF NOT EXISTS note_versions (
    id                TEXT    PRIMARY KEY,
    note_id           TEXT    NOT NULL,
    user_id           TEXT    NOT NULL,
    version_no        INTEGER NOT NULL,
    title             TEXT    NOT NULL,
    content_md        TEXT    NOT NULL,
    source_updated_at INTEGER NOT NULL,
    created_at        INTEGER NOT NULL,
    event_type        TEXT    NOT NULL,
    FOREIGN KEY(note_id) REFERENCES notes(id),
    FOREIGN KEY(user_id) REFERENCES users(id)
);

CREATE INDEX IF NOT EXISTS idx_note_versions_note_user_version
    ON note_versions (note_id, user_id, version_no DESC, id DESC);

CREATE INDEX IF NOT EXISTS idx_note_versions_user_created
    ON note_versions (user_id, created_at DESC, id DESC);

