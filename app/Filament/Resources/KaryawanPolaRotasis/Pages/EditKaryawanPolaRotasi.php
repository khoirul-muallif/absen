<?php

namespace App\Filament\Resources\KaryawanPolaRotasis\Pages;

use App\Filament\Resources\KaryawanPolaRotasis\KaryawanPolaRotasiResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditKaryawanPolaRotasi extends EditRecord
{
    protected static string $resource = KaryawanPolaRotasiResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
