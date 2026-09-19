<?php

namespace App\Services;

use App\Models\Transaction;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Sumber tunggal untuk laporan keuangan.
 *
 * Profit bersih = harga jual − harga modal − fee gateway yang DITANGGUNG
 * merchant. Fee gateway yang dibebankan ke pelanggan sudah termasuk dalam
 * total tagihan dan diteruskan ke provider, jadi ia tidak mengurangi profit
 * produk — kalau dikurangkan lagi, labanya terhitung dua kali.
 */
class ReportService
{
    /**
     * @return array{count:int, revenue:int, cost:int, gateway_fee:int, profit:int, average:int}
     */
    public function summary(CarbonInterface $from, CarbonInterface $to): array
    {
        $row = $this->baseQuery($from, $to)
            ->selectRaw('COUNT(*) as trx_count')
            ->selectRaw('COALESCE(SUM(total_amount), 0) as revenue')
            ->selectRaw('COALESCE(SUM(cost_price), 0) as cost')
            ->selectRaw('COALESCE(SUM(gateway_fee), 0) as gateway_fee')
            ->selectRaw('COALESCE(SUM(profit), 0) as profit')
            ->first();

        $count = (int) ($row->trx_count ?? 0);
        $revenue = (int) ($row->revenue ?? 0);

        return [
            'count' => $count,
            'revenue' => $revenue,
            'cost' => (int) ($row->cost ?? 0),
            'gateway_fee' => (int) ($row->gateway_fee ?? 0),
            'profit' => (int) ($row->profit ?? 0),
            'average' => $count > 0 ? intdiv($revenue, $count) : 0,
        ];
    }

    /**
     * Breakdown per kolom. $column harus dari daftar putih — nilainya masuk ke
     * SQL mentah (groupBy/selectRaw), jadi tidak boleh berasal dari input user.
     *
     * @return Collection<int, object>
     */
    public function breakdown(string $dimension, CarbonInterface $from, CarbonInterface $to): Collection
    {
        [$expression, $join] = match ($dimension) {
            'product' => ["COALESCE(products.name, 'Topup Saldo')", 'products'],
            'category' => ["COALESCE(products.category, 'topup')", 'products'],
            'gateway' => ['transactions.payment_gateway_code', null],
            'supplier' => ["COALESCE(supplier_configs.name, 'Manual / Tanpa Supplier')", 'supplier_configs'],
            'method' => ['transactions.payment_method', null],
            default => throw new \InvalidArgumentException('Dimensi laporan tidak dikenal: '.$dimension),
        };

        $query = $this->baseQuery($from, $to);

        if ($join === 'products') {
            $query->leftJoin('products', 'products.id', '=', 'transactions.product_id');
        } elseif ($join === 'supplier_configs') {
            $query->leftJoin('supplier_configs', 'supplier_configs.id', '=', 'transactions.supplier_config_id');
        }

        return $query
            ->selectRaw($expression.' as label')
            ->selectRaw('COUNT(*) as trx_count')
            ->selectRaw('COALESCE(SUM(transactions.total_amount), 0) as revenue')
            ->selectRaw('COALESCE(SUM(transactions.cost_price), 0) as cost')
            ->selectRaw('COALESCE(SUM(transactions.profit), 0) as profit')
            ->groupBy('label')
            ->orderByDesc('profit')
            ->get();
    }

    /**
     * @return Collection<int, object>
     */
    public function dailySeries(CarbonInterface $from, CarbonInterface $to): Collection
    {
        return $this->baseQuery($from, $to)
            ->selectRaw('DATE(transactions.created_at) as day')
            ->selectRaw('COUNT(*) as trx_count')
            ->selectRaw('COALESCE(SUM(transactions.total_amount), 0) as revenue')
            ->selectRaw('COALESCE(SUM(transactions.profit), 0) as profit')
            ->groupBy('day')
            ->orderBy('day')
            ->get();
    }

    /**
     * @return array<int, string>
     */
    public static function dimensions(): array
    {
        return [
            'product' => 'Per Produk',
            'category' => 'Per Kategori',
            'gateway' => 'Per Payment Gateway',
            'method' => 'Per Metode Pembayaran',
            'supplier' => 'Per Supplier',
        ];
    }

    /**
     * Hanya transaksi sukses yang dihitung sebagai pendapatan. Transaksi
     * pending/gagal/kedaluwarsa tidak pernah jadi omzet.
     */
    protected function baseQuery(CarbonInterface $from, CarbonInterface $to)
    {
        return Transaction::query()
            ->where('transactions.status', Transaction::STATUS_SUCCESS)
            ->whereBetween('transactions.created_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()]);
    }

    /**
     * Baris siap ekspor (CSV/Excel).
     *
     * @return array{0: array<int, string>, 1: array<int, array<int, string|int>>}
     */
    public function exportRows(string $dimension, CarbonInterface $from, CarbonInterface $to): array
    {
        $rows = $this->breakdown($dimension, $from, $to);

        return [
            ['Label', 'Jumlah Transaksi', 'Omzet', 'Modal', 'Profit Bersih'],
            $rows->map(fn (object $row): array => [
                (string) $row->label,
                (int) $row->trx_count,
                (int) $row->revenue,
                (int) $row->cost,
                (int) $row->profit,
            ])->all(),
        ];
    }

    /**
     * Rekap harian untuk rentang tanggal.
     *
     * @return array{0: array<int, string>, 1: array<int, array<int, string|int>>}
     */
    public function exportDailyRows(CarbonInterface $from, CarbonInterface $to): array
    {
        $rows = $this->dailySeries($from, $to);

        return [
            ['Tanggal', 'Jumlah Transaksi', 'Omzet', 'Profit Bersih'],
            $rows->map(fn (object $row): array => [
                (string) $row->day,
                (int) $row->trx_count,
                (int) $row->revenue,
                (int) $row->profit,
            ])->all(),
        ];
    }
}
