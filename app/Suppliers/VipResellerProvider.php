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
            return rtrim($this->credentials['base_url'], '/');
        }

        return $this->sandbox
            ? 'https://vip-reseller.co.id/api'
            : 'https://vip-reseller.co.id/api';
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
        $res = Http::asForm()->post($this->baseUrl(), array_merge(
            $this->authParams(),
            ['type' => 'balance']
        ));

        return $res->json() ?? ['result' => false, 'message' => 'No response'];
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

        $res = Http::asForm()->post($this->baseUrl(), $params);

        return $res->json() ?? ['result' => false, 'data' => []];
    }

    public function order(string $productCode, string $target, array $options = []): array
    {
        $res = Http::asForm()->post($this->baseUrl(), array_merge(
            $this->authParams(),
            [
                'type' => 'order',
                'service' => $productCode,
                'data_no' => $target,
            ]
        ));

        return $res->json() ?? ['result' => false, 'message' => 'No response'];
    }

    public function checkStatus(string $supplierTrxId): array
    {
        $res = Http::asForm()->post($this->baseUrl(), array_merge(
            $this->authParams(),
            ['type' => 'status', 'trxid' => $supplierTrxId]
        ));

        return $res->json() ?? ['result' => false, 'message' => 'No response'];
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

        $res = Http::asForm()->post($this->baseUrl().'/game-feature', $params);

        return $res->json() ?? ['result' => false, 'message' => 'No response'];
    }

    public function getGames(array $filters = []): array
    {
        $params = array_merge($this->authParams(), ['type' => 'games-list']);
        $res = Http::asForm()->post($this->baseUrl(), $params);

        return $res->json() ?? ['result' => false, 'data' => []];
    }
}
