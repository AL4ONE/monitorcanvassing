# Railway Configuration Guide

## Current Setup Issues & Fixes

### 1. Port Configuration

**Problem**: Railway menggunakan port 8080, tapi Laravel default port 8000.

**Solution**: Update start command untuk menggunakan `$PORT` environment variable (Railway akan set ini otomatis).

### 2. Start Command

**Current (WRONG)**:
```
php artisan migrate    php artisan db:seed    php artisan storage:link
```

**Correct**: 
- Migration dan seeder harus dijalankan sebagai **pre-deploy step** atau manual setelah deploy pertama
- Start command hanya untuk menjalankan server

### 3. Recommended Configuration

#### In Railway Dashboard:

**Start Command**:
```
php artisan serve --host=0.0.0.0 --port=$PORT
```

**Pre-Deploy Step** (optional, untuk auto-migrate):
```
php artisan migrate --force && php artisan db:seed --force && php artisan storage:link
```

**Target Port**: `8080` (atau biarkan Railway auto-detect dari `$PORT`)

---

## Step-by-Step Fix

### 1. Update Start Command

Di Railway Dashboard → **Deploy** tab → **Start Command**:
```
php artisan serve --host=0.0.0.0 --port=$PORT
```

### 2. Setup Pre-Deploy (Optional)

Jika ingin auto-migrate setiap deploy:
- Di Railway Dashboard → **Deploy** tab → **Add pre-deploy step**
- Command:
```
php artisan migrate --force && php artisan db:seed --force && php artisan storage:link
```

**Note**: `--force` flag diperlukan karena di production tidak ada interactive prompt.

### 3. Manual Setup (Recommended untuk pertama kali)

Setelah deploy pertama, jalankan manual via Railway CLI atau dashboard:

```bash
railway run php artisan migrate
railway run php artisan db:seed
railway run php artisan storage:link
```

### 4. Port Configuration

- **Target Port**: Biarkan Railway auto-detect dari `$PORT` atau set ke `8080`
- Laravel akan otomatis menggunakan port dari `$PORT` environment variable

---

## Environment Variables Checklist

Pastikan sudah set semua variables:

```
APP_NAME="Monitoring Canvassing STIQR"
APP_ENV=production
APP_KEY=base64:xxxxx
APP_DEBUG=false
APP_URL=https://monitorcanvassing-production.up.railway.app

DB_CONNECTION=pgsql
DB_HOST=${{Postgres.PGHOST}}
DB_PORT=${{Postgres.PGPORT}}
DB_DATABASE=${{Postgres.PGDATABASE}}
DB_USERNAME=${{Postgres.PGUSER}}
DB_PASSWORD=${{Postgres.PGPASSWORD}}

OCR_SPACE_API_KEY=your_key_here
```

---

## Verify Deployment

1. Check logs di Railway dashboard
2. Test health endpoint: `https://monitorcanvassing-production.up.railway.app/up`
3. Should return: `{"status":"ok"}`

---

## Troubleshooting

### Port Already in Use
- Pastikan start command menggunakan `$PORT`
- Railway akan set `$PORT` otomatis

### Migration Error
- Pastikan database connection sudah benar
- Check environment variables `DB_*`
- Run migration manual: `railway run php artisan migrate`

### Storage Link Error
- Run manual: `railway run php artisan storage:link`
- Check storage permissions

