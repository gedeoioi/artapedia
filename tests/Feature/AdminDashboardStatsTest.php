<?php

namespace Tests\Feature;

use App\Filament\Widgets\MemberStats;
use App\Filament\Widgets\SalesStats;
use App\Models\Product;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use ReflectionMethod;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminDashboardStatsTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_memisahkan_statistik_member_dari_omzet_produk(): void
    {
        $admin = User::factory()->create(['level' => 'admin', 'balance' => 900000]);
        $roleAdmin = User::factory()->create(['level' => 'member', 'balance' => 800000]);
        Role::create(['name' => 'admin'])->users()->attach($roleAdmin);
        User::factory()->create(['level' => 'member', 'balance' => 12000]);
        User::factory()->create(['level' => 'vip', 'balance' => 8000]);

        $product = Product::create([
            'supplier_code' => 'STATS-1',
            'name' => 'Produk Statistik',
            'game' => 'Game Statistik',
            'cost_basic' => 9000,
            'cost_premium' => 9000,
            'cost_special' => 9000,
            'price_guest' => 10400,
            'price_biasa' => 10400,
            'price_vip' => 10400,
            'is_active' => true,
            'in_stock' => true,
        ]);
        $this->makeTransaction($product->id, Transaction::STATUS_SUCCESS, 10400, 1400);
        $this->makeTransaction($product->id, Transaction::STATUS_PROCESSING, 12000, 3000);
        $this->makeTransaction(null, Transaction::STATUS_SUCCESS, 51000, 0, ['kind' => 'topup']);

        $memberStats = $this->stats(MemberStats::class);
        $salesStats = $this->stats(SalesStats::class);

        $this->assertSame(2, $memberStats[0]->getValue());
        $this->assertSame('Rp 20.000', $memberStats[1]->getValue());
        $this->assertSame('Rp 10.400', $salesStats[0]->getValue());
        $this->assertSame('Rp 1.400', $salesStats[1]->getValue());
        $this->assertSame(1, $salesStats[2]->getValue());
        $this->assertSame(1, $salesStats[3]->getValue());

        $this->actingAs($admin);
        Livewire::test(MemberStats::class)
            ->assertSee('Jumlah Member')
            ->assertSee('Total Saldo Member');
        Livewire::test(SalesStats::class)
            ->assertSee('Omzet produk hari ini');
    }

    private function makeTransaction(?int $productId, string $status, int $total, int $profit, array $meta = []): void
    {
        Transaction::create([
            'invoice_code' => 'INV-STATS-'.uniqid(),
            'product_id' => $productId,
            'payment_gateway_code' => 'ipaymu',
            'target_user_id' => $productId ? '12345678' : 'TOPUP',
            'quantity' => 1,
            'cost_price' => $total - $profit,
            'sell_price' => $total,
            'admin_fee' => 0,
            'gateway_fee' => 0,
            'total_amount' => $total,
            'profit' => $profit,
            'payment_method' => 'ipaymu',
            'status' => $status,
            'meta' => $meta,
        ]);
    }

    private function stats(string $widgetClass): array
    {
        return (new ReflectionMethod($widgetClass, 'getStats'))->invoke(app($widgetClass));
    }
}
