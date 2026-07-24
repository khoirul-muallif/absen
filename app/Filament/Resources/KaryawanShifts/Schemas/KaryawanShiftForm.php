<?php

namespace App\Filament\Resources\KaryawanShifts\Schemas;

use App\Models\Karyawan;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Validation\Rule;

class KaryawanShiftForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Penugasan Shift')
                    ->description('Tentukan shift yang berlaku untuk karyawan ini')
                    ->icon('heroicon-o-calendar-days')
                    ->columns(2)
                    ->schema([
                        Select::make('karyawan_id')
                            ->label('Karyawan')
                            ->relationship(
                                name: 'karyawan',
                                titleAttribute: 'nama',
                                modifyQueryUsing: fn ($query) => $query->where('tipe_jadwal', Karyawan::TIPE_UMUM),
                            )
                            ->required()
                            ->searchable()
                            ->preload()
                            ->columnSpanFull()
                            ->rules([
                                Rule::exists('karyawan', 'id')
                                    ->where('tipe_jadwal', Karyawan::TIPE_UMUM),
                            ])
                            ->validationMessages([
                                'exists' => 'Karyawan yang dipilih harus bertipe jadwal Umum.',
                            ])
                            ->helperText('Untuk karyawan tipe "Umum" — shift tetap per periode. Karyawan tipe "Rotasi" pakai menu "Shift Karyawan Rotasi".'),

                        Select::make('shift_id')
                            ->label('Shift')
                            ->relationship('shift', 'nama_shift')
                            ->required()
                            ->searchable()
                            ->preload()
                            ->columnSpanFull(),

                        DatePicker::make('tanggal_berlaku')
                            ->label('Berlaku Mulai')
                            ->required()
                            ->displayFormat('d M Y')
                            ->helperText('Shift ini aktif mulai tanggal ini'),

                        DatePicker::make('tanggal_berakhir')
                            ->label('Berlaku Sampai')
                            ->displayFormat('d M Y')
                            ->helperText('Kosongkan jika berlaku sampai diganti')
                            ->after('tanggal_berlaku'),
                    ]),
            ]);
    }
}
