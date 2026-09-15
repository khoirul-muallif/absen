<?php

namespace App\Filament\Resources\HariLiburs\Schemas;

use App\Models\Jadwal;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class HariLiburInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Hari Libur')
                    ->icon('heroicon-o-calendar-days')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('tanggal')
                            ->date('d M Y'),
                        TextEntry::make('nama'),
                        TextEntry::make('instansi.nama')
                            ->label('Instansi'),
                        IconEntry::make('is_cuti_bersama')
                            ->label('Cuti bersama')
                            ->boolean(),
                        TextEntry::make('keterangan')
                            ->placeholder('-')
                            ->columnSpanFull(),
                    ]),

                Section::make('Dampak ke Modul Lain')
                    ->icon('heroicon-o-exclamation-triangle')
                    ->description('Satu baris hari libur dibaca oleh 4 tempat — perubahan di sini tidak otomatis menyentuh data yang sudah terlanjur dibuat.')
                    ->schema([
                        TextEntry::make('jadwal_terdampak')
                            ->label('Jadwal kerja di tanggal ini')
                            ->state(function ($record): string {
                                $jumlah = Jadwal::whereDate('tanggal', $record->tanggal)
                                    ->where('jenis', '!=', 'libur')
                                    ->whereHas('karyawan', fn ($q) => $q->where('instansi_id', $record->instansi_id))
                                    ->count();

                                if ($jumlah === 0) {
                                    return 'Tidak ada jadwal kerja non-libur di tanggal ini — konsisten.';
                                }

                                return "⚠️ Masih ada {$jumlah} jadwal kerja (bukan libur) di tanggal ini. "
                                    .'Jalankan ulang generator jadwal, atau ubah barisnya manual. '
                                    .'Baris ber-sumber manual tetap tidak akan tertimpa generator.';
                            })
                            ->columnSpanFull(),

                        TextEntry::make('catatan_cuti_bersama')
                            ->label('Catatan cuti bersama')
                            ->visible(fn ($record): bool => (bool) $record->is_cuti_bersama)
                            ->state('Kebijakan RS: saat cuti bersama karyawan tetap masuk, yang ingin libur mengajukan cuti biasa. '
                                .'Tapi sistem masih memperlakukan baris ini sama seperti libur nasional — generator jadwal akan '
                                .'menandai karyawan umum libur, dan rekap harian tidak menghitungnya alpha. Penyesuaian belum dikerjakan.')
                            ->columnSpanFull(),

                        TextEntry::make('dibaca_oleh')
                            ->label('Dibaca oleh')
                            ->state('jadwal:generate-bulanan · jadwal:generate-rotasi · absensi:rekap-harian · peringatan tanggal di form Jadwal')
                            ->columnSpanFull(),
                    ]),

                Section::make('Sistem')
                    ->icon('heroicon-o-clock')
                    ->columns(2)
                    ->collapsed()
                    ->schema([
                        TextEntry::make('created_at')
                            ->label('Dibuat')
                            ->dateTime('d M Y H:i'),
                        TextEntry::make('updated_at')
                            ->label('Terakhir diubah')
                            ->dateTime('d M Y H:i'),
                    ]),
            ]);
    }
}
