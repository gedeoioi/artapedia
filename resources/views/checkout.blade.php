@extends('layouts.shop')

@section('title', 'Checkout '.$product->name)
@section('meta_description', 'Beli '.$product->name.' ('.$product->game.') di ArtaPedia.')

@section('content')
<script type="application/ld+json">
{!! json_encode(['@context' => 'https://schema.org', '@type' => 'Product', 'name' => $product->name, 'category' => $product->game, 'offers' => ['@type' => 'Offer', 'priceCurrency' => 'IDR', 'price' => $product->price_guest, 'availability' => $product->in_stock ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock']]) !!}
</script>

<div class="grid md:grid-cols-2 gap-4">
    <div class="card p-5">
        <div class="flex items-center gap-3 mb-4">
            @php $pIcon = $product->iconUrl(); @endphp
            @if($pIcon)
                <img src="{{ $pIcon }}" class="w-12 h-12" style="border-radius:12px" alt="{{ $product->game }}">
            @endif
            <div>
                <h1 class="font-bold text-lg leading-tight">{{ $product->name }}</h1>
                <div class="text-sm text-gray-500">{{ $product->game }} &bull; {{ $product->typeLabel() }}</div>
            </div>
        </div>
        <form method="POST" action="{{ route('checkout.store') }}" id="checkout-form">
            @csrf
            <input type="hidden" name="product_id" value="{{ $product->id }}">
            @php $isGame = $product->product_type === 'game'; @endphp

            <div class="step-heading"><span>Masukkan {{ $product->targetLabel() }}</span></div>
            <label class="text-sm font-semibold sr-only">{{ $product->targetLabel() }}</label>
            <input name="target_user_id" id="target-uid" required class="card w-full px-3 py-2 mt-1 mb-2"
                placeholder="{{ $isGame ? 'cth: 12345678' : 'cth: 081234567890' }}" autocomplete="off"
                @unless($isGame) inputmode="tel" @endunless>
            @if($isGame)
            <label class="text-sm font-semibold">Server / Zone
                @if($nicknameRequiresZone)
                    <span class="text-red-400">*</span>
                @else
                    <span class="muted font-normal">(opsional)</span>
                @endif
            </label>
            <input name="target_zone" id="zone" class="card w-full px-3 py-2 mt-1 mb-2" placeholder="cth: 1234" autocomplete="off"
                @if($nicknameRequiresZone) required aria-describedby="zone-note" @endif>
            @if($nicknameRequiresZone)
            <div id="zone-note" class="muted text-xs mb-2">Zone wajib diisi agar nickname bisa dicek otomatis.</div>
            @endif
            @if($nicknameSupported)
            <div class="card p-3 mb-3 flex items-center gap-3" id="nick-box" style="border-style:dashed">
                <button type="button" id="btn-nick" class="btn-primary px-4 py-2 text-sm whitespace-nowrap shrink-0">
                    <span id="btn-nick-label">Cek Nickname</span>
                </button>
                <div class="min-w-0 flex-1">
                    <div id="nick-hint" class="text-xs muted">Nickname terisi otomatis setelah User ID{{ $nicknameRequiresZone ? ' dan Zone' : '' }} diisi.</div>
                    <div id="nick-result" class="text-sm font-semibold hidden"></div>
                </div>
                <div id="nick-spinner" class="hidden shrink-0" aria-hidden="true">
                    <svg class="animate-spin" width="20" height="20" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" opacity=".25"/><path d="M12 2a10 10 0 0 1 10 10" stroke="currentColor" stroke-width="3" stroke-linecap="round"/></svg>
                </div>
            </div>
            @endif
            <input type="hidden" name="nickname" id="nickname">
            @endif
            <div class="step-heading"><span>Isi Kontak / WhatsApp</span></div>
            <div class="grid grid-cols-2 gap-2 mb-2">
                <div><label class="text-sm">No. HP @guest<span class="text-red-400">*</span>@endguest</label><input name="buyer_phone" value="{{ old('buyer_phone', auth()->user()?->phone) }}" type="tel" autocomplete="tel" class="card w-full px-3 py-2 mt-1" @guest required @endguest></div>
                <div><label class="text-sm">Email @guest<span class="text-red-400">*</span>@endguest</label><input name="buyer_email" value="{{ old('buyer_email', auth()->user()?->email) }}" type="email" autocomplete="email" class="card w-full px-3 py-2 mt-1" @guest required @endguest></div>
            </div>
            <div class="step-heading"><span>Pilih Nominal</span></div>
            <div class="quantity-section mb-4 mt-3">
                <div>
                    <label for="order-quantity" class="text-sm font-semibold">Jumlah Pesanan</label>
                    <div class="text-xs muted mt-0.5">{{ $maxQuantity > 1 ? 'Maksimal '.$maxQuantity.' item dalam satu transaksi.' : 'Produk ini hanya mendukung satu item per transaksi.' }}</div>
                </div>
                <div class="quantity-stepper" data-max="{{ $maxQuantity }}">
                    <button type="button" class="quantity-button" data-quantity-action="minus" aria-label="Kurangi jumlah">&minus;</button>
                    <input id="order-quantity" name="quantity" type="number" min="1" max="{{ $maxQuantity }}" value="{{ old('quantity', 1) }}" inputmode="numeric" readonly aria-label="Jumlah pesanan">
                    <button type="button" class="quantity-button" data-quantity-action="plus" aria-label="Tambah jumlah" @disabled($maxQuantity === 1)>+</button>
                </div>
            </div>
            <div class="step-heading"><span>Pilih Pembayaran</span></div>
            <div class="grid grid-cols-2 gap-2 mt-1 mb-3" id="gateways">
                @auth
                <label class="pay-card card p-3 flex items-center gap-2 cursor-pointer" data-pay="balance" data-pay-name="Saldo Member">
                    <input type="radio" name="gateway_code" value="balance" class="hidden" @checked(old('gateway_code') === 'balance')>
                    <span class="pay-logo">W</span>
                    <span class="min-w-0">
                        <span class="block text-sm font-semibold truncate">Saldo Member</span>
                        <span class="block text-xs muted">Rp {{ number_format(auth()->user()->balance, 0, ',', '.') }}</span>
                    </span>
                </label>
                @endauth
                @foreach($gateways as $gw)
                    <label class="pay-card card p-3 flex items-center gap-2 cursor-pointer" data-pay="{{ $gw->code }}" data-pay-name="{{ $gw->name }}">
                        <input type="radio" name="gateway_code" value="{{ $gw->code }}" class="hidden" @checked(old('gateway_code') === $gw->code)>
                        <span class="pay-logo">{{ mb_strtoupper(mb_substr($gw->name, 0, 1)) }}</span>
                        <span class="min-w-0">
                            <span class="block text-sm font-semibold truncate">{{ $gw->name }}</span>
                            <span class="block text-xs muted">{{ $gw->code === 'ipaymu' ? 'Min. Rp 10.000 • '.collect($ipaymuChannels)->pluck('label')->join(' / ') : 'QRIS / VA / E-Wallet' }}</span>
                        </span>
                    </label>
                @endforeach
            </div>
            @php
                $selectedIPaymuMethod = old('gateway_code') === 'ipaymu' ? old('ipaymu_method') : null;
                $selectedIPaymuChannel = old('gateway_code') === 'ipaymu' ? old('ipaymu_channel') : null;
                if (!isset($ipaymuChannels[$selectedIPaymuMethod]['channels'][$selectedIPaymuChannel])) {
                    $selectedIPaymuMethod = null;
                    $selectedIPaymuChannel = null;
                }
            @endphp
            @if($ipaymuChannels !== [])
            <div id="ipaymu-channel-panel" class="ipaymu-channel-panel hidden mb-3">
                <input type="hidden" name="ipaymu_method" id="ipaymu-method" value="{{ $selectedIPaymuMethod }}">
                <div class="ipaymu-panel-heading">
                    <span>
                        <small>Minimal pembayaran Rp10.000</small>
                    </span>
                    <span class="ipaymu-secure">Pembayaran aman</span>
                </div>
                @foreach($ipaymuChannels as $methodCode => $method)
                    <details class="ipaymu-method" @if($methodCode === $selectedIPaymuMethod) open @endif>
                        <summary>
                            <span>{{ $method['label'] }}</span>
                            <span class="ipaymu-chevron" aria-hidden="true">⌄</span>
                        </summary>
                        <div class="ipaymu-channel-grid">
                            @foreach($method['channels'] as $channelCode => $channelName)
                                @php $channelSelected = $selectedIPaymuMethod === $methodCode && $selectedIPaymuChannel === $channelCode; @endphp
                                <label class="ipaymu-channel-card {{ $channelSelected ? 'is-selected' : '' }}">
                                    <input type="radio" name="ipaymu_channel" value="{{ $channelCode }}" data-method="{{ $methodCode }}" data-channel-name="{{ $channelName }}" class="ipaymu-channel-input hidden" @checked($channelSelected)>
                                    <span class="ipaymu-channel-logo brand-{{ $channelCode }}">{{ $channelName }}</span>
                                    <small>Diproses otomatis</small>
                                </label>
                            @endforeach
                        </div>
                    </details>
                @endforeach
            </div>
            @endif
            @if($tripayChannels !== [])
            <div id="tripay-channel-panel" class="ipaymu-channel-panel hidden mb-3">
                <div class="ipaymu-panel-heading">
                    <span>
                        <strong>Pilih channel Tripay</strong>
                        <small>Minimal pembayaran Rp10.000</small>
                    </span>
                    <span class="ipaymu-secure">Pembayaran aman</span>
                </div>
                <div class="ipaymu-channel-grid">
                    @foreach($tripayChannels as $channel)
                        @php $tripaySelected = old('gateway_code') === 'tripay' && old('tripay_channel') === $channel['code']; @endphp
                        <label class="ipaymu-channel-card {{ $tripaySelected ? 'is-selected' : '' }}">
                            <input type="radio" name="tripay_channel" value="{{ $channel['code'] }}"
                                   data-channel-name="{{ $channel['label'] }}"
                                   class="tripay-channel-input hidden" @checked($tripaySelected)>
                            <span class="ipaymu-channel-logo">{{ $channel['label'] }}</span>
                            <small>{{ $channel['group_label'] }}</small>
                        </label>
                    @endforeach
                </div>
            </div>
            @endif
            <div id="gateway-warning" class="hidden card p-3 mb-3 text-sm" style="border-color:#ef4444;color:#fca5a5;background:rgba(239,68,68,.08)" role="alert"></div>
            <style>
                .pay-card { border-width: 1.5px; }
                .pay-card.pay-active { border-color: #f97316; background: rgba(249,115,22,.07); }
                .pay-logo { width: 2.25rem; height: 2.25rem; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-weight: 800; background: #26262b; color: #fdba74; flex-shrink: 0; }
                .quantity-section { display: flex; align-items: center; justify-content: space-between; gap: 1rem; }
                .quantity-stepper { display: grid; grid-template-columns: 42px minmax(48px, 58px) 42px; flex: 0 0 auto; overflow: hidden; border: 1px solid #393941; border-radius: 11px; background: #111115; }
                .quantity-button { display: flex; min-height: 42px; align-items: center; justify-content: center; color: #fdba74; font-size: 1.15rem; font-weight: 800; transition: .15s ease; }
                .quantity-button:hover:not(:disabled) { background: rgba(249,115,22,.12); }
                .quantity-button:disabled { cursor: not-allowed; color: #52525b; }
                .quantity-stepper input { width: 100%; border: 0; border-right: 1px solid #393941; border-left: 1px solid #393941; background: transparent; color: #fff; text-align: center; font-weight: 800; appearance: textfield; }
                .quantity-stepper input::-webkit-inner-spin-button, .quantity-stepper input::-webkit-outer-spin-button { margin: 0; appearance: none; }
                .ipaymu-channel-panel { overflow: hidden; border: 1px solid #3f3f46; border-radius: 12px; background: #18181c; }
                .ipaymu-panel-heading { display: flex; align-items: center; justify-content: space-between; gap: 1rem; padding: .85rem 1rem; background: #29292f; }
                .ipaymu-panel-heading strong, .ipaymu-panel-heading small { display: block; }
                .ipaymu-panel-heading small { margin-top: .15rem; color: #a1a1aa; font-size: .7rem; }
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
                html[data-theme="light"] .pay-logo { background: #fff7ed; color: #c2570c; }
                html[data-theme="light"] .quantity-stepper { border-color: #d6d3d1; background: #fff; }
                html[data-theme="light"] .quantity-stepper input { border-color: #d6d3d1; color: #1c1917; }
                html[data-theme="light"] .ipaymu-channel-panel { border-color: #d6d3d1; background: #fff; }
                html[data-theme="light"] .ipaymu-panel-heading { background: #f5f5f4; }
                html[data-theme="light"] .ipaymu-method { border-color: #e7e5e4; }
                html[data-theme="light"] .ipaymu-channel-card { border-color: #d6d3d1; background: #fafaf9; color: #1c1917; }
                html[data-theme="light"] .ipaymu-channel-card.is-selected { border-color: #f97316; background: #fff7ed; }
                @media (max-width: 420px) {
                    .quantity-section { align-items: stretch; flex-direction: column; }
                    .quantity-stepper { width: 100%; grid-template-columns: 1fr 1.15fr 1fr; }
                    .ipaymu-channel-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
                }
            </style>
        </form>
    </div>
    @php
        $summaryIcon = $product->iconUrl();
        $initialPrice = auth()->check() ? auth()->user()->priceFor($product) : $product->price_guest;
        $initialPaymentName = 'Pilih metode pembayaran';
        $initialQuantity = max(1, min($maxQuantity, (int) old('quantity', 1)));
    @endphp
    <aside class="order-summary h-fit" aria-label="Ringkasan pesanan">
        <div class="flex items-center gap-3 mb-5">
            @if($summaryIcon)
                <img src="{{ $summaryIcon }}" class="order-summary-icon" alt="{{ $product->game }}">
            @else
                <div class="order-summary-icon flex items-center justify-center font-extrabold text-orange-400">{{ mb_strtoupper(mb_substr($product->game, 0, 1)) }}</div>
            @endif
            <div class="min-w-0">
                <div class="font-bold truncate">{{ $product->game }}</div>
                <div class="text-sm mt-1 truncate">{{ $product->name }}</div>
            </div>
        </div>

        <div class="order-summary-rows">
            <div><span>Metode Pembayaran</span><strong id="sum-method">{{ $initialPaymentName }}</strong></div>
            <div><span>Harga</span><strong id="sum-sell">Rp {{ number_format($initialPrice, 0, ',', '.') }}</strong></div>
            <div><span>Jumlah Pembelian</span><strong id="sum-quantity">{{ $initialQuantity }}</strong></div>
            <div><span>Biaya layanan</span><strong id="sum-service">Rp 0</strong></div>
        </div>

        <div class="order-summary-total">
            <span>Total Pembayaran</span>
            <strong id="sum-total">Rp {{ number_format($initialPrice * $initialQuantity, 0, ',', '.') }}</strong>
        </div>
        <div class="step-heading mt-4"><span>Konfirmasi &amp; Bayar</span></div>
        <button type="submit" form="checkout-form" class="btn-primary w-full py-3 font-bold mt-2" id="btn-bayar" disabled>Bayar Sekarang</button>
        <p class="muted text-xs mt-2">Total di atas dihitung ulang di server sebelum transaksi dibuat.</p>
    </aside>

    {{-- Sticky bar khusus mobile: ringkasan harga tetap terlihat saat scroll. --}}
    <div class="sticky-checkout-bar md:hidden" id="sticky-bar" aria-hidden="true">
        <div class="min-w-0">
            <div class="text-xs muted" id="sticky-method">Pilih metode pembayaran</div>
            <div class="font-extrabold" id="sticky-total">Rp {{ number_format($initialPrice * $initialQuantity, 0, ',', '.') }}</div>
        </div>
        <button type="submit" form="checkout-form" class="btn-primary px-5 py-2.5 text-sm font-bold whitespace-nowrap" id="btn-bayar-mobile" disabled>Bayar</button>
    </div>
    <style>
        .order-summary { border: 1px dashed #4b4b52; border-radius: 13px; padding: 1.25rem; background: linear-gradient(145deg, #202024, #1a1a1e); box-shadow: 0 18px 50px rgba(0,0,0,.2); }
        .order-summary-icon { width: 64px; height: 64px; flex: 0 0 64px; border-radius: 9px; object-fit: cover; background: #29292f; }
        .order-summary-rows { display: grid; gap: .85rem; font-size: .875rem; }
        .order-summary-rows > div { display: flex; align-items: center; justify-content: space-between; gap: 1rem; }
        .order-summary-rows span { color: #d4d4d8; }
        .order-summary-rows strong { max-width: 55%; text-align: right; }
        .order-summary-total { display: flex; align-items: center; justify-content: space-between; gap: 1rem; margin-top: 1rem; padding-top: 1rem; border-top: 1px solid #4b4b52; font-size: 1.05rem; font-weight: 800; }
        .order-summary-total strong { color: #fdba74; font-size: 1.15rem; text-align: right; }
        html[data-theme="light"] .order-summary { border-color: #a8a29e; background: linear-gradient(145deg, #fff, #fafaf9); box-shadow: 0 15px 40px rgba(28,25,23,.07); }
        html[data-theme="light"] .order-summary-rows span { color: #57534e; }
        html[data-theme="light"] .order-summary-total { border-color: #d6d3d1; }
        html[data-theme="light"] .order-summary-total strong { color: #c2570c; }
        .step-heading { margin: 1.1rem 0 .35rem; font-weight: 800; font-size: .95rem; }
        .step-heading:first-child { margin-top: 0; }
        .sticky-checkout-bar { position: fixed; inset-inline: 0; bottom: 0; z-index: 40; display: flex; align-items: center; justify-content: space-between; gap: .75rem; border-top: 1px solid #3f3f46; padding: .7rem 1rem calc(.7rem + env(safe-area-inset-bottom)); background: #17171c; box-shadow: 0 -10px 30px rgba(0,0,0,.35); }
        html[data-theme="light"] .sticky-checkout-bar { border-color: #d6d3d1; background: #fff; box-shadow: 0 -10px 30px rgba(28,25,23,.08); }
    </style>
</div>
@endsection

@section('scripts')
<script>
const quoteUrl = "{{ route('checkout.quote') }}";
const nickUrl = "{{ route('checkout.nickname') }}";
const productId = {{ $product->id }};
const isGame = @js($product->product_type === 'game');
const token = document.querySelector('meta[name=csrf-token]').content;
const quantityInput = document.getElementById('order-quantity');
const maxQuantity = Number(quantityInput.max || 1);
const currentQuantity = () => Math.max(1, Math.min(maxQuantity, Number(quantityInput.value) || 1));
const scrollToOrderSummary = () => {
    const summary = document.querySelector('.order-summary');
    if (!summary) return;

    const start = window.scrollY;
    const target = Math.max(0, summary.getBoundingClientRect().top + start - 110);
    const distance = target - start;
    const duration = 550;

    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
        window.scrollTo(0, target);
        return;
    }

    const startedAt = performance.now();
    const animateScroll = now => {
        const progress = Math.min(1, (now - startedAt) / duration);
        const eased = progress < .5
            ? 4 * progress * progress * progress
            : 1 - Math.pow(-2 * progress + 2, 3) / 2;
        window.scrollTo(0, start + (distance * eased));
        if (progress < 1) requestAnimationFrame(animateScroll);
    };
    requestAnimationFrame(animateScroll);
};
async function refreshQuote() {
    const selectedGateway = document.querySelector('input[name=gateway_code]:checked');
    const gw = selectedGateway?.value;
    document.querySelectorAll('.pay-card').forEach(c => c.classList.toggle('pay-active', c.dataset.pay === gw));
    const activePayment = gw ? document.querySelector(`.pay-card[data-pay="${CSS.escape(gw)}"]`) : null;
    const selectedChannel = document.querySelector('.ipaymu-channel-input:checked');
    const selectedTripayChannel = document.querySelector('.tripay-channel-input:checked');
    const warning = document.getElementById('gateway-warning');
    const needsChannel = (gw === 'ipaymu' && !selectedChannel) || (gw === 'tripay' && !selectedTripayChannel);

    const methodName = gw === 'ipaymu' && selectedChannel
        ? `${activePayment?.dataset.payName || 'iPaymu'} • ${selectedChannel.dataset.channelName}`
        : (gw === 'tripay' && selectedTripayChannel
            ? `Tripay • ${selectedTripayChannel.dataset.channelName}`
            : (activePayment?.dataset.payName || 'Pilih metode pembayaran'));

    document.getElementById('sum-method').textContent = methodName;
    const stickyMethod = document.getElementById('sticky-method');
    if (stickyMethod) stickyMethod.textContent = methodName;

    document.getElementById('ipaymu-channel-panel')?.classList.toggle('hidden', gw !== 'ipaymu');
    document.getElementById('tripay-channel-panel')?.classList.toggle('hidden', gw !== 'tripay');

    if (!gw || needsChannel) {
        warning.textContent = needsChannel ? 'Pilih channel pembayaran terlebih dahulu.' : '';
        warning.classList.toggle('hidden', !needsChannel);
        setPayDisabled(true);
        return;
    }
    const quantity = currentQuantity();
    const r = await fetch(quoteUrl, {method: 'POST', headers: {'Content-Type': 'application/json', 'X-CSRF-TOKEN': token}, body: JSON.stringify({
        product_id: productId,
        gateway_code: gw,
        quantity,
        ipaymu_method: document.getElementById('ipaymu-method')?.value,
        ipaymu_channel: selectedChannel?.value,
        tripay_channel: selectedTripayChannel?.value,
    })});
    const j = await r.json();
    if (!r.ok) {
        warning.textContent = j.message || 'Perhitungan pembayaran gagal dimuat.';
        warning.classList.remove('hidden');
        setPayDisabled(true);
        return;
    }
    const f = n => 'Rp ' + Number(n).toLocaleString('id-ID');
    document.getElementById('sum-sell').textContent = f(j.subtotal ?? (j.sell_price * quantity));
    document.getElementById('sum-quantity').textContent = quantity;
    document.getElementById('sum-service').textContent = f((j.admin_fee || 0) + (j.gateway_fee || 0));
    const totalText = f(j.checkout_total ?? j.total);
    document.getElementById('sum-total').textContent = totalText;
    const stickyTotal = document.getElementById('sticky-total');
    if (stickyTotal) stickyTotal.textContent = totalText;
    warning.textContent = j.message || '';
    warning.classList.toggle('hidden', j.available !== false);
    setPayDisabled(j.available === false);
}

/**
 * Tombol desktop dan tombol sticky mobile harus selalu sinkron — kalau salah
 * satu tertinggal aktif, pelanggan bisa menekan tombol yang seharusnya mati.
 */
function setPayDisabled(disabled) {
    ['btn-bayar', 'btn-bayar-mobile'].forEach(id => {
        const button = document.getElementById(id);
        if (!button) return;
        button.disabled = disabled;
        button.style.opacity = disabled ? '.55' : '';
        button.style.cursor = disabled ? 'not-allowed' : '';
    });
}

document.querySelectorAll('.tripay-channel-input').forEach(el => el.addEventListener('change', async event => {
    document.querySelectorAll('#tripay-channel-panel .ipaymu-channel-card')
        .forEach(card => card.classList.toggle('is-selected', card.contains(event.currentTarget)));
    await refreshQuote();
    scrollToOrderSummary();
}));
document.querySelectorAll('input[name=gateway_code]').forEach(el => el.addEventListener('change', async event => {
    await refreshQuote();
    // iPaymu & Tripay butuh langkah memilih channel dulu, jadi jangan langsung
    // menggulir ke ringkasan dan melewati panel channelnya.
    if (!['ipaymu', 'tripay'].includes(event.currentTarget.value)) {
        scrollToOrderSummary();
    }
}));
document.querySelectorAll('.ipaymu-channel-input').forEach(el => el.addEventListener('change', async event => {
    document.getElementById('ipaymu-method').value = event.currentTarget.dataset.method;
    document.querySelectorAll('.ipaymu-channel-card').forEach(card => card.classList.toggle('is-selected', card.contains(event.currentTarget)));
    await refreshQuote();
    scrollToOrderSummary();
}));
document.querySelectorAll('[data-quantity-action]').forEach(button => button.addEventListener('click', () => {
    const direction = button.dataset.quantityAction === 'plus' ? 1 : -1;
    quantityInput.value = Math.max(1, Math.min(maxQuantity, currentQuantity() + direction));
    document.querySelector('[data-quantity-action="minus"]').disabled = currentQuantity() <= 1;
    document.querySelector('[data-quantity-action="plus"]').disabled = currentQuantity() >= maxQuantity;
    refreshQuote();
}));
document.querySelector('[data-quantity-action="minus"]').disabled = currentQuantity() <= 1;
document.querySelector('[data-quantity-action="plus"]').disabled = currentQuantity() >= maxQuantity;
refreshQuote();
if (isGame) {
const nickBox = document.getElementById('nick-box');

if (nickBox) {
const nickHint = document.getElementById('nick-hint');
const nickResult = document.getElementById('nick-result');
const nickSpinner = document.getElementById('nick-spinner');
const nickField = document.getElementById('nickname');
const uidInput = document.getElementById('target-uid');
const zoneInput = document.getElementById('zone');
const nickLabel = document.getElementById('btn-nick-label');
const esc = s => String(s).replace(/</g, '&lt;').replace(/>/g, '&gt;');
const nickRequiresZone = @js($nicknameRequiresZone);
const setNickState = (state, html) => {
    nickBox.style.borderColor = state === 'ok' ? '#22c55e' : (state === 'err' ? '#ef4444' : '');
    nickBox.style.background = state === 'ok' ? 'rgba(34,197,94,.08)' : (state === 'err' ? 'rgba(239,68,68,.08)' : '');
    nickResult.classList.toggle('hidden', !html);
    nickHint.classList.toggle('hidden', !!html);
    if (html) nickResult.innerHTML = html;
};

// Satu jalur untuk cek manual (tombol) dan cek otomatis (setelah ID diisi),
// supaya keduanya berperilaku persis sama.
const runNickCheck = async (manual = false) => {
    const uid = uidInput.value.trim();
    const zone = zoneInput.value.trim();

    if (!uid) {
        if (manual) setNickState('err', 'Isi User ID dulu.');
        return;
    }
    // Game yang butuh zone tidak bisa dicek sebelum zone diisi — tampilkan
    // arahan, bukan panggilan API yang pasti gagal.
    if (nickRequiresZone && !zone) {
        if (manual) setNickState('err', 'Isi Server / Zone dulu.');
        else { setNickState('', null); nickHint.classList.remove('hidden'); }
        return;
    }

    const btn = document.getElementById('btn-nick');
    if (btn) { btn.disabled = true; btn.style.opacity = '.6'; }
    nickLabel.textContent = 'Mengecek...';
    nickSpinner.classList.remove('hidden');

    try {
        const r = await fetch(nickUrl, {method: 'POST', headers: {'Content-Type': 'application/json', 'X-CSRF-TOKEN': token}, body: JSON.stringify({product_id: productId, user_id: uid, zone_id: zone})});
        const j = await r.json();
        if (j.ok && j.nickname) {
            setNickState('ok', '✓ ' + esc(j.nickname) + (j.country?.name ? ' <span class="muted font-normal">(' + esc(j.country.name) + ')</span>' : ''));
            nickField.value = j.nickname;
        } else {
            setNickState('err', '✕ ' + esc(j.message || 'Nickname tidak ditemukan.'));
            nickField.value = '';
        }
    } catch (err) {
        setNickState('err', '✕ Gagal menghubungi server.');
    }
    if (btn) { btn.disabled = false; btn.style.opacity = ''; }
    nickLabel.textContent = 'Cek Ulang';
    nickSpinner.classList.add('hidden');
};

document.getElementById('btn-nick').addEventListener('click', () => runNickCheck(true));

// Cek otomatis: jalankan setelah pembeli berhenti mengetik, bukan tiap ketukan
// tombol, supaya API cek nickname (berbayar & dibatasi 20/menit) tidak dibanjiri.
let nickTimer = null;
const scheduleNickCheck = () => {
    clearTimeout(nickTimer);
    nickField.value = '';
    nickLabel.textContent = 'Cek Nickname';
    setNickState('', null);
    nickHint.classList.remove('hidden');

    // Jangan panggil API saat input belum lengkap.
    if (!uidInput.value.trim()) return;
    if (nickRequiresZone && !zoneInput.value.trim()) return;

    nickTimer = setTimeout(() => runNickCheck(false), 700);
};
uidInput.addEventListener('input', scheduleNickCheck);
zoneInput.addEventListener('input', scheduleNickCheck);
} // end if (nickBox)
} // end if (isGame)
</script>
@endsection
