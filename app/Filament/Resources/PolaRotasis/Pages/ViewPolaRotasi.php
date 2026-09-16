<?php

namespace App\Filament\Resources\PolaRotasis\Pages;

use App\Filament\Resources\PolaRotasis\PolaRotasiResource;
use App\Models\PolaRotasi;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewPolaRotasi extends ViewRecord
{
    protected static string $resource = PolaRotasiResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('kembali')
                ->label('Kembali ke daftar')
                ->icon('heroicon-o-arrow-left')
                ->color('gray')
                ->url(fn () => PolaRotasiResource::getUrl('index')),

            EditAction::make(),

            // Guard yang sama seperti di tabel (fase 32).
            DeleteAction::make()
                ->visible(fn (PolaRotasi $record): bool => ! $record->sedangDipakai()),
        ];
    }
}
