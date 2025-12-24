# Fix Storage Link di Railway

## Problem
Gambar tidak muncul dengan error: "Gagal memuat gambar. Pastikan storage link sudah dibuat."

## Solution

### 1. Run Storage Link Command di Railway

**Via Railway Dashboard**:
1. Klik service backend Anda
2. Klik tab **"Deployments"**
3. Klik **"Run Command"** atau gunakan terminal
4. Jalankan:
   ```bash
   php artisan storage:link
   ```

**Via Railway CLI**:
```bash
railway run php artisan storage:link
```

### 2. Verify Storage Link

Setelah run command, check apakah link sudah dibuat:
```bash
railway run ls -la public/storage
```

Seharusnya ada symlink ke `../storage/app/public`

### 3. Check Storage Path

Pastikan file tersimpan di:
- `storage/app/public/screenshots/`
- Dan bisa diakses via: `public/storage/screenshots/`

### 4. Update Pre-Deploy Command (Optional)

Jika ingin auto-create storage link setiap deploy, tambahkan di Railway Dashboard → Deploy → Pre-Deploy:

```
php artisan storage:link || true
```

Flag `|| true` akan skip error jika link sudah ada.

### 5. Alternative: Use Public Disk Directly

Jika storage link tidak bekerja, bisa update `QualityCheckController` untuk menggunakan full path:

```php
$screenshotUrl = $message->screenshot_path 
    ? url('storage/' . $message->screenshot_path)
    : null;
```

Pastikan `APP_URL` sudah benar di Railway environment variables.

## Verify

1. Test screenshot URL langsung:
   ```
   https://monitorcanvassing-production.up.railway.app/storage/screenshots/FILENAME.jpg
   ```

2. Check browser console untuk error 404 atau CORS

3. Check Railway logs untuk error saat akses file

## Troubleshooting

### Link Tidak Berfungsi
- Pastikan `APP_URL` sudah benar
- Check permissions di storage folder
- Verify file benar-benar ada di `storage/app/public/screenshots/`

### 404 Error
- Pastikan storage link sudah dibuat
- Check `public/storage` folder exists
- Verify symlink pointing ke correct path

### Permission Error
- Check file permissions di Railway
- Pastikan Laravel bisa write ke storage folder

