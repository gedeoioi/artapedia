# Deploy ArtaPedia ke VPS Ubuntu (Nginx + MySQL + Git)

Target: Ubuntu 22.04/24.04, tanpa panel, akses root via SSH, sudah punya domain yang diarahkan (A record) ke IP VPS. Estimasi 30–45 menit.

> Semua perintah di bawah dijalankan di VPS. Bagian [LOKAL] dijalankan di laptop Windows kamu.

---

## 0. Push kode dari laptop [LOKAL]

```powershell
cd C:\Users\GedeOi\Desktop\project\artapedia
git init
git add -A
git commit -m "ArtaPedia siap deploy"
# buat repo kosong di GitHub/GitLab dulu, lalu:
git remote add origin git@github.com:USERNAME/artapedia.git
git branch -M main
git push -u origin main
```

Pastikan `public/build` ikut ter-commit (cek `git status` tidak mengabaikannya). Kalau `public/build` tidak ada di repo, nanti build di VPS (butuh Node).

---

## 1. Persiapan dasar VPS

```bash
sudo apt update && sudo apt upgrade -y
sudo apt install -y git curl unzip supervisor ufw
sudo ufw allow OpenSSH && sudo ufw allow 80 && sudo ufw allow 443 && sudo ufw --force enable
timedatectl set-timezone Asia/Jakarta
```

## 2. Install PHP 8.2 + ekstensi

```bash
sudo apt install -y software-properties-common
sudo add-apt-repository -y ppa:ondrej/php
sudo apt update
sudo apt install -y php8.2-fpm php8.2-cli php8.2-mysql php8.2-mbstring \
  php8.2-xml php8.2-curl php8.2-zip php8.2-bcmath php8.2-intl php8.2-gd \
  php8.2-redis php8.2-tokenizer php8.2-fileinfo php8.2-ctype
php -v   # harus 8.2.x
```

## 3. Install Composer + Node (opsional tapi disarankan)

```bash
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer
curl -fsSL https://deb.nodesource.com/setup_20.x | sudo -E bash -
sudo apt install -y nodejs
```

## 4. Install MySQL + buat database

```bash
sudo apt install -y mysql-server
sudo mysql_secure_installation   # ikuti wizard, boleh jawab Y semua
```

Buat DB + user (ganti `PASSWORD_KUAT`):

```bash
sudo mysql <<'SQL'
CREATE DATABASE artapedia CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'artapedia'@'localhost' IDENTIFIED BY 'PASSWORD_KUAT';
GRANT ALL PRIVILEGES ON artapedia.* TO 'artapedia'@'localhost';
FLUSH PRIVILEGES;
SQL
```

## 5. Clone kode + permission

```bash
sudo mkdir -p /var/www
sudo chown $USER:$USER /var/www
git clone git@github.com:USERNAME/artapedia.git /var/www/artapedia
# (kalau pakai HTTPS: git clone https://github.com/USERNAME/artapedia.git)
cd /var/www/artapedia
chmod +x deploy/deploy.sh
```

## 6. Konfigurasi .env production

```bash
cp .env.production.example .env
nano .env
```

Wajib diisi/cek: `APP_URL=https://domain-anda`, `DB_PASSWORD`, lalu:

```bash
composer install --no-interaction --prefer-dist --optimize-autoloader --no-dev
php artisan key:generate
php artisan storage:link
php artisan migrate --force
php artisan db:seed --force
sudo chown -R www-data:www-data storage bootstrap/cache
sudo chmod -R 775 storage bootstrap/cache
```

## 7. Nginx

```bash
sudo apt install -y nginx
sudo cp deploy/nginx-artapedia.conf /etc/nginx/sites-available/artapedia
sudo nano /etc/nginx/sites-available/artapedia   # ganti SERVER_NAME dengan domain kamu
sudo ln -s /etc/nginx/sites-available/artapedia /etc/nginx/sites-enabled/
sudo rm -f /etc/nginx/sites-enabled/default
sudo nginx -t && sudo systemctl reload nginx
```

## 8. HTTPS gratis (Certbot)

```bash
sudo apt install -y certbot python3-certbot-nginx
sudo certbot --nginx -d domain-anda -d www.domain-anda
# pilih redirect HTTP -> HTTPS saat ditanya
```

`SESSION_SECURE_COOKIE=true` di `.env` sudah disiapkan untuk HTTPS.

## 9. Worker queue + scheduler (Supervisor)

Tanpa ini: order ke supplier tidak jalan, polling tiap menit mati, webhook tertunda.

```bash
sudo cp deploy/supervisor-artapedia.conf /etc/supervisor/conf.d/artapedia.conf
sudo supervisorctl reread && sudo supervisorctl update
sudo supervisorctl status   # artapedia-queue + artapedia-schedule harus RUNNING
```

Isinya: 2 proses `queue:work` + 1 proses `schedule:work` (pengganti cron tiap menit).

## 10. Cache production + verifikasi

```bash
php artisan config:cache && php artisan route:cache && php artisan view:cache
php artisan schedule:list   # 4 job polling muncul
curl -s -o /dev/null -w "%{http_code}\n" https://domain-anda/
```

Buka: toko `https://domain-anda`, admin `https://domain-anda/admin`, login `admin@artapedia.id / password` (SEGERA ganti + isi kredensial supplier/gateway asli di admin).

## 11. Webhook supplier/gateway

- Digiflazz: menu Atur Koneksi > API > Webhook isi `https://domain-anda/webhook/supplier/digiflazz`, salin secret ke kredensial `webhook_secret` di `/admin/supplier-configs`.
- Xendit/Duitku/iPaymu: isi callback URL `https://domain-anda/webhook/payment/xendit` (dst) di dashboard masing-masing.
- Whitelist IP Digiflazz `52.74.250.133` di firewall kamu bila perlu (inbound dari mereka ke webhook).

---

## Deploy ulang (setiap ada kode baru)

[LOKAL]: `git push`. Di VPS:

```bash
cd /var/www/artapedia && ./deploy/deploy.sh
```

Script ini: pull → composer → npm build → migrate → seed → cache → restart worker.

## Troubleshooting

| Gejala | Periksa |
|---|---|
| 502 Bad Gateway | `systemctl status php8.2-fpm`, socket di nginx conf harus `php8.2-fpm.sock` |
| 500 + log `No application encryption key` | `php artisan key:generate`, lalu `config:cache` ulang |
| `Permission denied` storage | `chown -R www-data:www-data storage bootstrap/cache` |
| Order stuck `paid` tak diproses | `supervisorctl status`, pastikan queue RUNNING |
| Polling tak jalan tiap menit | pastikan `artapedia-schedule` RUNNING |
| Webhook 401 invalid_signature | cek secret/callback token di admin vs dashboard provider |
| Digiflazz rc=45 | IP VPS belum di-whitelist di Pengaturan Koneksi API Digiflazz |
