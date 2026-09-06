<?php

namespace App\Suppliers;

use App\Contracts\NicknameCheckableInterface;
use App\Contracts\SupplierProviderInterface;
use Illuminate\Support\Facades\Http;

class VipResellerProvider implements SupplierProviderInterface, NicknameCheckableInterface
{
    public function __construct(protected array $credentials = [], protected bool $sandbox = true) {}

    public function code(): string
    {
        return 'vip-reseller';
    }

    protected function baseUrl(): string
    {
        if (! empty($this->credentials['base_url'])) {
            return rtrim($this->credentials['base_url'], '/').'/';
        }

        // WAJIB trailing slash: https://vip-reseller.co.id/api (tanpa slash)
        // redirect 301 via Cloudflare dan POST body hilang -> "No response".
        return 'https://vip-reseller.co.id/api/';
    }

    protected function sign(): string
    {
        return md5(($this->credentials['api_id'] ?? '').($this->credentials['api_key'] ?? ''));
    }

    protected function authParams(): array
    {
        return [
            'key' => $this->credentials['api_id'] ?? '',
            'sign' => $this->sign(),
        ];
    }

    public function getBalance(): array
    {
        $res = $this->post(array_merge($this->authParams(), ['type' => 'balance']));

        $json = $res['json'] ?? null;
        if (! is_array($json) || ! array_key_exists('result', $json)) {
            return ['result' => false, 'message' => 'No response / bukan JSON: '.($res['preview'] ?? '-'), 'raw' => $res];
        }

        return $json;
    }

    protected function post(array $params, ?string $suffix = null): array
    {
        $url = $this->baseUrl().ltrim((string) ($suffix ?? ''), '/');

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

    public function getProducts(array $filters = []): array
    {
        $params = array_merge($this->authParams(), ['type' => 'services']);

        if (! empty($filters['game'])) {
            $params['filter_game'] = $filters['game'];
        }
        if (! empty($filters['status'])) {
            $params['filter_status'] = $filters['status'];
        }
        if (! empty($filters['code'])) {
            $params['filter_code'] = $filters['code'];
        }

        $res = $this->post($params);
        $json = $res['json'] ?? null;

        if (! is_array($json)) {
            return ['result' => false, 'message' => 'No response / bukan JSON: '.($res['preview'] ?? '-'), 'data' => []];
        }

        return $json;
    }

    public function order(string $productCode, string $target, array $options = []): array
    {
        $res = $this->post(array_merge(
            $this->authParams(),
            [
                'type' => 'order',
                'service' => $productCode,
                'data_no' => $target,
            ]
        ));

        $json = $res['json'] ?? null;

        return is_array($json) ? $json : ['result' => false, 'message' => 'No response / bukan JSON: '.($res['preview'] ?? '-')];
    }

    public function checkStatus(string $supplierTrxId): array
    {
        $res = $this->post(array_merge(
            $this->authParams(),
            ['type' => 'status', 'trxid' => $supplierTrxId]
        ));

        $json = $res['json'] ?? null;

        return is_array($json) ? $json : ['result' => false, 'message' => 'No response / bukan JSON: '.($res['preview'] ?? '-')];
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

        $res = $this->post($params, 'game-feature');
        $json = $res['json'] ?? null;

        return is_array($json) ? $json : ['result' => false, 'message' => 'No response / bukan JSON: '.($res['preview'] ?? '-')];
    }

    public function getGames(array $filters = []): array
    {
        $params = array_merge($this->authParams(), ['type' => 'games-list']);
        $res = $this->post($params);
        $json = $res['json'] ?? null;

        return is_array($json) ? $json : ['result' => false, 'message' => 'No response / bukan JSON: '.($res['preview'] ?? '-'), 'data' => []];
    }
}
