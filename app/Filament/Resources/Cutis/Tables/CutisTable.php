<?php

namespace App\Filament\Resources\Cutis\Tables;

use App\Exceptions\KuotaCutiTidakCukupException;
use App\Models\Cuti;
use App\Models\KuotaCuti;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class CutisTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('karyawan.nama')
                    ->label('Karyawan')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('jenisCuti.nama')
                    ->label('Jenis cuti')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('tanggal_mulai')
                    ->date()
                    ->sortable(),
                TextColumn::make('tanggal_selesai')
                    ->date()
                    ->sortable(),
                TextColumn::make('jumlah_hari')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('alasan')
                    ->limit(30)
                    ->tooltip(fn ($record) => $record->alasan)
                    ->wrap(),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'pending' => 'warning',
                        'approved' => 'success',
                        'rejected' => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('approver.name')
                    ->label('Disetujui oleh')
                    ->placeholder('-')
                    ->toggleable(),
                TextColumn::make('approved_at')
                    ->dateTime()
                    ->sortable()
                    ->placeholder('-')
                    ->toggleable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'approved' => 'Approved',
                        'rejected' => 'Rejected',
                    ]),
                SelectFilter::make('jenis_cuti_id')
                    ->label('Jenis cuti')
                    ->relationship('jenisCuti', 'nama'),
            ])
            ->recordActions([
                ActionGroup::make([
                    ViewAction::make(),
                    EditAction::make()
                        ->visible(fn ($record) => $record->isPending()),
                    Action::make('approve')
                        ->label('Setujui')
                        ->icon('heroicon-o-check')
                        ->color(fn ($record) => match (self::infoKuota($record)['keadaan']) {
                            'kurang' => 'danger',
                            default => 'success',
                        })
                        ->tooltip(function ($record) {
                            $info = self::infoKuota($record);

                            return match ($info['keadaan']) {
                                'kurang' => "⚠ Sisa kuota ({$info['sisa']}) kurang dari jumlah hari yang diajukan ({$record->jumlah_hari})",
                                'aman' => $info['pending'] > 0
                                    ? "Sisa {$info['sisa']} · pending lain {$info['pending']} hari · efektif {$info['efektif']}"
                                    : null,
                                default => null,
                            };
                        })
                        ->visible(fn ($record) => $record->isPending())
                        ->requiresConfirmation()
                        ->action(function ($record) {
                            try {
                                $record->approve(auth()->user());

                                Notification::make()
                                    ->title('Cuti disetujui')
                                    ->success()
                                    ->send();
                            } catch (KuotaCutiTidakCukupException $e) {
                                Notification::make()
                                    ->title('Gagal menyetujui cuti')
                                    ->body($e->getMessage())
                                    ->danger()
                                    ->send();
                            }
                        }),
                    Action::make('reject')
                        ->label('Tolak')
                        ->icon('heroicon-o-x-mark')
                        ->color('danger')
                        ->visible(fn ($record) => $record->isPending())
                        ->requiresConfirmation()
                        ->schema([
                            Textarea::make('catatan_approval')
                                ->label('Alasan penolakan')
                                ->required(),
                        ])
                        ->action(fn ($record, array $data) => $record->reject(auth()->user(), $data['catatan_approval'])),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    /**
     * Keadaan kuota untuk satu record, dipakai bersama oleh color() & tooltip()
     * pada action approve.
     *
     * 3 keadaan (sebelumnya 4 — 'belum_ada_row' dihapus di fase 42 karena
     * KuotaCuti::pastikanUntuk() sekarang membuat row otomatis saat approve,
     * jadi "belum ada row" tidak lagi berarti "tidak diperiksa"):
     *   tidak_potong - jenis cuti ini memang tidak menyentuh kuota
     *   kurang       - sisa (row ada, atau default_kuota kalau row belum ada)
     *                  < jumlah_hari; approve akan gagal dengan
     *                  KuotaCutiTidakCukupException
     *   aman         - approve akan lolos
     *
     * Catatan: 'kurang' dinilai dari sisa MENTAH (kuota - terpakai, atau
     * default_kuota kalau row belum ada), bukan sisa efektif setelah dikurangi
     * pending lain — karena itulah yang benar-benar dicek Cuti::afterApprove().
     */
    protected static function infoKuota($record): array
    {
        if (! $record->jenisCuti?->potong_kuota) {
            return ['keadaan' => 'tidak_potong'];
        }

        $tahun = $record->tanggal_mulai->year;
        $semester = $record->jenisCuti->semesterDari($record->tanggal_mulai);

        $sisa = KuotaCuti::sisaUntuk($record->karyawan_id, $record->jenis_cuti_id, $tahun, $semester)
            ?? $record->jenisCuti->default_kuota;

        $pending = Cuti::hariPendingUntuk(
            $record->karyawan_id,
            $record->jenis_cuti_id,
            $tahun,
            $record->id,
            $semester
        );

        return [
            'keadaan' => $sisa >= $record->jumlah_hari ? 'aman' : 'kurang',
            'tahun' => $tahun,
            'semester' => $semester,
            'sisa' => $sisa,
            'pending' => $pending,
            'efektif' => $sisa - $pending,
        ];
    }
}
