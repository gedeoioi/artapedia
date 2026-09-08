<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\SupplierConfig;

class SupplierConnectionTester
{
    /** Key kredensial yang wajib disamarkan di log. */
    protected const SECRET_KEYS = ['api_key', 'apikey', 'secret', 'webhook_secret', 'password', 'token', 'private_key'];

    protected function requiredKeys(string $code): array
    {
        return match ($code) {
            'toko-voucher' => ['member_code', 'secret'],
            'digiflazz' => ['username', 'api_key'],
            default => ['api_id', 'api_key'],
        };
    }

    public function test(SupplierConfig $config): array
    {
        $started = microtime(true);
        $steps = [];
        $lines = [];
        $lines[] = '['.now()->toDateTimeString().'] Tes koneksi ke '.$config->name.' ('.$config->code.')';
        $lines[] = 'Mode: '.($config->is_sandbox ? 'SANDBOX/testing' : 'PRODUCTION').' | Provider: '.$config->provider_class;

        // Deteksi decrypt gagal / kredensial korup: cast encrypted:array melempar
        // DecryptException -> tangkap di sini dengan pesan jelas (tanpa bocorkan isi).
        try {
            $credentials = $config->fresh()->credentials ?? [];
            if (! is_array($credentials)) {
                $credentials = [];
            }
        } catch (\Throwable $e) {
            $lines[] = '[GAGAL] Kredensial tidak bisa dibaca (key enkripsi berubah?): '.$e->getMessage();
            $msg = 'Kredensial rusak (gagal decrypt — APP_KEY berubah?). Isi ulang kredensial di form lalu simpan.';

            return $this->fail($config, $msg, $steps, $lines, $started);
        }

        $required = $this->requiredKeys($config->code);
        $missing = array_filter($required, fn ($k) => trim((string) ($credentials[$k] ?? '')) === '');
        if (! empty($missing)) {
            $lines[] = '[GAGAL] Kredensial kosong: '.implode(', ', $missing);
            $msg = 'Kredensial belum lengkap ('.implode(', ', $missing).'). Isi di form supplier lalu simpan.';

            return $this->fail($config, $msg, $steps, $lines, $started);
        }
        $lines[] = '[OK] Kredensial terisi: '.implode(', ', array_map(
            fn ($k) => $k.'='.mb_substr(trim((string) $credentials[$k]), 0, 3).'***('.mb_strlen(trim((string) $credentials[$k])).' char)',
            $required
        ));

        try {
            $provider = ProviderFactory::supplierFor($config->fresh());
            $lines[] = '[OK] Provider dibuat: '.get_class($provider);
        } catch (\Throwable $e) {
            $lines[] = '[GAGAL] Provider: '.$e->getMessage();

            return $this->fail($config, 'Class provider tidak bisa dibuat: '.$e->getMessage(), $steps, $lines, $started);
        }

        $lines[] = '[...] Langkah 1/2: cek saldo...';
        try {
            $balance = $provider->getBalance();
        } catch (\Throwable $e) {
            $lines[] = '[GAGAL] HTTP error saat cek saldo: '.$e->getMessage();

            return $this->fail($config, 'HTTP error saat cek saldo: '.$e->getMessage(), $steps, $lines, $started);
        }

        $masked = $this->maskSecrets($balance);
        $steps['balance_raw'] = $this->summarize($masked);
        $lines[] = '[RESP] cek saldo: '.json_encode($this->summarize($masked), JSON_UNESCAPED_SLASHES);
        $balanceOk = (bool) ($balance['result'] ?? false);

        if (! $balanceOk) {
            $msg = 'Kredensial ditolak / API error: '.($balance['message'] ?? json_encode($this->summarize($masked)));
            $lines[] = '[GAGAL] '.$msg;

            return $this->fail($config, $msg, $steps, $lines, $started);
        }

        $balanceValue = $balance['data']['balance'] ?? $balance['data']['deposit'] ?? $balance['balance'] ?? null;
        $lines[] = '[OK] Saldo: '.($balanceValue ?? '?');
        $lines[] = '[...] Langkah 2/2: ambil daftar produk...';

        try {
            $products = $provider->getProducts(['status' => 'available']);
        } catch (\Throwable $e) {
            $lines[] = '[GAGAL] HTTP error saat ambil produk: '.$e->getMessage();

            return $this->fail($config, 'Saldo OK tapi gagal ambil produk: '.$e->getMessage(), $steps, $lines, $started);
        }

        $rows = $products['data'] ?? $products['services'] ?? [];
        $steps['product_count'] = is_array($rows) ? count($rows) : 0;
        $productsOk = (bool) ($products['result'] ?? false);
        $lines[] = '[RESP] daftar produk: ok='.($productsOk ? 'ya' : 'tidak').', jumlah='.($steps['product_count']);

        if (! $productsOk) {
            if ($config->code === 'digiflazz' && $this->isPricelistRateLimited($products)) {
                $ms = (int) round((microtime(true) - $started) * 1000);

                if (is_numeric($balanceValue)) {
                    $config->cached_balance = (int) $balanceValue;
                }

                $summary = 'Koneksi Digiflazz berhasil. Saldo: '.($balanceValue ?? '?')
                    .'. Pricelist sedang dibatasi (maksimal 1x per 5 menit); coba sinkronisasi lagi nanti.';
                $lines[] = '[PERINGATAN] '.$summary;
                $this->saveLog($config, true, $summary, $lines);

                AuditLog::record('supplier.test_connection', $config, [], [
                    'ok' => true,
                    'warning' => 'pricelist_rate_limited',
                    'latency_ms' => $ms,
                ]);

                return [
                    'ok' => true,
                    'warning' => true,
                    'latency_ms' => $ms,
                    'message' => $summary,
                    'steps' => $steps,
                    'log' => implode("\n", $lines),
                ];
            }

            $msg = 'Saldo OK tapi daftar produk error: '.($products['message'] ?? 'unknown');
            $lines[] = '[GAGAL] '.$msg;

            return $this->fail($config, $msg, $steps, $lines, $started);
        }

        $ms = (int) round((microtime(true) - $started) * 1000);

        if (is_numeric($balanceValue)) {
            $config->cached_balance = (int) $balanceValue;
        }

        $summary = "Koneksi OK ({$ms} ms). Saldo: ".($balanceValue ?? '?').", produk: {$steps['product_count']}.";
        $lines[] = '[OK] '.$summary;

        $this->saveLog($config, true, $summary, $lines);

        AuditLog::record('supplier.test_connection', $config, [], [
            'ok' => true, 'latency_ms' => $ms, 'product_count' => $steps['product_count'],
        ]);

        return [
            'ok' => true,
            'latency_ms' => $ms,
            'message' => $summary,
            'steps' => $steps,
            'log' => implode("\n", $lines),
        ];
    }

