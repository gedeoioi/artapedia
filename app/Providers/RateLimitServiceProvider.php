<?php

namespace App\Providers;

use App\Models\Transaction;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class RateLimitServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // Checkout: satu IP hanya boleh membuat order baru dalam jumlah terbatas
        // per menit. Tanpa ini, endpoint checkout bisa dipakai untuk membanjiri
        // supplier dengan order (dan menahan saldo user) secara massal.
        RateLimiter::for('checkout', function (Request $request): Limit {
            return Limit::perMinute(10)->by($request->ip())->response(
                fn () => response()->json([
                    'message' => 'Terlalu banyak permintaan checkout. Coba lagi sebentar lagi.',
                ], 429),
            );
        });

        // Pencarian invoice: kunci utama anti-enumerasi. Nomor invoice dan nomor
        // WhatsApp sama-sama bisa ditebak, jadi batas per IP dan per nilai yang
        // dicari dipasang bersamaan agar satu IP tidak bisa menyapu nomor HP.
        RateLimiter::for('invoice-lookup', function (Request $request): array {
            $key = strtolower(trim((string) ($request->get('code') ?: $request->get('phone'))));

            return [
                Limit::perMinute(15)->by('ip:'.$request->ip()),
                Limit::perMinute(5)->by('key:'.sha1($key)),
            ];
        });

        // Endpoint callback gateway/supplier: batasi supaya tidak bisa dipakai
        // untuk brute-force signature atau menghabiskan resource.
        RateLimiter::for('webhook', function (Request $request): Limit {
            return Limit::perMinute(120)->by($request->ip());
        });

        // Cek nickname game memanggil API supplier berbayar/terbatas, jadi
        // dibatasi lebih ketat daripada checkout.
        RateLimiter::for('nickname-check', function (Request $request): Limit {
            return Limit::perMinute(20)->by($request->ip())->response(
                fn () => response()->json(['message' => 'Terlalu banyak cek nickname. Tunggu sebentar.'], 429),
            );
        });

        // Rating hanya boleh dikirim oleh pemilik invoice, sekali per invoice.
        RateLimiter::for('rating', function (Request $request): Limit {
            return Limit::perMinute(5)->by($request->user()?->id ?: $request->ip());
        });

        // Topup saldo membuat transaksi + panggilan gateway, jadi dibatasi.
        RateLimiter::for('topup', function (Request $request): Limit {
            return Limit::perMinute(10)->by($request->user()?->id ?: $request->ip());
        });

        // Topup manual mengunggah file: batasi agar disk tidak bisa diisi massal.
        RateLimiter::for('manual-topup', function (Request $request): Limit {
            return Limit::perMinute(5)->by($request->user()?->id ?: $request->ip());
        });
    }

    /**
     * Dipakai test untuk memastikan transaksi hasil checkout tetap terhitung
     * pada limit yang sama dengan request-nya.
     */
    public static function checkoutLimitKey(Transaction $trx): string
    {
        return 'checkout:'.$trx->id;
    }
}
