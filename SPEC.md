# 🚀 PROJECT SPECIFICATION: Minimal Markdown Notes Web App
### PHP 8.3 + SQLite + Docker + Caddy

**Role:** You are an expert Senior Full-Stack PHP Developer.  
**Objective:** Build a secure, minimalist, and portable Markdown notes app following the exact architecture below. Complete all phases in order. Do not skip ahead or leave stubs — every file must be fully implemented before moving to the next phase.

---

## 🛠 TECH STACK & CONSTRAINTS

- **PHP:** 8.3+ strictly. Use modern PHP 8.3 features where appropriate: typed class constants, `json_validate()`, `#[\Override]` attribute, readonly properties, union types, enums, and named arguments. No frameworks (no Laravel, Symfony, Slim, etc.). PDO only.
- **Database:** SQLite 3 via PDO. Use only ANSI-compatible SQL to ensure future Postgres migration compatibility. No SQLite-specific functions in query logic.
- **Frontend:** Server-side rendering only. No React, Vue, or SPA architecture. HTML is rendered by PHP templates.
- **Markdown Editor:** EasyMDE loaded via CDN (`https://unpkg.com/easymde/dist/easymde.min.js`).
- **Styling:** Raw CSS only. No Tailwind, Bootstrap, or CSS frameworks. Must be mobile-responsive (min 375px).
- **Session:** PHP native sessions with `HttpOnly`, `SameSite=Strict`, `Secure` cookie flags set via `session_set_cookie_params()` at boot.
- **Environment:** All secrets (`APP_PEPPER`, `DB_PATH`) loaded from a `.env` file parsed by a minimal custom loader in `src/Util/Env.php`. Never hardcoded. Boot fails loudly if required keys are missing.
- **Containerisation:** Docker + Docker Compose. PHP 8.3-FPM Alpine + Caddy Alpine. No other services required.
- **Error Reporting:** `E_ALL` in development. Zero warnings or notices permitted at any level.

---

## 📂 DIRECTORY STRUCTURE

Create this exact structure. No deviations.

```
/
├── docker/
│   ├── php/
│   │   └── php.ini            # E_ALL, pdo_sqlite on, sane upload/memory limits
│   └── caddy/
│       └── Caddyfile          # Reverse proxy config — dev (localhost) + prod (domain)
├── public/                    # Document root — Caddy serves this directory
│   └── index.php              # Single entry point — all requests routed here
├── assets/
│   ├── style.css              # All styles — mobile-first, raw CSS
│   └── app.js                 # EasyMDE init + CSRF auto-inject on forms
├── src/
│   ├── Auth/
│   │   ├── Session.php        # Session bootstrap, user auth state
│   │   ├── Password.php       # Argon2id hash + verify
│   │   └── Token.php          # API token generate, hash, verify
│   ├── Http/
│   │   ├── Router.php         # Pattern-based router (GET/POST/PATCH/DELETE)
│   │   ├── Request.php        # Wraps $_GET, $_POST, $_SERVER, $_COOKIE
│   │   └── Middleware.php     # Auth guard, CSRF check, rate limiter
│   ├── Repos/
│   │   ├── UserRepository.php
│   │   ├── NoteRepository.php
│   │   └── TokenRepository.php
│   └── Util/
│       ├── Env.php            # .env parser + required key enforcement
│       ├── Id.php             # ULID generation (sortable, URL-safe)
│       ├── Csrf.php           # Token generation + hash_equals validation
│       └── Validate.php       # Input validation rules (email, length, etc.)
├── views/                     # PHP templates — rendering only, zero business logic
│   ├── layout.php             # Shared HTML shell (head, nav, footer)
│   ├── login.php
│   ├── register.php
│   ├── notes/
│   │   ├── list.php
│   │   └── edit.php
│   └── settings/
│       ├── password.php
│       └── tokens.php
├── migrations/
│   └── 001_init.sql           # Full schema — executed by bin/migrate.php
├── bin/
│   └── migrate.php            # CLI: php bin/migrate.php
├── data/                      # Mounted as Docker volume — DB lives here
│   └── .gitkeep
├── Dockerfile                 # PHP 8.3-FPM Alpine image
├── docker-compose.yml         # App + Caddy services
├── .env                       # Never committed
├── .env.example               # Committed — all required keys, blank values
├── INSTALL.md                 # Setup instructions (dev + VPS)
└── .gitignore                 # Ignores: /data/*.sqlite, .env
```

---

## 🐳 DOCKER CONFIGURATION

