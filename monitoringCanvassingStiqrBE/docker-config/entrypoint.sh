#!/bin/sh
# ============================================================
# 🚀 Laravel Application Entrypoint
# ============================================================

set -e

echo "🚀 Starting Laravel application..."

# Set proper permissions
chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache
chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

# Run Laravel optimizations
cd /var/www/html

# Generate APP_KEY if not set
if [ -z "$APP_KEY" ] || [ "$APP_KEY" = "base64:YOUR_32_CHAR_KEY_HERE_CHANGE_THIS_IN_PRODUCTION" ]; then
    echo "🔑 Generating APP_KEY..."
    export APP_KEY=$(php artisan key:generate --show)
    echo "Generated APP_KEY: $APP_KEY"
fi

# Laravel menggunakan environment variables langsung
echo "📝 Caching Laravel configuration dari environment variables..."
php artisan config:cache || true
php artisan route:cache || true
php artisan view:cache || true

# Run database migrations (jika database tersedia)
echo "🔄 Running database migrations..."
php artisan migrate --force || echo "⚠️  Migration failed or database not available, continuing..."

echo "✅ Laravel setup complete, starting services..."

# Execute the main command (supervisord)
exec "$@"

[ -n "$VITE_OSS_URL" ] && echo "  VITE_OSS_URL: $VITE_OSS_URL" >> "$LOG_FILE"

# ============================================================
# ✏️ Step 3: Update placeholder & URL di file hasil build
# ============================================================
for f in $TARGET_FILES; do
  [ -e "$f" ] || continue

  if grep -qE "(http://)?localhost(:[0-9]{2,5})?|__VITE_API_URL__|__VITE_API_BASE_URL__|__VITE_IMAGE_TOKEN__|__VITE_OSS_URL__" "$f"; then
    echo "$(date '+%Y-%m-%d %H:%M:%S') 🛠️  Mengupdate file: $f" >> "$LOG_FILE"
    cp "$f" "$BACKUP_DIR/"

    # Ganti pola localhost / placeholder ke nilai environment
    sed -i \
      -e "s#http://localhost:4000#$VITE_API_URL#g" \
      -e "s#http://localhost:80#$VITE_API_URL#g" \
      -e "s#http://localhost#$VITE_API_URL#g" \
      -e "s#localhost:4000#$VITE_API_URL#g" \
      -e "s#localhost:80#$VITE_API_URL#g" \
      -e "s#localhost#$VITE_API_URL#g" \
      -e "s#__VITE_API_URL__#$VITE_API_URL#g" \
      "$f"

    # Ganti tambahan placeholder
    [ -n "$VITE_API_BASE_URL" ] && sed -i "s#__VITE_API_BASE_URL__#$VITE_API_BASE_URL#g" "$f"
    [ -n "$VITE_IMAGE_TOKEN" ] && sed -i "s#__VITE_IMAGE_TOKEN__#$VITE_IMAGE_TOKEN#g" "$f"
    [ -n "$VITE_OSS_URL" ] && sed -i "s#__VITE_OSS_URL__#$VITE_OSS_URL#g" "$f"

    echo "$(date '+%Y-%m-%d %H:%M:%S') ✅ File selesai diupdate: $f" >> "$LOG_FILE"
  fi
done

# ============================================================
# 🧼 Step 4: Bersihkan port number dari domain target (opsional)
# ============================================================
# Hanya jalankan jika ada VITE_API_URL atau VITE_API_BASE_URL yang di-set
if [ -n "$VITE_API_URL" ] || [ -n "$VITE_API_BASE_URL" ]; then
  TARGET_URL="${VITE_API_URL:-$VITE_API_BASE_URL}"
  echo "$(date '+%Y-%m-%d %H:%M:%S') 🧹 Mulai membersihkan port number dari domain: $TARGET_URL" >> "$LOG_FILE"

  DOMAIN=$(echo "$TARGET_URL" | sed -E 's#https?://([^/:]+).*#\1#')

  for f in /usr/share/nginx/html/assets/*.{js,css,html,json}; do
    [ -e "$f" ] || continue

    if grep -qE "${DOMAIN}:[0-9]{2,5}" "$f"; then
      echo "$(date '+%Y-%m-%d %H:%M:%S') ⚠️  Port ditemukan di $f" >> "$LOG_FILE"
      cp "$f" "$BACKUP_DIR/" 2>/dev/null
      sed -i -E "s#(${DOMAIN}):[0-9]{2,5}#\1#g" "$f"
      echo "$(date '+%Y-%m-%d %H:%M:%S') ✅ Port berhasil dihapus di $f" >> "$LOG_FILE"
    fi
  done
fi

echo "$(date '+%Y-%m-%d %H:%M:%S') 🎯 Semua proses selesai!" >> "$LOG_FILE"
echo "============================================================" >> "$LOG_FILE"

# ============================================================
# 🚀 Jalankan perintah lanjutan (CMD/ARG docker)
# ============================================================
exec "$@"
