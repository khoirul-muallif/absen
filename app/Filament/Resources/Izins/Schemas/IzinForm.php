<?php

namespace App\Filament\Resources\Izins\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TimePicker;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class IzinForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Data Pengajuan')
                    ->description('Karyawan dan waktu izin')
                    ->schema([
                        Select::make('karyawan_id')
                            ->relationship('karyawan', 'nama')
                            ->searchable()
                            ->preload()
                            ->required(),
                        DatePicker::make('tanggal')
                            ->required()
                            ->native(false),
                        Grid::make(2)->schema([
                            TimePicker::make('jam_keluar')
                                ->required()
                                ->seconds(false)
                                ->live(),
                            TimePicker::make('jam_kembali')
                                ->seconds(false)
                                ->live()
                                ->rule(function (Get $get) {
                                    return function (string $attribute, $value, \Closure $fail) use ($get) {
                                        $jamKeluar = $get('jam_keluar');
                                        if (! $jamKeluar || ! $value) {
                                            return;
                                        }

                                        if (\Carbon\Carbon::parse($value)->lte(\Carbon\Carbon::parse($jamKeluar))) {
                                            $fail('Jam kembali harus setelah jam keluar.');
                                        }
                                    };
                                }),
                        ]),
                    ]),

                Section::make('Detail')
                    ->schema([
                        Textarea::make('keperluan')
                            ->required()
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
