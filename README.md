# Monitoring Canvassing STIOR

Sistem monitoring untuk aktivitas canvassing online karyawan dengan fitur OCR untuk ekstraksi data dari screenshot DM Instagram.

## Fitur Utama

### Untuk Staff (Karyawan)
- ✅ Upload screenshot DM dengan UMKM
- ✅ OCR otomatis untuk extract:
  - Instagram username
  - Pesan/teks dari screenshot
  - Tanggal dari screenshot
- ✅ Validasi duplicate file (mencegah upload screenshot yang sama)
- ✅ Validasi continuity (follow-up harus berurutan)
- ✅ Dashboard untuk melihat progress harian
- ✅ Target: 50 canvassing + 50 follow-up per hari

### Untuk Supervisor (Atasan)
- ✅ Dashboard monitoring semua staff
- ✅ Quality check untuk setiap upload
- ✅ Deteksi red flags:
  - Duplicate screenshot
  - Follow-up tidak berurutan
  - Username mismatch
- ✅ Timeline untuk melihat history canvassing per prospect

## Tech Stack

### Backend
- Laravel 12
- PHP 8.2+
- MySQL/PostgreSQL/SQLite
- Laravel Sanctum (API Authentication)
- OCR.space API (untuk OCR)

### Frontend
- React 19
- React Router
- Axios
- Tailwind CSS
- Vite

## Setup

### Backend Setup

1. Install dependencies:
```bash
cd monitoringCanvassingStiqrBE
composer install
npm install
```

2. Setup environment:
```bash
cp .env.example .env
php artisan key:generate
```

3. Configure database di `.env`:
```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=monitoring_canvassing
DB_USERNAME=root
DB_PASSWORD=
```

4. Configure OCR API key (optional, bisa pakai free tier):
```env
OCR_SPACE_API_KEY=your_api_key_here
```

5. Run migrations:
```bash
php artisan migrate
```

6. Create storage link:
```bash
php artisan storage:link
```

7. Install Laravel Sanctum:
```bash
composer require laravel/sanctum
php artisan vendor:publish --provider="Laravel\Sanctum\SanctumServiceProvider"
php artisan migrate
```

8. Start server:
```bash
php artisan serve
```

### Frontend Setup

1. Install dependencies:
```bash
cd MonitoringCanvassingStiqrFE
npm install
```

2. Setup environment:
Buat file `.env`:
```env
VITE_API_BASE_URL=http://localhost:8000/api
```

3. Start dev server:
```bash
npm run dev
```

## Database Schema

### Tables
- `users` - User dengan role (staff/supervisor)
- `prospects` - Data UMKM/merchant
- `canvassing_cycles` - Siklus canvassing (1 prospect = 1 cycle)
- `messages` - Screenshot upload dengan OCR result
- `quality_checks` - Review dari supervisor

## API Endpoints

### Authentication
- `POST /api/login` - Login
- `POST /api/logout` - Logout
- `GET /api/me` - Get current user

### Messages
- `POST /api/messages/upload` - Upload screenshot
- `GET /api/messages` - List messages
- `GET /api/messages/{id}` - Get message detail

### Dashboard
- `GET /api/dashboard` - Get dashboard stats

### Quality Check (Supervisor only)
- `GET /api/quality-checks` - List pending reviews
- `GET /api/quality-checks/{id}` - Get message detail for review
- `POST /api/quality-checks/{id}/review` - Approve/reject message

## Flow Aplikasi

### Flow Staff
1. Staff login
2. Upload screenshot DM
3. Sistem:
   - Generate hash file (cek duplicate)
   - Run OCR untuk extract data
   - Validasi continuity (untuk follow-up)
   - Simpan sebagai pending
4. Supervisor review dan approve/reject

### Flow Supervisor
1. Supervisor login
2. Lihat dashboard dengan stats semua staff
3. Quality check:
   - Lihat screenshot
   - Lihat OCR result
   - Lihat timeline history
   - Approve atau reject dengan catatan

## Validasi

### Duplicate Detection
- Setiap file di-hash dengan SHA-256
- Jika hash sudah ada, upload ditolak

### Continuity Check
- Follow-up stage N harus ada stage N-1 sebelumnya
- Prospect harus sama dengan canvassing awal
- Staff harus sama dengan yang melakukan canvassing

### Daily Target
- Canvassing: 50 per hari
- Follow-up: 50 per hari
- Sistem track progress real-time

## OCR Configuration

Sistem menggunakan OCR.space API (free tier available). Untuk setup:

1. Daftar di https://ocr.space/ocrapi
2. Dapatkan API key
3. Tambahkan ke `.env`:
```env
OCR_SPACE_API_KEY=your_key_here
```

Jika tidak ada API key, sistem akan tetap berjalan tapi OCR extraction akan kosong (user bisa input manual).

## Development

### Backend
```bash
cd monitoringCanvassingStiqrBE
php artisan serve
```

### Frontend
```bash
cd MonitoringCanvassingStiqrFE
npm run dev
```

## Production Deployment

1. Build frontend:
```bash
cd MonitoringCanvassingStiqrFE
npm run build
```

2. Copy build files ke Laravel public directory atau serve separately

3. Setup Laravel untuk production:
```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

## Notes

- Screenshot disimpan di `storage/app/public/screenshots`
- Pastikan storage link sudah dibuat: `php artisan storage:link`
- OCR result disimpan sebagai suggestion, supervisor tetap perlu review
- Semua upload status awal adalah `pending`, perlu approval dari supervisor

