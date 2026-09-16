<?php

namespace App\Filament\Resources\Karyawans\Tables;

use App\Models\Karyawan;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class KaryawansTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('foto_profil')
                    ->label('')
                    ->circular()
                    ->defaultImageUrl(fn () => 'https://ui-avatars.com/api/?name=K&background=1D9E75&color=fff'),

                TextColumn::make('nama')
                    ->label('Nama')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('nip')
                    ->label('NIP')
                    ->searchable()
                    ->badge()
                    ->color('gray'),

                TextColumn::make('instansi.nama')
                    ->label('Instansi')
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('unit_kerja')
                    ->label('Unit Kerja')
                    ->searchable()
                    ->badge()
                    ->color('info')
                    ->placeholder('-'),

                // Field yang paling banyak mencabangkan perilaku sistem (fase 13)
                // sebelumnya sama sekali tidak muncul di tabel — bukan kolom,
                // bukan filter. Admin tidak punya cara melihat siapa umum dan
                // siapa rotasi selain membuka satu per satu.
                TextColumn::make('tipe_jadwal')
                    ->label('Tipe Jadwal')
                    ->badge()
                    ->color(fn (string $state): string => $state === Karyawan::TIPE_ROTASI ? 'warning' : 'primary')
                    ->formatStateUsing(fn (string $state): string => $state === Karyawan::TIPE_ROTASI ? 'Rotasi' : 'Umum')
                    ->tooltip(fn (string $state): string => $state === Karyawan::TIPE_ROTASI
                        ? 'Dijadwalkan dari pola siklus lewat menu Shift Karyawan Rotasi.'
                        : 'Dijadwalkan lewat penugasan shift periode di menu Shift Karyawan Umum.')
                    ->sortable(),

                TextColumn::make('jabatan')
                    ->label('Jabatan')
                    ->searchable()
                    ->toggleable(),

                TextColumn::make('status_pegawai')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'tetap'     => 'success',
                        'kontrak'   => 'warning',
                        'orientasi' => 'info',
                        'magang'    => 'gray',
                        default     => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'tetap'     => 'Tetap',
                        'kontrak'   => 'Kontrak',
                        'orientasi' => 'Orientasi',
                        'magang'    => 'Magang',
                        default     => $state,
                    }),

                TextColumn::make('role')
                    ->label('Role')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'admin'    => 'danger',
                        'karyawan' => 'primary',
                        default    => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => ucfirst($state))
                    ->tooltip('Belum mengatur akses apa pun — approval selalu lewat akun admin Filament terpisah.')
                    ->toggleable(),

                TextColumn::make('email')
                    ->label('Email')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('nomor_telepon')
                    ->label('Telepon')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('tanggal_bergabung')
                    ->label('Bergabung')
                    ->date('d M Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                IconColumn::make('is_active')
                    ->label('Aktif')
                    ->boolean()
                    ->trueColor('success')
                    ->falseColor('danger'),
            ])
            ->defaultSort('nama')
            ->filters([
                SelectFilter::make('instansi_id')
                    ->label('Instansi')
                    ->relationship('instansi', 'nama'),

                SelectFilter::make('tipe_jadwal')
                    ->label('Tipe Jadwal')
                    ->options([
                        Karyawan::TIPE_UMUM   => 'Umum',
                        Karyawan::TIPE_ROTASI => 'Rotasi',
                    ]),

                SelectFilter::make('unit_kerja')
                    ->label('Unit Kerja')
                    ->options(fn () => Karyawan::query()
                        ->whereNotNull('unit_kerja')
                        ->distinct()
                        ->orderBy('unit_kerja')
                        ->pluck('unit_kerja', 'unit_kerja')),

                SelectFilter::make('status_pegawai')
                    ->label('Status Pegawai')
                    ->options([
                        'tetap'     => 'Tetap',
                        'kontrak'   => 'Kontrak',
                        'orientasi' => 'Orientasi',
                        'magang'    => 'Magang',
                    ]),

                SelectFilter::make('role')
                    ->label('Role')
                    ->options([
                        'admin'    => 'Admin',
                        'karyawan' => 'Karyawan',
                    ]),

                TernaryFilter::make('is_active')
                    ->label('Status Aktif')
                    ->trueLabel('Aktif')
                    ->falseLabel('Tidak Aktif'),
            ])
            ->recordActions([
                ActionGroup::make([
                    ViewAction::make(),
                    EditAction::make(),

                    // SELURUH FK ke karyawan_id memakai ON DELETE CASCADE
                    // (SCHEMA.md), jadi satu klik Hapus melenyapkan absensi,
                    // cuti, izin, lembur, dinas, jadwal, kuota, dan penugasan
                    // orang itu — permanen, tanpa jejak, tanpa peringatan apa
                    // pun sebelumnya. Untuk karyawan yang berhenti bekerja,
                    // yang benar adalah menonaktifkan.
                    DeleteAction::make()
                        ->visible(fn (Karyawan $record): bool => ! $record->punyaRiwayat())
                        ->modalDescription('Karyawan ini belum punya riwayat absensi maupun pengajuan, jadi aman dihapus.'),

                    DeleteAction::make('tidak_bisa_hapus')
                        ->label('Hapus')
                        ->icon('heroicon-o-trash')
                        ->color('gray')
                        ->visible(fn (Karyawan $record): bool => $record->punyaRiwayat())
                        ->requiresConfirmation()
                        ->modalHeading('Karyawan ini tidak bisa dihapus')
                        ->modalDescription('Karyawan ini sudah punya riwayat (absensi, pengajuan, jadwal, atau penugasan shift). Menghapusnya akan ikut melenyapkan SEMUA data itu secara permanen. Untuk karyawan yang sudah berhenti bekerja, nonaktifkan saja lewat tombol Ubah — datanya tetap tersimpan untuk rekap dan audit.')
                        ->modalSubmitAction(false)
                        ->action(fn () => null),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->before(function (DeleteBulkAction $action, $records) {
                            $punyaRiwayat = $records->filter(fn (Karyawan $karyawan): bool => $karyawan->punyaRiwayat());

                            if ($punyaRiwayat->isNotEmpty()) {
                                Notification::make()
                                    ->title('Sebagian karyawan tidak bisa dihapus')
                                    ->body('Sudah punya riwayat absensi/pengajuan: '.$punyaRiwayat->pluck('nama')->join(', ').'. Tidak ada yang dihapus — nonaktifkan saja kalau sudah berhenti bekerja.')
                                    ->danger()
                                    ->send();

                                $action->cancel();
                            }
                        }),
                ]),
            ]);
    }
}
