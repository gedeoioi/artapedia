@extends('layouts.shop')

@section('title', 'Pembayaran '.$trx->invoice_code)

@section('content')
<div class="card p-5 max-w-xl mx-auto">
    <h1 class="font-bold text-lg">Invoice {{ $trx->invoice_code }}</h1>
    <div class="text-sm text-gray-600 mb-3">{{ $trx->product?->name ?? 'Topup Saldo' }} - Rp {{ number_format($trx->total_amount, 0, ',', '.') }}</div>
    <div class="card p-3 text-sm mb-3 {{ $trx->statusBadgeClass() }}" id="pay-status">
        <div class="font-semibold" id="pay-status-label">{{ $trx->statusLabel() }}</div>
    </div>
    @if($trx->payment_method === 'balance')
        <p class="text-sm">Dibayar dengan saldo member.</p>
    @else
        <p class="text-sm mb-2">Selesaikan pembayaran via <b>{{ $trx->payment_gateway_code }}</b> sebelum batas waktu.</p>
        @php
            $checkoutUrl = data_get($gatewayPayload, '_checkout_url') ?? data_get($gatewayPayload, 'Data.Url');
            $paymentNumber = data_get($gatewayPayload, 'Data.PaymentNo');
        @endphp
        @if($isQris)
            <div class="card p-4 mb-3 text-center">
                <div class="font-bold text-lg mb-1">Pembayaran QRIS</div>
                <div class="text-xs text-gray-500 mb-3">Scan menggunakan aplikasi bank atau e-wallet yang mendukung QRIS.</div>
                @if($qrisImage)
                    <img src="{{ $qrisImage }}" class="mx-auto bg-white p-3" style="width:min(100%,300px);border-radius:12px" alt="QRIS pembayaran {{ $trx->invoice_code }}">
                @else
                    <div class="text-sm text-red-400">QRIS tidak dapat ditampilkan. Silakan muat ulang halaman.</div>
                @endif
            </div>
        @elseif($paymentNumber)
            <div class="card p-3 mb-3">
                <div class="text-xs text-gray-500">Nomor pembayaran / Virtual Account</div>
                <div class="flex items-center gap-2 mt-1">
                    <code id="payment-number" class="font-bold text-lg flex-1">{{ $paymentNumber }}</code>
                    <button type="button" class="btn-primary px-3 py-2 text-xs" onclick="navigator.clipboard.writeText(document.getElementById('payment-number').textContent.trim())">Salin</button>
                </div>
            </div>
        @endif
        @if($checkoutUrl)
            <a href="{{ $checkoutUrl }}" class="btn-primary block w-full py-3 text-center font-bold mb-3" rel="nofollow">Bayar Sekarang</a>
        @endif
        @if($expiresAt)
            <div class="card p-3 mt-3 text-center">
                <div class="text-xs text-gray-500">Selesaikan pembayaran dalam</div>
                <div id="payment-countdown" class="font-bold text-xl mt-1" data-expires-at="{{ $expiresAt->toIso8601String() }}">--:--</div>
                <div class="text-xs text-gray-500 mt-1">Kedaluwarsa {{ $expiresAt->translatedFormat('d M Y, H:i') }}</div>
            </div>
        @endif
    @endif
</div>
@endsection

@section('scripts')
<script>
const statusUrl = @json(route('payment.status', $trx->invoice_code));
let timer;
const countdown = document.getElementById('payment-countdown');
let countdownTimer;

function refreshCountdown() {
    if (!countdown) return;
    const remaining = Math.max(0, new Date(countdown.dataset.expiresAt).getTime() - Date.now());
    const totalSeconds = Math.floor(remaining / 1000);
    const hours = Math.floor(totalSeconds / 3600);
    const minutes = Math.floor((totalSeconds % 3600) / 60);
    const seconds = totalSeconds % 60;
    countdown.textContent = hours > 0
        ? [hours, minutes, seconds].map(value => String(value).padStart(2, '0')).join(':')
        : [minutes, seconds].map(value => String(value).padStart(2, '0')).join(':');

    if (remaining <= 0) {
        countdown.textContent = 'Kedaluwarsa';
        countdown.style.color = '#f87171';
        if (countdownTimer) clearInterval(countdownTimer);
    }
}

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
refreshCountdown();
countdownTimer = setInterval(refreshCountdown, 1000);
</script>
@endsection
