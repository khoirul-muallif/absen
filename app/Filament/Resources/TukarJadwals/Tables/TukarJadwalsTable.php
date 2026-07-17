<?php

namespace App\Filament\Resources\TukarJadwals\Tables;

use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Textarea;
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
            ])
            ->recordActions([
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
                    ->action(fn ($record) => $record->approveAndSwap(auth()->user())),
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
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
