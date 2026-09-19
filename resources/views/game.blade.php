@extends('layouts.shop')

@section('title', $game)
@section('meta_description', 'Topup '.$game.' murah di ArtaPedia. Proses otomatis.')

@section('content')
<div class="flex items-center gap-3 mb-4">
    @php $catIcon = $icon?->iconUrl(); @endphp
    @if($catIcon)
        <img src="{{ $catIcon }}" class="w-14 h-14" style="border-radius:14px" alt="{{ $game }}">
    @endif
    <div>
        <h1 class="text-xl font-bold">{{ $game }}</h1>
        <div class="text-xs text-gray-500">{{ $products->count() }} produk tersedia</div>
        @if($ratingSummary['total'] > 0)
            <div class="text-xs mt-1">
                <span style="color:#facc15">{{ str_repeat('★', (int) round($ratingSummary['average'])) }}</span>
                <span class="font-bold">{{ number_format($ratingSummary['average'], 1, ',', '.') }}</span>
                <span class="text-gray-500">({{ $ratingSummary['total'] }} ulasan)</span>
            </div>
        @endif
    </div>
</div>
<div class="grid grid-cols-1 md:grid-cols-2 gap-3">
    @foreach($products as $p)
        @php $pIcon = $p->iconUrl(); @endphp
        <a href="{{ route('checkout.show', $p) }}" class="card p-4 flex justify-between items-center gap-3">
            <div class="flex items-center gap-3 min-w-0">
                @if($pIcon)
                    <img src="{{ $pIcon }}" class="w-10 h-10 shrink-0" style="border-radius:10px" alt="{{ $p->game }}" loading="lazy">
                @endif
                <div class="min-w-0">
                    <div class="font-semibold text-sm truncate">{{ $p->name }}</div>
                    <div class="text-xs text-gray-500">{{ $p->in_stock ? 'Stok tersedia' : 'Stok kosong' }}</div>
                </div>
            </div>
            <div class="text-sm font-bold shrink-0">Rp {{ number_format($p->price_guest, 0, ',', '.') }}</div>
        </a>
    @endforeach
</div>

@if($reviews->isNotEmpty())
    <section class="mt-8">
        <div class="flex items-center justify-between gap-3 mb-3">
            <h2 class="font-bold">Review pembeli {{ $game }}</h2>
            <a href="{{ route('reviews.index') }}" class="accent text-xs font-bold">Lihat semua</a>
        </div>
        <div class="grid gap-3 md:grid-cols-2">
            @foreach($reviews as $review)
                <article class="card p-4">
                    <div class="text-sm" style="color:#facc15">{{ str_repeat('★', $review->stars) }}<span class="text-gray-500">{{ str_repeat('☆', 5 - $review->stars) }}</span></div>
                    @if($review->comment)
                        <p class="mt-2 text-sm leading-relaxed">{{ $review->comment }}</p>
                    @endif
                    <div class="text-xs text-gray-500 mt-2">
                        {{ $review->transaction?->nickname ?: 'Pembeli' }} &middot; {{ $review->created_at->format('d/m/Y') }}
                    </div>
                </article>
            @endforeach
        </div>
    </section>
@endif
@endsection
