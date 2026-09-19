<?php

namespace Tests\Feature;

use App\Models\BalanceMutation;
use App\Models\ManualTopup;
use App\Models\SiteSetting;
use App\Models\Transaction;
use App\Models\User;
use App\Services\ManualTopupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ManualTopupTest extends TestCase
{
    use RefreshDatabase;

    protected function enableManualTopup(): void
    {
        SiteSetting::set('manual_topup_enabled', '1');
        SiteSetting::set('manual_topup_min', '10000');
        SiteSetting::set('manual_topup_banks', json_encode([
            ['name' => 'BCA', 'account_name' => 'ArtaPedia', 'account_number' => '1234567890'],
            ['name' => 'Mandiri', 'account_name' => 'ArtaPedia', 'account_number' => '9876543210'],
        ]));
    }

    protected function submitTopup(User $user, int $amount = 50000): ManualTopup
    {
        Storage::fake('public');

        return app(ManualTopupService::class)->submit(
            $user,
            $amount,
            'BCA',
            'Budi',
            // fake()->image() butuh ekstensi GD yang tidak terpasang di build PHP
            // ini; create() dengan mime eksplisit cukup untuk menguji validasi.
            UploadedFile::fake()->create('bukti.jpg', 100, 'image/jpeg'),
        );
    }

    public function test_member_dapat_mengajukan_topup_manual_dengan_bukti(): void
    {
        $this->enableManualTopup();
        $user = User::factory()->create(['level' => 'member']);

        $topup = $this->submitTopup($user);

        $this->assertSame(ManualTopup::STATUS_PENDING, $topup->status);
        $this->assertSame(50000, $topup->amount);
        $this->assertSame('BCA', $topup->bank_name);
        $this->assertSame('1234567890', $topup->bank_account_number);
        $this->assertNotNull($topup->proof_path);
        Storage::disk('public')->assertExists($topup->proof_path);
    }

    public function test_topup_di_bawah_minimum_ditolak(): void
    {
        $this->enableManualTopup();
        $user = User::factory()->create(['level' => 'member']);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Minimum topup manual');

        $this->submitTopup($user, 5000);
    }

    public function test_bank_yang_tidak_dikenal_ditolak(): void
    {
        $this->enableManualTopup();
        $user = User::factory()->create(['level' => 'member']);

        Storage::fake('public');

        $this->expectException(\RuntimeException::class);

        app(ManualTopupService::class)->submit(
            $user, 50000, 'Bank Palsu', 'Budi', UploadedFile::fake()->create('bukti.jpg', 100, 'image/jpeg'),
        );
    }

    public function test_approve_menambah_saldo_dan_mencatat_ledger(): void
    {
        $this->enableManualTopup();
        $user = User::factory()->create(['level' => 'member', 'balance' => 0]);
        $admin = User::factory()->create(['level' => 'admin']);

        $topup = $this->submitTopup($user);

        $approved = app(ManualTopupService::class)->approve($topup, $admin, 'Transfer valid');

        $this->assertSame(ManualTopup::STATUS_APPROVED, $approved->status);
        $this->assertSame($admin->id, $approved->reviewed_by);
        $this->assertNotNull($approved->reviewed_at);
        $this->assertSame(50000, $user->fresh()->balance);

        // Entri ledger harus tertaut ke transaksi supaya rekonsiliasi seimbang.
        $mutation = BalanceMutation::where('user_id', $user->id)->first();
        $this->assertNotNull($mutation);
        $this->assertSame(BalanceMutation::TYPE_TOPUP, $mutation->type);
        $this->assertSame(50000, $mutation->amount);
        $this->assertSame(0, $mutation->balance_before);
        $this->assertSame(50000, $mutation->balance_after);
        $this->assertSame($admin->id, $mutation->created_by);
        $this->assertNotNull($mutation->transaction_id);

        $this->assertNotNull($approved->transaction_id);
        $trx = Transaction::find($approved->transaction_id);
        $this->assertSame(Transaction::STATUS_SUCCESS, $trx->status);
        $this->assertSame('manual', $trx->payment_method);
        $this->assertSame(50000, $trx->total_amount);
    }

    /**
     * Approve ganda adalah bug saldo yang paling mahal di alur topup manual.
     * Penguncian baris + cek status di dalam transaksi yang sama harus membuat
     * percobaan kedua gagal, bukan mengkredit saldo dua kali.
     */
    public function test_double_approve_tidak_menambah_saldo_dua_kali(): void
    {
        $this->enableManualTopup();
        $user = User::factory()->create(['level' => 'member', 'balance' => 0]);
        $admin = User::factory()->create(['level' => 'admin']);

        $topup = $this->submitTopup($user);

        app(ManualTopupService::class)->approve($topup, $admin);

        try {
            app(ManualTopupService::class)->approve($topup->fresh(), $admin);
            $this->fail('Approve kedua seharusnya ditolak.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('sudah direview', $e->getMessage());
        }

        $this->assertSame(50000, $user->fresh()->balance, 'Saldo tidak boleh bertambah dua kali.');
        $this->assertSame(1, BalanceMutation::where('user_id', $user->id)->count());
        $this->assertSame(1, Transaction::where('user_id', $user->id)->count());
    }

    public function test_approve_setelah_reject_ditolak(): void
    {
        $this->enableManualTopup();
        $user = User::factory()->create(['level' => 'member', 'balance' => 0]);
        $admin = User::factory()->create(['level' => 'admin']);

        $topup = $this->submitTopup($user);

        app(ManualTopupService::class)->reject($topup, $admin, 'Bukti tidak jelas');

        $this->expectException(\RuntimeException::class);
        app(ManualTopupService::class)->approve($topup->fresh(), $admin);
    }

    public function test_reject_tidak_mengubah_saldo(): void
    {
        $this->enableManualTopup();
        $user = User::factory()->create(['level' => 'member', 'balance' => 0]);
        $admin = User::factory()->create(['level' => 'admin']);

        $topup = $this->submitTopup($user);

        $rejected = app(ManualTopupService::class)->reject($topup, $admin, 'Nominal tidak sesuai');

        $this->assertSame(ManualTopup::STATUS_REJECTED, $rejected->status);
        $this->assertSame('Nominal tidak sesuai', $rejected->review_note);
        $this->assertSame(0, $user->fresh()->balance);
        $this->assertSame(0, BalanceMutation::count());
    }

    public function test_saldo_setelah_approve_konsisten_dengan_ledger(): void
    {
        $this->enableManualTopup();
        $user = User::factory()->create(['level' => 'member', 'balance' => 0]);
        $admin = User::factory()->create(['level' => 'admin']);

        app(ManualTopupService::class)->approve($this->submitTopup($user), $admin);

        $ledgerSum = (int) BalanceMutation::where('user_id', $user->id)->sum('amount');

        $this->assertSame($ledgerSum, $user->fresh()->balance);
    }

    public function test_halaman_topup_manual_menampilkan_rekening_admin(): void
    {
        $this->enableManualTopup();
        $user = User::factory()->create(['level' => 'member']);

        $this->actingAs($user)
            ->get('/member/topup-manual')
            ->assertOk()
            ->assertSee('BCA')
            ->assertSee('1234567890')
            ->assertSee('ArtaPedia');
    }

    public function test_halaman_topup_manual_memberi_tahu_saat_dinonaktifkan(): void
    {
        SiteSetting::set('manual_topup_enabled', '0');
        $user = User::factory()->create(['level' => 'member']);

        $this->actingAs($user)
            ->get('/member/topup-manual')
            ->assertOk()
            ->assertSee('sedang tidak tersedia');
    }

    public function test_guest_tidak_bisa_membuka_topup_manual(): void
    {
        $this->get('/member/topup-manual')->assertRedirect('/login');
    }

    public function test_unggahan_bukan_gambar_ditolak(): void
    {
        $this->enableManualTopup();
        $user = User::factory()->create(['level' => 'member']);

        Storage::fake('public');

        $this->actingAs($user)
            ->post('/member/topup-manual', [
                'amount' => 50000,
                'bank_name' => 'BCA',
                'proof' => UploadedFile::fake()->create('virus.php', 10, 'application/x-php'),
            ])
            ->assertSessionHasErrors('proof');

        $this->assertSame(0, ManualTopup::count());
    }
}
