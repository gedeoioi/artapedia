<?php

namespace App\Suppliers;

use App\Contracts\NicknameCheckableInterface;
use App\Contracts\SupplierProviderInterface;
use Illuminate\Support\Facades\Http;

/**
 * VIPayment (vip-reseller.co.id) H2H API.
 *
 * Dok resmi:
 * - Profile  : https://vip-reseller.co.id/page/api/profile        -> POST /api/profile (saldo)
 * - Prepaid  : https://vip-reseller.co.id/page/api/prepaid        -> POST /api/prepaid (order/status/services pulsa & PPOB)
 * - Game     : https://vip-reseller.co.id/page/api/game-feature   -> POST /api/game-feature (order/status/services/get-nickname game)
 *
 * Auth: key = API KEY, sign = md5(API ID + API KEY), lihat di vip-reseller.co.id/account/profile.
 * Produk game (ML dll) HARUS lewat /api/game-feature dengan data_zone terpisah
 * (jangan digabung "id|zone" seperti sebelumnya).
 * Webhook: header X-Client-Signature = md5(API ID + API KEY), whitelist IP 178.248.73.218.
 */
class VipResellerProvider implements SupplierProviderInterface, NicknameCheckableInterface
{
    public const BASE_URL = 'https://vip-reseller.co.id';

    public function __construct(protected array $credentials = [], protected bool $sandbox = true) {}

    public function code(): string
    {
        return 'vip-reseller';
    }

    protected function baseUrl(): string
    {
        return rtrim($this->credentials['base_url'] ?? self::BASE_URL, '/');
    }

    protected function apiId(): string
    {
        return trim((string) ($this->credentials['api_id'] ?? ''));
    }

    protected function apiKey(): string
    {
        return trim((string) ($this->credentials['api_key'] ?? ''));
    }

    public function sign(): string
    {
        return md5($this->apiId().$this->apiKey());
    }

    protected function authParams(): array
    {
        return [
            'key' => $this->apiKey(),
            'sign' => $this->sign(),
        ];
    }

    protected function post(string $path, array $params): array
    {
        $url = $this->baseUrl().$path;

        try {
            $res = Http::asForm()->timeout(30)->post($url, $params);
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => 'HTTP error: '.$e->getMessage(), 'url' => $url];
        }

        $body = (string) $res->body();
        $json = $res->json();

