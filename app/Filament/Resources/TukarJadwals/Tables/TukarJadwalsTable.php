<?php

namespace App\Filament\Resources\TukarJadwals\Tables;

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

class TukarJadwalsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('karyawanPengaju.nama')
                    ->label('Pengaju')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('tanggal_asal')
                    ->label('Tanggal semula')
                    ->date(),
                TextColumn::make('shiftAsal.nama_shift')
                    ->label('Shift semula')
                    ->badge()
                    ->color('gray'),
                TextColumn::make('mode')
                    ->label('Jenis')
                    ->state(fn ($record) => $record->isPindahSendiri() ? 'Pindah' : 'Tukar')
                    ->badge()
                    ->color(fn ($record) => $record->isPindahSendiri() ? 'info' : 'gray'),
                TextColumn::make('tujuan')
                    ->label('Rekan / Tanggal baru')
                    ->state(fn ($record) => $record->isPindahSendiri()
                        ? $record->tanggal_baru?->format('d M Y')
                        : ($record->karyawanTujuan?->nama . ' — ' . $record->tanggal_tujuan?->format('d M Y') . ' (' . $record->shiftTujuan?->nama_shift . ')')),
                TextColumn::make('alasan')
                    ->limit(30)
                    ->tooltip(fn ($record) => $record->alasan)
                    ->wrap(),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'menunggu_rekan' => 'Menunggu Rekan',
                        'menunggu_admin' => 'Menunggu Admin',
                        'ditolak_rekan' => 'Ditolak Rekan',
                        'approved' => 'Approved',
                        'rejected' => 'Rejected',
                        default => $state,
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'menunggu_rekan' => 'warning',
                        'menunggu_admin' => 'info',
                        'ditolak_rekan' => 'danger',
                        'approved' => 'success',
                        'rejected' => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('approver.name')
                    ->label('Disetujui oleh')
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
                        'menunggu_rekan' => 'Menunggu Rekan',
                        'menunggu_admin' => 'Menunggu Admin',
                        'ditolak_rekan' => 'Ditolak Rekan',
                        'approved' => 'Approved',
                        'rejected' => 'Rejected',
                    ]),
            ])
            ->recordActions([
                ActionGroup::make([
                    ViewAction::make(),
                    EditAction::make()
                        ->visible(fn ($record) => $record->isPending()),
                    Action::make('approve')
                        ->label(fn ($record) => $record->isPindahSendiri() ? 'Setujui & Pindahkan' : 'Setujui & Tukar')
                        ->icon('heroicon-o-check')
                        ->color('success')
                        ->visible(fn ($record) => $record->isPending())
                        ->requiresConfirmation()
                        ->modalDescription(fn ($record) => $record->isPindahSendiri()
                            ? 'Jadwal akan langsung dipindah ke tanggal baru setelah disetujui.'
                            : 'Jadwal kedua karyawan akan langsung tertukar setelah disetujui.')
                        ->action(function ($record) {
                            $record->approveAndSwap(auth()->user());

                            Notification::make()
                                ->title('Tukar jadwal disetujui')
                                ->success()
                                ->send();
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
                        ->action(function ($record, array $data) {
                            $record->reject(auth()->user(), $data['catatan_approval']);

                            Notification::make()
                                ->title('Tukar jadwal ditolak')
                                ->success()
                                ->send();
                        }),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
