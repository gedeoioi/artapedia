<x-guest-layout>
    <x-slot name="title">Lupa Kata Sandi</x-slot>

    <h1 class="text-xl font-bold mb-1">Lupa kata sandi?</h1>
    <p class="text-sm text-gray-500 mb-5">Masukkan email Anda, kami akan mengirimkan tautan untuk membuat kata sandi baru.</p>

    <!-- Session Status -->
    <x-auth-session-status class="mb-4" :status="session('status')" />

    <form method="POST" action="{{ route('password.email') }}">
        @csrf

        <!-- Email -->
        <div>
            <x-input-label for="email" value="Email" />
            <x-text-input id="email" class="block mt-1 w-full" type="email" name="email" :value="old('email')" required autofocus placeholder="nama@email.com" />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <div class="mt-5">
            <x-primary-button class="w-full justify-center">
                Kirim Tautan Reset
            </x-primary-button>
        </div>
    </form>
</x-guest-layout>
