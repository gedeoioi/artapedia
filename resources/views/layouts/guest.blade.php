<!DOCTYPE html>
<html lang="id">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        @php
            $siteName = \App\Models\SiteSetting::get('site_name', config('app.name', 'ArtaPedia'));
            $primary = \App\Models\SiteSetting::get('primary_color', '#f97316');
            $favicon = null;
            try { $favicon = \App\Models\SiteSetting::faviconUrl(); } catch (\Throwable $e) {}
        @endphp
        <title>{{ $title ?? 'Masuk' }} - {{ $siteName }}</title>
        @if($favicon)<link rel="icon" href="{{ $favicon }}">@endif

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        <style>
            :root { --auth-primary: {{ $primary }}; }
            body {
                min-height: 100vh;
                background:
                    radial-gradient(circle at 50% -10%, rgba(249, 115, 22, .18), transparent 36%),
                    radial-gradient(circle at 8% 90%, rgba(249, 115, 22, .07), transparent 28%),
                    #0f0f12;
                color: #f5f4f0;
            }
            .auth-shell { position: relative; isolation: isolate; }
            .auth-shell::before {
                content: '';
                position: fixed;
                inset: 0;
                z-index: -1;
                pointer-events: none;
                opacity: .17;
                background-image: radial-gradient(rgba(255,255,255,.18) .7px, transparent .7px);
                background-size: 18px 18px;
                mask-image: linear-gradient(to bottom, black, transparent 75%);
            }
            .auth-brand-mark {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                padding: 7px;
                border: 1px solid rgba(249, 115, 22, .2);
                border-radius: 13px;
                background: rgba(255, 255, 255, .96);
                box-shadow: 0 8px 28px rgba(0, 0, 0, .28);
            }
            .auth-brand-name { color: var(--auth-primary); }
            .auth-brand-name:hover { color: #fb923c; }
            .auth-card {
                border: 1px solid #2c2c33;
                border-radius: 18px;
                background: linear-gradient(145deg, rgba(28, 28, 33, .98), rgba(20, 20, 24, .98));
                box-shadow: 0 28px 80px rgba(0, 0, 0, .42), inset 0 1px rgba(255, 255, 255, .025);
            }
            .auth-card label { color: #e7e5e4 !important; }
            .auth-card input[type="email"],
            .auth-card input[type="password"],
            .auth-card input[type="text"] {
                min-height: 46px;
                border: 1px solid #34343b !important;
                border-radius: 11px !important;
                background: #111115 !important;
                color: #fafafa !important;
                box-shadow: inset 0 1px 2px rgba(0, 0, 0, .24) !important;
            }
            .auth-card input::placeholder { color: #71717a; }
            .auth-card input:focus {
                border-color: var(--auth-primary) !important;
                --tw-ring-color: rgba(249, 115, 22, .22) !important;
                box-shadow: 0 0 0 3px rgba(249, 115, 22, .14) !important;
            }
            .auth-card input:-webkit-autofill {
                -webkit-text-fill-color: #fafafa;
                -webkit-box-shadow: 0 0 0 1000px #111115 inset !important;
            }
            .auth-card input[type="checkbox"] {
                border-color: #52525b;
                background-color: #111115;
                color: var(--auth-primary);
            }
            .auth-card button[type="submit"] {
                min-height: 46px;
                border: 1px solid #fb923c;
                border-radius: 11px;
                background: linear-gradient(to top, #7c2d12, #ea580c 55%, #f97316);
                box-shadow: 0 10px 24px rgba(249, 115, 22, .18), inset 0 1px rgba(255, 255, 255, .18);
            }
            .auth-card button[type="submit"]:hover { filter: brightness(1.08); }
            .auth-card .text-gray-500, .auth-card .text-gray-600 { color: #a1a1aa !important; }
            .auth-card .text-gray-700, .auth-card .text-gray-800 { color: #e7e5e4 !important; }
            .auth-link { color: #fb923c; text-underline-offset: 3px; }
            .auth-link:hover { color: #fdba74; }
        </style>
    </head>
    <body class="font-sans text-gray-900 antialiased">
        <div class="auth-shell min-h-screen flex flex-col sm:justify-center items-center px-4 py-8">
            <div class="mb-6 text-center">
                <a href="/" class="inline-flex items-center gap-3 group">
                    @php
                        $logo = null;
                        try { $logo = \App\Models\SiteSetting::logoUrl(); } catch (\Throwable $e) {}
                    @endphp
                    @if($logo)
                        <span class="auth-brand-mark"><img src="{{ $logo }}" alt="{{ $siteName }}" class="h-8 w-auto rounded"></span>
                    @else
                        <span class="auth-brand-mark"><x-application-logo class="w-8 h-8 fill-current text-orange-500" /></span>
                    @endif
                    <span class="auth-brand-name font-extrabold text-2xl tracking-tight transition">{{ $siteName }}</span>
                </a>
                @php
                    $tagline = null;
                    try { $tagline = \App\Models\SiteSetting::get('site_tagline'); } catch (\Throwable $e) {}
                @endphp
                @if($tagline)<div class="text-sm text-zinc-400 mt-2">{{ $tagline }}</div>@endif
            </div>

            <div class="w-full sm:max-w-md auth-card px-6 py-7 sm:px-8 sm:py-8 overflow-hidden">
                {{ $slot }}
            </div>

            <div class="mt-5 text-sm text-zinc-400">
                <a href="/" class="inline-flex items-center gap-2 hover:text-orange-400 transition">
                    <span aria-hidden="true">&larr;</span> Kembali ke beranda
                </a>
            </div>
        </div>
    </body>
</html>
