<?php

namespace App\Filament\Resources\Dinas\Schemas;

use App\Models\Cuti;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class DinasForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('karyawan_id')
                    ->relationship('karyawan', 'nama')
                    ->searchable()
                    ->preload()
                    ->live()
                    ->required(),
                DatePicker::make('tanggal_mulai')
                    ->required()
                    ->live(),
                DatePicker::make('tanggal_selesai')
                    ->required()
                    ->live()
                    ->afterOrEqual('tanggal_mulai')
                    ->rule(function (Get $get) {
                        return function (string $attribute, $value, \Closure $fail) use ($get) {
                            $mulai = $get('tanggal_mulai');
                            if (! $mulai || ! $value) {
                                return;
                            }

                            $tanggalMulai = \Carbon\Carbon::parse($mulai);
                            $tanggalSelesai = \Carbon\Carbon::parse($value);

                            if ($tanggalMulai->year !== $tanggalSelesai->year) {
                                $fail('Rentang dinas tidak boleh melintasi pergantian tahun. Buat pengajuan terpisah untuk masing-masing tahun.');
                                return;
                            }

                            $karyawanId = $get('karyawan_id');
                            if (! $karyawanId) {
                                return;
                            }

                            $bentrok = Cuti::where('karyawan_id', $karyawanId)
                                ->where('status', 'approved')
                                ->where('tanggal_mulai', '<=', $tanggalSelesai)
                                ->where('tanggal_selesai', '>=', $tanggalMulai)
                                ->exists();

                            if ($bentrok) {
                                $fail('Karyawan ini sudah tercatat cuti (disetujui) yang bentrok dengan rentang tanggal ini.');
                            }
                        };
                    }),
                TextInput::make('tujuan')
                    ->required(),
                Textarea::make('keperluan')
                    ->required()
                    ->columnSpanFull(),
            ]);
    }
}
