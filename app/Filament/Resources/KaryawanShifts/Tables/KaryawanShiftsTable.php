<?php

namespace App\Filament\Resources\KaryawanShifts\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class KaryawanShiftsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('karyawan.nama')
                    ->label('Karyawan')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('karyawan.unit_kerja')
                    ->label('Unit Kerja')
                    ->badge()
                    ->color('info')
                    ->toggleable(),

                TextColumn::make('shift.nama_shift')
                    ->label('Shift')
                    ->sortable()
                    ->badge()
                    ->color('primary'),

                TextColumn::make('shift.jam_masuk')
                    ->label('Jam Masuk')
                    ->time('H:i'),

                TextColumn::make('shift.jam_pulang')
                    ->label('Jam Pulang')
                    ->time('H:i'),

                TextColumn::make('tanggal_berlaku')
                    ->label('Berlaku Mulai')
                    ->date('d M Y')
                    ->sortable(),

                TextColumn::make('tanggal_berakhir')
                    ->label('Berlaku Sampai')
                    ->date('d M Y')
                    ->sortable()
                    ->placeholder('—')
                    ->description(fn ($record) => $record->tanggal_berakhir === null ? 'Aktif' : ''),

                TextColumn::make('created_at')
                    ->label('Dibuat')
                    ->dateTime('d M Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('tanggal_berlaku', 'desc')
            ->filters([
                SelectFilter::make('shift_id')
                    ->label('Shift')
                    ->relationship('shift', 'nama_shift'),

                SelectFilter::make('karyawan_id')
                    ->label('Karyawan')
                    ->relationship('karyawan', 'nama')
                    ->searchable(),
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
