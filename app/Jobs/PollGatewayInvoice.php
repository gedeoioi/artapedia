<?php

namespace App\Jobs;

use App\Services\PaymentService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class PollGatewayInvoice implements ShouldQueue
{
    use Queueable;

    public $tries = 3;

    public function __construct(public int $transactionId) {}

    public function handle(PaymentService $payments): void
    {
        $payments->pollGatewayStatus($this->transactionId);
    }
}
