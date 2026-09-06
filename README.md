# ArtaPedia

Topup game / pulsa / PPOB dengan supplier H2H pluggable dan multi payment gateway yang bisa dikonfigurasi dari dashboard admin.

## 1. Cara jalan lokal

```bash
cp .env.example .env
php artisan key:generate
php artisan migrate --seed   # admin@artapedia.id / password
php artisan serve
php artisan queue:work       # worker order async + polling
php artisan schedule:work    # sync harga + polling status
```

Horizon: `/horizon` (butuh Redis di production; queue default `database` agar jalan tanpa Redis).

## 2. Menambah supplier baru (tanpa ubah kode inti)

1. Buat 1 class di `app/Suppliers/`, implementasi `SupplierProviderInterface`:

```php
class MySupplier implements SupplierProviderInterface {
    public function code(): string { return 'my-supplier'; }
    public function getBalance(): array { ... }
    public function getProducts(array $filters = []): array { ... }
    public function order(string $productCode, string $target, array $options = []): array { ... }
    public function checkStatus(string $supplierTrxId): array { ... }
}
```

Opsional: implementasi `NicknameCheckableInterface::checkNickname()` jika supplier mendukung cek nickname.

2. Tambah 1 baris di `config/artapedia.php` → `suppliers`:

```php
'my-supplier' => App\Suppliers\MySupplier::class,
```

3. Dari admin `/admin/supplier-configs`: tambah row `code=my-supplier`, isi kredensial (tersimpan terenkripsi), toggle aktif, atur priority (fallback otomatis ke priority berikutnya jika gagal).

VIP Reseller: `sign = md5(api_id + api_key)`, produk via `type=services` + `filter_game` / `filter_status`, nickname via `POST /api/game-feature` (`type=get-nickname`, `code`, `target`, `additional_target`).

## 3. Menambah payment gateway baru (tanpa ubah kode inti)

1. Buat 1 class di `app/Payments/`, implementasi `PaymentGatewayInterface` (`createPayment`, `handleCallback`, `checkStatus`).
2. Tambah 1 baris di `config/artapedia.php` → `gateways`.
3. Dari admin `/admin/payment-gateway-configs`: tambah row, isi kredensial terenkripsi, toggle `is_active`, atur `sort_order` tampil di checkout, set `is_sandbox` untuk testing tanpa uang asli.

Webhook: `POST /webhook/payment/{gateway}` — idempoten via `reference_id` unik + cek status sebelum proses (duplicate call aman).

## 4. Keamanan saldo

- Semua mutasi saldo via `BalanceService` dalam `DB::transaction()` + `lockForUpdate()` + ledger `balance_mutations` (audit selisih).
- Test kritis: `php artisan test --filter=BalanceSafetyTest` (saldo kurang ditolak, race tidak minus, webhook ganda tidak dobel, ledger konsisten).
- CI: `.github/workflows/ci.yml` menjalankan test di atas setiap push.

## 5. Struktur penting

- `app/Contracts/` — `SupplierProviderInterface`, `NicknameCheckableInterface`, `PaymentGatewayInterface`
- `app/Services/` — `OrderService` (checkout + fallback supplier + refund), `PaymentService` (quote + topup + markPaid idempoten), `BalanceService` (locking), `ProviderFactory`
- `app/Jobs/` — `DispatchOrderToSupplier`, `PollTransactionStatus`, `SyncSupplierProducts`, `SendWaNotification`
- `app/Filament/` — resources produk/transaksi/supplier/gateway/game-icon, page `PullProducts` (Tarik Produk), widget `SalesStats`
- Customer: `/` beranda, `/game/{game}`, `/product/{id}/checkout`, `/pay/{invoice}`, `/cek-invoice`, `/member`
