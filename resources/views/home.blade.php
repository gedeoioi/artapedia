@extends('layouts.shop')

@section('title', 'Topup Game & Pulsa Murah')

@section('content')
@php
    $siteName = \App\Models\SiteSetting::get('site_name', 'ArtaPedia');
    $tagline = \App\Models\SiteSetting::get('site_tagline', 'Topup game, pulsa & PPOB');
@endphp

<!-- HERO -->
<section class="hero-grad card p-6 md:p-10 mb-8 text-center">
    <div class="inline-block text-xs font-semibold px-3 py-1 mb-3 badge-ok" style="border-radius: 999px;">
        Topup game &amp; voucher termurah &bull; buka 24 jam
    </div>
    <h1 class="text-2xl md:text-4xl font-extrabold mb-2 leading-tight">Topup Game Favoritmu<br>dalam Hitungan Detik.</h1>
    <p class="muted text-sm md:text-base mb-5">{{ $tagline }}. {{ $totalProducts }} produk dari {{ $totalGames }} game, pembayaran lengkap Indonesia.</p>
    <form action="{{ route('home') }}" class="flex gap-2 max-w-xl mx-auto">
        <input name="q" value="{{ $q }}" placeholder="Cari Mobile Legends, Free Fire, voucher..." class="card flex-1 px-4 py-3 text-sm" autofocus>
        <button class="btn-primary px-6 py-3 text-sm">Cari</button>
    </form>
    @if($gateways->isNotEmpty())
    <div class="flex flex-wrap justify-center gap-2 mt-4">
        @foreach($gateways as $gw)
            <span class="text-xs px-3 py-1 card muted">{{ $gw->name }}</span>
        @endforeach
        <span class="text-xs px-3 py-1 card muted">Saldo member</span>
    </div>
    @endif
</section>

<!-- FLASH SALE -->
@if($flashSale->isNotEmpty())
<section class="mb-8">
    <div class="flash-grad card p-5 mb-3 flex items-center justify-between">
        <div>
            <div class="font-extrabold text-lg text-white">FLASH SALE</div>
            <div class="text-xs text-orange-100">Harga termurah hari ini, stok tersedia.</div>
        </div>
        <div class="text-xs text-orange-100">Berakhir 23:59</div>
    </div>
    <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
        @foreach($flashSale as $p)
            @php $pIcon = $p->iconUrl(); @endphp
            <a href="{{ route('checkout.show', $p) }}" class="card p-4 hover:border-orange-500 transition">
                <div class="flex items-center gap-2 mb-2">
                    @if($pIcon)
                        <img src="{{ $pIcon }}" class="w-10 h-10" style="border-radius:10px" alt="{{ $p->game }}" loading="lazy">
                    @endif
                    <div class="min-w-0">
                        <div class="text-sm font-semibold truncate">{{ $p->name }}</div>
                        <div class="text-xs muted">{{ $p->game }}</div>
                    </div>
                </div>
                <div class="text-base font-extrabold accent">Rp {{ number_format($p->price_guest, 0, ',', '.') }}</div>
            </a>
        @endforeach
    </div>
</section>
@endif

<!-- TRENDING -->
@if($trending->isNotEmpty())
<section class="mb-8">
    <h2 class="font-extrabold text-lg">TRENDING</h2>
    <p class="muted text-xs mb-3">Produk paling populer saat ini.</p>
    <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
        @foreach($trending as $g)
            @php $catIcon = ($icons[$g->game] ?? null)?->iconUrl(); @endphp
            <a href="{{ route('game.show', $g->game) }}" class="card p-4 hover:border-orange-500 transition">
                <div class="flex items-center gap-3">
                    @if($catIcon)
                        <img src="{{ $catIcon }}" class="w-12 h-12" style="border-radius:12px" alt="{{ $g->game }}" loading="lazy">
                    @else
                        <div class="w-12 h-12 flex items-center justify-center font-bold text-lg btn-primary">{{ mb_substr($g->game, 0, 1) }}</div>
                    @endif
                    <div class="min-w-0">
                        <div class="font-semibold text-sm truncate">{{ $g->game }}</div>
                        <div class="text-xs muted">{{ $g->total }} produk</div>
                    </div>
                </div>
                <div class="flex items-center justify-between mt-3">
                    <div class="text-xs muted">Mulai dari</div>
                    <div class="text-sm font-bold">Rp {{ number_format($g->min_price, 0, ',', '.') }}</div>
                </div>
            </a>
        @endforeach
    </div>
