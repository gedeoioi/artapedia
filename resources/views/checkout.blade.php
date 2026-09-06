@extends('layouts.shop')

@section('title', 'Checkout '.$product->name)
@section('meta_description', 'Beli '.$product->name.' ('.$product->game.') di ArtaPedia.')

@section('content')
<script type="application/ld+json">
{!! json_encode(['@context' => 'https://schema.org', '@type' => 'Product', 'name' => $product->name, 'category' => $product->game, 'offers' => ['@type' => 'Offer', 'priceCurrency' => 'IDR', 'price' => $product->price_guest, 'availability' => $product->in_stock ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock']]) !!}
</script>

<div class="grid md:grid-cols-2 gap-4">
    <div class="card p-5">
        <h1 class="font-bold text-lg">{{ $product->name }}</h1>
        <div class="text-sm text-gray-500 mb-4">{{ $product->game }}</div>
        <form method="POST" action="{{ route('checkout.store') }}" id="checkout-form">
            @csrf
            <input type="hidden" name="product_id" value="{{ $product->id }}">
            <label class="text-sm font-semibold">User ID / Tujuan</label>
            <input name="target_user_id" required class="card w-full px-3 py-2 mt-1 mb-2" placeholder="cth: 12345678">
            <label class="text-sm font-semibold">Server / Zone (opsional)</label>
            <input name="target_zone" id="zone" class="card w-full px-3 py-2 mt-1 mb-2" placeholder="cth: 1234">
            <div class="flex gap-2 mb-2">
                <button type="button" id="btn-nick" class="card px-3 py-2 text-sm">Cek Nickname</button>
                <span id="nick-result" class="text-sm text-gray-600"></span>
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
document.getElementById('btn-nick').addEventListener('click', async () => {
    const uid = document.querySelector('input[name=target_user_id]').value;
    const zone = document.getElementById('zone').value;
    const r = await fetch(nickUrl, {method: 'POST', headers: {'Content-Type': 'application/json', 'X-CSRF-TOKEN': token}, body: JSON.stringify({product_id: productId, user_id: uid, zone_id: zone})});
    const j = await r.json();
    document.getElementById('nick-result').textContent = j.ok ? JSON.stringify(j.data).slice(0, 120) : (j.message || 'Gagal');
});
</script>
@endsection
