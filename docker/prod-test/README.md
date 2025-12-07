# Production-like Testing Environment

This directory contains a Docker setup that **mimics production server behavior**.

## How It Works

**Architecture:** Uses official `php:8.4-apache` image with mounted volumes

```
Host (Windows)              Container (php:8.4-apache)
─────────────               ──────────────────────────
tsumego-hero/          →    /var/www/html/ (mounted)
├── src/                    ├── Apache 2.4 serves from webroot/
├── webroot/                ├── PHP 8.4 + opcache
└── deploy.sh               └── MySQL client
```

**Key similarity to production:**
- Code lives on host filesystem (like production server's /var/www/)
- `docker-compose exec web ./deploy.sh` mimics `ssh prod && ./deploy.sh`
- File changes are immediately visible (no image rebuild needed)
- Opcache and Apache configured like production

## Environment Specs

Official PHP image with production settings:
- php:8.4-apache (Debian-based, official Docker image)
- PHP 8.4 with opcache (revalidate_freq=60)
- Apache 2.4 with mod_rewrite
- MySQL 8.0

## Initial Setup

1. **Start the containers:**
```powershell
cd docker\prod-test
docker-compose up -d
```

2. **Run initial deployment (sets up everything):**
```powershell
docker-compose exec web bash -c "cd /var/www/html && ./deploy.sh"
```

This runs the full deployment process:
- Installs dependencies (composer install)
- Runs database migrations (phinx migrate)
- Clears all caches
- Builds assets (CSS/JS)
- Sets permissions

3. **Access the application:**
- URL: http://localhost:8080
- MySQL: localhost:3307 (root/root or db/db)

**Note:** The project directory is mounted as a volume, so code changes are immediately visible. You don't need to rebuild the Docker image when editing PHP/JS files.

## Testing Code Changes

After making changes to PHP, JS, or CSS files:

```powershell
# Re-run deployment to rebuild assets and clear caches
docker-compose exec web bash -c "cd /var/www/html && ./deploy.sh"
```

This mimics the production deployment cycle: `ssh prod && ./deploy.sh`