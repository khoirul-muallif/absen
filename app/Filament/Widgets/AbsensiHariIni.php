<?php

namespace App\Filament\Widgets;

use App\Models\Absensi;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class AbsensiHariIni extends BaseWidget
{
    protected static ?string $heading = 'Absensi Hari Ini';
    protected static ?int $sort = 3;
    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Absensi::query()
                    ->with(['karyawan', 'shift'])
                    ->whereDate('tanggal', today())
                    ->latest('waktu_masuk')
            )
            ->columns([
                TextColumn::make('karyawan.nama')
                    ->label('Karyawan')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('karyawan.unit_kerja')
                    ->label('Unit')
                    ->badge()
                    ->color('info'),

                TextColumn::make('shift.nama_shift')
                    ->label('Shift'),

                TextColumn::make('waktu_masuk')
                    ->label('Masuk')
                    ->dateTime('H:i')
                    ->sortable(),

                TextColumn::make('waktu_pulang')
                    ->label('Pulang')
                    ->dateTime('H:i')
                    ->placeholder('—'),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'tepat_waktu' => 'success',
                        'terlambat'   => 'warning',
                        'alpha'       => 'danger',
                        'izin'        => 'info',
                        'sakit'       => 'warning',
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
            ])
            ->paginated([10, 25, 50])
            ->defaultPaginationPageOption(10);
    }
}
