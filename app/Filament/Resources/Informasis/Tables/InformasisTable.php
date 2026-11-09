<?php

namespace App\Filament\Resources\Informasis\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class InformasisTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('tanggal_terbit')
                    ->label('Terbit')
                    ->date('d M Y')
                    ->sortable(),

                TextColumn::make('judul')
                    ->searchable()
                    ->wrap(),

                TextColumn::make('isi')
                    ->label('Cuplikan')
                    ->state(fn ($record) => $record->ringkasan)
                    ->wrap()
                    ->toggleable(),

                TextColumn::make('instansi.nama')
                    ->label('Instansi')
                    ->sortable()
                    ->toggleable(),

                IconColumn::make('is_aktif')
                    ->label('Tampil')
                    ->boolean(),

                TextColumn::make('pembuat.name')
                    ->label('Dibuat oleh')
                    ->placeholder('-')
                    ->toggleable(),

                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('tanggal_terbit', 'desc')
            ->filters([
                SelectFilter::make('instansi_id')
                    ->label('Instansi')
                    ->relationship('instansi', 'nama'),

                TernaryFilter::make('is_aktif')
                    ->label('Tampil ke karyawan'),
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
