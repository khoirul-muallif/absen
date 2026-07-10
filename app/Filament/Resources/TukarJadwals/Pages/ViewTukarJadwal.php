<?php

namespace App\Filament\Resources\TukarJadwals\Pages;

use App\Filament\Resources\TukarJadwals\TukarJadwalResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewTukarJadwal extends ViewRecord
{
    protected static string $resource = TukarJadwalResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
