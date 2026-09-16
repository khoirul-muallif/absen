<?php

namespace App\Filament\Resources\Shifts\Pages;

use App\Filament\Resources\Shifts\ShiftResource;
use App\Models\Shift;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewShift extends ViewRecord
{
    protected static string $resource = ShiftResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('kembali')
                ->label('Kembali ke daftar')
                ->icon('heroicon-o-arrow-left')
                ->color('gray')
                ->url(fn () => ShiftResource::getUrl('index')),

            EditAction::make(),

            // Guard yang sama seperti di ShiftsTable & EditShift.
            DeleteAction::make()
                ->visible(fn (Shift $record): bool => ! $record->sedangDipakai()),
        ];
    }
}
