<?php

namespace App\Services;

use App\Models\BalanceMutation;
use App\Models\ManualTopup;
use App\Models\SiteSetting;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ManualTopupService
{
    public function __construct(protected BalanceService $balances) {}

    /**
     * Bank tujuan transfer, diatur admin lewat SiteSetting.
     *
     * @return array<int, array<string, string>>
     */
    public function banks(): array
    {
        $raw = trim((string) SiteSetting::get('manual_topup_banks', ''));

        if ($raw === '') {
            return [];
        }

        $decoded = json_decode($raw, true);
        if (! is_array($decoded)) {
            return [];
        }

        return collect($decoded)
            ->filter(fn ($bank): bool => is_array($bank) && filled($bank['name'] ?? null))
            ->map(fn (array $bank): array => [
                'name' => (string) $bank['name'],
                'account_name' => (string) ($bank['account_name'] ?? ''),
                'account_number' => (string) ($bank['account_number'] ?? ''),
            ])
            ->values()
            ->all();
    }

    public function minimumAmount(): int
    {
        return max(1, (int) SiteSetting::get('manual_topup_min', 10000));
    }

    public function isEnabled(): bool
    {
        return (string) SiteSetting::get('manual_topup_enabled', '0') === '1';
    }

    public function submit(User $user, int $amount, string $bankName, ?string $senderName, UploadedFile $proof): ManualTopup
    {
        if (! $this->isEnabled()) {
            throw new \RuntimeException('Topup manual sedang tidak tersedia. Silakan gunakan topup otomatis.');
        }

        if ($amount < $this->minimumAmount()) {
            throw new \RuntimeException('Minimum topup manual adalah Rp '.number_format($this->minimumAmount(), 0, ',', '.').'.');
        }

        $bank = collect($this->banks())->firstWhere('name', $bankName);
        if (! $bank) {
            throw new \RuntimeException('Bank tujuan tidak dikenal. Muat ulang halaman lalu pilih kembali.');
        }

        $path = $proof->store('manual-topup', 'public');

        return ManualTopup::create([
            'code' => 'MT-'.now()->format('Ymd').'-'.Str::upper(Str::random(6)),
            'user_id' => $user->id,
            'amount' => $amount,
            'bank_name' => $bank['name'],
            'bank_account_name' => $bank['account_name'],
            'bank_account_number' => $bank['account_number'],
            'sender_name' => $senderName,
            'proof_path' => $path,
            'status' => ManualTopup::STATUS_PENDING,
        ]);
    }

    /**
     * Approve topup manual. Idempotensinya dipegang oleh transisi status di
     * dalam transaksi yang sama dengan penguncian baris: approve kedua tidak
     * bisa mengkredit saldo dua kali karena barisnya sudah tidak `pending`.
     */
    public function approve(ManualTopup $topup, User $admin, ?string $note = null): ManualTopup
    {
        return DB::transaction(function () use ($topup, $admin, $note): ManualTopup {
            $locked = ManualTopup::whereKey($topup->id)->lockForUpdate()->firstOrFail();

            if (! $locked->isPending()) {
                throw new \RuntimeException('Topup ini sudah direview sebelumnya ('.$locked->statusLabel().').');
            }

            $user = User::whereKey($locked->user_id)->lockForUpdate()->firstOrFail();

            // Transaksi bayangan ini yang menjadi penaut entri ledger, supaya
            // rekonsiliasi (SUM(ledger) vs users.balance) tetap seimbang dan
            // saldo tidak pernah bertambah tanpa jejak transaksi.
            $trx = Transaction::create([
                'invoice_code' => 'INV-MT-'.$locked->code,
                'user_id' => $user->id,
                'product_id' => null,
                'supplier_config_id' => null,
                'payment_gateway_code' => 'manual',
                'target_user_id' => 'TOPUP-MANUAL',
                'quantity' => 1,
                'cost_price' => 0,
                'sell_price' => $locked->amount,
                'admin_fee' => 0,
                'gateway_fee' => 0,
                'total_amount' => $locked->amount,
                'profit' => 0,
                'payment_method' => 'manual',
                'status' => Transaction::STATUS_SUCCESS,
                'paid_at' => now(),
                'buyer_phone' => $user->whatsapp ?: $user->phone,
                'buyer_email' => $user->email,
                'notes' => 'Topup manual '.$locked->code.' disetujui '.$admin->name,
                'meta' => ['kind' => 'topup', 'manual_topup_id' => $locked->id],
            ]);

            $this->balances->credit(
                $user,
                $locked->amount,
                BalanceMutation::TYPE_TOPUP,
                'Topup manual '.$locked->code.' disetujui '.$admin->name,
                $trx->id,
                $admin->id,
            );

            $locked->forceFill([
                'status' => ManualTopup::STATUS_APPROVED,
                'review_note' => $note,
                'reviewed_by' => $admin->id,
                'reviewed_at' => now(),
                'transaction_id' => $trx->id,
            ])->save();

            return $locked->fresh();
        });
    }

    public function reject(ManualTopup $topup, User $admin, ?string $note = null): ManualTopup
    {
        return DB::transaction(function () use ($topup, $admin, $note): ManualTopup {
            $locked = ManualTopup::whereKey($topup->id)->lockForUpdate()->firstOrFail();

            if (! $locked->isPending()) {
                throw new \RuntimeException('Topup ini sudah direview sebelumnya ('.$locked->statusLabel().').');
            }

            $locked->forceFill([
                'status' => ManualTopup::STATUS_REJECTED,
                'review_note' => $note,
                'reviewed_by' => $admin->id,
                'reviewed_at' => now(),
            ])->save();

            return $locked->fresh();
        });
    }
}
