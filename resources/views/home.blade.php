@extends('layouts.shop')

@section('title', 'Topup Game & Pulsa Murah')

@section('content')
@php
    $siteName = \App\Models\SiteSetting::get('site_name', 'ArtaPedia');
    $tagline = \App\Models\SiteSetting::get('site_tagline', 'Topup game, pulsa & PPOB');
@endphp

<!-- SLIDE BANNER -->
@if(($banners ?? collect())->isNotEmpty())
<section class="mb-8" x-data="{ i: 0, total: {{ $banners->count() }} }" x-init="setInterval(() => { i = (i + 1) % total }, 5000)">
    <div class="relative overflow-hidden card" style="border-radius:16px">
        @foreach($banners as $idx => $b)
            <div x-show="i === {{ $idx }}" x-transition.opacity.duration.500ms class="w-full">
                @if($b->imageUrl())
                    <a @if($b->link_url) href="{{ $b->link_url }}" @endif class="block">
                        <img src="{{ $b->imageUrl() }}" alt="{{ $b->title }}" class="w-full h-40 md:h-64 object-cover">
                    </a>
                @else
                    <a @if($b->link_url) href="{{ $b->link_url }}" @endif class="block flash-grad p-6 md:p-10 text-white">
                        <div class="font-extrabold text-xl md:text-3xl">{{ $b->title }}</div>
                        @if($b->subtitle)<div class="text-sm text-orange-100 mt-1">{{ $b->subtitle }}</div>@endif
                        @if($b->button_text)<span class="inline-block mt-3 bg-white text-orange-600 text-sm font-bold px-4 py-2" style="border-radius:12px">{{ $b->button_text }}</span>@endif
                    </a>
                @endif
            </div>
        @endforeach
        @if($banners->count() > 1)
        <div class="absolute bottom-3 left-0 right-0 flex justify-center gap-2">
            @foreach($banners as $idx => $b)
                <button @click="i = {{ $idx }}" :class="i === {{ $idx }} ? 'bg-white' : 'bg-white/40'" class="w-2 h-2" style="border-radius:999px" aria-label="Slide {{ $idx + 1 }}"></button>
            @endforeach
        </div>
        <button @click="i = (i - 1 + total) % total" class="absolute left-2 top-1/2 -translate-y-1/2 bg-black/40 text-white w-8 h-8" style="border-radius:999px">&#8249;</button>
        <button @click="i = (i + 1) % total" class="absolute right-2 top-1/2 -translate-y-1/2 bg-black/40 text-white w-8 h-8" style="border-radius:999px">&#8250;</button>
        @endif
    </div>
    <div class="text-center mt-2 text-sm font-semibold" x-text="@js($banners->pluck('title')->values()) [i]"></div>
</section>
@endif

<!-- KATEGORI TERFAVORIT -->
@if(($favorites ?? collect())->isNotEmpty())
<section class="mb-8">
    <h2 class="font-extrabold text-lg">Kategori Terfavorit</h2>
    <p class="muted text-xs mb-3">Paling sering dibeli pelanggan.</p>
    <div class="grid grid-cols-4 md:grid-cols-8 gap-3">
        @foreach($favorites as $g)
            @php $catIcon = ($icons[$g->game] ?? null)?->iconUrl(); @endphp
            <a href="{{ route('game.show', $g->game) }}" class="card p-3 hover:border-orange-500 transition flex flex-col items-center text-center gap-2">
                @if($catIcon)
                    <img src="{{ $catIcon }}" class="w-14 h-14 object-cover" style="border-radius:14px" alt="{{ $g->game }}" loading="lazy">
                @else
                    <div class="w-14 h-14 flex items-center justify-center font-bold text-xl btn-primary" style="border-radius:14px">{{ mb_substr($g->game, 0, 1) }}</div>
                @endif
                <div class="text-xs font-semibold leading-tight" style="display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden">{{ $g->game }}</div>
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
<div class="grid grid-cols-4 md:grid-cols-8 gap-3 mb-8">
    @forelse($games as $g)
        @php $catIcon = ($icons[$g->game] ?? null)?->iconUrl(); @endphp
        <a href="{{ route('game.show', $g->game) }}" class="card p-3 hover:border-orange-500 transition flex flex-col items-center text-center gap-2">
            @if($catIcon)
                <img src="{{ $catIcon }}" class="w-14 h-14 object-cover" style="border-radius:14px" alt="{{ $g->game }}" loading="lazy">
            @else
                <div class="w-14 h-14 flex items-center justify-center font-bold text-xl btn-primary" style="border-radius:14px">{{ mb_substr($g->game, 0, 1) }}</div>
            @endif
            <div class="text-xs font-semibold leading-tight" style="display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden">{{ $g->game }}</div>
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
