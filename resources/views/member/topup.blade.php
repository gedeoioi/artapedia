@extends('layouts.shop')

@section('title', 'Topup Saldo')

@section('content')
@php
    $selectedGateway = old('gateway_code');
    $selectedIPaymuMethod = $selectedGateway === 'ipaymu' ? old('ipaymu_method') : null;
    $selectedIPaymuChannel = $selectedGateway === 'ipaymu' ? old('ipaymu_channel') : null;
    if (!isset($ipaymuChannels[$selectedIPaymuMethod]['channels'][$selectedIPaymuChannel])) {
        $selectedIPaymuMethod = null;
        $selectedIPaymuChannel = null;
    }
    $initialAmount = max(10000, min(10000000, (int) old('amount', 50000)));
@endphp

<div class="grid md:grid-cols-2 gap-4 max-w-5xl mx-auto">
    <div class="card p-5">
        <h1 class="font-bold text-lg mb-4">Topup Saldo</h1>
        <form method="POST" action="{{ route('topup.store') }}" id="topup-form">
            @csrf
            <label for="topup-amount" class="text-sm font-semibold">Nominal Topup</label>
            <div class="text-xs muted mt-0.5 mb-2">Minimal Rp10.000 dan maksimal Rp10.000.000.</div>
            <input id="topup-amount" name="amount" type="number" min="10000" max="10000000" step="1000" value="{{ $initialAmount }}" required class="card w-full px-3 py-2 mb-1">
            @error('amount') <p class="text-sm text-red-500 mb-2">{{ $message }}</p> @enderror

            @if(auth()->user()->phone)
                <div class="text-sm muted my-3">Pembayaran menggunakan nomor akun <b>{{ auth()->user()->phone }}</b>. <a href="{{ route('profile.edit') }}" class="underline">Ubah</a></div>
            @else
                <div class="card p-3 text-sm text-red-500 my-3">Nomor HP akun belum tersedia. <a href="{{ route('profile.edit') }}" class="underline font-semibold">Lengkapi profil</a> sebelum melakukan topup.</div>
            @endif

            <div class="font-semibold mb-2">Pilih Pembayaran</div>
            <div class="grid grid-cols-2 gap-2 mb-3" id="topup-gateways">
                @foreach($gateways as $gateway)
                    <label class="pay-card card p-3 flex items-center gap-2 cursor-pointer" data-pay="{{ $gateway->code }}" data-pay-name="{{ $gateway->name }}">
                        <input type="radio" name="gateway_code" value="{{ $gateway->code }}" class="hidden" @checked($selectedGateway === $gateway->code)>
                        <span class="pay-logo">{{ mb_strtoupper(mb_substr($gateway->name, 0, 1)) }}</span>
                        <span class="min-w-0">
                            <span class="block text-sm font-semibold truncate">{{ $gateway->name }}</span>
                            <span class="block text-xs muted">{{ $gateway->code === 'ipaymu' ? collect($ipaymuChannels)->pluck('label')->join(' / ') : 'Pembayaran otomatis' }}</span>
                        </span>
                    </label>
                @endforeach
            </div>
            @error('gateway_code') <p class="text-sm text-red-500 mb-2">{{ $message }}</p> @enderror

            @if($ipaymuChannels !== [])
                <div id="topup-ipaymu-panel" class="ipaymu-channel-panel hidden mb-3">
                    <input type="hidden" name="ipaymu_method" id="topup-ipaymu-method" value="{{ $selectedIPaymuMethod }}">
                    <div class="ipaymu-panel-heading">
                        <small>Minimal pembayaran Rp10.000</small>
                        <span class="ipaymu-secure">Pembayaran aman</span>
                    </div>
                    @foreach($ipaymuChannels as $methodCode => $method)
                        <details class="ipaymu-method" @if($methodCode === $selectedIPaymuMethod) open @endif>
                            <summary><span>{{ $method['label'] }}</span><span class="ipaymu-chevron" aria-hidden="true">⌄</span></summary>
                            <div class="ipaymu-channel-grid">
                                @foreach($method['channels'] as $channelCode => $channelName)
                                    @php $channelSelected = $selectedIPaymuMethod === $methodCode && $selectedIPaymuChannel === $channelCode; @endphp
                                    <label class="ipaymu-channel-card {{ $channelSelected ? 'is-selected' : '' }}">
                                        <input type="radio" name="ipaymu_channel" value="{{ $channelCode }}" data-method="{{ $methodCode }}" data-channel-name="{{ $channelName }}" class="topup-ipaymu-channel hidden" @checked($channelSelected)>
                                        <span class="ipaymu-channel-logo brand-{{ $channelCode }}">{{ $channelName }}</span>
                                        <small>Diproses otomatis</small>
                                    </label>
                                @endforeach
                            </div>
                        </details>
                    @endforeach
                </div>
            @endif
            @error('ipaymu_method') <p class="text-sm text-red-500 mb-2">{{ $message }}</p> @enderror
            @error('ipaymu_channel') <p class="text-sm text-red-500 mb-2">{{ $message }}</p> @enderror
            <div id="topup-warning" class="hidden card p-3 text-sm" style="border-color:#ef4444;color:#fca5a5;background:rgba(239,68,68,.08)" role="alert"></div>
        </form>
    </div>

    <aside class="topup-summary card p-5 h-fit" aria-label="Ringkasan topup">
        <h2 class="font-bold text-lg mb-4">Ringkasan Pembayaran</h2>
        <div class="summary-rows">
            <div><span>Metode Pembayaran</span><strong id="topup-summary-method">Pilih metode pembayaran</strong></div>
            <div><span>Nominal Topup</span><strong id="topup-summary-amount">Rp {{ number_format($initialAmount, 0, ',', '.') }}</strong></div>
            <div><span>Biaya layanan</span><strong id="topup-summary-fee">Rp 0</strong></div>
        </div>
        <div class="summary-total"><span>Total Pembayaran</span><strong id="topup-summary-total">Rp {{ number_format($initialAmount, 0, ',', '.') }}</strong></div>
        <button type="submit" form="topup-form" class="btn-primary w-full py-3 font-bold mt-4" id="topup-submit" disabled>Buat Pembayaran</button>
    </aside>
