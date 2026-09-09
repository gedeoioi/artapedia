@extends('layouts.shop')

@section('title', 'Topup Saldo')

@section('content')
<div class="card p-5 max-w-md mx-auto">
    <h1 class="font-bold text-lg mb-3">Topup Saldo</h1>
    <form method="POST" action="{{ route('topup.store') }}" class="grid gap-2">
        @csrf
        <label class="text-sm">Nominal (min Rp 10.000)</label>
        <input name="amount" type="number" min="10000" max="10000000" step="1000" value="{{ old('amount', 50000) }}" required class="card px-3 py-2">
        @error('amount') <p class="text-sm text-red-500">{{ $message }}</p> @enderror

        <label class="text-sm mt-1">Nomor WhatsApp / HP</label>
        <input name="phone" type="tel" inputmode="tel" value="{{ old('phone', auth()->user()->phone) }}" placeholder="081234567890" required class="card px-3 py-2">
        @error('phone') <p class="text-sm text-red-500">Nomor HP wajib diisi dengan format Indonesia, contoh 081234567890.</p> @enderror

        @foreach($gateways as $gw)
            <label class="card p-3 flex items-center gap-2 text-sm">
                <input type="radio" name="gateway_code" value="{{ $gw->code }}" @checked(old('gateway_code', $gateways->first()?->code) === $gw->code)>
                <b>{{ $gw->name }}</b> <span class="text-gray-500">{{ $gw->code }}</span>
            </label>
        @endforeach
        @error('gateway_code') <p class="text-sm text-red-500">{{ $message }}</p> @enderror
        <button class="card py-2 font-bold">Buat Pembayaran</button>
    </form>
</div>
@endsection
