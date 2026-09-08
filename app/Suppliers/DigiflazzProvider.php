<?php

namespace App\Suppliers;

use App\Contracts\SupplierProviderInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Digiflazz buyer API.
 *
 * Dok: https://developer.digiflazz.com/api/buyer/persiapan/
 * - Semua request POST JSON ke https://api.digiflazz.com/v1
 * - Kredensial: username + api_key (diatur di Pengaturan Koneksi API Digiflazz,
 *   sekalian whitelist IP development & production di sana)
 * - Sandbox = flag "testing": true (pakai test-case customer_no dari dok)
 * - ref_id HARUS unik per transaksi & stabil: order ulang / cek status
 *   prepaid memakai ref_id yang SAMA (tidak double charge)
 */
class DigiflazzProvider implements SupplierProviderInterface
{
    public const BASE_URL = 'https://api.digiflazz.com/v1';

    /** rc yang berarti transaksi TERBENTUK (punya status final/pending). */
    public const FORMED_RCS = [
        '00', '01', '02', '03', '50', '51', '52', '53', '54', '55',
        '57', '58', '59', '60', '63', '70', '71', '72', '73', '74',
        '84', '85', '86', '99',
    ];

    public function __construct(protected array $credentials = [], protected bool $sandbox = true) {}

    public function code(): string
    {
        return 'digiflazz';
    }

    protected function baseUrl(): string
    {
        return rtrim($this->credentials['base_url'] ?? self::BASE_URL, '/');
    }

    protected function username(): string
    {
        return trim((string) ($this->credentials['username'] ?? ''));
    }

    protected function apiKey(): string
    {
        return trim((string) ($this->credentials['api_key'] ?? ''));
    }

    public function signDeposit(): string
    {
        return md5($this->username().$this->apiKey().'depo');
    }

    public function signPricelist(): string
    {
        return md5($this->username().$this->apiKey().'pricelist');
    }

    public function signRef(string $refId): string
    {
        return md5($this->username().$this->apiKey().$refId);
    }

    protected function post(string $path, array $body): array
    {
        try {
            $res = Http::asJson()->timeout(30)->post($this->baseUrl().$path, $body);
        } catch (\Throwable $e) {
            return ['http_ok' => false, 'message' => 'HTTP error: '.$e->getMessage()];
        }

        $json = $res->json();
        if (! is_array($json)) {
            return ['http_ok' => false, 'message' => 'Response bukan JSON', 'raw' => $res->body()];
        }
        $json['http_ok'] = $res->successful();

        return $json;
    }

    public function getBalance(): array
    {
        $json = $this->post('/cek-saldo', [
            'cmd' => 'deposit',
            'username' => $this->username(),
            'sign' => $this->signDeposit(),
        ]);

        $deposit = $json['data']['deposit'] ?? null;

        if (! ($json['http_ok'] ?? false) || ! is_numeric($deposit)) {
            return [
                'result' => false,
                'message' => $json['data']['message'] ?? $json['message'] ?? 'Gagal cek saldo Digiflazz',
                'raw' => $json,
            ];
        }

        return ['result' => true, 'data' => ['balance' => (int) $deposit], 'raw' => $json];
    }

