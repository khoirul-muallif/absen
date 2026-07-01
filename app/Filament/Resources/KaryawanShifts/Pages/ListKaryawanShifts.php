<?php

namespace App\Filament\Resources\KaryawanShifts\Pages;

use App\Filament\Resources\KaryawanShifts\KaryawanShiftResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListKaryawanShifts extends ListRecords
{
    protected static string $resource = KaryawanShiftResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
