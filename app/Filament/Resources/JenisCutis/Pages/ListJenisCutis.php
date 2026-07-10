<?php

namespace App\Filament\Resources\JenisCutis\Pages;

use App\Filament\Resources\JenisCutis\JenisCutiResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListJenisCutis extends ListRecords
{
    protected static string $resource = JenisCutiResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
