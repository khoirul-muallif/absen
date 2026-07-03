<?php

namespace App\Filament\Resources\Karyawans\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class KaryawansTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('foto_profil')
                    ->label('')
                    ->circular()
                    ->defaultImageUrl(fn () => 'https://ui-avatars.com/api/?name=K&background=1D9E75&color=fff'),

                TextColumn::make('nama')
                    ->label('Nama')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('nip')
                    ->label('NIP')
                    ->searchable()
                    ->badge()
                    ->color('gray'),

                TextColumn::make('instansi.nama')
                    ->label('Instansi')
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('unit_kerja')
                    ->label('Unit Kerja')
                    ->searchable()
                    ->badge()
                    ->color('info'),

                TextColumn::make('jabatan')
                    ->label('Jabatan')
                    ->searchable()
                    ->toggleable(),

                TextColumn::make('status_pegawai')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'tetap'     => 'success',
                        'kontrak'   => 'warning',
                        'orientasi' => 'info',
                        'magang'    => 'gray',
                        default     => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'tetap'     => 'Tetap',
                        'kontrak'   => 'Kontrak',
                        'orientasi' => 'Orientasi',
                        'magang'    => 'Magang',
                        default     => $state,
                    }),

                TextColumn::make('role')
                    ->label('Role')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'admin'    => 'danger',
                        'karyawan' => 'primary',
                        default    => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => ucfirst($state)),

                TextColumn::make('email')
                    ->label('Email')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('nomor_telepon')
                    ->label('Telepon')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('tanggal_bergabung')
                    ->label('Bergabung')
                    ->date('d M Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                IconColumn::make('is_active')
                    ->label('Aktif')
                    ->boolean()
                    ->trueColor('success')
                    ->falseColor('danger'),
            ])
            ->defaultSort('nama')
            ->filters([
                SelectFilter::make('instansi_id')
                    ->label('Instansi')
                    ->relationship('instansi', 'nama'),

                SelectFilter::make('status_pegawai')
                    ->label('Status Pegawai')
                    ->options([
                        'tetap'     => 'Tetap',
                        'kontrak'   => 'Kontrak',
                        'orientasi' => 'Orientasi',
                        'magang'    => 'Magang',
                    ]),

                SelectFilter::make('role')
                    ->label('Role')
                    ->options([
                        'admin'    => 'Admin',
                        'karyawan' => 'Karyawan',
                    ]),

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
