<?php

namespace App\Filament\Resources\KaryawanShifts\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Schemas\Schema;

class KaryawanShiftForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Penugasan Shift')
                    ->description('Tentukan shift yang berlaku untuk karyawan ini')
                    ->icon('heroicon-o-calendar-days')
                    ->columns(2)
                    ->schema([
                        Select::make('karyawan_id')
                            ->label('Karyawan')
                            ->relationship('karyawan', 'nama')
                            ->required()
                            ->searchable()
                            ->preload()
                            ->columnSpanFull(),

                        Select::make('shift_id')
                            ->label('Shift')
                            ->relationship('shift', 'nama_shift')
                            ->required()
                            ->searchable()
                            ->preload()
                            ->columnSpanFull(),

                        DatePicker::make('tanggal_berlaku')
                            ->label('Berlaku Mulai')
                            ->required()
                            ->displayFormat('d M Y')
                            ->helperText('Shift ini aktif mulai tanggal ini'),

                        DatePicker::make('tanggal_berakhir')
                            ->label('Berlaku Sampai')
                            ->displayFormat('d M Y')
                            ->helperText('Kosongkan jika berlaku sampai diganti')
                            ->after('tanggal_berlaku'),
                    ]),
            ]);
    }
}
