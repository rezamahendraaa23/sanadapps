#!/usr/bin/env bash
# Pasang SANAD di Ubuntu 22.04/24.04 atau Debian 12/13 (server kosong) dalam satu perintah:
#   sudo bash scripts/install-ubuntu.sh
# Dijalankan dari folder kode SANAD hasil git clone. Aman diulang bila sempat terhenti.
set -euo pipefail

APP_DIR="$(cd "$(dirname "$0")/.." && pwd)"
LOG=/var/log/sanad-install.log
step() { echo; echo "==> $*"; }
fail() { echo; echo "GAGAL: $*"; echo "Rincian ada di $LOG"; exit 1; }

[ "$(id -u)" -eq 0 ] || fail "Jalankan dengan sudo: sudo bash scripts/install-ubuntu.sh"
. /etc/os-release
OS="$ID $VERSION_ID"
CODENAME="$VERSION_CODENAME"
# Versi PHP per sistem: Debian 13 sudah membawa 8.4; Ubuntu dan Debian 12 mengambil 8.3 dari repo PHP tambahan.
case "$OS" in
    "ubuntu 22.04"|"ubuntu 24.04") PHPV=8.3; PHP_REPO=ondrej ;;
    "debian 12") PHPV=8.3; PHP_REPO=sury ;;
    "debian 13") PHPV=8.4; PHP_REPO=none ;;
    *) fail "Skrip ini untuk Ubuntu 22.04/24.04 atau Debian 12/13 (terdeteksi: $OS)." ;;
esac
[ "$(dpkg --print-architecture)" = "amd64" ] || fail "Skrip ini untuk server amd64 (x86_64)."
[ -f "$APP_DIR/artisan" ] || fail "Jalankan dari dalam folder kode SANAD."

echo "Pemasangan SANAD. Jawab tiga pertanyaan, sisanya otomatis (sekitar 10 sampai 20 menit)."
read -rp "Nama pesantren: " SCHOOL
read -rp "Email admin pertama: " ADMIN_EMAIL
read -rp "Port web [80]: " PORT; PORT=${PORT:-80}
[ -n "$SCHOOL" ] && [ -n "$ADMIN_EMAIL" ] || fail "Nama pesantren dan email admin wajib diisi."

exec > >(tee -a "$LOG") 2>&1

step "1/8 Swap (hanya bila RAM di bawah 2 GB)"
if [ "$(awk '/MemTotal/ {print $2}' /proc/meminfo)" -lt 2000000 ] && ! swapon --show | grep -q /swapfile; then
    fallocate -l 2G /swapfile && chmod 600 /swapfile && mkswap /swapfile && swapon /swapfile
    grep -q '/swapfile' /etc/fstab || echo '/swapfile none swap sw 0 0' >> /etc/fstab
fi

step "2/8 Paket sistem, PHP $PHPV, PostgreSQL 16, Node 22, Chrome ($OS)"
export DEBIAN_FRONTEND=noninteractive
apt-get update -q
apt-get install -yq curl git unzip nginx ca-certificates gnupg cron
case "$PHP_REPO" in
    ondrej)
        apt-get install -yq software-properties-common
        add-apt-repository -y ppa:ondrej/php ;;
    sury)
        curl -fsSLo /usr/share/keyrings/sury-php.gpg https://packages.sury.org/php/apt.gpg
        echo "deb [signed-by=/usr/share/keyrings/sury-php.gpg] https://packages.sury.org/php/ $CODENAME main" > /etc/apt/sources.list.d/sury-php.list ;;
esac
install -d /usr/share/postgresql-common/pgdg
curl -fsSo /usr/share/postgresql-common/pgdg/apt.postgresql.org.asc https://www.postgresql.org/media/keys/ACCC4CF8.asc
echo "deb [signed-by=/usr/share/postgresql-common/pgdg/apt.postgresql.org.asc] https://apt.postgresql.org/pub/repos/apt $CODENAME-pgdg main" > /etc/apt/sources.list.d/pgdg.list
command -v node >/dev/null && node -v | grep -q '^v22' \
    || curl -fsSL https://deb.nodesource.com/setup_22.x | bash - \
    || echo "Node 22 dari NodeSource belum tersedia untuk $OS; memakai Node bawaan sistem."
apt-get update -q
apt-get install -yq php$PHPV-fpm php$PHPV-cli php$PHPV-pgsql php$PHPV-mbstring php$PHPV-intl php$PHPV-gd php$PHPV-zip php$PHPV-bcmath php$PHPV-xml php$PHPV-curl \
    postgresql-16 nodejs fonts-noto-core
# Vite butuh Node 20.19 atau lebih baru.
node -e 'const [a,b]=process.versions.node.split(".").map(Number); process.exit(a>20||(a===20&&b>=19)?0:1)' \
    || fail "Node $(node -v) terlalu lama; butuh 20.19 atau lebih baru."
if ! command -v google-chrome >/dev/null; then
    curl -fsSLo /tmp/chrome.deb https://dl.google.com/linux/direct/google-chrome-stable_current_amd64.deb
    apt-get install -yq /tmp/chrome.deb
fi
command -v composer >/dev/null || curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

