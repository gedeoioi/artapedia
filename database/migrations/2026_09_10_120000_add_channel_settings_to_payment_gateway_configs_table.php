<?php

use App\Payments\IPaymuGateway;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_gateway_configs', function (Blueprint $table) {
            $table->json('channel_settings')->nullable()->after('fee_percent');
        });

        DB::table('payment_gateway_configs')
            ->where('code', 'ipaymu')
            ->orderBy('id')
            ->each(function (object $gateway): void {
                $settings = [];
                foreach (IPaymuGateway::CHECKOUT_CHANNELS as $method => $group) {
                    $settings[$method] = [
                        'channels' => array_keys($group['channels']),
                        'fee_flat' => (int) $gateway->fee_flat,
                        'fee_percent' => (float) $gateway->fee_percent,
                    ];
                }

                DB::table('payment_gateway_configs')
                    ->where('id', $gateway->id)
                    ->update(['channel_settings' => json_encode($settings, JSON_THROW_ON_ERROR)]);
            });
    }

    public function down(): void
    {
        Schema::table('payment_gateway_configs', function (Blueprint $table) {
            $table->dropColumn('channel_settings');
        });
    }
};
