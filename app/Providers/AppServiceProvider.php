<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Services\BalanceService;
use App\Services\OrderService;
use App\Services\PaymentService;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(BalanceService::class);
        $this->app->singleton(PaymentService::class);
        $this->app->singleton(OrderService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
