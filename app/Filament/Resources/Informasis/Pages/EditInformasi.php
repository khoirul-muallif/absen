<?php

namespace App\Filament\Resources\Informasis\Pages;

use App\Filament\Resources\Informasis\InformasiResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditInformasi extends EditRecord
{
    protected static string $resource = InformasiResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
