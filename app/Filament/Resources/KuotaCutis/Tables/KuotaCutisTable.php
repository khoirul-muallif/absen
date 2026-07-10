<?php

namespace App\Filament\Resources\KuotaCutis\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class KuotaCutisTable
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
                TextColumn::make('tahun')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('kuota')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('terpakai')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('sisa')
                    ->label('Sisa')
                    ->state(fn ($record) => $record->kuota - $record->terpakai)
                    ->badge()
                    ->color(fn ($record) => match (true) {
                        ($record->kuota - $record->terpakai) <= 0 => 'danger',
                        ($record->kuota - $record->terpakai) <= 3 => 'warning',
                        default => 'success',
                    }),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('tahun', 'desc')
            ->filters([
                SelectFilter::make('tahun')
                    ->options(fn () => \App\Models\KuotaCuti::query()
                        ->distinct()
                        ->orderByDesc('tahun')
                        ->pluck('tahun', 'tahun')
                        ->toArray()),
                SelectFilter::make('jenis_cuti_id')
                    ->label('Jenis cuti')
                    ->relationship('jenisCuti', 'nama'),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
