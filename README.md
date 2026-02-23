# 📝 Basenotes

A minimal, self-hosted Markdown notes app. No frameworks, no dependencies — just PHP 8.3, PostgreSQL, and Docker.

## Features

- **Markdown editor** powered by [EasyMDE](https://github.com/Ionaru/easy-markdown-editor)
- **Full-text search** across note titles and content
- **REST API** with Bearer token auth and cursor pagination
- **Mobile-friendly** — works down to 375px
- Runs entirely in Docker — no PHP or PostgreSQL install needed on your machine

## Quick Start

```bash
# 1. Clone
git clone https://github.com/your-username/basenotes.git
cd basenotes

# 2. Create your env file
cp .env.example .env
# Edit .env — generate APP_PEPPER with:
docker run --rm php:8.3-cli php -r "echo bin2hex(random_bytes(32));"

# 3. Start containers
docker compose up -d

# 4. Run migrations
docker compose exec app php bin/migrate.php

# 5. Open http://localhost and register an account
```

## Admin Access

Promote an existing user to admin (after they register):

```bash
docker compose exec app php bin/admin-user.php promote --email you@example.com
```

Admins can manage users at `/app/admin/users` (enable/disable accounts).

## Environment Variables

| Variable | Description |
|----------|-------------|
| `APP_PEPPER` | 64-char hex string for HMAC-hashing API tokens. Generate once, never change. |
| `APP_ENV` | `development` or `production` |
| `DB_HOST` | PostgreSQL host (Docker service name: `postgres`) |
| `DB_PORT` | PostgreSQL port (default `5432`) |
| `DB_NAME` | PostgreSQL database name |
| `DB_USER` | PostgreSQL username |
| `DB_PASS` | PostgreSQL password |

## API

All API routes require an `Authorization: Bearer <token>` header. Generate tokens in **Settings → API Tokens**.

| Method | Path | Scope | Description |
|--------|------|-------|-------------|
| `GET` | `/api/v1/notes` | `notes:read` | List notes (cursor-paginated) |
| `GET` | `/api/v1/notes/{id}` | `notes:read` | Retrieve a note by ID |
| `POST` | `/api/v1/notes` | `notes:write` | Create a note |
| `PATCH` | `/api/v1/notes/{id}` | `notes:write` | Update a note |
| `DELETE` | `/api/v1/notes/{id}` | `notes:write` | Delete a note |

**Example:**

```bash
curl -H "Authorization: Bearer nt_YOUR_TOKEN" http://localhost/api/v1/notes
```

**Response:**
```json
{
  "data": [{ "id": "...", "title": "My Note", "updated_at": 1234567890 }],
  "next_cursor": "base64string or null",
  "per_page": 20
}
```

**Example (single note):**

```bash
curl -H "Authorization: Bearer nt_YOUR_TOKEN" http://localhost/api/v1/notes/<note_id>
```

**Response:**
```json
{
  "id": "...",
  "title": "My Note",
  "content_md": "# Hello",
  "created_at": 1234567890,
  "updated_at": 1234567890
}
```

## Tech Stack

- **PHP 8.3-FPM** (Alpine) — no frameworks, PDO only
- **PostgreSQL 16** — relational database, stored in a named Docker volume
- **Caddy 2** — reverse proxy with automatic HTTPS in production
- **EasyMDE** — Markdown editor loaded via CDN

## Deployment (VPS)

See [INSTALL.md](INSTALL.md) for full instructions including production Caddy config, SSL, backups, and updating.

## Project Structure

```
public/index.php        # Single entry point — all requests routed here
public/assets/          # style.css + app.js (served directly by Caddy)
src/
  Auth/                 # Session, password hashing, API token logic
  Http/                 # Router, Request, Middleware (auth, CSRF, rate limiting)
  Repos/                # PDO repositories for users, notes, tokens
  Util/                 # Env loader, ULID generator, CSRF, validation
views/                  # PHP templates — rendering only, no business logic
assets/                 # style.css + app.js (served directly by Caddy from public/assets/)
migrations/             # SQL schema files, run via bin/migrate.php
```
