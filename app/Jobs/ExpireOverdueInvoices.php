<?php

namespace App\Jobs;

use App\Services\PaymentService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ExpireOverdueInvoices implements ShouldQueue
{
    use Queueable;

    public function handle(PaymentService $payments): void
    {
        $payments->expireOverdueInvoices();
    }
}
