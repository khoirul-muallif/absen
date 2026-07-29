<?php

namespace App\Filament\Resources\Cutis\Pages;

use App\Filament\Resources\Cutis\CutiResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Actions\Action;

class EditCuti extends EditRecord
{
    protected static string $resource = CutiResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('kembali')
            ->label('Kembali ke daftar')
            ->icon('heroicon-o-arrow-left')
            ->color('gray')
            ->url(fn () => \App\Filament\Resources\Cutis\CutiResource::getUrl('index')),

            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
