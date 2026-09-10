<?php

namespace App\Filament\Resources\PaymentGatewayConfigs\Pages;

use App\Filament\Resources\PaymentGatewayConfigs\PaymentGatewayConfigResource;
use App\Payments\IPaymuGateway;
use App\Services\ProviderFactory;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditPaymentGatewayConfig extends EditRecord
{
    protected static string $resource = PaymentGatewayConfigResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('syncIPaymuChannels')
                ->label('Sinkronkan Channel iPaymu')
                ->color('warning')
                ->requiresConfirmation()
                ->modalDescription('Daftar channel aktif akan diambil dari akun iPaymu. Nilai fee setiap kelompok tetap dipertahankan.')
                ->visible(fn (): bool => $this->record->code === 'ipaymu')
                ->action(function (): void {
                    try {
                        $gateway = ProviderFactory::gatewayFor($this->record);
                        if (! $gateway instanceof IPaymuGateway) {
                            throw new \RuntimeException('Provider gateway bukan iPaymu.');
                        }

                        $remoteChannels = $gateway->activePaymentChannels();
                        $settings = $this->record->channel_settings ?? [];
                        foreach (IPaymuGateway::CHECKOUT_CHANNELS as $method => $group) {
                            $settings[$method] = [
                                'channels' => array_values(array_intersect(
                                    array_keys($group['channels']),
                                    $remoteChannels[$method] ?? [],
                                )),
                                'fee_flat' => (int) ($settings[$method]['fee_flat'] ?? $this->record->fee_flat),
                                'fee_percent' => (float) ($settings[$method]['fee_percent'] ?? $this->record->fee_percent),
                            ];
                        }

                        $this->record->update(['channel_settings' => $settings]);
                        $this->fillForm();

                        Notification::make()
                            ->title('Channel iPaymu berhasil disinkronkan')
                            ->body(collect($remoteChannels)->flatten()->count().' channel aktif ditemukan.')
                            ->success()
                            ->send();
                    } catch (\Throwable $e) {
                        report($e);
                        Notification::make()
                            ->title('Sinkronisasi channel gagal')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),
            DeleteAction::make(),
        ];
    }
}
