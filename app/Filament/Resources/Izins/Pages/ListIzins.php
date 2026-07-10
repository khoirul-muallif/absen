<?php

namespace App\Filament\Resources\Izins\Pages;

use App\Filament\Resources\Izins\IzinResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListIzins extends ListRecords
{
    protected static string $resource = IzinResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
