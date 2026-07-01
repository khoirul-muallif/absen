<?php

namespace App\Filament\Resources\QrInstansis\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class QrInstansiForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('instansi_id')
                    ->relationship('instansi', 'id')
                    ->required(),
                TextInput::make('kode_qr')
                    ->required(),
                Toggle::make('is_active')
                    ->required(),
                DateTimePicker::make('expired_at'),
            ]);
    }
}