### `Dockerfile`

```dockerfile
FROM php:8.3-fpm-alpine

RUN apk add --no-cache sqlite-libs sqlite-dev \
    && docker-php-ext-install pdo pdo_sqlite

COPY docker/php/php.ini /usr/local/etc/php/conf.d/custom.ini

WORKDIR /var/www/html

COPY . .

RUN chown -R www-data:www-data /var/www/html/data
```

### `docker-compose.yml`

```yaml
services:
  app:
    build: .
    restart: unless-stopped
    volumes:
      - .:/var/www/html
      - notes_data:/var/www/html/data
    environment:
      - APP_ENV=${APP_ENV}
      - APP_PEPPER=${APP_PEPPER}
      - DB_PATH=${DB_PATH}

  caddy:
    image: caddy:2-alpine
    restart: unless-stopped
    ports:
      - "80:80"
      - "443:443"
    volumes:
      - ./docker/caddy/Caddyfile:/etc/caddy/Caddyfile
      - caddy_data:/data
      - caddy_config:/config
      - ./public:/var/www/html/public
    depends_on:
      - app

volumes:
  notes_data:
  caddy_data:
  caddy_config:
```

### `docker/caddy/Caddyfile`

Two blocks — comment out whichever is not in use:

```
# --- DEVELOPMENT (localhost) ---
:80 {
    root * /var/www/html/public
    php_fastcgi app:9000
    file_server
    encode gzip
}

# --- PRODUCTION (VPS with auto-SSL) ---
# notes.yourdomain.com {
#     root * /var/www/html/public
#     php_fastcgi app:9000
#     file_server
#     encode gzip
#     tls your@email.com
# }
```

### `docker/php/php.ini`

```ini
error_reporting = E_ALL
display_errors = On
display_startup_errors = On
log_errors = On
memory_limit = 128M
upload_max_filesize = 10M
post_max_size = 10M
session.cookie_httponly = 1
session.cookie_samesite = Strict
session.use_strict_mode = 1
extension=pdo_sqlite
```

---

## 🗄 DATABASE SCHEMA

File: `migrations/001_init.sql`

Use `INTEGER` for all Unix timestamps. Use `TEXT` for all IDs (ULID format).

```sql
CREATE TABLE IF NOT EXISTS users (
    id            TEXT    PRIMARY KEY,
    email         TEXT    UNIQUE NOT NULL,
    password_hash TEXT    NOT NULL,
    created_at    INTEGER NOT NULL,
    updated_at    INTEGER NOT NULL
);

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
```

---

## 🐘 PHP 8.3 USAGE REQUIREMENTS

These features must be used where naturally appropriate — not forced, but not avoided:

- **Typed class constants** — all constants in every class must declare an explicit type.
  ```php
  const string PREFIX = 'nt_';
  const int TOKEN_BYTES = 32;
  ```
- **Readonly properties** — use on all DTOs and value objects where mutation after construction is not needed.
- **Backed enums** — use for token scopes and HTTP methods in the router.
  ```php
  enum Scope: string {
      case Read  = 'notes:read';
      case Write = 'notes:write';
  }

  enum Method: string {
      case GET    = 'GET';
      case POST   = 'POST';
      case PATCH  = 'PATCH';
      case DELETE = 'DELETE';
  }
  ```
- **`json_validate()`** — call before every `json_decode()` in API handlers. Return HTTP 400 if validation fails.
- **`#[\Override]`** — apply to all methods that override a parent or implement an interface method.
- **Named arguments** — use for calls with multiple optional parameters.
  ```php
  session_set_cookie_params(lifetime: 0, path: '/', secure: true, httponly: true, samesite: 'Strict');
  ```
- **First-class callable syntax** — use when passing methods as callables.
  ```php
  array_map($this->sanitize(...), $inputs);
  ```
- **`never` return type** — use on methods that always throw or always redirect.
- **`match` expressions** — prefer over `switch` for HTTP method dispatch and status code mapping.

---

## 🔐 SECURITY RULES (Non-Negotiable)

### Passwords
- Hash with `password_hash($pass, PASSWORD_ARGON2ID)`.
- Minimum 10 characters enforced in `Validate.php` before hashing.
- On password change: verify current password first with `password_verify()`, then hash new password, then call `session_regenerate_id(true)`.

