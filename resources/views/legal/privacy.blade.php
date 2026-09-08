@extends('layouts.shop')

@section('title', 'Privacy Policy')
@section('meta_description', 'Kebijakan privasi dan pengelolaan data pengguna '.\App\Models\SiteSetting::get('site_name', 'ArtaPedia').'.')

@section('content')
@php $siteName = \App\Models\SiteSetting::get('site_name', 'ArtaPedia'); @endphp
<article class="card max-w-4xl mx-auto p-6 md:p-10">
    <header class="border-b border-neutral-800 pb-6 mb-7">
        <div class="text-xs uppercase tracking-[.16em] font-bold accent mb-2">Informasi Legal</div>
        <h1 class="text-2xl md:text-3xl font-extrabold">Privacy Policy</h1>
        <p class="muted text-sm mt-2">Kebijakan Privasi · Berlaku sejak 8 September 2026</p>
    </header>

    <div class="space-y-7 text-base leading-7">
        <section>
            <h2 class="font-bold text-lg mb-2">1. Ruang lingkup</h2>
            <p class="muted">Kebijakan ini menjelaskan bagaimana {{ $siteName }} mengumpulkan, menggunakan, menyimpan, dan membagikan data ketika Anda mengakses situs, membuat akun, atau melakukan transaksi.</p>
        </section>

        <section>
            <h2 class="font-bold text-lg mb-2">2. Data yang kami kumpulkan</h2>
            <ul class="list-disc pl-5 space-y-2 muted">
                <li><strong class="text-inherit">Data akun:</strong> nama, alamat email, nomor telepon atau WhatsApp, dan kredensial yang tersimpan secara terlindungi.</li>
                <li><strong class="text-inherit">Data transaksi:</strong> produk, nilai transaksi, User ID atau nomor tujuan, zone, nickname, kontak pembeli, serta status pembayaran dan pengiriman.</li>
                <li><strong class="text-inherit">Data pembayaran:</strong> metode pembayaran, nomor referensi, jumlah, dan status dari gateway. Kami tidak menyimpan nomor kartu pembayaran lengkap.</li>
                <li><strong class="text-inherit">Data teknis:</strong> alamat IP, jenis perangkat atau browser, waktu akses, serta log yang diperlukan untuk keamanan dan pemecahan masalah.</li>
            </ul>
        </section>

        <section>
            <h2 class="font-bold text-lg mb-2">3. Cara kami menggunakan data</h2>
            <p class="muted">Data digunakan untuk membuat dan mengelola akun, memproses pembayaran dan pesanan, mengirim pembaruan transaksi, menyediakan dukungan, mencegah penipuan, menjaga keamanan, memperbaiki layanan, serta memenuhi kewajiban hukum dan pembukuan.</p>
        </section>

        <section>
            <h2 class="font-bold text-lg mb-2">4. Pembagian data</h2>
            <p class="muted">Kami dapat membagikan data yang diperlukan kepada gateway pembayaran, supplier produk digital, penyedia infrastruktur atau komunikasi, dan penasihat profesional. Data juga dapat diberikan kepada pihak berwenang jika diwajibkan oleh hukum. Kami tidak menjual data pribadi Anda.</p>
        </section>

        <section>
            <h2 class="font-bold text-lg mb-2">5. Cookie dan sesi</h2>
            <p class="muted">Situs menggunakan cookie atau teknologi serupa untuk mempertahankan sesi login, melindungi formulir, menyimpan preferensi yang diperlukan, dan menjaga keamanan layanan. Menonaktifkan cookie tertentu dapat menyebabkan sebagian fitur tidak berfungsi.</p>
        </section>

        <section>
            <h2 class="font-bold text-lg mb-2">6. Penyimpanan data</h2>
            <p class="muted">Data disimpan selama diperlukan untuk menyediakan layanan, menyelesaikan sengketa, mencegah penyalahgunaan, dan memenuhi kewajiban hukum, pajak, atau pembukuan. Setelah tidak diperlukan, data akan dihapus atau dianonimkan sesuai kemampuan teknis dan ketentuan yang berlaku.</p>
        </section>

        <section>
            <h2 class="font-bold text-lg mb-2">7. Keamanan</h2>
            <p class="muted">Kami menerapkan pengamanan teknis dan operasional yang wajar untuk melindungi data. Namun, tidak ada sistem elektronik yang sepenuhnya bebas risiko. Anda juga perlu menjaga kata sandi dan tidak membagikan kode akses kepada pihak lain.</p>
        </section>

        <section>
            <h2 class="font-bold text-lg mb-2">8. Hak dan pilihan Anda</h2>
            <p class="muted">Anda dapat meminta akses, koreksi, atau penghapusan data pribadi, serta menyampaikan keberatan atas penggunaan tertentu. Sebagian data transaksi mungkin tetap perlu disimpan apabila diwajibkan hukum atau diperlukan untuk kepentingan keamanan dan penyelesaian transaksi.</p>
        </section>

        <section>
            <h2 class="font-bold text-lg mb-2">9. Perubahan kebijakan</h2>
            <p class="muted">Kami dapat memperbarui kebijakan ini ketika layanan atau ketentuan hukum berubah. Versi terbaru dan tanggal berlakunya akan tersedia pada halaman ini.</p>
        </section>

        <section>
            <h2 class="font-bold text-lg mb-2">10. Kontak</h2>
            <p class="muted">Untuk pertanyaan atau permintaan terkait privasi, hubungi kami melalui WhatsApp {{ \App\Models\SiteSetting::get('contact_whatsapp', '-') }} atau email {{ \App\Models\SiteSetting::get('contact_email', '-') }}.</p>
        </section>
    </div>
</article>
@endsection
