<?php

namespace App\Filament\Resources\KuotaCutis\Schemas;

use App\Models\JenisCuti;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Utilities\Get;

class KuotaCutiForm
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
                Select::make('jenis_cuti_id')
                    ->relationship('jenisCuti', 'nama')
                    ->searchable()
                    ->preload()
                    ->live()
                    ->required()
                    ->afterStateUpdated(function ($state, Set $set) {
                        if ($state) {
                            $defaultKuota = JenisCuti::find($state)?->default_kuota;
                            $set('kuota', $defaultKuota ?? 0);
                        }
                    }),
                TextInput::make('tahun')
                    ->required()
                    ->numeric()
                    ->default(now()->year)
                    ->minValue(2020)
                    ->maxValue(2100)
                    ->live()
                    ->rule(function (Get $get, $record) {
                        return function (string $attribute, $value, \Closure $fail) use ($get, $record) {
                            $karyawanId = $get('karyawan_id');
                            $jenisCutiId = $get('jenis_cuti_id');
                            if (! $karyawanId || ! $jenisCutiId || ! $value) {
                                return;
                            }

                            $sudahAda = \App\Models\KuotaCuti::where('karyawan_id', $karyawanId)
                                ->where('jenis_cuti_id', $jenisCutiId)
                                ->where('tahun', $value)
                                ->when($record, fn ($q) => $q->where('id', '!=', $record->id))
                                ->exists();

                            if ($sudahAda) {
                                $fail('Kuota untuk karyawan, jenis cuti, dan tahun ini sudah ada. Edit data yang sudah ada, jangan buat duplikat.');
                            }
                        };
                    }),
                TextInput::make('kuota')
                    ->required()
                    ->numeric()
                    ->helperText('Otomatis terisi dari default kuota jenis cuti, bisa diubah manual'),
                TextInput::make('terpakai')
                    ->required()
                    ->numeric()
                    ->default(0)
                    ->live(onBlur: true)
                    ->helperText(function (Get $get) {
                        $kuota = (int) ($get('kuota') ?? 0);
                        $terpakai = (int) ($get('terpakai') ?? 0);

                        if ($terpakai > $kuota) {
                            return "⚠ Melebihi kuota ({$kuota}) — sisa akan menjadi negatif.";
                        }

                        return 'Biasanya otomatis bertambah saat cuti disetujui, edit manual hanya untuk koreksi.';
                    }),
            ]);
    }
}