### API Tokens
- Format: `nt_` + `base64url_encode(random_bytes(32))` — generated once, shown once in the UI.
- Storage: `hash_hmac('sha256', $rawToken, APP_PEPPER)` — only the hash is stored in the DB.
- Verification: re-hash the Bearer token from the `Authorization` header, compare with `hash_equals()` against the stored hash.
- On successful API auth: update `last_used_at` to `time()`.
- On revoke: set `revoked_at = time()`. Revoked tokens are rejected immediately on next request.

### CSRF
- Generate token at session start: `$_SESSION['csrf_token'] = bin2hex(random_bytes(32))`.
- All POST forms include a hidden field: `<input type="hidden" name="_csrf" value="...">`.
- `Middleware.php` validates using `hash_equals($_SESSION['csrf_token'], $_POST['_csrf'] ?? '')` before any POST handler runs. Failure = HTTP 403, no further processing.

### Rate Limiting
- Track failed login attempts in session: count + first-attempt timestamp.
- After 5 failed logins within a 15-minute window: return HTTP 429, show lockout message with time remaining. Do not reveal whether the email exists.
- Reset counter on successful login.

### SQL Injection
- Every database query uses PDO prepared statements with bound parameters. Zero string interpolation in SQL. Any violation is an immediate blocker.

### Soft Delete
- Every `notes` query must include `AND deleted_at IS NULL` unless explicitly querying deleted records. `NoteRepository` enforces this as a default — opt-out, not opt-in.

### Output Escaping
- Every value rendered in a PHP template must pass through `htmlspecialchars($value, ENT_QUOTES, 'UTF-8')`. Implement a global helper `e(string $val): string` for this. No raw `echo` of user-supplied data anywhere.

---

## 🛣 ROUTING PLAN

All HTTP requests enter via `public/index.php`. `Router.php` matches URI pattern + HTTP method and dispatches to the appropriate controller method.

### Web Routes (Session-authenticated SSR)

| Method | Path | Description |
|--------|------|-------------|
| GET | `/login` | Show login form |
| POST | `/login` | Authenticate user, start session |
| GET | `/register` | Show register form |
| POST | `/register` | Create user account |
| POST | `/logout` | Destroy session, redirect to `/login` |
| GET | `/app/notes` | List notes; optional `?q=` search |
| GET | `/app/notes/new` | Blank note editor |
| POST | `/app/notes` | Save new note |
| GET | `/app/notes/{id}` | View/edit existing note |
| POST | `/app/notes/{id}` | Update existing note |
| POST | `/app/notes/{id}/delete` | Soft delete note |
| GET | `/app/settings/password` | Show password change form |
| POST | `/app/settings/password` | Process password change |
| GET | `/app/settings/tokens` | List API tokens |
| POST | `/app/settings/tokens` | Generate new API token |
| POST | `/app/settings/tokens/{id}/revoke` | Revoke token |

All `/app/*` routes pass through auth middleware. Unauthenticated requests redirect to `/login`.

### API Routes (Bearer token, JSON only)

| Method | Path | Scope Required | Description |
|--------|------|---------------|-------------|
| GET | `/api/v1/notes` | `notes:read` | List notes, cursor-paginated |
| POST | `/api/v1/notes` | `notes:write` | Create note |
| PATCH | `/api/v1/notes/{id}` | `notes:write` | Update note fields |
| DELETE | `/api/v1/notes/{id}` | `notes:write` | Soft delete note |

**Cursor Pagination** for `GET /api/v1/notes`:
- Cursor = `base64_encode("{updated_at}:{id}")`, passed as `?cursor=` query param.
- Default page size: 20. Maximum: 100 via `?per_page=`.
- Response shape:
  ```json
  {
    "data": [{ "id": "...", "title": "...", "updated_at": 1234567890 }],
    "next_cursor": "base64string or null",
    "per_page": 20
  }
  ```

**API Error Shape** (all error responses):
```json
{ "error": { "code": "UNAUTHORIZED", "message": "Human-readable message." } }
```

**HTTP status codes used:** 200, 201, 400, 401, 403, 404, 422, 429, 500.

---

## ⚙️ ENVIRONMENT CONFIG

`.env.example` (committed to version control):
```
APP_PEPPER=
APP_ENV=production
DB_PATH=/var/www/html/data/notes.sqlite
```

`.env` (never committed — add to `.gitignore`):
```
APP_PEPPER=<64 random hex chars — generate with: php -r "echo bin2hex(random_bytes(32));">
APP_ENV=production
DB_PATH=/var/www/html/data/notes.sqlite
```

