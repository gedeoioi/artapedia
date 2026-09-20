<?php

namespace Tests\Feature;

use App\Filament\Resources\WaNotificationSettings\Pages\EditWaNotificationSetting;
use App\Jobs\SendWaNotification;
use App\Models\Transaction;
use App\Models\User;
use App\Models\WaMessage;
use App\Models\WaNotificationSetting;
use App\Services\WaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Notifikasi WhatsApp: dulu job-nya didefinisikan tapi TIDAK PERNAH
 * di-dispatch, jadi tidak ada satu pun pesan yang terkirim. Test di sini
 * menjaga rantai itu tetap tersambung, dan menjaga supaya kegagalan gateway
 * tidak diam-diam dianggap berhasil.
 */
class WaNotificationTest extends TestCase
{
    use RefreshDatabase;

    protected function configureWa(array $over = []): WaNotificationSetting
    {
        return WaNotificationSetting::updateOrCreate(['name' => 'trx_status'], array_merge([
            'is_active' => true,
            'api_url' => 'https://api.deoioi.my.id',
            'api_token' => 'wag_uji',
            'template' => 'Invoice {invoice} status {status}.',
            'schedule' => 'on_event',
        ], $over));
    }

    protected function transaksi(array $over = []): Transaction
    {
        return Transaction::create(array_merge([
            'invoice_code' => 'INV-WA-'.uniqid(),
            'buyer_phone' => '081234567890',
            'target_user_id' => '12345',
            'quantity' => 1,
            'cost_price' => 1000,
            'sell_price' => 1200,
            'admin_fee' => 0,
            'gateway_fee' => 0,
            'total_amount' => 1200,
            'profit' => 200,
            'payment_method' => 'balance',
            'status' => Transaction::STATUS_PENDING,
        ], $over));
    }

    // ---------- Rantai dispatch: inilah yang dulu putus ----------

    public function test_status_final_menjadwalkan_notifikasi(): void
    {
        Queue::fake();
        $this->configureWa();

        $trx = $this->transaksi();
        $trx->status = Transaction::STATUS_SUCCESS;
        $trx->save();
        $trx->notifyBuyer();

        Queue::assertPushed(SendWaNotification::class, fn ($job) => $job->transactionId === $trx->id);
    }

    /**
     * Simpan ulang pada transaksi yang sudah final tidak boleh mengirim lagi —
     * kalau tidak, setiap update kecil mengirim ulang notifikasi ke pembeli.
     */
    public function test_simpan_ulang_tidak_mengirim_ulang(): void
    {
        Queue::fake();
        $this->configureWa();

        $trx = $this->transaksi();
        $trx->status = Transaction::STATUS_SUCCESS;
        $trx->save();
        $trx->notifyBuyer();

        // Instance baru, status tidak berubah -> tidak ada notifikasi kedua.
        $trx->refresh();
        $trx->notes = 'catatan admin';
        $trx->save();
        $trx->notifyBuyer();

        Queue::assertPushed(SendWaNotification::class, 1);
    }

    public function test_status_bukan_final_tidak_mengirim(): void
    {
        Queue::fake();
        $this->configureWa();

        $trx = $this->transaksi();
        $trx->status = Transaction::STATUS_PROCESSING;
        $trx->save();
        $trx->notifyBuyer();

        Queue::assertNothingPushed();
    }

    public function test_tanpa_nomor_pembeli_tidak_mengirim(): void
    {
        Queue::fake();
        $this->configureWa();

        $trx = $this->transaksi(['buyer_phone' => null]);
        $trx->status = Transaction::STATUS_SUCCESS;
        $trx->save();
        $trx->notifyBuyer();

        Queue::assertNothingPushed();
    }

    /** Notifikasi dimatikan admin -> job jalan tapi tidak mengirim apa pun. */
    public function test_notifikasi_mati_tidak_mengirim(): void
    {
        Http::fake();
        $this->configureWa(['is_active' => false]);

        $trx = $this->transaksi();
        $trx->status = Transaction::STATUS_SUCCESS;
        $trx->save();

        (new SendWaNotification($trx->id))->handle(app(WaService::class));

        Http::assertNothingSent();
    }

