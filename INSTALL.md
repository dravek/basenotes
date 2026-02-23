# INSTALL.md — Markdown Notes App

Setup instructions for local development and VPS production deployment.

---

## Requirements

| Tool | Minimum Version | Notes |
|------|----------------|-------|
| Docker | 24+ | [docs.docker.com/get-docker](https://docs.docker.com/get-docker/) |
| Docker Compose | v2 (plugin) | Included with Docker Desktop. On Linux: `apt install docker-compose-plugin` |
| Git | Any | For cloning the repo |

No PHP, PostgreSQL, or Caddy installation required on your machine. Everything runs inside Docker.

---

## Local Development Setup

### 1. Clone the repository

```bash
git clone https://github.com/your-username/notes-app.git
cd notes-app
```

### 2. Create your environment file

```bash
cp .env.example .env
```

Open `.env` and fill in the required values:

```env
APP_PEPPER=<generate this — see below>
APP_ENV=development
DB_HOST=postgres
DB_PORT=5432
DB_NAME=basenotes
DB_USER=basenotes
DB_PASS=basenotes
```

**Generate `APP_PEPPER`** — run this once and paste the output into `.env`:

```bash
docker run --rm php:8.3-cli php -r "echo bin2hex(random_bytes(32));"
```

Or if you have PHP installed locally:

```bash
php -r "echo bin2hex(random_bytes(32));"
```

`APP_PEPPER` must be exactly 64 hex characters. Keep it secret. If it changes, all existing API tokens will be invalidated.

### 3. Start the containers

```bash
docker compose up -d
```

This will:
- Pull `php:8.3-fpm-alpine`, `postgres:16-alpine`, and `caddy:2-alpine` images on first run
- Build the PHP container with `pdo_pgsql` installed
- Start `postgres`, `app` (PHP-FPM), and `caddy` (web server) services
- Create named Docker volumes for PostgreSQL and Caddy data/config

Check the containers are running:

```bash
docker compose ps
```

`postgres`, `app`, and `caddy` should show `running`/`healthy`.

### 4. Run database migrations

```bash
docker compose exec app php bin/migrate.php
```

Expected output:

```
Migration 001_init.sql applied successfully.
Database setup complete.
```

This creates all required tables in the PostgreSQL database container.

### 5. Open the app

Visit [http://localhost](http://localhost) in your browser.

Register a new account and start taking notes.

---

## Stopping and Starting

```bash
# Stop containers (data is preserved in volumes)
docker compose down

# Start again
docker compose up -d

# Stop AND delete all data (destructive — PostgreSQL volume is removed)
docker compose down -v
```

---

## Viewing Logs

```bash
# All services
docker compose logs -f

# PHP only
docker compose logs -f app

# Caddy only
docker compose logs -f caddy
```

---

## Running CLI Commands

All `php bin/*` commands must be run inside the `app` container:

```bash
docker compose exec app php bin/migrate.php
```

---

## VPS Production Deployment

### Quick VPS Checklist (Docker)

1. Install Docker + Compose v2.
2. Point your domain A record to the VPS IP.
3. Set `.env` with `APP_ENV=production`, `APP_PEPPER`, and a strong `DB_PASS`.
4. Enable the production Caddy block with your domain + email.
5. Map ports `80:80` and `443:443` in `docker-compose.yml`.
6. `docker compose up -d` and run migrations.

### Requirements on the VPS

- A VPS running Ubuntu 22.04 or 24.04 (or any Linux with Docker support)
- Docker and Docker Compose v2 installed
- A domain name pointed at the VPS IP address via an A record
- Ports 80 and 443 open in the VPS firewall

### Install Docker on Ubuntu

```bash
curl -fsSL https://get.docker.com | sh
sudo usermod -aG docker $USER
newgrp docker
```

Verify:

```bash
docker --version
docker compose version
```

### Point your domain to the VPS

In your DNS provider, create an A record:

```
notes.yourdomain.com  →  YOUR_VPS_IP
```

DNS changes can take a few minutes to propagate. You can verify with:

```bash
dig notes.yourdomain.com +short
```

It should return your VPS IP before you proceed.

### Deploy the app

```bash
# On the VPS
git clone https://github.com/your-username/notes-app.git /var/www/notes
cd /var/www/notes

cp .env.example .env
nano .env
```

Set these values in `.env`:

```env
APP_PEPPER=<64 random hex chars — generate same as local setup>
APP_ENV=production
DB_HOST=postgres
DB_PORT=5432
DB_NAME=basenotes
DB_USER=basenotes
DB_PASS=<strong password>
```

### Enable production Caddy config

Open `docker/caddy/Caddyfile` and swap the active blocks:

```
# Comment out the dev block:
# :80 {
#     ...
# }

# Uncomment and configure the production block:
notes.yourdomain.com {
    root * /var/www/html/public
    php_fastcgi app:9000
    file_server
    encode gzip
    tls your@email.com
}
```

Replace `notes.yourdomain.com` with your actual domain and `your@email.com` with your email address. Caddy uses the email for Let's Encrypt certificate registration.

### Start the containers on the VPS

Before you start, update the published ports for production:

```yaml
# docker-compose.yml (caddy service)
ports:
  - "80:80"
  - "443:443"
```

The default `8080/8443` mapping is intended for local dev.

```bash
docker compose up -d
docker compose exec app php bin/migrate.php
```

Visit `https://notes.yourdomain.com` — Caddy will automatically provision and renew the SSL certificate. This may take 30–60 seconds on first start.

---

## Updating the App

```bash
# On the VPS
cd /var/www/notes
git pull
docker compose up -d --build
# Run migrations only if new migration files were added
docker compose exec app php bin/migrate.php
```

---

## Backups

The PostgreSQL database lives in a Docker named volume (`postgres_data`). To back it up:

### Manual backup

```bash
docker compose exec -T postgres pg_dump -U basenotes -d basenotes > basenotes_$(date +%F).sql
```

### Copy the backup off the VPS

```bash
# Run this on your local machine
scp user@YOUR_VPS_IP:/var/www/notes/basenotes_YYYY-MM-DD.sql ./basenotes_backup.sql
```

### Automated daily backup (recommended)

Add this cron job on the VPS (`crontab -e`):

```bash
0 2 * * * cd /var/www/notes && docker compose exec -T postgres pg_dump -U basenotes -d basenotes > /var/www/notes/backups/basenotes_$(date +\%F).sql
```

Make sure `/var/www/notes/backups` exists and is writable by the user running cron.

---

## Restoring from Backup

```bash
# Stop the app
docker compose down

# Restore backup into Postgres (destructive if schema/data already exists)
docker compose exec -T postgres psql -U basenotes -d basenotes < basenotes_backup.sql

# Start again
docker compose up -d
```

---

## Environment Variables Reference

| Variable | Required | Description |
|----------|----------|-------------|
| `APP_PEPPER` | Yes | 64-char hex string used to HMAC-hash API tokens. Generate once, never change. |
| `APP_ENV` | Yes | `development` or `production`. Controls error display behaviour. |
| `DB_HOST` | Yes | PostgreSQL host. In Docker Compose this is `postgres`. |
| `DB_PORT` | Yes | PostgreSQL port. Default: `5432`. |
| `DB_NAME` | Yes | PostgreSQL database name. |
| `DB_USER` | Yes | PostgreSQL username. |
| `DB_PASS` | Yes | PostgreSQL password. |

---

## Troubleshooting

**Containers won't start**
```bash
docker compose logs app
docker compose logs caddy
```
Check for missing `.env` values or port conflicts on 80/443.

**`APP_PEPPER` error on boot**
The app throws a `RuntimeException` if `APP_PEPPER` is missing or empty in `.env`. Generate it and restart:
```bash
docker compose restart app
```

**SSL certificate not provisioning**
- Confirm DNS A record is pointing to your VPS IP: `dig notes.yourdomain.com +short`
- Confirm ports 80 and 443 are open: `ufw allow 80 && ufw allow 443`
- Check Caddy logs: `docker compose logs caddy`

**Database connection failed**
```bash
docker compose exec app php bin/migrate.php
```
If this fails, check the `DB_*` values in `.env` and confirm the `postgres` service is healthy:
```bash
docker compose ps
docker compose logs postgres
```

**Postgres credentials/authentication error**
```bash
docker compose logs postgres
```
Check that `DB_NAME`, `DB_USER`, and `DB_PASS` in `.env` match the Postgres container settings.

**Reset everything and start fresh (destructive)**
```bash
docker compose down -v
docker compose up -d
docker compose exec app php bin/migrate.php
```

---

## Keep It Running

The Docker services are configured with `restart: unless-stopped` in `docker-compose.yml`, so they will:
- Restart automatically if they crash.
- Restart on VPS reboot.

You can confirm with:

```bash
docker compose ps
```
This deletes all data including the database.

---

## Security Checklist Before Going Live

- [ ] `APP_PEPPER` is set to a unique 64-char hex string
- [ ] `.env` is listed in `.gitignore` and never committed
- [ ] `APP_ENV` is set to `production`
- [ ] Domain A record is pointing to the correct VPS IP
- [ ] Caddy production block is active with your domain and email
- [ ] Dev Caddy block (`:80`) is commented out
- [ ] VPS firewall allows only ports 22 (SSH), 80, and 443
- [ ] Automated backups are configured
