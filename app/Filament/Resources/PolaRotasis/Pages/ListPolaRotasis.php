<?php

namespace App\Filament\Resources\PolaRotasis\Pages;

use App\Filament\Resources\PolaRotasis\PolaRotasiResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPolaRotasis extends ListRecords
{
    protected static string $resource = PolaRotasiResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
