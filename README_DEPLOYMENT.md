# 🚀 Deployment Guide - Monitoring Canvassing STIQR

Panduan lengkap untuk deploy aplikasi ke staging environment.

## 📋 Tech Stack
- **Frontend**: React + Vite → **Vercel**
- **Backend**: Laravel → **Railway**
- **Database**: PostgreSQL → **Railway**

---

## 🗄️ Step 1: Setup PostgreSQL di Railway

1. Login ke [Railway](https://railway.app)
2. Klik **"New Project"**
3. Klik **"New"** → **"Database"** → **"Add PostgreSQL"**
4. Tunggu sampai database siap (sekitar 1-2 menit)
5. Database credentials akan otomatis tersedia sebagai environment variables:
   - `PGHOST`
   - `PGPORT`
   - `PGDATABASE`
   - `PGUSER`
   - `PGPASSWORD`

**Catatan**: Simpan project ini, kita akan deploy backend di project yang sama.

---

## 🔧 Step 2: Deploy Backend (Laravel) ke Railway

### 2.1 Push Code ke GitHub
```bash
git add .
git commit -m "Prepare for deployment"
git push origin main
```

### 2.2 Deploy di Railway

1. Di Railway project yang sama (atau buat project baru)
2. Klik **"New"** → **"GitHub Repo"**
3. Pilih repository Anda
4. **PENTING**: Set **Root Directory** ke `monitoringCanvassingStiqrBE`
   - Di Railway dashboard → Settings → Root Directory → `monitoringCanvassingStiqrBE`

### 2.3 Setup PHP Version

Railway akan otomatis detect PHP 8.3 dari `nixpacks.toml`. Jika tidak, tambahkan variable:
```
PHP_VERSION=8.3
```

### 2.4 Setup Environment Variables

Di Railway dashboard → **Variables** tab, tambahkan:

```env
APP_NAME="Monitoring Canvassing STIQR"
APP_ENV=production
APP_KEY=base64:PASTE_KEY_DISINI
APP_DEBUG=false
APP_URL=https://YOUR_APP_NAME.up.railway.app

DB_CONNECTION=pgsql
DB_HOST=${{Postgres.PGHOST}}
DB_PORT=${{Postgres.PGPORT}}
DB_DATABASE=${{Postgres.PGDATABASE}}
DB_USERNAME=${{Postgres.PGUSER}}
DB_PASSWORD=${{Postgres.PGPASSWORD}}

OCR_SPACE_API_KEY=your_ocr_space_api_key_here
```

**Cara Generate APP_KEY**:
```bash
cd monitoringCanvassingStiqrBE
php artisan key:generate --show
# Copy hasilnya (format: base64:xxxxx) ke APP_KEY di Railway
```

**Cara dapat APP_URL**:
- Setelah deploy pertama, Railway akan generate URL seperti: `https://monitoring-canvassing-stiqr-production.up.railway.app`
- Copy URL tersebut ke `APP_URL`

### 2.5 Setup Database

Setelah deploy pertama berhasil, jalankan migration:

**Via Railway Dashboard**:
1. Klik service backend Anda
2. Klik tab **"Deployments"**
3. Klik **"View Logs"**
4. Klik **"Run Command"** atau gunakan terminal di dashboard
5. Jalankan:
   ```bash
   php artisan migrate
   php artisan db:seed
   php artisan storage:link
   ```

**Via Railway CLI** (optional):
```bash
npm i -g @railway/cli
railway login
railway link
railway run php artisan migrate
railway run php artisan db:seed
railway run php artisan storage:link
```

### 2.6 Catat Backend URL

Setelah deploy, catat URL backend Anda:
```
https://YOUR_APP_NAME.up.railway.app
```

Ini akan digunakan di frontend.

---

## 🎨 Step 3: Deploy Frontend (React) ke Vercel

### 3.1 Push Code ke GitHub
(Sudah dilakukan di step 2.1)

### 3.2 Deploy di Vercel

1. Login ke [Vercel](https://vercel.com)
2. Klik **"Add New Project"**
3. Import repository dari GitHub
4. **PENTING**: Set **Root Directory** ke `MonitoringCanvassingStiqrFE`
   - Di Vercel → Settings → General → Root Directory → `MonitoringCanvassingStiqrFE`

### 3.3 Setup Environment Variables

Di Vercel dashboard → **Settings** → **Environment Variables**, tambahkan:

```env
VITE_API_BASE_URL=https://YOUR_APP_NAME.up.railway.app/api
```

**Ganti** `YOUR_APP_NAME.up.railway.app` dengan URL backend dari Railway (step 2.5).

### 3.4 Build Settings

Vercel akan otomatis detect Vite, tapi pastikan:
- **Framework Preset**: Vite
- **Build Command**: `npm run build`
- **Output Directory**: `dist`
- **Install Command**: `npm install`

### 3.5 Deploy

Klik **"Deploy"** dan tunggu sampai selesai.

---

## ✅ Step 4: Post-Deployment Checklist

### Backend (Railway)

- [ ] Migration sudah dijalankan
- [ ] Seeder sudah dijalankan (untuk default users)
- [ ] Storage link sudah dibuat
- [ ] Environment variables sudah benar
- [ ] Backend URL bisa diakses (test: `https://YOUR_URL/up`)

### Frontend (Vercel)

- [ ] Environment variable `VITE_API_BASE_URL` sudah di-set
- [ ] Build berhasil tanpa error
- [ ] Frontend URL bisa diakses

### Testing

- [ ] Buka frontend URL di browser
- [ ] Coba login dengan:
  - **Staff**: `staff@example.com` / `password`
  - **Supervisor**: `supervisor@example.com` / `password`
- [ ] Test upload screenshot
- [ ] Test quality check (jika sebagai supervisor)

---

## 🔍 Troubleshooting

### Database Connection Error

**Error**: `SQLSTATE[HY000] [2002] Connection refused`

**Solusi**:
1. Pastikan PostgreSQL service sudah running di Railway
2. Check environment variables `DB_*` sudah benar
3. Pastikan menggunakan `${{Postgres.PGHOST}}` format (bukan hardcode)

### CORS Error

**Error**: `Access to XMLHttpRequest blocked by CORS policy`

**Solusi**:
1. CORS sudah di-enable di `bootstrap/app.php`
2. Pastikan frontend URL sudah benar di `VITE_API_BASE_URL`
3. Jika masih error, install package:
   ```bash
   composer require fruitcake/laravel-cors
   ```

### Storage Files Not Accessible

**Error**: Screenshot tidak muncul

**Solusi**:
1. Pastikan `php artisan storage:link` sudah dijalankan
2. Check permissions di storage folder
3. Pastikan `APP_URL` sudah benar

### Migration Error

**Error**: Migration failed

**Solusi**:
1. Check database connection sudah benar
2. Pastikan semua migration files tidak ada error
3. Run `php artisan migrate:status` untuk check status
4. Jika perlu reset (HATI-HATI: akan hapus semua data):
   ```bash
   railway run php artisan migrate:fresh --seed
   ```

### Build Error di Vercel

**Error**: Build failed

**Solusi**:
1. Check build logs di Vercel
2. Pastikan `VITE_API_BASE_URL` sudah di-set
3. Pastikan root directory sudah benar (`MonitoringCanvassingStiqrFE`)

---

## 📝 Environment Variables Summary

### Backend (Railway)
```env
APP_NAME="Monitoring Canvassing STIQR"
APP_ENV=production
APP_KEY=base64:xxxxx
APP_DEBUG=false
APP_URL=https://your-backend.railway.app

DB_CONNECTION=pgsql
DB_HOST=${{Postgres.PGHOST}}
DB_PORT=${{Postgres.PGPORT}}
DB_DATABASE=${{Postgres.PGDATABASE}}
DB_USERNAME=${{Postgres.PGUSER}}
DB_PASSWORD=${{Postgres.PGPASSWORD}}

OCR_SPACE_API_KEY=your_key_here
```

### Frontend (Vercel)
```env
VITE_API_BASE_URL=https://your-backend.railway.app/api
```

---

## 🔄 Update Database Schema

Jika ada perubahan migration baru:

```bash
# Via Railway CLI
railway run php artisan migrate

# Atau via Railway Dashboard → Run Command
php artisan migrate
```

---

## 📊 Monitoring

### Railway
- Check logs: Dashboard → Service → Deployments → View Logs
- Monitor resource usage: Dashboard → Service → Metrics
- Setup alerts: Dashboard → Settings → Notifications

### Vercel
- Check deployment logs: Dashboard → Project → Deployments
- Monitor build times: Dashboard → Project → Analytics
- Check errors: Dashboard → Project → Logs

---

## 🔐 Security Notes

- ✅ Jangan commit `.env` file ke Git
- ✅ Gunakan environment variables untuk semua sensitive data
- ✅ Set `APP_DEBUG=false` di production
- ✅ Backup database secara berkala
- ✅ Monitor error logs secara rutin
- ✅ Update dependencies secara berkala

---

## 📞 Support

Jika ada masalah:
1. Check logs di Railway & Vercel
2. Check error messages di browser console
3. Verify environment variables sudah benar
4. Test API endpoint langsung (Postman/curl)

---

## 🎉 Success!

Setelah semua step selesai, aplikasi Anda sudah live di:
- **Frontend**: `https://your-app.vercel.app`
- **Backend**: `https://your-app.up.railway.app`
- **Database**: PostgreSQL di Railway

Selamat! 🚀