`Env.php` must:
- Parse `KEY=VALUE` lines, ignore `#` comments and blank lines.
- Expose a static `Env::get(string $key): string` method.
- On boot, assert all required keys (`APP_PEPPER`, `APP_ENV`, `DB_PATH`) are present and non-empty.
- Throw a descriptive `RuntimeException` if any key is missing — never silently continue.

---

## 🏗 BUILD PHASES (Execute in Strict Order)

Claude Code must fully implement and verify each phase before beginning the next. Stubs, TODOs, and placeholder returns are not acceptable at phase completion.

---

### Phase 0 — Docker Environment

**Deliverables:**
- `Dockerfile` — PHP 8.3-FPM Alpine with `pdo_sqlite` installed.
- `docker-compose.yml` — `app` and `caddy` services with named volumes.
- `docker/php/php.ini` — `E_ALL`, `pdo_sqlite`, sane limits.
- `docker/caddy/Caddyfile` — dev block active, prod block commented.
- `.env.example` and `.gitignore`.

**Verification:**
```bash
cp .env.example .env
# Edit .env: set APP_PEPPER to 64 random hex chars
docker compose up -d
docker compose ps
# Both app and caddy show status: running
curl http://localhost
# Returns HTTP response (even a 404 is fine at this stage)
```

---

### Phase 1 — Skeleton + Migration

**Deliverables:**
- All directories and placeholder files per the structure above.
- `migrations/001_init.sql` with complete schema.
- `src/Util/Env.php` — full `.env` parser with required key enforcement.
- `bin/migrate.php` — loads `.env`, connects via PDO to SQLite at `DB_PATH`, executes all `migrations/*.sql` files in alphabetical filename order, prints per-file success or failure, exits with code `0` on success or `1` on any failure.

**Verification:**
```bash
docker compose exec app php bin/migrate.php
# Output: "Migration 001_init.sql applied successfully."
# data/notes.sqlite exists with all 3 tables confirmed via:
docker compose exec app php -r "
  \$db = new PDO('sqlite:/var/www/html/data/notes.sqlite');
  \$tables = \$db->query(\"SELECT name FROM sqlite_master WHERE type='table'\")->fetchAll();
  print_r(\$tables);
"
```

---

### Phase 2 — DB Layer + Register/Login

**Deliverables:**
- `src/Util/Id.php` — ULID generation (monotonically sortable, URL-safe, no external libraries — implement directly using `random_bytes` and `time`).
- `src/Util/Csrf.php` — generate and validate CSRF tokens.
- `src/Util/Validate.php` — rules: `email()`, `minLength()`, `required()`, returns typed error arrays.
- `src/Auth/Password.php` — `hash(string $password): string` and `verify(string $password, string $hash): bool` using `PASSWORD_ARGON2ID`.
- `src/Auth/Session.php` — `start()`, `userId(): string|null`, `set()`, `destroy()`.
- `src/Repos/UserRepository.php` — `findByEmail(string $email): UserDto|null`, `create(UserDto $dto): void`.
- `src/Http/Request.php`, `Router.php`, `Middleware.php`.
- `public/index.php` — bootstraps Env, Session, Router.
- Register, login, logout routes + views with CSRF tokens embedded.
- `views/layout.php` with mobile-friendly base HTML shell.
- Rate limiter active on POST `/login`.

**Verification:**
```bash
# Open http://localhost in browser
# Register a new user → redirects to /app/notes
# Log out → redirects to /login
# Enter wrong password 5 times → lockout message appears
# Attempt 6 with correct password → still blocked (429)
# Submit login with missing _csrf field → HTTP 403
```

---

### Phase 3 — Notes CRUD + EasyMDE

**Deliverables:**
- `src/Repos/NoteRepository.php`:
  - `listByUser(string $userId, ?string $search = null): NoteDto[]`
  - `findById(string $id, string $userId): NoteDto|null`
  - `create(NoteDto $dto): void`
  - `update(NoteDto $dto): void`
  - `softDelete(string $id, string $userId): void`
  - All methods enforce `deleted_at IS NULL` by default.
- All `/app/notes` routes and views fully implemented.
- `edit.php` loads EasyMDE via CDN. Form submits raw Markdown in a `<textarea>`. `app.js` initialises EasyMDE on the textarea.
- Search via `?q=` uses `LIKE :q` with `%keyword%` in a prepared statement across both `title` and `content_md`.
- Note list shows title + truncated content preview + last updated date.

