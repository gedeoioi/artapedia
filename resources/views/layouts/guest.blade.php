<!DOCTYPE html>
<html lang="id">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        @php
            $siteName = \App\Models\SiteSetting::get('site_name', config('app.name', 'ArtaPedia'));
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
            body { background: #fff; }
            .auth-card { border: 1px solid #e5e5e5; border-radius: 12px; background: #fff; }
        </style>
    </head>
    <body class="font-sans text-gray-900 antialiased">
        <div class="min-h-screen flex flex-col sm:justify-center items-center pt-6 sm:pt-0 px-4">
            <div class="mb-5 text-center">
                <a href="/" class="inline-flex items-center gap-2">
                    @php
                        $logo = null;
                        try { $logo = \App\Models\SiteSetting::logoUrl(); } catch (\Throwable $e) {}
                    @endphp
                    @if($logo)
                        <img src="{{ $logo }}" alt="{{ $siteName }}" class="h-10 w-auto rounded">
                    @else
                        <x-application-logo class="w-12 h-12 fill-current text-gray-700" />
                    @endif
                    <span class="font-bold text-2xl">{{ $siteName }}</span>
                </a>
                @php
                    $tagline = null;
                    try { $tagline = \App\Models\SiteSetting::get('site_tagline'); } catch (\Throwable $e) {}
                @endphp
                @if($tagline)<div class="text-sm text-gray-500 mt-1">{{ $tagline }}</div>@endif
            </div>

            <div class="w-full sm:max-w-md auth-card px-6 py-6 overflow-hidden">
                {{ $slot }}
            </div>

            <div class="mt-4 text-sm text-gray-500">
                <a href="/" class="underline hover:text-gray-800">&larr; Kembali ke beranda</a>
            </div>
        </div>
    </body>
</html>
