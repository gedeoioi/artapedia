@extends('layouts.shop')

@section('title', 'Dashboard Member')

@section('content')
<div class="grid sm:grid-cols-2 xl:grid-cols-4 gap-3 mb-4">
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
    <div class="card p-4 flex flex-col">
        <div class="flex items-center gap-2 mb-1">
            <span class="inline-flex w-8 h-8 items-center justify-center rounded-lg text-orange-400" style="background:rgba(249,115,22,.1)">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/></svg>
            </span>
            <div>
                <div class="text-xs text-gray-500">Profil akun</div>
                <div class="font-bold truncate max-w-40">{{ $user->name }}</div>
            </div>
        </div>
        <div class="text-xs text-gray-500 truncate mt-1">{{ $user->phone ?: 'Nomor HP belum diisi' }}</div>
        <a href="{{ route('profile.edit') }}" class="btn-primary inline-flex items-center justify-center gap-2 px-3 py-2 text-sm mt-3 w-fit">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4Z"/></svg>
            Edit Profil
        </a>
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
