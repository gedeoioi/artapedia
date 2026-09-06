<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'ArtaPedia') - Topup Game, Pulsa & PPOB</title>
    <meta name="description" content="@yield('meta_description', 'ArtaPedia: topup game, pulsa, dan PPOB cepat, aman, harga reseller.')">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body { background: #fff; color: #1a1a1a; }
        .card { border: 1px solid #e5e5e5; border-radius: 12px; background: #fff; }
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
        <a href="{{ route('home') }}" class="font-bold text-xl">ArtaPedia</a>
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
    <div class="max-w-5xl mx-auto px-4 py-6 text-sm text-gray-600">ArtaPedia &copy; {{ date('Y') }} - Topup game & PPOB.</div>
</footer>
@yield('scripts')
</body>
</html>
