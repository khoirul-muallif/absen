<?php

namespace App\Filament\Resources\KaryawanShifts\Pages;

use App\Filament\Resources\KaryawanShifts\KaryawanShiftResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditKaryawanShift extends EditRecord
{
    protected static string $resource = KaryawanShiftResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
