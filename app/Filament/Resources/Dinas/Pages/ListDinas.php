<?php

namespace App\Filament\Resources\Dinas\Pages;

use App\Filament\Resources\Dinas\DinasResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListDinas extends ListRecords
{
    protected static string $resource = DinasResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
