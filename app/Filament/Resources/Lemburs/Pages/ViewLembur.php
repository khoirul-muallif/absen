<?php

namespace App\Filament\Resources\Lemburs\Pages;

use App\Filament\Resources\Lemburs\LemburResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewLembur extends ViewRecord
{
    protected static string $resource = LemburResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
