@extends('layouts.shop')

@section('title', 'Cek Invoice')

@section('content')
<div class="invoice-page max-w-3xl mx-auto">
    <div class="card p-5 sm:p-6 mb-4">
        <div class="flex items-start gap-3 mb-4">
            <span class="invoice-heading-icon" aria-hidden="true">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 3h16v18l-3-2-3 2-3-2-3 2-4-2Z"/><path d="M8 8h8M8 12h6"/></svg>
            </span>
            <div>
                <h1 class="font-bold text-xl">Cek Invoice</h1>
                <p class="muted text-sm mt-1">Masukkan kode invoice untuk melihat rincian dan status pesanan.</p>
            </div>
        </div>
        <form action="{{ route('invoice.show') }}" class="invoice-search-form">
            <label for="invoice-code" class="sr-only">Kode invoice</label>
            <input id="invoice-code" name="code" value="{{ $code ?? '' }}" placeholder="Contoh: INV-20260911-XXXX" class="card px-4 py-3" required autocomplete="off">
            <button class="btn-primary px-6 py-3">Cek Invoice</button>
        </form>
    </div>

    @if(isset($trx))
        @if($trx)
            @php
                $payload = $trx->invoice?->payload ?? $trx->payment_payload ?? [];
                $channel = strtolower((string) data_get($payload, 'Data.Channel'));
                $paymentName = trim((string) data_get($payload, 'Data.PaymentName'));
                $paymentMethod = match (strtolower((string) $trx->payment_method)) {
                    'balance' => 'Saldo Member',
                    'ipaymu' => $paymentName ?: match ($channel) {
                        'mpm', 'qris' => 'QRIS',
                        default => $channel ? strtoupper($channel) : 'Transfer',
                    },
                    default => \Illuminate\Support\Str::headline($trx->payment_method ?: $trx->payment_gateway_code ?: '-'),
                };
                $targetLabel = $trx->product?->product_type === \App\Models\Product::TYPE_GAME
                    ? 'ID Game'
                    : ($trx->product?->targetLabel() ?? 'ID / Nomor Tujuan');
                $isTopup = ($trx->meta['kind'] ?? null) === 'topup';
            @endphp

            <article class="card invoice-result overflow-hidden">
                <header class="invoice-result-header">
                    <div class="min-w-0">
                        <div class="muted text-xs uppercase tracking-wider font-semibold">Kode Invoice</div>
                        <h2 class="font-bold text-lg sm:text-xl mt-1 break-all">{{ $trx->invoice_code }}</h2>
                        <div class="muted text-sm mt-1">{{ $trx->product?->name ?? 'Topup Saldo' }}</div>
                    </div>
                    <span class="invoice-status-pill {{ $trx->statusBadgeClass() }}">{{ $trx->statusLabel() }}</span>
                </header>

                <dl class="invoice-detail-grid">
                    @unless($isTopup)
                        <div class="invoice-detail-item">
                            <dt>{{ $targetLabel }}</dt>
                            <dd>{{ $trx->target_user_id ?: '-' }}</dd>
                            @if($trx->target_zone)
                                <small>Server / Zone: {{ $trx->target_zone }}</small>
                            @endif
                        </div>
                        <div class="invoice-detail-item">
                            <dt>Nickname</dt>
                            <dd>{{ $trx->nickname ?: '-' }}</dd>
                        </div>
                    @endunless
                    <div class="invoice-detail-item">
                        <dt>Jumlah Pesanan</dt>
                        <dd>{{ number_format($trx->quantity ?: 1, 0, ',', '.') }} item</dd>
                    </div>
                    <div class="invoice-detail-item">
                        <dt>Total Pembayaran</dt>
                        <dd class="invoice-total">Rp {{ number_format($trx->total_amount, 0, ',', '.') }}</dd>
                    </div>
                    <div class="invoice-detail-item">
                        <dt>Metode Pembayaran</dt>
                        <dd>{{ $paymentMethod }}</dd>
                    </div>
                    <div class="invoice-detail-item">
                        <dt>Status Transaksi</dt>
                        <dd>{{ $trx->statusLabel() }}</dd>
                    </div>
                    <div class="invoice-detail-item invoice-time">
                        <dt>Waktu Transaksi</dt>
                        <dd>{{ $trx->created_at?->timezone('Asia/Jakarta')->format('d/m/Y, H:i') ?? '-' }} WIB</dd>
                    </div>
                </dl>
            </article>
        @else
            <div class="card invoice-not-found p-5 text-sm">
                <span class="invoice-heading-icon" aria-hidden="true">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="m20 20-4-4M8 11h6"/></svg>
                </span>
                <div><strong>Invoice tidak ditemukan</strong><p class="muted mt-1">Periksa kembali kode invoice yang dimasukkan.</p></div>
            </div>
        @endif
    @endif
</div>

<style>
    .invoice-search-form { display:grid; grid-template-columns:minmax(0,1fr) auto; gap:.75rem; }
    .invoice-search-form input { min-width:0; outline:none; transition:border-color .2s, box-shadow .2s; }
    .invoice-search-form input:focus { border-color:var(--primary); box-shadow:0 0 0 3px rgba(249,115,22,.14); }
    .invoice-heading-icon { display:inline-flex; width:44px; height:44px; flex:none; align-items:center; justify-content:center; border-radius:12px; background:rgba(249,115,22,.12); color:#fb923c; }
    .invoice-result { padding:1.25rem; }
    .invoice-result-header { display:flex; align-items:flex-start; justify-content:space-between; gap:1rem; padding-bottom:1.15rem; border-bottom:1px solid #2d2d33; }
    .invoice-status-pill { display:inline-flex; flex:none; align-items:center; justify-content:center; padding:.5rem .8rem; border-radius:999px; font-size:.75rem; font-weight:800; white-space:nowrap; }
    .invoice-detail-grid { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:.75rem; margin-top:1rem; }
    .invoice-detail-item { min-width:0; padding:1rem; border:1px solid #2b2b31; border-radius:11px; background:#131317; }
    .invoice-detail-item dt { color:#a1a1aa; font-size:.75rem; font-weight:600; }
    .invoice-detail-item dd { margin-top:.3rem; font-size:.95rem; font-weight:750; overflow-wrap:anywhere; }
    .invoice-detail-item small { display:block; margin-top:.25rem; color:#a1a1aa; font-size:.72rem; }
    .invoice-detail-item .invoice-total { color:#fb923c; font-size:1.05rem; }
    .invoice-time { grid-column:1/-1; }
    .invoice-not-found { display:flex; align-items:center; gap:.9rem; }
    html[data-theme="light"] .invoice-result-header { border-color:#e7e5e4; }
    html[data-theme="light"] .invoice-detail-item { border-color:#e7e5e4; background:#fafaf9; }
    @media (max-width:640px) {
        .invoice-search-form { grid-template-columns:1fr; }
        .invoice-result-header { flex-direction:column; }
        .invoice-detail-grid { grid-template-columns:1fr; }
        .invoice-time { grid-column:auto; }
        .invoice-status-pill { align-self:flex-start; }
    }
</style>
@endsection
