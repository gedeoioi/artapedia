<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center gap-3">
            <span class="inline-flex w-10 h-10 items-center justify-center rounded-xl bg-orange-500/10 text-orange-400">
                <svg width="21" height="21" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/></svg>
            </span>
            <div>
                <h1 class="font-bold text-xl leading-tight">Profil Saya</h1>
                <p class="text-sm text-zinc-400 mt-0.5">Kelola informasi dan keamanan akun Anda.</p>
            </div>
        </div>
    </x-slot>

    <div class="py-8 sm:py-10 px-4">
        <div class="max-w-4xl mx-auto space-y-5">
            <div class="member-panel p-5 sm:p-7">
                <div class="max-w-2xl">
                    @include('profile.partials.update-profile-information-form')
                </div>
            </div>

            <div class="member-panel p-5 sm:p-7">
                <div class="max-w-2xl">
                    @include('profile.partials.update-password-form')
                </div>
            </div>

            <div class="member-panel p-5 sm:p-7">
                <div class="max-w-2xl">
                    @include('profile.partials.delete-user-form')
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
