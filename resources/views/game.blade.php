@extends('layouts.shop')

@section('title', $game)
@section('meta_description', 'Topup '.$game.' murah di ArtaPedia. Proses otomatis.')

@section('content')
<div class="flex items-center gap-3 mb-4">
    @php $catIcon = $icon?->icon_path ? asset('storage/'.$icon->icon_path) : null; @endphp
    @if($catIcon)
        <img src="{{ $catIcon }}" class="w-14 h-14" style="border-radius:14px" alt="{{ $game }}">
    @endif
    <div>
        <h1 class="text-xl font-bold">{{ $game }}</h1>
        <div class="text-xs text-gray-500">{{ $products->count() }} produk tersedia</div>
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
@endsection
