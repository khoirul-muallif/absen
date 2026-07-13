<?php

namespace App\Filament\Resources\Shifts\Schemas;

use Filament\Schemas\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Filament\Forms\Components\CheckboxList;


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

                            Select::make('mode_toleransi')
                            ->label('Mode Toleransi')
                            ->options([
                                'harian' => 'Per Hari (reset tiap hari)',
                                'akumulasi_bulanan' => 'Akumulasi Bulanan',
                            ])
                            ->default('harian')
                            ->required()
                            ->helperText('Akumulasi bulanan: total keterlambatan dijumlah sebulan, baru dianggap pelanggaran setelah melebihi toleransi'),
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


                Section::make('Pola Hari Kerja')
                    ->icon('heroicon-o-calendar-days')
                    ->schema([
                        CheckboxList::make('hari_kerja')
                            ->label('Hari kerja')
                            ->options([
                                1 => 'Senin',
                                2 => 'Selasa',
                                3 => 'Rabu',
                                4 => 'Kamis',
                                5 => 'Jumat',
                                6 => 'Sabtu',
                                0 => 'Minggu',
                            ])
                            ->default([1, 2, 3, 4, 5])
                            ->columns(4)
                            ->helperText('Kosongkan semua jika shift berlaku tiap hari (misal shift jaga 24 jam)'),
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
