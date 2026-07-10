<?php

namespace App\Filament\Resources\Dinas\Pages;

use App\Filament\Resources\Dinas\DinasResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewDinas extends ViewRecord
{
    protected static string $resource = DinasResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
