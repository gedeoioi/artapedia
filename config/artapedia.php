<?php

use App\Models\Transaction;
use App\Payments\DuitkuGateway;
use App\Payments\IPaymuGateway;
use App\Payments\TripayGateway;
use App\Payments\XenditGateway;
use App\Suppliers\DigiflazzProvider;
use App\Suppliers\TokoVoucherProvider;
use App\Suppliers\VipResellerProvider;

return [
    /*
     * Jendela idempotensi order (detik). Request checkout yang identik dalam
     * rentang ini dianggap satu order. Jendelanya bergeser, bukan menempel di
     * batas menit, supaya tidak ada titik mati saat menit berganti.
     */
    'idempotency_window_seconds' => (int) env('ORDER_IDEMPOTENCY_WINDOW', 120),

    'initial_admin' => [
        'email' => env('INITIAL_ADMIN_EMAIL', 'admin@artapedia.id'),
        'password' => env('INITIAL_ADMIN_PASSWORD', ''),
    ],

    'suppliers' => [
        'vip-reseller' => VipResellerProvider::class,
        'digiflazz' => DigiflazzProvider::class,
        'toko-voucher' => TokoVoucherProvider::class,
    ],
    'gateways' => [
        // Tripay didaftarkan lebih dulu sebagai default (paling ramah untuk
        // usaha baru: onboarding cukup KTP) dan sort_order 0 di seeder.
        'tripay' => TripayGateway::class,
        'xendit' => XenditGateway::class,
        'duitku' => DuitkuGateway::class,
        'ipaymu' => IPaymuGateway::class,
    ],

    /*
     * Gateway WhatsApp.
     *
     * Kontrak API gateway yang dipakai (diverifikasi langsung ke endpoint-nya):
     *   POST {base_url}/api/send-message
     *   header: X-API-Key: <token>
     *   body:   {"to": "62812...", "body": "pesan"}
     *   sukses: 201 {"success": true, "data": {"status": "sent", ...}}
     *   gagal : 401 {"error": "..."} / 200 {"success": false, "error": "..."}
     *
     * base_url default menunjuk ke host API, BUKAN host situs pemasarannya —
     * keduanya domain berbeda dan hanya host API yang melayani /api/send-message.
     */
    'wa' => [
        'base_url' => env('WA_GATEWAY_URL', 'https://api.deoioi.my.id'),
        'send_path' => '/api/send-message',
        'timeout' => (int) env('WA_GATEWAY_TIMEOUT', 20),
        'connect_timeout' => (int) env('WA_GATEWAY_CONNECT_TIMEOUT', 8),

        // Nama header token bisa diubah kalau gateway berganti kontrak.
        'token_header' => env('WA_GATEWAY_TOKEN_HEADER', 'X-API-Key'),

        // Payload dikirim sebagai JSON dengan nilai yang di-escape, sehingga
        // pesan berisi kutip atau baris baru tidak bisa merusak bentuk JSON.
        // Dua bentuk didukung supaya gateway tipe form (Fonnte/Wablas) tetap bisa.
        'payload_template' => env(
            'WA_GATEWAY_PAYLOAD',
            '{"to": "{{phone}}", "body": "{{message}}"}'
        ),

        // Nama field pada payload form-encoded, dipakai kalau format = form.
        'format' => env('WA_GATEWAY_FORMAT', 'json'), // json | form
        'form_fields' => [
            'target' => env('WA_GATEWAY_FIELD_PHONE', 'target'),
            'message' => env('WA_GATEWAY_FIELD_MESSAGE', 'message'),
        ],

        // Batas percobaan job; setelah habis, pesan ditandai gagal (tidak dibuang)
        // supaya bisa dikirim ulang manual dari admin.
        'tries' => (int) env('WA_GATEWAY_TRIES', 3),
        'backoff' => [10, 60, 300],

        // Status transaksi yang memicu notifikasi ke pembeli.
        'notify_statuses' => [
            Transaction::STATUS_SUCCESS,
            Transaction::STATUS_FAILED,
            Transaction::STATUS_EXPIRED,
        ],
    ],
];
