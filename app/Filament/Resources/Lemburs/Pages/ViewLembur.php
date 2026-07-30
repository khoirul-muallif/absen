<?php

namespace App\Filament\Resources\Lemburs\Pages;

use App\Filament\Resources\Lemburs\LemburResource;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewLembur extends ViewRecord
{
    protected static string $resource = LemburResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('kembali')
            ->label('Kembali ke daftar')
            ->icon('heroicon-o-arrow-left')
            ->color('gray')
            ->url(fn () => \App\Filament\Resources\Lemburs\LemburResource::getUrl('index')),

            EditAction::make()
                ->visible(fn ($record) => $record->isPending()),
        ];
    }
}