**Verification:**
```bash
# Create note with Markdown → saved, appears in list
# Edit note → changes persist on page reload
# GET /app/notes?q=keyword → filters results correctly
# Delete note → row disappears from list; DB row has deleted_at set
# EasyMDE toolbar renders on desktop; textarea visible on mobile
```

---

### Phase 4 — API Tokens + Password Change

**Deliverables:**
- `src/Auth/Token.php`:
  - `generate(): array{raw: string, hash: string}` — returns both for single-use display.
  - `hash(string $raw): string` — HMAC-SHA256 with `APP_PEPPER`.
  - `verify(string $raw, string $storedHash): bool` — uses `hash_equals()`.
- `src/Repos/TokenRepository.php`:
  - `create(TokenDto $dto): void`
  - `listByUser(string $userId): TokenDto[]`
  - `findByHash(string $hash): TokenDto|null`
  - `revoke(string $id, string $userId): void`
  - `updateLastUsed(string $id): void`
- All `/app/settings/*` routes and views.
- Raw token shown exactly once in UI after creation, inside a `<code>` block with a copy button. Never stored raw; never shown again.
- All `/api/v1/*` routes with:
  - `Content-Type: application/json` on all responses.
  - `Authorization: Bearer nt_xxx` parsing in `Middleware.php`.
  - Scope enforcement per route.
  - `json_validate()` before `json_decode()` on all request bodies.
  - Cursor pagination on `GET /api/v1/notes`.
- Password change: verifies current password, enforces 10+ char minimum on new password, calls `session_regenerate_id(true)` on success.

**Verification:**
```bash
# Generate token via UI — appears once starting with nt_
# Then test via curl inside the container:
docker compose exec app curl -s \
  -H "Authorization: Bearer nt_YOUR_TOKEN_HERE" \
  http://localhost/api/v1/notes
# → JSON with data array and next_cursor

docker compose exec app curl -s -X POST \
  -H "Authorization: Bearer nt_YOUR_TOKEN_HERE" \
  -H "Content-Type: application/json" \
  -d '{"title":"API Note","content_md":"# Hello"}' \
  http://localhost/api/v1/notes
# → HTTP 201 with new note JSON

docker compose exec app curl -s \
  -H "Authorization: Bearer nt_WRONG" \
  http://localhost/api/v1/notes
# → HTTP 401 {"error":{"code":"UNAUTHORIZED","message":"..."}}

# Revoke token in UI → same token now returns 401
# Password change with wrong current password → error shown, session unchanged
# Password change success → user stays logged in, session regenerated
```

---

## ✅ DEFINITION OF DONE

All of the following must be true before the project is considered complete:

- [ ] `docker compose up -d` starts both services with no errors.
- [ ] `docker compose exec app php bin/migrate.php` creates all 3 tables from scratch.
- [ ] Zero PHP warnings or notices at `E_ALL` error level.
- [ ] All class constants have explicit types (PHP 8.3 typed constants).
- [ ] All DTOs use readonly properties.
- [ ] Backed enums used for token scopes and HTTP methods in the router.
- [ ] `json_validate()` called before every `json_decode()` in API handlers.
- [ ] `#[\Override]` applied to all interface implementations and method overrides.
- [ ] User can register (email + 10+ char password), log in, log out.
- [ ] 5 failed logins in 15 min triggers lockout — attempt 6 returns 429 without processing.
- [ ] EasyMDE loads, accepts Markdown, and saves correctly to SQLite.
- [ ] Notes list shows non-deleted notes only. Search filters by title and content.
- [ ] Soft delete sets `deleted_at` — note disappears from all list and edit queries.
- [ ] API token generated in UI starts with `nt_`. Raw token shown once only.
- [ ] `curl` with valid Bearer token returns correct JSON with pagination cursor.
- [ ] `curl` with wrong or revoked token returns HTTP 401 with error JSON.
- [ ] `PATCH` on another user's note returns HTTP 403.
- [ ] Every SQL query uses prepared statements — zero string interpolation in SQL.
- [ ] Every template value passes through `e()` before output.
- [ ] CSRF missing or mismatched on any POST returns HTTP 403 immediately.
- [ ] `APP_PEPPER` missing from `.env` throws `RuntimeException` on boot.
- [ ] Password change fails with wrong current password — session unchanged.
- [ ] Password change succeeds → `session_regenerate_id(true)` called.
- [ ] UI is fully usable on a 375px wide screen without horizontal scroll.
- [ ] `.env` and `*.sqlite` are listed in `.gitignore`.
- [ ] `INSTALL.md` accurately reflects the actual steps to run the project.
