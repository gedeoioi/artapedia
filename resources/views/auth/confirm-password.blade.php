<x-guest-layout>
    <x-slot name="title">Konfirmasi Kata Sandi</x-slot>

    <h1 class="text-xl font-bold mb-1">Area aman</h1>
    <p class="text-sm text-gray-500 mb-5"> demi keamanan, silakan konfirmasi kata sandi Anda sebelum melanjutkan.</p>

    <form method="POST" action="{{ route('password.confirm') }}">
        @csrf

        <!-- Kata Sandi -->
        <div>
            <x-input-label for="password" value="Kata Sandi" />
            <x-text-input id="password" class="block mt-1 w-full"
                            type="password"
                            name="password"
                            required autocomplete="current-password" />
            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <div class="mt-5">
            <x-primary-button class="w-full justify-center">
                Konfirmasi
            </x-primary-button>
        </div>
    </form>
</x-guest-layout>
