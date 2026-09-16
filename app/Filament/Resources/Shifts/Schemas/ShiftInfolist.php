<?php

namespace App\Filament\Resources\Shifts\Schemas;

use App\Models\Shift;
use Carbon\Carbon;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ShiftInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Informasi Shift')
                    ->icon('heroicon-o-clock')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('nama_shift')
                            ->label('Nama shift'),
                        TextEntry::make('instansi.nama')
                            ->label('Instansi'),
                        TextEntry::make('jam_masuk')
                            ->label('Jam masuk')
                            ->time('H:i'),
                        TextEntry::make('jam_pulang')
                            ->label('Jam pulang')
                            ->time('H:i'),
                        TextEntry::make('durasi')
                            ->label('Durasi shift')
                            ->state(function (Shift $record): string {
                                $menit = self::durasiMenit($record);
                                $jam = intdiv($menit, 60);
                                $sisa = $menit % 60;

                                $teks = $sisa > 0 ? "{$jam} jam {$sisa} menit" : "{$jam} jam";

                                return self::lintasTengahMalam($record)
                                    ? $teks.' (melewati tengah malam)'
                                    : $teks;
                            }),
                        IconEntry::make('is_active')
                            ->label('Aktif')
                            ->boolean(),
                    ]),

                Section::make('Toleransi Keterlambatan')
                    ->icon('heroicon-o-exclamation-triangle')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('mode_toleransi')
                            ->label('Mode')
                            ->badge()
                            ->formatStateUsing(fn (string $state): string => $state === 'akumulasi_bulanan' ? 'Akumulasi Bulanan' : 'Per Hari')
                            ->color(fn (string $state): string => $state === 'akumulasi_bulanan' ? 'info' : 'gray'),

                        TextEntry::make('toleransi_menit')
                            ->label('Toleransi')
                            ->suffix(' menit')
                            ->color(fn (Shift $record): string => $record->mode_toleransi === 'akumulasi_bulanan' ? 'warning' : 'gray'),

                        // Ini yang paling sering disalahpahami: toleransi_menit
                        // TIDAK pernah dipakai untuk status harian. Dijelaskan
                        // sebagai kalimat, bukan dibiarkan jadi dua angka yang
                        // admin harus tafsirkan sendiri.
                        TextEntry::make('arti_toleransi')
                            ->label('Artinya')
                            ->state(fn (Shift $record): string => $record->mode_toleransi === 'akumulasi_bulanan'
                                ? "Karyawan tetap tercatat \"terlambat\" begitu lewat 1 menit dari jam masuk. Angka {$record->toleransi_menit} menit dipakai sebagai batas AKUMULASI sebulan — begitu total keterlambatan sebulan melewatinya, karyawan ditandai melanggar untuk keperluan KPI."
                                : 'Karyawan tercatat "terlambat" begitu lewat 1 menit dari jam masuk. Angka toleransi TIDAK dipakai sama sekali di mode ini, dan tidak ada penanda pelanggaran bulanan.')
                            ->columnSpanFull(),
                    ]),

                Section::make('Pola Hari Kerja')
                    ->icon('heroicon-o-calendar-days')
                    ->schema([
                        TextEntry::make('hari_kerja')
                            ->label('Hari kerja')
                            ->state(function (Shift $record): string {
                                $hariKerja = $record->hari_kerja;

                                if (empty($hariKerja) || ! is_array($hariKerja)) {
                                    return 'Setiap hari (tidak ada libur mingguan)';
                                }

                                $nama = [0 => 'Minggu', 1 => 'Senin', 2 => 'Selasa', 3 => 'Rabu', 4 => 'Kamis', 5 => 'Jumat', 6 => 'Sabtu'];
                                sort($hariKerja);

                                return collect($hariKerja)->map(fn ($hari) => $nama[$hari] ?? '?')->join(', ');
                            })
                            ->helperText('Dipakai generator jadwal bulanan untuk melewati hari libur mingguan, dan oleh rekap harian supaya hari non-kerja tidak dihitung alpha.')
                            ->columnSpanFull(),
                    ]),

                Section::make('Pemakaian')
                    ->icon('heroicon-o-link')
                    ->description('Menentukan apakah shift ini masih bisa dihapus.')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('jumlah_karyawan')
                            ->label('Penugasan karyawan')
                            ->state(fn (Shift $record): int => $record->karyawan()->count()),

                        TextEntry::make('jumlah_jadwal')
                            ->label('Baris jadwal')
                            ->state(fn (Shift $record): int => $record->jadwals()->count()),

                        TextEntry::make('jumlah_absensi')
                            ->label('Baris absensi')
                            ->state(fn (Shift $record): int => $record->absensi()->count()),

                        TextEntry::make('status_hapus')
                            ->label('Bisa dihapus?')
                            ->state(fn (Shift $record): string => $record->sedangDipakai()
                                ? 'Tidak. Shift ini masih direferensikan data di atas — menghapusnya akan merusak riwayat yang sudah tercatat. Nonaktifkan saja kalau sudah tidak dipakai lagi.'
                                : 'Ya, shift ini belum pernah dipakai data mana pun.')
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

    /**
     * Durasi shift dalam menit, sadar kasus lintas tengah malam.
     *
     * Pakai jamMasukString()/jamPulangString(), bukan properti mentah — lihat
     * catatan jebakan cast di Shift::jamMasukString().
     */
    protected static function durasiMenit(Shift $record): int
    {
        $masuk = Carbon::createFromFormat('H:i:s', $record->jamMasukString());
        $pulang = Carbon::createFromFormat('H:i:s', $record->jamPulangString());

        $menit = (int) $masuk->diffInMinutes($pulang, false);

        // jam_pulang lebih awal = shift malam, pulangnya keesokan hari.
        return $menit <= 0 ? $menit + 1440 : $menit;
    }

    protected static function lintasTengahMalam(Shift $record): bool
    {
        return $record->jamPulangString() <= $record->jamMasukString();
    }
}
