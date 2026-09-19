# Deploy ArtaPedia ke VPS Ubuntu (Nginx + MySQL + Git)

Target: Ubuntu 22.04/24.04, tanpa panel, akses root via SSH, sudah punya domain yang
diarahkan (A record) ke IP VPS. Estimasi 30–45 menit.

> Semua perintah di bawah dijalankan di VPS, kecuali bagian **[LOKAL]**.

---

## 0. Kode sudah ada di GitHub [LOKAL]

Repo: **https://github.com/gedeoioi/artapedia** (private).

```bash
cd C:/Users/GedeOi/Desktop/project/artapedia
git push origin main
```

Tidak perlu `git init` / `git remote add` lagi — sudah dikonfigurasi.

**`public/build` TIDAK ikut di-commit** (ada di `.gitignore`) dan itu memang disengaja:
aset frontend dibangun di VPS oleh `deploy/deploy.sh` (`npm ci && npm run build`).
Kalau `npm` tidak tersedia, deploy berhenti dengan pesan jelas — bukan situs yang
tampil tanpa CSS.

Repo ini **private**, jadi VPS perlu akses. Pilih salah satu:

- **Deploy key (disarankan)** — key khusus read-only:
  ```bash
  ssh-keygen -t ed25519 -C "artapedia-vps" -f ~/.ssh/artapedia -N ""
  cat ~/.ssh/artapedia.pub   # tempel ke GitHub → repo → Settings → Deploy keys
  ```
- **Personal Access Token** (fine-grained, scope `Contents: Read` saja):
  ```bash
  git clone https://<TOKEN>@github.com/gedeoioi/artapedia.git /var/www/artapedia
  ```

---

## 1. Persiapan dasar VPS

```bash
sudo apt update && sudo apt upgrade -y
sudo apt install -y git curl unzip supervisor ufw
sudo ufw allow OpenSSH && sudo ufw allow 80 && sudo ufw allow 443 && sudo ufw --force enable
sudo timedatectl set-timezone Asia/Jakarta
```

Zona waktu wajib `Asia/Jakarta`. Kalau tidak, jadwal rekonsiliasi malam (`02:00`),
stempel waktu invoice, dan laporan harian akan bergeser.

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

Ekstensi `pcntl` dan `posix` sudah termasuk di paket `php8.2-cli` — dibutuhkan Horizon,
tidak perlu dipasang terpisah di Linux.

## 3. Install Composer + Node

```bash
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer

curl -fsSL https://deb.nodesource.com/setup_20.x | sudo -E bash -
sudo apt install -y nodejs
node -v   # minimal 20
```

Node **wajib** ada: `deploy/deploy.sh` membangun aset frontend di VPS.

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

> **Collation.** Panduan ini memakai `utf8mb4_unicode_ci`, bukan `utf8mb4_0900_ai_ci`.
> MariaDB (default di sebagian image) tidak mengenal collation `0900` milik MySQL 8 dan
> menolak koneksi dengan `Unknown collation: 'utf8mb4_0900_ai_ci'`. Kalau itu terjadi,
> tambahkan `DB_COLLATION=utf8mb4_unicode_ci` di `.env`.

## 5. Clone kode + permission

```bash
sudo mkdir -p /var/www
sudo chown $USER:$USER /var/www
git clone git@github.com:gedeoioi/artapedia.git /var/www/artapedia
cd /var/www/artapedia
chmod +x deploy/deploy.sh
```

## 6. Konfigurasi .env production

```bash
cp .env.production.example .env
nano .env
```

Wajib diisi:

| Variabel | Nilai |
|---|---|
| `APP_URL` | `https://domain-anda` (tanpa trailing slash) |
| `DB_PASSWORD` | password dari langkah 4 |
| `INITIAL_ADMIN_EMAIL` | email admin kamu |
| `INITIAL_ADMIN_PASSWORD` | **ganti placeholder-nya** — lihat peringatan di bawah |
| `SESSION_SECURE_COOKIE` | `true` (sudah terisi) |

