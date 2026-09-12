@extends('layouts.shop')

@section('title', 'Kontak')
@section('meta_description', 'Hubungi tim dukungan '.\App\Models\SiteSetting::get('site_name', 'ArtaPedia').' untuk bantuan transaksi dan layanan.')

@section('content')
@php
    $siteName = \App\Models\SiteSetting::get('site_name', 'ArtaPedia');
    $whatsapp = trim((string) \App\Models\SiteSetting::get('contact_whatsapp', ''));
    $whatsappNumber = preg_replace('/\D+/', '', $whatsapp);
    $email = trim((string) \App\Models\SiteSetting::get('contact_email', ''));
    $address = trim((string) \App\Models\SiteSetting::get('contact_address', ''));
    $whatsappUrl = $whatsappNumber ? 'https://wa.me/'.$whatsappNumber.'?text='.rawurlencode('Halo '.$siteName.', saya membutuhkan bantuan terkait transaksi.') : null;
@endphp

<section class="max-w-5xl mx-auto">
    <header class="card relative overflow-hidden px-6 py-9 md:px-10 md:py-12 mb-6">
        <div class="absolute inset-0 hero-grad pointer-events-none" aria-hidden="true"></div>
        <div class="relative max-w-2xl">
            <div class="text-xs uppercase tracking-[.16em] font-bold accent mb-2">Pusat Bantuan</div>
            <h1 class="text-3xl md:text-4xl font-extrabold">Ada yang bisa kami bantu?</h1>
            <p class="muted mt-3 leading-7">Hubungi tim {{ $siteName }} untuk kendala pembayaran, status pesanan, pengiriman produk, atau pertanyaan lainnya. Kami akan membantu berdasarkan data transaksi yang tersedia.</p>
        </div>
    </header>

    <div class="grid md:grid-cols-5 gap-6">
        <div class="md:col-span-3 space-y-4">
            <h2 class="font-extrabold text-lg">Pilih kanal dukungan</h2>

            @if($whatsappUrl)
            <a href="{{ $whatsappUrl }}" target="_blank" rel="noopener noreferrer" class="card group p-5 flex items-center gap-4 hover:border-green-500 transition-colors">
                <span class="h-12 w-12 rounded-xl bg-green-500/15 text-green-400 flex items-center justify-center shrink-0">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-6 w-6" aria-hidden="true"><path d="M20 11.5a8 8 0 0 1-11.8 7L4 20l1.5-4.1A8 8 0 1 1 20 11.5Z"/><path d="M9 8.5c.4 2.4 2.1 4.1 4.5 4.5"/></svg>
                </span>
                <span class="min-w-0 flex-1">
                    <span class="block font-extrabold group-hover:text-green-400">WhatsApp</span>
                    <span class="block muted text-sm mt-0.5 truncate">{{ $whatsapp }}</span>
                </span>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="h-5 w-5 muted shrink-0" aria-hidden="true"><path d="m9 18 6-6-6-6"/></svg>
            </a>
            @else
            <div class="card p-5 flex items-center gap-4 opacity-70">
                <span class="h-12 w-12 rounded-xl bg-neutral-500/15 flex items-center justify-center shrink-0">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-6 w-6" aria-hidden="true"><path d="M20 11.5a8 8 0 0 1-11.8 7L4 20l1.5-4.1A8 8 0 1 1 20 11.5Z"/></svg>
                </span>
                <span><span class="block font-extrabold">WhatsApp</span><span class="block muted text-sm mt-0.5">Belum tersedia</span></span>
            </div>
            @endif

            @if($email)
            <a href="mailto:{{ $email }}?subject={{ rawurlencode('Bantuan Transaksi '.$siteName) }}" class="card group p-5 flex items-center gap-4 hover:border-orange-500 transition-colors">
                <span class="h-12 w-12 rounded-xl bg-orange-500/15 text-orange-400 flex items-center justify-center shrink-0">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-6 w-6" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/></svg>
                </span>
                <span class="min-w-0 flex-1">
                    <span class="block font-extrabold group-hover:text-orange-500">Email</span>
                    <span class="block muted text-sm mt-0.5 truncate">{{ $email }}</span>
                </span>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="h-5 w-5 muted shrink-0" aria-hidden="true"><path d="m9 18 6-6-6-6"/></svg>
            </a>
            @else
            <div class="card p-5 flex items-center gap-4 opacity-70">
                <span class="h-12 w-12 rounded-xl bg-neutral-500/15 flex items-center justify-center shrink-0">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-6 w-6" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/></svg>
                </span>
                <span><span class="block font-extrabold">Email</span><span class="block muted text-sm mt-0.5">Belum tersedia</span></span>
            </div>
            @endif

            <div class="card p-5 flex items-start gap-4">
                <span class="h-12 w-12 rounded-xl bg-blue-500/15 text-blue-400 flex items-center justify-center shrink-0">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-6 w-6" aria-hidden="true"><path d="M20 10c0 5-8 11-8 11S4 15 4 10a8 8 0 1 1 16 0Z"/><circle cx="12" cy="10" r="2.5"/></svg>
                </span>
                <span class="min-w-0 flex-1">
                    <span class="block font-extrabold">Alamat</span>
                    <span class="block muted text-sm mt-0.5 leading-6 whitespace-pre-line">{{ $address ?: 'Belum tersedia' }}</span>
                </span>
            </div>

            <p class="muted text-xs leading-5">Jangan pernah memberikan kata sandi, PIN, atau kode OTP kepada siapa pun, termasuk tim {{ $siteName }}.</p>
        </div>

        <aside class="md:col-span-2 card p-5 md:p-6 self-start">
            <div class="h-10 w-10 rounded-xl bg-orange-500/15 text-orange-500 flex items-center justify-center mb-4" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="h-5 w-5"><path d="M9 11h6M9 15h4"/><path d="M5 3h14a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2Z"/></svg>
            </div>
            <h2 class="font-extrabold text-lg">Agar cepat ditangani</h2>
            <p class="muted text-sm mt-1 mb-4">Sertakan informasi berikut saat menghubungi kami:</p>
            <ul class="space-y-3 text-sm">
                <li class="flex gap-3"><span class="accent font-extrabold">01</span><span>Kode invoice transaksi</span></li>
                <li class="flex gap-3"><span class="accent font-extrabold">02</span><span>Nama produk dan nominal</span></li>
                <li class="flex gap-3"><span class="accent font-extrabold">03</span><span>Waktu serta metode pembayaran</span></li>
                <li class="flex gap-3"><span class="accent font-extrabold">04</span><span>Bukti pembayaran yang jelas</span></li>
            </ul>
            <a href="{{ route('invoice.index') }}" class="mt-6 card w-full px-4 py-2.5 inline-flex items-center justify-center text-sm font-bold hover:border-orange-500">Cek Status Invoice</a>
        </aside>
    </div>

    <div class="grid sm:grid-cols-2 gap-4 mt-6">
        <a href="{{ route('support.faq') }}" class="card p-5 hover:border-orange-500 transition-colors">
            <div class="font-bold">Baca FAQ</div>
            <p class="muted text-sm mt-1">Jawaban cepat untuk pertanyaan umum.</p>
        </a>
        <a href="{{ route('legal.refund') }}" class="card p-5 hover:border-orange-500 transition-colors">
            <div class="font-bold">Kebijakan Refund</div>
            <p class="muted text-sm mt-1">Pelajari syarat pengembalian dana.</p>
        </a>
    </div>
</section>
@endsection
