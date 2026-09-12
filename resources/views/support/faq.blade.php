@extends('layouts.shop')

@section('title', 'FAQ')
@section('meta_description', 'Pertanyaan umum seputar pembelian, pembayaran, pengiriman, dan refund di '.\App\Models\SiteSetting::get('site_name', 'ArtaPedia').'.')

@section('content')
@php
    $siteName = \App\Models\SiteSetting::get('site_name', 'ArtaPedia');
    $faqs = [
        ['Bagaimana cara melakukan pembelian?', 'Pilih kategori dan produk, masukkan data tujuan dengan benar, pilih metode pembayaran, lalu selesaikan pembayaran sesuai instruksi. Setelah terkonfirmasi, pesanan akan diproses otomatis.'],
        ['Apakah harus membuat akun?', 'Tidak. Anda dapat melakukan pembelian sebagai tamu. Namun, akun member memudahkan Anda melihat riwayat transaksi dan menggunakan fitur saldo yang tersedia.'],
        ['Berapa lama pesanan diproses?', 'Sebagian besar pesanan diproses otomatis dalam beberapa menit setelah pembayaran terkonfirmasi. Waktu dapat lebih lama apabila bank, gateway pembayaran, operator, atau supplier sedang mengalami gangguan.'],
        ['Bagaimana cara mengecek status transaksi?', 'Buka halaman Cek Transaksi, lalu masukkan kode invoice yang diterima saat pemesanan. Simpan kode invoice sampai pesanan selesai.'],
        ['Mengapa pembayaran saya masih pending?', 'Konfirmasi pembayaran dapat terlambat dari pihak bank atau gateway. Pastikan nominal dan tujuan pembayaran benar. Jika status tidak berubah setelah waktu yang wajar, hubungi dukungan dengan menyertakan kode invoice dan bukti pembayaran.'],
        ['Apa yang harus dilakukan jika produk belum masuk?', 'Periksa kembali data tujuan dan status invoice. Jika transaksi sudah sukses tetapi produk belum diterima, hubungi dukungan agar kami dapat menelusuri serial number atau status pengiriman dari supplier.'],
        ['Apakah pesanan dapat dibatalkan?', 'Pesanan yang belum dibayar akan kedaluwarsa otomatis. Pesanan yang telah dibayar dan sedang diproses tidak dapat dibatalkan sepihak karena produk digital dikirim secara otomatis.'],
        ['Apakah saya bisa mendapatkan refund?', 'Refund dapat diajukan jika pembayaran diterima tetapi transaksi dinyatakan gagal dan produk belum terkirim. Pesanan sukses atau kesalahan data tujuan dari pengguna tidak memenuhi syarat refund.'],
        ['Bagaimana jika saya salah memasukkan User ID atau nomor tujuan?', 'Segera hubungi dukungan jika pesanan belum diproses. Namun, produk yang telah sukses dikirim ke data tujuan yang Anda masukkan tidak dapat ditarik, dipindahkan, atau dikembalikan.'],
        ['Metode pembayaran apa yang tersedia?', 'Metode yang tersedia ditampilkan saat checkout dan dapat mencakup QRIS, virtual account, e-wallet, atau saldo member. Ketersediaannya mengikuti konfigurasi dan status penyedia pembayaran.'],
    ];
@endphp

<section class="max-w-4xl mx-auto">
    <header class="text-center max-w-2xl mx-auto mb-8">
        <div class="mx-auto mb-4 h-12 w-12 rounded-2xl bg-orange-500/15 text-orange-500 flex items-center justify-center" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="h-6 w-6"><circle cx="12" cy="12" r="10"/><path d="M9.5 9a2.7 2.7 0 0 1 5.2 1c0 2-2.7 2.2-2.7 4M12 18h.01"/></svg>
        </div>
        <div class="text-xs uppercase tracking-[.16em] font-bold accent mb-2">Pusat Bantuan</div>
        <h1 class="text-3xl md:text-4xl font-extrabold">Pertanyaan yang sering diajukan</h1>
        <p class="muted mt-3">Temukan jawaban singkat tentang transaksi dan layanan {{ $siteName }}.</p>
    </header>

    <div class="space-y-3" x-data="{ active: null }">
        @foreach($faqs as [$question, $answer])
        <article class="card overflow-hidden">
            <h2>
                <button
                    type="button"
                    class="w-full px-5 py-4 flex items-center justify-between gap-4 text-left font-bold hover:text-orange-500 transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-orange-500 focus-visible:ring-inset"
                    @click="active = active === {{ $loop->index }} ? null : {{ $loop->index }}"
                    :aria-expanded="active === {{ $loop->index }}"
                    aria-controls="faq-answer-{{ $loop->index }}"
                >
                    <span>{{ $question }}</span>
                    <span class="h-7 w-7 rounded-full bg-orange-500/10 text-orange-500 flex items-center justify-center shrink-0 transition-transform" :class="active === {{ $loop->index }} && 'rotate-45'" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="h-4 w-4"><path d="M12 5v14M5 12h14"/></svg>
                    </span>
                </button>
            </h2>
            <div id="faq-answer-{{ $loop->index }}" x-show="active === {{ $loop->index }}" x-cloak>
                <p class="muted px-5 pb-5 pr-14 text-sm leading-6">{{ $answer }}</p>
            </div>
        </article>
        @endforeach
    </div>

    <div class="card mt-8 p-6 text-center bg-orange-500/5">
        <h2 class="font-extrabold text-lg">Belum menemukan jawaban?</h2>
        <p class="muted text-sm mt-1 mb-4">Tim dukungan kami siap membantu kendala transaksi Anda.</p>
        <div class="flex flex-col sm:flex-row justify-center gap-3">
            <a href="{{ route('support.contact') }}" class="btn-primary px-5 py-2.5 text-sm">Hubungi Dukungan</a>
            <a href="{{ route('invoice.index') }}" class="card px-5 py-2.5 text-sm font-bold hover:border-orange-500">Cek Transaksi</a>
        </div>
    </div>
</section>
@endsection
