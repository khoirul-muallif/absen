<?php

namespace App\Filament\Resources\PolaRotasis\Pages;

use App\Filament\Resources\PolaRotasis\PolaRotasiResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditPolaRotasi extends EditRecord
{
    protected static string $resource = PolaRotasiResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
