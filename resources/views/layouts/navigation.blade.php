<nav x-data="{ open: false }" class="member-topbar">
    @php
        $memberSiteName = \App\Models\SiteSetting::get('site_name', 'ArtaPedia');
        $memberLogo = \App\Models\SiteSetting::logoUrl();
    @endphp
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between h-16">
            <div class="flex items-center gap-3 sm:gap-5">
                <a href="{{ route('home') }}" class="inline-flex items-center gap-2.5 shrink-0" aria-label="Beranda {{ $memberSiteName }}">
                    <span class="member-logo-mark">
                        @if($memberLogo)
                            <img src="{{ $memberLogo }}" alt="{{ $memberSiteName }}" class="w-full h-full object-contain p-1">
                        @else
                            <x-application-logo class="w-7 h-7 fill-current text-orange-500" />
                        @endif
                    </span>
                    <span class="member-brand text-xl font-extrabold tracking-tight hidden sm:inline">{{ $memberSiteName }}</span>
                </a>

                <div class="hidden sm:flex items-center gap-1">
                    <a href="{{ route('member.dashboard') }}" class="member-nav-button {{ request()->routeIs('member.dashboard', 'dashboard') ? 'is-active' : '' }}">
                        <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>
                        Member Area
                    </a>
                    <a href="{{ route('topup.create') }}" class="member-nav-button {{ request()->routeIs('topup.*') ? 'is-active' : '' }}">
                        <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m13 2-9 12h7l-1 8 9-12h-7l1-8Z"/></svg>
                        Topup
                    </a>
                </div>
            </div>

            <div class="hidden sm:flex sm:items-center">
                <x-dropdown align="right" width="48" contentClasses="py-1 member-dropdown">
                    <x-slot name="trigger">
                        <button class="member-nav-button">
                            <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/></svg>
                            <span>{{ Auth::user()->name }}</span>
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>
                        </button>
                    </x-slot>

                    <x-slot name="content">
                        <x-dropdown-link :href="route('profile.edit')">Profil</x-dropdown-link>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <x-dropdown-link :href="route('logout')" onclick="event.preventDefault(); this.closest('form').submit();">Keluar</x-dropdown-link>
                        </form>
                    </x-slot>
                </x-dropdown>
            </div>

            <div class="flex items-center sm:hidden">
                <button @click="open = ! open" class="member-nav-button" aria-label="Buka menu">
                    <svg x-show="!open" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 6h16M4 12h16M4 18h16"/></svg>
                    <svg x-show="open" style="display:none" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m6 6 12 12M18 6 6 18"/></svg>
                </button>
            </div>
        </div>
    </div>

    <div x-show="open" x-transition class="sm:hidden border-t border-zinc-800" style="display:none">
        <div class="px-4 py-3 grid gap-1">
            <a href="{{ route('member.dashboard') }}" class="member-nav-button {{ request()->routeIs('member.dashboard', 'dashboard') ? 'is-active' : '' }}">Dasbor</a>
            <a href="{{ route('topup.create') }}" class="member-nav-button {{ request()->routeIs('topup.*') ? 'is-active' : '' }}">Topup</a>
            <a href="{{ route('profile.edit') }}" class="member-nav-button {{ request()->routeIs('profile.*') ? 'is-active' : '' }}">Profil</a>
            <form method="POST" action="{{ route('logout') }}">@csrf<button class="member-nav-button w-full">Keluar</button></form>
        </div>
    </div>
</nav>
