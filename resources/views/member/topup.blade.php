@extends('layouts.shop')

@section('title', 'Topup Saldo')

@section('content')
<div class="card p-5 max-w-md mx-auto">
    <h1 class="font-bold text-lg mb-3">Topup Saldo</h1>
    <form method="POST" action="{{ route('topup.store') }}" class="grid gap-2">
        @csrf
        <label class="text-sm">Nominal (min Rp 10.000)</label>
        <input name="amount" type="number" min="10000" step="1000" value="50000" class="card px-3 py-2">
        @foreach($gateways as $gw)
            <label class="card p-3 flex items-center gap-2 text-sm">
                <input type="radio" name="gateway_code" value="{{ $gw->code }}" @if($loop->first) checked @endif>
                <b>{{ $gw->name }}</b> <span class="text-gray-500">{{ $gw->code }}</span>
            </label>
        @endforeach
        <button class="card py-2 font-bold">Buat Pembayaran</button>
    </form>
</div>
@endsection
