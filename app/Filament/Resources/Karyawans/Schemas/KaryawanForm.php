<?php

namespace App\Filament\Resources\Karyawans\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class KaryawanForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('instansi_id')
                    ->relationship('instansi', 'id')
                    ->required(),
                TextInput::make('nip')
                    ->required(),
                TextInput::make('nama')
                    ->required(),
                TextInput::make('email')
                    ->label('Email address')
                    ->email()
                    ->required(),
                TextInput::make('password')
                    ->password()
                    ->required(),
                TextInput::make('nomor_telepon')
                    ->tel(),
                TextInput::make('foto_profil'),
                TextInput::make('foto_wajah'),
                Select::make('status_pegawai')
                    ->options(['tetap' => 'Tetap', 'kontrak' => 'Kontrak', 'orientasi' => 'Orientasi', 'magang' => 'Magang'])
                    ->default('orientasi')
                    ->required(),
                Select::make('role')
                    ->options(['admin' => 'Admin', 'karyawan' => 'Karyawan'])
                    ->default('karyawan')
                    ->required(),
                TextInput::make('unit_kerja'),
                TextInput::make('jabatan'),
                DatePicker::make('tanggal_bergabung'),
                Toggle::make('is_active')
                    ->required(),
            ]);
    }
}
