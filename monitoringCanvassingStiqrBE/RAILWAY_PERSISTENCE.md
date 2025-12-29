# Mengatasi Masalah Gambar Hilang saat Redeploy di Railway

Masalah ini terjadi karena sistem file Railway bersifat **ephemeral** (sementara). Setiap kali Anda melakukan redeploy, container lama dihancurkan dan container baru dibuat, sehingga semua file yang diupload ke disk lokal (`storage/app/public`) akan hilang.

Ada dua solusi untuk masalah ini:

## Solusi 1: Menggunakan Railway Volume (Recommended for Staging)

Solusi paling mudah untuk staging adalah menggunakan fitur **Volume** dari Railway agar folder storage menjadi persisten.

### Langkah-langkah:

1.  Buka Dashboard Railway.
2.  Pilih project **Monitoring Canvassing Stiqr**.
3.  Klik card service Backend (monitoringCanvassingStiqrBE).
4.  Pilih tab **Volumes**.
5.  Klik **Add Volume**.
6.  Isi **Mount Path** dengan path absolut ke folder storage public Laravel:
    ```
    /app/storage/app/public
    ```
    *(Path ini adalah default untuk aplikasi Nixpacks/Laravel di Railway)*
7.  Klik **Add**.
8.  Railway akan otomatis me-restart service Anda.

Setelah ini, file yang diupload akan disimpan di Volume terpisah yang tidak akan hilang saat redeploy.

> **Catatan**: 
> - Pastikan command `php artisan storage:link` dijalankan saat deploy (lihat `RAILWAY_CONFIG.md`).
> - Jika gambar masih 404, coba jalankan `php artisan storage:link` via Railway CLI/Command sehabis volume dipasang.

## Solusi 2: Menggunakan Cloud Storage (AWS S3, R2, dll) - (Recommended for Production)

Untuk aplikasi production yang scalable, disarankan menggunakan Object Storage terpisah seperti AWS S3, Cloudflare R2, atau DigitalOcean Spaces.

### Langkah-langkah:

1.  Buat bucket di provider pilihan (misal AWS S3).
2.  Di Railway Dashboard, masuk ke tab **Variables** service backend.
3.  Tambahkan variables berikut:

    ```env
    FILESYSTEM_DISK=s3
    AWS_ACCESS_KEY_ID=your-key-id
    AWS_SECRET_ACCESS_KEY=your-secret-key
    AWS_DEFAULT_REGION=ap-southeast-1
    AWS_BUCKET=your-bucket-name
    AWS_URL=https://your-bucket-url
    ```
4.  Laravel otomatis akan menggunakan driver S3 untuk menyimpan file.

---

## Ringkasan
Untuk kasus Anda sekarang ("staging" dan butuh cepat), **Solusi 1 (Railway Volume)** adalah yang paling tepat. Gunakan mount path `/app/storage/app/public`.
