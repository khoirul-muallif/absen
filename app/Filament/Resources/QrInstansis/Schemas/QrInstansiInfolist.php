<?php

namespace App\Filament\Resources\QrInstansis\Schemas;

use App\Models\QrInstansi;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class QrInstansiInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('QR Code')
                    ->icon('heroicon-o-qr-code')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('instansi.nama')
                            ->label('Instansi'),
                        TextEntry::make('status_validitas')
                            ->label('Status')
                            ->badge()
                            ->state(fn (QrInstansi $record): string => $record->statusValiditas())
                            ->color(fn (string $state): string => match ($state) {
                                'Berlaku'     => 'success',
                                'Kedaluwarsa' => 'warning',
                                'Nonaktif'    => 'danger',
                                default       => 'gray',
                            }),
                        TextEntry::make('kode_qr')
                            ->label('Kode QR')
                            ->copyable()
                            ->copyMessage('Kode QR disalin!')
                            ->helperText('Kode inilah yang di-encode ke gambar QR dan dipindai aplikasi karyawan. Gambar QR-nya sendiri belum dibuat di sini — salin kode ini ke generator QR untuk mencetaknya.')
                            ->columnSpanFull(),
                    ]),

                Section::make('Masa Berlaku')
                    ->icon('heroicon-o-clock')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('expired_at')
                            ->label('Kadaluarsa')
                            ->dateTime('d M Y H:i')
                            ->placeholder('Permanen'),

                        TextEntry::make('arti_masa_berlaku')
                            ->label('Artinya')
                            ->state(function (QrInstansi $record): string {
                                if ($record->expired_at === null) {
                                    return 'QR ini berlaku selamanya sampai dinonaktifkan manual — pilihan yang benar untuk QR yang ditempel tetap di pintu masuk.';
                                }

                                return $record->expired_at->isPast()
                                    ? 'Tanggal kadaluarsa sudah lewat. Pemindaian ditolak walau status aktifnya masih menyala.'
                                    : 'QR berhenti berlaku pada tanggal di samping. Setelah itu pemindaian ditolak tanpa perlu dinonaktifkan manual.';
                            })
                            ->columnSpanFull(),
                    ]),

                Section::make('Pemakaian')
                    ->icon('heroicon-o-archive-box')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('jumlah_absensi')
                            ->label('Dipakai absen')
                            ->state(fn (QrInstansi $record): string => $record->absensi()->count().' kali'),

                        TextEntry::make('status_kode')
                            ->label('Kode QR')
                            ->state(fn (QrInstansi $record): string => $record->kodeMasihBisaDiubah()
                                ? 'Masih bisa diubah — belum pernah dipakai absen, jadi kemungkinan besar QR fisiknya belum dicetak.'
                                : 'Terkunci. QR fisiknya sudah tercetak dan tertempel; mengubah kode akan membuat semua QR terpasang tidak valid.'),

                        TextEntry::make('status_hapus')
                            ->label('Bisa dihapus?')
                            ->state(fn (QrInstansi $record): string => $record->sedangDipakai()
                                ? 'Tidak. Riwayat absensi merujuk ke QR ini (foreign key RESTRICT). Kalau fisiknya sudah dicabut, nonaktifkan saja.'
                                : 'Ya, QR ini belum pernah dipakai absen.')
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
