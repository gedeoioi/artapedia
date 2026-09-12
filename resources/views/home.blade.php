@extends('layouts.shop')

@section('title', 'Topup Game & Pulsa Murah')

@section('content')
@php
    $siteName = \App\Models\SiteSetting::get('site_name', 'ArtaPedia');
    $tagline = \App\Models\SiteSetting::get('site_tagline', 'Topup game, pulsa & PPOB');
@endphp

<!-- SLIDE BANNER -->
@if(($banners ?? collect())->isNotEmpty())
<section class="mb-8"
    x-data="{
        i: 0,
        total: {{ $banners->count() }},
        durations: @js($banners->map(fn ($b) => max(2, (int) ($b->duration_seconds ?? 5)) * 1000)->values()),
        timer: null,
        go(n) { this.i = (n + this.total) % this.total; this.restart(); },
        next() { this.go(this.i + 1); },
        prev() { this.go(this.i - 1); },
        restart() { clearTimeout(this.timer); this.timer = setTimeout(() => this.next(), this.durations[this.i] ?? 5000); }
    }"
    x-init="restart()">
    <div class="relative overflow-hidden card" style="border-radius:16px">
        <div class="flex transition-transform duration-700 ease-in-out" :style="'transform: translateX(-' + (i * 100) + '%); width: ' + (total * 100) + '%'">
            @foreach($banners as $b)
                <div class="shrink-0" style="width: {{ 100 / max(1, $banners->count()) }}%">
                    @if($b->imageUrl())
                        <a @if($b->link_url) href="{{ $b->link_url }}" @endif class="block aspect-[9/2]" aria-label="{{ $b->title }}">
                            <img src="{{ $b->imageUrl() }}" alt="{{ $b->title }}" class="w-full h-full object-cover" draggable="false">
                        </a>
                    @else
                        <a @if($b->link_url) href="{{ $b->link_url }}" @endif class="flash-grad aspect-[9/2] px-6 md:px-10 text-white flex flex-col justify-center" aria-label="{{ $b->title }}">
                            <div class="font-extrabold text-lg md:text-3xl">{{ $b->title }}</div>
                            @if($b->subtitle)<div class="hidden sm:block text-sm text-orange-100 mt-1">{{ $b->subtitle }}</div>@endif
                            @if($b->button_text)<span class="hidden sm:inline-block self-start mt-3 bg-white text-orange-600 text-sm font-bold px-4 py-2" style="border-radius:12px">{{ $b->button_text }}</span>@endif
                        </a>
                    @endif
                </div>
            @endforeach
        </div>
        @if($banners->count() > 1)
        <div class="absolute bottom-3 left-0 right-0 flex justify-center gap-2">
            @foreach($banners as $idx => $b)
                <button @click="go({{ $idx }})" :class="i === {{ $idx }} ? 'bg-white w-6' : 'bg-white/40 w-2'" class="h-2 transition-all" style="border-radius:999px" aria-label="Slide {{ $idx + 1 }}"></button>
            @endforeach
        </div>
        <button @click="prev()" class="absolute left-2 top-1/2 -translate-y-1/2 bg-black/40 text-white w-8 h-8" style="border-radius:999px" aria-label="Sebelumnya">&#8249;</button>
        <button @click="next()" class="absolute right-2 top-1/2 -translate-y-1/2 bg-black/40 text-white w-8 h-8" style="border-radius:999px" aria-label="Berikutnya">&#8250;</button>
        @endif
    </div>
</section>
@endif

<!-- KATEGORI TERFAVORIT -->
@if(($favorites ?? collect())->isNotEmpty())
<section class="mb-8">
    <div class="mb-4">
        <div>
            <h2 class="font-extrabold text-lg tracking-wide flex items-center gap-2"><span aria-hidden="true">🔥</span> KATEGORI FAVORIT</h2>
            <p class="muted text-xs mt-1 ml-7">Kategori yang paling populer saat ini.</p>
        </div>
    </div>
    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-3.5">
        @foreach($favorites as $g)
            @php $catIcon = ($icons[$g->game] ?? null)?->iconUrl(); @endphp
            <a href="{{ route('game.show', $g->game) }}" class="favorite-card group" aria-label="Buka kategori {{ $g->game }}">
                <div class="category-cover">
                    @if($catIcon)
                        <img src="{{ $catIcon }}" class="w-full h-full object-cover" alt="{{ $g->game }}" loading="lazy">
                    @else
                        <div class="w-full h-full flex items-center justify-center text-3xl font-black text-orange-100 flash-grad">{{ mb_substr($g->game, 0, 1) }}</div>
                    @endif
                </div>
                <div class="min-w-0 py-3 pr-4 pl-1">
                    <div class="font-extrabold text-sm md:text-base leading-snug truncate">{{ $g->game }}</div>
                    <div class="muted text-xs mt-1 truncate">{{ $g->product_count ?? $g->total }} pilihan · mulai Rp{{ number_format((int) ($g->min_price ?? 0), 0, ',', '.') }}</div>
                </div>
            </a>
        @endforeach
    </div>
