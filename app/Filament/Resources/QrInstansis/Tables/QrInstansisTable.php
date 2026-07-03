<?php

namespace App\Filament\Resources\QrInstansis\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class QrInstansisTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('instansi.nama')
                    ->label('Instansi')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('kode_qr')
                    ->label('Kode QR')
                    ->searchable()
                    ->limit(20)
                    ->tooltip(fn ($record) => $record->kode_qr)
                    ->badge()
                    ->color('gray')
                    ->copyable()
                    ->copyMessage('Kode QR disalin!')
                    ->copyMessageDuration(1500),

                IconColumn::make('is_active')
                    ->label('Aktif')
                    ->boolean()
                    ->trueColor('success')
                    ->falseColor('danger'),

                TextColumn::make('expired_at')
                    ->label('Kadaluarsa')
                    ->dateTime('d M Y H:i')
                    ->sortable()
                    ->placeholder('Tidak ada (permanen)')
                    ->color(fn ($record) => $record->expired_at?->isPast() ? 'danger' : 'success'),

                TextColumn::make('created_at')
                    ->label('Dibuat')
                    ->dateTime('d M Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('instansi_id')
                    ->label('Instansi')
                    ->relationship('instansi', 'nama'),

                TernaryFilter::make('is_active')
                    ->label('Status QR')
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
