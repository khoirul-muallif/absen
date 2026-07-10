<?php

namespace App\Filament\Resources\Lemburs\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TimePicker;
use Filament\Schemas\Schema;

class LemburForm
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
                TimePicker::make('jam_mulai')
                    ->required(),
                TimePicker::make('jam_selesai')
                    ->required(),
                Textarea::make('alasan')
                    ->required()
                    ->columnSpanFull(),
            ]);
    }
}