</div>

<style>
    .pay-card { border-width: 1.5px; }
    .pay-card.pay-active { border-color: #f97316; background: rgba(249,115,22,.07); }
    .pay-logo { width: 2.25rem; height: 2.25rem; border-radius: 10px; display: flex; align-items: center; justify-content: center; flex: 0 0 auto; background: #26262b; color: #fdba74; font-weight: 800; }
    .ipaymu-channel-panel { overflow: hidden; border: 1px solid #3f3f46; border-radius: 12px; background: #18181c; }
    .ipaymu-panel-heading { display: flex; align-items: center; justify-content: space-between; gap: 1rem; padding: .85rem 1rem; background: #29292f; }
    .ipaymu-panel-heading small { color: #a1a1aa; font-size: .7rem; }
    .ipaymu-secure { border-radius: 999px; padding: .3rem .55rem; background: rgba(249,115,22,.12); color: #fdba74; font-size: .68rem; font-weight: 700; white-space: nowrap; }
    .ipaymu-method { border-top: 1px solid #35353b; }
    .ipaymu-method summary { display: flex; cursor: pointer; list-style: none; align-items: center; justify-content: space-between; padding: .8rem 1rem; font-size: .82rem; font-weight: 800; }
    .ipaymu-method summary::-webkit-details-marker { display: none; }
    .ipaymu-method[open] .ipaymu-chevron { transform: rotate(180deg); }
    .ipaymu-chevron { transition: transform .15s ease; }
    .ipaymu-channel-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: .65rem; padding: 0 .75rem .8rem; }
    .ipaymu-channel-card { display: flex; min-width: 0; cursor: pointer; flex-direction: column; gap: .4rem; border: 1px solid #45454d; border-radius: 10px; padding: .65rem; background: #303036; transition: .15s ease; }
    .ipaymu-channel-card:hover { border-color: #71717a; transform: translateY(-1px); }
    .ipaymu-channel-card.is-selected { border-color: #f97316; box-shadow: 0 0 0 1px rgba(249,115,22,.35); background: rgba(249,115,22,.08); }
    .ipaymu-channel-logo { display: flex; min-height: 34px; align-items: center; overflow: hidden; border-radius: 6px; padding: .35rem .5rem; background: #fff; color: #18181b; font-size: .76rem; font-weight: 900; text-overflow: ellipsis; white-space: nowrap; }
    .ipaymu-channel-card small { padding-top: .35rem; border-top: 1px dashed #57575f; color: #a1a1aa; font-size: .62rem; font-style: italic; }
    .brand-bca { color:#07549b; } .brand-bni { color:#e66018; } .brand-mandiri { color:#173d73; }
    .brand-bri { color:#075ca8; } .brand-bsi { color:#159688; } .brand-cimb { color:#a71930; }
    .brand-dana { color:#158bd2; } .brand-shopeepay { color:#ee4d2d; } .brand-permata { color:#168270; }
    .topup-summary { border-style: dashed; }
    .summary-rows { display: grid; gap: .85rem; font-size: .875rem; }
    .summary-rows > div, .summary-total { display: flex; align-items: center; justify-content: space-between; gap: 1rem; }
    .summary-rows span { color: #d4d4d8; }
    .summary-rows strong { max-width: 55%; text-align: right; }
    .summary-total { margin-top: 1rem; padding-top: 1rem; border-top: 1px solid #4b4b52; font-size: 1.05rem; font-weight: 800; }
    .summary-total strong { color: #fdba74; font-size: 1.15rem; text-align: right; }
    html[data-theme="light"] .pay-logo { background: #fff7ed; color: #c2570c; }
    html[data-theme="light"] .ipaymu-channel-panel { border-color: #d6d3d1; background: #fff; }
    html[data-theme="light"] .ipaymu-panel-heading { background: #f5f5f4; }
    html[data-theme="light"] .ipaymu-method { border-color: #e7e5e4; }
    html[data-theme="light"] .ipaymu-channel-card { border-color: #d6d3d1; background: #fafaf9; color: #1c1917; }
    html[data-theme="light"] .ipaymu-channel-card.is-selected { border-color: #f97316; background: #fff7ed; }
    html[data-theme="light"] .summary-rows span { color: #57534e; }
    html[data-theme="light"] .summary-total { border-color: #d6d3d1; }
    html[data-theme="light"] .summary-total strong { color: #c2570c; }
    @media (max-width: 420px) { .ipaymu-channel-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
</style>
@endsection

@section('scripts')
<script>
const topupQuoteUrl = @json(route('topup.quote'));
const topupToken = document.querySelector('meta[name=csrf-token]').content;
const topupAmount = document.getElementById('topup-amount');
const topupSubmit = document.getElementById('topup-submit');
const topupWarning = document.getElementById('topup-warning');
const accountCanTopup = @js((bool) auth()->user()->phone);
const formatRupiah = value => 'Rp ' + Number(value || 0).toLocaleString('id-ID');

const scrollToTopupSummary = () => {
    const summary = document.querySelector('.topup-summary');
    if (!summary) return;
    const target = Math.max(0, summary.getBoundingClientRect().top + window.scrollY - 110);
    window.scrollTo({ top: target, behavior: 'smooth' });
};

async function refreshTopupQuote() {
    const selectedGateway = document.querySelector('input[name=gateway_code]:checked');
    const gatewayCode = selectedGateway?.value;
    const activePayment = gatewayCode ? document.querySelector(`.pay-card[data-pay="${CSS.escape(gatewayCode)}"]`) : null;
    const selectedChannel = document.querySelector('.topup-ipaymu-channel:checked');
    const amount = Math.max(0, Number(topupAmount.value) || 0);
    document.querySelectorAll('.pay-card').forEach(card => card.classList.toggle('pay-active', card.dataset.pay === gatewayCode));
    document.getElementById('topup-ipaymu-panel')?.classList.toggle('hidden', gatewayCode !== 'ipaymu');
    document.getElementById('topup-summary-method').textContent = gatewayCode === 'ipaymu' && selectedChannel
        ? `${activePayment?.dataset.payName || 'iPaymu'} • ${selectedChannel.dataset.channelName}`
        : (activePayment?.dataset.payName || 'Pilih metode pembayaran');
    document.getElementById('topup-summary-amount').textContent = formatRupiah(amount);

    if (!gatewayCode || amount < 10000 || amount > 10000000 || (gatewayCode === 'ipaymu' && !selectedChannel)) {
        document.getElementById('topup-summary-fee').textContent = formatRupiah(0);
        document.getElementById('topup-summary-total').textContent = formatRupiah(amount);
        topupWarning.textContent = gatewayCode === 'ipaymu' && !selectedChannel ? 'Pilih channel pembayaran terlebih dahulu.' : '';
        topupWarning.classList.toggle('hidden', !topupWarning.textContent);
        topupSubmit.disabled = true;
        topupSubmit.style.opacity = '.55';
        topupSubmit.style.cursor = 'not-allowed';
        return false;
    }

    const response = await fetch(topupQuoteUrl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': topupToken, 'Accept': 'application/json' },
        body: JSON.stringify({ amount, gateway_code: gatewayCode, ipaymu_method: document.getElementById('topup-ipaymu-method')?.value, ipaymu_channel: selectedChannel?.value }),
    });
    const quote = await response.json();
    if (!response.ok) {
        topupWarning.textContent = quote.message || 'Perhitungan pembayaran gagal dimuat.';
        topupWarning.classList.remove('hidden');
        topupSubmit.disabled = true;
        return false;
    }

    document.getElementById('topup-summary-fee').textContent = formatRupiah(quote.gateway_fee);
    document.getElementById('topup-summary-total').textContent = formatRupiah(quote.total);
    topupWarning.textContent = quote.message || '';
    topupWarning.classList.toggle('hidden', quote.available !== false);
    topupSubmit.disabled = !accountCanTopup || quote.available === false;
    topupSubmit.style.opacity = topupSubmit.disabled ? '.55' : '';
    topupSubmit.style.cursor = topupSubmit.disabled ? 'not-allowed' : '';
    return !topupSubmit.disabled;
}

document.querySelectorAll('input[name=gateway_code]').forEach(input => input.addEventListener('change', async event => {
    await refreshTopupQuote();
    if (event.currentTarget.value !== 'ipaymu') scrollToTopupSummary();
}));
document.querySelectorAll('.topup-ipaymu-channel').forEach(input => input.addEventListener('change', async event => {
    document.getElementById('topup-ipaymu-method').value = event.currentTarget.dataset.method;
    document.querySelectorAll('.ipaymu-channel-card').forEach(card => card.classList.toggle('is-selected', card.contains(event.currentTarget)));
    await refreshTopupQuote();
    scrollToTopupSummary();
}));
let topupQuoteTimer;
topupAmount.addEventListener('input', () => {
    clearTimeout(topupQuoteTimer);
    topupQuoteTimer = setTimeout(refreshTopupQuote, 250);
});
refreshTopupQuote();
</script>
@endsection
