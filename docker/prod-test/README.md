# Production-like Testing Environment

This directory contains a Docker setup that **mimics production server behavior**.

## Quick Start

### Complete Fresh Start
```powershell
cd docker\prod-test
docker-compose down -v
Remove-Item ..\..\config\database.php -Force -ErrorAction SilentlyContinue
docker-compose up -d
Start-Sleep -Seconds 10
docker-compose exec web bash -c "cd /var/www/html && ./deploy.sh"
```

### Start Existing Environment
```powershell
cd docker\prod-test
docker-compose up -d
docker-compose exec web bash -c "cd /var/www/html && ./deploy.sh"
```

Access: http://test.tsumego.ddev.site:8080

**Note**: Database config is auto-created from template on first start (non-interactive).
If you have an empty `config/database.php` in your workspace, delete it first:
```powershell
Remove-Item config\database.php -Force
```