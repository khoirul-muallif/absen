<?php

namespace App\Filament\Resources\Shifts\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class ShiftForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('instansi_id')
                    ->relationship('instansi', 'id')
                    ->required(),
                TextInput::make('nama_shift')
                    ->required(),
                TimePicker::make('jam_masuk')
                    ->required(),
                TimePicker::make('jam_pulang')
                    ->required(),
                TextInput::make('toleransi_menit')
                    ->required()
                    ->numeric()
                    ->default(15),
                Toggle::make('is_active')
                    ->required(),
            ]);
    }
}
