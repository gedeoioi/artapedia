@extends('layouts.shop')

@section('title', 'Pembayaran '.$trx->invoice_code)

@section('content')
<div class="card p-5 max-w-xl mx-auto">
    <h1 class="font-bold text-lg">Invoice {{ $trx->invoice_code }}</h1>
    <div class="text-sm text-gray-600 mb-3">{{ $trx->product->name }} - Rp {{ number_format($trx->total_amount, 0, ',', '.') }}</div>
    @php
        $badge = $trx->status === 'success' ? 'badge-ok' : (in_array($trx->status, ['pending','paid','processing']) ? 'badge-pending' : 'badge-fail');
    @endphp
    <div class="card p-2 text-sm mb-3 {{ $badge }}" id="pay-status">Status: {{ $trx->status }} - menunggu pembayaran terdeteksi</div>
    @if($trx->payment_method === 'balance')
        <p class="text-sm">Dibayar dengan saldo member. Pesanan diteruskan ke supplier otomatis.</p>
    @else
        <p class="text-sm mb-2">Selesaikan pembayaran via <b>{{ $trx->payment_gateway_code }}</b> sebelum batas waktu.</p>
        @if($trx->invoice?->payload || $trx->payment_payload)
            <pre class="card p-3 text-xs overflow-auto">{{ json_encode($trx->invoice?->payload ?? $trx->payment_payload, JSON_PRETTY_PRINT) }}</pre>
        @endif
        @if($trx->invoice?->expired_at)
            <p class="text-xs text-gray-500 mt-2">Berlaku hingga {{ $trx->invoice->expired_at }}</p>
        @endif
    @endif
</div>
@endsection

@section('scripts')
<script>
const statusUrl = "{{ route('payment.status', $trx->invoice_code) }}";
const timer = setInterval(async () => {
    try {
        const r = await fetch(statusUrl);
        const j = await r.json();
        document.getElementById('pay-status').textContent = 'Status: ' + j.status;
        if (['success', 'failed', 'expired'].includes(j.status)) clearInterval(timer);
    } catch (e) {}
}, 5000);
</script>
@endsection
