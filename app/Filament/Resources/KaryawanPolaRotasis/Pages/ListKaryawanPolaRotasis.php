<?php

namespace App\Filament\Resources\KaryawanPolaRotasis\Pages;

use App\Filament\Resources\KaryawanPolaRotasis\KaryawanPolaRotasiResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListKaryawanPolaRotasis extends ListRecords
{
    protected static string $resource = KaryawanPolaRotasiResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
