<?php

namespace App\Filament\Resources\KaryawanShifts\Schemas;

use App\Models\KaryawanShift;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class KaryawanShiftInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Penugasan')
                    ->icon('heroicon-o-user')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('karyawan.nama')
                            ->label('Karyawan'),
                        TextEntry::make('karyawan.unit_kerja')
                            ->label('Unit kerja')
                            ->placeholder('-'),
                        TextEntry::make('shift_label')
                            ->label('Shift')
                            ->state(fn (KaryawanShift $record): string => $record->shift?->labelLengkap() ?? '-'),
                        TextEntry::make('status_periode')
                            ->label('Status')
                            ->badge()
                            ->state(fn (KaryawanShift $record): string => self::statusPeriode($record))
                            ->color(fn (string $state): string => match ($state) {
                                'Sedang berlaku' => 'success',
                                'Belum mulai'    => 'warning',
                                'Sudah berakhir' => 'gray',
                                default          => 'gray',
                            }),
                    ]),

                Section::make('Periode')
                    ->icon('heroicon-o-calendar-days')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('tanggal_berlaku')
                            ->label('Berlaku mulai')
                            ->date('d M Y'),
                        TextEntry::make('tanggal_berakhir')
                            ->label('Berlaku sampai')
                            ->date('d M Y')
                            ->placeholder('Sampai diganti'),
                        TextEntry::make('catatan_periode')
                            ->label('Catatan')
                            ->state(fn (KaryawanShift $record): string => $record->tanggal_berakhir === null
                                ? 'Penugasan ini tidak punya tanggal akhir. Selama masih begini, karyawan tidak bisa diberi penugasan shift lain — akhiri dulu yang ini.'
                                : 'Generator jadwal bulanan hanya memakai penugasan yang periodenya mencakup bulan yang digenerate. Untuk bulan di luar rentang ini, perpanjang dulu tanggal akhirnya.')
                            ->columnSpanFull(),
                    ]),

                // Admin yang membuka penugasan biasanya ingin tahu jam kerjanya,
                // tapi datanya ada di Resource lain. Ditampilkan di sini supaya
                // tidak perlu bolak-balik ke menu Shift.
                Section::make('Rincian Shift')
                    ->icon('heroicon-o-clock')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('shift.jam_masuk')
                            ->label('Jam masuk')
                            ->time('H:i'),
                        TextEntry::make('shift.jam_pulang')
                            ->label('Jam pulang')
                            ->time('H:i'),
                        TextEntry::make('shift.mode_toleransi')
                            ->label('Mode toleransi')
                            ->badge()
                            ->formatStateUsing(fn (?string $state): string => $state === 'akumulasi_bulanan' ? 'Akumulasi Bulanan' : 'Per Hari')
                            ->color(fn (?string $state): string => $state === 'akumulasi_bulanan' ? 'info' : 'gray'),

                        TextEntry::make('hari_kerja_shift')
                            ->label('Hari kerja')
                            ->state(function (KaryawanShift $record): string {
                                $hariKerja = $record->shift?->hari_kerja;

                                if (empty($hariKerja) || ! is_array($hariKerja)) {
                                    return 'Setiap hari (tidak ada libur mingguan)';
                                }

                                $nama = [0 => 'Minggu', 1 => 'Senin', 2 => 'Selasa', 3 => 'Rabu', 4 => 'Kamis', 5 => 'Jumat', 6 => 'Sabtu'];
                                sort($hariKerja);

                                return collect($hariKerja)->map(fn ($hari) => $nama[$hari] ?? '?')->join(', ');
                            })
                            ->helperText('Di luar hari ini, karyawan tidak dijadwalkan dan tidak dihitung alpha.')
                            ->columnSpanFull(),
                    ]),

                Section::make('Riwayat Penugasan Karyawan Ini')
                    ->icon('heroicon-o-queue-list')
                    ->schema([
                        TextEntry::make('penugasan_lain')
                            ->label('Penugasan lain')
                            ->state(function (KaryawanShift $record): string {
                                $lain = KaryawanShift::with('shift')
                                    ->where('karyawan_id', $record->karyawan_id)
                                    ->where('id', '!=', $record->id)
                                    ->orderBy('tanggal_berlaku')
                                    ->get();

                                if ($lain->isEmpty()) {
                                    return 'Tidak ada. Ini satu-satunya penugasan shift untuk karyawan ini.';
                                }

                                return $lain->map(function (KaryawanShift $item): string {
                                    $sampai = $item->tanggal_berakhir
                                        ? $item->tanggal_berakhir->format('d M Y')
                                        : 'sampai diganti';

                                    return sprintf(
                                        '%s: %s → %s',
                                        $item->shift?->nama_shift ?? 'shift terhapus',
                                        $item->tanggal_berlaku->format('d M Y'),
                                        $sampai
                                    );
                                })->join(' · ');
                            })
                            ->helperText('Periode penugasan tidak boleh beririsan — satu karyawan umum hanya punya satu shift aktif pada satu waktu.')
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

    protected static function statusPeriode(KaryawanShift $record): string
    {
        $hariIni = today();

        if ($record->tanggal_berlaku > $hariIni) {
            return 'Belum mulai';
        }

        if ($record->tanggal_berakhir !== null && $record->tanggal_berakhir < $hariIni) {
            return 'Sudah berakhir';
        }

        return 'Sedang berlaku';
    }
}
