<?php

namespace App\Filament\Resources\Absensis\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class AbsensisTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('karyawan.id')
                    ->searchable(),
                TextColumn::make('shift.id')
                    ->searchable(),
                TextColumn::make('qrInstansi.id')
                    ->searchable(),
                TextColumn::make('tanggal')
                    ->date()
                    ->sortable(),
                TextColumn::make('waktu_masuk')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('latitude_masuk')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('longitude_masuk')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('foto_masuk')
                    ->searchable(),
                TextColumn::make('waktu_pulang')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('latitude_pulang')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('longitude_pulang')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('foto_pulang')
                    ->searchable(),
                TextColumn::make('status')
                    ->badge(),
                TextColumn::make('keterangan')
                    ->searchable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
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
