@extends('layouts.shop')

@section('title', 'Dashboard Member')

@section('content')
<div class="grid md:grid-cols-3 gap-3 mb-4">
    <div class="card p-4">
        <div class="text-xs text-gray-500">Saldo</div>
        <div class="text-xl font-bold">Rp {{ number_format($user->balance, 0, ',', '.') }}</div>
        <a href="{{ route('topup.create') }}" class="card inline-block px-3 py-1 text-sm mt-2">+ Topup</a>
    </div>
    <div class="card p-4">
        <div class="text-xs text-gray-500">Level akun</div>
        <div class="text-xl font-bold capitalize">{{ $user->level }}</div>
        <div class="text-xs mt-2">Harga VIP / Biasa mengikuti level.</div>
    </div>
    <div class="card p-4">
        <div class="text-xs text-gray-500">Status</div>
        <div class="text-xl font-bold capitalize">{{ $user->status }}</div>
    </div>
</div>

<div class="card p-4">
    <h2 class="font-bold mb-2">Riwayat transaksi</h2>
    <table class="bordered text-sm">
        <thead><tr><th>Invoice</th><th>Produk</th><th>Total</th><th>Status</th><th>Rating</th></tr></thead>
        <tbody>
        @foreach($transactions as $t)
            @php $b = $t->status === 'success' ? 'badge-ok' : (in_array($t->status, ['pending','paid','processing']) ? 'badge-pending' : 'badge-fail'); @endphp
            <tr>
                <td><a class="underline" href="{{ route('payment.show', $t->invoice_code) }}">{{ $t->invoice_code }}</a></td>
                <td>{{ $t->product->name ?? '-' }}</td>
                <td>Rp {{ number_format($t->total_amount, 0, ',', '.') }}</td>
                <td><span class="card px-2 py-1 text-xs {{ $b }}">{{ $t->status }}</span></td>
                <td>
                    @if($t->status === 'success')
                        <form method="POST" action="{{ route('member.rate', $t) }}" class="flex gap-1">
                            @csrf
                            <input name="stars" type="number" min="1" max="5" value="{{ $t->rating?->stars ?? 5 }}" class="card w-14 px-1">
                            <button class="card px-2">OK</button>
                        </form>
                    @else - @endif
                </td>
            </tr>
        @endforeach
        </tbody>
    </table>
</div>
@endsection
