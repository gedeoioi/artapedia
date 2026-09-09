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

        @if(auth()->user()->phone)
            <div class="text-sm muted mb-1">Pembayaran menggunakan nomor akun <b>{{ auth()->user()->phone }}</b>. <a href="{{ route('profile.edit') }}" class="underline">Ubah</a></div>
        @else
            <div class="card p-3 text-sm text-red-500 mb-1">Nomor HP akun belum tersedia. <a href="{{ route('profile.edit') }}" class="underline font-semibold">Lengkapi profil</a> sebelum melakukan topup.</div>
        @endif

        @foreach($gateways as $gw)
            <label class="card p-3 flex items-center gap-2 text-sm">
                <input type="radio" name="gateway_code" value="{{ $gw->code }}" @checked(old('gateway_code', $gateways->first()?->code) === $gw->code)>
                <b>{{ $gw->name }}</b> <span class="text-gray-500">{{ $gw->code }}</span>
            </label>
        @endforeach
        @error('gateway_code') <p class="text-sm text-red-500">{{ $message }}</p> @enderror
        <button class="card py-2 font-bold" @disabled(! auth()->user()->phone)>Buat Pembayaran</button>
    </form>
</div>
@endsection
