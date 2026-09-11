<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoiceCheckTest extends TestCase
{
    use RefreshDatabase;

    public function test_hasil_cek_invoice_menampilkan_rincian_transaksi_lengkap(): void
    {
        $member = User::factory()->create();
        $product = Product::create([
            'supplier_code' => 'INVOICE-ML-1',
            'name' => '86 Diamonds',
            'game' => 'Mobile Legends',
            'product_type' => Product::TYPE_GAME,
            'cost_basic' => 10000,
            'cost_premium' => 9500,
            'cost_special' => 9000,
            'price_guest' => 12000,
            'price_biasa' => 11500,
            'price_vip' => 11000,
            'is_active' => true,
            'in_stock' => true,
        ]);
        $transaction = Transaction::create([
            'invoice_code' => 'INV-DETAIL-LENGKAP',
            'user_id' => $member->id,
            'product_id' => $product->id,
            'target_user_id' => '12345678',
            'target_zone' => '1234',
            'nickname' => 'ArtaPlayer',
            'quantity' => 2,
            'cost_price' => 20000,
            'sell_price' => 24000,
            'admin_fee' => 0,
            'gateway_fee' => 1000,
            'total_amount' => 25000,
            'profit' => 4000,
            'payment_gateway_code' => 'balance',
            'payment_method' => 'balance',
            'status' => Transaction::STATUS_SUCCESS,
        ]);

        $this->get(route('invoice.show', ['code' => $transaction->invoice_code]))
            ->assertOk()
            ->assertSee('INV-DETAIL-LENGKAP')
            ->assertSee('86 Diamonds')
            ->assertSee('ID Game')
            ->assertSee('12345678')
            ->assertSee('Server / Zone: 1234')
            ->assertSee('Nickname')
            ->assertSee('ArtaPlayer')
            ->assertSee('Jumlah Pesanan')
            ->assertSee('2 item')
            ->assertSee('Total Pembayaran')
            ->assertSee('Rp 25.000')
            ->assertSee('Metode Pembayaran')
            ->assertSee('Saldo Member')
            ->assertSee('Status Transaksi')
            ->assertSee('Transaksi berhasil')
            ->assertSee('Waktu Transaksi')
            ->assertSee('WIB')
            ->assertDontSee('WITA')
            ->assertDontSee('Supplier trx id')
            ->assertDontSee('Status supplier');

        $this->actingAs($member)
            ->get(route('member.dashboard'))
            ->assertOk()
            ->assertSee(route('invoice.show', ['code' => $transaction->invoice_code]), false);
    }
}
