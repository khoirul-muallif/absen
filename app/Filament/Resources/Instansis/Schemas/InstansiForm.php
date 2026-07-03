<?php

namespace App\Filament\Resources\Instansis\Schemas;

use Filament\Schemas\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class InstansiForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Informasi Instansi')
                    ->description('Data utama instansi / rumah sakit')
                    ->icon('heroicon-o-building-office-2')
                    ->columns(2)
                    ->schema([
                        TextInput::make('nama')
                            ->label('Nama Instansi')
                            ->placeholder('RSU Banyumanik 2')
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),

                        TextInput::make('kode_instansi')
                            ->label('Kode Instansi')
                            ->placeholder('RSUB2')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(50)
                            ->helperText('Kode unik untuk identifikasi instansi (tidak bisa diubah setelah dipakai QR)'),

                        TextInput::make('telepon')
                            ->label('Nomor Telepon')
                            ->placeholder('024-xxxxxxx')
                            ->tel()
                            ->maxLength(20),

                        Textarea::make('alamat')
                            ->label('Alamat Lengkap')
                            ->placeholder('Jl. ..., Semarang')
                            ->rows(2)
                            ->columnSpanFull(),
                    ]),

                Section::make('Lokasi & Validasi GPS')
                    ->description('Koordinat pusat instansi untuk validasi kehadiran karyawan')
                    ->icon('heroicon-o-map-pin')
                    ->columns(3)
                    ->schema([
                        TextInput::make('latitude')
                            ->label('Latitude')
                            ->placeholder('-7.0333')
                            ->required()
                            ->numeric()
                            ->step(0.0000001)
                            ->helperText('Contoh: -7.0333'),

                        TextInput::make('longitude')
                            ->label('Longitude')
                            ->placeholder('110.4167')
                            ->required()
                            ->numeric()
                            ->step(0.0000001)
                            ->helperText('Contoh: 110.4167'),

                        TextInput::make('radius_meter')
                            ->label('Radius Validasi (meter)')
                            ->required()
                            ->numeric()
                            ->default(100)
                            ->minValue(10)
                            ->maxValue(5000)
                            ->suffix('m')
                            ->helperText('Karyawan harus berada dalam radius ini untuk absen'),
                    ]),

                Section::make('Status')
                    ->schema([
                        Toggle::make('is_active')
                            ->label('Instansi Aktif')
                            ->helperText('Nonaktifkan jika instansi tidak lagi digunakan')
                            ->default(true),
                    ]),
            ]);
    }
}
