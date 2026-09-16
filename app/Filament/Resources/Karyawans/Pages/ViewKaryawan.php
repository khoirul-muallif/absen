<?php

namespace App\Filament\Resources\Karyawans\Pages;

use App\Filament\Resources\Karyawans\KaryawanResource;
use App\Models\Karyawan;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewKaryawan extends ViewRecord
{
    protected static string $resource = KaryawanResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('kembali')
                ->label('Kembali ke daftar')
                ->icon('heroicon-o-arrow-left')
                ->color('gray')
                ->url(fn () => KaryawanResource::getUrl('index')),

            EditAction::make(),

            // Guard yang sama seperti di tabel: semua FK ke karyawan_id
            // memakai ON DELETE CASCADE.
            DeleteAction::make()
                ->visible(fn (Karyawan $record): bool => ! $record->punyaRiwayat()),
        ];
    }
}
