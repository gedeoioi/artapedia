<!DOCTYPE html>
<html lang="id" data-theme="{{ \App\Models\SiteSetting::theme() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @php
        $siteName = \App\Models\SiteSetting::get('site_name', 'ArtaPedia');
        $siteDesc = \App\Models\SiteSetting::get('site_description');
        $favicon = \App\Models\SiteSetting::faviconUrl();
        $primary = \App\Models\SiteSetting::get('primary_color', '#f59e0b');
        $accent = \App\Models\SiteSetting::get('accent_color', '#1a1a1a');
        $theme = \App\Models\SiteSetting::theme();
    @endphp
    <title>@yield('title', $siteName) - {{ $siteName }}, Topup Game, Pulsa & PPOB</title>
    <meta name="description" content="@yield('meta_description', $siteDesc)">
    @if($favicon)<link rel="icon" href="{{ $favicon }}">@endif
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        :root { --primary: {{ $primary }}; --accent: {{ $accent }}; }
        body { background: #fff; color: #1a1a1a; }
        html[data-theme="dark"] body { background: #111; color: #eee; }
        html[data-theme="dark"] .card { background: #1c1c1c; border-color: #333; color: #eee; }
        html[data-theme="dark"] table.bordered th { background: #222; color: #ccc; }
        html[data-theme="dark"] table.bordered th, html[data-theme="dark"] table.bordered td { border-color: #333; }
        html[data-theme="blue"] body { background: #f0f6ff; }
        html[data-theme="green"] body { background: #f0faf2; }
        .card { border: 1px solid #e5e5e5; border-radius: 12px; background: #fff; }
        .btn-primary { background: var(--primary); color: #fff; border-radius: 12px; }
        a.accent, .accent { color: var(--primary); }
        .badge-ok { background: #e6f6ec; color: #15803d; }
        .badge-pending { background: #fef3c7; color: #92400e; }
        .badge-fail { background: #fee2e2; color: #b91c1c; }
        table.bordered { border-collapse: collapse; width: 100%; }
        table.bordered th, table.bordered td { border-bottom: 1px solid #e5e5e5; padding: 10px 12px; text-align: left; }
        table.bordered th { background: #fafafa; font-size: 12px; text-transform: uppercase; letter-spacing: .04em; color: #555; }
    </style>
    @yield('head')
</head>
<body class="min-h-screen">
<header class="border-b">
    <div class="max-w-5xl mx-auto px-4 py-4 flex items-center gap-4">
        @php $logo = \App\Models\SiteSetting::logoUrl(); @endphp
        <a href="{{ route('home') }}" class="font-bold text-xl flex items-center gap-2">
            @if($logo)<img src="{{ $logo }}" alt="{{ $siteName }}" class="h-8 w-auto rounded">@endif
            <span>{{ $siteName }}</span>
        </a>
        <form action="{{ route('home') }}" class="flex-1 flex gap-2">
            <input name="q" value="{{ request('q') }}" placeholder="Cari game / produk..." class="card flex-1 px-4 py-2">
            <button class="card px-4 py-2">Cari</button>
        </form>
        <a href="{{ route('invoice.index') }}" class="text-sm underline">Cek Invoice</a>
        @auth
            <a href="{{ route('member.dashboard') }}" class="text-sm underline">Member</a>
            <a href="{{ route('dashboard') }}" class="text-sm underline">Akun</a>
        @else
            <a href="{{ route('login') }}" class="text-sm underline">Masuk</a>
        @endauth
    </div>
</header>
<main class="max-w-5xl mx-auto px-4 py-6">
    @if(session('ok'))<div class="card p-3 mb-4 badge-ok">{{ session('ok') }}</div>@endif
    @if($errors->any())<div class="card p-3 mb-4 badge-fail">@foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach</div>@endif
    @yield('content')
</main>
<footer class="border-t mt-10">
    <div class="max-w-5xl mx-auto px-4 py-6 text-sm text-gray-600">{{ $siteName }} &copy; {{ date('Y') }} - {{ \App\Models\SiteSetting::get('footer_text', 'Topup game & PPOB.') }}</div>
</footer>
@yield('scripts')
</body>
</html>
