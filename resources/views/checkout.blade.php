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
                <div class="text-sm text-gray-500">{{ $product->game }}</div>
            </div>
        </div>
        <form method="POST" action="{{ route('checkout.store') }}" id="checkout-form">
            @csrf
            <input type="hidden" name="product_id" value="{{ $product->id }}">
            <label class="text-sm font-semibold">User ID / Tujuan</label>
            <input name="target_user_id" id="target-uid" required class="card w-full px-3 py-2 mt-1 mb-2" placeholder="cth: 12345678" autocomplete="off">
            <label class="text-sm font-semibold">Server / Zone <span class="muted font-normal">(opsional)</span></label>
            <input name="target_zone" id="zone" class="card w-full px-3 py-2 mt-1 mb-2" placeholder="cth: 1234" autocomplete="off">
            <div class="card p-3 mb-3 flex items-center gap-3" id="nick-box" style="border-style:dashed">
                <button type="button" id="btn-nick" class="btn-primary px-4 py-2 text-sm whitespace-nowrap shrink-0">
                    <span id="btn-nick-label">Cek Nickname</span>
                </button>
                <div class="min-w-0 flex-1">
                    <div id="nick-hint" class="text-xs muted">Pastikan User ID benar sebelum bayar.</div>
                    <div id="nick-result" class="text-sm font-semibold hidden"></div>
                </div>
                <div id="nick-spinner" class="hidden shrink-0" aria-hidden="true">
                    <svg class="animate-spin" width="20" height="20" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" opacity=".25"/><path d="M12 2a10 10 0 0 1 10 10" stroke="currentColor" stroke-width="3" stroke-linecap="round"/></svg>
                </div>
            </div>
            <input type="hidden" name="nickname" id="nickname">
            <div class="grid grid-cols-2 gap-2 mb-2">
                <div><label class="text-sm">No. HP</label><input name="buyer_phone" class="card w-full px-3 py-2 mt-1"></div>
                <div><label class="text-sm">Email</label><input name="buyer_email" type="email" class="card w-full px-3 py-2 mt-1"></div>
            </div>
            <label class="text-sm font-semibold">Metode pembayaran</label>
            <div class="grid gap-2 mt-1 mb-3" id="gateways">
                @auth
                <label class="card p-3 flex items-center gap-2">
                    <input type="radio" name="gateway_code" value="balance" checked>
                    <span class="text-sm font-semibold">Saldo member</span>
                    <span class="text-xs text-gray-500">Rp {{ number_format(auth()->user()->balance, 0, ',', '.') }}</span>
                </label>
                @endauth
                @foreach($gateways as $gw)
                    <label class="card p-3 flex items-center gap-2">
                        <input type="radio" name="gateway_code" value="{{ $gw->code }}" @guest checked @endguest>
                        <span class="text-sm font-semibold">{{ $gw->name }}</span>
                        <span class="text-xs text-gray-500">{{ $gw->code }}</span>
                    </label>
                @endforeach
            </div>
            <button class="card w-full py-2 font-bold" id="btn-bayar">Bayar</button>
        </form>
    </div>
    <div class="card p-5 h-fit">
        <h2 class="font-bold mb-2">Ringkasan</h2>
        <div class="text-sm flex justify-between"><span>Harga</span><span id="sum-sell">Rp {{ number_format($product->price_guest, 0, ',', '.') }}</span></div>
        <div class="text-sm flex justify-between"><span>Fee gateway</span><span id="sum-fee">Rp 0</span></div>
        <div class="font-bold flex justify-between mt-2"><span>Total</span><span id="sum-total">Rp {{ number_format($product->price_guest, 0, ',', '.') }}</span></div>
    </div>
</div>
@endsection

@section('scripts')
<script>
const quoteUrl = "{{ route('checkout.quote') }}";
const nickUrl = "{{ route('checkout.nickname') }}";
const productId = {{ $product->id }};
const token = document.querySelector('meta[name=csrf-token]').content;
async function refreshQuote() {
    const gw = document.querySelector('input[name=gateway_code]:checked')?.value || 'balance';
    const r = await fetch(quoteUrl, {method: 'POST', headers: {'Content-Type': 'application/json', 'X-CSRF-TOKEN': token}, body: JSON.stringify({product_id: productId, gateway_code: gw})});
    const j = await r.json();
    const f = n => 'Rp ' + Number(n).toLocaleString('id-ID');
    document.getElementById('sum-sell').textContent = f(j.sell_price);
    document.getElementById('sum-fee').textContent = f(j.gateway_fee);
    document.getElementById('sum-total').textContent = f(j.total);
}
document.querySelectorAll('input[name=gateway_code]').forEach(el => el.addEventListener('change', refreshQuote));
refreshQuote();
document.getElementById('btn-nick').addEventListener('click', async (e) => {
    const btn = e.currentTarget;
    const label = document.getElementById('btn-nick-label');
    const uid = document.getElementById('target-uid').value.trim();
    const zone = document.getElementById('zone').value.trim();
    const box = document.getElementById('nick-box');
    const hint = document.getElementById('nick-hint');
    const result = document.getElementById('nick-result');
    const spinner = document.getElementById('nick-spinner');
    const esc = s => String(s).replace(/</g, '&lt;').replace(/>/g, '&gt;');
    const setState = (state, html) => {
        box.style.borderColor = state === 'ok' ? '#22c55e' : (state === 'err' ? '#ef4444' : '');
        box.style.background = state === 'ok' ? 'rgba(34,197,94,.08)' : (state === 'err' ? 'rgba(239,68,68,.08)' : '');
        result.classList.toggle('hidden', !html);
        hint.classList.toggle('hidden', !!html);
        if (html) result.innerHTML = html;
    };
    if (!uid) { setState('err', 'Isi User ID dulu.'); return; }
    btn.disabled = true;
    btn.style.opacity = '.6';
    label.textContent = 'Mengecek...';
    spinner.classList.remove('hidden');
    setState('', null);
    hint.classList.remove('hidden');
    try {
        const r = await fetch(nickUrl, {method: 'POST', headers: {'Content-Type': 'application/json', 'X-CSRF-TOKEN': token}, body: JSON.stringify({product_id: productId, user_id: uid, zone_id: zone})});
        const j = await r.json();
        if (j.ok && j.nickname) {
            setState('ok', '✓ ' + esc(j.nickname) + (j.country?.name ? ' <span class="muted font-normal">(' + esc(j.country.name) + ')</span>' : ''));
            document.getElementById('nickname').value = j.nickname;
        } else {
            setState('err', '✕ ' + esc(j.message || 'Nickname tidak ditemukan.'));
            document.getElementById('nickname').value = '';
        }
    } catch (err) {
        setState('err', '✕ Gagal menghubungi server.');
    }
    btn.disabled = false;
    btn.style.opacity = '';
    label.textContent = 'Cek Ulang';
    spinner.classList.add('hidden');
});
['target-uid', 'zone'].forEach(id => document.getElementById(id).addEventListener('input', () => {
    document.getElementById('nickname').value = '';
    document.getElementById('btn-nick-label').textContent = 'Cek Nickname';
}));
</script>
@endsection
