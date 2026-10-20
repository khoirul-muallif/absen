<?php

namespace App\Filament\Resources\Jadwals\Pages;

use App\Filament\Pages\GenerateJadwal;
use App\Filament\Resources\Jadwals\JadwalResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListJadwals extends ListRecords
{
    protected static string $resource = JadwalResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('generateJadwal')
            ->label('Generate Jadwal')
            ->icon('heroicon-m-calendar-days')
            ->url(GenerateJadwal::getUrl()),

            CreateAction::make(),
        ];
    }
}
