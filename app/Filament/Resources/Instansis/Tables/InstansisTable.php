<?php

namespace App\Filament\Resources\Instansis\Tables;

use App\Models\Instansi;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class InstansisTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('nama')
                    ->label('Nama Instansi')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('kode_instansi')
                    ->label('Kode')
                    ->searchable()
                    ->badge()
                    ->color('gray'),

                TextColumn::make('alamat')
                    ->label('Alamat')
                    ->searchable()
                    ->limit(40)
                    ->placeholder('-')
                    ->toggleable(),

                TextColumn::make('telepon')
                    ->label('Telepon')
                    ->searchable()
                    ->placeholder('-')
                    ->toggleable(),

                TextColumn::make('karyawan_count')
                    ->label('Karyawan')
                    ->counts('karyawan')
                    ->badge()
                    ->color(fn (int $state): string => $state > 0 ? 'success' : 'gray')
                    ->toggleable(),

                TextColumn::make('radius_meter')
                    ->label('Radius (m)')
                    ->numeric()
                    ->sortable()
                    ->suffix(' m'),

                TextColumn::make('koordinat')
                    ->label('Koordinat')
                    ->state(fn (Instansi $record): string => $record->latitude.', '.$record->longitude)
                    ->toggleable(isToggledHiddenByDefault: true),

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
            ->defaultSort('nama')
            ->filters([
                TernaryFilter::make('is_active')
                    ->label('Status Aktif')
                    ->trueLabel('Aktif')
                    ->falseLabel('Tidak Aktif'),
            ])
            ->recordActions([
                ActionGroup::make([
                    ViewAction::make(),
                    EditAction::make(),

                    // Lima tabel menggantung ke instansi_id — karyawan, shift,
                    // qr_instansi, hari_liburs, pola_rotasis — dan lewat
                    // karyawan, seluruh riwayat absensi & pengajuan ikut
                    // (semua FK karyawan_id ON DELETE CASCADE). Menghapus satu
                    // instansi berpotensi melenyapkan hampir seluruh isi
                    // sistem. Selama baru ada satu instansi, itu berarti
                    // semuanya.
                    DeleteAction::make()
                        ->visible(fn (Instansi $record): bool => ! $record->sedangDipakai())
                        ->modalDescription('Instansi ini belum punya karyawan, shift, QR, hari libur, maupun pola rotasi, jadi aman dihapus.'),

                    DeleteAction::make('tidak_bisa_hapus')
                        ->label('Hapus')
                        ->icon('heroicon-o-trash')
                        ->color('gray')
                        ->visible(fn (Instansi $record): bool => $record->sedangDipakai())
                        ->requiresConfirmation()
                        ->modalHeading('Instansi ini tidak bisa dihapus')
                        ->modalDescription('Instansi ini masih punya karyawan, shift, QR, hari libur, atau pola rotasi. Menghapusnya akan ikut melenyapkan semuanya beserta seluruh riwayat absensi dan pengajuan karyawannya — permanen. Kalau instansi sudah tidak beroperasi, nonaktifkan saja lewat tombol Ubah.')
                        ->modalSubmitAction(false)
                        ->action(fn () => null),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->before(function (DeleteBulkAction $action, $records) {
                            $terpakai = $records->filter(fn (Instansi $instansi): bool => $instansi->sedangDipakai());

                            if ($terpakai->isNotEmpty()) {
                                Notification::make()
                                    ->title('Sebagian instansi tidak bisa dihapus')
                                    ->body('Masih punya data terkait: '.$terpakai->pluck('nama')->join(', ').'. Tidak ada yang dihapus.')
                                    ->danger()
                                    ->send();

                                $action->cancel();
                            }
                        }),
                ]),
            ]);
    }
}
