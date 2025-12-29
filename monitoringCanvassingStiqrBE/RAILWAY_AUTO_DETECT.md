# Railway Auto-Detect Configuration

## Problem
Railway tidak bisa detect composer dengan benar dari nixpacks.toml.

## Solution
Hapus `nixpacks.toml` dan biarkan Railway **auto-detect** Laravel project.

Railway sangat baik dalam auto-detecting Laravel projects dan akan:
- Auto-detect PHP version dari `composer.json`
- Auto-install composer
- Auto-run `composer install`
- Auto-detect start command

## What Railway Will Auto-Detect

Railway akan otomatis:
1. Detect Laravel dari `composer.json`
2. Install PHP 8.3 (dari requirement di composer.json)
3. Install composer
4. Run `composer install --no-dev --optimize-autoloader`
5. Detect start command: `php artisan serve`

## Manual Override (if needed)

Jika perlu override, gunakan `railway.toml`:

```toml
[build]
builder = "nixpacks"

[deploy]
startCommand = "php artisan serve --host=0.0.0.0 --port=$PORT"
healthcheckPath = "/up"
```

## Environment Variables Still Needed

Pastikan set di Railway dashboard:
- `APP_KEY`
- `APP_URL`
- `DB_*` variables
- `OCR_SPACE_API_KEY`

## Pre-Deploy Commands

Set di Railway dashboard → Deploy → Pre-Deploy:
```
php artisan migrate --force && php artisan db:seed --force && php artisan storage:link
```

Atau jalankan manual setelah deploy pertama.



