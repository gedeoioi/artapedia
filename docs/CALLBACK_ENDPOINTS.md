# Endpoint Callback — ArtaPedia

Dokumen ini adalah daftar endpoint yang harus **didaftarkan di dashboard masing-masing
provider**. Semua endpoint di sini dikecualikan dari CSRF (`bootstrap/app.php` →
`validateCsrfTokens(except: ['webhook/*'])`) karena pemanggilnya server, bukan browser
pengguna — keamanannya dijaga oleh verifikasi signature/token per provider.

Ganti `https://domain-anda.com` dengan domain produksi. `APP_URL` di `.env` harus sama,
karena URL callback dibentuk dari `config('app.url')`.

Semua endpoint dibatasi rate limit `throttle:webhook` (120 request/menit per IP).

---

## 1. Payment gateway (uang masuk)

### 1.1 Tripay (default)

| | |
|---|---|
| **URL callback** | `POST https://domain-anda.com/webhook/payment/tripay` |
| **Header wajib** | `X-Callback-Signature`, `X-Callback-Event` |
| **Event diproses** | `payment_status` |
| **Verifikasi** | `HMAC-SHA256(body_mentah, private_key)` dibandingkan dengan header `X-Callback-Signature` |

Di dashboard Tripay: **Merchant → Callback URL** isi URL di atas. Tambahkan juga IP
server Anda di **Whitelist IP** — Tripay menolak callback ke host yang tidak terdaftar.

Penting: yang ditandatangani adalah **body request mentah**. Implementasi di
`App\Payments\TripayGateway::handleCallback()` menerima `$rawBody` langsung dari
`$request->getContent()`, bukan hasil `json_encode(json_decode(...))`, karena encode ulang
tidak dijamin byte-identik (urutan key, spasi, escaping) dan akan membuat callback sah
ditolak.

Status yang dianggap lunas hanya `PAID`. `UNPAID`, `EXPIRED`, `REFUND`, dan `FAILED`
tidak pernah mengkredit saldo.

### 1.2 Xendit

| | |
|---|---|
| **URL callback** | `POST https://domain-anda.com/webhook/payment/xendit` |
| **Header wajib** | `x-callback-token` |
| **Verifikasi** | Token dibandingkan (`hash_equals`) dengan `callback_token` di admin |

Di dashboard Xendit: **Settings → Webhooks → Invoices** isi URL di atas, lalu salin
**Verification Token** ke admin ArtaPedia (Gateway → Xendit → Callback Token).

### 1.3 Duitku

| | |
|---|---|
| **URL callback** | `POST https://domain-anda.com/webhook/payment/duitku` |
| **Body** | `merchantOrderId`, `amount`, `resultCode`, `signature` |
| **Verifikasi** | `md5(merchantCode + merchantOrderId + amount + apiKey)` |

Di dashboard Duitku: **Project → Callback URL** isi URL di atas.

### 1.4 iPaymu

| | |
|---|---|
| **URL callback** | `POST https://domain-anda.com/webhook/payment/ipaymu` |
| **Header wajib** | `X-Signature` |
| **Verifikasi** | `HMAC-SHA256(json_normalisasi(payload), callback_secret atau VA)` |

Payload dinormalisasi (urutan key di-sort, tipe nilai dipaksa konsisten) sebelum
dihitung — lihat `IPaymuGateway::normalizeCallbackPayload()`. Di dashboard iPaymu:
**Integrasi → Notify URL** isi URL di atas.

---

## 2. Supplier H2H (status pengiriman)

Callback supplier memakai endpoint terpisah dari callback pembayaran. Yang ini
melaporkan **status pengiriman produk**, bukan uang masuk.

| Supplier | URL callback | Verifikasi |
|---|---|---|
| VIP Reseller | `POST https://domain-anda.com/webhook/supplier/vip-reseller` | Header `X-Client-Signature` = `md5(api_id + api_key)` |
| Digiflazz | `POST https://domain-anda.com/webhook/supplier/digiflazz` | Header signature + secret sesuai dokumentasi Digiflazz |
| TokoVoucher | `POST https://domain-anda.com/webhook/supplier/toko-voucher` | Sesuai dokumentasi TokoVoucher |

VIP Reseller juga mewajibkan **whitelist IP** di sisi mereka (IP server Anda), dan
ArtaPedia memverifikasi IP asal callback. Konfirmasi arah requirement-nya di dokumen
masing-masing supplier sebelum go-live.

---

## 3. Cara menguji callback secara lokal

Endpoint webhook menerima POST JSON. Untuk Tripay, signature harus dihitung dari body
mentah:

```bash
PRIVATE_KEY="private_key_anda"
BODY='{"reference":"T0001ABC","merchant_ref":"INV-20260101-ABCD1234","status":"PAID","total_amount":25000}'
SIG=$(printf '%s' "$BODY" | openssl dgst -sha256 -hmac "$PRIVATE_KEY" | awk '{print $2}')

curl -X POST https://domain-anda.com/webhook/payment/tripay \
  -H "Content-Type: application/json" \
  -H "X-Callback-Signature: $SIG" \
  -H "X-Callback-Event: payment_status" \
  -d "$BODY"
```

Balasan `{"ok":true}` berarti callback diterima. `{"ok":false,"reason":"invalid_signature"}`
dengan HTTP 400 berarti signature tidak cocok.

Semua callback bersifat **idempoten**: `PaymentService::markPaid()` menolak memproses
transaksi yang sudah final/paid/processing/expired, sehingga callback ganda tidak pernah
mengkredit saldo dua kali. Aman mengirim ulang untuk pengujian.

---

## 4. Checklist go-live

1. `APP_URL` di `.env` sudah domain produksi (bukan `localhost`).
2. Semua URL di atas sudah didaftarkan di dashboard provider **dan** whitelist IP diisi.
3. Kredensial gateway diisi lewat admin (**Gateway Pembayaran**), bukan di `.env` —
   tersimpan terenkripsi (`encrypted:array`).
4. `php artisan queue:work` dan cron `php artisan schedule:run` berjalan.
5. `php artisan balance:reconcile` dijalankan manual sekali, harus melaporkan
   `OK: SUM(ledger) == users.balance untuk semua user`.
6. Uji satu transaksi kecil dengan uang asli, lalu pastikan saldo bertambah **dan**
   `balance_mutations` bertambah satu baris.
