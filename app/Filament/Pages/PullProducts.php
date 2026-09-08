<?php

namespace App\Filament\Pages;

use App\Jobs\SyncSupplierProducts;
use App\Models\AuditLog;
use App\Models\Product;
use App\Models\SupplierConfig;
use App\Models\Transaction;
use App\Services\ProviderFactory;
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
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;

class PullProducts extends Page implements HasSchemas, HasTable
{
    use InteractsWithSchemas;
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowDownTray;

    protected static ?string $navigationLabel = 'Tarik Produk';

    protected static ?string $title = 'Tarik Produk dari Supplier';

    protected string $view = 'filament.pages.pull-products';

    public ?array $data = [
        'supplier_id' => null,
        'channel' => 'game',
        'filter_game' => null,
        'filter_status' => null,
    ];

    public array $gameOptions = [];

    public function mount(): void
    {
        $this->form->fill($this->data);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('supplier_id')
                    ->label('Supplier')
                    ->options(SupplierConfig::query()->orderBy('priority')->pluck('name', 'id'))
                    ->required()
                    ->live()
                    ->afterStateUpdated(function ($state) {
                        $this->data['supplier_id'] = $state;
                        $this->data['filter_game'] = null;
                        $this->form->fill($this->data);
                        $this->loadGames();
                        $this->resetTable();
                    }),
                Select::make('channel')
                    ->label('Jenis produk')
                    ->options(['game' => 'Game (ID + Zone + cek nickname)', 'prepaid' => 'Pulsa / Paket Data / PPOB (nomor HP)'])
                    ->default('game')
                    ->required()
                    ->live()
                    ->afterStateUpdated(function ($state) {
                        $this->data['channel'] = $state;
                        $this->data['filter_game'] = null;
                        $this->form->fill($this->data);
                        $this->loadGames();
                        $this->resetTable();
                    }),
                Select::make('filter_game')
                    ->label('Filter game / operator (kosongkan = tarik SEMUA)')
                    ->options(fn () => $this->gameOptions)
                    ->searchable()
                    ->placeholder('Semua')
                    ->live()
                    ->afterStateUpdated(function ($state) {
                        $this->data['filter_game'] = $state;
                        $this->resetTable();
                    }),
                TextInput::make('filter_status')
                    ->label('Filter status (opsional)')
                    ->placeholder('cth: available')
                    ->live(debounce: 500)
                    ->afterStateUpdated(function ($state) {
                        $this->data['filter_status'] = $state;
                        $this->resetTable();
                    }),
            ])
            ->statePath('data');
    }

    public function loadGames(): void
    {
        $this->gameOptions = [];
        $supplierId = (int) ($this->data['supplier_id'] ?? 0);
        if (! $supplierId) {
            return;
        }
        $supplier = SupplierConfig::find($supplierId);
        if (! $supplier) {
            return;
        }

        // Sumber 1 (utama): daftar game dari API supplier.
        // Channel prepaid (pulsa/data) memakai filter category/brand juga.
        $apiError = null;
        try {
            $provider = ProviderFactory::supplierFor($supplier);
            if (method_exists($provider, 'getGames')) {
                $res = $provider->getGames(['channel' => $this->data['channel'] ?? 'game']);
                if (! ($res['result'] ?? false)) {
                    $apiError = (string) ($res['message'] ?? 'Gagal ambil daftar game dari supplier');
                } else {
                    foreach ((array) ($res['data'] ?? []) as $row) {
                        $name = is_array($row) ? ($row['name'] ?? $row['game'] ?? null) : (string) $row;
                        if ($name) {
                            $this->gameOptions[$name] = $name;
                        }
                    }
                }
            }
        } catch (\Throwable $e) {
            report($e);
            $apiError = $e->getMessage();
        }

        // Sumber 2 (fallback): game yang sudah pernah ditarik untuk supplier ini.
        if (empty($this->gameOptions)) {
            $this->gameOptions = Product::where('supplier_config_id', $supplier->id)
                ->distinct()->orderBy('game')->pluck('game', 'game')->toArray();
        }

        if (empty($this->gameOptions) && $apiError) {
            Notification::make()
                ->title('Daftar kategori kosong')
                ->body($apiError.' — kamu tetap bisa tarik SEMUA (kosongkan filter) lalu pilih kategori.')
                ->warning()
                ->send();
        }
    }

    protected function selectedSupplier(): ?SupplierConfig
    {
        $id = (int) ($this->data['supplier_id'] ?? 0);

        return $id ? SupplierConfig::find($id) : null;
    }

    protected function syncFilters(): array
    {
        $filters = array_filter([
            'game' => $this->data['filter_game'] ?? null,
            'status' => $this->data['filter_status'] ?? null,
        ]);
        // Channel prepaid (pulsa/data/PPOB) diteruskan ke provider
        // (Digiflazz: cmd=prepaid, VIP: /api/prepaid).
        if (($this->data['channel'] ?? 'game') === 'prepaid') {
            $filters['channel'] = 'prepaid';
            $filters['cmd'] = 'prepaid';
        }

        return $filters;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('sync')
                ->label('Tarik Sekarang')
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->color('primary')
                ->requiresConfirmation()
                ->modalHeading(fn () => 'Tarik produk dari '.$this->selectedSupplier()?->name.'?')
                ->modalDescription(fn () => $this->data['filter_game'] ?? null
                    ? 'Hanya kategori "'.$this->data['filter_game'].'" yang ditarik (upsert, data lain aman).'
                    : 'SEMUA produk supplier ini ditarik (upsert per supplier_code).')
                ->action(function () {
                    $supplier = $this->selectedSupplier();
                    if (! $supplier) {
                        Notification::make()->title('Pilih supplier dulu')->danger()->send();

                        return;
                    }

                    $job = new SyncSupplierProducts($supplier->id, $this->syncFilters());
                    $result = $job->handle();
                    $this->resetTable();

                    if ($result['ok']) {
                        Notification::make()
                            ->title($result['message'].($this->data['filter_game'] ?? null ? ' ('.$this->data['filter_game'].')' : ''))
                            ->success()
                            ->send();
                    } else {
                        Notification::make()
                            ->title('Sync gagal')
                            ->body($result['message'])
                            ->danger()
                            ->send();
                    }
                }),
            Action::make('removeProducts')
                ->label('Hapus Produk')
                ->icon(Heroicon::OutlinedTrash)
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading('Hapus produk yang ditarik?')
                ->modalDescription(fn () => $this->removeDescription())
                ->modalSubmitActionLabel('Ya, hapus')
                ->action(function () {
                    $supplier = $this->selectedSupplier();
                    if (! $supplier) {
                        Notification::make()->title('Pilih supplier dulu')->danger()->send();

                        return;
                    }

                    $query = Product::where('supplier_config_id', $supplier->id)
                        ->when($this->data['filter_game'] ?? null, fn ($q, $g) => $q->where('game', $g));

                    $ids = (clone $query)->pluck('id');
                    $usedCount = Transaction::whereIn('product_id', $ids)->distinct('product_id')->count('product_id');

                    // Proteksi: produk yang sudah punya transaksi TIDAK dihapus
                    // (menghindari invoice yatim), hanya dinonaktifkan.
                    $deletableIds = (clone $query)->whereNotIn('id', function ($q) {
                        $q->select('product_id')->from('transactions')->whereNotNull('product_id');
                    })->pluck('id');

                    $deleted = Product::whereIn('id', $deletableIds)->delete();
                    $deactivated = 0;
                    if ($usedCount > 0) {
                        $deactivated = Product::where('supplier_config_id', $supplier->id)
                            ->when($this->data['filter_game'] ?? null, fn ($q, $g) => $q->where('game', $g))
                            ->whereIn('id', function ($q) {
                                $q->select('product_id')->from('transactions')->whereNotNull('product_id');
                            })
                            ->update(['is_active' => false, 'in_stock' => false]);
                    }

                    AuditLog::record('products.bulk_remove', $supplier, [], [
                        'game' => $this->data['filter_game'] ?? 'SEMUA',
                        'deleted' => $deleted,
                        'deactivated' => $deactivated,
                    ]);

                    $this->resetTable();

                    Notification::make()
                        ->title("Selesai: {$deleted} dihapus".($deactivated ? ", {$deactivated} dinonaktifkan (punya transaksi)" : ''))
                        ->success()
                        ->send();
                }),
        ];
    }

    protected function removeDescription(): string
    {
        $supplier = $this->selectedSupplier();
        $scope = ($this->data['filter_game'] ?? null)
            ? 'kategori "'.$this->data['filter_game'].'" dari '.$supplier?->name
            : 'SEMUA produk dari '.$supplier?->name;

        return 'Hapus '.$scope.' agar bisa tarik ulang dari nol? '
            .'Produk yang sudah punya transaksi TIDAK dihapus (hanya dinonaktifkan) agar invoice tetap valid.';
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(function () {
                $query = Product::query()->orderByDesc('id')->limit(50);
                $supplierId = (int) ($this->data['supplier_id'] ?? 0);
                if ($supplierId) {
                    $query->where('supplier_config_id', $supplierId);
                }
                if (! empty($this->data['filter_game'])) {
                    $query->where('game', $this->data['filter_game']);
                }

                return $query;
            })
            ->columns([
                TextColumn::make('supplier_code')->searchable()->copyable(),
                TextColumn::make('name')->searchable()->limit(40),
                TextColumn::make('game'),
                TextColumn::make('cost_basic')->numeric()->sortable(),
                TextColumn::make('cost_premium')->numeric()->sortable(),
                TextColumn::make('cost_special')->numeric()->sortable(),
                TextColumn::make('in_stock')->badge()
                    ->formatStateUsing(fn ($state) => $state ? 'Tersedia' : 'Kosong')
                    ->color(fn ($state) => $state ? 'success' : 'danger'),
            ])
            ->emptyStateHeading('Belum ada produk')
            ->emptyStateDescription(fn () => empty($this->data['supplier_id'])
                ? 'Pilih supplier di atas untuk melihat produk.'
                : 'Klik Tarik Sekarang untuk menarik produk dari supplier.')
            ->paginated(false);
    }
}
