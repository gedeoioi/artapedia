<?php

namespace App\Filament\Resources\ManualTopups\Schemas;

use Filament\Schemas\Schema;

class ManualTopupForm
{
    /**
     * Tidak ada form create/edit: topup manual hanya dibuat dari unggahan
     * member. Menyediakan form di admin akan menjadi jalur kedua yang bisa
     * mengubah nominal/status tanpa melewati alur review.
     */
    public static function configure(Schema $schema): Schema
    {
        return $schema;
    }
}
