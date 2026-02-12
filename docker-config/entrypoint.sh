#!/bin/sh
# ============================================================
# 🚀 Script Update & Pembersihan URL dan Token Frontend Build
# ------------------------------------------------------------
# Fungsi:
#   1️⃣ Persiapkan direktori log & backup
#   2️⃣ Ambil nilai environment (VITE_API_URL, VITE_API_BASE_URL,
#       VITE_IMAGE_TOKEN, VITE_OSS_URL)
#   3️⃣ Ganti semua placeholder di hasil build frontend
#   4️⃣ Bersihkan port number dari domain target
# ============================================================

# ============================================================
# 📁 Step 1: Lokasi file & inisialisasi direktori
# ============================================================
TARGET_FILES="/usr/share/nginx/html/assets/*.js"
LOG_FILE="/var/log/script/update_api_url.log"
BACKUP_DIR="/usr/share/nginx/html/assets_backup_$(date '+%Y%m%d_%H%M%S')"
LOG_DIR=$(dirname "$LOG_FILE")

# Pastikan direktori log dan backup ada
[ ! -d "$LOG_DIR" ] && mkdir -p "$LOG_DIR"
mkdir -p "$BACKUP_DIR"

echo "============================================================" >> "$LOG_FILE"
echo "$(date '+%Y-%m-%d %H:%M:%S') 🚀 Memulai proses update konfigurasi frontend" >> "$LOG_FILE"

# ============================================================
# 🧩 Step 2: Ambil environment variable (dari Kubernetes/Docker)
# ============================================================
# Environment variables akan di-inject oleh Kubernetes/Docker saat runtime
# Tidak perlu baca dari file .env karena file tersebut hanya untuk build-time

echo "$(date '+%Y-%m-%d %H:%M:%S') 📋 Environment Variables:" >> "$LOG_FILE"
echo "  VITE_API_URL: ${VITE_API_URL:-<not set>}" >> "$LOG_FILE"
echo "  VITE_API_BASE_URL: ${VITE_API_BASE_URL:-<not set>}" >> "$LOG_FILE"
echo "  VITE_IMAGE_TOKEN: ${VITE_IMAGE_TOKEN:-<not set>}" >> "$LOG_FILE"
echo "  VITE_OSS_URL: ${VITE_OSS_URL:-<not set>}" >> "$LOG_FILE"

# Jika tidak ada environment variable yang di-set, skip replacement dan langsung jalankan nginx
if [ -z "$VITE_API_URL" ] && [ -z "$VITE_API_BASE_URL" ] && [ -z "$VITE_IMAGE_TOKEN" ] && [ -z "$VITE_OSS_URL" ]; then
  echo "$(date '+%Y-%m-%d %H:%M:%S') ⚠️  Tidak ada environment variable yang di-set — skip update, lanjut ke nginx" >> "$LOG_FILE"
  echo "$(date '+%Y-%m-%d %H:%M:%S') 🚀 Menjalankan nginx..." >> "$LOG_FILE"
  exec "$@"
fi

echo "$(date '+%Y-%m-%d %H:%M:%S') 🌐 Environment untuk replacement:" >> "$LOG_FILE"
[ -n "$VITE_API_URL" ] && echo "  VITE_API_URL: $VITE_API_URL" >> "$LOG_FILE"
[ -n "$VITE_API_BASE_URL" ] && echo "  VITE_API_BASE_URL: $VITE_API_BASE_URL" >> "$LOG_FILE"
[ -n "$VITE_IMAGE_TOKEN" ] && echo "  VITE_IMAGE_TOKEN: $VITE_IMAGE_TOKEN" >> "$LOG_FILE"
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
