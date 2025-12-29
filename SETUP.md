# Setup Guide - Monitoring Canvassing STIOR

## Quick Start

### 1. Backend Setup

```bash
cd monitoringCanvassingStiqrBE

# Install dependencies
composer install
npm install

# Setup environment
cp .env.example .env
php artisan key:generate

# Configure database di .env
# DB_CONNECTION=mysql
# DB_HOST=127.0.0.1
# DB_DATABASE=monitoring_canvassing
# DB_USERNAME=root
# DB_PASSWORD=

# Install Laravel Sanctum
composer require laravel/sanctum
php artisan vendor:publish --provider="Laravel\Sanctum\SanctumServiceProvider"
php artisan migrate

# Run migrations
php artisan migrate

# Create storage link
php artisan storage:link

# Seed database (optional - untuk testing)
php artisan db:seed

# Start server
php artisan serve
```

### 2. Frontend Setup

```bash
cd MonitoringCanvassingStiqrFE

# Install dependencies
npm install

# Create .env file
echo "VITE_API_BASE_URL=http://localhost:8000/api" > .env

# Start dev server
npm run dev
```

### 3. OCR Setup (Optional)

1. Daftar di https://ocr.space/ocrapi
2. Dapatkan API key (free tier available)
3. Tambahkan ke `monitoringCanvassingStiqrBE/.env`:
```env
OCR_SPACE_API_KEY=your_api_key_here
```

## Default Login Credentials

Setelah run `php artisan db:seed`:

**Supervisor:**
- Email: supervisor@stiqr.com
- Password: password

**Staff:**
- Email: staff1@stiqr.com
- Password: password
- Email: staff2@stiqr.com
- Password: password

## Testing Flow

1. Login sebagai staff
2. Upload screenshot DM Instagram
3. Sistem akan:
   - Cek duplicate (hash file)
   - Run OCR untuk extract username, pesan, tanggal
   - Validasi continuity (untuk follow-up)
   - Simpan sebagai pending
4. Login sebagai supervisor
5. Lihat dashboard untuk monitoring
6. Quality check untuk approve/reject upload

## Troubleshooting

### Storage link tidak berfungsi
```bash
php artisan storage:link
```

### Permission error pada storage
```bash
chmod -R 775 storage
chmod -R 775 bootstrap/cache
```

### CORS error
Pastikan `config/cors.php` sudah dikonfigurasi dengan benar untuk development.

### OCR tidak bekerja
- Pastikan API key sudah di-set di `.env`
- Atau biarkan kosong, sistem tetap berjalan tapi OCR result akan kosong



