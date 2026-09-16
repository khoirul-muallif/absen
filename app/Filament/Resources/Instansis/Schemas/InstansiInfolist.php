<?php

namespace App\Filament\Resources\Instansis\Schemas;

use App\Models\Instansi;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class InstansiInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Informasi Instansi')
                    ->icon('heroicon-o-building-office-2')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('nama')
                            ->label('Nama instansi'),
                        TextEntry::make('kode_instansi')
                            ->label('Kode instansi')
                            ->badge()
                            ->color('gray')
                            ->helperText('Dikirim ke aplikasi mobile lewat endpoint profil. TIDAK dipakai untuk pemindaian QR — itu memakai kode QR tersendiri.'),
                        TextEntry::make('telepon')
                            ->placeholder('-'),
                        IconEntry::make('is_active')
                            ->label('Aktif')
                            ->boolean(),
                        TextEntry::make('alamat')
                            ->placeholder('-')
                            ->columnSpanFull(),
                    ]),

                Section::make('Lokasi & Validasi GPS')
                    ->icon('heroicon-o-map-pin')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('latitude'),
                        TextEntry::make('longitude'),
                        TextEntry::make('radius_meter')
                            ->label('Radius validasi')
                            ->suffix(' meter'),

                        TextEntry::make('cek_wilayah')
                            ->label('Catatan koordinat')
                            ->state(function (Instansi $record): string {
                                $lat = (float) $record->latitude;
                                $lng = (float) $record->longitude;

                                $diIndonesia = $lat >= -11 && $lat <= 6 && $lng >= 95 && $lng <= 141;

                                return $diIndonesia
                                    ? 'Titik berada di wilayah Indonesia.'
                                    : '⚠️ Titik berada di luar wilayah Indonesia. Kalau ini tidak disengaja, semua absen karyawan akan ditolak "di luar radius" tanpa petunjuk penyebabnya.';
                            })
                            ->columnSpanFull(),

                        TextEntry::make('arti_radius')
                            ->label('Artinya')
                            ->state(fn (Instansi $record): string => sprintf(
                                'Karyawan hanya bisa absen kalau posisinya kurang dari %d meter dari titik di atas. Jarak dihitung dengan rumus Haversine, jadi radius ini lingkaran — bukan kotak.',
                                $record->radius_meter
                            ))
                            ->columnSpanFull(),
                    ]),

                // Menjelaskan kenapa tombol Hapus disembunyikan, sekaligus
                // menunjukkan seberapa besar yang akan ikut hilang.
                Section::make('Data Terkait')
                    ->icon('heroicon-o-archive-box')
                    ->description('Semua data ini menggantung ke instansi — ikut terhapus permanen kalau instansinya dihapus.')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('jumlah_karyawan')
                            ->label('Karyawan')
                            ->state(fn (Instansi $record): int => $record->karyawan()->count()),
                        TextEntry::make('jumlah_shift')
                            ->label('Shift')
                            ->state(fn (Instansi $record): int => $record->shift()->count()),
                        TextEntry::make('jumlah_qr')
                            ->label('QR')
                            ->state(fn (Instansi $record): string => $record->qrInstansi()->count()
                                .' ('.$record->qrAktif()->count().' aktif)'),
                        TextEntry::make('jumlah_hari_libur')
                            ->label('Hari libur')
                            ->state(fn (Instansi $record): int => $record->hariLiburs()->count()),
                        TextEntry::make('jumlah_pola')
                            ->label('Pola rotasi')
                            ->state(fn (Instansi $record): int => $record->polaRotasis()->count()),

                        TextEntry::make('status_hapus')
                            ->label('Bisa dihapus?')
                            ->state(fn (Instansi $record): string => $record->sedangDipakai()
                                ? 'Tidak. Lewat karyawan, seluruh riwayat absensi & pengajuan juga ikut terhapus. Kalau instansi sudah tidak beroperasi, nonaktifkan saja.'
                                : 'Ya, instansi ini belum punya data terkait apa pun.')
                            ->columnSpanFull(),

                        TextEntry::make('status_kode')
                            ->label('Kode instansi')
                            ->state(fn (Instansi $record): string => $record->kodeMasihBisaDiubah()
                                ? 'Masih bisa diubah — belum ada karyawan maupun QR.'
                                : 'Terkunci karena sudah ada karyawan atau QR yang beredar.')
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