step "3/8 Database"
ENV="$APP_DIR/.env"
DB_PASS=$( [ -f "$ENV" ] && grep -E '^DB_PASSWORD=' "$ENV" | cut -d= -f2- | tr -d '"' || true )
[ -n "${DB_PASS:-}" ] && [ "$DB_PASS" != "sanad" ] || DB_PASS=$(tr -dc 'A-Za-z0-9' < /dev/urandom | head -c 24)
sudo -u postgres psql -tc "SELECT 1 FROM pg_roles WHERE rolname='sanad'" | grep -q 1 \
    && sudo -u postgres psql -qc "ALTER USER sanad WITH PASSWORD '$DB_PASS';" \
    || sudo -u postgres psql -qc "CREATE USER sanad WITH PASSWORD '$DB_PASS';"
sudo -u postgres psql -tc "SELECT 1 FROM pg_database WHERE datname='sanad'" | grep -q 1 || sudo -u postgres createdb -O sanad sanad

step "4/8 Pengaturan .env"
cd "$APP_DIR"
[ -f .env ] || cp .env.example .env
IP=$(curl -fsS https://api.ipify.org || hostname -I | awk '{print $1}')
URL="http://$IP$( [ "$PORT" = 80 ] || echo ":$PORT" )"
setenv() { if grep -qE "^$1=" .env; then sed -i "s|^$1=.*|$1=$2|" .env; else echo "$1=$2" >> .env; fi; }
setenv APP_ENV production
setenv APP_DEBUG false
setenv APP_URL "$URL"
setenv DB_HOST 127.0.0.1
setenv DB_PORT 5432
setenv DB_DATABASE sanad
setenv DB_USERNAME sanad
setenv DB_PASSWORD "$DB_PASS"
setenv DB_PERSISTENT false
setenv QUEUE_CONNECTION database
setenv CACHE_STORE database
setenv SESSION_DRIVER database
setenv BROWSERSHOT_CHROME_PATH /usr/bin/google-chrome
setenv SANAD_SCHOOL_NAME "\"$SCHOOL\""
setenv SANAD_ADMIN_EMAIL "$ADMIN_EMAIL"

step "5/8 Kode, tampilan, dan database"
export COMPOSER_ALLOW_SUPERUSER=1
composer install --no-dev --optimize-autoloader --no-interaction
PUPPETEER_SKIP_DOWNLOAD=1 npm ci --no-audit --no-fund
npm run build
grep -qE '^APP_KEY=base64:' .env || php artisan key:generate --force
SEED_OUT=$(php artisan migrate --seed --force 2>&1); echo "$SEED_OUT"
php artisan storage:link 2>/dev/null || true
php artisan config:cache && php artisan route:cache && php artisan view:cache
chown -R www-data:www-data storage bootstrap/cache

step "6/8 Web server (Nginx di port $PORT)"
cat > /etc/nginx/sites-available/sanad <<NGINX
server {
    listen $PORT;
    server_name _;
    root $APP_DIR/public;
    index index.php;
    client_max_body_size 25M;
    location / { try_files \$uri \$uri/ /index.php?\$query_string; }
    location ~ \.php\$ { include snippets/fastcgi-php.conf; fastcgi_pass unix:/run/php/php$PHPV-fpm.sock; }
    location ~ /\.(?!well-known) { deny all; }
}
NGINX
ln -sf /etc/nginx/sites-available/sanad /etc/nginx/sites-enabled/sanad
rm -f /etc/nginx/sites-enabled/default
# Hemat RAM: pekerja PHP dinyalakan saat dibutuhkan saja.
sed -i 's/^pm = .*/pm = ondemand/; s/^pm.max_children = .*/pm.max_children = 4/' /etc/php/$PHPV/fpm/pool.d/www.conf
systemctl restart php$PHPV-fpm
nginx -t && systemctl reload nginx

step "7/8 Antrean dan penjadwal"
cat > /etc/systemd/system/sanad-queue.service <<UNIT
[Unit]
Description=SANAD queue worker
After=network.target postgresql.service

[Service]
User=www-data
WorkingDirectory=$APP_DIR
ExecStart=/usr/bin/php artisan queue:work --sleep=3 --tries=3 --max-time=3600
Restart=always

[Install]
WantedBy=multi-user.target
UNIT
systemctl daemon-reload
systemctl enable --now sanad-queue
echo "* * * * * cd $APP_DIR && php artisan schedule:run >> /dev/null 2>&1" | crontab -u www-data -

step "8/8 Pemeriksaan"
sleep 2
CODE=$(curl -s -o /dev/null -w '%{http_code}' "http://127.0.0.1:$PORT/up" || true)
[ "$CODE" = "200" ] || fail "Aplikasi belum menjawab (kode $CODE). Lihat storage/logs/laravel.log"

PASS_LINE=$(echo "$SEED_OUT" | grep -o 'Sandi awal: .*' || true)
echo
echo "============================================================"
echo " SANAD terpasang."
echo " Buka   : $URL"
echo " Masuk  : tab Staf, email $ADMIN_EMAIL"
if [ -n "$PASS_LINE" ]; then
    echo " ${PASS_LINE}   <- CATAT SEKARANG, hanya tampil sekali"
else
    echo " Admin sudah ada sebelumnya; pakai sandi lama."
fi
echo " Bila port $PORT bukan 80, pastikan port itu memang dibuka penyedia server."
echo "============================================================"
