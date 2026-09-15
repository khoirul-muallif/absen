<?php

namespace App\Filament\Resources\Absensis\Pages;

use App\Filament\Resources\Absensis\AbsensiResource;
use App\Filament\Resources\Absensis\Concerns\MenghitungKeterlambatan;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditAbsensi extends EditRecord
{
    use MenghitungKeterlambatan;

    protected static string $resource = AbsensiResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    /**
     * Sebelumnya hook ini TIDAK ADA sama sekali — mengubah waktu_masuk lewat
     * Edit menyimpan waktu barunya tapi membiarkan menit_terlambat, status,
     * dan melebihi_toleransi_bulanan pada nilai lama.
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        return $this->hitungKolomKeterlambatan($data, $this->record->id);
    }
}
