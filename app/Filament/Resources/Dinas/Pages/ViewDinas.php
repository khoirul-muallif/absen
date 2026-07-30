<?php

namespace App\Filament\Resources\Dinas\Pages;

use App\Filament\Resources\Dinas\DinasResource;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewDinas extends ViewRecord
{
    protected static string $resource = DinasResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('kembali')
                ->label('Kembali ke daftar')
                ->icon('heroicon-o-arrow-left')
                ->color('gray')
                ->url(fn () => \App\Filament\Resources\Dinas\DinasResource::getUrl('index')),
            EditAction::make()
                ->visible(fn ($record) => $record->isPending()),
        ];
    }
}
