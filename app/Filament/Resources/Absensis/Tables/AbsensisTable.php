<?php

namespace App\Filament\Resources\Absensis\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class AbsensisTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('karyawan.nama')
                    ->label('Karyawan')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('shift.nama_shift')
                    ->label('Shift')
                    ->sortable(),

                TextColumn::make('tanggal')
                    ->label('Tanggal')
                    ->date('d M Y')
                    ->sortable(),

                TextColumn::make('waktu_masuk')
                    ->label('Masuk')
                    ->dateTime('H:i')
                    ->sortable(),

                TextColumn::make('waktu_pulang')
                    ->label('Pulang')
                    ->dateTime('H:i')
                    ->sortable(),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'tepat_waktu' => 'success',
                        'terlambat'   => 'warning',
                        'alpha'       => 'danger',
                        'izin'        => 'info',
                        'sakit'       => 'warning',
                        'cuti'        => 'info',
                        'dinas'       => 'info',
                        'libur'       => 'gray',
                        default       => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'tepat_waktu' => 'Tepat Waktu',
                        'terlambat'   => 'Terlambat',
                        'alpha'       => 'Alpha',
                        'izin'        => 'Izin',
                        'sakit'       => 'Sakit',
                        'cuti'        => 'Cuti',
                        'dinas'       => 'Dinas',
                        'libur'       => 'Libur',
                        default       => $state,
                    }),

                ImageColumn::make('foto_masuk')
                    ->label('Foto Masuk')
                    ->circular()
                    ->toggleable(),

                TextColumn::make('keterangan')
                    ->label('Keterangan')
                    ->limit(30)
                    ->toggleable(),

                // Kolom tersembunyi default (bisa ditampilkan via toggle)
                TextColumn::make('latitude_masuk')
                    ->label('Lat. Masuk')
                    ->numeric(decimalPlaces: 7)
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('longitude_masuk')
                    ->label('Lng. Masuk')
                    ->numeric(decimalPlaces: 7)
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('latitude_pulang')
                    ->label('Lat. Pulang')
                    ->numeric(decimalPlaces: 7)
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('longitude_pulang')
                    ->label('Lng. Pulang')
                    ->numeric(decimalPlaces: 7)
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('created_at')
                    ->label('Dibuat')
                    ->dateTime('d M Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('tanggal', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'tepat_waktu' => 'Tepat Waktu',
                        'terlambat'   => 'Terlambat',
                        'alpha'       => 'Alpha',
                        'izin'        => 'Izin',
                        'sakit'       => 'Sakit',
                        'cuti'        => 'Cuti',
                        'dinas'       => 'Dinas',
                        'libur'       => 'Libur',
                    ]),

                SelectFilter::make('shift_id')
                    ->label('Shift')
                    ->relationship('shift', 'nama_shift'),
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