    // ---------- Kontrak gateway ----------

    public function test_payload_dan_header_sesuai_kontrak_gateway(): void
    {
        Http::fake(['api.deoioi.my.id/*' => Http::response(['success' => true, 'data' => ['status' => 'sent']], 201)]);
        $this->configureWa();

        $trx = $this->transaksi();
        $trx->status = Transaction::STATUS_SUCCESS;
        $trx->save();

        (new SendWaNotification($trx->id))->handle(app(WaService::class));

        Http::assertSent(function ($request) use ($trx) {
            $body = json_decode($request->body(), true);

            return $request->url() === 'https://api.deoioi.my.id/api/send-message'
                && $request->hasHeader('X-API-Key', 'wag_uji')
                && ($body['to'] ?? null) === '6281234567890'
                && str_contains((string) ($body['body'] ?? ''), $trx->invoice_code);
        });
    }

    public function test_http_201_tapi_success_false_dianggap_gagal(): void
    {
        Http::fake(['api.deoioi.my.id/*' => Http::response(['success' => false, 'error' => 'Device tidak terhubung'], 201)]);
        $this->configureWa();

        $trx = $this->transaksi();
        $hasil = app(WaService::class)->send($trx->buyer_phone, 'uji', 'trx_status', $trx->id);

        $this->assertFalse($hasil['ok'], 'HTTP 201 bukan jaminan terkirim');
        $this->assertSame('Device tidak terhubung', $hasil['error']);
    }

    public function test_401_dicatat_dengan_pesan_gateway(): void
    {
        Http::fake(['api.deoioi.my.id/*' => Http::response(['error' => 'API key tidak valid.'], 401)]);
        $this->configureWa();

        $trx = $this->transaksi();
        $hasil = app(WaService::class)->send($trx->buyer_phone, 'uji', 'trx_status', $trx->id);

        $this->assertFalse($hasil['ok']);
        $this->assertSame(401, $hasil['http_status']);
        $this->assertStringContainsString('API key tidak valid', (string) $hasil['error']);
    }

    // ---------- Normalisasi nomor ----------

    public function test_nomor_dinormalisasi_ke_62(): void
    {
        $wa = app(WaService::class);

        $this->assertSame('6281234567890', $wa->normalizePhone('081234567890'));
        $this->assertSame('6281234567890', $wa->normalizePhone('+62 812-3456-7890'));
        $this->assertSame('6281234567890', $wa->normalizePhone('6281234567890'));
        $this->assertSame('6281234567890', $wa->normalizePhone('81234567890'));
        $this->assertSame('6281234567890', $wa->normalizePhone('(0812) 3456 7890'));
        $this->assertSame('', $wa->normalizePhone(''));
        $this->assertSame('', $wa->normalizePhone('abc'));
    }

    // ---------- URL gateway ----------

    public function test_url_host_saja_ditambah_path(): void
    {
        $wa = app(WaService::class);

        $this->assertSame('https://api.deoioi.my.id/api/send-message', $wa->resolveUrl('https://api.deoioi.my.id'));
        $this->assertSame('https://api.deoioi.my.id/api/send-message', $wa->resolveUrl('https://api.deoioi.my.id/'));
        // Sudah lengkap -> tidak ditambah dua kali
        $this->assertSame('https://api.deoioi.my.id/api/send-message', $wa->resolveUrl('https://api.deoioi.my.id/api/send-message'));
    }

    // ---------- Log kirim ----------

    public function test_percobaan_ulang_menambah_attempts_bukan_baris(): void
    {
        Http::fake(['api.deoioi.my.id/*' => Http::response(['error' => 'gagal'], 500)]);
        $this->configureWa();

        $trx = $this->transaksi();
        $wa = app(WaService::class);

        $wa->send($trx->buyer_phone, 'uji', 'trx_status', $trx->id);
        $wa->send($trx->buyer_phone, 'uji', 'trx_status', $trx->id);
        $wa->send($trx->buyer_phone, 'uji', 'trx_status', $trx->id);

        $this->assertSame(1, WaMessage::count(), 'harus satu baris per (transaksi, jenis)');
        $this->assertSame(3, (int) WaMessage::first()->attempts);
        $this->assertSame(WaMessage::STATUS_FAILED, WaMessage::first()->status);
    }

