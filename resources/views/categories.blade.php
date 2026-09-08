@extends('layouts.shop')

@section('title', 'Semua Kategori')

@section('content')
<h1 class="sr-only">Semua Kategori</h1>
<div class="mb-5">
    @include('partials.catalog-tabs', ['routeName' => 'categories.index'])
</div>
<form action="{{ route('categories.index') }}" class="flex gap-2 max-w-md mb-4">
    <input type="hidden" name="type" value="{{ $activeType }}">
    <input name="q" value="{{ $q }}" placeholder="Cari kategori..." class="card flex-1 px-4 py-2 text-sm">
    <button class="btn-primary px-5 py-2 text-sm">Cari</button>
</form>
<div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 xl:grid-cols-6 gap-3 md:gap-4 mb-8">
    @forelse($games as $g)
        @php $catIcon = ($icons[$g->game] ?? null)?->iconUrl(); @endphp
        <a href="{{ route('game.show', $g->game) }}" class="category-card group" aria-label="Buka kategori {{ $g->game }}">
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
        <div class="card p-6 text-sm muted col-span-full text-center">Tidak ada kategori {{ \App\Models\Product::TYPES[$activeType] ?? '' }} ditemukan.</div>
    @endforelse
</div>
<div class="flex justify-center">{{ $games->links() }}</div>
@endsection