    public function getProducts(array $filters = []): array
    {
        // Rate limit Digiflazz: full pricelist max 1x/5 mnt, per kode 1x/detik.
        // Scheduler default per jam, jadi aman.
        $cmd = strtolower((string) ($filters['cmd'] ?? 'prepaid'));
        $cmd = $cmd === 'pasca' ? 'pasca' : 'prepaid';

        $body = ['cmd' => $cmd, 'username' => $this->username(), 'sign' => $this->signPricelist()];
        foreach (['code' => 'code', 'game' => 'brand', 'brand' => 'brand', 'category' => 'category', 'type' => 'type'] as $filter => $param) {
            if (! empty($filters[$filter])) {
                $body[$param] = $filters[$filter];
            }
        }

        $json = $this->post('/price-list', $body);
        $rows = $json['data'] ?? null;

        // Respons error Digiflazz berbentuk data = OBJECT ({message, rc}),
        // sedangkan sukses = LIST. Bedakan agar error tidak dianggap sukses kosong.
        if (! ($json['http_ok'] ?? false) || ! is_array($rows) || ! array_is_list($rows)) {
            return [
                'result' => false,
                'rc' => (string) (is_array($rows) ? ($rows['rc'] ?? '') : ($json['rc'] ?? '')),
                'message' => (is_array($rows) ? ($rows['message'] ?? null) : null)
                    ?? $json['data']['message'] ?? 'Gagal ambil pricelist Digiflazz (mungkin rate-limit rc=83)',
                'raw' => $json,
            ];
        }

        $out = [];
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $normalized = $this->normalizeRow($row, $cmd);
            if (! $normalized['code']) {
                continue;
            }
            // Server sering mengabaikan filter brand/category/type (delay 10-15 mnt),
            // jadi saring lokal secara persis (case-insensitive).
            if (! empty($filters['game']) && strcasecmp($normalized['game'], (string) $filters['game']) !== 0) {
                continue;
            }
            if (! empty($filters['brand']) && strcasecmp($normalized['game'], (string) $filters['brand']) !== 0) {
                continue;
            }
            if (! empty($filters['category']) && strcasecmp($normalized['category'], (string) $filters['category']) !== 0) {
                continue;
            }
            if (! empty($filters['type']) && strcasecmp($normalized['type'], (string) $filters['type']) !== 0) {
                continue;
            }
            if (! empty($filters['code']) && stripos($normalized['code'], (string) $filters['code']) === false) {
                continue;
            }
            // Digiflazz tidak punya param filter status -> saring lokal.
            if (! empty($filters['status']) && strtolower((string) $filters['status']) === 'available' && ! $normalized['in_stock']) {
                continue;
            }
            $out[] = $normalized;
        }