    protected function isPricelistRateLimited(array $products): bool
    {
        $rc = (string) ($products['rc'] ?? data_get($products, 'raw.data.rc', ''));
        $message = strtolower((string) ($products['message'] ?? ''));

        return $rc === '83'
            || str_contains($message, 'limitasi pengecekan pricelist')
            || str_contains($message, 'rate limit');
    }

    protected function fail(SupplierConfig $config, string $message, array $steps, array $lines, float $started): array
    {
        $ms = (int) round((microtime(true) - $started) * 1000);

        $this->saveLog($config, false, $message, $lines);

        AuditLog::record('supplier.test_connection', $config, [], ['ok' => false, 'message' => $message]);

        return ['ok' => false, 'latency_ms' => $ms, 'message' => $message, 'steps' => $steps, 'log' => implode("\n", $lines)];
    }

    protected function saveLog(SupplierConfig $config, bool $ok, string $summary, array $lines): void
    {
        $log = implode("\n", $lines);
        if (mb_strlen($log) > 8000) {
            $log = mb_substr($log, 0, 8000)."\n... (dipotong)";
        }

        $config->forceFill([
            'last_test_at' => now(),
            'last_test_ok' => $ok,
            'last_test_summary' => mb_substr($summary, 0, 255),
            'last_test_log' => $log,
        ])->save();
    }

    protected function maskSecrets(mixed $data): mixed
    {
        if (! is_array($data)) {
            return $data;
        }

        $out = [];
        foreach ($data as $k => $v) {
            if (is_array($v)) {
                $out[$k] = $this->maskSecrets($v);

                continue;
            }
            $lower = strtolower((string) $k);
            foreach (self::SECRET_KEYS as $secret) {
                if (str_contains($lower, $secret) && is_string($v) && $v !== '') {
                    $v = mb_substr($v, 0, 3).'***';
                    break;
                }
            }
            $out[$k] = $v;
        }

        return $out;
    }

    protected function summarize(mixed $data): mixed
    {
        if (! is_array($data)) {
            return $data;
        }

        $out = [];
        foreach ($data as $k => $v) {
            $out[$k] = is_array($v) ? ('['.count($v).' item]') : (is_string($v) ? mb_substr($v, 0, 120) : $v);
        }

        return $out;
    }
}
