<?php

namespace App\Jobs;

use App\Services\OrderService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class PollTransactionStatus implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $transactionId) {}

    public function handle(OrderService $orders): void
    {
        $orders->pollStatus($this->transactionId);
    }
}
