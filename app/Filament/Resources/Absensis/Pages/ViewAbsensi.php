<?php

namespace App\Filament\Resources\Absensis\Pages;

use App\Filament\Resources\Absensis\AbsensiResource;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewAbsensi extends ViewRecord
{
    protected static string $resource = AbsensiResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('kembali')
                ->label('Kembali ke daftar')
                ->icon('heroicon-o-arrow-left')
                ->color('gray')
                ->url(fn () => AbsensiResource::getUrl('index')),

            EditAction::make(),
        ];
    }
}
