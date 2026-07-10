<?php

namespace App\Filament\Resources\JenisCutis\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class JenisCutisTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('nama')
                    ->searchable()
                    ->sortable()
                    ->weight('medium'),
                TextColumn::make('default_kuota')
                    ->label('Default kuota')
                    ->numeric()
                    ->suffix(' hari')
                    ->sortable(),
                IconColumn::make('is_tahunan')
                    ->label('Tahunan')
                    ->boolean(),
                IconColumn::make('potong_kuota')
                    ->label('Potong kuota')
                    ->boolean(),
                IconColumn::make('perlu_lampiran')
                    ->label('Wajib lampiran')
                    ->boolean(),
                IconColumn::make('is_active')
                    ->label('Aktif')
                    ->boolean(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('nama')
            ->filters([
                TernaryFilter::make('is_active')
                    ->label('Status aktif'),
                TernaryFilter::make('is_tahunan')
                    ->label('Kuota tahunan'),
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
