<?php

namespace App\Filament\Resources\QrInstansis\Pages;

use App\Filament\Resources\QrInstansis\QrInstansiResource;
use App\Models\QrInstansi;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewQrInstansi extends ViewRecord
{
    protected static string $resource = QrInstansiResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('kembali')
                ->label('Kembali ke daftar')
                ->icon('heroicon-o-arrow-left')
                ->color('gray')
                ->url(fn () => QrInstansiResource::getUrl('index')),

            Action::make('cetak')
                ->label('Cetak QR')
                ->icon('heroicon-o-printer')
                ->url(fn (QrInstansi $record): string => $record->urlCetak(), shouldOpenInNewTab: true),
                
            EditAction::make(),

            DeleteAction::make()
                ->visible(fn (QrInstansi $record): bool => ! $record->sedangDipakai()),
        ];
    }
}