        return [
            'ok' => $res->successful(),
            'status' => $res->status(),
            'json' => $json,
            'preview' => mb_substr($body, 0, 200),
            'url' => $url,
        ];
    }

    protected function unwrap(array $res): array
    {
        $json = $res['json'] ?? null;

        if (! is_array($json)) {
            return ['result' => false, 'message' => 'No response / bukan JSON: '.($res['preview'] ?? '-'), 'raw' => $res];
        }

        return $json;
    }

    /** Saldo via POST /api/profile -> data.balance */
    public function getBalance(): array
    {
        $json = $this->unwrap($this->post('/api/profile', $this->authParams()));

        $balance = $json['data']['balance'] ?? null;

        if (! ($json['result'] ?? false) || ! is_numeric($balance)) {
            return [
                'result' => false,
                'message' => $json['message'] ?? 'Gagal ambil saldo VIPayment',
                'raw' => $json,
            ];
        }

        return ['result' => true, 'data' => ['balance' => (int) $balance], 'raw' => $json];
    }

    /**
     * Daftar layanan.
     * - Game: POST /api/game-feature type=services + filter_game + filter_status
     * - Pulsa/PPOB: POST /api/prepaid type=services + filter_type (type|brand) + filter_value
     * Pilih via $filters['channel'] = 'game' (default) | 'prepaid'.
     */
    public function getProducts(array $filters = []): array
    {
        $channel = strtolower((string) ($filters['channel'] ?? 'game'));
        $params = $this->authParams() + ['type' => 'services'];

        if ($channel === 'prepaid') {
            if (! empty($filters['filter_type'])) {
                $params['filter_type'] = $filters['filter_type'];
            } elseif (! empty($filters['game'])) {
                $params['filter_type'] = 'brand';
            }
            if (! empty($filters['filter_value'])) {
                $params['filter_value'] = $filters['filter_value'];
            } elseif (! empty($filters['game'])) {
                $params['filter_value'] = $filters['game'];
            }
            $path = '/api/prepaid';
        } else {
            if (! empty($filters['game'])) {
                $params['filter_game'] = $filters['game'];
            }
            if (! empty($filters['status'])) {
                $params['filter_status'] = $filters['status'];
            }
            $path = '/api/game-feature';
        }

        $json = $this->unwrap($this->post($path, $params));

        if (! ($json['result'] ?? false) || ! is_array($json['data'] ?? null)) {
            return ['result' => false, 'message' => $json['message'] ?? 'Gagal ambil layanan', 'data' => [], 'raw' => $json];
        }

        $out = [];
        foreach ($json['data'] as $row) {
            if (! is_array($row) || empty($row['code'])) {
                continue;
            }
            $normalized = $this->normalizeRow($row, $channel);
            if (! empty($filters['status']) && strtolower((string) $filters['status']) === 'available' && ! $normalized['in_stock']) {
                continue;
            }
            $out[] = $normalized;
        }

        return ['result' => true, 'data' => $out, 'raw' => ['count' => count($out)]];
    }

    protected function normalizeRow(array $row, string $channel): array
    {
        $price = $row['price'] ?? 0;
        $basic = is_array($price) ? (int) ($price['basic'] ?? 0) : (int) $price;
        $premium = is_array($price) ? (int) ($price['premium'] ?? $basic) : $basic;
        $special = is_array($price) ? (int) ($price['special'] ?? $basic) : $basic;

        return [
            'code' => (string) ($row['code'] ?? ''),
            'name' => (string) ($row['name'] ?? ''),
            'game' => (string) ($row['game'] ?? $row['brand'] ?? ''),
            'category' => (string) ($row['category'] ?? ''),
            'type' => (string) ($row['type'] ?? ''),
            'price' => $basic,
            'price_basic' => $basic,
            'price_premium' => $premium,
            'price_special' => $special,
            'status' => (string) ($row['status'] ?? 'available'),
            'in_stock' => strtolower((string) ($row['status'] ?? 'available')) === 'available',
            'desc' => (string) ($row['description'] ?? $row['note'] ?? ''),
            'channel' => $channel,
        ];
    }

    /**
     * Order.
     * - Game: POST /api/game-feature, data_no = user ID, data_zone = zone (TERPISAH).
     * - Prepaid: POST /api/prepaid, data_no = nomor tujuan.
     * $options['channel'] / $options['zone'] / $options['additional_data'] / $options['quantity'] (joki).
     */
    public function order(string $productCode, string $target, array $options = []): array
    {
        $isGame = $this->isGameChannel($productCode, $options);

        $params = array_merge($this->authParams(), [
            'type' => 'order',
            'service' => $productCode,
            'data_no' => $target,
        ]);

        if ($isGame && ! empty($options['zone'])) {
            $params['data_zone'] = $options['zone'];
        }
        foreach (['additional_data', 'post_additional_data', 'quantity'] as $extra) {
            if (isset($options[$extra]) && $options[$extra] !== '') {
                $params[$extra === 'post_additional_data' ? 'post_additional_data' : ($extra === 'additional_data' ? 'additional_data' : 'quantity')] = $options[$extra];
            }
        }

        $json = $this->unwrap($this->post($isGame ? '/api/game-feature' : '/api/prepaid', $params));

        if (! ($json['result'] ?? false) || ! is_array($json['data'] ?? null)) {
            return ['result' => false, 'message' => $json['message'] ?? 'Order ditolak VIPayment', 'raw' => $json];
        }

        $data = $json['data'];

        return [
            'result' => true,
            'data' => [
                'trxid' => (string) ($data['trxid'] ?? ''),
                'status' => (string) ($data['status'] ?? 'waiting'),
                'sn' => (string) ($data['note'] ?? ''),
                'price' => (int) ($data['price'] ?? 0),
                'balance' => (int) ($data['balance'] ?? 0),
                'message' => (string) ($json['message'] ?? ''),
            ],
            'raw' => $json,
        ];
    }

    protected function isGameChannel(string $productCode, array $options): bool
    {
        if (! empty($options['channel'])) {
            return strtolower((string) $options['channel']) !== 'prepaid';
        }

        return true;
    }

    /**
     * Cek status: POST /api/game-feature (default) atau /api/prepaid.
     * Response data = ARRAY; ambil item trxid yang cocok (atau pertama).
     */
    public function checkStatus(string $supplierTrxId, array $options = []): array
    {
        $params = array_merge($this->authParams(), ['type' => 'status', 'trxid' => $supplierTrxId]);
        $path = (! empty($options['channel']) && strtolower((string) $options['channel']) === 'prepaid')
            ? '/api/prepaid'
            : '/api/game-feature';

        $json = $this->unwrap($this->post($path, $params));

        if (! ($json['result'] ?? false)) {
            return ['result' => false, 'message' => $json['message'] ?? 'Status tidak ditemukan', 'raw' => $json];
        }

        $item = $this->pickStatusItem($json['data'] ?? null, $supplierTrxId);

        if (! $item) {
            return ['result' => false, 'message' => 'Trxid tidak ditemukan di response', 'raw' => $json];
        }

        $status = (string) ($item['status'] ?? 'waiting');

        return [
            'result' => true,
            'status' => self::mapStatus($status),
            'data' => [
                'status' => $status,
                'trxid' => (string) ($item['trxid'] ?? $supplierTrxId),
                'sn' => (string) ($item['note'] ?? ''),
                'price' => (int) ($item['price'] ?? 0),
                'message' => (string) ($json['message'] ?? ''),
            ],
            'raw' => $json,
        ];
    }

    protected function pickStatusItem(mixed $data, string $trxid): ?array
    {
        if (! is_array($data)) {
            return null;
        }
        if (array_key_exists('trxid', $data)) {
            return $data;
        }
        foreach ($data as $item) {
            if (is_array($item) && (string) ($item['trxid'] ?? '') === $trxid) {
                return $item;
            }
        }

        return is_array($data[0] ?? null) ? $data[0] : null;
    }

    public static function mapStatus(string $status): string
    {
        return match (strtolower($status)) {
            'success' => 'success',
            'error' => 'failed',
            default => 'pending', // waiting | processing
        };
    }

    /**
     * Cek nickname: POST /api/game-feature type=get-nickname,
     * code = kode nickname (lihat .../api/nickname-game-code.txt),
     * target = user ID, additional_target = zone ID.
     *
     * Kode resmi (params = yang wajib diisi):
     * - mobile-legends (MLBB): userId + zoneId
     * - mobile-legends-region (MLBB region, min saldo 100rb): userId + zoneId
     * - free-fire (FF & FF Max): userId saja
     * - pubgm: userId saja | valorant: userId saja
     * - genshin-impact: userId + zone | honkai-star-rail: userId + zone
     * - pointblank: userId saja
     */
    public const NICKNAME_CODES = [
        'mobile-legends' => ['games' => ['mobile legends', 'mlbb', 'mobile legends a', 'mobile legends b', 'mobile legends gift'], 'needs_zone' => true],
        'mobile-legends-region' => ['games' => [], 'needs_zone' => true],
        'free-fire' => ['games' => ['free fire', 'free fire max', 'free fire global', 'free fire max global'], 'needs_zone' => false],
        'pubgm' => ['games' => ['pubg mobile', 'pubg mobile (global)', 'pubg mobile (id)', 'pubg : new state mobile'], 'needs_zone' => false],
        'valorant' => ['games' => ['valorant'], 'needs_zone' => false],
        'genshin-impact' => ['games' => ['genshin impact'], 'needs_zone' => true],
        'honkai-star-rail' => ['games' => ['honkai star rail'], 'needs_zone' => true],
        'pointblank' => ['games' => ['point blank', 'point blank (id)', 'voucher pb zepetto'], 'needs_zone' => false],
    ];

    public static function guessNicknameCode(string $gameName): ?string
    {
        $lower = mb_strtolower(trim($gameName));
        foreach (self::NICKNAME_CODES as $code => $meta) {
            if (in_array($lower, $meta['games'], true)) {
                return $code;
            }
        }

        return null;
    }

    public static function nicknameNeedsZone(string $code): bool
    {
        return (bool) (self::NICKNAME_CODES[$code]['needs_zone'] ?? true);
    }

    public function checkNickname(string $gameCode, string $userId, ?string $zoneId = null): array
    {
        $params = array_merge($this->authParams(), [
            'type' => 'get-nickname',
            'code' => $gameCode,
            'target' => $userId,
        ]);

        if ($zoneId !== null && $zoneId !== '') {
            $params['additional_target'] = $zoneId;
        }

        $json = $this->unwrap($this->post('/api/game-feature', $params));

        if (! ($json['result'] ?? false)) {
            return ['result' => false, 'message' => $json['message'] ?? 'Nickname tidak ditemukan', 'raw' => $json];
        }

        $nickname = $json['data'] ?? null;
        $country = $json['country'] ?? null;

        return [
            'result' => true,
            'nickname' => is_string($nickname) ? $nickname : null,
            'country' => $country,
            'data' => $nickname,
            'raw' => $json,
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
     * Verifikasi webhook VIPayment: header X-Client-Signature = md5(API ID + API KEY).
     */
    public function verifyWebhook(?string $signatureHeader): bool
    {
        if ($signatureHeader === null || $signatureHeader === '') {
            return false;
        }

        return hash_equals($this->sign(), $signatureHeader);
    }
}
