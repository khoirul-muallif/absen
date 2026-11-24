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
                            TimePicker::make('jam_kembali_rencana')
                                ->label('Rencana kembali jam')
                                ->seconds(false)
                                ->required()
                                ->after('jam_keluar')
                                ->helperText('Perkiraan yang diisi karyawan sebelum pergi.'),

                            TimePicker::make('jam_kembali_aktual')
                                ->label('Benar-benar kembali jam')
                                ->seconds(false)
                                ->helperText('Diisi otomatis saat karyawan menekan "Saya sudah kembali" di aplikasi. Isi manual hanya untuk koreksi.'),
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
