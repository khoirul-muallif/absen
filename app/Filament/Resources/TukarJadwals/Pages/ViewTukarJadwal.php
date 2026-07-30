<?php

namespace App\Filament\Resources\TukarJadwals\Pages;

use App\Filament\Resources\TukarJadwals\TukarJadwalResource;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewTukarJadwal extends ViewRecord
{
    protected static string $resource = TukarJadwalResource::class;

    protected function getHeaderActions(): array
{
    return [
        Action::make('kembali')
            ->label('Kembali ke daftar')
            ->icon('heroicon-o-arrow-left')
            ->color('gray')
            ->url(fn () => \App\Filament\Resources\TukarJadwals\TukarJadwalResource::getUrl('index')),
        EditAction::make()
            ->visible(fn ($record) => $record->isPending()),
    ];
}
}
