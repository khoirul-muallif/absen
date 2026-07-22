<?php

namespace App\Filament\Resources\PolaRotasis\Tables;

use App\Models\PolaRotasi;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class PolaRotasisTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('unit_kerja')
                    ->label('Unit Kerja')
                    ->badge()
                    ->color('info')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('nama_pola')
                    ->label('Nama Pola')
                    ->searchable()
                    ->weight('bold'),

                TextColumn::make('langkah')
                    ->label('Panjang Siklus')
                    ->state(fn ($record) => count($record->langkah) . ' hari')
                    ->badge(),

                IconColumn::make('berlaku_saat_libur_nasional')
                    ->label('Tetap Saat Libur Nasional')
                    ->boolean(),

                IconColumn::make('is_active')
                    ->label('Aktif')
                    ->boolean(),

                TextColumn::make('created_at')
                    ->label('Dibuat')
                    ->dateTime('d M Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('unit_kerja')
            ->filters([
                SelectFilter::make('unit_kerja')
                    ->label('Unit Kerja')
                    ->options(fn () => PolaRotasi::query()->distinct()->pluck('unit_kerja', 'unit_kerja')),

                TernaryFilter::make('is_active')
                    ->label('Status Aktif'),
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
