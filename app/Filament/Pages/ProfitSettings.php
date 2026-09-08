<?php

namespace App\Filament\Pages;

use App\Models\AuditLog;
use App\Models\SiteSetting;
use App\Services\ProfitCalculator;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class ProfitSettings extends Page implements HasSchemas
{
    use InteractsWithSchemas;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static ?string $navigationLabel = 'Atur Profit';

    protected static string|\UnitEnum|null $navigationGroup = 'Pengaturan';

    protected static ?string $title = 'Atur Profit Produk';

    protected string $view = 'filament.pages.profit-settings';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'profit_mode' => SiteSetting::get('profit_mode', ProfitCalculator::MODE_PERCENT),
            'profit_percent' => SiteSetting::get('profit_percent', 5),
            'profit_flat' => SiteSetting::get('profit_flat', 0),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('profit_mode')
                    ->label('Metode profit')
                    ->options(ProfitCalculator::MODES)
                    ->required()
                    ->live(),
                TextInput::make('profit_percent')
                    ->label('Persentase profit')
                    ->numeric()
                    ->minValue(0)
                    ->maxValue(100)
                    ->step(0.01)
                    ->suffix('%')
                    ->required(fn ($get): bool => $get('profit_mode') === ProfitCalculator::MODE_PERCENT)
                    ->visible(fn ($get): bool => $get('profit_mode') === ProfitCalculator::MODE_PERCENT),
                TextInput::make('profit_flat')
                    ->label('Profit flat per produk')
                    ->numeric()
                    ->minValue(0)
                    ->maxValue(1_000_000_000)
                    ->prefix('Rp')
                    ->required(fn ($get): bool => $get('profit_mode') === ProfitCalculator::MODE_FLAT)
                    ->visible(fn ($get): bool => $get('profit_mode') === ProfitCalculator::MODE_FLAT),
            ])
            ->statePath('data');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('save')
                ->label('Simpan Pengaturan')
                ->action(fn () => $this->save()),
            Action::make('saveAndApply')
                ->label('Simpan & Terapkan ke Semua Produk')
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading('Hitung ulang semua harga jual?')
                ->modalDescription('Harga guest, reseller biasa, dan VIP akan dihitung ulang dari modal masing-masing memakai pengaturan ini.')
                ->action(fn () => $this->save(applyToProducts: true)),
        ];
    }

    public function save(bool $applyToProducts = false): void
    {
        $state = $this->form->getState();
        $old = [
            'profit_mode' => SiteSetting::get('profit_mode', ProfitCalculator::MODE_PERCENT),
            'profit_percent' => SiteSetting::get('profit_percent', 5),
            'profit_flat' => SiteSetting::get('profit_flat', 0),
        ];

        foreach (['profit_mode', 'profit_percent', 'profit_flat'] as $key) {
            if (array_key_exists($key, $state)) {
                SiteSetting::set($key, $state[$key]);
            }
        }

        $updated = $applyToProducts
            ? app(ProfitCalculator::class)->applyToExistingProducts()
            : 0;

        AuditLog::record('profit_settings.update', null, $old, $state + [
            'applied_to_products' => $applyToProducts,
            'updated_products' => $updated,
        ]);

        Notification::make()
            ->title($applyToProducts
                ? "Pengaturan tersimpan dan {$updated} produk diperbarui"
                : 'Pengaturan profit tersimpan')
            ->success()
            ->send();
    }
}
