<?php

namespace App\Filament\Resources\Lemburs\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TimePicker;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class LemburForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Data Pengajuan')
                    ->description('Karyawan dan waktu lembur')
                    ->schema([
                        Select::make('karyawan_id')
                            ->relationship('karyawan', 'nama')
                            ->searchable()
                            ->preload()
                            ->required(),
                        DatePicker::make('tanggal')
                            ->required(),
                        Grid::make(2)->schema([
                            TimePicker::make('jam_mulai')
                                ->required()
                                ->seconds(false)
                                ->live(),
                            TimePicker::make('jam_selesai')
                                ->required()
                                ->seconds(false)
                                ->live()
                                ->helperText('Kalau lembur lewat tengah malam, jam selesai boleh lebih kecil dari jam mulai (mis. mulai 22:00, selesai 02:00) — dianggap 1 pengajuan yang sama, bukan 2 hari terpisah.')
                                ->rule(function (Get $get) {
                                    return function (string $attribute, $value, \Closure $fail) use ($get) {
                                        $jamMulai = $get('jam_mulai');
                                        if (! $jamMulai || ! $value) {
                                            return;
                                        }

                                        if (\Carbon\Carbon::parse($value)->equalTo(\Carbon\Carbon::parse($jamMulai))) {
                                            $fail('Jam selesai tidak boleh sama dengan jam mulai (durasi lembur nol).');
                                        }
                                    };
                                }),
                        ]),
                    ]),

                Section::make('Detail')
                    ->schema([
                        Textarea::make('alasan')
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
