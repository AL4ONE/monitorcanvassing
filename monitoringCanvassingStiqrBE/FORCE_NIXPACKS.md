# Force Railway to Use Nixpacks Builder

## Problem
Railway masih menggunakan Dockerfile yang di-generate otomatis, bukan Nixpacks builder.

## Solution

### 1. Di Railway Dashboard

**Build Tab** → **Builder**:
- Pilih **"Nixpacks"** secara eksplisit
- JANGAN pilih "Railpack" atau "Dockerfile"

**Build Tab** → **Custom Build Command**:
- Biarkan KOSONG (jangan set apapun)
- Railway akan auto-detect dari composer.json

### 2. Verify Files

Pastikan:
- ✅ `railway.toml` ada dengan `builder = "nixpacks"`
- ✅ `railway.json` ada dengan `"builder": "NIXPACKS"`
- ✅ TIDAK ADA `Dockerfile` di root directory
- ✅ `composer.json` ada dengan PHP requirement

### 3. Update composer.lock (if needed)

Jika masih error tentang composer.lock, update di local:

```bash
cd monitoringCanvassingStiqrBE
composer update --no-dev --lock
git add composer.lock
git commit -m "Update composer.lock for PHP 8.3"
git push origin main
```

### 4. Alternative: Use Railway CLI

Install Railway CLI dan force builder:

```bash
npm i -g @railway/cli
railway login
railway link
railway variables set RAILWAY_BUILDER=nixpacks
```

## What Railway Should Do

Setelah force Nixpacks:
1. Detect Laravel dari composer.json
2. Install PHP 8.3
3. Install composer
4. Run `composer install --no-dev --optimize-autoloader`
5. Start dengan `php artisan serve`

## If Still Using Dockerfile

Jika Railway masih menggunakan Dockerfile:
1. Check Railway dashboard → Build → Builder
2. Pastikan pilih "Nixpacks" bukan "Dockerfile"
3. Redeploy service



