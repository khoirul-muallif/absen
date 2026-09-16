<?php

namespace App\Filament\Resources\QrInstansis\Tables;

use App\Models\QrInstansi;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class QrInstansisTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('instansi.nama')
                    ->label('Instansi')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('kode_qr')
                    ->label('Kode QR')
                    ->searchable()
                    ->limit(20)
                    ->tooltip(fn ($record) => $record->kode_qr)
                    ->badge()
                    ->color('gray')
                    ->copyable()
                    ->copyMessage('Kode QR disalin!')
                    ->copyMessageDuration(1500),

                // Menggantikan IconColumn is_active yang menyesatkan: QR dengan
                // expired_at sudah lewat tetap tampil bercentang hijau padahal
                // sudah tidak bisa dipakai absen.
                TextColumn::make('status_validitas')
                    ->label('Status')
                    ->badge()
                    ->state(fn (QrInstansi $record): string => $record->statusValiditas())
                    ->color(fn (string $state): string => match ($state) {
                        'Berlaku'     => 'success',
                        'Kedaluwarsa' => 'warning',
                        'Nonaktif'    => 'danger',
                        default       => 'gray',
                    })
                    ->tooltip(fn (string $state): string => match ($state) {
                        'Berlaku'     => 'Bisa dipakai absen.',
                        'Kedaluwarsa' => 'Masih aktif, tapi tanggal kadaluarsanya sudah lewat — pemindaian ditolak.',
                        'Nonaktif'    => 'Dimatikan manual, pemindaian ditolak.',
                        default       => '',
                    }),

                TextColumn::make('expired_at')
                    ->label('Kadaluarsa')
                    ->dateTime('d M Y H:i')
                    ->sortable()
                    ->placeholder('Permanen')
                    ->color(fn ($record) => $record->expired_at?->isPast() ? 'danger' : null),

                TextColumn::make('absensi_count')
                    ->label('Dipakai absen')
                    ->counts('absensi')
                    ->badge()
                    ->color(fn (int $state): string => $state > 0 ? 'info' : 'gray')
                    ->suffix('x')
                    ->toggleable(),

                TextColumn::make('created_at')
                    ->label('Dibuat')
                    ->dateTime('d M Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('instansi_id')
                    ->label('Instansi')
                    ->relationship('instansi', 'nama'),

                TernaryFilter::make('is_active')
                    ->label('Status QR')
                    ->trueLabel('Aktif')
                    ->falseLabel('Tidak Aktif'),

                // Filter ini yang benar-benar menjawab "QR mana yang bisa
                // dipakai sekarang" — beda dari is_active yang mengabaikan
                // kadaluarsa.
                Filter::make('masih_berlaku')
                    ->label('Hanya yang masih berlaku')
                    ->query(fn (Builder $query): Builder => $query
                        ->where('is_active', true)
                        ->where(fn (Builder $q) => $q
                            ->whereNull('expired_at')
                            ->orWhere('expired_at', '>', now())))
                    ->toggle(),
            ])
            ->recordActions([
                ActionGroup::make([
                    ViewAction::make(),
                    EditAction::make(),

                    // absensi.qr_instansi_id memakai ON DELETE RESTRICT, jadi
                    // tanpa guard ini penghapusan QR yang pernah dipakai absen
                    // melempar QueryException 1451 mentah ke layar.
                    DeleteAction::make()
                        ->visible(fn (QrInstansi $record): bool => ! $record->sedangDipakai()),

                    DeleteAction::make('tidak_bisa_hapus')
                        ->label('Hapus')
                        ->icon('heroicon-o-trash')
                        ->color('gray')
                        ->visible(fn (QrInstansi $record): bool => $record->sedangDipakai())
                        ->requiresConfirmation()
                        ->modalHeading('QR ini tidak bisa dihapus')
                        ->modalDescription('QR ini sudah pernah dipakai absen, dan riwayat absensinya merujuk ke sini. Menghapusnya akan merusak riwayat yang sudah tercatat. Kalau QR fisiknya sudah dicabut, nonaktifkan saja lewat tombol Ubah.')
                        ->modalSubmitAction(false)
                        ->action(fn () => null),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->before(function (DeleteBulkAction $action, $records) {
                            $terpakai = $records->filter(fn (QrInstansi $qr): bool => $qr->sedangDipakai());

                            if ($terpakai->isNotEmpty()) {
                                Notification::make()
                                    ->title('Sebagian QR tidak bisa dihapus')
                                    ->body('Sudah pernah dipakai absen: '.$terpakai->count().' QR. Tidak ada yang dihapus — nonaktifkan saja kalau fisiknya sudah dicabut.')
                                    ->danger()
                                    ->send();

                                $action->cancel();
                            }
                        }),
                ]),
            ]);
    }
}
