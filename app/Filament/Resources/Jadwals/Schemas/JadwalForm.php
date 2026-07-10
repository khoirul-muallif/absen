<?php

namespace App\Filament\Resources\Jadwals\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Validation\Rules\Unique;

class JadwalForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('karyawan_id')
                    ->relationship('karyawan', 'nama')
                    ->searchable()
                    ->preload()
                    ->live()
                    ->required(),
                Select::make('shift_id')
                    ->relationship('shift', 'nama_shift')
                    ->searchable()
                    ->preload()
                    ->required(),
                DatePicker::make('tanggal')
                    ->required()
                    ->unique(
                        table: 'jadwals',
                        column: 'tanggal',
                        ignoreRecord: true,
                        modifyRuleUsing: fn (Unique $rule, Get $get) => $rule->where('karyawan_id', $get('karyawan_id')),
                    )
                    ->validationMessages([
                        'unique' => 'Karyawan ini sudah punya jadwal di tanggal tersebut.',
                    ]),
                Select::make('jenis')
                    ->options([
                        'reguler' => 'Reguler',
                        'piket' => 'Piket',
                    ])
                    ->default('reguler')
                    ->required(),
                Textarea::make('keterangan')
                    ->columnSpanFull(),
            ]);
    }
}
