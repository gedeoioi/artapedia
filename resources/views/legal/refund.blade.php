@extends('layouts.shop')

@section('title', 'Refund Policy')
@section('meta_description', 'Kebijakan pembatalan dan pengembalian dana transaksi di '.\App\Models\SiteSetting::get('site_name', 'ArtaPedia').'.')

@section('content')
@php $siteName = \App\Models\SiteSetting::get('site_name', 'ArtaPedia'); @endphp
<article class="card max-w-4xl mx-auto overflow-hidden">
    <header class="relative border-b border-neutral-800 px-6 py-8 md:px-10 md:py-10 overflow-hidden">
        <div class="absolute -right-16 -top-20 h-52 w-52 rounded-full bg-orange-500/10 blur-3xl" aria-hidden="true"></div>
        <div class="relative">
            <div class="text-xs uppercase tracking-[.16em] font-bold accent mb-2">Informasi Transaksi</div>
            <h1 class="text-2xl md:text-3xl font-extrabold">Refund Policy</h1>
            <p class="muted text-sm mt-2">Kebijakan Pengembalian Dana · Berlaku sejak 12 September 2026</p>
        </div>
    </header>

    <div class="px-6 py-7 md:px-10 md:py-9">
        <div class="grid sm:grid-cols-2 gap-3 mb-9">
            <div class="rounded-xl border border-green-500/25 bg-green-500/10 p-4">
                <div class="text-sm font-extrabold text-green-400 mb-1">Dapat diajukan</div>
                <p class="muted text-xs leading-5">Pembayaran diterima, transaksi berstatus gagal, dan produk belum terkirim.</p>
            </div>
            <div class="rounded-xl border border-red-500/25 bg-red-500/10 p-4">
                <div class="text-sm font-extrabold text-red-400 mb-1">Tidak dapat diajukan</div>
                <p class="muted text-xs leading-5">Produk sudah sukses terkirim atau data tujuan yang dimasukkan pengguna salah.</p>
            </div>
        </div>

        <div class="space-y-7 text-base leading-7">
            <section>
                <h2 class="font-bold text-lg mb-2">1. Ketentuan umum</h2>
                <p class="muted">Kebijakan ini berlaku untuk transaksi produk digital melalui {{ $siteName }}. Karena produk diproses otomatis dan tidak dapat ditarik kembali setelah terkirim, pengembalian dana hanya dapat dilakukan pada kondisi tertentu setelah pemeriksaan status pembayaran dan pengiriman.</p>
            </section>

            <section>
                <h2 class="font-bold text-lg mb-2">2. Transaksi yang memenuhi syarat</h2>
                <ul class="list-disc pl-5 space-y-2 muted">
                    <li>Pembayaran telah terkonfirmasi, tetapi pesanan dinyatakan gagal oleh sistem atau supplier.</li>
                    <li>Produk belum diterima pada User ID, nomor telepon, atau tujuan yang tercantum di pesanan.</li>
                    <li>Terjadi pembayaran ganda untuk invoice yang sama dan kedua pembayaran telah kami verifikasi.</li>
                    <li>Nominal yang dibayarkan tidak dapat diproses karena gangguan layanan dan pesanan tidak berhasil dibuat.</li>
                </ul>
            </section>

            <section>
                <h2 class="font-bold text-lg mb-2">3. Transaksi yang tidak memenuhi syarat</h2>
                <ul class="list-disc pl-5 space-y-2 muted">
                    <li>Pesanan telah berstatus sukses dan produk sudah dikirim.</li>
                    <li>Kesalahan User ID, zone, nomor telepon, email, server, atau data tujuan lain dari pengguna.</li>
                    <li>Perubahan pikiran setelah pembayaran atau setelah pesanan diproses.</li>
                    <li>Keterlambatan sementara dari bank, gateway pembayaran, operator, atau supplier ketika transaksi masih diproses.</li>
                    <li>Permintaan yang tidak dilengkapi bukti pembayaran dan kode invoice yang valid.</li>
                </ul>
            </section>

            <section>
                <h2 class="font-bold text-lg mb-2">4. Cara mengajukan refund</h2>
                <ol class="list-decimal pl-5 space-y-2 muted">
                    <li>Pastikan status transaksi melalui halaman <a href="{{ route('invoice.index') }}" class="accent font-semibold hover:underline">Cek Transaksi</a>.</li>
                    <li>Hubungi tim dukungan melalui halaman <a href="{{ route('support.contact') }}" class="accent font-semibold hover:underline">Kontak</a>.</li>
                    <li>Sertakan kode invoice, kontak pembeli, waktu pembayaran, nominal, dan bukti pembayaran.</li>
                    <li>Tunggu proses verifikasi. Kami dapat meminta informasi tambahan bila diperlukan.</li>
                </ol>
            </section>

            <section>
                <h2 class="font-bold text-lg mb-2">5. Waktu dan metode pengembalian</h2>
                <p class="muted">Permintaan akan diperiksa setelah data lengkap diterima. Refund yang disetujui diproses ke saldo akun atau metode lain yang diinformasikan oleh tim dukungan. Waktu dana diterima dapat berbeda mengikuti proses bank, e-wallet, atau penyedia pembayaran terkait.</p>
            </section>

            <section>
                <h2 class="font-bold text-lg mb-2">6. Pembayaran kedaluwarsa dan selisih nominal</h2>
                <p class="muted">Pembayaran setelah batas waktu, pembayaran dengan nominal tidak sesuai, atau pembayaran yang tidak teridentifikasi akan ditinjau secara manual. Penyelesaian mengikuti hasil verifikasi gateway pembayaran dan dapat dikenai potongan biaya yang sudah dibebankan oleh penyedia pembayaran apabila berlaku.</p>
            </section>

            <section>
                <h2 class="font-bold text-lg mb-2">7. Keputusan dan perubahan kebijakan</h2>
                <p class="muted">Keputusan refund dibuat berdasarkan catatan sistem, supplier, dan penyedia pembayaran. Kami dapat memperbarui kebijakan ini untuk menyesuaikan layanan atau peraturan tanpa mengurangi hak konsumen yang dijamin hukum.</p>
            </section>
        </div>

        <div class="mt-9 rounded-xl border border-orange-500/30 bg-orange-500/10 p-5 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <div class="font-bold">Butuh bantuan terkait transaksi?</div>
                <p class="muted text-sm mt-1">Siapkan kode invoice agar pemeriksaan lebih cepat.</p>
            </div>
            <a href="{{ route('support.contact') }}" class="btn-primary px-5 py-2.5 text-sm text-center shrink-0">Hubungi Dukungan</a>
        </div>
    </div>
</article>
@endsection
