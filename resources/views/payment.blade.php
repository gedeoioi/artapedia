@extends('layouts.shop')

@section('title', 'Pembayaran '.$trx->invoice_code)

@section('content')
<div class="card p-5 max-w-xl mx-auto">
    <h1 class="font-bold text-lg">Invoice {{ $trx->invoice_code }}</h1>
    <div class="text-sm text-gray-600 mb-3">{{ $trx->product->name }} - Rp {{ number_format($trx->total_amount, 0, ',', '.') }}</div>
    <div class="card p-3 text-sm mb-3 {{ $trx->statusBadgeClass() }}" id="pay-status">
        <div class="font-semibold" id="pay-status-label">{{ $trx->statusLabel() }}</div>
    </div>
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
const statusUrl = @json(route('payment.status', $trx->invoice_code));
let timer;

async function refreshStatus() {
    try {
        const r = await fetch(statusUrl, { headers: { 'Accept': 'application/json' }, cache: 'no-store' });
        if (!r.ok) return;
        const j = await r.json();
        const box = document.getElementById('pay-status');
        box.className = 'card p-3 text-sm mb-3 ' + j.badge;
        document.getElementById('pay-status-label').textContent = j.status_label;

        if (['success', 'failed', 'expired'].includes(j.status) && timer) clearInterval(timer);
    } catch (e) {}
}

refreshStatus();
timer = setInterval(refreshStatus, 5000);
</script>
@endsection
