<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Transaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentStatusTest extends TestCase
{
    use RefreshDatabase;

    public function test_halaman_processing_menampilkan_proses_supplier_bukan_menunggu_pembayaran(): void
    {
        $product = Product::create([
            'supplier_code' => 'ML5', 'name' => '5 Diamonds', 'game' => 'Mobile Legends',
            'cost_basic' => 1000, 'cost_premium' => 1000, 'cost_special' => 1000,
            'price_guest' => 1200, 'price_biasa' => 1150, 'price_vip' => 1100,
            'is_active' => true, 'in_stock' => true,
        ]);
        $trx = Transaction::create([
            'invoice_code' => 'INV-STATUS-1', 'product_id' => $product->id,
            'payment_gateway_code' => 'balance', 'target_user_id' => '123456',
            'quantity' => 1, 'cost_price' => 1000, 'sell_price' => 1200,
            'admin_fee' => 0, 'gateway_fee' => 0, 'total_amount' => 1200, 'profit' => 200,
            'payment_method' => 'balance', 'status' => Transaction::STATUS_PROCESSING,
            'supplier_trx_id' => 'VP123', 'supplier_status' => 'waiting', 'paid_at' => now(),
        ]);

        $this->get(route('payment.show', $trx->invoice_code))
            ->assertOk()
            ->assertSee('Sedang diproses')
            ->assertDontSee('Pesanan sudah diterima supplier dan sedang dalam antrean.')
            ->assertDontSee('Status supplier:')
            ->assertDontSee('Status: processing - menunggu pembayaran terdeteksi');

        $this->getJson(route('payment.status', $trx->invoice_code))
            ->assertOk()
            ->assertJson([
                'status' => 'processing',
                'status_label' => 'Sedang diproses',
                'supplier_status' => 'waiting',
                'badge' => 'badge-pending',
            ]);
    }
}
