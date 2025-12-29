# Quick Start Deployment Guide

Panduan cepat untuk deploy aplikasi ke staging.

## Prerequisites
- Akun GitHub
- Akun Railway (https://railway.app)
- Akun Vercel (https://vercel.com)
- OCR.space API Key

---

## Step 1: Setup PostgreSQL di Railway

1. Login Railway → New Project
2. Add PostgreSQL Database
3. Catat credentials (akan otomatis jadi env vars)

---

## Step 2: Deploy Backend ke Railway

1. Di Railway project yang sama:
   - New → GitHub Repo
   - Pilih repo Anda
   - **Root Directory**: `monitoringCanvassingStiqrBE`

2. **Environment Variables** (di Railway):
   ```
   APP_NAME="Monitoring Canvassing STIQR"
   APP_ENV=production
   APP_KEY=base64:PASTE_KEY_DARI_PHP_ARTISAN_KEY_GENERATE
   APP_DEBUG=false
   APP_URL=https://YOUR_APP_NAME.up.railway.app
   
   DB_CONNECTION=pgsql
   DB_HOST=${{Postgres.PGHOST}}
   DB_PORT=${{Postgres.PGPORT}}
   DB_DATABASE=${{Postgres.PGDATABASE}}
   DB_USERNAME=${{Postgres.PGUSER}}
   DB_PASSWORD=${{Postgres.PGPASSWORD}}
   
   OCR_SPACE_API_KEY=your_key_here
   ```

3. **Generate APP_KEY** (di local):
   ```bash
   cd monitoringCanvassingStiqrBE
   php artisan key:generate --show
   # Copy hasilnya ke APP_KEY di Railway
   ```

4. **Setelah deploy pertama**, jalankan:
   ```bash
   # Via Railway CLI atau di dashboard → Deployments → Run Command
   railway run php artisan migrate
   railway run php artisan db:seed
   railway run php artisan storage:link
   ```

5. **Catat Backend URL**: `https://YOUR_APP_NAME.up.railway.app`

---

## Step 3: Deploy Frontend ke Vercel

1. Login Vercel → Add New Project
2. Import dari GitHub
3. **Root Directory**: `MonitoringCanvassingStiqrFE`

4. **Environment Variables**:
   ```
   VITE_API_BASE_URL=https://YOUR_APP_NAME.up.railway.app/api
   ```
   (Ganti dengan URL backend dari Railway)

5. Deploy!

---

## Step 4: Setup CORS (jika perlu)

Jika ada CORS error, update `bootstrap/app.php`:

```php
->withMiddleware(function (Middleware $middleware): void {
    $middleware->api(prepend: [
        \Illuminate\Http\Middleware\HandleCors::class,
    ]);
    
    $middleware->alias([
        'role' => \App\Http\Middleware\RoleMiddleware::class,
    ]);
})
```

Atau install package:
```bash
composer require fruitcake/laravel-cors
```

---

## Step 5: Verify

1. Buka frontend URL di Vercel
2. Coba login dengan default user:
   - Staff: `staff@example.com` / `password`
   - Supervisor: `supervisor@example.com` / `password`

---

## Troubleshooting

### Database Error
- Check env vars DB_* sudah benar
- Pastikan PostgreSQL service running

### CORS Error
- Update CORS config di Laravel
- Check frontend API URL sudah benar

### Storage Error
- Run `php artisan storage:link`
- Check permissions

### Migration Error
- Check database connection
- Run `php artisan migrate:fresh` (HATI-HATI!)

---

## Railway CLI (Optional)

Install Railway CLI untuk manage via terminal:
```bash
npm i -g @railway/cli
railway login
railway link
railway run php artisan migrate
```

---

## Notes

- Jangan commit `.env` file
- Backup database secara berkala
- Monitor logs di Railway & Vercel dashboard



