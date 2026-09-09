<!DOCTYPE html>
<html lang="id" data-theme="{{ \App\Models\SiteSetting::theme() }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        @php
            $siteName = \App\Models\SiteSetting::get('site_name', config('app.name', 'ArtaPedia'));
            $primary = \App\Models\SiteSetting::get('primary_color', '#f97316');
            $favicon = \App\Models\SiteSetting::faviconUrl();
        @endphp
        <title>{{ $siteName }}</title>
        @if($favicon)<link rel="icon" href="{{ $favicon }}">@endif

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700,800&display=swap" rel="stylesheet" />
        @vite(['resources/css/app.css', 'resources/js/app.js'])

        <style>
            :root { --member-primary: {{ $primary }}; }
            body { margin: 0; background: #0d0d10; color: #f5f5f4; }
            .member-shell {
                min-height: 100vh;
                background:
                    radial-gradient(circle at 80% -15%, rgba(249, 115, 22, .12), transparent 30%),
                    radial-gradient(circle at 0 80%, rgba(249, 115, 22, .06), transparent 28%),
                    #0d0d10;
            }
            .member-topbar {
                position: sticky;
                top: 0;
                z-index: 40;
                border-bottom: 1px solid #29292f;
                background: rgba(22, 22, 26, .94);
                backdrop-filter: blur(14px);
            }
            .member-logo-mark {
                display: inline-flex;
                width: 38px;
                height: 38px;
                align-items: center;
                justify-content: center;
                overflow: hidden;
                border: 1px solid rgba(249, 115, 22, .22);
                border-radius: 11px;
                background: #fff;
                box-shadow: 0 7px 20px rgba(0, 0, 0, .24);
            }
            .member-brand { color: #f5f5f4; }
            .member-nav-button {
                display: inline-flex;
                align-items: center;
                gap: .45rem;
                border: 1px solid transparent;
                border-radius: 10px;
                padding: .62rem .9rem;
                color: #d4d4d8;
                font-size: .875rem;
                font-weight: 700;
                transition: .18s ease;
            }
            .member-nav-button:hover { color: #fff; background: #29292f; }
            .member-nav-button.is-active {
                border-color: #fb923c;
                color: #fff;
                background: linear-gradient(to top, #8a3608, var(--member-primary));
                box-shadow: 0 8px 20px rgba(249, 115, 22, .18), inset 0 1px rgba(255, 255, 255, .18);
            }
            .member-heading { border-bottom: 1px solid #26262c; }
            .member-panel {
                border: 1px solid #2d2d34;
                border-radius: 16px;
                background: linear-gradient(145deg, rgba(27, 27, 32, .98), rgba(20, 20, 24, .98));
                box-shadow: 0 18px 55px rgba(0, 0, 0, .25), inset 0 1px rgba(255, 255, 255, .025);
            }
            .member-shell .member-panel .text-gray-900,
            .member-shell .member-panel .text-gray-800,
            .member-shell .member-panel label { color: #f5f5f4 !important; }
            .member-shell .member-panel .text-gray-600,
            .member-shell .member-panel .text-gray-500 { color: #a1a1aa !important; }
            .member-shell .member-panel input {
                min-height: 46px;
                border: 1px solid #393941 !important;
                border-radius: 11px !important;
                background: #101014 !important;
                color: #fafafa !important;
                box-shadow: inset 0 1px 2px rgba(0, 0, 0, .28) !important;
            }
            .member-shell .member-panel input::placeholder { color: #71717a; }
            .member-shell .member-panel input:focus {
                border-color: var(--member-primary) !important;
                --tw-ring-color: rgba(249, 115, 22, .2) !important;
                box-shadow: 0 0 0 3px rgba(249, 115, 22, .12) !important;
            }
            .member-shell .member-panel .bg-gray-800 {
                border: 1px solid #fb923c !important;
                border-radius: 10px !important;
                background: linear-gradient(to top, #8a3608, var(--member-primary)) !important;
                box-shadow: 0 8px 20px rgba(249, 115, 22, .16);
            }
            .member-shell .member-panel .bg-gray-800:hover { filter: brightness(1.08); }
            .member-dropdown {
                overflow: hidden;
                border: 1px solid #34343b;
                background: #1b1b20 !important;
                box-shadow: 0 18px 45px rgba(0, 0, 0, .4);
            }
            .member-dropdown a { color: #e4e4e7 !important; }
            .member-dropdown a:hover, .member-dropdown a:focus { background: #29292f !important; }
            .member-modal { background: #1b1b20 !important; color: #f5f5f4; }
            .member-modal .text-gray-900, .member-modal .text-gray-800, .member-modal label { color: #f5f5f4 !important; }
            .member-modal .text-gray-600, .member-modal .text-gray-500 { color: #a1a1aa !important; }
            .member-modal input { border-color: #393941 !important; background: #101014 !important; color: #fafafa !important; }
            .member-modal .bg-white { border-color: #52525b !important; background: #27272a !important; color: #f5f5f4 !important; }
            html[data-theme="light"] body, html[data-theme="light"] .member-shell { background: #f5f5f4; color: #1c1917; }
            html[data-theme="light"] .member-topbar { border-color: #e7e5e4; background: rgba(255, 255, 255, .95); }
            html[data-theme="light"] .member-brand { color: #1c1917; }
            html[data-theme="light"] .member-nav-button { color: #57534e; }
            html[data-theme="light"] .member-nav-button:hover { background: #f5f5f4; color: #1c1917; }
            html[data-theme="light"] .member-heading { border-color: #e7e5e4; }
            html[data-theme="light"] .member-panel { border-color: #e7e5e4; background: #fff; box-shadow: 0 15px 40px rgba(28,25,23,.07); }
            html[data-theme="light"] .member-shell .member-panel .text-gray-900,
            html[data-theme="light"] .member-shell .member-panel .text-gray-800,
            html[data-theme="light"] .member-shell .member-panel label { color: #1c1917 !important; }
            html[data-theme="light"] .member-shell .member-panel .text-gray-600,
            html[data-theme="light"] .member-shell .member-panel .text-gray-500 { color: #78716c !important; }
            html[data-theme="light"] .member-shell .member-panel input { border-color: #d6d3d1 !important; background: #fff !important; color: #1c1917 !important; }
            html[data-theme="light"] .member-modal { background: #fff !important; color: #1c1917; }
            html[data-theme="light"] .member-modal .text-gray-900,
            html[data-theme="light"] .member-modal .text-gray-800,
            html[data-theme="light"] .member-modal label { color: #1c1917 !important; }
        </style>
    </head>
    <body class="font-sans antialiased">
        <div class="member-shell">
            @include('layouts.navigation')

            @isset($header)
                <header class="member-heading">
                    <div class="max-w-5xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
                        {{ $header }}
                    </div>
                </header>
            @endisset

            <main>{{ $slot }}</main>
        </div>
    </body>
</html>
