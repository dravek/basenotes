# Copilot Instructions

## Project Overview

Minimal Markdown notes web app. PHP 8.3 + PostgreSQL + Docker + Caddy. No frameworks. Server-side rendering only.

## Running the App

```bash
# Start containers
docker compose up -d

# Run migrations (required on first start or after new migration files)
docker compose exec app php bin/migrate.php

# View logs
docker compose logs -f app
docker compose logs -f caddy
```

All `php bin/*` commands must run inside the `app` container via `docker compose exec app`.

## Architecture

All HTTP requests enter via `public/index.php` → `Router.php` dispatches to controller methods.

- **Web routes** (`/login`, `/register`, `/app/notes/*`, `/app/settings/*`) — session-authenticated SSR
- **API routes** (`/api/v1/notes*`) — Bearer token auth, JSON-only responses
- All `/app/*` routes pass through auth middleware; unauthenticated requests redirect to `/login`

### Key file roles
- `src/Util/Env.php` — parses `.env`, enforces required keys, throws `RuntimeException` on missing values
- `src/Util/Id.php` — ULID generation (no external libraries)
- `src/Util/Csrf.php` — CSRF token generation and `hash_equals` validation
- `src/Auth/Token.php` — API token generate/hash/verify using `hash_hmac('sha256', $raw, APP_PEPPER)`
- `src/Http/Middleware.php` — auth guard, CSRF check, rate limiter
- `views/layout.php` — shared HTML shell; all templates render via this
- `bin/migrate.php` — runs all `migrations/*.sql` files in alphabetical order

## PHP 8.3 Requirements

These features are **mandatory** where naturally applicable:

- **Typed class constants**: `const string PREFIX = 'nt_';`
- **Readonly properties**: all DTOs and value objects
- **Backed enums**: token scopes (`Scope: string`) and HTTP methods (`Method: string`) in the router
- **`json_validate()`**: call before every `json_decode()` in API handlers; return HTTP 400 on failure
- **`#[\Override]`**: apply to all interface implementations and method overrides
- **Named arguments**: for calls with multiple optional parameters (e.g., `session_set_cookie_params`)
- **First-class callable syntax**: when passing methods as callables (`$this->sanitize(...)`)
- **`never` return type**: on methods that always throw or redirect
- **`match` expressions**: prefer over `switch` for HTTP method dispatch and status code mapping

## Security Rules (Non-Negotiable)

- **All SQL**: PDO prepared statements with bound parameters. Zero string interpolation in SQL.
- **Output escaping**: every template value goes through `e(string $val): string` (`htmlspecialchars($v, ENT_QUOTES, 'UTF-8')`). No raw `echo` of user data.
- **CSRF**: all POST forms include `<input type="hidden" name="_csrf">`. Middleware validates with `hash_equals`. Failure = HTTP 403.
- **Soft delete**: every `notes` query must include `AND deleted_at IS NULL` unless explicitly querying deleted records. Opt-out, not opt-in.
- **Passwords**: Argon2id via `password_hash($pass, PASSWORD_ARGON2ID)`. 10-char minimum enforced in `Validate.php`.
- **API tokens**: format `nt_` + `base64url_encode(random_bytes(32))`. Only the HMAC-SHA256 hash is stored. Raw token shown once in UI, never again.
- **Rate limiting**: 5 failed logins within 15 min → HTTP 429 lockout. Counter resets on success.
- **Session cookies**: `HttpOnly`, `SameSite=Strict`, `Secure` set via `session_set_cookie_params()` at boot.

## Database Conventions

- IDs: `TEXT` in ULID format (sortable, URL-safe)
- Timestamps: `INTEGER` (Unix epoch)
- Use only ANSI-compatible SQL where possible; Postgres-specific features require explicit justification
- Schema lives in `migrations/001_init.sql`; tables: `users`, `notes`, `api_tokens`

## API Conventions

- All API responses: `Content-Type: application/json`
- Error shape: `{ "error": { "code": "UPPERCASE_CODE", "message": "Human-readable." } }`
- Cursor pagination on `GET /api/v1/notes`: cursor = `base64_encode("{updated_at}:{id}")`
- HTTP status codes used: 200, 201, 400, 401, 403, 404, 422, 429, 500

## Environment

Required `.env` keys (boot throws `RuntimeException` if missing or empty):

| Key | Description |
|-----|-------------|
| `APP_PEPPER` | 64-char hex string for HMAC-hashing API tokens. Changing it invalidates all tokens. |
| `APP_ENV` | `development` or `production` |
| `DB_HOST` | Docker service name for Postgres (`postgres`) |
| `DB_PORT` | Postgres port (`5432`) |
| `DB_NAME` | Database name |
| `DB_USER` | Database user |
| `DB_PASS` | Database password |

Generate `APP_PEPPER`:
```bash
docker run --rm php:8.3-cli php -r "echo bin2hex(random_bytes(32));"
```

## Frontend

- Raw CSS only (`assets/style.css`). No frameworks. Mobile-responsive, min 375px.
- EasyMDE loaded via CDN: `https://unpkg.com/easymde/dist/easymde.min.js`
- `assets/app.js` initialises EasyMDE on the note textarea and auto-injects CSRF tokens on forms
- Templates are in `views/` — rendering only, zero business logic