> **Penting soal admin pertama.** Di lingkungan `production`, seeder hanya membuat akun
> admin jika `INITIAL_ADMIN_PASSWORD` **tidak kosong**. Kalau kamu biarkan placeholder
> `ISI_PASSWORD_ADMIN_KUAT` dari file contoh, akun admin akan benar-benar dibuat dengan
> password itu — dan placeholder tersebut ikut tersimpan di repo. Selalu ganti dulu,
> lalu ganti lagi setelah login pertama.

Lanjutkan:

```bash
composer install --no-interaction --prefer-dist --optimize-autoloader --no-dev
php artisan key:generate
php artisan storage:link
php artisan migrate --force
php artisan db:seed --force
sudo chown -R www-data:www-data storage bootstrap/cache
sudo chmod -R 775 storage bootstrap/cache
```

`db:seed --force` membuat admin dari `INITIAL_ADMIN_*` **dan** peran Super Admin / Admin
/ Operator. Aman dijalankan berulang (semua memakai `firstOrCreate`).

## 7. Nginx

```bash
sudo apt install -y nginx
sudo cp deploy/nginx-artapedia.conf /etc/nginx/sites-available/artapedia
sudo nano /etc/nginx/sites-available/artapedia   # ganti SERVER_NAME dengan domain kamu
sudo ln -s /etc/nginx/sites-available/artapedia /etc/nginx/sites-enabled/
sudo rm -f /etc/nginx/sites-enabled/default
sudo nginx -t && sudo systemctl reload nginx
```

**Wajib:** `root` harus menunjuk ke `/var/www/artapedia/public`, bukan akar project.
Kalau diarahkan ke akar project, `.env` dan seluruh kode bisa diunduh publik.

## 8. HTTPS gratis (Certbot)

```bash
sudo apt install -y certbot python3-certbot-nginx
sudo certbot --nginx -d domain-anda -d www.domain-anda
# pilih redirect HTTP -> HTTPS saat ditanya
```

Setelah HTTPS aktif, pastikan `APP_URL` sudah `https://` lalu:

```bash
php artisan config:cache
```

Callback gateway/supplier mewajibkan HTTPS di produksi — tanpa itu sebagian provider
menolak mengirim webhook.

## 9. Worker queue + scheduler (Supervisor)

Tanpa ini: order tidak diteruskan ke supplier, polling tiap menit mati, webhook
tertunda, dan **rekonsiliasi saldo malam tidak pernah jalan**.

```bash
sudo cp deploy/supervisor-artapedia.conf /etc/supervisor/conf.d/artapedia.conf
sudo supervisorctl reread && sudo supervisorctl update
sudo supervisorctl status   # artapedia-queue (2 proses) + artapedia-schedule: RUNNING
```

Isinya: 2 proses `queue:work` + 1 proses `schedule:work` (pengganti cron tiap menit).
`schedule:work` diberi jeda `sleep 50` agar sinkron dengan batas menit.

> Kalau tidak memakai Redis, biarkan `QUEUE_CONNECTION=database` (default). Dashboard
> Horizon (`/horizon`) hanya berfungsi bila `QUEUE_CONNECTION=redis` + Redis terpasang.

## 10. Cache production + verifikasi

```bash
php artisan config:cache && php artisan route:cache && php artisan view:cache
php artisan schedule:list          # harus 5 tugas, termasuk reconcile-balances 0 2 * * *
php artisan balance:reconcile      # harus: OK, SUM(ledger) == users.balance
curl -s -o /dev/null -w "%{http_code}\n" https://domain-anda/
```

`balance:reconcile` wajib melaporkan **OK** sebelum toko dibuka. Kalau ada selisih,
perbaiki dulu dengan `php artisan balance:reconcile --fix`, lalu jalankan sekali lagi
untuk membuktikan selisihnya benar-benar hilang.

Buka toko `https://domain-anda` dan admin `https://domain-anda/admin`, login memakai
`INITIAL_ADMIN_EMAIL` / `INITIAL_ADMIN_PASSWORD`.

