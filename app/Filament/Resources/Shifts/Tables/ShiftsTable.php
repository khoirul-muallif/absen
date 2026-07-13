<?php

namespace App\Filament\Resources\Shifts\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class ShiftsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('nama_shift')
                    ->label('Nama Shift')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('instansi.nama')
                    ->label('Instansi')
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('jam_masuk')
                    ->label('Jam Masuk')
                    ->time('H:i')
                    ->sortable(),

                TextColumn::make('jam_pulang')
                    ->label('Jam Pulang')
                    ->time('H:i')
                    ->sortable(),

                TextColumn::make('toleransi_menit')
                    ->label('Toleransi')
                    ->numeric()
                    ->sortable()
                    ->suffix(' menit')
                    ->badge()
                    ->color('warning'),

                TextColumn::make('mode_toleransi')
                    ->label('Mode Toleransi')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => $state === 'akumulasi_bulanan' ? 'Akumulasi' : 'Harian')
                    ->color(fn (string $state) => $state === 'akumulasi_bulanan' ? 'info' : 'gray')
                    ->toggleable(),

                TextColumn::make('hari_kerja')
                    ->label('Hari Kerja')
                    ->state(function ($record): string {
                        $hariKerja = $record->hari_kerja;

                        if (empty($hariKerja) || ! is_array($hariKerja)) {
                            return 'Setiap hari';
                        }

                        $nama = [0 => 'Min', 1 => 'Sen', 2 => 'Sel', 3 => 'Rab', 4 => 'Kam', 5 => 'Jum', 6 => 'Sab'];
                        sort($hariKerja);

                        return collect($hariKerja)->map(fn ($hari) => $nama[$hari] ?? '?')->join(', ');
                    })
                    ->toggleable(),

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
            ->defaultSort('nama_shift')
            ->filters([
                SelectFilter::make('instansi_id')
                    ->label('Instansi')
                    ->relationship('instansi', 'nama'),

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
