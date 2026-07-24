<?php

namespace App\Filament\Resources\KaryawanPolaRotasis\Schemas;

use App\Models\Karyawan;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Validation\Rule;

class KaryawanPolaRotasiForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Assignment Pola Rotasi')
                    ->description('Assign karyawan rotasi ke pola, dengan tanggal_mulai sebagai anchor posisi siklus (bisa beda antar karyawan biar shift ke-cover / staggered)')
                    ->icon('heroicon-o-calendar-date-range')
                    ->columns(2)
                    ->schema([
                        Select::make('karyawan_id')
                            ->label('Karyawan')
                            ->relationship(
                                name: 'karyawan',
                                titleAttribute: 'nama',
                                modifyQueryUsing: fn ($query) => $query->where('tipe_jadwal', Karyawan::TIPE_ROTASI),
                            )
                            ->required()
                            ->searchable()
                            ->preload()
                            ->columnSpanFull()
                            ->rules([
                                Rule::exists('karyawan', 'id')
                                    ->where('tipe_jadwal', Karyawan::TIPE_ROTASI),
                            ])
                            ->validationMessages([
                                'exists' => 'Karyawan yang dipilih harus bertipe jadwal Rotasi.',
                            ])
                            ->helperText('Untuk karyawan tipe "Rotasi" — pakai pola siklus, bukan shift tetap. Karyawan tipe "Umum" pakai menu "Shift Karyawan Umum".'),

                        Select::make('pola_rotasi_id')
                            ->label('Pola Rotasi')
                            ->relationship('polaRotasi', 'nama_pola')
                            ->required()
                            ->searchable()
                            ->preload()
                            ->columnSpanFull(),

                        DatePicker::make('tanggal_mulai')
                            ->label('Tanggal Mulai (Anchor Siklus)')
                            ->required()
                            ->displayFormat('d M Y')
                            ->helperText('Posisi hari ke-1 di siklus dimulai dari tanggal ini'),

                        DatePicker::make('tanggal_berakhir')
                            ->label('Berlaku Sampai')
                            ->displayFormat('d M Y')
                            ->helperText('Kosongkan jika berlaku sampai diganti')
                            ->after('tanggal_mulai'),
                    ]),
            ]);
    }
}
