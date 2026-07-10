<?php

namespace App\Filament\Resources\TukarJadwals\Pages;

use App\Filament\Resources\TukarJadwals\TukarJadwalResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListTukarJadwals extends ListRecords
{
    protected static string $resource = TukarJadwalResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
