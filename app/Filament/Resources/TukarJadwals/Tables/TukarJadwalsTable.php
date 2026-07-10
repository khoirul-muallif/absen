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
                TextColumn::make('jadwal.karyawan.nama')
                    ->label('Pengaju')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('jadwal.tanggal')
                    ->label('Tanggal jadwal pengaju')
                    ->date(),
                TextColumn::make('jadwalTujuan.karyawan.nama')
                    ->label('Rekan tujuan')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('jadwalTujuan.tanggal')
                    ->label('Tanggal jadwal tujuan')
                    ->date(),
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
                    ->label('Setujui & Tukar')
                    ->icon('heroicon-o-arrows-right-left')
                    ->color('success')
                    ->visible(fn ($record) => $record->isPending())
                    ->requiresConfirmation()
                    ->modalDescription('Jadwal kedua karyawan akan langsung tertukar setelah disetujui.')
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
