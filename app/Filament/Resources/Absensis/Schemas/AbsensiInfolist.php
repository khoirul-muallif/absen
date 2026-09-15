<?php

namespace App\Filament\Resources\Absensis\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class AbsensiInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Identitas Absensi')
                    ->icon('heroicon-o-identification')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('karyawan.nama')
                            ->label('Karyawan'),
                        TextEntry::make('shift.nama_shift')
                            ->label('Shift')
                            ->placeholder('-'),
                        TextEntry::make('tanggal')
                            ->date('d M Y'),
                        TextEntry::make('status')
                            ->badge()
                            ->color(fn (string $state): string => match ($state) {
                                'tepat_waktu' => 'success',
                                'terlambat'   => 'warning',
                                'alpha'       => 'danger',
                                'izin'        => 'info',
                                'sakit'       => 'warning',
                                'cuti'        => 'info',
                                'dinas'       => 'info',
                                'libur'       => 'gray',
                                default       => 'gray',
                            })
                            ->formatStateUsing(fn (string $state): string => match ($state) {
                                'tepat_waktu' => 'Tepat Waktu',
                                'terlambat'   => 'Terlambat',
                                'alpha'       => 'Alpha',
                                'izin'        => 'Izin',
                                'sakit'       => 'Sakit',
                                'cuti'        => 'Cuti',
                                'dinas'       => 'Dinas',
                                'libur'       => 'Libur',
                                default       => $state,
                            }),
                        TextEntry::make('qrInstansi.kode_qr')
                            ->label('QR Instansi')
                            ->placeholder('Tidak lewat pemindaian QR'),
                        TextEntry::make('keterangan')
                            ->placeholder('-')
                            ->columnSpanFull(),

                        // Baris berstatus cuti/dinas lahir dari
                        // Cuti::afterApprove()/Dinas::afterApprove(), bukan dari
                        // entri manual. Perlu terlihat jelas di halaman detail
                        // supaya admin tidak menyangka ini data yang bebas diubah.
                        TextEntry::make('asal_data')
                            ->label('Asal data')
                            ->state(fn ($record): string => in_array($record->status, ['cuti', 'dinas'])
                                ? 'Hasil sinkronisasi otomatis dari pengajuan '.($record->status === 'cuti' ? 'Cuti' : 'Dinas').' yang disetujui.'
                                : 'Entri manual / hasil absen karyawan.')
                            ->columnSpanFull(),
                    ]),

                Section::make('Data Masuk')
                    ->icon('heroicon-o-arrow-right-circle')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('waktu_masuk')
                            ->dateTime('d M Y H:i')
                            ->placeholder('Belum absen masuk'),
                        TextEntry::make('menit_terlambat')
                            ->label('Menit terlambat')
                            ->numeric()
                            ->suffix(' menit'),
                        IconEntry::make('melebihi_toleransi_bulanan')
                            ->label('Melewati toleransi bulanan')
                            ->boolean()
                            ->trueColor('danger')
                            ->falseColor('gray')
                            ->helperText('Penanda KPI — akumulasi keterlambatan sebulan sudah melewati toleransi shift.'),
                        ImageEntry::make('foto_masuk')
                            ->label('Foto masuk')
                            ->placeholder('-'),
                        TextEntry::make('latitude_masuk')
                            ->label('Koordinat masuk')
                            ->placeholder('-')
                            ->state(fn ($record): ?string => $record->latitude_masuk && $record->longitude_masuk
                                ? "{$record->latitude_masuk}, {$record->longitude_masuk}"
                                : null)
                            ->columnSpanFull(),
                    ]),

                Section::make('Data Pulang')
                    ->icon('heroicon-o-arrow-left-circle')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('waktu_pulang')
                            ->dateTime('d M Y H:i')
                            ->placeholder('Belum absen pulang'),
                        TextEntry::make('durasi')
                            ->label('Durasi kerja')
                            ->state(fn ($record): string => $record->durasiMenit() !== null
                                ? $record->durasiMenit().' menit'
                                : '-'),
                        ImageEntry::make('foto_pulang')
                            ->label('Foto pulang')
                            ->placeholder('-'),
                        TextEntry::make('latitude_pulang')
                            ->label('Koordinat pulang')
                            ->placeholder('-')
                            ->state(fn ($record): ?string => $record->latitude_pulang && $record->longitude_pulang
                                ? "{$record->latitude_pulang}, {$record->longitude_pulang}"
                                : null)
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
