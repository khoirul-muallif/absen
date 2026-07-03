<?php

namespace App\Filament\Resources\Shifts\Schemas;

use Filament\Schemas\Components\Section;
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
                Section::make('Informasi Shift')
                    ->description('Jadwal jam kerja untuk shift ini')
                    ->icon('heroicon-o-clock')
                    ->columns(2)
                    ->schema([
                        Select::make('instansi_id')
                            ->label('Instansi')
                            ->relationship('instansi', 'nama')
                            ->required()
                            ->searchable()
                            ->preload()
                            ->columnSpanFull(),

                        TextInput::make('nama_shift')
                            ->label('Nama Shift')
                            ->placeholder('Pagi, Siang, Malam...')
                            ->required()
                            ->maxLength(255),

                        TextInput::make('toleransi_menit')
                            ->label('Toleransi Keterlambatan')
                            ->required()
                            ->numeric()
                            ->default(15)
                            ->minValue(0)
                            ->maxValue(120)
                            ->suffix('menit')
                            ->helperText('Karyawan masih dianggap tepat waktu dalam batas ini'),
                    ]),

                Section::make('Jam Kerja')
                    ->icon('heroicon-o-arrow-right-circle')
                    ->columns(2)
                    ->schema([
                        TimePicker::make('jam_masuk')
                            ->label('Jam Masuk')
                            ->required()
                            ->seconds(false),

                        TimePicker::make('jam_pulang')
                            ->label('Jam Pulang')
                            ->required()
                            ->seconds(false),
                    ]),

                Section::make('Status')
                    ->schema([
                        Toggle::make('is_active')
                            ->label('Shift Aktif')
                            ->default(true)
                            ->helperText('Nonaktifkan jika shift ini sudah tidak digunakan'),
                    ]),
            ]);
    }
}
