#!/bin/sh
# ============================================================
# 🚀 Laravel Application Entrypoint (Production Safe)
# ============================================================


set -e
# ============================================================
# 🚦 Dynamic NGINX CORS Origin from ENV (template based)
# ============================================================
if [ "$NGINX_CORS_ENABLE" = "true" ]; then
  # Jika CORS_ALLOWED_ORIGINS tidak di-set, jangan generate config baru, biarkan nginx.conf bawaan yang dipakai
  NGINX_CONF_TEMPLATE="/var/www/html/docker-config/nginx.conf.template"
  NGINX_CONF_TARGET="/etc/nginx/http.d/default.conf"

  # Generate nginx.conf dari template hanya jika CORS_ALLOWED_ORIGINS di-set dan tidak kosong
  if [ -n "$CORS_ALLOWED_ORIGINS" ]; then
    if [ -f "$NGINX_CONF_TEMPLATE" ]; then
      echo "🔄 Generating nginx.conf with CORS origins: $CORS_ALLOWED_ORIGINS"
      envsubst < "$NGINX_CONF_TEMPLATE" > "$NGINX_CONF_TARGET"
    else
      echo "⚠️ nginx.conf template not found: $NGINX_CONF_TEMPLATE"
    fi
  else
    echo "ℹ️ CORS_ALLOWED_ORIGINS not set, using default nginx.conf."
  fi
else
  echo "ℹ️ CORS_NGINX not true, skip dynamic nginx.conf generation."
fi

# Ensure LOG_FILE and BACKUP_DIR are set and directories exist
LOG_FILE="/var/log/entrypoint.log"
BACKUP_DIR="/tmp/backup_assets"
mkdir -p "$(dirname "$LOG_FILE")"
mkdir -p "$BACKUP_DIR"

echo "============================================================"
echo "🚀 Starting Laravel application"
echo "APP_ENV        : ${APP_ENV:-undefined}"
echo "RUN_MIGRATION  : ${RUN_MIGRATION:-false}"
echo "============================================================"

APP_DIR="/var/www/html"

cd "$APP_DIR"

# ============================================================
# 🔐 Fix Permissions
# ============================================================
echo "🔐 Ensuring storage subfolders and fixing permissions..."
# Laravel storage subfolders
for dir in storage/app storage/framework storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache; do
  mkdir -p "$dir"
  chown -R www-data:www-data "$dir"
  chmod -R 775 "$dir"
done

# Create/repair public/storage symlink to storage/app/public
LINK_PATH="${APP_DIR}/public/storage"
TARGET_PATH="${APP_DIR}/storage/app/public"
echo "🔗 Ensuring storage symlink: $LINK_PATH -> $TARGET_PATH"
mkdir -p "$TARGET_PATH"
if [ -L "$LINK_PATH" ]; then
  CURRENT_TARGET=$(readlink "$LINK_PATH" || true)
  if [ "$CURRENT_TARGET" != "$TARGET_PATH" ]; then
    echo "↪️ Updating existing symlink (was: $CURRENT_TARGET)"
    rm -f "$LINK_PATH"
    ln -sfn "$TARGET_PATH" "$LINK_PATH"
  else
    echo "✅ Symlink already correct"
  fi
elif [ -e "$LINK_PATH" ]; then
  echo "🧹 Removing non-symlink path at $LINK_PATH"
  rm -rf "$LINK_PATH"
  ln -sfn "$TARGET_PATH" "$LINK_PATH"
else
  ln -sfn "$TARGET_PATH" "$LINK_PATH"
  echo "✅ Symlink created"
fi

# ============================================================
# 🔑 Generate APP_KEY (jika APP_KEY di .env kosong)
# ============================================================
echo "🔍 Checking APP_KEY..."

# ============================================================
# 1️⃣ Cek APP_KEY di environment (harus ada & valid)
# ============================================================

if [ -n "$APP_KEY" ] && echo "$APP_KEY" | grep -q "^base64:"; then
  echo "🔐 APP_KEY found in environment and valid, skipping generation"

else
  echo "ℹ️ APP_KEY not found or invalid in environment"

  # ============================================================
  # 2️⃣ Pastikan file .env ada
  # ============================================================
  if [ ! -f ".env" ]; then
    if [ -f ".env.example" ]; then
      echo "📄 .env not found, creating from .env.example"
      cp .env.example .env
    else
      echo "📄 .env.example not found, creating empty .env"
      touch .env
    fi
  fi

  # ============================================================
  # 3️⃣ Cek APP_KEY di .env
  # ============================================================
  if grep -q "^APP_KEY=base64:" .env; then
    echo "🔐 APP_KEY already exists in .env, skipping generation"
  else
    echo "🔑 APP_KEY not found in .env, generating..."
    php artisan key:generate --force
    echo "✅ APP_KEY generated and saved to .env"
  fi
fi

# ============================================================
# 🧠 Laravel Cache
# ============================================================
ARTISAN_CLEAR="${ARTISAN_CLEAR:-true}"
if [ "$ARTISAN_CLEAR" = "true" ]; then
  echo "🧠 Caching Laravel configuration..."
  php artisan config:clear || true
  php artisan config:cache || true
  php artisan route:cache || true
  php artisan view:cache || true
  php artisan optimize:clear || true
fi

# ============================================================
# 🔄 Controlled Database Migration
# ============================================================
echo "🔄 Checking migration conditions..."

if [ "$RUN_MIGRATION" = "true" ]; then
  if [ "$APP_ENV" != "production" ]; then
    echo "⚠️ Running database migration (APP_ENV=$APP_ENV)"
    php artisan migrate --force || echo "⚠️ Migration failed, continuing"
  else
    echo "🚫 Migration skipped (production environment)"
  fi
else
  echo "ℹ️ RUN_MIGRATION disabled"
fi

echo "✅ Laravel initialization completed"
echo "============================================================"


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

# ============================================================
# 🚀 Start main container process (php-fpm / nginx / supervisord)
# ============================================================

exec "$@"
