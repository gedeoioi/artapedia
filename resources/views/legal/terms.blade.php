@extends('layouts.shop')

@section('title', 'Terms & Conditions')
@section('meta_description', 'Syarat dan ketentuan penggunaan layanan '.\App\Models\SiteSetting::get('site_name', 'ArtaPedia').'.')

@section('content')
@php $siteName = \App\Models\SiteSetting::get('site_name', 'ArtaPedia'); @endphp
<article class="card max-w-4xl mx-auto p-6 md:p-10">
    <header class="border-b border-neutral-800 pb-6 mb-7">
        <div class="text-xs uppercase tracking-[.16em] font-bold accent mb-2">Informasi Legal</div>
        <h1 class="text-2xl md:text-3xl font-extrabold">Terms &amp; Conditions</h1>
        <p class="muted text-sm mt-2">Syarat dan Ketentuan · Berlaku sejak 8 September 2026</p>
    </header>

    <div class="space-y-7 text-base leading-7">
        <section>
            <h2 class="font-bold text-lg mb-2">1. Persetujuan pengguna</h2>
            <p class="muted">Dengan mengakses atau menggunakan {{ $siteName }}, Anda menyatakan telah membaca, memahami, dan menyetujui syarat ini. Jika tidak menyetujuinya, mohon tidak melanjutkan penggunaan layanan.</p>
        </section>

        <section>
            <h2 class="font-bold text-lg mb-2">2. Layanan</h2>
            <p class="muted">{{ $siteName }} menyediakan pembelian produk digital, termasuk top-up game, pulsa, paket data, voucher, dan layanan lain yang tersedia pada katalog. Harga, stok, nominal, metode pembayaran, serta estimasi proses dapat berubah mengikuti kondisi sistem dan penyedia layanan.</p>
        </section>

        <section>
            <h2 class="font-bold text-lg mb-2">3. Akun dan keamanan</h2>
            <p class="muted">Anda bertanggung jawab menjaga kerahasiaan akun, kata sandi, dan aktivitas yang dilakukan melalui akun Anda. Berikan informasi yang benar dan segera hubungi kami jika menemukan penggunaan akun tanpa izin.</p>
        </section>

        <section>
            <h2 class="font-bold text-lg mb-2">4. Pemesanan dan pembayaran</h2>
            <ul class="list-disc pl-5 space-y-2 muted">
                <li>Periksa produk, nominal, User ID, zone, nomor tujuan, email, dan informasi lain sebelum membayar.</li>
                <li>Pesanan diproses setelah pembayaran berhasil dikonfirmasi oleh sistem atau mitra pembayaran.</li>
                <li>Status transaksi dapat tertunda ketika terjadi gangguan pada bank, gateway pembayaran, supplier, operator, atau penerbit produk.</li>
                <li>Biaya layanan atau biaya gateway akan ditampilkan sebelum pesanan dikonfirmasi.</li>
            </ul>
        </section>

        <section>
            <h2 class="font-bold text-lg mb-2">5. Kesalahan data tujuan</h2>
            <p class="muted">Anda bertanggung jawab memastikan seluruh data tujuan benar. Produk digital yang telah berhasil dikirim ke tujuan yang Anda masukkan umumnya tidak dapat dibatalkan, dipindahkan, atau dikembalikan.</p>
        </section>

        <section>
            <h2 class="font-bold text-lg mb-2">6. Pembatalan dan pengembalian dana</h2>
            <p class="muted">Pengembalian dana dapat diberikan apabila pembayaran diterima tetapi transaksi dinyatakan gagal dan produk belum terkirim. Pengembalian diproses ke saldo akun atau metode lain yang kami tentukan berdasarkan hasil pemeriksaan. Pesanan sukses dan kesalahan data dari pengguna tidak memenuhi syarat pengembalian dana, kecuali diwajibkan oleh hukum.</p>
        </section>

        <section>
            <h2 class="font-bold text-lg mb-2">7. Penggunaan yang dilarang</h2>
            <p class="muted">Anda dilarang menggunakan layanan untuk penipuan, transaksi melanggar hukum, eksploitasi celah sistem, gangguan keamanan, manipulasi pembayaran, atau tindakan lain yang merugikan pengguna, {{ $siteName }}, maupun mitra kami. Kami dapat membatasi atau menutup akses yang terindikasi melanggar.</p>
        </section>

        <section>
            <h2 class="font-bold text-lg mb-2">8. Layanan pihak ketiga</h2>
            <p class="muted">Pemrosesan pembayaran dan pengiriman produk melibatkan penyedia pihak ketiga. Gangguan pada layanan mereka dapat memengaruhi waktu proses. Kami akan membantu menelusuri transaksi dan menyampaikan status berdasarkan informasi yang tersedia.</p>
        </section>

        <section>
            <h2 class="font-bold text-lg mb-2">9. Batas tanggung jawab</h2>
            <p class="muted">Sejauh diizinkan hukum, tanggung jawab kami atas suatu transaksi dibatasi pada nilai transaksi terkait. Ketentuan ini tidak mengurangi hak konsumen yang tidak dapat dikesampingkan berdasarkan peraturan yang berlaku.</p>
        </section>

        <section>
            <h2 class="font-bold text-lg mb-2">10. Perubahan ketentuan</h2>
            <p class="muted">Kami dapat memperbarui ketentuan ini untuk menyesuaikan layanan atau peraturan. Versi terbaru akan ditampilkan pada halaman ini beserta tanggal berlakunya.</p>
        </section>

        <section>
            <h2 class="font-bold text-lg mb-2">11. Kontak</h2>
            <p class="muted">Pertanyaan mengenai ketentuan ini dapat disampaikan melalui WhatsApp {{ \App\Models\SiteSetting::get('contact_whatsapp', '-') }} atau email {{ \App\Models\SiteSetting::get('contact_email', '-') }}.</p>
        </section>
    </div>
</article>
@endsection
