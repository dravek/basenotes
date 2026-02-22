# INSTALL.md — Markdown Notes App

Setup instructions for local development and VPS production deployment.

---

## Requirements

| Tool | Minimum Version | Notes |
|------|----------------|-------|
| Docker | 24+ | [docs.docker.com/get-docker](https://docs.docker.com/get-docker/) |
| Docker Compose | v2 (plugin) | Included with Docker Desktop. On Linux: `apt install docker-compose-plugin` |
| Git | Any | For cloning the repo |

No PHP, SQLite, or Caddy installation required on your machine. Everything runs inside Docker.

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
DB_PATH=/var/www/html/data/notes.sqlite
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
- Pull `php:8.3-fpm-alpine` and `caddy:2-alpine` images on first run
- Build the PHP container with `pdo_sqlite` installed
- Start both the `app` (PHP-FPM) and `caddy` (web server) services
- Create named Docker volumes for the database and Caddy config

Check both containers are running:

```bash
docker compose ps
```

Both `app` and `caddy` should show `running`.

### 4. Run database migrations

```bash
docker compose exec app php bin/migrate.php
```

Expected output:

```
Migration 001_init.sql applied successfully.
Database setup complete.
```

This creates `data/notes.sqlite` with all required tables inside the Docker volume.

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

# Stop AND delete all data (destructive — SQLite volume is removed)
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
DB_PATH=/var/www/html/data/notes.sqlite
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

The SQLite database lives in a Docker named volume (`notes_data`). To back it up:

### Manual backup

```bash
docker compose exec app cp /var/www/html/data/notes.sqlite /var/www/html/data/notes_backup_$(date +%F).sqlite
```

### Copy the backup off the VPS

```bash
# Run this on your local machine
scp user@YOUR_VPS_IP:/var/lib/docker/volumes/notes_notes_data/_data/notes.sqlite ./notes_backup.sqlite
```

### Automated daily backup (recommended)

Add this cron job on the VPS (`crontab -e`):

```bash
0 2 * * * docker exec notes-app-1 cp /var/www/html/data/notes.sqlite /var/www/html/data/notes_$(date +\%F).sqlite
```

Adjust the container name (`notes-app-1`) to match your actual container name from `docker compose ps`.

---

## Restoring from Backup

```bash
# Stop the app
docker compose down

# Copy your backup sqlite file into the volume
docker run --rm -v notes_notes_data:/data -v $(pwd):/backup alpine \
  cp /backup/notes_backup.sqlite /data/notes.sqlite

# Start again
docker compose up -d
```

---

## Environment Variables Reference

| Variable | Required | Description |
|----------|----------|-------------|
| `APP_PEPPER` | Yes | 64-char hex string used to HMAC-hash API tokens. Generate once, never change. |
| `APP_ENV` | Yes | `development` or `production`. Controls error display behaviour. |
| `DB_PATH` | Yes | Absolute path to the SQLite file inside the container. Default: `/var/www/html/data/notes.sqlite` |

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

**Database file not found**
```bash
docker compose exec app php bin/migrate.php
```
If this fails, check `DB_PATH` in `.env` matches `/var/www/html/data/notes.sqlite` exactly.

**Permission denied on `/data` directory**
```bash
docker compose exec app chown -R www-data:www-data /var/www/html/data
```

**Reset everything and start fresh (destructive)**
```bash
docker compose down -v
docker compose up -d
docker compose exec app php bin/migrate.php
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
