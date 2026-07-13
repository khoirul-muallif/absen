<?php

namespace App\Filament\Resources\Absensis\Pages;

use App\Filament\Resources\Absensis\AbsensiResource;
use Filament\Resources\Pages\CreateRecord;

class CreateAbsensi extends CreateRecord
{
    protected static string $resource = AbsensiResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (! empty($data['waktu_masuk']) && ! empty($data['shift_id'])) {
            $shift = \App\Models\Shift::find($data['shift_id']);
            $waktuMasuk = \Carbon\Carbon::parse($data['waktu_masuk']);

            $totalTerlambatSebelumnya = 0;
            if ($shift->mode_toleransi === 'akumulasi_bulanan') {
                $totalTerlambatSebelumnya = \App\Models\Absensi::where('karyawan_id', $data['karyawan_id'])
                    ->whereYear('tanggal', $waktuMasuk->year)
                    ->whereMonth('tanggal', $waktuMasuk->month)
                    ->sum('menit_terlambat');
            }

            $menitTerlambatHariIni = $shift->hitungMenitTerlambat($waktuMasuk);

            $data['menit_terlambat'] = $menitTerlambatHariIni;
            $data['status'] = $shift->tentukanStatus($waktuMasuk);
            $data['melebihi_toleransi_bulanan'] = $shift->sudahMelebihiToleransiBulanan(
                $totalTerlambatSebelumnya + $menitTerlambatHariIni
            );
        }

        return $data;
    }
}
