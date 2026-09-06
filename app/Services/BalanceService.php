<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\BalanceMutation;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class BalanceService
{
    public function adjust(User $user, int $amount, string $type, string $description = '', ?int $transactionId = null, ?int $actorId = null): BalanceMutation
    {
        return DB::transaction(function () use ($user, $amount, $type, $description, $transactionId, $actorId) {
            $locked = User::whereKey($user->id)->lockForUpdate()->firstOrFail();

            if ($locked->status === 'suspended') {
                throw new \RuntimeException('Akun disuspend.');
            }

            $before = (int) $locked->balance;
            $after = $before + $amount;

            if ($after < 0) {
                throw new \RuntimeException('Saldo tidak cukup.');
            }

            $locked->balance = $after;
            $locked->save();

            $mutation = BalanceMutation::create([
                'user_id' => $locked->id,
                'transaction_id' => $transactionId,
                'type' => $type,
                'amount' => $amount,
                'balance_before' => $before,
                'balance_after' => $after,
                'description' => $description,
                'reference' => 'MUT-'.now()->format('YmdHis').'-'.Str::upper(Str::random(8)),
                'created_by' => $actorId ?? auth()->id(),
            ]);

            AuditLog::record('balance.'.$type, $locked, ['balance' => $before], ['balance' => $after], $actorId ?? auth()->id());

            return $mutation;
        });
    }

    public function debit(User $user, int $amount, string $description = '', ?int $transactionId = null): BalanceMutation
    {
        return $this->adjust($user, -abs($amount), BalanceMutation::TYPE_ORDER, $description, $transactionId);
    }

    public function credit(User $user, int $amount, string $type, string $description = '', ?int $transactionId = null): BalanceMutation
    {
        return $this->adjust($user, abs($amount), $type, $description, $transactionId);
    }
}
