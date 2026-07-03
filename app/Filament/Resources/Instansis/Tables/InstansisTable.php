<?php

namespace App\Filament\Resources\Instansis\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class InstansisTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('nama')
                    ->label('Nama Instansi')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('kode_instansi')
                    ->label('Kode')
                    ->searchable()
                    ->badge()
                    ->color('gray'),

                TextColumn::make('alamat')
                    ->label('Alamat')
                    ->searchable()
                    ->limit(40)
                    ->toggleable(),

                TextColumn::make('telepon')
                    ->label('Telepon')
                    ->searchable()
                    ->toggleable(),

                TextColumn::make('radius_meter')
                    ->label('Radius (m)')
                    ->numeric()
                    ->sortable()
                    ->suffix(' m'),

                TextColumn::make('latitude')
                    ->label('Latitude')
                    ->numeric(decimalPlaces: 7)
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('longitude')
                    ->label('Longitude')
                    ->numeric(decimalPlaces: 7)
                    ->toggleable(isToggledHiddenByDefault: true),

                IconColumn::make('is_active')
                    ->label('Aktif')
                    ->boolean()
                    ->trueColor('success')
                    ->falseColor('danger'),

                TextColumn::make('created_at')
                    ->label('Dibuat')
                    ->dateTime('d M Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TernaryFilter::make('is_active')
                    ->label('Status Aktif')
                    ->trueLabel('Aktif')
                    ->falseLabel('Tidak Aktif'),
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
