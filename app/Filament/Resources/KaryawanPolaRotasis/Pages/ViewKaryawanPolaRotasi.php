<?php

namespace App\Filament\Resources\KaryawanPolaRotasis\Pages;

use App\Filament\Resources\KaryawanPolaRotasis\KaryawanPolaRotasiResource;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewKaryawanPolaRotasi extends ViewRecord
{
    protected static string $resource = KaryawanPolaRotasiResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('kembali')
                ->label('Kembali ke daftar')
                ->icon('heroicon-o-arrow-left')
                ->color('gray')
                ->url(fn () => KaryawanPolaRotasiResource::getUrl('index')),

            EditAction::make(),

            DeleteAction::make()
                ->modalDescription('Menghapus assignment ini membuat karyawan rotasi tidak punya pola pada periode tersebut: jadwalnya tidak ikut digenerate, dan rekap harian akan menandainya "jadwal_hilang". Kalau tujuannya mengganti pola, lebih aman mengisi tanggal akhir di assignment ini lalu membuat yang baru.'),
        ];
    }
}
