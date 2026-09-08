<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Callback ini berasal dari server payment gateway/supplier, bukan browser
        // pengguna, sehingga tidak memiliki CSRF token. Keamanan endpoint tetap
        // dijaga oleh signature/token masing-masing provider.
        $middleware->validateCsrfTokens(except: [
            'webhook/*',
        ]);

        // Percayai header X-Forwarded-* dari Nginx agar Laravel tahu
        // request asli HTTPS (penting untuk Livewire + session secure).
        $middleware->trustProxies(at: '*', headers: Request::HEADER_X_FORWARDED_FOR |
            Request::HEADER_X_FORWARDED_HOST |
            Request::HEADER_X_FORWARDED_PORT |
            Request::HEADER_X_FORWARDED_PROTO |
            Request::HEADER_X_FORWARDED_AWS_ELB
        );
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
