<?php

namespace App\Services;

use App\Models\Product;
use App\Models\SupplierConfig;
use App\Suppliers\VipResellerProvider;

/**
 * Cek nickname game SELALU via API VIPayment (/api/game-feature type=get-nickname),
 * terlepas supplier asal produk (Digiflazz / TokoVoucher / VIP).
 *
 * Alasan: hanya VIPayment yang punya endpoint get-nickname. Nickname pemain
 * (ML, FF, dll) sama untuk semua supplier — yang dicek User ID + Zone-nya,
 * bukan dari supplier mana produk dibeli.
 *
 * Syarat: minimal 1 config supplier vip-reseller AKTIF dengan kredensial valid
 * (api_id + api_key). Kalau tidak ada, kembalikan error yang jelas.
 */
class NicknameService
{
    public function checker(): ?VipResellerProvider
    {
        $config = SupplierConfig::where('code', 'vip-reseller')
            ->where('is_active', true)
            ->first();

        if (! $config) {
            return null;
        }

        $provider = ProviderFactory::supplierFor($config);

        return $provider instanceof VipResellerProvider ? $provider : null;
    }

    public function isAvailable(): bool
    {
        return $this->checker() !== null;
    }

    public function resolveCode(Product $product): ?string
    {
        if ($product->nickname_check_code) {
            return $product->nickname_check_code;
        }

        return VipResellerProvider::guessNicknameCode($product->game);
    }

    public function check(Product $product, string $userId, ?string $zoneId = null): array
    {
        $provider = $this->checker();

        if (! $provider) {
            return ['ok' => false, 'message' => 'Layanan cek nickname nonaktif (supplier VIPayment belum aktif).'];
        }

        $code = $this->resolveCode($product);

        if (! $code) {
            return ['ok' => false, 'message' => 'Produk belum dipetakan ke kode nickname. Isi kolom nickname_check_code di admin.'];
        }

        if (VipResellerProvider::nicknameNeedsZone($code) && empty($zoneId)) {
            return ['ok' => false, 'message' => 'Zone / Server wajib diisi untuk game ini.'];
        }

        $res = $provider->checkNickname($code, $userId, $zoneId);

        if (! ($res['result'] ?? false)) {
            return ['ok' => false, 'message' => $res['message'] ?? 'Nickname tidak ditemukan. Periksa User ID / Zone.'];
        }

        $nickname = $res['nickname'] ?? (is_string($res['data'] ?? null) ? $res['data'] : null);
        $country = $res['country'] ?? null;

        return [
            'ok' => true,
            'nickname' => $nickname,
            'country' => $country,
            'via' => 'vip-reseller',
            'message' => $nickname
                ? 'Nickname: '.$nickname.(is_array($country) && isset($country['name']) ? ' ('.$country['name'].')' : '')
                : 'Ditemukan',
        ];
    }
}
