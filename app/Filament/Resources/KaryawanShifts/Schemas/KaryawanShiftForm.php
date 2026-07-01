<?php

namespace App\Filament\Resources\KaryawanShifts\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Schemas\Schema;

class KaryawanShiftForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('karyawan_id')
                    ->relationship('karyawan', 'id')
                    ->required(),
                Select::make('shift_id')
                    ->relationship('shift', 'id')
                    ->required(),
                DatePicker::make('tanggal_berlaku')
                    ->required(),
                DatePicker::make('tanggal_berakhir'),
            ]);
    }
}
