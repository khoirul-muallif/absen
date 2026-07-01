<?php

namespace App\Filament\Resources\Instansis\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class InstansiForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('nama')
                    ->required(),
                TextInput::make('kode_instansi')
                    ->required(),
                TextInput::make('latitude')
                    ->required()
                    ->numeric(),
                TextInput::make('longitude')
                    ->required()
                    ->numeric(),
                TextInput::make('radius_meter')
                    ->required()
                    ->numeric()
                    ->default(100),
                TextInput::make('alamat'),
                TextInput::make('telepon')
                    ->tel(),
                Toggle::make('is_active')
                    ->required(),
            ]);
    }
}