    public function test_pesan_berisi_kutip_dan_baris_baru_tetap_json_valid(): void
    {
        Http::fake(['api.deoioi.my.id/*' => Http::response(['success' => true], 201)]);
        $this->configureWa();

        $trx = $this->transaksi();
        $pesan = "Pesan dengan \"kutip\" dan\nbaris baru & simbol \\ backslash";

        app(WaService::class)->send($trx->buyer_phone, $pesan, 'trx_status', $trx->id);

        Http::assertSent(function ($request) use ($pesan) {
            $body = json_decode($request->body(), true);

            // Kalau escaping salah, JSON tidak bisa di-decode atau isinya berubah.
            return is_array($body) && ($body['body'] ?? null) === $pesan;
        });
    }

    public function test_log_menyimpan_status_dan_waktu_terkirim(): void
    {
        Http::fake(['api.deoioi.my.id/*' => Http::response(['success' => true], 201)]);
        $this->configureWa();

        $trx = $this->transaksi();
        app(WaService::class)->send($trx->buyer_phone, 'uji', 'trx_status', $trx->id);

        $log = WaMessage::first();
        $this->assertSame(WaMessage::STATUS_SENT, $log->status);
        $this->assertSame(201, $log->http_status);
        $this->assertNotNull($log->sent_at);
    }

    // ---------- Koneksi bersama ----------

    /**
     * Baris yang hanya berisi template (tanpa URL/token) harus memakai koneksi
     * bersama, atau pesannya resolve ke null dan tidak pernah terkirim.
     */
    public function test_baris_tanpa_url_memakai_koneksi_bersama(): void
    {
        $this->configureWa();

        WaNotificationSetting::updateOrCreate(['name' => 'daily_recap'], [
            'is_active' => true,
            'template' => 'Rekap harian',
            'schedule' => 'daily',
            'api_url' => null,
            'api_token' => null,
        ]);

        $wa = app(WaService::class);

        $this->assertTrue($wa->isConfigured('daily_recap'));
        $this->assertSame('https://api.deoioi.my.id/api/send-message', $wa->connection('daily_recap')['url']);
    }

    public function test_tanpa_kredensial_sama_sekali_gagal_dengan_pesan_jelas(): void
    {
        $trx = $this->transaksi();
        $hasil = app(WaService::class)->send($trx->buyer_phone, 'uji', 'trx_status', $trx->id);

        $this->assertFalse($hasil['ok']);
        $this->assertStringContainsString('belum dikonfigurasi', (string) $hasil['error']);
    }

    /**
     * Inti masalah sebelumnya: kolom api_token ada di tabel dan dipakai kode,
     * tapi TIDAK ADA di form admin — jadi operator tidak punya cara mengisinya
     * dan pengiriman selalu gagal.
     */
    public function test_api_token_bisa_disimpan_dari_form_admin(): void
    {
        $admin = User::factory()->create(['level' => 'admin']);
        $admin->assignRole(Role::firstOrCreate(['name' => 'admin']));
        $this->actingAs($admin);

        // Barisnya dibuat oleh seeder, yang tidak jalan di test.
        $setting = $this->configureWa(['api_token' => '', 'api_url' => '']);

        Livewire::test(EditWaNotificationSetting::class, [
            'record' => $setting->getRouteKey(),
        ])
            ->fillForm([
                'api_url' => 'https://api.deoioi.my.id',
                'api_token' => 'wag_dari_admin',
                'is_active' => true,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $setting = WaNotificationSetting::where('name', 'trx_status')->firstOrFail();

        $this->assertSame('wag_dari_admin', $setting->api_token, 'token harus tersimpan dari form');
        $this->assertTrue($setting->is_active);
        $this->assertTrue(app(WaService::class)->isConfigured('trx_status'));
    }
}
