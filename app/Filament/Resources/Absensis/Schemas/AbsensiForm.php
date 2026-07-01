<?php

namespace App\Filament\Resources\Absensis\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class AbsensiForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('karyawan_id')
                    ->relationship('karyawan', 'id')
                    ->required(),
                Select::make('shift_id')
                    ->relationship('shift', 'id')
                    ->required(),
                Select::make('qr_instansi_id')
                    ->relationship('qrInstansi', 'id')
                    ->required(),
                DatePicker::make('tanggal')
                    ->required(),
                DateTimePicker::make('waktu_masuk'),
                TextInput::make('latitude_masuk')
                    ->numeric(),
                TextInput::make('longitude_masuk')
                    ->numeric(),
                TextInput::make('foto_masuk'),
                DateTimePicker::make('waktu_pulang'),
                TextInput::make('latitude_pulang')
                    ->numeric(),
                TextInput::make('longitude_pulang')
                    ->numeric(),
                TextInput::make('foto_pulang'),
                Select::make('status')
                    ->options([
            'tepat_waktu' => 'Tepat waktu',
            'terlambat' => 'Terlambat',
            'alpha' => 'Alpha',
            'izin' => 'Izin',
            'sakit' => 'Sakit',
            'cuti' => 'Cuti',
            'dinas' => 'Dinas',
            'libur' => 'Libur',
        ])
                    ->default('alpha')
                    ->required(),
                TextInput::make('keterangan'),
            ]);
    }
}
