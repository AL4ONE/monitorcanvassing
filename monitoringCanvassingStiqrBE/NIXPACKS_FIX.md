# Fix Nixpacks Build Error

## Problem
Error: `undefined variable 'composer'`

Nixpacks tidak bisa menemukan package `composer` karena format yang salah.

## Solution

Format yang benar untuk composer di Nixpacks adalah `php83Packages.composer`, bukan hanya `composer`.

## Fixed Configuration

File `nixpacks.toml` sudah di-update dengan format yang benar:

```toml
[phases.setup]
nixPkgs = ["php83", "php83Packages.composer", "nodejs-18_x"]
```

## Alternative: Let Railway Auto-Detect

Jika masih error, bisa juga biarkan Railway auto-detect PHP dan composer:

**Option 1**: Hapus `nixpacks.toml` dan biarkan Railway auto-detect
- Railway akan otomatis detect Laravel project
- Akan install PHP dan composer secara otomatis

**Option 2**: Gunakan format minimal
```toml
[phases.setup]
nixPkgs = ["php83", "nodejs-18_x"]

[phases.install]
cmds = [
  "composer install --no-dev --optimize-autoloader --no-interaction",
  "php artisan config:cache",
  "php artisan route:cache",
  "php artisan view:cache"
]

[start]
cmd = "php artisan serve --host=0.0.0.0 --port=$PORT"
```

Railway biasanya sudah include composer di PHP package, jadi tidak perlu specify secara eksplisit.

## Recommended: Minimal Config

Coba hapus `nixpacks.toml` dan biarkan Railway auto-detect. Railway sangat baik dalam auto-detecting Laravel projects.

