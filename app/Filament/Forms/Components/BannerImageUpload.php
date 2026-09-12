<?php

namespace App\Filament\Forms\Components;

use Filament\Forms\Components\FileUpload;

class BannerImageUpload extends FileUpload
{
    public function getAutomaticallyCropImagesAspectRatio(): ?string
    {
        return '9:2';
    }
}