</section>
@endif

<!-- SEMUA KATEGORI -->
<div class="flex items-center justify-between mb-3">
    <h2 class="font-bold text-lg">Semua Kategori</h2>
    @if($q)<a href="{{ route('home') }}" class="text-xs underline">Reset pencarian</a>@endif
</div>
<div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-8">
    @forelse($games as $g)
        @php $catIcon = ($icons[$g->game] ?? null)?->iconUrl(); @endphp
        <a href="{{ route('game.show', $g->game) }}" class="card p-4 hover:border-orange-500 transition group">
            <div class="flex items-center gap-3">
                @if($catIcon)
                    <img src="{{ $catIcon }}" class="w-12 h-12" style="border-radius:12px" alt="{{ $g->game }}" loading="lazy">
                @else
                    <div class="w-12 h-12 flex items-center justify-center font-bold text-lg btn-primary">{{ mb_substr($g->game, 0, 1) }}</div>
                @endif
                <div class="min-w-0">
                    <div class="font-semibold text-sm truncate">{{ $g->game }}</div>
                    <div class="text-xs muted">{{ $g->total }} produk</div>
                </div>
            </div>
            <div class="flex items-center justify-between mt-3">
                <div class="text-xs muted">Mulai dari</div>
                <div class="text-sm font-bold">Rp {{ number_format($g->min_price, 0, ',', '.') }}</div>
            </div>
        </a>
    @empty
        <div class="card p-6 text-sm muted col-span-full text-center">
            <div class="font-semibold mb-1">Belum ada produk</div>
            <div>Jalankan sync dari supplier di admin, atau ubah kata kunci pencarian.</div>
        </div>
    @endforelse
</div>

<!-- CARA ORDER -->
<div class="grid grid-cols-1 md:grid-cols-3 gap-3 mb-8">
    <div class="card p-4 flex gap-3 items-start">
        <div class="text-2xl font-extrabold accent">1</div>
        <div><div class="font-semibold text-sm">Pilih produk</div><div class="text-xs muted">Cari game, pilih nominal, isi User ID tujuan.</div></div>
    </div>
    <div class="card p-4 flex gap-3 items-start">
        <div class="text-2xl font-extrabold accent">2</div>
        <div><div class="font-semibold text-sm">Bayar</div><div class="text-xs muted">QRIS / VA / e-wallet atau potong saldo member.</div></div>
    </div>
    <div class="card p-4 flex gap-3 items-start">
        <div class="text-2xl font-extrabold accent">3</div>
        <div><div class="font-semibold text-sm">Otomatis masuk</div><div class="text-xs muted">Diproses ke supplier, pantau via Cek Transaksi.</div></div>
    </div>
</div>

<!-- PRODUK TERBARU -->
@if($popular->isNotEmpty())
<div class="flex items-center justify-between mb-3">
    <h2 class="font-bold text-lg">Produk Terbaru</h2>
</div>
<div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-8">
    @foreach($popular as $p)
        @php $pIcon = $p->iconUrl(); @endphp
        <a href="{{ route('checkout.show', $p) }}" class="card p-4 hover:border-orange-500 transition">
            <div class="flex items-center gap-2 mb-2">
                @if($pIcon)
                    <img src="{{ $pIcon }}" class="w-8 h-8" style="border-radius:8px" alt="{{ $p->game }}" loading="lazy">
                @endif
                <div class="min-w-0">
                    <div class="text-sm font-semibold truncate">{{ $p->name }}</div>
                    <div class="text-xs muted">{{ $p->game }}</div>
                </div>
            </div>
            <div class="flex items-center justify-between">
                <div class="text-sm font-bold">Rp {{ number_format($p->price_guest, 0, ',', '.') }}</div>
                <span class="text-xs px-2 py-1 badge-ok" style="border-radius:999px">Stok ada</span>
            </div>
        </a>
    @endforeach
</div>
@endif

<!-- CEK TRANSAKSI -->
<div class="card p-5">
    <div class="font-bold mb-1">Sudah pesan? Lacak di sini</div>
    <p class="muted text-xs mb-3">Masukkan kode invoice untuk melihat status pembayaran dan pengiriman.</p>
    <form action="{{ route('invoice.show') }}" class="flex gap-2 max-w-md">
        <input name="code" placeholder="cth: INV-20240101-XXXX" class="card flex-1 px-3 py-2 text-sm">
        <button class="btn-primary px-5 py-2 text-sm">Cek Transaksi</button>
    </form>
</div>
@endsection
