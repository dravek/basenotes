CREATE UNIQUE INDEX IF NOT EXISTS idx_note_versions_note_user_version_unique
    ON note_versions (note_id, user_id, version_no);

