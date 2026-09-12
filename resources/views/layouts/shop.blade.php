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
        $primary = \App\Models\SiteSetting::get('primary_color', '#f97316');
        $accent = \App\Models\SiteSetting::get('accent_color', '#1a1a1a');
        $theme = \App\Models\SiteSetting::theme();
    @endphp
    <title>@yield('title', $siteName) - {{ $siteName }}, Topup Game, Pulsa & PPOB</title>
    <meta name="description" content="@yield('meta_description', $siteDesc)">
    <meta name="robots" content="index, follow">
    @if($favicon)<link rel="icon" href="{{ $favicon }}">@endif
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>
        :root { --primary: {{ $primary }}; --accent: {{ $accent }}; }
        body { background: #0f0f12; color: #f5f4f0; font-family: ui-sans-serif, system-ui, sans-serif; }
        html[data-theme="light"] body { background: #fafaf9; color: #1a1a1a; }
        .card { border: 1px solid #26262b; border-radius: 12px; background: #17171c; }
        html[data-theme="light"] .card { border-color: #e5e5e5; background: #fff; }
        .btn-primary {
            background: linear-gradient(to top, #351b08 0%, #c2570c 50%, #f97316 100%);
            color: #fff; border-radius: 12px; font-weight: 700;
        }
        .btn-primary:hover { filter: brightness(1.1); }
        a.accent, .accent { color: #f97316; }
        .badge-ok { background: rgba(34,197,94,.15); color: #4ade80; }
        .badge-pending { background: rgba(250,204,21,.15); color: #facc15; }
        .badge-fail { background: rgba(239,68,68,.15); color: #f87171; }
        html[data-theme="light"] .badge-ok { background: #e6f6ec; color: #15803d; }
        html[data-theme="light"] .badge-pending { background: #fef3c7; color: #92400e; }
        html[data-theme="light"] .badge-fail { background: #fee2e2; color: #b91c1c; }
        table.bordered { border-collapse: collapse; width: 100%; }
        table.bordered th, table.bordered td { border-bottom: 1px solid #26262b; padding: 10px 12px; text-align: left; }
        table.bordered th { background: #1c1c21; font-size: 12px; text-transform: uppercase; letter-spacing: .04em; color: #a1a1aa; }
        html[data-theme="light"] table.bordered th, html[data-theme="light"] table.bordered td { border-color: #e5e5e5; }
        html[data-theme="light"] table.bordered th { background: #fafafa; color: #555; }
        .hero-grad { background: radial-gradient(ellipse 80% 60% at 50% -10%, rgba(249,115,22,.25), transparent); }
        .muted { color: #a1a1aa; }
        html[data-theme="light"] .muted { color: #6b7280; }
        .topbar { background: #17171c; border-bottom: 1px solid #26262b; }
        html[data-theme="light"] .topbar { background: #fff; border-color: #e5e5e5; }
        .navlink {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            padding: 7px 2px;
            font-size: 14px;
            font-weight: 600;
            color: #d4d4d8;
            white-space: nowrap;
            transition: color .2s ease, transform .2s ease;
        }
        .navlink svg { width: 17px; height: 17px; color: #f97316; }
        .navlink:hover { color: #f97316; transform: translateY(-1px); }
        .navlink:focus-visible { outline: 3px solid rgba(249, 115, 22, .3); outline-offset: 3px; border-radius: 6px; }
        html[data-theme="light"] .navlink { color: #374151; }
        .foot { background: #0a0a0c; border-top: 1px solid #26262b; }
        html[data-theme="light"] .foot { background: #f5f5f4; border-color: #e5e5e5; }
        .flash-grad { background: linear-gradient(135deg, #7c2d12, #c2570c 60%, #f97316); }
        .catalog-tabs {
            display: flex;
            gap: 12px;
            overflow-x: auto;
            padding: 2px 0 5px;
            scrollbar-width: none;
        }
        .catalog-tabs::-webkit-scrollbar { display: none; }
        [x-cloak] { display: none !important; }
        .catalog-tabs a, .catalog-tabs button {
            flex: none;
            min-height: 40px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0 18px;
            border: 1px solid transparent;
            border-radius: 999px;
            background: #25252b;
            color: #fff;
            font-size: 14px;
            font-weight: 800;
            transition: background-color .2s ease, border-color .2s ease, transform .2s ease;
        }
        .catalog-tabs a:hover, .catalog-tabs button:hover { border-color: rgba(249, 115, 22, .6); transform: translateY(-1px); }
        .catalog-tabs a:focus-visible, .catalog-tabs button:focus-visible { outline: 3px solid rgba(249, 115, 22, .35); outline-offset: 2px; }
        .catalog-tabs a.is-active, .catalog-tabs button.is-active { background: var(--primary); border-color: var(--primary); }
        .catalog-more-button {
            min-width: 164px;
            min-height: 44px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0 22px;
            border: 2px solid var(--primary);
            border-radius: 999px;
            background: linear-gradient(180deg, #242429 0%, #18181c 100%);
            color: #fff;
            font-size: 13px;
            font-weight: 800;
            box-shadow: inset 0 0 0 2px #101013, 0 7px 18px rgba(0, 0, 0, .24);
            transition: transform .2s ease, border-color .2s ease, box-shadow .2s ease, background-color .2s ease;
        }
        .catalog-more-button:hover {
            transform: translateY(-2px);
            border-color: #fb923c;
            background: linear-gradient(180deg, #2c2c31 0%, #1d1d21 100%);
            box-shadow: inset 0 0 0 2px #101013, 0 10px 24px rgba(249, 115, 22, .18);
        }
        .catalog-more-button:active { transform: translateY(0); }
        .catalog-more-button:focus-visible { outline: 3px solid rgba(249, 115, 22, .3); outline-offset: 3px; }
        .favorite-card, .category-card {
            position: relative;
            display: block;
            overflow: hidden;
            border: 1px solid #29292f;
            background: #19191e;
            color: inherit;
            isolation: isolate;
            transition: transform .25s ease, border-color .25s ease, box-shadow .25s ease;
        }
        .favorite-card {
            display: flex;
            align-items: center;
            min-height: 104px;
            border-radius: 15px;
            background-color: #222228;
            background-image:
                linear-gradient(90deg, rgba(255, 255, 255, .025), transparent 48%),
                radial-gradient(circle, rgba(255, 255, 255, .075) 1px, transparent 1.2px);
            background-size: 100% 100%, 8px 8px;
            box-shadow: 0 10px 24px rgba(0, 0, 0, .16);
        }
        .category-card { border-radius: 14px; }
        .favorite-card:hover, .category-card:hover {
            transform: translateY(-4px);
            border-color: rgba(249, 115, 22, .8);
            box-shadow: 0 18px 42px rgba(0, 0, 0, .3), 0 0 0 1px rgba(249, 115, 22, .12);
        }
        .category-cover { position: relative; overflow: hidden; background: #24242a; }
        .category-cover::after {
            content: '';
            position: absolute;
            inset: auto 0 0;
            height: 35%;
            background: linear-gradient(to top, rgba(10, 10, 12, .42), transparent);
            pointer-events: none;
        }
        .category-cover img { transition: transform .4s ease, filter .4s ease; }
        .favorite-card:hover .category-cover img, .category-card:hover .category-cover img {
            transform: scale(1.045);
            filter: saturate(1.08);
        }
        .favorite-card .category-cover {
            width: 86px;
            height: 86px;
            flex: none;
            margin: 8px;
            border-radius: 11px;
        }
        .favorite-card .category-cover::after { display: none; }
        .line-clamp-2-custom {
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        html[data-theme="light"] .favorite-card,
        html[data-theme="light"] .category-card { border-color: #e7e5e4; background-color: #fff; box-shadow: 0 10px 28px rgba(28, 25, 23, .07); }
        html[data-theme="light"] .catalog-tabs a,
        html[data-theme="light"] .catalog-tabs button { background: #e7e5e4; color: #292524; }
        html[data-theme="light"] .catalog-tabs a.is-active,
        html[data-theme="light"] .catalog-tabs button.is-active { background: var(--primary); color: #fff; }
        html[data-theme="light"] .catalog-more-button {
            background: linear-gradient(180deg, #fff 0%, #f5f5f4 100%);
            color: #292524;
            box-shadow: inset 0 0 0 2px #fff, 0 7px 18px rgba(28, 25, 23, .12);
        }
        html[data-theme="light"] .favorite-card {
            background-image:
                linear-gradient(90deg, rgba(249, 115, 22, .04), transparent 48%),
                radial-gradient(circle, rgba(120, 113, 108, .14) 1px, transparent 1.2px);
        }
        html[data-theme="light"] .category-cover { background: #f5f5f4; }
        @media (prefers-reduced-motion: reduce) {
            .favorite-card, .category-card, .category-cover img, .catalog-tabs a, .catalog-tabs button, .catalog-more-button { transition: none; }
        }
    </style>
    @yield('head')
</head>
<body class="min-h-screen">
<header class="topbar sticky top-0 z-50">
    <div class="max-w-6xl mx-auto px-4 py-3 flex items-center gap-3">
        @php $logo = \App\Models\SiteSetting::logoUrl(); @endphp
        <a href="{{ route('home') }}" class="font-bold text-xl flex items-center gap-2 shrink-0">
            @if($logo)<img src="{{ $logo }}" alt="{{ $siteName }}" class="h-8 w-auto rounded">@endif
            <span>{{ $siteName }}</span>
        </a>
        <form action="{{ route('home') }}" class="hidden md:flex flex-1 max-w-sm gap-2">
            @if(request('type'))<input type="hidden" name="type" value="{{ request('type') }}">@endif
            <input name="q" value="{{ request('q') }}" placeholder="Cari game / voucher..." class="card flex-1 px-4 py-2 text-sm">
        </form>
        <nav class="hidden lg:flex items-center gap-7 ml-3">
            <a href="{{ route('home') }}" class="navlink" aria-label="Topup">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M13 2 4.8 13h6.6L11 22l8.2-11h-6.6L13 2Z"/>
                </svg>
                <span>Topup</span>
            </a>
            <a href="{{ route('invoice.index') }}" class="navlink" aria-label="Riwayat transaksi">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M3 12a9 9 0 1 0 3-6.7"/>
                    <path d="M3 4v5h5M12 7v5l3 2"/>
                </svg>
                <span>Riwayat</span>
            </a>
            @auth
                <a href="{{ route('member.dashboard') }}" class="navlink">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <circle cx="12" cy="8" r="4"/><path d="M4.5 21a7.5 7.5 0 0 1 15 0"/>
                    </svg>
                    <span>Member</span>
                </a>
            @endauth
        </nav>
        <div class="ml-auto flex items-center gap-2">
            <a href="{{ route('invoice.index') }}" class="navlink lg:hidden" aria-label="Riwayat transaksi">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M3 12a9 9 0 1 0 3-6.7"/><path d="M3 4v5h5M12 7v5l3 2"/>
                </svg>
                <span class="hidden sm:inline">Riwayat</span>
            </a>
            @auth
                <a href="{{ route('member.dashboard') }}" class="btn-primary px-4 py-2 text-sm">Member Area</a>
            @else
                <a href="{{ route('login') }}" class="btn-primary px-5 py-2 text-sm">Masuk</a>
            @endauth
        </div>
    </div>
    <div class="md:hidden px-4 pb-3">
        <form action="{{ route('home') }}" class="flex gap-2">
            <input name="q" value="{{ request('q') }}" placeholder="Cari game / voucher..." class="card flex-1 px-4 py-2 text-sm">
            <button class="btn-primary px-4 py-2 text-sm">Cari</button>
        </form>
    </div>
</header>
<main class="max-w-6xl mx-auto px-4 py-6">
    @if(session('ok'))<div class="card p-3 mb-4 badge-ok">{{ session('ok') }}</div>@endif
    @if($errors->any())<div class="card p-3 mb-4 badge-fail">@foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach</div>@endif
    @yield('content')
</main>
<footer class="foot mt-12">
    <div class="max-w-6xl mx-auto px-4 py-10 grid grid-cols-2 md:grid-cols-4 gap-8 text-sm">
        <div>
            <div class="font-bold text-base mb-2">{{ $siteName }}</div>
            <p class="muted text-xs">{{ \App\Models\SiteSetting::get('footer_text', 'Topup game & PPOB.') }}</p>
            <p class="muted text-xs mt-2">{{ \App\Models\SiteSetting::get('contact_email') }} {{ \App\Models\SiteSetting::get('contact_whatsapp') }}</p>
        </div>
        <div data-footer-section="layanan">
            <div class="font-bold mb-2">Layanan</div>
            <div class="flex flex-col gap-1 muted">
                <a href="{{ route('home') }}" class="hover:text-orange-500">Topup Game</a>
                <a href="{{ route('invoice.index') }}" class="hover:text-orange-500">Cek Transaksi</a>
                <a href="{{ route('member.dashboard') }}" class="hover:text-orange-500">Member</a>
            </div>
        </div>
        <div data-footer-section="bantuan">
            <div class="font-bold mb-2">Bantuan</div>
            <div class="flex flex-col gap-1 muted">
                <a href="{{ route('invoice.index') }}" class="hover:text-orange-500">Lacak Invoice</a>
                <a href="{{ route('support.faq') }}" class="hover:text-orange-500">FAQ</a>
                <a href="{{ route('support.contact') }}" class="hover:text-orange-500">Kontak</a>
                <a href="{{ route('legal.refund') }}" class="hover:text-orange-500">Refund Policy</a>
                <a href="{{ route('login') }}" class="hover:text-orange-500">Masuk / Daftar</a>
                <a href="{{ route('legal.terms') }}" class="hover:text-orange-500">Terms &amp; Conditions</a>
                <a href="{{ route('legal.privacy') }}" class="hover:text-orange-500">Privacy Policy</a>
            </div>
        </div>
        <div>
            <div class="font-bold mb-2">Kontak</div>
            <div class="muted text-xs">WhatsApp: {{ \App\Models\SiteSetting::get('contact_whatsapp', '-') }}<br>Email: {{ \App\Models\SiteSetting::get('contact_email', '-') }}</div>
            <a href="{{ route('support.contact') }}" class="inline-flex mt-2 text-xs font-bold accent hover:underline">Hubungi kami</a>
        </div>
    </div>
    <div class="border-t border-neutral-800">
        <div class="max-w-6xl mx-auto px-4 py-4 text-xs muted flex justify-between">
            <span>{{ $siteName }} &copy; {{ date('Y') }}. All rights reserved.</span>
            <span>Proses otomatis 24 jam</span>
        </div>
    </div>
</footer>
@yield('scripts')
</body>
</html>
