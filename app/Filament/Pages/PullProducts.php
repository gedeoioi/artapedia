<?php

namespace App\Filament\Pages;

use App\Jobs\SyncSupplierProducts;
use App\Models\Product;
use App\Models\SupplierConfig;
use App\Services\ProviderFactory;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;

class PullProducts extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowDownTray;

    protected static ?string $navigationLabel = 'Tarik Produk';

    protected static ?string $title = 'Tarik Produk dari Supplier';

    protected string $view = 'filament.pages.pull-products';

    public ?int $supplier_id = null;

    public ?string $filter_game = null;

    public ?string $filter_status = null;

    public array $gameOptions = [];

    public array $lastResult = [];

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('supplier_id')
                ->label('Supplier')
                ->options(SupplierConfig::query()->pluck('name', 'id'))
                ->required()
                ->live()
                ->afterStateUpdated(fn () => $this->loadGames()),
            Select::make('filter_game')
                ->label('Filter game (kosongkan = tarik SEMUA)')
                ->options($this->gameOptions)
                ->searchable()
                ->placeholder('Semua game'),
            TextInput::make('filter_status')
                ->label('Filter status (opsional)')
                ->placeholder('cth: available'),
        ]);
    }

    public function loadGames(): void
    {
        $this->gameOptions = [];
        if (! $this->supplier_id) {
            return;
        }
        $supplier = SupplierConfig::find($this->supplier_id);
        if (! $supplier) {
            return;
        }
        try {
            $provider = ProviderFactory::supplierFor($supplier);
            if (! method_exists($provider, 'getGames')) {
                $games = Product::where('supplier_config_id', $supplier->id)
                    ->distinct()->pluck('game', 'game')->toArray();
                $this->gameOptions = $games;
                return;
            }
            $res = $provider->getGames();
            $rows = $res['data'] ?? [];
            foreach ((array) $rows as $row) {
                $name = is_array($row) ? ($row['name'] ?? $row['game'] ?? null) : (string) $row;
                if ($name) {
                    $this->gameOptions[$name] = $name;
                }
            }
        } catch (\Throwable $e) {
            report($e);
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('sync')
                ->label('Tarik Sekarang')
                ->action(function () {
                    $supplier = SupplierConfig::findOrFail($this->supplier_id);
                    $filters = array_filter([
                        'game' => $this->filter_game,
                        'status' => $this->filter_status,
                    ]);

                    $job = new SyncSupplierProducts($supplier->id, $filters);
                    $job->handle();

                    $this->lastResult = Product::where('supplier_config_id', $supplier->id)
                        ->when($this->filter_game, fn ($q) => $q->where('game', $this->filter_game))
                        ->orderByDesc('id')->limit(50)->get()->toArray();

                    Notification::make()->title('Sync selesai')->success()->send();
                }),
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(Product::query()->where('supplier_config_id', $this->supplier_id ?? 0)->orderByDesc('id')->limit(50))
            ->columns([
                TextColumn::make('supplier_code')->searchable(),
                TextColumn::make('name')->searchable(),
                TextColumn::make('game'),
                TextColumn::make('cost_basic')->money('IDR'),
                TextColumn::make('cost_premium')->money('IDR'),
                TextColumn::make('cost_special')->money('IDR'),
                TextColumn::make('in_stock')->badge()
                    ->formatStateUsing(fn ($state) => $state ? 'Tersedia' : 'Kosong'),
            ]);
    }
}
