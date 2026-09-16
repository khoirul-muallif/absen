<?php

namespace App\Filament\Resources\Shifts\Pages;

use App\Filament\Resources\Shifts\ShiftResource;
use App\Models\Shift;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditShift extends EditRecord
{
    protected static string $resource = ShiftResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Guard yang sama seperti di ShiftsTable — absensi.shift_id memakai
            // ON DELETE RESTRICT, jadi tanpa ini penghapusan shift yang pernah
            // dipakai absensi melempar QueryException 1451 mentah.
            //
            // Halaman Edit sengaja diberi guard terpisah: pola "tabel sudah
            // benar tapi halaman lain bolong" sudah jadi bug di
            // ViewCuti/ViewDinas/ViewTukarJadwal (fase 25).
            DeleteAction::make()
                ->visible(fn (Shift $record): bool => ! $record->sedangDipakai()),
        ];
    }
}
