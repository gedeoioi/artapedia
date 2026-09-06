@extends('layouts.shop')

@section('title', 'Semua Kategori')

@section('content')
<div class="flex items-center justify-between mb-3">
    <h1 class="font-extrabold text-xl">Semua Kategori</h1>
</div>
<form action="{{ route('categories.index') }}" class="flex gap-2 max-w-md mb-4">
    <input name="q" value="{{ $q }}" placeholder="Cari kategori..." class="card flex-1 px-4 py-2 text-sm">
    <button class="btn-primary px-5 py-2 text-sm">Cari</button>
</form>
<div class="grid grid-cols-5 gap-2 mb-6">
    @forelse($games as $g)
        @php $catIcon = ($icons[$g->game] ?? null)?->iconUrl(); @endphp
        <a href="{{ route('game.show', $g->game) }}" class="card p-2 hover:border-orange-500 transition flex flex-col items-center text-center gap-1.5">
            @if($catIcon)
                <img src="{{ $catIcon }}" class="w-12 h-12 object-cover" style="border-radius:12px" alt="{{ $g->game }}" loading="lazy">
            @else
                <div class="w-12 h-12 flex items-center justify-center font-bold text-lg btn-primary" style="border-radius:12px">{{ mb_substr($g->game, 0, 1) }}</div>
            @endif
            <div class="text-[11px] font-semibold leading-tight" style="display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden">{{ $g->game }}</div>
            <div class="text-[10px] muted">{{ $g->total }} produk</div>
        </a>
    @empty
        <div class="card p-6 text-sm muted col-span-full text-center">Tidak ada kategori ditemukan.</div>
    @endforelse
</div>
<div class="flex justify-center">{{ $games->links() }}</div>
@endsection