        return ['result' => true, 'data' => $out, 'raw' => ['count' => count($out)]];
    }

    protected function normalizeRow(array $row, string $cmd): array
    {
        $inStock = (bool) ($row['buyer_product_status'] ?? true)
            && (bool) ($row['seller_product_status'] ?? true);

        return [
            'code' => (string) ($row['buyer_sku_code'] ?? ''),
            'name' => (string) ($row['product_name'] ?? ''),
            'game' => (string) ($row['brand'] ?? ''),
            'category' => (string) ($row['category'] ?? ''),
            'type' => (string) ($row['type'] ?? ''),
            // Pascabayar: harga tergantung tagihan -> cost 0, admin/komisi disimpan mentah.
            'price' => $cmd === 'pasca' ? 0 : (int) ($row['price'] ?? 0),
            'status' => $inStock ? 'available' : 'empty',
            'in_stock' => $inStock,
            'stock' => ($row['unlimited_stock'] ?? true) ? -1 : (int) ($row['stock'] ?? 0),
            'desc' => (string) ($row['desc'] ?? ''),
            'seller_name' => (string) ($row['seller_name'] ?? ''),
            'admin' => (int) ($row['admin'] ?? 0),
            'commission' => (int) ($row['commission'] ?? 0),
        ];
    }

    public function getGames(array $filters = []): array
    {
        // JANGAN cache kegagalan: rate-limit rc=83 / kredensial salah tidak boleh
        // mengunci dropdown kategori kosong selama 10 menit.
        // Cache sukses per username agar ganti akun tidak basi.
        $key = 'digiflazz_brands.'.md5($this->username());
        $ttl = (int) ($this->credentials['brands_cache_ttl'] ?? 600);

        try {
            $brands = Cache::remember($key, $ttl, function () {
                $res = $this->getProducts([]);

                if (! ($res['result'] ?? false)) {
                    // Lempar agar remember() TIDAK menyimpan hasil gagal.
                    throw new \RuntimeException($res['message'] ?? 'Gagal ambil daftar brand Digiflazz');
                }

                return array_values(array_unique(array_filter(array_column($res['data'], 'game'))));
            });
        } catch (\Throwable $e) {
            return ['result' => false, 'message' => $e->getMessage(), 'data' => []];
        }
        sort($brands);

        return ['result' => true, 'data' => $brands];
    }

    public function order(string $productCode, string $target, array $options = []): array
    {
        // ref_id stabil per trx (diisi OrderService: "AP-{id}").
        // Retry / cek status memakai ref_id yang sama -> tidak double charge.
        $refId = (string) ($options['ref_id'] ?? ('AP-'.($options['trx_id'] ?? uniqid()).'-'.substr(md5(uniqid('', true)), 0, 6)));

        $body = [
            'username' => $this->username(),
            'buyer_sku_code' => $productCode,
            'customer_no' => $target,
            'ref_id' => $refId,
            'sign' => $this->signRef($refId),
        ];
        if ($this->sandbox) {
            $body['testing'] = true;
        }
        if (! empty($options['max_price'])) {
            $body['max_price'] = (int) $options['max_price'];
        }
        if (! empty($options['cb_url'])) {
            $body['cb_url'] = $options['cb_url'];
        }

        $json = $this->post('/transaction', $body);
        $data = $json['data'] ?? null;

        if (! is_array($data) || ($data['ref_id'] ?? null) === null) {
            return ['result' => false, 'message' => $json['message'] ?? 'No response dari Digiflazz', 'raw' => $json];
        }

        $rc = (string) ($data['rc'] ?? '');

        if (! self::transactionFormed($rc)) {
            // Request ditolak (saldo kurang rc=44, sign salah rc=41, IP belum whitelist rc=45, ...).
            // Boleh fallback ke supplier lain -> result false.
            return [
                'result' => false,
                'message' => "Digiflazz rc={$rc}: ".($data['message'] ?? 'request ditolak'),
                'rc' => $rc,
                'raw' => $json,
            ];
        }

        $status = (string) ($data['status'] ?? 'Pending');

        return [
            'result' => true,
            'data' => [
                'trxid' => (string) $data['ref_id'],
                'status' => $status,
                'sn' => (string) ($data['sn'] ?? ''),
                'price' => (int) ($data['price'] ?? 0),
                'message' => (string) ($data['message'] ?? ''),
                'rc' => $rc,
            ],
            'raw' => $json,
        ];
    }

    public function checkStatus(string $supplierTrxId, array $options = []): array
    {
        // Prepaid: cek status = topup ulang dengan ref_id yang SAMA.
        $body = [
            'username' => $this->username(),
            'buyer_sku_code' => (string) ($options['buyer_sku_code'] ?? ''),
            'customer_no' => (string) ($options['customer_no'] ?? ''),
            'ref_id' => $supplierTrxId,
            'sign' => $this->signRef($supplierTrxId),
        ];
        if ($this->sandbox) {
            $body['testing'] = true;
        }

        $json = $this->post('/transaction', $body);
        $data = $json['data'] ?? null;

        if (! is_array($data) || ($data['ref_id'] ?? null) === null) {
            return ['result' => false, 'message' => 'Status tidak ditemukan', 'raw' => $json];
        }

        $status = (string) ($data['status'] ?? 'Pending');

        return [
            'result' => true,
            'status' => self::mapStatus($status),
            'data' => [
                'status' => $status,
                'trxid' => (string) $data['ref_id'],
                'sn' => (string) ($data['sn'] ?? ''),
                'rc' => (string) ($data['rc'] ?? ''),
                'message' => (string) ($data['message'] ?? ''),
            ],
            'raw' => $json,
        ];
    }

    public static function transactionFormed(string $rc): bool
    {
        return in_array($rc, self::FORMED_RCS, true);
    }

    public static function mapStatus(string $status): string
    {
        return match (strtolower($status)) {
            'sukses', 'success' => 'success',
            'gagal', 'failed' => 'failed',
            default => 'pending',
        };
    }

    /**
     * Verifikasi webhook Digiflazz: header X-Hub-Signature = "sha1=" + HMAC-SHA1(raw body, webhook secret).
     * Secret diatur di Digiflazz: Atur Koneksi > API > Webhook.
     */
    public function verifyWebhook(string $rawBody, ?string $signatureHeader): bool
    {
        $secret = (string) ($this->credentials['webhook_secret'] ?? '');

        if ($secret === '' || $signatureHeader === null || $signatureHeader === '') {
            return false;
        }

        return hash_equals('sha1='.hash_hmac('sha1', $rawBody, $secret), $signatureHeader);
    }
}
