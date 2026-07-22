<?php

namespace App\Filament\Resources\KaryawanPolaRotasis\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class KaryawanPolaRotasisTable
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

                TextColumn::make('polaRotasi.nama_pola')
                    ->label('Pola Rotasi')
                    ->badge()
                    ->color('primary')
                    ->sortable(),

                TextColumn::make('tanggal_mulai')
                    ->label('Mulai (Anchor)')
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
            ->defaultSort('tanggal_mulai', 'desc')
            ->filters([
                SelectFilter::make('pola_rotasi_id')
                    ->label('Pola Rotasi')
                    ->relationship('polaRotasi', 'nama_pola'),

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
