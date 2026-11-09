<?php

namespace App\Filament\Resources\Informasis\Pages;

use App\Filament\Resources\Informasis\InformasiResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListInformasis extends ListRecords
{
    protected static string $resource = InformasiResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
