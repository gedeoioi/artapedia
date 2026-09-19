<?php

namespace App\Filament\Pages;

use App\Services\ReportExportService;
use App\Services\ReportService;
use App\Support\AdminRoles;
use App\Support\Rupiah;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class Reports extends Page implements HasSchemas
{
    use InteractsWithSchemas;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    protected static ?string $navigationLabel = 'Laporan & Profit';

    protected static string|\UnitEnum|null $navigationGroup = 'Laporan';

    protected static ?string $title = 'Laporan & Profit';

    protected string $view = 'filament.pages.reports';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'from' => now()->startOfMonth()->toDateString(),
            'to' => now()->toDateString(),
            'dimension' => 'product',
        ]);
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->hasAdminPermission(AdminRoles::PERM_REPORTS) ?? false;
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                DatePicker::make('from')
                    ->label('Dari tanggal')
                    ->required()
                    ->live(),
                DatePicker::make('to')
                    ->label('Sampai tanggal')
                    ->required()
                    ->live(),
                Select::make('dimension')
                    ->label('Kelompokkan per')
                    ->options(ReportService::dimensions())
                    ->required()
                    ->live(),
            ])
            ->columns(3)
            ->statePath('data');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('exportXlsx')
                ->label('Export Excel')
                ->icon('heroicon-o-table-cells')
                ->action(function (ReportExportService $exports) {
                    [$from, $to] = $exports->resolveRange(
                        $this->data['from'] ?? null,
                        $this->data['to'] ?? null,
                    );
                    $dimension = (string) ($this->data['dimension'] ?? 'product');

                    $table = app(ReportService::class)->exportRows($dimension, $from, $to);
                    $filename = 'laporan-'.$dimension.'-'.$from->format('Ymd').'-'.$to->format('Ymd').'.xlsx';

                    Notification::make()
                        ->title('File Excel dibuat')
                        ->body($filename)
                        ->success()
                        ->send();

                    return $exports->xlsx($table, $filename);
                }),
            Action::make('exportPdf')
                ->label('Export PDF')
                ->icon('heroicon-o-printer')
                ->openUrlInNewTab()
                ->url(fn (): string => route('admin.reports.print', [
                    'from' => $this->data['from'] ?? null,
                    'to' => $this->data['to'] ?? null,
                    'dimension' => $this->data['dimension'] ?? 'product',
                ])),
        ];
    }

    protected function getViewData(): array
    {
        $reports = app(ReportService::class);
        [$from, $to] = app(ReportExportService::class)->resolveRange(
            $this->data['from'] ?? null,
            $this->data['to'] ?? null,
        );
        $dimension = (string) ($this->data['dimension'] ?? 'product');

        return [
            'summary' => $reports->summary($from, $to),
            'breakdown' => $reports->breakdown($dimension, $from, $to),
            'daily' => $reports->dailySeries($from, $to),
            'dimensionLabel' => ReportService::dimensions()[$dimension] ?? $dimension,
            'from' => $from,
            'to' => $to,
            'rupiah' => fn ($value): string => Rupiah::format($value),
        ];
    }
}