Semua supplier dan gateway awal **sengaja nonaktif**. Urutan mengaktifkan:

1. `/admin/supplier-configs` — isi API ID + API Key, uji koneksi, aktifkan
2. `/admin/payment-gateway-configs` — isi kredensial gateway, aktifkan
3. `/admin/site-settings` — rekening topup manual, kontak, tema
4. `/admin/cron-settings` — pastikan semua tugas polling aktif
5. `/admin/wa-notification-settings` — isi URL + token WA gateway

## 11. Webhook supplier/gateway

| Provider | URL callback |
|---|---|
| Tripay | `https://domain-anda/webhook/payment/tripay` |
| Xendit | `https://domain-anda/webhook/payment/xendit` |
| Duitku | `https://domain-anda/webhook/payment/duitku` |
| iPaymu | `https://domain-anda/webhook/payment/ipaymu` |
| Digiflazz | `https://domain-anda/webhook/supplier/digiflazz` |
| VIP Reseller | `https://domain-anda/webhook/supplier/vip-reseller` |
| TokoVoucher | `https://domain-anda/webhook/supplier/toko-voucher` |

Rincian verifikasi signature tiap provider ada di `docs/CALLBACK_ENDPOINTS.md`.

Jangan lupa **whitelist IP**: sebagian provider (VIP Reseller, Digiflazz) menolak
mengirim callback ke IP yang belum terdaftar di sisi mereka.

---

## Deploy ulang (setiap ada kode baru)

**[LOKAL]** `git push origin main`. Di VPS:

```bash
cd /var/www/artapedia && ./deploy/deploy.sh
```

Script ini: pull → permission → composer → npm build → migrate → seed → cache →
restart worker.

> **Jangan jalankan `vendor/bin/pint` di VPS.** CI sudah memeriksa format; menjalankan
> Pint di server produksi menulis ulang file yang dilacak Git dan membuat `git pull`
> berikutnya gagal karena perubahan lokal.

## Backup

Yang wajib di-backup: **database** dan `storage/app/public` (bukti transfer topup
manual, logo, banner, ikon game).

```bash
# Database
mysqldump -u artapedia -p artapedia | gzip > ~/backup-artapedia-$(date +%F).sql.gz

# File unggahan
tar -czf ~/backup-storage-$(date +%F).tar.gz -C /var/www/artapedia/storage/app/public .
```

Backup database tanpa file unggahan membuat bukti transfer hilang saat restore —
topup manual tidak bisa diverifikasi ulang.

---

## Troubleshooting

| Gejala | Periksa |
|---|---|
| 502 Bad Gateway | `systemctl status php8.2-fpm`; socket di nginx conf harus cocok dengan versi PHP (`php8.2-fpm.sock`) |
| 500 + log `No application encryption key` | `php artisan key:generate`, lalu `config:cache` ulang |
| `Unknown collation: 'utf8mb4_0900_ai_ci'` | tambahkan `DB_COLLATION=utf8mb4_unicode_ci` di `.env`, `config:cache` ulang |
| `Permission denied` pada storage | `sudo chown -R www-data:www-data storage bootstrap/cache` |
| Tampilan tanpa CSS | `public/build` belum ada — `npm ci && npm run build`, pastikan Node terpasang |
| Order stuck `paid` tak diproses | `sudo supervisorctl status`, pastikan `artapedia-queue` RUNNING |
| Polling / rekonsiliasi tak jalan | pastikan `artapedia-schedule` RUNNING; cek `php artisan schedule:list` |
| Webhook 401 `invalid_signature` | cocokkan secret/callback token di admin vs dashboard provider |
| Webhook tidak sampai sama sekali | whitelist IP server kamu di dashboard provider |
| Digiflazz rc=45 | IP VPS belum di-whitelist di Pengaturan Koneksi API Digiflazz |
| Perubahan `.env` tidak berefek | `php artisan config:clear && php artisan config:cache` |
