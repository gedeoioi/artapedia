<x-guest-layout>
    <x-slot name="title">Verifikasi Email</x-slot>

    <h1 class="text-xl font-bold mb-1">Verifikasi email Anda</h1>
    <p class="text-sm text-gray-500 mb-5">Terima kasih sudah mendaftar! Klik tautan verifikasi yang baru saja kami kirim ke email Anda. Belum menerima? Kirim ulang di bawah.</p>

    @if (session('status') == 'verification-link-sent')
        <div class="mb-4 font-medium text-sm text-green-600">
            Tautan verifikasi baru telah dikirim ke email Anda.
        </div>
    @endif

    <div class="mt-4 flex items-center justify-between gap-3">
        <form method="POST" action="{{ route('verification.send') }}">
            @csrf

            <div>
                <x-primary-button>
                    Kirim Ulang Email
                </x-primary-button>
            </div>
        </form>

        <form method="POST" action="{{ route('logout') }}">
            @csrf

            <button type="submit" class="underline text-sm text-gray-500 hover:text-gray-800">
                Keluar
            </button>
        </form>
    </div>
</x-guest-layout>
