# ============================================================
# 🏗️ TAHAP 1: BUILD STAGE
# ------------------------------------------------------------
# Tujuan: Membangun aplikasi frontend menggunakan Node.js
# Basis image: Node 22 Alpine (ringan & cepat)
# ============================================================
FROM node:22-alpine AS img-builder

# 🌱 Set environment untuk build
ENV NODE_ENV=development

# 📁 Tentukan direktori kerja di dalam container
WORKDIR /app

# 🔒 Pastikan direktori dimiliki oleh user "node" agar aman
RUN chown node:node /app

# � Jalankan perintah sebagai user non-root (node)
USER node

# �📦 Salin file package.json & package-lock.json untuk caching layer dependensi
# Pastikan ownership untuk user node
COPY --chown=node:node package*.json ./

# 📥 Install semua dependensi (termasuk devDependencies untuk build)
# Menggunakan --legacy-peer-deps untuk mengatasi konflik dependency React v19
RUN npm install --legacy-peer-deps

# 📂 Salin seluruh source code aplikasi ke container
COPY --chown=node:node . .

# 🔧 Salin .env.docker ke .env untuk build environment variables
COPY --chown=node:node .env.docker .env

# ⚙️ Jalankan build frontend
RUN npm run build

# 🧾 (Opsional) Debug hasil build: tampilkan isi direktori build
RUN pwd && ls -alsh build


# ============================================================
# 🚀 TAHAP 2: PRODUCTION STAGE
# ------------------------------------------------------------
# Tujuan: Menjalankan hasil build menggunakan NGINX
# Basis image: nginx:1.25-alpine (ringan & stabil)
# ============================================================
FROM nginx:1.25-alpine

# 📦 Salin hasil build dari tahap pertama ke direktori web NGINX
COPY --from=img-builder /app/build /usr/share/nginx/html

# ⚙️ Ganti konfigurasi default NGINX dengan file custom
COPY docker-config/nginx.conf /etc/nginx/conf.d/default.conf

# 🧰 Tambahkan entrypoint script untuk dynamic runtime replacement
COPY docker-config/entrypoint.sh /entrypoint.sh

# 🔐 Pastikan entrypoint script dapat dieksekusi
RUN chmod +x /entrypoint.sh && ls -l /

# 🚀 Gunakan entrypoint script sebagai eksekusi awal container
ENTRYPOINT ["/entrypoint.sh"]

# 🌐 Buka port 80 untuk akses HTTP
EXPOSE 80

# 🧠 Jalankan NGINX di foreground (agar container tetap hidup)
CMD ["nginx", "-g", "daemon off;"]
