@extends('layouts.shop')

@section('title', 'Topup Game & Pulsa Murah')

@section('content')
@php
    $siteName = \App\Models\SiteSetting::get('site_name', 'ArtaPedia');
    $tagline = \App\Models\SiteSetting::get('site_tagline', 'Topup game, pulsa & PPOB');
@endphp

<!-- HERO -->
<div class="card p-6 md:p-10 mb-8 overflow-hidden relative">
    <div class="max-w-xl">
        <div class="inline-block text-xs font-semibold px-3 py-1 mb-3 badge-ok" style="border-radius: 999px;">
            Proses otomatis &bull; {{ $totalProducts }} produk &bull; {{ $totalGames }} game
        </div>
        <h1 class="text-2xl md:text-4xl font-bold mb-2 leading-tight">Topup game favoritmu<br>dalam hitungan detik.</h1>
        <p class="text-gray-600 text-sm md:text-base mb-5">{{ $tagline }}. Bayar via QRIS / VA / e-wallet atau saldo member, pesanan diteruskan otomatis ke supplier.</p>
        <form action="{{ route('home') }}" class="flex gap-2 max-w-md">
            <input name="q" value="{{ $q }}" placeholder="Cari Mobile Legends, Free Fire, ..." class="card flex-1 px-4 py-3 text-sm" autofocus>
            <button class="btn-primary px-6 py-3 text-sm font-bold">Cari</button>
        </form>
        @if($gateways->isNotEmpty())
        <div class="flex flex-wrap gap-2 mt-4">
            @foreach($gateways as $gw)
                <span class="text-xs px-3 py-1 card text-gray-600">{{ $gw->name }}</span>
            @endforeach
            <span class="text-xs px-3 py-1 card text-gray-600">Saldo member</span>
        </div>
        @endif
    </div>
</div>

<!-- CARA ORDER -->
<div class="grid grid-cols-1 md:grid-cols-3 gap-3 mb-8">
    <div class="card p-4 flex gap-3 items-start">
        <div class="text-2xl font-bold accent">1</div>
        <div><div class="font-semibold text-sm">Pilih produk</div><div class="text-xs text-gray-500">Cari game, pilih nominal, isi User ID tujuan.</div></div>
    </div>
    <div class="card p-4 flex gap-3 items-start">
        <div class="text-2xl font-bold accent">2</div>
        <div><div class="font-semibold text-sm">Bayar</div><div class="text-xs text-gray-500">QRIS / VA / e-wallet atau potong saldo member.</div></div>
    </div>
    <div class="card p-4 flex gap-3 items-start">
        <div class="text-2xl font-bold accent">3</div>
        <div><div class="font-semibold text-sm">Otomatis masuk</div><div class="text-xs text-gray-500">Diproses ke supplier, pantau via Cek Invoice.</div></div>
    </div>
</div>

<!-- KATEGORI -->
<div class="flex items-center justify-between mb-3">
    <h2 class="font-bold text-lg">Kategori populer</h2>
    @if($q)<a href="{{ route('home') }}" class="text-xs underline">Reset pencarian</a>@endif
</div>
<div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-8">
    @forelse($games as $g)
        @php $catIcon = ($icons[$g->game] ?? null)?->iconUrl(); @endphp
        <a href="{{ route('game.show', $g->game) }}" class="card p-4 hover:shadow-sm transition group">
            <div class="flex items-center gap-3">
                @if($catIcon)
                    <img src="{{ $catIcon }}" class="w-12 h-12" style="border-radius:12px" alt="{{ $g->game }}">
                @else
                    <div class="w-12 h-12 flex items-center justify-center font-bold text-lg btn-primary">{{ mb_substr($g->game, 0, 1) }}</div>
                @endif
                <div class="min-w-0">
                    <div class="font-semibold text-sm truncate">{{ $g->game }}</div>
                    <div class="text-xs text-gray-500">{{ $g->total }} produk</div>
                </div>
            </div>
            <div class="flex items-center justify-between mt-3">
                <div class="text-xs text-gray-500">Mulai dari</div>
                <div class="text-sm font-bold">Rp {{ number_format($g->min_price, 0, ',', '.') }}</div>
            </div>
        </a>
    @empty
        <div class="card p-6 text-sm text-gray-500 col-span-full text-center">
            <div class="font-semibold mb-1">Belum ada produk</div>
            <div>Jalankan sync dari supplier di admin, atau ubah kata kunci pencarian.</div>
        </div>
    @endforelse
</div>

<!-- PRODUK TERBARU -->
@if($popular->isNotEmpty())
<div class="flex items-center justify-between mb-3">
    <h2 class="font-bold text-lg">Produk terbaru</h2>
</div>
<div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-8">
    @foreach($popular as $p)
        @php $pIcon = $p->iconUrl(); @endphp
        <a href="{{ route('checkout.show', $p) }}" class="card p-4 hover:shadow-sm transition">
            <div class="flex items-center gap-2 mb-2">
                @if($pIcon)
                    <img src="{{ $pIcon }}" class="w-8 h-8" style="border-radius:8px" alt="{{ $p->game }}" loading="lazy">
                @endif
                <div class="min-w-0">
                    <div class="text-sm font-semibold truncate">{{ $p->name }}</div>
                    <div class="text-xs text-gray-500">{{ $p->game }}</div>
                </div>
            </div>
            <div class="flex items-center justify-between">
                <div class="text-sm font-bold">Rp {{ number_format($p->price_guest, 0, ',', '.') }}</div>
                @if($p->in_stock)
                    <span class="text-xs px-2 py-1 badge-ok" style="border-radius:999px">Stok ada</span>
                @else
                    <span class="text-xs px-2 py-1 badge-fail" style="border-radius:999px">Kosong</span>
                @endif
            </div>
        </a>
    @endforeach
</div>
@endif

<!-- KEUNGGULAN + CEK INVOICE -->
<div class="grid md:grid-cols-2 gap-3">
    <div class="card p-5">
        <div class="font-bold mb-2">Kenapa {{ $siteName }}?</div>
        <ul class="text-sm text-gray-600 space-y-1">
            <li>Harga reseller bertingkat: Guest, Biasa, VIP.</li>
            <li>Multi-supplier dengan fallback otomatis.</li>
            <li>Invoice bisa dicek publik tanpa login.</li>
        </ul>
    </div>
    <div class="card p-5">
        <div class="font-bold mb-2">Sudah pesan? Lacak di sini</div>
        <form action="{{ route('invoice.show') }}" class="flex gap-2">
            <input name="code" placeholder="cth: INV-20240101-XXXX" class="card flex-1 px-3 py-2 text-sm">
            <button class="btn-primary px-4 py-2 text-sm font-bold">Cek</button>
        </form>
    </div>
</div>
@endsection
