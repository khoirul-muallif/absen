<?php

namespace App\Filament\Resources\Jadwals\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class JadwalInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Jadwal')
                    ->icon('heroicon-o-calendar-days')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('karyawan.nama')
                            ->label('Karyawan'),
                        TextEntry::make('tanggal')
                            ->date('d M Y'),
                        TextEntry::make('jenis')
                            ->badge()
                            ->color(fn (string $state): string => match ($state) {
                                'reguler' => 'success',
                                'piket'   => 'warning',
                                'libur'   => 'gray',
                                'cuti'    => 'info',
                                'dinas'   => 'info',
                                default   => 'gray',
                            })
                            ->formatStateUsing(fn (string $state): string => match ($state) {
                                'reguler' => 'Reguler',
                                'piket'   => 'Piket',
                                'libur'   => 'Libur',
                                'cuti'    => 'Cuti',
                                'dinas'   => 'Dinas',
                                default   => $state,
                            }),
                        TextEntry::make('shift.nama_shift')
                            ->label('Shift')
                            ->placeholder('Tidak ada shift (libur/cuti/dinas)'),
                        TextEntry::make('keterangan')
                            ->placeholder('-')
                            ->columnSpanFull(),
                    ]),

                Section::make('Asal & Perlindungan Data')
                    ->icon('heroicon-o-shield-check')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('sumber')
                            ->badge()
                            ->color(fn (string $state): string => $state === 'manual' ? 'primary' : 'gray')
                            ->formatStateUsing(fn (string $state): string => $state === 'manual' ? 'Manual' : 'Generate'),

                        TextEntry::make('efek_generate_ulang')
                            ->label('Kalau generator dijalankan ulang')
                            ->state(fn ($record): string => $record->sumber === 'manual'
                                ? 'Baris ini DILINDUNGI — tidak akan ditimpa, bahkan oleh --overwrite-generate.'
                                : 'Baris ini BOLEH ditimpa oleh jadwal:generate-rotasi --overwrite-generate.'),

                        TextEntry::make('asal_data')
                            ->label('Asal data')
                            ->state(fn ($record): string => in_array($record->jenis, ['cuti', 'dinas'], true)
                                ? 'Hasil sinkronisasi otomatis dari pengajuan '.($record->jenis === 'cuti' ? 'Cuti' : 'Dinas').' yang disetujui. Perbaiki lewat modul tersebut, bukan dari sini.'
                                : 'Entri manual admin atau hasil generator jadwal.')
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
