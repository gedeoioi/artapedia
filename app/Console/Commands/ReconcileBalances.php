<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\BalanceMutation;
use App\Models\BalanceReconciliation;
use App\Models\Transaction;
use App\Models\User;
use App\Services\AdminAlertService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ReconcileBalances extends Command
{
    protected $signature = 'balance:reconcile
                            {--fix : Perbaiki users.balance agar sama dengan SUM(ledger)}
                            {--alert : Kirim notifikasi WA ke admin bila ada selisih}';

    protected $description = 'Bandingkan SUM(balance_mutations) vs users.balance dan laporkan selisihnya';

    public function handle(AdminAlertService $alerts): int
    {
        $rows = $this->mismatchedUsers();
        $unbacked = $this->unbackedTransactionCount();
        $repaired = 0;

        foreach ($rows as $row) {
            $action = 'none';

            if ($this->option('fix')) {
                $this->repair($row);
                $action = 'repaired';
                $repaired++;
            }

            BalanceReconciliation::create([
                'user_id' => $row->id,
                'ledger_total' => $row->ledger_total,
                'cached_balance' => $row->cached_balance,
                'difference' => $row->difference,
                'unbacked_transactions' => $unbacked,
                'action' => $action,
                'alerted' => false,
            ]);
        }

        $this->render($rows, $unbacked, $repaired);

        if ($rows->isNotEmpty() && $this->option('alert')) {
            $this->notifyAdmins($alerts, $rows, $unbacked);
        }

        if ($unbacked > 0) {
            $this->newLine();
            $this->warn("{$unbacked} transaksi berstatus final/berjalan TANPA baris ledger. Cache dan ledger sama-sama setuju pada kasus ini, jadi perbandingan saldo tidak melihatnya — periksa manual.");
        }

        // Selisih yang tersisa setelah --fix berarti perbaikannya gagal.
        // Jalankan ulang untuk membuktikan, jangan percaya satu kali jalan.
        $remaining = $rows->isNotEmpty() && ! $this->option('fix');

        return $remaining ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @return \Illuminate\Support\Collection<int, object>
     */
    protected function mismatchedUsers()
    {
        return User::query()
            ->leftJoinSub(
                BalanceMutation::query()
                    ->selectRaw('user_id, SUM(amount) as ledger_total')
                    ->groupBy('user_id'),
                'ledger',
                'ledger.user_id',
                '=',
                'users.id',
            )
            ->selectRaw('users.id as id, users.name as name, users.email as email')
            ->selectRaw('users.balance as cached_balance')
            ->selectRaw('COALESCE(ledger.ledger_total, 0) as ledger_total')
            ->get()
            ->map(function (User $row): object {
                $ledger = (int) $row->getAttribute('ledger_total');
                $cached = (int) $row->getAttribute('cached_balance');

                return (object) [
                    'id' => (int) $row->id,
                    'name' => (string) $row->name,
                    'email' => (string) $row->email,
                    'ledger_total' => $ledger,
                    'cached_balance' => $cached,
                    'difference' => $cached - $ledger,
                ];
            })
            ->filter(fn (object $row): bool => $row->difference !== 0)
            ->values();
    }

    protected function unbackedTransactionCount(): int
    {
        return Transaction::query()
            ->whereIn('status', [
                Transaction::STATUS_PAID,
                Transaction::STATUS_PROCESSING,
                Transaction::STATUS_SUCCESS,
            ])
            ->whereNotExists(function ($query): void {
                $query->select(DB::raw(1))
                    ->from('balance_mutations')
                    ->whereColumn('balance_mutations.transaction_id', 'transactions.id');
            })
            ->count();
    }

    /**
     * Ledger adalah sumber kebenaran, users.balance hanya cache. Karena itu
     * perbaikannya adalah menyamakan cache dengan ledger — BUKAN menambah baris
     * ledger sebesar selisihnya, karena itu akan menggeser SUM(ledger) dan cache
     * dengan jumlah yang sama sehingga selisihnya tetap utuh.
     *
     * Baris penyeimbang ditulis dengan amount = 0 dan sebelum/sesudah yang
     * sebenarnya, supaya invarian "setiap perubahan saldo punya baris ledger"
     * tetap berlaku tanpa mengubah SUM(ledger).
     */
    protected function repair(object $row): void
    {
        DB::transaction(function () use ($row): void {
            $locked = User::whereKey($row->id)->lockForUpdate()->firstOrFail();
            $before = (int) $locked->balance;
            $after = $row->ledger_total;

            if ($before === $after) {
                return;
            }

            $locked->balance = $after;
            $locked->save();

            BalanceMutation::create([
                'user_id' => $locked->id,
                'transaction_id' => null,
                'type' => BalanceMutation::TYPE_ADJUST,
                'amount' => 0,
                'balance_before' => $before,
                'balance_after' => $after,
                'description' => 'Koreksi rekonsiliasi: cache disamakan dengan ledger',
                'reference' => 'REC-'.now()->format('YmdHis').'-'.Str::upper(Str::random(6)),
                'created_by' => null,
            ]);

            AuditLog::record(
                'balance.reconcile_fix',
                $locked,
                ['balance' => $before],
                ['balance' => $after, 'ledger_total' => $row->ledger_total],
            );
        });
    }

    protected function render($rows, int $unbacked, int $repaired): void
    {
        if ($rows->isEmpty()) {
            $this->info('OK: SUM(ledger) == users.balance untuk semua user.');
        } else {
            $this->error("Ditemukan {$rows->count()} user dengan selisih saldo:");
            $this->table(
                ['ID', 'Nama', 'Ledger', 'Cache', 'Selisih'],
                $rows->map(fn (object $row): array => [
                    $row->id,
                    $row->name,
                    number_format($row->ledger_total, 0, ',', '.'),
                    number_format($row->cached_balance, 0, ',', '.'),
                    number_format($row->difference, 0, ',', '.'),
                ])->all(),
            );
        }

        if ($repaired > 0) {
            $this->info("{$repaired} user diperbaiki (cache disamakan dengan ledger).");
            $this->comment('Jalankan ulang `php artisan balance:reconcile` untuk membuktikan selisihnya hilang.');
        }

        if ($unbacked > 0) {
            $this->line("Transaksi tanpa baris ledger: {$unbacked}");
        }
    }

    /**
     * Nama method tidak boleh `alert` — Symfony Command sudah punya method
     * publik dengan nama itu, dan menurunkannya sebagai protected adalah fatal
     * error saat class dimuat (command-nya tidak akan pernah terdaftar).
     */
    protected function notifyAdmins(AdminAlertService $alerts, $rows, int $unbacked): void
    {
        $lines = [
            '*REKONSILIASI SALDO ARTAPEDIA*',
            'Ditemukan '.$rows->count().' user dengan selisih.',
        ];

        foreach ($rows->take(10) as $row) {
            $lines[] = '- '.$row->name.' (ID '.$row->id.'): ledger '.number_format($row->ledger_total, 0, ',', '.')
                .' vs cache '.number_format($row->cached_balance, 0, ',', '.')
                .' (selisih '.number_format($row->difference, 0, ',', '.').')';
        }

        if ($unbacked > 0) {
            $lines[] = 'Transaksi tanpa baris ledger: '.$unbacked;
        }

        $sent = $alerts->send(implode("\n", $lines), 'admin_alert');

        BalanceReconciliation::whereIn('user_id', $rows->pluck('id'))
            ->whereDate('created_at', now()->toDateString())
            ->update(['alerted' => $sent]);

        if (! $sent) {
            $this->warn('Notifikasi WA tidak terkirim (channel admin_alert belum dikonfigurasi / gagal).');
        }
    }
}
