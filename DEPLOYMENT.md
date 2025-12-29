# Deployment Guide

Panduan deployment aplikasi Monitoring Canvassing STIQR ke staging environment.

## Tech Stack
- **Frontend**: React + Vite → Vercel
- **Backend**: Laravel → Railway
- **Database**: PostgreSQL → Railway

---

## 1. Setup Database (PostgreSQL di Railway)

### Langkah-langkah:
1. Login ke [Railway](https://railway.app)
2. Buat project baru
3. Klik **"New"** → **"Database"** → **"Add PostgreSQL"**
4. Tunggu sampai database siap
5. Catat environment variables berikut:
   - `PGHOST`
   - `PGPORT`
   - `PGDATABASE`
   - `PGUSER`
   - `PGPASSWORD`

---

## 2. Setup Backend (Laravel di Railway)

### Langkah-langkah:

1. **Push code ke GitHub** (jika belum)
   ```bash
   git add .
   git commit -m "Prepare for deployment"
   git push origin main
   ```

2. **Deploy di Railway**:
   - Login ke Railway
   - Di project yang sama (atau buat project baru)
   - Klik **"New"** → **"GitHub Repo"**
   - Pilih repository Anda
   - Pilih folder `monitoringCanvassingStiqrBE` sebagai root directory

3. **Setup Environment Variables**:
   Di Railway dashboard, tambahkan variables berikut:
   ```
   APP_NAME="Monitoring Canvassing STIQR"
   APP_ENV=production
   APP_KEY=base64:YOUR_APP_KEY_HERE
   APP_DEBUG=false
   APP_URL=https://your-backend-url.railway.app
   
   DB_CONNECTION=pgsql
   DB_HOST=${{Postgres.PGHOST}}
   DB_PORT=${{Postgres.PGPORT}}
   DB_DATABASE=${{Postgres.PGDATABASE}}
   DB_USERNAME=${{Postgres.PGUSER}}
   DB_PASSWORD=${{Postgres.PGPASSWORD}}
   
   OCR_SPACE_API_KEY=your_ocr_space_api_key
   ```

4. **Generate APP_KEY**:
   ```bash
   # Di local, jalankan:
   php artisan key:generate --show
   # Copy hasilnya ke APP_KEY di Railway
   ```

5. **Setup Build & Deploy**:
   Railway akan otomatis detect Laravel dan menjalankan:
   - `composer install`
   - `php artisan migrate` (jika ada migration)
   - `php artisan storage:link` (untuk public storage)

6. **Run Migration**:
   Setelah deploy pertama, jalankan migration:
   - Di Railway dashboard → **"Deployments"** → **"View Logs"**
   - Atau gunakan Railway CLI:
     ```bash
     railway run php artisan migrate
     ```

7. **Setup Storage Link**:
   ```bash
   railway run php artisan storage:link
   ```

8. **Catat Backend URL**:
   Railway akan generate URL seperti: `https://your-app-name.up.railway.app`
   Copy URL ini untuk digunakan di frontend.

---

## 3. Setup Frontend (React di Vercel)

### Langkah-langkah:

1. **Push code ke GitHub** (jika belum)

2. **Deploy di Vercel**:
   - Login ke [Vercel](https://vercel.com)
   - Klik **"Add New Project"**
   - Import repository dari GitHub
   - Pilih folder `MonitoringCanvassingStiqrFE` sebagai root directory

3. **Setup Environment Variables**:
   Di Vercel dashboard → **Settings** → **Environment Variables**, tambahkan:
   ```
   VITE_API_BASE_URL=https://your-backend-url.railway.app/api
   ```
   Ganti `your-backend-url.railway.app` dengan URL backend dari Railway.

4. **Setup Build Settings**:
   Vercel akan otomatis detect Vite:
   - **Framework Preset**: Vite
   - **Build Command**: `npm run build`
   - **Output Directory**: `dist`
   - **Install Command**: `npm install`

5. **Deploy**:
   Klik **"Deploy"** dan tunggu sampai selesai.

---

## 4. Post-Deployment Setup

### Backend (Railway):

1. **Run Seeder** (untuk create default users):
   ```bash
   railway run php artisan db:seed
   ```

2. **Setup CORS** (jika perlu):
   Update `config/cors.php` untuk allow frontend domain:
   ```php
   'allowed_origins' => [
       'https://your-frontend.vercel.app',
   ],
   ```

3. **Setup Storage**:
   Pastikan storage link sudah dibuat:
   ```bash
   railway run php artisan storage:link
   ```

### Frontend (Vercel):

1. **Verify API Connection**:
   - Buka aplikasi di Vercel
   - Coba login
   - Pastikan API calls berhasil

---

## 5. Environment Variables Summary

### Backend (Railway):
```
APP_NAME="Monitoring Canvassing STIQR"
APP_ENV=production
APP_KEY=base64:...
APP_DEBUG=false
APP_URL=https://your-backend.railway.app

DB_CONNECTION=pgsql
DB_HOST=${{Postgres.PGHOST}}
DB_PORT=${{Postgres.PGPORT}}
DB_DATABASE=${{Postgres.PGDATABASE}}
DB_USERNAME=${{Postgres.PGUSER}}
DB_PASSWORD=${{Postgres.PGPASSWORD}}

OCR_SPACE_API_KEY=...
```

### Frontend (Vercel):
```
VITE_API_BASE_URL=https://your-backend.railway.app/api
```

---

## 6. Troubleshooting

### Database Connection Error:
- Pastikan PostgreSQL service sudah running di Railway
- Check environment variables DB_* sudah benar
- Pastikan database sudah dibuat

### CORS Error:
- Update `config/cors.php` di Laravel
- Atau tambahkan middleware CORS di `bootstrap/app.php`

### Storage Files Not Accessible:
- Pastikan `php artisan storage:link` sudah dijalankan
- Check permissions di storage folder

### Migration Error:
- Pastikan database connection sudah benar
- Check migration files tidak ada error
- Run `php artisan migrate:fresh` jika perlu (HATI-HATI: akan hapus semua data)

---

## 7. Update Database Schema

Jika ada perubahan migration:
```bash
# Di Railway CLI atau via dashboard
railway run php artisan migrate
```

---

## 8. Monitoring

### Railway:
- Check logs di Railway dashboard
- Monitor resource usage
- Setup alerts jika perlu

### Vercel:
- Check deployment logs
- Monitor build times
- Setup analytics jika perlu

---

## Notes:
- Pastikan semua sensitive data menggunakan environment variables
- Jangan commit `.env` file ke Git
- Backup database secara berkala
- Monitor error logs secara rutin



