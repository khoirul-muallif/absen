<?php

namespace App\Filament\Resources\Izins\Pages;

use App\Filament\Resources\Izins\IzinResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewIzin extends ViewRecord
{
    protected static string $resource = IzinResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
