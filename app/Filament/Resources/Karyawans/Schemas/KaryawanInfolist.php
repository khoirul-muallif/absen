<?php

namespace App\Filament\Resources\Karyawans\Schemas;

use App\Models\Karyawan;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class KaryawanInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Identitas')
                    ->icon('heroicon-o-user')
                    ->columns(2)
                    ->schema([
                        ImageEntry::make('foto_profil')
                            ->label('Foto profil')
                            ->circular()
                            ->placeholder('-'),
                        TextEntry::make('nama')
                            ->label('Nama lengkap'),
                        TextEntry::make('nip')
                            ->label('NIP')
                            ->badge()
                            ->color('gray'),
                        TextEntry::make('email'),
                        TextEntry::make('nomor_telepon')
                            ->label('Nomor telepon')
                            ->placeholder('-'),
                        IconEntry::make('is_active')
                            ->label('Aktif')
                            ->boolean(),
                    ]),

                Section::make('Jabatan & Unit')
                    ->icon('heroicon-o-briefcase')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('instansi.nama')
                            ->label('Instansi'),
                        TextEntry::make('unit_kerja')
                            ->label('Unit kerja')
                            ->badge()
                            ->color('info')
                            ->placeholder('-'),
                        TextEntry::make('jabatan')
                            ->placeholder('-'),
                        TextEntry::make('status_pegawai')
                            ->label('Status pegawai')
                            ->badge()
                            ->formatStateUsing(fn (string $state): string => ucfirst($state)),
                        TextEntry::make('tanggal_bergabung')
                            ->label('Tanggal bergabung')
                            ->date('d M Y')
                            ->placeholder('-'),
                        TextEntry::make('role')
                            ->badge()
                            ->formatStateUsing(fn (string $state): string => ucfirst($state))
                            ->helperText('Murni label — belum mengatur akses apa pun. Approval selalu lewat akun admin Filament terpisah.'),
                    ]),

                Section::make('Penjadwalan')
                    ->icon('heroicon-o-calendar-days')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('tipe_jadwal')
                            ->label('Tipe jadwal')
                            ->badge()
                            ->color(fn (string $state): string => $state === Karyawan::TIPE_ROTASI ? 'warning' : 'primary')
                            ->formatStateUsing(fn (string $state): string => $state === Karyawan::TIPE_ROTASI ? 'Rotasi' : 'Umum'),

                        TextEntry::make('jumlah_assignment')
                            ->label('Penugasan tercatat')
                            ->state(fn (Karyawan $record): string => $record->isRotasi()
                                ? $record->karyawanPolaRotasis()->count().' assignment pola rotasi'
                                : $record->karyawanShift()->count().' penugasan shift periode'),

                        TextEntry::make('arti_tipe_jadwal')
                            ->label('Artinya')
                            ->state(fn (Karyawan $record): string => $record->isRotasi()
                                ? 'Dijadwalkan dari pola siklus lewat menu "Shift Karyawan Rotasi" (grup Manajemen Rotasi). Unit kerja harus sama persis dengan unit pola, kalau tidak pola tidak bisa di-assign. Kalau tidak pernah di-assign, rekap harian menandainya "jadwal_hilang" — bukan alpha, bukan libur, melainkan anomali yang perlu dicek manual.'
                                : 'Dijadwalkan lewat penugasan shift periode di menu "Shift Karyawan Umum" (grup Manajemen Shift). Tanpa penugasan yang berlaku, absen lewat aplikasi ditolak dengan "Tidak ada shift aktif untuk hari ini".')
                            ->columnSpanFull(),

                        TextEntry::make('catatan_ubah_tipe')
                            ->label('Catatan')
                            ->visible(fn (Karyawan $record): bool => $record->punyaAssignmentJadwal())
                            ->state('Tipe jadwal tidak bisa diubah selama penugasan di atas masih ada — lepas dulu penugasannya, supaya tidak meninggalkan data yang tidak dipakai siapa pun.')
                            ->columnSpanFull(),
                    ]),

                // Menjelaskan kenapa tombol Hapus disembunyikan, sekaligus
                // menunjukkan seberapa besar yang akan ikut hilang kalau
                // penghapusan tetap dipaksakan lewat jalur lain.
                Section::make('Riwayat Terkait')
                    ->icon('heroicon-o-archive-box')
                    ->description('Semua data ini terhubung ke karyawan dengan ON DELETE CASCADE — ikut terhapus permanen kalau akunnya dihapus.')
                    ->columns(4)
                    ->schema([
                        TextEntry::make('jumlah_absensi')
                            ->label('Absensi')
                            ->state(fn (Karyawan $record): int => $record->absensi()->count()),
                        TextEntry::make('jumlah_jadwal')
                            ->label('Jadwal')
                            ->state(fn (Karyawan $record): int => $record->jadwals()->count()),
                        TextEntry::make('jumlah_cuti')
                            ->label('Cuti')
                            ->state(fn (Karyawan $record): int => $record->cutis()->count()),
                        TextEntry::make('jumlah_izin')
                            ->label('Izin')
                            ->state(fn (Karyawan $record): int => $record->izins()->count()),
                        TextEntry::make('jumlah_lembur')
                            ->label('Lembur')
                            ->state(fn (Karyawan $record): int => $record->lemburs()->count()),
                        TextEntry::make('jumlah_dinas')
                            ->label('Dinas')
                            ->state(fn (Karyawan $record): int => $record->dinas()->count()),
                        TextEntry::make('jumlah_kuota')
                            ->label('Kuota cuti')
                            ->state(fn (Karyawan $record): int => $record->kuotaCutis()->count()),

                        TextEntry::make('status_hapus')
                            ->label('Bisa dihapus?')
                            ->state(fn (Karyawan $record): string => $record->punyaRiwayat()
                                ? 'Tidak. Untuk karyawan yang sudah berhenti bekerja, nonaktifkan saja — datanya tetap tersimpan untuk rekap dan audit.'
                                : 'Ya, karyawan ini belum punya riwayat apa pun.')
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