</section>
@endif

<!-- SEMUA KATEGORI -->
<div class="flex items-center justify-between gap-4 mb-4">
    <h2 class="sr-only">Semua Kategori</h2>
    @if($q)<a href="{{ route('home') }}" class="text-xs font-semibold accent hover:underline">Reset pencarian</a>@endif
</div>
<div x-data="{
    activeType: @js($activeType),
    pageByType: { game: 0, pulsa: 0, data: 0, voucher: 0 },
    categoryPageTransitioning: false,
    changeCategoryPage(type, direction) {
        if (this.categoryPageTransitioning) return;
        this.categoryPageTransitioning = true;
        window.setTimeout(() => {
            this.pageByType[type] += direction;
            window.requestAnimationFrame(() => window.requestAnimationFrame(() => {
                this.categoryPageTransitioning = false;
            }));
        }, 180);
    }
}">
    <div class="mb-5">
        @include('partials.catalog-tabs', ['routeName' => 'home', 'interactive' => true])
    </div>
    @foreach($categoryPagesByType as $type => $typePages)
    <section
        id="category-panel-{{ $type }}"
        x-show="activeType === @js($type)"
        @if($type !== $activeType) x-cloak @endif
        aria-label="Kategori {{ \App\Models\Product::TYPES[$type] ?? $type }}"
    >
        @foreach($typePages as $pageIndex => $typeGames)
        <div
            x-show="pageByType[@js($type)] === {{ $pageIndex }}"
            :class="{ 'category-page-changing': categoryPageTransitioning }"
            :aria-busy="categoryPageTransitioning"
            class="category-page-transition"
            @if($type !== $activeType || $pageIndex !== 0) x-cloak @endif
        >
            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3 md:gap-4 mb-5">
                @forelse($typeGames as $g)
                    @php $catIcon = ($icons[$g->game] ?? null)?->iconUrl(); @endphp
                    <a href="{{ route('game.show', $g->game) }}" data-cat-item class="category-card group" aria-label="Buka kategori {{ $g->game }}">
                        <div class="category-cover aspect-square">
                            @if($catIcon)
                                <img src="{{ $catIcon }}" class="w-full h-full object-cover" alt="{{ $g->game }}" loading="lazy">
                            @else
                                <div class="w-full h-full flex items-center justify-center text-5xl font-black text-orange-100 flash-grad">{{ mb_substr($g->game, 0, 1) }}</div>
                            @endif
                        </div>
                        <div class="p-3">
                            <div class="text-sm font-bold leading-snug line-clamp-2-custom min-h-[2.5rem]">{{ $g->game }}</div>
                            <div class="text-xs muted mt-1.5">{{ $g->total }} produk</div>
                            <div class="text-xs font-bold accent mt-0.5">Mulai Rp{{ number_format((int) $g->min_price, 0, ',', '.') }}</div>
                        </div>
                    </a>
                @empty
                    <div class="card p-6 text-sm muted col-span-full text-center">
                        <div class="font-semibold mb-1">Belum ada produk</div>
                        <div>Tidak ada kategori {{ \App\Models\Product::TYPES[$type] ?? '' }} yang cocok.</div>
                    </div>
                @endforelse
            </div>

            @if($typePages->count() > 1)
            <div class="flex items-center justify-center gap-3 mb-8" aria-label="Navigasi kelompok kategori">
                @if($pageIndex > 0)
                <button type="button" @click="changeCategoryPage(@js($type), -1)" :disabled="categoryPageTransitioning" class="card px-5 py-2.5 text-sm font-bold hover:border-orange-500 disabled:opacity-50">
                    Sebelumnya
                </button>
                @endif
                <span class="muted text-xs">{{ $pageIndex + 1 }} / {{ $typePages->count() }}</span>
                @if($pageIndex < $typePages->count() - 1)
                <button type="button" @click="changeCategoryPage(@js($type), 1)" :disabled="categoryPageTransitioning" class="catalog-more-button disabled:opacity-50">
                    Lihat Selanjutnya
                </button>
                @endif
            </div>
            @else
            <div class="mb-8"></div>
            @endif
        </div>
        @endforeach
    </section>
    @endforeach
</div>

<style>
    .category-page-transition {
        opacity: 1;
        transform: translateY(0) scale(1);
        transition: opacity .32s ease, transform .32s cubic-bezier(.22, 1, .36, 1);
        will-change: opacity, transform;
    }
    .category-page-transition.category-page-changing {
        opacity: 0;
        transform: translateY(9px) scale(.995);
        pointer-events: none;
    }
    @media (prefers-reduced-motion: reduce) {
        .category-page-transition { transition: none; }
    }
</style>

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
