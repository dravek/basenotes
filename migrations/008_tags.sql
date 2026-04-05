CREATE TABLE IF NOT EXISTS tags (
    id         TEXT    PRIMARY KEY,
    name       TEXT    NOT NULL,
    slug       TEXT    NOT NULL,
    created_at INTEGER NOT NULL,
    updated_at INTEGER NOT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS idx_tags_slug
    ON tags (slug);

CREATE TABLE IF NOT EXISTS note_tags (
    note_id TEXT NOT NULL,
    tag_id  TEXT NOT NULL,
    created_at INTEGER NOT NULL,
    PRIMARY KEY (note_id, tag_id),
    FOREIGN KEY(note_id) REFERENCES notes(id) ON DELETE CASCADE,
    FOREIGN KEY(tag_id) REFERENCES tags(id) ON DELETE CASCADE
);

CREATE INDEX IF NOT EXISTS idx_note_tags_tag_note
    ON note_tags (tag_id, note_id);
