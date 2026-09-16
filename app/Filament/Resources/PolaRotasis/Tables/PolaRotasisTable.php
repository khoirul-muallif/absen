<?php

namespace App\Filament\Resources\PolaRotasis\Tables;

use App\Models\PolaRotasi;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class PolaRotasisTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('unit_kerja')
                    ->label('Unit Kerja')
                    ->badge()
                    ->color('info')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('nama_pola')
                    ->label('Nama Pola')
                    ->searchable()
                    ->weight('bold'),

                TextColumn::make('langkah')
                    ->label('Panjang Siklus')
                    // Versi lama: count($record->langkah) langsung. Kalau kolom
                    // langkah null (bukan array kosong), count() melempar
                    // TypeError dan SELURUH tabel gagal dirender, bukan cuma
                    // satu baris.
                    ->state(fn (PolaRotasi $record): string => $record->panjangSiklus().' hari')
                    ->badge()
                    ->color(fn (PolaRotasi $record): string => $record->panjangSiklus() === 0 ? 'danger' : 'gray')
                    ->tooltip(fn (PolaRotasi $record): ?string => $record->panjangSiklus() === 0
                        ? 'Pola ini belum punya langkah sama sekali — generator akan melewatinya.'
                        : null),

                IconColumn::make('berlaku_saat_libur_nasional')
                    ->label('Tetap Saat Libur Nasional')
                    ->boolean()
                    ->tooltip('Aktif = unit 24 jam, tetap dijadwalkan saat libur nasional.'),

                TextColumn::make('karyawan_pola_rotasis_count')
                    ->label('Dipakai')
                    ->counts('karyawanPolaRotasis')
                    ->badge()
                    ->color(fn (int $state): string => $state > 0 ? 'success' : 'gray')
                    ->suffix(' karyawan')
                    ->toggleable(),

                IconColumn::make('is_active')
                    ->label('Aktif')
                    ->boolean(),

                TextColumn::make('created_at')
                    ->label('Dibuat')
                    ->dateTime('d M Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('unit_kerja')
            ->filters([
                SelectFilter::make('unit_kerja')
                    ->label('Unit Kerja')
                    ->options(fn () => PolaRotasi::query()->distinct()->pluck('unit_kerja', 'unit_kerja')),

                TernaryFilter::make('is_active')
                    ->label('Status Aktif'),

                TernaryFilter::make('berlaku_saat_libur_nasional')
                    ->label('Tetap saat libur nasional'),
            ])
            ->recordActions([
                ActionGroup::make([
                    ViewAction::make(),

                    EditAction::make(),

                    // Perilaku FK karyawan_pola_rotasis.pola_rotasi_id tidak
                    // disebut di SCHEMA.md — kalau CASCADE, penghapusan
                    // menghilangkan assignment diam-diam dan karyawan rotasi
                    // kehilangan sumber jadwalnya; kalau RESTRICT, muncul
                    // QueryException 1451 mentah. Guard ini aman untuk keduanya.
                    DeleteAction::make()
                        ->visible(fn (PolaRotasi $record): bool => ! $record->sedangDipakai()),

                    DeleteAction::make('tidak_bisa_hapus')
                        ->label('Hapus')
                        ->icon('heroicon-o-trash')
                        ->color('gray')
                        ->visible(fn (PolaRotasi $record): bool => $record->sedangDipakai())
                        ->requiresConfirmation()
                        ->modalHeading('Pola ini tidak bisa dihapus')
                        ->modalDescription('Pola ini masih di-assign ke karyawan lewat menu Shift Karyawan Rotasi. Menghapusnya membuat karyawan itu kehilangan sumber jadwalnya. Lepas dulu assignment-nya, atau nonaktifkan pola ini lewat tombol Ubah.')
                        ->modalSubmitAction(false)
                        ->action(fn () => null),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->before(function (DeleteBulkAction $action, $records) {
                            $terpakai = $records->filter(fn (PolaRotasi $pola): bool => $pola->sedangDipakai());

                            if ($terpakai->isNotEmpty()) {
                                Notification::make()
                                    ->title('Sebagian pola tidak bisa dihapus')
                                    ->body('Masih di-assign ke karyawan: '.$terpakai->pluck('nama_pola')->join(', ').'. Tidak ada yang dihapus.')
                                    ->danger()
                                    ->send();

                                $action->cancel();
                            }
                        }),
                ]),
            ]);
    }
}
