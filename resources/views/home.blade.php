@extends('layouts.shop')

@section('title', 'Topup Game & Pulsa Murah')

@section('content')
<div class="card p-6 mb-6">
    <h1 class="text-2xl font-bold mb-1">Topup game, pulsa & PPOB</h1>
    <p class="text-gray-600 text-sm mb-4">Proses otomatis, bayar via QRIS / VA / e-wallet atau saldo member.</p>
    <form action="{{ route('home') }}" class="flex gap-2">
        <input name="q" value="{{ $q }}" placeholder="Cari Mobile Legends, Free Fire, ..." class="card flex-1 px-4 py-2">
        <button class="card px-5 py-2">Cari</button>
    </form>
</div>

<h2 class="font-bold mb-3">Kategori populer</h2>
<div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-8">
    @forelse($games as $g)
        <a href="{{ route('game.show', $g->game) }}" class="card p-4">
            @if(($icons[$g->game] ?? null)?->icon_path)
                <img src="{{ asset('storage/'.$icons[$g->game]->icon_path) }}" class="w-10 h-10 rounded mb-2" alt="{{ $g->game }}">
            @endif
            <div class="font-semibold text-sm">{{ $g->game }}</div>
            <div class="text-xs text-gray-500">Mulai Rp {{ number_format($g->min_price, 0, ',', '.') }}</div>
        </a>
    @empty
        <div class="card p-4 text-sm text-gray-500">Belum ada produk. Jalankan sync dari supplier di admin.</div>
    @endforelse
</div>

<h2 class="font-bold mb-3">Produk terbaru</h2>
<div class="grid grid-cols-2 md:grid-cols-4 gap-3">
    @foreach($popular as $p)
        <a href="{{ route('checkout.show', $p) }}" class="card p-4">
            <div class="text-sm font-semibold">{{ $p->name }}</div>
            <div class="text-xs text-gray-500 mb-1">{{ $p->game }}</div>
            <div class="text-sm">Rp {{ number_format($p->price_guest, 0, ',', '.') }}</div>
        </a>
    @endforeach
</div>
@endsection
