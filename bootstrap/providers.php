<?php

use App\Providers\AppServiceProvider;
use App\Providers\Filament\AdminPanelProvider;
use App\Providers\HorizonServiceProvider;
use App\Providers\RateLimitServiceProvider;

return [
    AppServiceProvider::class,
    RateLimitServiceProvider::class,
    AdminPanelProvider::class,
    HorizonServiceProvider::class,
];
