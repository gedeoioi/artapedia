<?php

namespace App\Services;

use App\Models\Product;
use App\Models\SiteSetting;
use App\Models\SupplierConfig;
use App\Suppliers\VipResellerProvider;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

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
    /**
     * Kredensial yang wajib ada sebelum pengecek dianggap siap.
     */
    public function hasCredentials(SupplierConfig $config): bool
    {
        $credentials = $config->credentials ?? [];

        return trim((string) ($credentials['api_id'] ?? '')) !== ''
            && trim((string) ($credentials['api_key'] ?? '')) !== '';
    }

    /**
     * Pengecek nickname, atau null kalau belum siap dipakai.
     *
     * "Aktif" saja tidak cukup: config yang aktif tapi kredensialnya kosong
     * tetap akan gagal saat dipanggil, dan pembeli melihat pesan error dari
     * API. Jadi kredensial ikut diperiksa di sini, supaya tombol/cek otomatis
     * tidak pernah memanggil API yang pasti gagal.
     */
    public function checker(): ?VipResellerProvider
    {
        $config = SupplierConfig::where('code', 'vip-reseller')
            ->where('is_active', true)
            ->first();

        if (! $config || ! $this->hasCredentials($config)) {
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

    /**
     * Apakah produk ini mendukung cek nickname.
     *
     * Dianggap mendukung kalau kodenya bisa ditentukan: dari kolom
     * nickname_check_code di admin, atau ditebak dari nama game. Dipakai
     * checkout untuk memutuskan apakah cek otomatis dijalankan.
     */
    public function supports(Product $product): bool
    {
        return $this->resolveCode($product) !== null;
    }

    /**
     * Apakah game ini memerlukan Server / Zone saat cek nickname.
     *
     * Untuk game seperti Mobile Legends, cek otomatis akan selalu gagal kalau
     * zone belum diisi — jadi checkout perlu tahu agar bisa menandainya wajib.
     */
    public function requiresZone(Product $product): bool
    {
        $code = $this->resolveCode($product);

        return $code !== null && VipResellerProvider::nicknameNeedsZone($code);
    }

    public function check(Product $product, string $userId, ?string $zoneId = null): array
    {
        $provider = $this->checker();

        if (! $provider) {
            return ['ok' => false, 'message' => 'Cek nickname belum aktif. Isi User ID dan Server secara manual.'];
        }

        $code = $this->resolveCode($product);

        if (! $code) {
            // Pesan ini sampai ke pembeli, jadi jangan menyebut kolom admin.
            return ['ok' => false, 'message' => 'Cek nickname belum tersedia untuk produk ini. Isi User ID dan Server secara manual.'];
        }

        if (VipResellerProvider::nicknameNeedsZone($code) && empty($zoneId)) {
            return ['ok' => false, 'message' => 'Zone / Server wajib diisi untuk game ini.'];
        }

        // Mengetik ulang ID yang sama adalah hal biasa (salah ketik, ganti
        // nominal, kembali ke halaman). Hasil yang sudah pernah didapat dipakai
        // ulang supaya kuota endpoint berbayar tidak terbuang. Hanya hasil
        // BERHASIL yang di-cache — kegagalan bisa bersifat sementara, dan
        // menyimpannya berarti pembeli melihat error lama setelah diperbaiki.
        $ttl = (int) SiteSetting::get('nickname_cache_ttl', 300);
        $cacheKey = 'nickname:'.sha1($code.'|'.$userId.'|'.($zoneId ?? ''));

        if ($ttl > 0) {
            $cached = Cache::get($cacheKey);

            if (is_array($cached)) {
                return $cached + ['cached' => true];
            }
        }

        $res = $provider->checkNickname($code, $userId, $zoneId);

        // Respons mentah selalu dicatat. Tanpa ini, pesan singkat seperti "Fails."
        // dari API tidak bisa ditelusuri sama sekali setelah kejadian.
        Log::info('Cek nickname', [
            'game_code' => $code,
            'user_id' => $userId,
            'zone_id' => $zoneId,
            'raw' => $res['raw'] ?? null,
        ]);

        if (! ($res['result'] ?? false)) {
            // Kegagalan transport / respons bukan JSON adalah masalah konfigurasi,
            // bukan ID pemain. Pembeli tidak bisa memperbaikinya dengan mencoba
            // ulang, jadi pesannya harus jujur menyebut cek sedang tidak tersedia.
            $mentah = (string) ($res['message'] ?? '');
            $transportGagal = str_starts_with($mentah, 'HTTP error')
                || str_starts_with($mentah, 'No response');

            if ($transportGagal) {
                return ['ok' => false, 'message' => 'Cek nickname sedang tidak bisa dihubungi. Isi User ID dan Server secara manual.'];
            }

            return ['ok' => false, 'message' => $mentah !== '' ? $mentah : 'Nickname tidak ditemukan. Periksa User ID / Zone.'];
        }

        $nickname = $res['nickname'] ?? (is_string($res['data'] ?? null) ? $res['data'] : null);
        $country = $res['country'] ?? null;

        $hasil = [
            'ok' => true,
            'nickname' => $nickname,
            'country' => $country,
            'via' => 'vip-reseller',
            'message' => $nickname
                ? 'Nickname: '.$nickname.(is_array($country) && isset($country['name']) ? ' ('.$country['name'].')' : '')
                : 'Ditemukan',
        ];

        if ($ttl > 0) {
            Cache::put($cacheKey, $hasil, $ttl);
        }

        return $hasil;
    }
}
