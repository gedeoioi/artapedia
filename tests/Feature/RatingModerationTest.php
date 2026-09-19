<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Rating;
use App\Models\SupplierConfig;
use App\Models\Transaction;
use App\Models\User;
use App\Support\ProfanityFilter;
use App\Suppliers\VipResellerProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RatingModerationTest extends TestCase
{
    use RefreshDatabase;

    protected function seedProduct(): Product
    {
        $supplier = SupplierConfig::create([
            'code' => 'vip-reseller', 'name' => 'VIP', 'provider_class' => VipResellerProvider::class,
            'is_active' => true, 'is_sandbox' => true, 'priority' => 0,
            'credentials' => ['api_id' => 'x', 'api_key' => 'y'],
        ]);

        return Product::create([
            'supplier_config_id' => $supplier->id, 'supplier_code' => 'ML-100',
            'name' => 'ML 100 Diamond', 'game' => 'Mobile Legends',
            'cost_basic' => 10000, 'cost_premium' => 9500, 'cost_special' => 9000,
            'price_guest' => 12000, 'price_biasa' => 11500, 'price_vip' => 11000,
            'is_active' => true, 'in_stock' => true,
        ]);
    }

    protected function successTransaction(User $user, Product $product): Transaction
    {
        return Transaction::create([
            'invoice_code' => 'INV-RATE-'.uniqid(),
            'user_id' => $user->id,
            'product_id' => $product->id,
            'supplier_config_id' => $product->supplier_config_id,
            'payment_gateway_code' => 'balance', 'target_user_id' => '123',
            'quantity' => 1, 'cost_price' => 10000, 'sell_price' => 12000,
            'admin_fee' => 0, 'gateway_fee' => 0, 'total_amount' => 12000, 'profit' => 2000,
            'payment_method' => 'balance', 'status' => Transaction::STATUS_SUCCESS,
            'paid_at' => now(),
        ]);
    }

    public function test_rating_baru_masuk_antrean_moderasi_bukan_langsung_tayang(): void
    {
        $product = $this->seedProduct();
        $user = User::factory()->create();
        $trx = $this->successTransaction($user, $product);

        $this->actingAs($user)
            ->post("/member/rate/{$trx->id}", ['stars' => 5, 'comment' => 'Cepat sekali'])
            ->assertRedirect();

        $rating = Rating::first();
        $this->assertSame(Rating::STATUS_PENDING, $rating->status);
        $this->assertSame(5, $rating->stars);
        $this->assertNull($rating->moderation_note);
    }

    public function test_rating_pending_tidak_tampil_di_beranda(): void
    {
        $product = $this->seedProduct();
        $user = User::factory()->create();
        $trx = $this->successTransaction($user, $product);

        Rating::create([
            'transaction_id' => $trx->id, 'user_id' => $user->id,
            'stars' => 5, 'comment' => 'Komentar rahasia belum dimoderasi',
            'status' => Rating::STATUS_PENDING,
        ]);

        $this->get('/')
            ->assertOk()
            ->assertDontSee('Komentar rahasia belum dimoderasi');
    }

    public function test_rating_approved_tampil_di_beranda_dan_halaman_game(): void
    {
        $product = $this->seedProduct();
        $user = User::factory()->create();
        $trx = $this->successTransaction($user, $product);

        Rating::create([
            'transaction_id' => $trx->id, 'user_id' => $user->id,
            'stars' => 5, 'comment' => 'Prosesnya cepat dan aman',
            'status' => Rating::STATUS_APPROVED,
        ]);

        $this->get('/')->assertOk()->assertSee('Prosesnya cepat dan aman');
        $this->get('/game/Mobile%20Legends')->assertOk()->assertSee('Prosesnya cepat dan aman');
    }

    public function test_rating_hidden_tidak_tampil(): void
    {
        $product = $this->seedProduct();
        $user = User::factory()->create();
        $trx = $this->successTransaction($user, $product);

        Rating::create([
            'transaction_id' => $trx->id, 'user_id' => $user->id,
            'stars' => 1, 'comment' => 'Komentar yang disembunyikan admin',
            'status' => Rating::STATUS_HIDDEN,
        ]);

        $this->get('/')->assertOk()->assertDontSee('Komentar yang disembunyikan admin');
        $this->get('/review')->assertOk()->assertDontSee('Komentar yang disembunyikan admin');
    }

    public function test_satu_rating_per_invoice_selamanya(): void
    {
        $product = $this->seedProduct();
        $user = User::factory()->create();
        $trx = $this->successTransaction($user, $product);

        $this->actingAs($user)->post("/member/rate/{$trx->id}", ['stars' => 5]);

        $this->actingAs($user)
            ->post("/member/rate/{$trx->id}", ['stars' => 1])
            ->assertSessionHasErrors('stars');

        $this->assertSame(1, Rating::count());
        $this->assertSame(5, Rating::first()->stars);
    }

    public function test_unique_index_mencegah_rating_ganda_di_level_db(): void
    {
        $product = $this->seedProduct();
        $user = User::factory()->create();
        $trx = $this->successTransaction($user, $product);

        Rating::create([
            'transaction_id' => $trx->id, 'user_id' => $user->id, 'stars' => 5,
            'status' => Rating::STATUS_PENDING,
        ]);

        $this->expectException(\Illuminate\Database\QueryException::class);

        Rating::create([
            'transaction_id' => $trx->id, 'user_id' => $user->id, 'stars' => 3,
            'status' => Rating::STATUS_PENDING,
        ]);
    }

    public function test_hanya_pemilik_invoice_yang_bisa_menilai(): void
    {
        $product = $this->seedProduct();
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $trx = $this->successTransaction($owner, $product);

        $this->actingAs($other)
            ->post("/member/rate/{$trx->id}", ['stars' => 5])
            ->assertForbidden();

        $this->assertSame(0, Rating::count());
    }

    public function test_transaksi_belum_sukses_tidak_bisa_dinilai(): void
    {
        $product = $this->seedProduct();
        $user = User::factory()->create();
        $trx = $this->successTransaction($user, $product);
        $trx->forceFill(['status' => Transaction::STATUS_PENDING])->save();

        $this->actingAs($user)
            ->post("/member/rate/{$trx->id}", ['stars' => 5])
            ->assertStatus(422);

        $this->assertSame(0, Rating::count());
    }

    public function test_komentar_kata_kasar_ditandai_untuk_moderasi(): void
    {
        $product = $this->seedProduct();
        $user = User::factory()->create();
        $trx = $this->successTransaction($user, $product);

        $this->actingAs($user)
            ->post("/member/rate/{$trx->id}", ['stars' => 1, 'comment' => 'Pelayanan anjing banget'])
            ->assertRedirect();

        $rating = Rating::first();

        // Tidak dibuang: tetap tersimpan, tapi diberi catatan untuk admin.
        $this->assertSame('Pelayanan anjing banget', $rating->comment);
        $this->assertNotNull($rating->moderation_note);
        $this->assertStringContainsString('anjing', $rating->moderation_note);
        $this->assertSame(Rating::STATUS_PENDING, $rating->status);
    }

    public function test_komentar_bersih_tidak_ditandai(): void
    {
        $product = $this->seedProduct();
        $user = User::factory()->create();
        $trx = $this->successTransaction($user, $product);

        $this->actingAs($user)
            ->post("/member/rate/{$trx->id}", ['stars' => 5, 'comment' => 'Mantap, cepat, terpercaya'])
            ->assertRedirect();

        $this->assertNull(Rating::first()->moderation_note);
    }

    public function test_halaman_review_publik_hanya_menampilkan_yang_disetujui(): void
    {
        $product = $this->seedProduct();
        $user = User::factory()->create();

        $approved = $this->successTransaction($user, $product);
        $pending = $this->successTransaction($user, $product);

        Rating::create([
            'transaction_id' => $approved->id, 'user_id' => $user->id,
            'stars' => 5, 'comment' => 'Ulasan yang sudah tayang', 'status' => Rating::STATUS_APPROVED,
        ]);
        Rating::create([
            'transaction_id' => $pending->id, 'user_id' => $user->id,
            'stars' => 2, 'comment' => 'Ulasan yang belum tayang', 'status' => Rating::STATUS_PENDING,
        ]);

        $this->get('/review')
            ->assertOk()
            ->assertSee('Ulasan yang sudah tayang')
            ->assertDontSee('Ulasan yang belum tayang')
            ->assertSee('1 ulasan');
    }

    public function test_profanity_filter_menangkap_varian_karakter(): void
    {
        $this->assertTrue(ProfanityFilter::contains('dasar anjing kamu'));
        $this->assertTrue(ProfanityFilter::contains('ANJING'));
        $this->assertTrue(ProfanityFilter::contains('anjiiiing'));

        // Batas kata: tidak boleh memicu kata lain yang hanya mirip.
        $this->assertFalse(ProfanityFilter::contains('kasari'));
        $this->assertFalse(ProfanityFilter::contains('mantap jiwa'));
        $this->assertFalse(ProfanityFilter::contains(''));
    }

    public function test_admin_bisa_menyetujui_dan_menyembunyikan_rating(): void
    {
        $product = $this->seedProduct();
        $user = User::factory()->create();
        $trx = $this->successTransaction($user, $product);

        $rating = Rating::create([
            'transaction_id' => $trx->id, 'user_id' => $user->id,
            'stars' => 4, 'comment' => 'Bagus', 'status' => Rating::STATUS_PENDING,
        ]);

        // Layar moderasi admin harus bisa dirender (menangkap error komponen).
        $admin = User::factory()->create(['level' => 'admin']);
        $admin->assignRole(\Spatie\Permission\Models\Role::firstOrCreate(['name' => 'admin']));

        $this->actingAs($admin)->get('/admin/ratings')->assertOk();

        $rating->forceFill(['status' => Rating::STATUS_APPROVED, 'moderation_note' => null])->save();
        $this->get('/review')->assertOk()->assertSee('Bagus');

        $rating->forceFill(['status' => Rating::STATUS_HIDDEN])->save();
        $this->get('/review')->assertOk()->assertDontSee('Bagus');
    }
}
