<?php

namespace App\Filament\Resources\HariLiburs\Schemas;

use App\Models\Instansi;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class HariLiburForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('instansi_id')
                    ->relationship('instansi', 'nama')
                    ->searchable()
                    ->preload()
                    ->default(fn () => Instansi::first()?->id)
                    ->required(),
                DatePicker::make('tanggal')
                    ->required()
                    ->unique(
                        table: 'hari_liburs',
                        column: 'tanggal',
                        ignoreRecord: true,
                    )
                    ->validationMessages([
                        'unique' => 'Tanggal ini sudah terdaftar sebagai hari libur.',
                    ]),
                TextInput::make('nama')
                    ->required()
                    ->placeholder('Contoh: Idul Fitri 1447 H, Hari Kemerdekaan'),
                Textarea::make('keterangan')
                    ->columnSpanFull(),
                Toggle::make('is_cuti_bersama')
                    ->label('Cuti bersama')
                    ->helperText('Aktifkan jika ini termasuk cuti bersama pemerintah, bukan hari libur resmi'),
            ]);
    }
}
