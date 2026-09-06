<?php

namespace App\Suppliers;

use App\Contracts\SupplierProviderInterface;
use Illuminate\Support\Facades\Http;

/**
 * TokoVoucher API.
 *
 * Dok: https://docs.tokovoucher.net/
 * - Base: https://api.tokovoucher.net/ (POST JSON) + GET query untuk produk/saldo
 * - Kredensial: member_code + secret (halaman Pengaturan Secret Key),
 *   signature default = secret itu sendiri untuk GET saldo/produk.
 * - Order/status: sign = md5(MEMBER_CODE:SECRET:REF_ID)
 * - ref_id unik & stabil per transaksi (cek status & webhook pakai ref_id ini)
 * - HTTP error / timeout = PENDING (jangan dianggap gagal, tunggu webhook/polling)
 * - Whitelist IP mereka 188.166.243.56 di server kita; IP VPS kita di-whitelist di member area.
 */
class TokoVoucherProvider implements SupplierProviderInterface
{
    public const BASE_URL = 'https://api.tokovoucher.net';

    public function __construct(protected array $credentials = [], protected bool $sandbox = true) {}

    public function code(): string
    {
        return 'toko-voucher';
    }

    protected function baseUrl(): string
    {
        return rtrim($this->credentials['base_url'] ?? self::BASE_URL, '/');
    }

    protected function memberCode(): string
    {
        return (string) ($this->credentials['member_code'] ?? '');
    }

    protected function secret(): string
    {
        return (string) ($this->credentials['secret'] ?? '');
    }

    public function signRef(string $refId): string
    {
        return md5($this->memberCode().':'.$this->secret().':'.$refId);
    }

    protected function get(string $path, array $query): array
    {
        try {
            $res = Http::timeout(30)->get($this->baseUrl().$path, $query);
        } catch (\Throwable $e) {
            return ['http_ok' => false, 'pending_hint' => true, 'message' => 'HTTP error: '.$e->getMessage()];
        }

        $json = $res->json();
        if (! is_array($json)) {
            return ['http_ok' => false, 'pending_hint' => true, 'message' => 'Response bukan JSON', 'preview' => mb_substr((string) $res->body(), 0, 200)];
        }
        $json['http_ok'] = $res->successful();

        return $json;
    }

    protected function postJson(string $path, array $body): array
    {
        try {
            $res = Http::asJson()->timeout(30)->post($this->baseUrl().$path, $body);
        } catch (\Throwable $e) {
            return ['http_ok' => false, 'pending_hint' => true, 'message' => 'HTTP error: '.$e->getMessage()];
        }

        $json = $res->json();
        if (! is_array($json)) {
            return ['http_ok' => false, 'pending_hint' => true, 'message' => 'Response bukan JSON', 'preview' => mb_substr((string) $res->body(), 0, 200)];
        }
        $json['http_ok'] = $res->successful();

        return $json;
    }

    /** Saldo: GET /member?member_code=&signature= (signature default = secret) */
    public function getBalance(): array
    {
        $json = $this->get('/member', [
            'member_code' => $this->memberCode(),
            'signature' => $this->secret(),
        ]);

        $saldo = $json['data']['saldo'] ?? null;

        if (($json['status'] ?? 0) != 1 || ! is_numeric($saldo)) {
            return [
                'result' => false,
                'message' => $json['error_msg'] ?? $json['message'] ?? 'Gagal cek saldo TokoVoucher',
                'raw' => $json,
            ];
        }

        return ['result' => true, 'data' => ['balance' => (int) $saldo], 'raw' => $json];
    }

