<?php

namespace App\Filament\Pages;

use App\Models\AuditLog;
use App\Models\SiteSetting;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class SiteSettings extends Page implements HasSchemas
{
    use InteractsWithSchemas;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static ?string $navigationLabel = 'Pengaturan Website';

    protected static string|\UnitEnum|null $navigationGroup = 'Pengaturan';

    protected static ?string $title = 'Pengaturan Website';

    protected string $view = 'filament.pages.site-settings';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill(SiteSetting::allKeyed());
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('site_name')
                    ->label('Nama website')
                    ->required()
                    ->maxLength(64),
                Textarea::make('site_description')
                    ->label('Deskripsi website (meta description / SEO)')
                    ->rows(3)
                    ->maxLength(500)
                    ->columnSpanFull(),
                TextInput::make('site_tagline')
                    ->label('Tagline (tampil di beranda)')
                    ->maxLength(128)
                    ->columnSpanFull(),
                FileUpload::make('logo_path')
                    ->label('Logo website (PNG/SVG, tampil di header)')
                    ->image()
                    ->disk('public')
                    ->directory('site')
                    ->visibility('public')
                    ->maxSize(2048)
                    ->helperText('Kosongkan untuk memakai teks nama website.'),
                FileUpload::make('favicon_path')
                    ->label('Favicon (ICO/PNG kecil, tampil di tab browser)')
                    ->disk('public')
                    ->directory('site')
                    ->visibility('public')
                    ->maxSize(1024)
                    ->acceptedFileTypes(['image/x-icon', 'image/png', 'image/svg+xml', 'image/vnd.microsoft.icon'])
                    ->helperText('Idealnya 32x32 atau 48x48 px.'),
                Select::make('theme')
                    ->label('Tema tampilan')
                    ->options(SiteSetting::THEMES)
                    ->required()
                    ->live(),
                ColorPicker::make('primary_color')
                    ->label('Warna utama (tombol/link)'),
                ColorPicker::make('accent_color')
                    ->label('Warna teks aksen'),
                Textarea::make('footer_text')
                    ->label('Teks footer')
                    ->rows(2)
                    ->columnSpanFull(),
                TextInput::make('contact_whatsapp')
                    ->label('No. WhatsApp kontak')
                    ->tel()
                    ->placeholder('cth: 6281234567890'),
                TextInput::make('contact_email')
                    ->label('Email kontak')
                    ->email(),
            ])
            ->statePath('data');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('save')
                ->label('Simpan Pengaturan')
                ->action(function () {
                    $state = $this->form->getState();
                    $old = SiteSetting::allKeyed();

                    foreach (SiteSetting::DEFAULTS as $key => $default) {
                        if (array_key_exists($key, $state)) {
                            SiteSetting::set($key, $state[$key]);
                        }
                    }

                    AuditLog::record('site_settings.update', null, $old, $state);

                    Notification::make()
                        ->title('Pengaturan website tersimpan')
                        ->success()
                        ->send();
                }),
        ];
    }
}
