<?php

namespace App\Filament\Resources\Izins\Pages;

use App\Filament\Resources\Izins\IzinResource;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewIzin extends ViewRecord
{
    protected static string $resource = IzinResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('kembali')
            ->label('Kembali ke daftar')
            ->icon('heroicon-o-arrow-left')
            ->color('gray')
            ->url(fn () => \App\Filament\Resources\Izins\IzinResource::getUrl('index')),
            EditAction::make(),
        ];
    }
}