    /**
     * Daftar produk.
     * - Tanpa filter / filter game (nama operator, cth "Free Fire"):
     *   GET /member/produk/full lalu saring lokal (endpoint search hanya paham
     *   PREFIX KODE seperti FF/FF5/ML, bukan nama operator).
     * - Filter kode eksplisit (['code'/'kode']): GET /produk/code?kode= (prefix).
     */
    public function getProducts(array $filters = []): array
    {
        if (! empty($filters['code']) || ! empty($filters['kode'])) {
            return $this->searchProducts((string) ($filters['code'] ?? $filters['kode']));
        }

        $json = $this->get('/member/produk/full', [
            'member_code' => $this->memberCode(),
            'signature' => $this->secret(),
        ]);

        if (($json['status'] ?? 0) != 1 || ! is_array($json['data'] ?? null)) {
            return [
                'result' => false,
                'message' => $json['error_msg'] ?? $json['message'] ?? 'Gagal ambil produk TokoVoucher',
                'data' => [],
                'raw' => $json,
            ];
        }

        $data = $json['data'];
        $categories = collect($data['category'] ?? [])->keyBy('id');
        $operators = collect($data['operator'] ?? [])->keyBy('id');
        $types = collect($data['jenis'] ?? [])->keyBy('id');

        $out = [];
        foreach ((array) ($data['produk'] ?? []) as $row) {
            if (! is_array($row) || empty($row['kode_produk'])) {
                continue;
            }
            $operator = $operators->get($row['operator_id'] ?? null, []);
            $category = $categories->get($row['kategori_id'] ?? null, []);
            $type = $types->get($row['jenis_id'] ?? null, []);

            $out[] = $this->normalizeRow($row, (array) $operator, (array) $category, (array) $type);
        }

        // Saring lokal: game = nama operator persis (dropdown Tarik Produk berisi
        // nama operator dari getGames, bukan prefix kode).
        if (! empty($filters['game'])) {
            $want = strtolower(trim((string) $filters['game']));
            $out = array_values(array_filter($out, fn ($r) => strtolower(trim((string) ($r['game'] ?? ''))) === $want));
        }

        if (! empty($filters['status']) && strtolower((string) $filters['status']) === 'available') {
            $out = array_values(array_filter($out, fn ($r) => $r['in_stock']));
        }

        return ['result' => true, 'data' => $out, 'raw' => ['count' => count($out)]];
    }

    protected function searchProducts(string $kode): array
    {
        $json = $this->get('/produk/code', [
            'member_code' => $this->memberCode(),
            'signature' => $this->secret(),
            'kode' => $kode,
        ]);

        if (($json['status'] ?? 0) != 1 || ! is_array($json['data'] ?? null)) {
            return [
                'result' => false,
                'message' => $json['error_msg'] ?? $json['message'] ?? 'Produk tidak ditemukan',
                'data' => [],
                'raw' => $json,
            ];
        }

        $out = [];
        foreach ($json['data'] as $row) {
            if (! is_array($row) || empty($row['code'])) {
                continue;
            }
            $out[] = [
                'code' => (string) $row['code'],
                'name' => (string) ($row['nama_produk'] ?? $row['code']),
                'game' => (string) ($row['operator_produk'] ?? ''),
                'category' => (string) ($row['category_name'] ?? ''),
                'type' => (string) ($row['jenis_name'] ?? ''),
                'price' => (int) ($row['price'] ?? 0),
                'status' => ! empty($row['status']) ? 'available' : 'empty',
                'in_stock' => ! empty($row['status']),
                'desc' => (string) ($row['deskripsi'] ?? ''),
            ];
        }

        return ['result' => true, 'data' => $out, 'raw' => ['count' => count($out)]];
    }

    protected function normalizeRow(array $row, array $operator, array $category, array $type): array
    {
        $game = (string) ($operator['nama'] ?? '');
        $inStock = ((int) ($row['status'] ?? 0) === 1) && ((int) ($operator['status'] ?? 1) === 1);

        return [
            'code' => (string) ($row['kode_produk'] ?? ''),
            'name' => (string) ($row['nama'] ?? ''),
            'game' => $game !== '' ? $game : 'Lainnya',
            'category' => (string) ($category['nama'] ?? ''),
            'type' => (string) ($type['nama'] ?? ''),
            // 3 tier harga TokoVoucher: gold / vip / vvip.
            'price' => (int) ($row['price'] ?? 0),
            'price_gold' => (int) ($row['price_gold'] ?? $row['price'] ?? 0),
            'price_vip' => (int) ($row['price_vip'] ?? $row['price'] ?? 0),
            'price_vvip' => (int) ($row['price_vvip'] ?? $row['price'] ?? 0),
            'status' => $inStock ? 'available' : 'empty',
            'in_stock' => $inStock,
            'desc' => (string) ($row['deskripsi'] ?? ''),
            'logo' => (string) ($operator['logo'] ?? ''),
            'format_form' => (string) ($type['format_form'] ?? ''),
        ];
    }

