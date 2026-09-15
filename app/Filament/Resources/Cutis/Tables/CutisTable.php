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
                            'belum_ada_row' => 'warning',
                            default => 'success',
                        })
                        ->tooltip(function ($record) {
                            $info = self::infoKuota($record);

                            return match ($info['keadaan']) {
                                'kurang' => "⚠ Sisa kuota ({$info['sisa']}) kurang dari jumlah hari yang diajukan ({$record->jumlah_hari})",
                                'belum_ada_row' => "Belum ada data kuota tahun {$info['tahun']} — approve tetap bisa, tapi tidak akan memotong kuota.",
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
     * 4 keadaan yang sengaja dibedakan (sebelumnya cuma 2, dan row yang belum
     * ada di-treat sebagai sisa 0 — bikin tombol merah untuk approve yang
     * sebenarnya akan sukses):
     *   tidak_potong  - jenis cuti ini memang tidak menyentuh kuota
     *   belum_ada_row - KuotaCuti belum pernah dibuat (kebijakan fase 22:
     *                   bukan dasar menolak, tapi admin tetap perlu tahu
     *                   bahwa approve ini tidak akan tercatat di kuota)
     *   kurang        - sisa nyata di DB < jumlah_hari; approve akan gagal
     *                   dengan KuotaCutiTidakCukupException
     *   aman          - approve akan lolos
     *
     * Catatan: 'kurang' sengaja dinilai dari sisa MENTAH (kuota - terpakai),
     * bukan sisa efektif setelah dikurangi pending lain — karena itulah yang
     * benar-benar dicek Cuti::afterApprove(). Pengajuan pending lain tidak
     * membuat approve ini gagal, jadi cuma diinformasikan lewat tooltip.
     */
    protected static function infoKuota($record): array
    {
        if (! $record->jenisCuti?->potong_kuota) {
            return ['keadaan' => 'tidak_potong'];
        }

        $tahun = $record->tanggal_mulai->year;

        $sisa = KuotaCuti::sisaUntuk($record->karyawan_id, $record->jenis_cuti_id, $tahun);

        if ($sisa === null) {
            return ['keadaan' => 'belum_ada_row', 'tahun' => $tahun];
        }

        $pending = Cuti::hariPendingUntuk(
            $record->karyawan_id,
            $record->jenis_cuti_id,
            $tahun,
            $record->id
        );

        return [
            'keadaan' => $sisa >= $record->jumlah_hari ? 'aman' : 'kurang',
            'tahun' => $tahun,
            'sisa' => $sisa,
            'pending' => $pending,
            'efektif' => $sisa - $pending,
        ];
    }
}
