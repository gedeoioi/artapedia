@extends('layouts.shop')

@section('title', 'Cek Invoice')

@section('content')
<div class="card p-5 max-w-xl mx-auto mb-4">
    <h1 class="font-bold text-lg mb-2">Cek Invoice</h1>
    <form action="{{ route('invoice.show') }}" class="flex gap-2">
        <input name="code" value="{{ $code ?? '' }}" placeholder="cth: INV-20240101-XXXX" class="card flex-1 px-3 py-2">
        <button class="card px-4">Cek</button>
    </form>
</div>

@if(isset($trx))
    @if($trx)
        <div class="card p-5 max-w-xl mx-auto">
            <div class="font-bold">{{ $trx->invoice_code }}</div>
            <div class="text-sm text-gray-600 mb-3">{{ $trx->product->name ?? '' }} - Rp {{ number_format($trx->total_amount, 0, ',', '.') }}</div>
            <ol class="text-sm space-y-2">
                <li>✅ Order dibuat ({{ $trx->created_at }})</li>
                <li>{{ $trx->paid_at ? '✅' : '⏳' }} Pembayaran {{ $trx->paid_at ? 'diterima '.$trx->paid_at : 'menunggu' }}</li>
                <li>{{ $trx->processed_at ? '✅' : '⏳' }} Diproses ke supplier {{ $trx->processed_at ?? '' }}</li>
                <li>
                    @if($trx->status === 'success') ✅ Sukses
                    @elseif($trx->status === 'failed') ❌ Gagal
                    @elseif($trx->status === 'expired') ❌ Kedaluwarsa
                    @else ⏳ {{ $trx->status }}
                    @endif
                </li>
            </ol>
        </div>
    @else
        <div class="card p-4 max-w-xl mx-auto text-sm">Invoice tidak ditemukan.</div>
    @endif
@endif
@endsection
