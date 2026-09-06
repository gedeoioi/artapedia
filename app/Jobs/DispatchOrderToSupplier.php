<?php

namespace App\Jobs;

use App\Services\OrderService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class DispatchOrderToSupplier implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $transactionId, public ?int $supplierId = null, public bool $manual = false) {}

    public function handle(OrderService $orders): void
    {
        $orders->dispatchToSupplier($this->transactionId, $this->supplierId, $this->manual);
    }
}
