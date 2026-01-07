# STIQR Canvas Backend

Backend API untuk aplikasi STIQR Canvas - sistem manajemen canvassing dan follow-up untuk tim sales.

## Tech Stack

- **Laravel 11** - PHP Framework
- **PHP 8.3** - Runtime
- **PostgreSQL 16** - Database
- **Docker & Docker Compose** - Containerization
- **S3-Compatible Storage** (IS3 CloudHost) - File Storage
- **Laravel Sanctum** - API Authentication

## Features

- 🔐 Authentication dengan Laravel Sanctum (Token-based)
- 📸 Upload screenshot ke S3-compatible storage (public access)
- 🔍 OCR integration untuk ekstraksi data dari screenshot
- 👥 Role-based access (Staff, Supervisor)
- 📊 Dashboard dan reporting
- ✅ Quality check workflow
- 🔄 Canvassing cycle management

## Requirements

- Docker & Docker Compose
- Git

## Quick Start

### 1. Clone Repository

```bash
git clone <repository-url>
cd stiqr-canvas-be
```

### 2. Setup Environment

Copy `.env.example` ke `.env` dan sesuaikan konfigurasi:

```bash
cp .env.example .env
```

Konfigurasi penting di `.env`:

```env
# App
APP_NAME=Laravel
APP_ENV=local
APP_KEY=base64:... # Generate dengan: php artisan key:generate
APP_DEBUG=true
APP_URL=http://localhost

# Database
DB_CONNECTION=pgsql
DB_HOST=postgres
DB_PORT=5432
DB_DATABASE=canvasdb
DB_USERNAME=postgres
DB_PASSWORD=your_secure_password

# S3 Storage (IS3 CloudHost atau AWS S3)
FILESYSTEM_DISK=s3
AWS_ACCESS_KEY_ID=your_access_key
AWS_SECRET_ACCESS_KEY=your_secret_key
AWS_DEFAULT_REGION=ap-southeast-1
AWS_BUCKET=your-bucket-name
AWS_ENDPOINT=https://is3.cloudhost.id
AWS_USE_PATH_STYLE_ENDPOINT=true
AWS_URL=https://is3.cloudhost.id/your-bucket-name

# OCR Service (Optional - untuk production)
OCR_SPACE_API_KEY=your_ocr_api_key

# Testing (Development Only)
ALLOW_FAKE_OCR=true  # Set false di production
```

### 3. Build & Run dengan Docker

```bash
# Build image
docker compose build --no-cache

# Start containers
docker compose up -d

# Check status
docker compose ps
```

### 4. Run Migrations

```bash
docker exec -it stiqrcanvas-be-prod php artisan migrate --force
```

### 5. Create Test User

```bash
docker exec -it stiqrcanvas-be-prod php artisan tinker

# Di dalam tinker:
App\Models\User::create([
    'name' => 'Staff User',
    'email' => 'staff@example.com',
    'password' => 'password123',
    'role' => 'staff'
]);
```

## API Endpoints

### Authentication

```bash
# Login
POST /api/login
Content-Type: application/json
{
  "email": "staff@example.com",
  "password": "password123"
}

# Response
{
  "success": true,
  "token": "1|xxx...",
  "user": {
    "id": 1,
    "name": "Staff User",
    "email": "staff@example.com",
    "role": "staff"
  }
}

# Logout
POST /api/logout
Authorization: Bearer {token}

# Get current user
GET /api/me
Authorization: Bearer {token}
```

### Messages / Screenshots

```bash
# Upload screenshot
POST /api/messages/upload
Authorization: Bearer {token}
Content-Type: multipart/form-data

# Parameters:
- screenshot: File (required) - Image file (jpg, png, gif, max 10MB)
- stage: Integer (required) - 0-7 (0 = canvassing, 1-7 = follow-up days)
- category: String (required) - umkm_fb, coffee_shop, atau restoran
- channel: String (optional) - instagram, tiktok, facebook, threads, whatsapp, other
- interaction_status: String (optional) - no_response, menolak, tertarik, menerima
- contact_number: String (optional) - Nomor kontak

# Response
{
  "success": true,
  "message": "Screenshot berhasil diupload",
  "data": {
    "id": 1,
    "stage": 0,
    "ocr_result": {...},
    "validation_status": "pending"
  }
}

# List messages
GET /api/messages?page=1&per_page=20
Authorization: Bearer {token}

# Get message detail
GET /api/messages/{id}
Authorization: Bearer {token}

# Response includes public S3 URL:
{
  "data": {...},
  "screenshot_url": "https://is3.cloudhost.id/bucket/screenshots/uuid.jpg"
}

# Delete message (staff only, pending status only)
DELETE /api/messages/{id}
Authorization: Bearer {token}
```

