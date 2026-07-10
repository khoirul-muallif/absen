<?php

namespace App\Filament\Resources\TukarJadwals\Pages;

use App\Filament\Resources\TukarJadwals\TukarJadwalResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditTukarJadwal extends EditRecord
{
    protected static string $resource = TukarJadwalResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
