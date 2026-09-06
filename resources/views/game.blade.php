@extends('layouts.shop')

@section('title', $game)
@section('meta_description', 'Topup '.$game.' murah di ArtaPedia. Proses otomatis.')

@section('content')
<h1 class="text-xl font-bold mb-4">{{ $game }}</h1>
<div class="grid grid-cols-1 md:grid-cols-2 gap-3">
    @foreach($products as $p)
        <a href="{{ route('checkout.show', $p) }}" class="card p-4 flex justify-between items-center">
            <div>
                <div class="font-semibold text-sm">{{ $p->name }}</div>
                <div class="text-xs text-gray-500">{{ $p->in_stock ? 'Stok tersedia' : 'Stok kosong' }}</div>
            </div>
            <div class="text-sm font-bold">Rp {{ number_format($p->price_guest, 0, ',', '.') }}</div>
        </a>
    @endforeach
</div>
@endsection