    public function getGames(array $filters = []): array
    {
        $res = $this->getProducts([]);

        if (! ($res['result'] ?? false)) {
            return $res;
        }

        $brands = array_values(array_unique(array_filter(array_column($res['data'], 'game'))));
        sort($brands);

        return ['result' => true, 'data' => $brands];
    }

    /**
     * Order: POST /v1/transaksi {ref_id, produk, tujuan, server_id, member_code, sign}.
     * ref_id stabil per trx ("TV-{id}"); server_id terpisah untuk game (dok: boleh gabung "id|server" atau param terpisah).
     * HTTP error/timeout = PENDING (result true + status pending, jangan fallback).
     */
    public function order(string $productCode, string $target, array $options = []): array
    {
        $refId = (string) ($options['ref_id'] ?? ('TV-'.($options['trx_id'] ?? uniqid()).'-'.substr(md5(uniqid('', true)), 0, 6)));

        $body = [
            'ref_id' => $refId,
            'produk' => $productCode,
            'tujuan' => $target,
            'server_id' => (string) ($options['server_id'] ?? ''),
            'member_code' => $this->memberCode(),
            'signature' => $this->signRef($refId),
        ];

        $json = $this->postJson('/v1/transaksi', $body);

        if (! empty($json['pending_hint']) && ! isset($json['status'])) {
            return [
                'result' => true,
                'data' => ['trxid' => $refId, 'status' => 'pending', 'message' => $json['message'] ?? 'Timeout — menunggu webhook/polling'],
                'raw' => $json,
            ];
        }

        $status = strtolower((string) ($json['status'] ?? ''));

        if ($status === '' && isset($json['error_msg'])) {
            return ['result' => false, 'message' => 'TokoVoucher: '.$json['error_msg'], 'raw' => $json];
        }

        return [
            'result' => true,
            'data' => [
                'trxid' => (string) ($json['trx_id'] ?? $refId),
                'ref_id' => (string) ($json['ref_id'] ?? $refId),
                'status' => $status !== '' ? $status : 'pending',
                'sn' => (string) ($json['sn'] ?? ''),
                'price' => (int) ($json['price'] ?? 0),
                'message' => (string) ($json['message'] ?? ''),
            ],
            'raw' => $json,
        ];
    }

    /**
     * Cek status: POST /v1/transaksi/status {ref_id, member_code, sign}.
     * Pakai ref_id KITA (bukan trx_id mereka).
     */
    public function checkStatus(string $supplierTrxId, array $options = []): array
    {
        $refId = (string) ($options['ref_id'] ?? $supplierTrxId);

        $json = $this->postJson('/v1/transaksi/status', [
            'ref_id' => $refId,
            'member_code' => $this->memberCode(),
            'signature' => $this->signRef($refId),
        ]);

        if (! empty($json['pending_hint']) && ! isset($json['status'])) {
            return ['result' => true, 'status' => 'pending', 'data' => ['status' => 'pending', 'trxid' => $refId], 'raw' => $json];
        }

        $status = strtolower((string) ($json['status'] ?? ''));

        if ($status === '') {
            return ['result' => false, 'message' => $json['error_msg'] ?? 'Status tidak ditemukan', 'raw' => $json];
        }

        return [
            'result' => true,
            'status' => self::mapStatus($status),
            'data' => [
                'status' => $status,
                'trxid' => (string) ($json['trx_id'] ?? $refId),
                'ref_id' => (string) ($json['ref_id'] ?? $refId),
                'sn' => (string) ($json['sn'] ?? ''),
                'price' => (int) ($json['price'] ?? 0),
                'message' => (string) ($json['message'] ?? ''),
            ],
            'raw' => $json,
        ];
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
     * Verifikasi webhook: header X-TokoVoucher-Authorization = md5(MEMBER_CODE:SECRET:REF_ID).
     */
    public function verifyWebhook(?string $authHeader, string $refId): bool
    {
        if ($authHeader === null || $authHeader === '' || $refId === '') {
            return false;
        }

        return hash_equals($this->signRef($refId), trim($authHeader));
    }
}
