<?php

namespace App\Filament\Resources\KaryawanShifts\Pages;

use App\Filament\Resources\KaryawanShifts\KaryawanShiftResource;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewKaryawanShift extends ViewRecord
{
    protected static string $resource = KaryawanShiftResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('kembali')
                ->label('Kembali ke daftar')
                ->icon('heroicon-o-arrow-left')
                ->color('gray')
                ->url(fn () => KaryawanShiftResource::getUrl('index')),

            EditAction::make(),

            // Tidak ada FK RESTRICT ke karyawan_shift, jadi penghapusan aman
            // secara teknis. Tapi konsekuensinya nyata: karyawan jadi tanpa
            // shift aktif, absen lewat API ditolak "Tidak ada shift aktif
            // untuk hari ini", dan generator jadwal bulanan melewatinya.
            DeleteAction::make()
                ->modalDescription('Menghapus penugasan ini membuat karyawan tidak punya shift pada periode tersebut: absennya akan ditolak dan jadwalnya tidak ikut digenerate. Kalau tujuannya mengganti shift, lebih aman mengisi tanggal akhir di penugasan ini lalu membuat penugasan baru.'),
        ];
    }
}
