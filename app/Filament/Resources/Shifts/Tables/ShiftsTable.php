<?php

namespace App\Filament\Resources\Shifts\Tables;

use App\Models\Shift;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class ShiftsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('nama_shift')
                    ->label('Nama Shift')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    // Nama saja bisa ambigu: "Pagi" IGD dan "Pagi" Rawat Jalan
                    // adalah dua baris berbeda dengan jam berbeda.
                    ->description(fn (Shift $record): string => $record->jam_masuk->format('H:i').'–'.$record->jam_pulang->format('H:i')),

                TextColumn::make('instansi.nama')
                    ->label('Instansi')
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('jam_masuk')
                    ->label('Jam Masuk')
                    ->time('H:i')
                    ->sortable(),

                TextColumn::make('jam_pulang')
                    ->label('Jam Pulang')
                    ->time('H:i')
                    ->sortable(),

                TextColumn::make('toleransi_menit')
                    ->label('Toleransi')
                    ->numeric()
                    ->sortable()
                    ->suffix(' menit')
                    ->badge()
                    // Abu-abu kalau mode harian, karena angkanya memang tidak
                    // dipakai sama sekali di mode itu.
                    ->color(fn (Shift $record): string => $record->mode_toleransi === 'akumulasi_bulanan' ? 'warning' : 'gray')
                    ->tooltip(fn (Shift $record): string => $record->mode_toleransi === 'akumulasi_bulanan'
                        ? 'Dipakai sebagai batas akumulasi keterlambatan sebulan.'
                        : 'Tidak dipakai — mode harian mengabaikan toleransi.'),

                TextColumn::make('mode_toleransi')
                    ->label('Mode Toleransi')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => $state === 'akumulasi_bulanan' ? 'Akumulasi' : 'Harian')
                    ->color(fn (string $state) => $state === 'akumulasi_bulanan' ? 'info' : 'gray')
                    ->toggleable(),

                TextColumn::make('hari_kerja')
                    ->label('Hari Kerja')
                    ->state(function ($record): string {
                        $hariKerja = $record->hari_kerja;

                        if (empty($hariKerja) || ! is_array($hariKerja)) {
                            return 'Setiap hari';
                        }

                        $nama = [0 => 'Min', 1 => 'Sen', 2 => 'Sel', 3 => 'Rab', 4 => 'Kam', 5 => 'Jum', 6 => 'Sab'];
                        sort($hariKerja);

                        return collect($hariKerja)->map(fn ($hari) => $nama[$hari] ?? '?')->join(', ');
                    })
                    ->toggleable(),

                IconColumn::make('is_active')
                    ->label('Aktif')
                    ->boolean()
                    ->trueColor('success')
                    ->falseColor('danger'),

                TextColumn::make('created_at')
                    ->label('Dibuat')
                    ->dateTime('d M Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('nama_shift')
            ->filters([
                SelectFilter::make('instansi_id')
                    ->label('Instansi')
                    ->relationship('instansi', 'nama'),

                TernaryFilter::make('is_active')
                    ->label('Status Aktif')
                    ->trueLabel('Aktif')
                    ->falseLabel('Tidak Aktif'),
            ])
            ->recordActions([
                ActionGroup::make([
                    EditAction::make(),

                    // absensi.shift_id memakai ON DELETE RESTRICT, jadi tanpa
                    // guard ini penghapusan shift yang pernah dipakai absensi
                    // melempar QueryException 1451 mentah ke layar. Relasi
                    // jadwals & karyawan_shift ikut dicek supaya penghapusan
                    // tidak diam-diam menghilangkan jadwal atau assignment.
                    DeleteAction::make()
                        ->visible(fn (Shift $record): bool => ! $record->sedangDipakai()),

                    DeleteAction::make('tidak_bisa_hapus')
                        ->label('Hapus')
                        ->icon('heroicon-o-trash')
                        ->color('gray')
                        ->visible(fn (Shift $record): bool => $record->sedangDipakai())
                        ->requiresConfirmation()
                        ->modalHeading('Shift ini tidak bisa dihapus')
                        ->modalDescription('Shift ini masih dipakai oleh data absensi, jadwal, atau penugasan karyawan. Menghapusnya akan merusak riwayat yang sudah tercatat. Nonaktifkan saja lewat tombol Ubah kalau shift ini sudah tidak dipakai lagi.')
                        ->modalSubmitAction(false)
                        ->action(fn () => null),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        // Bulk delete lebih berbahaya: tanpa guard, satu baris
                        // yang masih dipakai bikin seluruh batch gagal di tengah.
                        ->before(function (DeleteBulkAction $action, $records) {
                            $terpakai = $records->filter(fn (Shift $shift): bool => $shift->sedangDipakai());

                            if ($terpakai->isNotEmpty()) {
                                Notification::make()
                                    ->title('Sebagian shift tidak bisa dihapus')
                                    ->body('Masih dipakai data absensi/jadwal/penugasan: '.$terpakai->pluck('nama_shift')->join(', ').'. Tidak ada yang dihapus.')
                                    ->danger()
                                    ->send();

                                $action->cancel();
                            }
                        }),
                ]),
            ]);
    }
}
