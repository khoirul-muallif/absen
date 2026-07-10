<?php

namespace App\Filament\Resources\KuotaCutis\Schemas;

use App\Models\JenisCuti;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

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
                    ->maxValue(2100),
                TextInput::make('kuota')
                    ->required()
                    ->numeric()
                    ->helperText('Otomatis terisi dari default kuota jenis cuti, bisa diubah manual'),
                TextInput::make('terpakai')
                    ->required()
                    ->numeric()
                    ->default(0)
                    ->helperText('Biasanya otomatis bertambah saat cuti disetujui, edit manual hanya untuk koreksi'),
            ]);
    }
}