### Dashboard

```bash
# Get dashboard stats
GET /api/dashboard
Authorization: Bearer {token}
```

## Development

### Run Commands Inside Container

```bash
# Artisan commands
docker exec -it stiqrcanvas-be-prod php artisan <command>

# Tinker
docker exec -it stiqrcanvas-be-prod php artisan tinker

# Clear cache
docker exec -it stiqrcanvas-be-prod php artisan cache:clear
docker exec -it stiqrcanvas-be-prod php artisan config:clear
```

### View Logs

```bash
# Application logs
docker exec -it stiqrcanvas-be-prod tail -f storage/logs/laravel.log

# Container logs
docker compose logs -f backend

# Nginx logs
docker exec -it stiqrcanvas-be-prod tail -f /var/log/nginx/error.log
```

### Rebuild After Code Changes

```bash
# Rebuild image
docker compose build --no-cache backend

# Restart containers
docker compose up -d
```

## S3 Storage Configuration

Aplikasi ini menggunakan S3-compatible storage dengan konfigurasi:

- **Public Visibility**: File yang diupload otomatis public-readable
- **Random Filenames**: UUID untuk keamanan
- **Path**: `screenshots/{uuid}.{ext}`
- **URL Format**: `{AWS_URL}/screenshots/{uuid}.{ext}`

### Testing S3 Connection

```bash
docker exec -it stiqrcanvas-be-prod php artisan tinker

# Test write
Storage::disk('s3')->put('test.txt', 'Hello S3');

# Test read
Storage::disk('s3')->exists('test.txt');

# Get public URL
Storage::disk('s3')->url('screenshots/file.jpg');
```

## Testing Mode

Untuk development/testing tanpa OCR API:

1. Set `ALLOW_FAKE_OCR=true` di `.env`
2. Upload akan bypass OCR validation dan generate dummy data
3. **WAJIB set `ALLOW_FAKE_OCR=false` di production**

## Deployment

### Production Checklist

- [ ] Set `APP_ENV=production`
- [ ] Set `APP_DEBUG=false`
- [ ] Generate `APP_KEY` yang secure
- [ ] Set `ALLOW_FAKE_OCR=false`
- [ ] Konfigurasi `OCR_SPACE_API_KEY` yang valid
- [ ] Gunakan password database yang strong
- [ ] Setup SSL/TLS untuk HTTPS
- [ ] Configure firewall rules
- [ ] Setup backup untuk database dan S3
- [ ] Configure log rotation

### Environment Variables

Pastikan semua environment variables di-forward ke container via `docker-compose.yml`:

```yaml
environment:
  - APP_NAME=${APP_NAME}
  - APP_ENV=${APP_ENV}
  - APP_KEY=${APP_KEY}
  - DB_CONNECTION=${DB_CONNECTION}
  # ... dst
```

## Troubleshooting

### Port 80 Already in Use

```bash
# Check what's using port 80
sudo lsof -i :80

# Option 1: Stop the service
sudo systemctl stop nginx

# Option 2: Change port in docker-compose.yml
ports:
  - "8080:80"  # Use port 8080 instead
```

### Database Connection Failed

```bash
# Check postgres is running
docker compose ps

# Check postgres logs
docker compose logs postgres

# Verify DB credentials in .env match docker-compose.yml
```

### S3 Upload 403 Forbidden

```bash
# Verify credentials
docker exec stiqrcanvas-be-prod php artisan tinker
config('filesystems.disks.s3.key');
config('filesystems.disks.s3.secret');

# Test connection
Storage::disk('s3')->put('test.txt', 'test');
```

### File Permission Issues

```bash
# Fix storage permissions
docker exec -it stiqrcanvas-be-prod chmod -R 775 storage bootstrap/cache
docker exec -it stiqrcanvas-be-prod chown -R www-data:www-data storage bootstrap/cache
```

## License

Proprietary - All rights reserved.
