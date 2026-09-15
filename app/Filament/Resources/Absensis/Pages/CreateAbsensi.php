<?php

namespace App\Filament\Resources\Absensis\Pages;

use App\Filament\Resources\Absensis\AbsensiResource;
use App\Filament\Resources\Absensis\Concerns\MenghitungKeterlambatan;
use Filament\Resources\Pages\CreateRecord;

class CreateAbsensi extends CreateRecord
{
    use MenghitungKeterlambatan;

    protected static string $resource = AbsensiResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Record belum ada, jadi tidak ada yang perlu dikecualikan dari
        // akumulasi bulanan.
        return $this->hitungKolomKeterlambatan($data);
    }
}
