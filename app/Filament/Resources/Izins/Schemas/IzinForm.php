<?php

namespace App\Filament\Resources\Izins\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TimePicker;
use Filament\Schemas\Schema;

class IzinForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('karyawan_id')
                    ->relationship('karyawan', 'nama')
                    ->searchable()
                    ->preload()
                    ->required(),
                DatePicker::make('tanggal')
                    ->required(),
                TimePicker::make('jam_keluar')
                    ->required(),
                TimePicker::make('jam_kembali'),
                Textarea::make('keperluan')
                    ->required()
                    ->columnSpanFull(),
            ]);
    }
}
