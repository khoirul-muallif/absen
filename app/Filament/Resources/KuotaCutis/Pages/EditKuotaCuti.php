<?php

namespace App\Filament\Resources\KuotaCutis\Pages;

use App\Filament\Resources\KuotaCutis\KuotaCutiResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditKuotaCuti extends EditRecord
{
    protected static string $resource = KuotaCutiResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
