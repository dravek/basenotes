CREATE TABLE IF NOT EXISTS users (
    id            TEXT    PRIMARY KEY,
    email         TEXT    UNIQUE NOT NULL,
    password_hash TEXT    NOT NULL,
    created_at    INTEGER NOT NULL,
    updated_at    INTEGER NOT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS idx_users_email_lower
    ON users (LOWER(email));

CREATE TABLE IF NOT EXISTS notes (
    id          TEXT    PRIMARY KEY,
    user_id     TEXT    NOT NULL,
    title       TEXT    NOT NULL DEFAULT 'Untitled',
    content_md  TEXT    NOT NULL DEFAULT '',
    created_at  INTEGER NOT NULL,
    updated_at  INTEGER NOT NULL,
    deleted_at  INTEGER NULL,
    FOREIGN KEY(user_id) REFERENCES users(id)
);

CREATE TABLE IF NOT EXISTS api_tokens (
    id           TEXT    PRIMARY KEY,
    user_id      TEXT    NOT NULL,
    name         TEXT    NOT NULL,
    token_hash   TEXT    NOT NULL,
    scopes       TEXT    NOT NULL DEFAULT 'notes:read',
    created_at   INTEGER NOT NULL,
    last_used_at INTEGER NULL,
    revoked_at   INTEGER NULL,
    FOREIGN KEY(user_id) REFERENCES users(id)
);

CREATE INDEX IF NOT EXISTS idx_notes_user_deleted_updated
    ON notes (user_id, deleted_at, updated_at DESC, id DESC);

CREATE INDEX IF NOT EXISTS idx_api_tokens_user_created
    ON api_tokens (user_id, created_at DESC);

CREATE UNIQUE INDEX IF NOT EXISTS idx_api_tokens_token_hash
    ON api_tokens (token_hash);
